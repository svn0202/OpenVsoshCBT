<?php

//============================================================+
// File name   : tce_password_reset.php
// Begin       : 2012-04-14
// Last Update : 2023-11-30
//
// Description : Password Reset form.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display password reset form.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2008-03-30
 */

require_once '../config/tce_config.php';
require_once '../../shared/code/tce_functions_openvsosh_settings.php';

$access_settings = openvsosh_get_access_settings();
if (!$access_settings['password_reset_enabled']) {
    // password reset is disabled, redirect to main page
    header('Location: ' . K_PATH_HOST . K_PATH_TCEXAM);
    exit();
}

$pagelevel = 0;
require_once '../../shared/code/tce_authorization.php';

/** @var array{
 *     t_password_assistance:string,
 *     w_email:string,
 *     a_meta_charset:string,
 *     m_user_verification_sent:string,
 *     h_index:string,
 *     d_reset_password:string,
 *     h_usered_email:string,
 *     w_submit:string,
 *     h_submit:string
 * } $l
 */
/** @var mixed $db */
$thispage_title = $l['t_password_assistance'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';

// comma separated list of required fields
$_REQUEST['ff_required'] = 'user_email';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_email'], ENT_COMPAT, $l['a_meta_charset']);

// process submitted data
if (isset($_POST['resetpassword']) && ($formstatus = F_check_form_fields())) {
    // Read the submitted email explicitly from $_POST instead of relying on the register-globals
    // emulation in tce_config.php (plan Stage 8.2). F_check_form_fields() above has already
    // validated that 'user_email' is present and matches the email format.
    $user_email = isset($_POST['user_email']) && is_string($_POST['user_email']) ? $_POST['user_email'] : '';
    // check submitted form fields
    $user_verifycode = md5(uniqid((string) random_int(0, mt_getrandmax()), true));
    // verification code
    $user_verifycode[0] = '@';
    // get user ID
    $user_id = 0;
    $sql = 'SELECT user_id FROM ' . K_TABLE_USERS . " WHERE user_email='" . F_escape_sql($db, $user_email) . "'";
    $r = f_tmf_password_reset_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tmf_password_reset_row(F_db_fetch_array($r));
        $user_id = $m === null ? 0 : (int) ($m['user_id'] ?? 0);
    } else {
        F_display_db_error();
    }

    if ($user_id > 0) {
        // update verification code
        $sqlu =
            'UPDATE '
            . K_TABLE_USERS
            . " SET user_verifycode='"
            . F_escape_sql($db, $user_verifycode)
            . "' WHERE user_id="
            . $user_id
            . '';
        $ru = f_tmf_password_reset_query_result(F_db_query($sqlu, $db));
        if (!$ru) {
            F_display_db_error();
        }

        // send email confirmation
        require_once '../../shared/code/tce_functions_user_registration.php';
        F_send_user_reg_email($user_id, $user_email, $user_verifycode);
    }

    F_print_error('MESSAGE', $user_email . ': ' . $l['m_user_verification_sent']);
    echo '<div class="container">' . K_NEWLINE;
    echo
        '<strong><a href="index.php" title="' . $l['h_index'] . '">' . $l['h_index'] . ' &gt;</a></strong>' . K_NEWLINE
    ;
    echo '</div>' . K_NEWLINE;
    require_once '../code/tce_page_footer.php';
    exit();
} //end of add

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_usereditor">'
        . K_NEWLINE
;

echo '<p>' . $l['d_reset_password'] . '</p>' . K_NEWLINE;

echo
    get_form_row_text_input(
        'user_email',
        $l['w_email'],
        $l['h_usered_email'],
        '',
        '',
        K_EMAIL_RE_PATTERN,
        255,
        false,
        false,
        false,
        '',
        true,
        'email',
        'email',
    )
;

echo '<div class="row">' . K_NEWLINE;

F_submit_button('resetpassword', $l['w_submit'], $l['h_submit']);

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** @return non-empty-array<array-key, mixed>|null */
function f_tmf_password_reset_row(mixed $row): ?array
{
    return is_array($row) && $row !== [] ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool|string */
function f_tmf_password_reset_query_result(mixed $result): mixed
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
