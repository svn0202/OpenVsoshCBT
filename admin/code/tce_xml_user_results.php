<?php

//============================================================+
// File name   : tce_xml_user_results.php
// Begin       : 2008-12-26
// Last Update : 2023-11-30
//
// Description : Export all user's results in XML or JSON format.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Export all user's results in XML or JSON format.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2008-12-26
 * @param $_REQUEST['user_id'] (int) user ID
 * @param $_REQUEST['startdate'] (int) start date
 * @param $_REQUEST['enddate'] (int) end date
 * @param $_REQUEST['orderfield'] (string) ORDER BY portion of SQL selection query
 */

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_test.php';
require_once '../../shared/code/tce_functions_test_stats.php';
require_once '../code/tce_functions_statistics.php';
require_once 'tce_functions_user_select.php';

$requested_user_id = $_REQUEST['user_id'] ?? null;
if ($requested_user_id !== null && f_tce_xml_user_results_is_positive($requested_user_id)) {
    $user_id = (int) $requested_user_id;
    if (!f_is_authorized_editor_for_user($user_id)) {
        exit();
    }
} else {
    exit();
}

$requested_startdate = $_REQUEST['startdate'] ?? null;
if ($requested_startdate !== null && f_tce_xml_user_results_is_positive($requested_startdate)) {
    $startdate = urldecode(f_tce_xml_user_results_string($requested_startdate));
    $startdate_time = (int) strtotime($startdate);
    $startdate = date(K_TIMESTAMP_FORMAT, $startdate_time);
} else {
    $startdate = date('Y') . '-01-01 00:00:00';
}

$requested_enddate = $_REQUEST['enddate'] ?? null;
if ($requested_enddate !== null && f_tce_xml_user_results_is_positive($requested_enddate)) {
    $enddate = urldecode(f_tce_xml_user_results_string($requested_enddate));
    $enddate_time = (int) strtotime($enddate);
    $enddate = date(K_TIMESTAMP_FORMAT, $enddate_time);
} else {
    $enddate = date('Y') . '-01-01 00:00:00';
}

$requested_order_field = $_REQUEST['order_field'] ?? null;
if (is_string($requested_order_field) && in_array($requested_order_field, ['testuser_creation_time', 'total_score'], true)) {
    $order_field = $requested_order_field;
} else {
    $order_field = 'testuser_creation_time';
}

$output_format = isset($_REQUEST['format'])
    ? strtoupper(f_tce_xml_user_results_string($_REQUEST['format']))
    : 'XML';
$out_filename = 'tcexam_user_results_' . $user_id . '_' . date('YmdHis');
$xml = F_xml_export_user_results($user_id, $startdate, $enddate, $order_field);

switch ($output_format) {
    case 'JSON':
        header('Content-Description: JSON File Transfer');
        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Thu, 04 Jan 1973 00:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        // force download dialog
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream', false);
        header('Content-Type: application/download', false);
        header('Content-Type: application/json', false);
        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename=' . $out_filename . '.json;');
        header('Content-Transfer-Encoding: binary');
        $xmlobj = new SimpleXMLElement($xml);
        echo json_encode($xmlobj, JSON_THROW_ON_ERROR);
        break;

    case 'XML':
    default:
        header('Content-Description: XML File Transfer');
        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Thu, 04 Jan 1973 00:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        // force download dialog
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream', false);
        header('Content-Type: application/download', false);
        header('Content-Type: application/xml', false);
        // use the Content-Disposition header to supply a recommended filename
        header('Content-Disposition: attachment; filename=' . $out_filename . '.xml;');
        header('Content-Transfer-Encoding: binary');
        echo $xml;
        break;
}

/**
 * Export user results in XML format.
 * @param $user_id (int) user ID - if greater than zero, filter stats for the specified user.
 * @param $startdate (string) start date ID - if greater than zero, filter stats for the specified starting date
 * @param $enddate (string) end date ID - if greater than zero, filter stats for the specified ending date
 * @param $order_field (string) Ordering fields for SQL query.
 * @author Nicola Asuni
 * @return string XML data
 */
function f_xml_export_user_results(int $user_id, string $startdate, string $enddate, string $order_field): string
{
    global $l, $db;
    /** @var array{w_locked:string,w_unlocked:string} $l */
    /** @var mixed $db */
    require_once '../config/tce_config.php';

    /** @var array{session_user_level:int|string,session_user_id:int|string} $session */
    $session = $_SESSION;

    // define symbols for answers list
    $qtype = ['S', 'M', 'T', 'O', 'C']; // question types
    $type = ['single', 'multiple', 'text', 'ordering', 'matching'];
    $boolean = ['false', 'true'];
    $allowed_order_fields = ['testuser_creation_time', 'total_score'];
    if (!in_array($order_field, $allowed_order_fields, true)) {
        $order_field = 'testuser_creation_time';
    }

    $xml = ''; // XML data to be returned

    $xml .= '<?xml version="1.0" encoding="UTF-8" ?>' . K_NEWLINE;
    $xml .= '<tcexamuserresults version="' . f_tce_xml_user_results_string(K_TCEXAM_VERSION) . '">' . K_NEWLINE;
    $xml .= K_TAB . '<header';
    $xml .= ' lang="' . f_tce_xml_user_results_string(K_USER_LANG) . '"';
    $xml .= ' date="' . date(K_TIMESTAMP_FORMAT) . '">' . K_NEWLINE;
    $xml .= K_TAB . K_TAB . '<user_id>' . $user_id . '</user_id>' . K_NEWLINE;
    $sql = 'SELECT user_name, user_lastname, user_firstname FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id . '';
    $r = f_tce_xml_user_results_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tce_xml_user_results_row(F_db_fetch_array($r));
        if ($m) {
            /** @var array{user_name:string,user_lastname:string,user_firstname:string} $m */
            $xml .= K_TAB . K_TAB . '<user_name>' . $m['user_name'] . '</user_name>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . '<user_lastname>' . $m['user_lastname'] . '</user_lastname>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . '<user_firstname>' . $m['user_firstname'] . '</user_firstname>' . K_NEWLINE;
        }
    } else {
        F_display_db_error();
    }

    $xml .= K_TAB . K_TAB . '<date_from>' . $startdate . '</date_from>' . K_NEWLINE;
    $xml .= K_TAB . K_TAB . '<date_to>' . $enddate . '</date_to>' . K_NEWLINE;
    $xml .= K_TAB . '</header>' . K_NEWLINE;
    $xml .= K_TAB . '<body>' . K_NEWLINE;

    $statsdata = [];
    $statsdata['score'] = [];
    $statsdata['right'] = [];
    $statsdata['wrong'] = [];
    $statsdata['unanswered'] = [];
    $statsdata['undisplayed'] = [];
    $statsdata['unrated'] = [];

    $sql =
        'SELECT
			testuser_id,
			test_id,
			test_name,
			testuser_creation_time,
			testuser_status,
			SUM(testlog_score) AS total_score,
			MAX(testlog_change_time) AS testuser_end_time
		FROM '
        . K_TABLE_TESTS_LOGS
        . ', '
        . K_TABLE_TEST_USER
        . ', '
        . K_TABLE_TESTS
        . '
		WHERE testuser_status>0
			AND testuser_creation_time>=\''
        . f_tce_xml_user_results_string(F_escape_sql($db, $startdate))
        . '\'
			AND testuser_creation_time<=\''
        . f_tce_xml_user_results_string(F_escape_sql($db, $enddate))
        . '\'
			AND testuser_user_id='
        . $user_id
        . '
			AND testlog_testuser_id=testuser_id
			AND testuser_test_id=test_id';
    if ((int) $session['session_user_level'] < f_tce_xml_user_results_int(K_AUTH_ADMINISTRATOR)) {
        $sql .= ' AND test_user_id IN (' . f_get_authorized_users((int) $session['session_user_id']) . ')';
    }

    $sql .=
        ' GROUP BY testuser_id, test_id, test_name, testuser_creation_time, testuser_status ORDER BY '
        . $order_field
        . '';
    $r = f_tce_xml_user_results_query_result(F_db_query($sql, $db));
    if ($r) {
        $passed = 0;
        while ($m = f_tce_xml_user_results_row(F_db_fetch_array($r))) {
            /**
             * @var array{
             *     test_id:int|string,test_name:string,testuser_creation_time:string,testuser_end_time:string,
             *     testuser_status:int|string,total_score:int|float|numeric-string
             * } $m
             */
            $usrtestdata = f_tce_xml_user_results_test_stats(f_get_user_test_stat((int) $m['test_id'], $user_id));
            $halfscore = $usrtestdata['max_score'] / 2;
            $xml .= K_TAB . K_TAB . "<test id='" . $m['test_id'] . "'>" . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . '<start_time>' . $m['testuser_creation_time'] . '</start_time>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . '<end_time>' . $m['testuser_end_time'] . '</end_time>' . K_NEWLINE;
            $time_diff = (int) strtotime($m['testuser_end_time']) - (int) strtotime($m['testuser_creation_time']); //sec
            $time_diff = gmdate('H:i:s', $time_diff);
            $xml .= K_TAB . K_TAB . K_TAB . '<time>' . $time_diff . '</time>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . '<name>' . f_text_to_xml($m['test_name']) . '</name>' . K_NEWLINE;
            if ($usrtestdata['score_threshold'] > 0) {
                if ($usrtestdata['score'] >= $usrtestdata['score_threshold']) {
                    $xml .= K_TAB . K_TAB . K_TAB . '<passed>true</passed>' . K_NEWLINE;
                    ++$passed;
                } else {
                    $xml .= K_TAB . K_TAB . K_TAB . '<passed>false</passed>' . K_NEWLINE;
                }
            } elseif ($usrtestdata['score'] > $halfscore) {
                ++$passed;
            }

            $xml .= K_TAB . K_TAB . K_TAB . '<score>' . round((float) $m['total_score'], 3) . '</score>' . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . '<score_percent>'
                . round((100 * $usrtestdata['score']) / $usrtestdata['max_score'])
                . '</score_percent>'
                . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . '<right>' . $usrtestdata['right'] . '</right>' . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . '<right_percent>'
                . round((100 * $usrtestdata['right']) / $usrtestdata['all'])
                . '</right_percent>'
                . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . '<wrong>' . $usrtestdata['wrong'] . '</wrong>' . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . '<wrong_percent>'
                . round((100 * $usrtestdata['wrong']) / $usrtestdata['all'])
                . '</wrong_percent>'
                . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . '<unanswered>' . $usrtestdata['unanswered'] . '</unanswered>' . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . '<unanswered_percent>'
                . round((100 * $usrtestdata['unanswered']) / $usrtestdata['all'])
                . '</unanswered_percent>'
                . K_NEWLINE;
            $xml .=
                K_TAB . K_TAB . K_TAB . '<undisplayed>' . $usrtestdata['undisplayed'] . '</undisplayed>' . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . '<undisplayed_percent>'
                . round((100 * $usrtestdata['undisplayed']) / $usrtestdata['all'])
                . '</undisplayed_percent>'
                . K_NEWLINE;
            $status = (int) $m['testuser_status'] === 4 ? $l['w_locked'] : $l['w_unlocked'];

            $xml .= K_TAB . K_TAB . K_TAB . '<status>' . $status . '</status>' . K_NEWLINE;
            $xml .=
                K_TAB . K_TAB . K_TAB . '<comment>' . f_text_to_xml($usrtestdata['comment']) . '</comment>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . '</test>' . K_NEWLINE;

            // collects data for descriptive statistics
            $statsdata['score'][] = $m['total_score'] / $usrtestdata['max_score'];
            $statsdata['right'][] = $usrtestdata['right'] / $usrtestdata['all'];
            $statsdata['wrong'][] = $usrtestdata['wrong'] / $usrtestdata['all'];
            $statsdata['unanswered'][] = $usrtestdata['unanswered'] / $usrtestdata['all'];
            $statsdata['undisplayed'][] = $usrtestdata['undisplayed'] / $usrtestdata['all'];
            $statsdata['unrated'][] = $usrtestdata['unrated'] / $usrtestdata['all'];
        }
    } else {
        F_display_db_error();
    }

    // calculate statistics
    $stats = f_tce_xml_user_results_statistics(f_get_array_statistics($statsdata));
    $excludestat = ['sum', 'variance'];
    $calcpercent = ['mean', 'median', 'mode', 'minimum', 'maximum', 'range', 'standard_deviation'];

    $xml .= K_TAB . K_TAB . '<teststatistics>' . K_NEWLINE;
    $passed_output = isset($passed) ? (string) $passed : '';
    $xml .= K_TAB . K_TAB . K_TAB . '<passed>' . $passed_output . '</passed>' . K_NEWLINE;
    $passed_perc = 0;

    $xml .= K_TAB . K_TAB . K_TAB . '<passed_percent>' . round(100 * $passed_perc) . '</passed_percent>' . K_NEWLINE;
    /**
     * @var array{
     *     max_score:int|float,score_threshold:int|float,score:int|float,right:int|float,wrong:int|float,
     *     unanswered:int|float,undisplayed:int|float,unrated:int|float,all:int|float,comment:string
     * } $usrtestdata
     */
    foreach ($stats as $row => $columns) {
        if (!in_array($row, $excludestat, true)) {
            $xml .= K_TAB . K_TAB . K_TAB . '<' . $row . '>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<score>' . round($columns['score'], 3) . '</score>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<right>' . round($columns['right'], 3) . '</right>' . K_NEWLINE;
            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<wrong>' . round($columns['wrong'], 3) . '</wrong>' . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . K_TAB
                . '<unanswered>'
                . round($columns['unanswered'], 3)
                . '</unanswered>'
                . K_NEWLINE;
            $xml .=
                K_TAB
                . K_TAB
                . K_TAB
                . K_TAB
                . '<undisplayed>'
                . round($columns['undisplayed'], 3)
                . '</undisplayed>'
                . K_NEWLINE;
            $xml .=
                K_TAB . K_TAB . K_TAB . K_TAB . '<unrated>' . round($columns['unrated'], 3) . '</unrated>' . K_NEWLINE;
            if (in_array($row, $calcpercent, true)) {
                $xml .=
                    K_TAB
                    . K_TAB
                    . K_TAB
                    . K_TAB
                    . '<score_percent>'
                    . round(100 * ($columns['score'] / $usrtestdata['max_score']))
                    . '</score_percent>'
                    . K_NEWLINE;
                $xml .=
                    K_TAB
                    . K_TAB
                    . K_TAB
                    . K_TAB
                    . '<right_percent>'
                    . round(100 * ($columns['right'] / $usrtestdata['all']))
                    . '</right_percent>'
                    . K_NEWLINE;
                $xml .=
                    K_TAB
                    . K_TAB
                    . K_TAB
                    . K_TAB
                    . '<wrong_percent>'
                    . round(100 * ($columns['wrong'] / $usrtestdata['all']))
                    . '</wrong_percent>'
                    . K_NEWLINE;
                $xml .=
                    K_TAB
                    . K_TAB
                    . K_TAB
                    . K_TAB
                    . '<unanswered_percent>'
                    . round(100 * ($columns['unanswered'] / $usrtestdata['all']))
                    . '</unanswered_percent>'
                    . K_NEWLINE;
                $xml .=
                    K_TAB
                    . K_TAB
                    . K_TAB
                    . K_TAB
                    . '<undisplayed_percent>'
                    . round(100 * ($columns['undisplayed'] / $usrtestdata['all']))
                    . '</undisplayed_percent>'
                    . K_NEWLINE;
                $xml .=
                    K_TAB
                    . K_TAB
                    . K_TAB
                    . K_TAB
                    . '<unrated_percent>'
                    . round(100 * ($columns['unrated'] / $usrtestdata['all']))
                    . '</unrated_percent>'
                    . K_NEWLINE;
            }

            $xml .= K_TAB . K_TAB . K_TAB . '</' . $row . '>' . K_NEWLINE;
        }
    }

    $xml .= K_TAB . K_TAB . '</teststatistics>' . K_NEWLINE;

    $xml .= K_TAB . '</body>' . K_NEWLINE;

    return $xml . ('</tcexamuserresults>' . K_NEWLINE);
}

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_xml_user_results_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve configured integer values without specializing them during analysis. */
function f_tce_xml_user_results_int(mixed $value): int
{
    return (int) $value;
}

/**
 * Preserve legacy request comparisons.
 *
 * @param int|string|float|bool|array<array-key,mixed>|null $value
 */
function f_tce_xml_user_results_is_positive(int|string|float|bool|array|null $value): bool
{
    if (is_array($value)) {
        return true;
    }

    return $value !== null && $value > 0;
}

/**
 * @return object|resource|bool
 */
function f_tce_xml_user_results_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key,mixed>|null */
function f_tce_xml_user_results_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/**
 * @return array{
 *     max_score:int|float,score_threshold:int|float,score:int|float,right:int|float,wrong:int|float,
 *     unanswered:int|float,undisplayed:int|float,unrated:int|float,all:int|float,comment:string
 * }
 */
function f_tce_xml_user_results_test_stats(mixed $stats): array
{
    /**
     * @var array{
     *     max_score:int|float,score_threshold:int|float,score:int|float,right:int|float,wrong:int|float,
     *     unanswered:int|float,undisplayed:int|float,unrated:int|float,all:int|float,comment:string
     * } $stats
     */
    return $stats;
}

/**
 * @return array<string,array{
 *     score:int|float,right:int|float,wrong:int|float,unanswered:int|float,
 *     undisplayed:int|float,unrated:int|float
 * }>
 */
function f_tce_xml_user_results_statistics(mixed $stats): array
{
    /**
     * @var array<string,array{
     *     score:int|float,right:int|float,wrong:int|float,unanswered:int|float,
     *     undisplayed:int|float,unrated:int|float
     * }> $stats
     */
    return $stats;
}
