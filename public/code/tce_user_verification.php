<?php

//============================================================+
// File name   : tce_user_verification.php
// Begin       : 2008-03-31
// Last Update : 2023-11-30
//
// Description : User verification.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * User verification.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2008-03-30
 */

require_once '../config/tce_config.php';
/** @var mixed $db */
/**
 * @var array{
 *     t_user_registration: string,
 *     w_new_password: string,
 *     m_user_registration_ok: string,
 *     m_otp_qrcode: string,
 *     h_index: string
 * } $l
 */

require_once '../../shared/config/tce_user_registration.php';

$email_input = isset($_REQUEST['a']) && is_string($_REQUEST['a']) ? $_REQUEST['a'] : '';
$email = preg_replace('/[^a-zA-Z0-9_\.\-\@]/', '', $email_input) ?? '';
$verifycode_input = isset($_REQUEST['b']) && is_string($_REQUEST['b']) ? $_REQUEST['b'] : '';
$verifycode = preg_replace('/[^A-Fa-f0-9\@]/', '', $verifycode_input) ?? '';
$userid = isset($_REQUEST['c']) && is_string($_REQUEST['c']) ? (int) $_REQUEST['c'] : 0;
$is_password_reset = str_starts_with($verifycode, '@');

$pagelevel = 0;
require_once '../../shared/code/tce_authorization.php';

$thispage_title = $l['t_user_registration'];
$thispage_description = '';
require_once '../code/tce_page_header.php';

$sql =
    'SELECT *
	FROM '
    . K_TABLE_USERS
    . '
	WHERE (user_verifycode=\''
    . F_escape_sql($db, $verifycode)
    . '\'
		AND user_id=\''
    . $userid
    . '\'
		AND user_email=\''
    . F_escape_sql($db, $email)
    . '\')
		LIMIT 1';
$normalize_row = static fn(mixed $row): ?array => is_array($row) ? $row : null;
$r = F_db_query($sql, $db);
/** @var \mysqli_result|\PgSql\Result|false $r */
if ($r !== false) {
    $m = $normalize_row(F_db_fetch_array($r));
    if ($m !== null) {
        $user_name = (string) ($m['user_name'] ?? '');
        $user_otpkey = (string) ($m['user_otpkey'] ?? '');
        $new_password = '';
        // update user level
        if ($is_password_reset) {
            // password reset
            $new_password = substr(md5(uniqid((string) random_int(0, mt_getrandmax()), true)), 0, 8);
            $sqlu =
                'UPDATE '
                . K_TABLE_USERS
                . " SET user_password='"
                . F_escape_sql($db, get_password_hash($new_password))
                . "', user_verifycode=NULL WHERE user_id="
                . $userid
                . '';
        } else {
            // user registration
            $sqlu =
                'UPDATE ' . K_TABLE_USERS . " SET user_level='1', user_verifycode=NULL WHERE user_id=" . $userid . '';
        }

        $ru = F_db_query($sqlu, $db);
        /** @var \mysqli_result|\PgSql\Result|bool $ru */
        if (!$ru) {
            F_display_db_error(false);
        } else {
            if ($is_password_reset) {
                F_print_error('MESSAGE', $l['w_new_password'] . ': ' . $new_password);
            } else {
                F_print_error('MESSAGE', $l['m_user_registration_ok']);
            }

            echo K_NEWLINE;
            echo '<div class="container">' . K_NEWLINE;
            $otp_login = (static fn(mixed $value): bool => $value === true)(constant('K_OTP_LOGIN'));
            if ($otp_login) {
                require_once '../../vendor/autoload.php'; // Composer-managed tc-lib-barcode
                $host = preg_replace(
                    '/[h][t][t][p][s]?[:][\/][\/]/',
                    '',
                    (string) constant('K_PATH_HOST'),
                ) ?? '';
                $barcode = new Com\Tecnick\Barcode\Barcode();
                $qrcode = $barcode->getBarcodeObj(
                    'QRCODE,H',
                    'otpauth://totp/'
                    . rawurlencode($host . ':' . $user_name)
                    . '?secret='
                    . rawurlencode($user_otpkey)
                    . '&issuer='
                    . rawurlencode($host)
                    . '&algorithm=SHA1&digits=6&period=30',
                    -6,
                    -6,
                    'black',
                );
                echo '<p>' . $l['m_otp_qrcode'] . '</p>' . K_NEWLINE;
                echo '<h2>' . $user_otpkey . '</h2>' . K_NEWLINE;
                echo '<div style="margin:40px 40px 40px 40px;">' . K_NEWLINE;
                echo
                    '<img src="data:image/png;base64,'
                        . base64_encode($qrcode->getPngData(false))
                        . '" alt="OTP QR code" />'
                        . K_NEWLINE
                ;
                echo '</div>' . K_NEWLINE;
            }

            echo
                '<strong><a href="index.php" title="'
                    . $l['h_index']
                    . '">'
                    . $l['h_index']
                    . ' &gt;</a></strong>'
                    . K_NEWLINE
            ;
            echo '</div>' . K_NEWLINE;
            require_once '../code/tce_page_footer.php';
            exit();
        }
    }
} else {
    F_display_db_error(false);
}

F_print_error('ERROR', 'USER VERIFICATION ERROR');
echo K_NEWLINE;
echo '<div class="container">' . K_NEWLINE;
echo '<strong><a href="index.php" title="' . $l['h_index'] . '">' . $l['h_index'] . ' &gt;</a></strong>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';
