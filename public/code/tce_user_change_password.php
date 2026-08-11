<?php

//============================================================+
// File name   : tce_user_change_password.php
// Begin       : 2010-09-17
// Last Update : 2023-11-30
//
// Description : Form to change user password
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Form to change user password
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2010-09-17
 */

require_once '../config/tce_config.php';

$currentpassword = isset($_POST['currentpassword']) && is_string($_POST['currentpassword'])
    ? $_POST['currentpassword']
    : '';
$newpassword = isset($_POST['newpassword']) && is_string($_POST['newpassword']) ? $_POST['newpassword'] : '';
$newpassword_repeat = isset($_POST['newpassword_repeat']) && is_string($_POST['newpassword_repeat'])
    ? $_POST['newpassword_repeat']
    : '';

/** @var int $pagelevel */
$pagelevel = K_AUTH_USER_CHANGE_PASSWORD;
/** @var array{
 *     t_user_change_password: string,
 *     w_current_password: string,
 *     w_new_password: string,
 *     a_meta_charset: string,
 *     m_different_passwords: string,
 *     m_login_wrong: string,
 *     m_password_updated: string,
 *     h_password: string,
 *     d_password_length: string,
 *     h_password_repeat: string,
 *     w_repeat: string,
 *     w_update: string,
 *     h_update: string,
 *     hp_user_change_password: string
 * } $l
 */
/** @var mixed $db */
$thispage_title = $l['t_user_change_password'];
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/config/tce_user_registration.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../code/tce_page_header.php';

/** @var array{session_user_id:int} $_SESSION */
$user_id = (int) $_SESSION['session_user_id'];

// comma separated list of required fields
$_REQUEST['ff_required'] = 'currentpassword,newpassword,newpassword_repeat';
$_REQUEST['ff_required_labels'] = htmlspecialchars(
    $l['w_current_password'] . ',' . $l['w_new_password'] . ',' . $l['w_new_password'],
    ENT_COMPAT,
    $l['a_meta_charset'],
);

// process submitted data
/** @var string $menu_mode */
switch ($menu_mode) {
    case 'update': // Update user
        if ($formstatus = F_check_form_fields()) {
            // check password
            // @mago-expect lint:no-insecure-comparison -- confirm-field match: both operands are same-request user input, not a stored secret
            if (empty($newpassword) || empty($newpassword_repeat) || $newpassword !== $newpassword_repeat) {
                //print message and exit
                F_print_error('WARNING', $l['m_different_passwords']);
                $formstatus = false;

                break;
            }

            $sql = 'SELECT user_password FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id;
            $r = f_tmf_change_password_query_result(F_db_query($sql, $db));
            if ($r) {
                $m = f_tmf_change_password_row(F_db_fetch_array($r));
                if (
                    $m === null
                    || !check_password($currentpassword, (string) ($m['user_password'] ?? ''))
                ) {
                    F_print_error('WARNING', $l['m_login_wrong']);
                    $formstatus = false;

                    break;
                }
            } else {
                F_display_db_error(false);
                break;
            }

            $sql =
                'UPDATE '
                . K_TABLE_USERS
                . ' SET
				user_password=\''
                . F_escape_sql($db, get_password_hash($newpassword))
                . '\'
				WHERE user_id='
                . $user_id;
            $r = f_tmf_change_password_query_result(F_db_query($sql, $db));
            if (!$r) {
                F_display_db_error(false);
            } else {
                F_print_error('MESSAGE', $l['m_password_updated']);
            }
        }

        break;

    default:
        break;
} //end of switch

echo '<div class="container">' . K_NEWLINE;

echo '<div class="gsoformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_editor">'
        . K_NEWLINE
;

echo
    get_form_row_text_input(
        'currentpassword',
        $l['w_current_password'],
        $l['h_password'],
        '',
        '',
        '',
        255,
        false,
        false,
        true,
        '',
        true,
        'current-password',
    )
;
echo
    get_form_row_text_input(
        'newpassword',
        $l['w_new_password'],
        $l['h_password'],
        ' (' . $l['d_password_length'] . ')',
        '',
        K_USRREG_PASSWORD_RE,
        255,
        false,
        false,
        true,
        '',
        true,
        'new-password',
    )
;
echo
    get_form_row_text_input(
        'newpassword_repeat',
        $l['w_new_password'],
        $l['h_password_repeat'],
        ' (' . $l['w_repeat'] . ')',
        '',
        '',
        255,
        false,
        false,
        true,
        '',
        true,
        'new-password',
    )
;

echo '<div class="row">' . K_NEWLINE;

F_submit_button('update', $l['w_update'], $l['h_update']);

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_user_change_password'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once __DIR__ . '/tce_page_footer.php';

/** @return array<array-key, mixed>|null */
function f_tmf_change_password_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool */
function f_tmf_change_password_query_result(mixed $result): mixed
{
    if (
        is_bool($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
}
