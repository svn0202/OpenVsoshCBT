<?php

//============================================================+
// File name   : tce_tsv_users.php
// Begin       : 2006-03-30
// Last Update : 2023-11-30
//
// Description : Functions to export users using TSV format.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display all users in TSV format.
 * (Tab Delimited Text File)
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-30
 */

// check user's authorization
require_once '../config/tce_config.php';
/** @var int $pagelevel */
$pagelevel = K_AUTH_EXPORT_USERS;
require_once '../../shared/code/tce_authorization.php';

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
header('Content-Disposition: attachment; filename=tcexam_users_' . date('YmdHis') . '.tsv;');
header('Content-Transfer-Encoding: binary');

echo f_tsv_export_users();

/**
 * Export all users to TSV grouped by users' groups.
 * @author Nicola Asuni
 * @since 2006-03-30
 * @return string TSV data
 */
function f_tsv_export_users(): string
{
    global $l, $db;
    require_once '../config/tce_config.php';

    $tsv = ''; // TSV data to be returned

    // print column names
    $tsv .= 'user_id';
    $tsv .= K_TAB . 'user_name';
    $tsv .= K_TAB . 'user_password';
    $tsv .= K_TAB . 'user_email';
    $tsv .= K_TAB . 'user_regdate';
    $tsv .= K_TAB . 'user_ip';
    $tsv .= K_TAB . 'user_firstname';
    $tsv .= K_TAB . 'user_lastname';
    $tsv .= K_TAB . 'user_birthdate';
    $tsv .= K_TAB . 'user_birthplace';
    $tsv .= K_TAB . 'user_regnumber';
    $tsv .= K_TAB . 'user_ssn';
    $tsv .= K_TAB . 'user_level';
    $tsv .= K_TAB . 'user_verifycode';
    $tsv .= K_TAB . 'user_otpkey';
    $tsv .= K_TAB . 'user_groups';

    $sql = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE (user_id>1)';
    /** @var int|string $session_user_level */
    $session_user_level = $_SESSION['session_user_level'] ?? 0;
    /** @var int|string $session_user_id */
    $session_user_id = $_SESSION['session_user_id'] ?? 0;
    if ($session_user_level < K_AUTH_ADMINISTRATOR) {
        // filter for level
        $sql .=
            ' AND ((user_level<'
            . $session_user_level
            . ') OR (user_id='
            . $session_user_id
            . '))';
        // filter for groups
        $sql .=
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

    $sql .= ' ORDER BY user_lastname,user_firstname,user_name';
    $r = f_tmf_tsv_users_query_result(F_db_query($sql, $db));
    if ($r) {
        while (($m = f_tmf_tsv_users_row(F_db_fetch_array($r))) !== null) {
            /** @var array{
             *     user_id: scalar|null,
             *     user_name: scalar|null,
             *     user_email: scalar|null,
             *     user_regdate: scalar|null,
             *     user_ip: scalar|null,
             *     user_firstname: scalar|null,
             *     user_lastname: scalar|null,
             *     user_birthdate?: scalar|null,
             *     user_birthplace: scalar|null,
             *     user_regnumber: scalar|null,
             *     user_ssn: scalar|null,
             *     user_level: scalar|null,
             *     user_verifycode: scalar|null,
             *     user_otpkey: scalar|null
             * } $m
             */
            $tsv .= K_NEWLINE . (string) $m['user_id'];
            $tsv .= K_TAB . (string) $m['user_name'];
            $tsv .= K_TAB; // password cannot be exported because is encrypted
            $tsv .= K_TAB . (string) $m['user_email'];
            $tsv .= K_TAB . (string) $m['user_regdate'];
            $tsv .= K_TAB . (string) $m['user_ip'];
            $tsv .= K_TAB . (string) $m['user_firstname'];
            $tsv .= K_TAB . (string) $m['user_lastname'];
            $tsv .= K_TAB . substr((string) ($m['user_birthdate'] ?? ''), 0, 10);
            $tsv .= K_TAB . (string) $m['user_birthplace'];
            $tsv .= K_TAB . (string) $m['user_regnumber'];
            $tsv .= K_TAB . (string) $m['user_ssn'];
            $tsv .= K_TAB . (string) $m['user_level'];
            $tsv .= K_TAB . (string) $m['user_verifycode'];
            $tsv .= K_TAB . (string) $m['user_otpkey'];
            $tsv .= K_TAB;
            $grp = '';
            // comma separated list of user's groups
            $sqlg =
                'SELECT *
				FROM '
                . K_TABLE_GROUPS
                . ', '
                . K_TABLE_USERGROUP
                . '
				WHERE usrgrp_group_id=group_id
					AND usrgrp_user_id='
                . (string) $m['user_id']
                . '
				ORDER BY group_name';
            $rg = f_tmf_tsv_users_query_result(F_db_query($sqlg, $db));
            if ($rg) {
                while (($mg = f_tmf_tsv_users_row(F_db_fetch_array($rg))) !== null) {
                    /** @var array{group_name: scalar|null} $mg */
                    $grp .= (string) $mg['group_name'] . ',';
                }
            } else {
                F_display_db_error();
            }

            if ($grp !== '') {
                // add user's groups removing last comma
                $tsv .= substr($grp, 0, -1);
            }
        }
    } else {
        F_display_db_error();
    }

    return $tsv;
}

/** @return non-empty-array<array-key, mixed>|null */
function f_tmf_tsv_users_row(mixed $row): ?array
{
    return is_array($row) && $row !== [] ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool|string */
function f_tmf_tsv_users_query_result(mixed $result): mixed
{
    if (
        is_bool($result)
        || is_string($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
}
