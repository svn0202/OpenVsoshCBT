<?php

//============================================================+
// File name   : tce_xml_users.php
// Begin       : 2006-03-17
// Last Update : 2026-03-04
//
// Description : Functions to export users' accounts using
//               XML or JSON format.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display all users in XML or JSON format.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-17
 */

// check user's authorization
require_once '../config/tce_config.php';
/** @var int $pagelevel */
$pagelevel = K_AUTH_EXPORT_USERS;
require_once '../../shared/code/tce_authorization.php';

/** @var string $requested_format */
$requested_format = $_REQUEST['format'] ?? 'XML';
$output_format = strtoupper($requested_format);
$out_filename = 'tcexam_users_' . date('YmdHis');
$xml = F_xml_export_users();

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
 * Export all users to XML grouped by users' groups.
 * @author Nicola Asuni
 * @since 2006-03-17
 * @return string XML data
 */
function f_xml_export_users(): string
{
    global $l, $db;
    require_once '../config/tce_config.php';

    $boolean = ['false', 'true'];

    $xml = ''; // XML data to be returned

    $xml .= '<?xml version="1.0" encoding="UTF-8" ?>' . K_NEWLINE;
    /** @var string $tcexam_version */
    $tcexam_version = K_TCEXAM_VERSION;
    /** @var string $user_language */
    $user_language = K_USER_LANG;
    $xml .= '<tcexamusers version="' . $tcexam_version . '">' . K_NEWLINE;
    $xml .= K_TAB . '<header';
    $xml .= ' lang="' . $user_language . '"';
    $xml .= ' date="' . date(K_TIMESTAMP_FORMAT) . '">' . K_NEWLINE;
    $xml .= K_TAB . '</header>' . K_NEWLINE;
    $xml .= K_TAB . '<body>' . K_NEWLINE;

    // select users
    $sqla = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE (user_id>1)';
    /** @var int|string $session_user_level */
    $session_user_level = $_SESSION['session_user_level'] ?? 0;
    /** @var int|string $session_user_id */
    $session_user_id = $_SESSION['session_user_id'] ?? 0;
    if ($session_user_level < K_AUTH_ADMINISTRATOR) {
        // filter for level
        $sqla .=
            ' AND ((user_level<'
            . $session_user_level
            . ') OR (user_id='
            . $session_user_id
            . '))';
        // filter for groups
        $sqla .=
            ' AND user_id IN (SELECT tb.usrgrp_user_id
			FROM '
            . K_TABLE_USERGROUP
            . ' AS ta, '
            . K_TABLE_USERGROUP
            . ' AS tb
			WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
				AND ta.usrgrp_user_id='
            . (int) $session_user_id
            . '
				AND tb.usrgrp_user_id=user_id)';
    }

    $sqla .= ' ORDER BY user_lastname,user_firstname,user_name';
    $ra = F_db_query($sqla, $db);
    /** @var \mysqli_result|\PgSql\Result|false $ra */
    if ($ra !== false) {
        while (($ma = f_xml_export_users_row(F_db_fetch_array($ra))) !== null) {
            /** @var array{
             *     user_id: mixed,
             *     user_name: mixed,
             *     user_email: mixed,
             *     user_regdate: mixed,
             *     user_ip: mixed,
             *     user_firstname: mixed,
             *     user_lastname: mixed,
             *     user_birthdate?: mixed,
             *     user_birthplace: mixed,
             *     user_regnumber: mixed,
             *     user_ssn: mixed,
             *     user_level: mixed,
             *     user_verifycode: mixed,
             *     user_otpkey: mixed
             * } $ma
             */
            $xml .= K_TAB . K_TAB . K_TAB . '<user id="' . (string) ($ma['user_id'] ?? '') . '">' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<name>';
            $xml .= f_text_to_xml($ma['user_name']);
            $xml .= '</name>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<password>';
            // password cannot be exported because is encrypted
            //$xml .= $ma['user_password'];
            $xml .= '</password>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<email>';
            $xml .= f_text_to_xml($ma['user_email']);
            $xml .= '</email>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<regdate>';
            $xml .= f_text_to_xml($ma['user_regdate']);
            $xml .= '</regdate>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<ip>';
            $xml .= f_text_to_xml($ma['user_ip']);
            $xml .= '</ip>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<firstname>';
            $xml .= f_text_to_xml($ma['user_firstname']);
            $xml .= '</firstname>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<lastname>';
            $xml .= f_text_to_xml($ma['user_lastname']);
            $xml .= '</lastname>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<birthdate>';
            $xml .= f_text_to_xml(substr((string) ($ma['user_birthdate'] ?? ''), 0, 10));
            $xml .= '</birthdate>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<birthplace>';
            $xml .= f_text_to_xml($ma['user_birthplace']);
            $xml .= '</birthplace>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<regnumber>';
            $xml .= f_text_to_xml($ma['user_regnumber']);
            $xml .= '</regnumber>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<ssn>';
            $xml .= f_text_to_xml($ma['user_ssn']);
            $xml .= '</ssn>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<level>';
            $xml .= f_text_to_xml($ma['user_level']);
            $xml .= '</level>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<verifycode>';
            $xml .= f_text_to_xml($ma['user_verifycode']);
            $xml .= '</verifycode>' . K_NEWLINE;

            $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<otpkey>';
            $xml .= f_text_to_xml($ma['user_otpkey']);
            $xml .= '</otpkey>' . K_NEWLINE;

            // add user's groups
            $sqlg =
                'SELECT *
				FROM '
                . K_TABLE_GROUPS
                . ', '
                . K_TABLE_USERGROUP
                . '
				WHERE usrgrp_group_id=group_id
					AND usrgrp_user_id='
                . (string) ($ma['user_id'] ?? '')
                . '
				ORDER BY group_name';
            $rg = F_db_query($sqlg, $db);
            /** @var \mysqli_result|\PgSql\Result|false $rg */
            if ($rg !== false) {
                while (($mg = f_xml_export_users_row(F_db_fetch_array($rg))) !== null) {
                    /** @var array{group_id: mixed, group_name: mixed} $mg */
                    $xml .= K_TAB . K_TAB . K_TAB . K_TAB . '<group id="' . (string) ($mg['group_id'] ?? '') . '">';
                    $xml .= f_text_to_xml($mg['group_name']);
                    $xml .= '</group>' . K_NEWLINE;
                }
            } else {
                F_display_db_error();
            }

            $xml .= K_TAB . K_TAB . K_TAB . '</user>' . K_NEWLINE;
        }
    } else {
        F_display_db_error();
    }

    $xml .= K_TAB . '</body>' . K_NEWLINE;
    return $xml . ('</tcexamusers>' . K_NEWLINE);
}

/** @return non-empty-array<array-key, mixed>|null */
function f_xml_export_users_row(mixed $row): ?array
{
    return is_array($row) && $row !== [] ? $row : null;
}
