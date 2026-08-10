<?php

//============================================================+
// File name   : tce_tsv_result_allusers.php
// Begin       : 2006-03-30
// Last Update : 2023-11-30
//
// Description : Functions to export users' results using
//               TSV file format (tab delimited text).
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display all test results in TSV format.
 * (Tab Delimited Text File)
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-30
 */

require_once '../config/tce_config.php';
$pagelevel = (int) constant('K_AUTH_ADMIN_RESULTS');
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_test_stats.php';

$test_id = isset($_REQUEST['test_id']) && is_string($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
if ($test_id > 0) {
    // check user's authorization
    require_once '../../shared/code/tce_authorization.php';
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        exit();
    }
}

$group_id = isset($_REQUEST['group_id']) && is_string($_REQUEST['group_id'])
    ? max(0, (int) $_REQUEST['group_id']) : 0;

$user_id = isset($_REQUEST['user_id']) && is_string($_REQUEST['user_id']) ? (int) $_REQUEST['user_id'] : 0;

if (isset($_REQUEST['startdate']) && is_string($_REQUEST['startdate'])) {
    $startdate = $_REQUEST['startdate'];
    $startdate_time = strtotime($startdate);
    $startdate = date(K_TIMESTAMP_FORMAT, $startdate_time === false ? 0 : $startdate_time);
} else {
    $startdate = 0;
}

if (isset($_REQUEST['enddate']) && is_string($_REQUEST['enddate'])) {
    $enddate = $_REQUEST['enddate'];
    $enddate_time = strtotime($enddate);
    $enddate = date(K_TIMESTAMP_FORMAT, $enddate_time === false ? 0 : $enddate_time);
} else {
    $enddate = 0;
}

if (
    isset($_REQUEST['order_field'])
    && is_string($_REQUEST['order_field'])
    && !empty($_REQUEST['order_field'])
    && in_array($_REQUEST['order_field'], [
        'testuser_creation_time',
        'testuser_end_time',
        'user_name',
        'user_lastname',
        'user_firstname',
        'total_score',
    ], true)
) {
    $order_field = $_REQUEST['order_field'];
} else {
    $order_field = 'total_score, user_lastname, user_firstname';
}

if (!isset($_REQUEST['orderdir']) || empty($_REQUEST['orderdir'])) {
    $full_order_field = $order_field;
} else {
    $full_order_field = $order_field . ' DESC';
}

$display_mode = isset($_REQUEST['display_mode']) && is_string($_REQUEST['display_mode'])
    ? max(0, min(5, (int) $_REQUEST['display_mode'])) : 0;

// send headers
header('Content-Description: TXT File Transfer');
header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
header('Pragma: public');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
// force download dialog
header('Content-Type: application/force-download');
header('Content-Type: application/octet-stream', false);
header('Content-Type: application/download', false);
header('Content-Type: text/tab-separated-values', false);
// use the Content-Disposition header to supply a recommended filename
header('Content-Disposition: attachment; filename=tcexam_test_results_' . $test_id . '_' . date('YmdHis') . '.tsv;');
header('Content-Transfer-Encoding: binary');

/** @var array{testuser: list<array{user_id: int|string}>} $data */
$data = f_get_all_users_test_stat(
    $test_id,
    $group_id,
    $user_id,
    $startdate,
    $enddate,
    $full_order_field,
    false,
    $display_mode,
);
// format data as HTML table
/** @var string $table */
$table = f_print_test_result_stat($data, 1, $order_field, '', false, $display_mode);
/** @var string $test_stat */
$test_stat = f_print_test_stat($test_id, $group_id, $user_id, $startdate, $enddate, 0, $data, $display_mode);
$table .= $test_stat;
// convert HTML table to TSV
echo f_html_to_tsv($table);

if ($user_id === 0) {
    /** @var array<int|string, int|string> $users */
    $users = [];
    foreach ($data['testuser'] as $tu) {
        $users[$tu['user_id']] = $tu['user_id'];
    }

    if (count($users) > 1) {
        echo K_NEWLINE . K_NEWLINE . K_NEWLINE . '<<< DETAILS >>>' . K_NEWLINE;
        // display detailed stats for each user
        foreach ($users as $uid) {
            echo K_NEWLINE . K_NEWLINE . '### USER' . K_TAB . $uid . K_NEWLINE . K_NEWLINE;

            /** @var array{testuser: list<array{user_id: int|string}>} $usrdata */
            $usrdata = f_get_all_users_test_stat(
                $test_id,
                $group_id,
                $uid,
                $startdate,
                $enddate,
                $full_order_field,
            );
            // format data as HTML table
            /** @var string $table */
            $table = f_print_test_result_stat($usrdata, 1, $order_field, '', false, $display_mode);
            /** @var string $test_stat */
            $test_stat = f_print_test_stat(
                $test_id,
                $group_id,
                $uid,
                $startdate,
                $enddate,
                0,
                $usrdata,
                $display_mode,
            );
            $table .= $test_stat;
            // convert HTML table to TSV
            echo f_html_to_tsv($table);
        }
    }
}
