<?php

//============================================================+
// File name   : tce_edit_user.php
// Begin       : 2002-02-08
// Last Update : 2023-11-30
//
// Description : Edit user data.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display form to edit users.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2002-02-08
 */

require_once '../config/tce_config.php';

// explicit reads of POST inputs (formerly provided by register-globals emulation)
$forcedelete = f_tce_edit_user_string($_POST['forcedelete'] ?? '');
$newpassword = isset($_POST['newpassword']) && is_string($_POST['newpassword']) ? $_POST['newpassword'] : '';
$newpassword_repeat = isset($_POST['newpassword_repeat']) && is_string($_POST['newpassword_repeat'])
    ? $_POST['newpassword_repeat']
    : '';
$user_groups = f_tce_edit_user_string_list($_POST['user_groups'] ?? []);
// editable user fields submitted by the form (loaded from the DB later when editing)
$user_name = f_tce_edit_user_string($_POST['user_name'] ?? '');
$user_email = f_tce_edit_user_string($_POST['user_email'] ?? '');
$user_password = f_tce_edit_user_string($_POST['user_password'] ?? '');
$user_regnumber = f_tce_edit_user_string($_POST['user_regnumber'] ?? '');
$user_firstname = f_tce_edit_user_string($_POST['user_firstname'] ?? '');
$user_lastname = f_tce_edit_user_string($_POST['user_lastname'] ?? '');
$user_birthdate = f_tce_edit_user_string($_POST['user_birthdate'] ?? '');
$user_birthplace = f_tce_edit_user_string($_POST['user_birthplace'] ?? '');
$user_ssn = f_tce_edit_user_string($_POST['user_ssn'] ?? '');
$user_note = mb_substr(trim(f_tce_edit_user_string($_POST['user_note'] ?? '')), 0, 5000);
$user_schedule = mb_substr(trim(f_tce_edit_user_string($_POST['user_schedule'] ?? '')), 0, 5000);
$user_level = isset($_POST['user_level']) ? (int) $_POST['user_level'] : 0;
$user_otpkey = f_tce_edit_user_string($_POST['user_otpkey'] ?? '');
// round-tripped hidden fields preserved on UPDATE (overwritten internally in the add branch)
$user_ip = isset($_POST['user_ip']) && is_string($_POST['user_ip']) ? $_POST['user_ip'] : '';
$user_regdate = isset($_POST['user_regdate']) && is_string($_POST['user_regdate']) ? $_POST['user_regdate'] : '';
$user_searchterms = trim(f_tce_edit_user_string($_REQUEST['user_searchterms'] ?? ''));

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_USERS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/config/tce_user_registration.php';

/**
 * @var array{
 *     a_meta_charset:string,d_password_length:string,h_add:string,h_birth_date:string,h_birth_place:string,
 *     h_cancel:string,h_clear:string,h_delete:string,h_firstname:string,h_fiscal_code:string,h_ip:string,
 *     h_lastname:string,h_level:string,h_login_name:string,h_otpkey:string,h_password:string,
 *     h_password_repeat:string,h_regcode:string,h_regdate:string,h_update:string,h_usered_email:string,
 *     hp_edit_user:string,m_authorization_denied:string,m_delete_anonymous:string,m_delete_confirm:string,
 *     m_different_passwords:string,m_duplicate_name:string,m_duplicate_regnumber:string,m_duplicate_ssn:string,
 *     m_empty_password:string,m_form_missing_fields:string,m_user_deleted:string,m_user_updated:string,
 *     t_user_editor:string,w_add:string,w_birth_date:string,w_birth_place:string,w_cancel:string,w_clear:string,
 *     w_confirm:string,w_date_format:string,w_delete:string,w_email:string,w_firstname:string,w_fiscal_code:string,
 *     w_groups:string,w_ip:string,w_lastname:string,w_level:string,w_name:string,w_otp_qrcode:string,w_otpkey:string,
 *     w_password:string,w_regcode:string,w_regdate:string,w_repeat:string,w_search:string,w_select:string,
 *     w_update:string,w_user:string,w_username:string
 * } $l
 */
/** @var mixed $db */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;

$thispage_title = $l['t_user_editor'];
require_once '../code/tce_page_header.php';

require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_otp.php';
require_once '../../shared/code/tce_functions_user_photo.php';
require_once 'tce_functions_user_select.php';

$formstatus = f_tce_edit_user_bool($formstatus ?? false);
$menu_mode = f_tce_edit_user_string($menu_mode ?? '');

$user_id = 0;
$session_user_level = (int) ($_SESSION['session_user_level'] ?? 0);
$session_user_id = (int) ($_SESSION['session_user_id'] ?? 0);
$administrator_level = (int) K_AUTH_ADMINISTRATOR;
$delete_users_level = (int) K_AUTH_DELETE_USERS;
if (isset($_REQUEST['user_id'])) {
    $user_id = (int) $_REQUEST['user_id'];
    if (!f_is_authorized_editor_for_user($user_id)) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
}

if (isset($_REQUEST['group_id'])) {
    $group_id = (int) $_REQUEST['group_id'];
    if (!f_is_authorized_editor_for_group($group_id)) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
}

if (isset($_REQUEST['user_level'])) {
    $user_level = (int) $_REQUEST['user_level'];
    if ($session_user_level < $administrator_level) {
        if (f_legacy_int_equals($user_id, $session_user_id)) {
            // you cannot change your own level
            $user_level = $session_user_level;
        } else {
            // you cannot create a user with a level equal or higher than yours
            $user_level = min(max(0, $session_user_level - 1), $user_level);
        }
    }
}

// comma separated list of required fields
$_REQUEST['ff_required'] = 'user_name';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_name'], ENT_COMPAT, $l['a_meta_charset']);

switch ($menu_mode) { // process submitted data
    case 'delete':
            // ask confirmation
            if (
                $session_user_level < $delete_users_level
                || f_legacy_int_equals($user_id, $session_user_id)
                || f_legacy_int_equals($user_id, 1)
            ) {
                F_print_error('ERROR', $l['m_authorization_denied']);
                break;
            }

            F_print_error('WARNING', $l['m_delete_confirm']);
            ?>
        <div class="confirmbox">
        <form action="<?php echo
            htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        ; ?>" method="post" enctype="multipart/form-data" id="form_delete">
        <div>
        <input type="hidden" name="user_id" id="user_id" value="<?php echo $user_id; ?>" />
        <input type="hidden" name="user_name" id="user_name" value="<?php echo
            htmlspecialchars(stripslashes($user_name), ENT_QUOTES, $l['a_meta_charset'])
        ; ?>" />
        <?php

        F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
        F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
        echo f_get_csrf_token_field() . K_NEWLINE;
        ?>
        </div>
        </form>
        </div>
        <?php

        break;

    case 'forcedelete':
            // Delete specified user
            if (
                $session_user_level < $delete_users_level
                || f_legacy_int_equals($user_id, $session_user_id)
                || f_legacy_int_equals($user_id, 1)
            ) {
                F_print_error('ERROR', $l['m_authorization_denied']);
                break;
            }

            if ($forcedelete === $l['w_delete']) { //check if delete button has been pushed (redundant check)
                if (f_legacy_int_equals($user_id, 1)) { //can't delete anonymous user
                    F_print_error('WARNING', $l['m_delete_anonymous']);
                } else {
                    $sql = 'DELETE FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id . '';
                    $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
                    if (!$r) {
                        F_display_db_error(false);
                    } else {
                        $photo_path = f_tmf_user_photo_path((int) $user_id);
                        if (is_file($photo_path)) {
                            unlink($photo_path);
                        }
                        $user_id = false;
                        F_print_error('MESSAGE', '[' . stripslashes($user_name) . '] ' . $l['m_user_deleted']);
                    }
                }
            }

            break;

    case 'update':
        // Update user
            // check if the confirmation chekbox has been selected
            if (!isset($_REQUEST['confirmupdate']) || !f_legacy_int_equals($_REQUEST['confirmupdate'], 1)) {
                F_print_error(
                    'WARNING',
                    $l['m_form_missing_fields'] . ': ' . $l['w_confirm'] . ' &rarr; ' . $l['w_update'],
                );

                break;
            }

            if ($formstatus = F_check_form_fields()) {
                // check if name is unique
                if (!F_check_unique(
                    K_TABLE_USERS,
                    "user_name='" . F_escape_sql($db, $user_name) . "'",
                    'user_id',
                    $user_id,
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                // check if registration number is unique
                if (
                    strlen($user_regnumber) > 0
                    && !F_check_unique(
                        K_TABLE_USERS,
                        "user_regnumber='" . F_escape_sql($db, $user_regnumber) . "'",
                        'user_id',
                        $user_id,
                    )
                ) {
                    F_print_error('WARNING', $l['m_duplicate_regnumber']);
                    $formstatus = false;

                    break;
                }

                // check if ssn is unique
                if (
                    strlen($user_ssn) > 0
                    && !F_check_unique(
                        K_TABLE_USERS,
                        "user_ssn='" . F_escape_sql($db, $user_ssn) . "'",
                        'user_id',
                        $user_id,
                    )
                ) {
                    F_print_error('WARNING', $l['m_duplicate_ssn']);
                    $formstatus = false;

                    break;
                }

                // check password
                if (!empty($newpassword) || !empty($newpassword_repeat)) {
                    // @mago-expect lint:no-insecure-comparison -- confirm-field match: both operands are same-request user input, not a stored secret
                    if ($newpassword === $newpassword_repeat) {
                        $user_password = get_password_hash($newpassword);
                        // update OTP key
                        $user_otpkey = f_get_random_otp_key();
                    } else { //print message and exit
                        F_print_error('WARNING', $l['m_different_passwords']);
                        $formstatus = false;

                        break;
                    }
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_USERS
                    . ' SET
				user_regdate=\''
                    . F_escape_sql($db, $user_regdate)
                    . '\',
				user_ip=\''
                    . F_escape_sql($db, $user_ip)
                    . '\',
				user_name=\''
                    . F_escape_sql($db, $user_name)
                    . '\',
				user_email='
                    . f_empty_to_null($user_email)
                    . ',
				user_password=\''
                    . F_escape_sql($db, $user_password)
                    . '\',
				user_regnumber='
                    . f_empty_to_null($user_regnumber)
                    . ',
				user_firstname='
                    . f_empty_to_null($user_firstname)
                    . ',
				user_lastname='
                    . f_empty_to_null($user_lastname)
                    . ',
				user_birthdate='
                    . f_empty_to_null($user_birthdate)
                    . ',
				user_birthplace='
                    . f_empty_to_null($user_birthplace)
                    . ',
				user_ssn='
                    . f_empty_to_null($user_ssn)
                    . ',
				user_note='
                    . f_empty_to_null($user_note)
                    . ',
				user_schedule='
                    . f_empty_to_null($user_schedule)
                    . ',
				user_level=\''
                    . $user_level
                    . '\',
				user_otpkey='
                    . f_empty_to_null($user_otpkey)
                    . '
				WHERE user_id='
                    . $user_id
                    . '';
                $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', stripslashes($user_name) . ': ' . $l['m_user_updated']);
                }

                // remove old groups
                $old_user_groups = f_tce_edit_user_id_list(F_get_user_groups($user_id));
                foreach ($old_user_groups as $group_id) {
                    if (f_is_authorized_editor_for_group($group_id)) {
                        // delete previous groups
                        $sql =
                            'DELETE FROM '
                            . K_TABLE_USERGROUP
                            . '
						WHERE usrgrp_user_id='
                            . $user_id
                            . ' AND usrgrp_group_id='
                            . f_tce_edit_user_string($group_id)
                            . '';
                        $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
                        if (!$r) {
                            F_display_db_error(false);
                        }
                    }
                }

                // update user's groups
                if (!empty($user_groups)) {
                    foreach ($user_groups as $group_id) {
                        $group_id = (int) $group_id;
                        if (f_is_authorized_editor_for_group($group_id)) {
                            $sql =
                                'INSERT INTO '
                                . K_TABLE_USERGROUP
                                . ' (
							usrgrp_user_id,
							usrgrp_group_id
							) VALUES (
							\''
                                . $user_id
                                . '\',
							\''
                                . $group_id
                                . '\'
							)';
                            $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
                            if (!$r) {
                                F_display_db_error(false);
                            }
                        }
                    }
                }
            }

            break;

    case 'add':
        // Add user
            if ($formstatus = F_check_form_fields()) { // check submittef form fields
                // check if name is unique
                if (!F_check_unique(K_TABLE_USERS, "user_name='" . F_escape_sql($db, $user_name) . "'")) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                // check if registration number is unique
                if (
                    strlen($user_regnumber) > 0
                    && !F_check_unique(K_TABLE_USERS, "user_regnumber='" . F_escape_sql($db, $user_regnumber) . "'")
                ) {
                    F_print_error('WARNING', $l['m_duplicate_regnumber']);
                    $formstatus = false;

                    break;
                }

                // check if ssn is unique
                if (
                    strlen($user_ssn) > 0
                    && !F_check_unique(K_TABLE_USERS, "user_ssn='" . F_escape_sql($db, $user_ssn) . "'")
                ) {
                    F_print_error('WARNING', $l['m_duplicate_ssn']);
                    $formstatus = false;

                    break;
                }

                // check password
                if (!empty($newpassword) || !empty($newpassword_repeat)) { // update password
                    // @mago-expect lint:no-insecure-comparison -- confirm-field match: both operands are same-request user input, not a stored secret
                    if ($newpassword === $newpassword_repeat) {
                        $user_password = get_password_hash($newpassword);
                        // update OTP key
                        $user_otpkey = f_get_random_otp_key();
                    } else { //print message and exit
                        F_print_error('WARNING', $l['m_different_passwords']);
                        $formstatus = false;

                        break;
                    }
                } else { //print message and exit
                    F_print_error('WARNING', $l['m_empty_password']);
                    $formstatus = false;

                    break;
                }

                $normalized_user_ip = get_normalized_ip($_SERVER['REMOTE_ADDR']);
                $user_ip = is_string($normalized_user_ip) ? $normalized_user_ip : ''; // get the user's IP number
                $user_regdate = date(K_TIMESTAMP_FORMAT); // get the registration date and time

                $sql =
                    'INSERT INTO '
                    . K_TABLE_USERS
                    . ' (
				user_regdate,
				user_ip,
				user_name,
				user_email,
				user_password,
				user_regnumber,
				user_firstname,
				user_lastname,
				user_birthdate,
				user_birthplace,
				user_ssn,
				user_note,
				user_schedule,
				user_level,
				user_otpkey
				) VALUES (
				\''
                    . F_escape_sql($db, $user_regdate)
                    . '\',
				\''
                    . F_escape_sql($db, $user_ip)
                    . '\',
				\''
                    . F_escape_sql($db, $user_name)
                    . '\',
				'
                    . f_empty_to_null($user_email)
                    . ',
				\''
                    . F_escape_sql($db, $user_password)
                    . '\',
				'
                    . f_empty_to_null($user_regnumber)
                    . ',
				'
                    . f_empty_to_null($user_firstname)
                    . ',
				'
                    . f_empty_to_null($user_lastname)
                    . ',
				'
                    . f_empty_to_null($user_birthdate)
                    . ',
				'
                    . f_empty_to_null($user_birthplace)
                    . ',
				'
                    . f_empty_to_null($user_ssn)
                    . ',
				'
                    . f_empty_to_null($user_note)
                    . ',
				'
                    . f_empty_to_null($user_schedule)
                    . ',
				\''
                    . $user_level
                    . '\',
				'
                    . f_empty_to_null($user_otpkey)
                    . '
				)';
                $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
                }

                // add user's groups
                if (!empty($user_groups)) {
                    foreach ($user_groups as $group_id) {
                        $group_id = (int) $group_id;
                        if (f_is_authorized_editor_for_group($group_id)) {
                            $sql =
                                'INSERT INTO '
                                . K_TABLE_USERGROUP
                                . ' (
							usrgrp_user_id,
							usrgrp_group_id
							) VALUES (
							\''
                                . $user_id
                                . '\',
							\''
                                . $group_id
                                . '\'
							)';
                            $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
                            if (!$r) {
                                F_display_db_error(false);
                            }
                        }
                    }
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $user_regdate = '';
            $user_ip = '';
            $user_name = '';
            $user_email = '';
            $user_password = '';
            $user_regnumber = '';
            $user_firstname = '';
            $user_lastname = '';
            $user_birthdate = '';
            $user_birthplace = '';
            $user_ssn = '';
            $user_note = '';
            $user_schedule = '';
            $user_level = '';
            $user_otpkey = '';
            break;

    default:
            break;
} //end of switch

// --- Initialize variables
if ($formstatus && $menu_mode !== 'clear') {
    if (empty($user_id)) {
        $user_id = 0;
        $user_regdate = '';
        $user_ip = '';
        $user_name = '';
        $user_email = '';
        $user_password = '';
        $user_regnumber = '';
        $user_firstname = '';
        $user_lastname = '';
        $user_birthdate = '';
        $user_birthplace = '';
        $user_ssn = '';
        $user_note = '';
        $user_schedule = '';
        $user_level = '';
        $user_otpkey = '';
    } else {
        $sql = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id . ' LIMIT 1';
        $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
        if ($r) {
            if (($m = f_tce_edit_user_row(F_db_fetch_array($r))) !== null) {
                $user_id = (int) $m['user_id'];
                $user_regdate = f_tce_edit_user_string($m['user_regdate']);
                $user_ip = f_tce_edit_user_string($m['user_ip']);
                $user_name = f_tce_edit_user_string($m['user_name']);
                $user_email = f_tce_edit_user_string($m['user_email']);
                $user_password = f_tce_edit_user_string($m['user_password']);
                $user_regnumber = f_tce_edit_user_string($m['user_regnumber']);
                $user_firstname = f_tce_edit_user_string($m['user_firstname']);
                $user_lastname = f_tce_edit_user_string($m['user_lastname']);
                $user_birthdate = substr(f_tce_edit_user_string($m['user_birthdate']), 0, 10);
                $user_birthplace = f_tce_edit_user_string($m['user_birthplace']);
                $user_ssn = f_tce_edit_user_string($m['user_ssn']);
                $user_note = f_tce_edit_user_string($m['user_note']);
                $user_schedule = f_tce_edit_user_string($m['user_schedule']);
                $user_level = (int) $m['user_level'];
                $user_otpkey = f_tce_edit_user_string($m['user_otpkey']);
            } else {
                $user_regdate = '';
                $user_ip = '';
                $user_name = '';
                $user_email = '';
                $user_password = '';
                $user_regnumber = '';
                $user_firstname = '';
                $user_lastname = '';
                $user_birthdate = '';
                $user_birthplace = '';
                $user_ssn = '';
                $user_note = '';
                $user_schedule = '';
                $user_level = '';
                $user_otpkey = '';
            }
        } else {
            F_display_db_error();
        }
    }
}

if (
    in_array($menu_mode, ['add', 'update'], true)
    && !empty($user_id)
    && isset($_FILES['user_photo'])
    && (int) ($_FILES['user_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
) {
    $photo_result = f_tce_edit_user_photo_result(f_tmf_user_photo_store($_FILES['user_photo'], (int) $user_id));
    F_print_error($photo_result['status'] === 'stored' ? 'MESSAGE' : 'WARNING', $photo_result['message']);
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_usereditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="user_id">' . $l['w_user'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="user_id" id="user_id" onchange="document.getElementById(\'form_usereditor\').submit()">' . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if (f_legacy_int_equals($user_id, 0)) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
// Do not preload the whole users table here. The adjacent popup provides
// server-side search and pagination; this select only needs the current user.
if ((int) $user_id > 0) {
    echo
        '<option value="'
            . f_tce_edit_user_string($user_id)
            . '" selected="selected">'
            . htmlspecialchars(
                trim($user_lastname . ' ' . $user_firstname) . ' - ' . $user_name,
                ENT_NOQUOTES,
                $l['a_meta_charset'],
            )
            . '</option>'
            . K_NEWLINE
    ;
}

if ($user_searchterms !== '') {
    $sql = 'SELECT user_id, user_name, user_email, user_firstname, user_lastname, user_level FROM '
        . K_TABLE_USERS . ' WHERE (user_id>1)';
    $words = preg_split('/\s+/u', $user_searchterms, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($words === false ? [] : $words as $word) {
        $word = f_tce_edit_user_string(F_escape_sql($db, $word));
        $sql .= " AND ((user_name LIKE '%" . $word . "%')"
            . " OR (user_email LIKE '%" . $word . "%')"
            . " OR (user_firstname LIKE '%" . $word . "%')"
            . " OR (user_lastname LIKE '%" . $word . "%'))";
    }

    if ($session_user_level < $administrator_level) {
        $sql .= ' AND ((user_level<' . $session_user_level . ') OR (user_id='
            . $session_user_id . '))';
        $sql .= ' AND user_id IN (SELECT tb.usrgrp_user_id FROM '
            . K_TABLE_USERGROUP . ' AS ta, ' . K_TABLE_USERGROUP . ' AS tb'
            . ' WHERE ta.usrgrp_group_id=tb.usrgrp_group_id'
            . ' AND ta.usrgrp_user_id=' . $session_user_id
            . ' AND tb.usrgrp_user_id=user_id)';
    }

    $sql .= ' ORDER BY user_lastname, user_firstname, user_name';
    if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
        $sql = 'SELECT * FROM (' . $sql . ') WHERE rownum <= ' . K_MAX_ROWS_PER_PAGE;
    } else {
        $sql .= ' LIMIT ' . K_MAX_ROWS_PER_PAGE;
    }

    $r = f_tce_edit_user_query_result(F_db_query($sql, $db));
    if ($r) {
        while (($m = f_tce_edit_user_search_row(F_db_fetch_array($r))) !== null) {
            if ((int) $m['user_id'] === $user_id) {
                continue;
            }

            $display_name = trim(
                f_tce_edit_user_string($m['user_lastname']) . ' ' . f_tce_edit_user_string($m['user_firstname']),
            );
            $option_parts = array_filter([
                $display_name,
                f_tce_edit_user_string($m['user_name']),
                f_tce_edit_user_string($m['user_email']),
            ], static fn (string $value): bool => strlen($value) > 0);
            echo '<option value="' . (int) $m['user_id'] . '">'
                . htmlspecialchars(implode(' — ', $option_parts), ENT_NOQUOTES, $l['a_meta_charset'])
                . '</option>' . K_NEWLINE;
        }
    } else {
        echo '</select></span></div>' . K_NEWLINE;
        F_display_db_error();
    }
}

echo '</select>' . K_NEWLINE;

$search_hint = $l['w_search'] . ': ' . $l['w_username'] . ', ' . $l['w_name'] . ', ' . $l['w_email'];
echo '<input type="search" name="user_searchterms" id="user_searchterms" value="'
    . htmlspecialchars($user_searchterms, ENT_COMPAT, $l['a_meta_charset'])
    . '" size="24" maxlength="255" title="'
    . htmlspecialchars($search_hint, ENT_QUOTES, $l['a_meta_charset'])
    . '" aria-label="' . htmlspecialchars($search_hint, ENT_QUOTES, $l['a_meta_charset']) . '" />';
F_submit_button('search', $l['w_search'], $search_hint);

// link for user selection popup
$jsaction = "selectWindow=window.open('tce_select_users_popup.php?cid=user_id', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no');return false;";
echo '<button type="button" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</button>';

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo
    get_form_row_text_input(
        'user_name',
        $l['w_username'],
        $l['h_login_name'],
        '',
        $user_name,
        '',
        255,
        false,
        false,
        false,
        '',
        true,
    )
;
echo
    get_form_row_text_input(
        'user_email',
        $l['w_email'],
        $l['h_usered_email'],
        '',
        $user_email,
        K_EMAIL_RE_PATTERN,
        255,
        false,
        false,
        false,
        '',
        false,
        '',
        'email',
    )
;
echo
    get_form_row_text_input(
        'newpassword',
        $l['w_password'],
        $l['h_password'],
        ' (' . $l['d_password_length'] . ')',
        '',
        K_USRREG_PASSWORD_RE,
        255,
        false,
        false,
        true,
        '',
        false,
        'new-password',
    )
;
echo
    get_form_row_text_input(
        'newpassword_repeat',
        $l['w_password'],
        $l['h_password_repeat'],
        ' (' . $l['w_repeat'] . ')',
        '',
        '',
        255,
        false,
        false,
        true,
        '',
        false,
        'new-password',
    )
;
echo get_form_row_fixed_value('user_regdate', $l['w_regdate'], $l['h_regdate'], '', $user_regdate);
echo get_form_row_fixed_value('user_ip', $l['w_ip'], $l['h_ip'], '', $user_ip);
echo get_form_row_select_box('user_level', $l['w_level'], $l['h_level'], '', $user_level, [
    0,
    1,
    2,
    3,
    4,
    5,
    6,
    7,
    8,
    9,
    10,
]);
echo
    get_form_row_text_input(
        'user_regnumber',
        $l['w_regcode'],
        $l['h_regcode'],
        '',
        $user_regnumber,
        '',
        255,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'user_firstname',
        $l['w_firstname'],
        $l['h_firstname'],
        '',
        $user_firstname,
        '',
        255,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'user_lastname',
        $l['w_lastname'],
        $l['h_lastname'],
        '',
        $user_lastname,
        '',
        255,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'user_birthdate',
        $l['w_birth_date'],
        $l['h_birth_date'] . ' ' . $l['w_date_format'],
        '',
        $user_birthdate,
        '',
        10,
        true,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'user_birthplace',
        $l['w_birth_place'],
        $l['h_birth_place'],
        '',
        $user_birthplace,
        '',
        255,
        false,
        false,
        false,
    )
;
echo
    get_form_row_text_input(
        'user_ssn',
        $l['w_fiscal_code'],
        $l['h_fiscal_code'],
        '',
        $user_ssn,
        '',
        255,
        false,
        false,
        false,
    )
;
echo '<div class="row"><span class="label"><label for="user_photo">Фотография</label></span>'
    . '<span class="formw">';
if ((int) $user_id > 0 && is_file(f_tmf_user_photo_path((int) $user_id))) {
    echo '<img class="participant-photo-preview" src="../../public/code/tce_user_photo.php?id='
        . (int) $user_id . '" alt="Фотография участника" />';
}
echo '<input type="file" name="user_photo" id="user_photo" accept="image/jpeg,image/png" />'
    . '<small>JPEG или PNG, до 5 МБ. Файл будет безопасно перекодирован.</small></span></div>';
echo '<div class="row"><span class="label"><label for="user_note">Заметка</label></span>'
    . '<span class="formw"><textarea name="user_note" id="user_note" rows="4" maxlength="5000">'
    . htmlspecialchars($user_note, ENT_NOQUOTES, $l['a_meta_charset']) . '</textarea></span></div>';
echo '<div class="row"><span class="label"><label for="user_schedule">Расписание</label></span>'
    . '<span class="formw"><textarea name="user_schedule" id="user_schedule" rows="4" maxlength="5000">'
    . htmlspecialchars($user_schedule, ENT_NOQUOTES, $l['a_meta_charset']) . '</textarea></span></div>';

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="user_groups">' . $l['w_groups'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="user_groups[]" id="user_groups" size="5" multiple="multiple">' . K_NEWLINE;
$sql = 'SELECT * FROM ' . K_TABLE_GROUPS . ' ORDER BY group_name';
$r = f_tce_edit_user_query_result(F_db_query($sql, $db));
if ($r) {
    while (($m = f_tce_edit_user_group_row(F_db_fetch_array($r))) !== null) {
        echo '<option value="' . $m['group_id'] . '"';
        if (!f_is_authorized_editor_for_group($m['group_id'])) {
            echo ' style="text-decoration:line-through;"';
        }

        if (f_is_user_on_group($user_id, $m['group_id'])) {
            echo ' selected="selected"';
            $m['group_name'] = '* ' . $m['group_name'];
        }

        echo '>' . htmlspecialchars($m['group_name'], ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_row_text_input('user_otpkey', $l['w_otpkey'], $l['h_otpkey'], '', $user_otpkey, '', 255, false, false, false);

// display QR-Code for Google authenticator
if (!empty($user_otpkey)) {
    require_once '../../vendor/autoload.php'; // Composer-managed tc-lib-barcode
    $host = f_tce_edit_user_string(preg_replace('/[h][t][t][p][s]?[:][\/][\/]/', '', K_PATH_HOST));
    $barcode = new Com\Tecnick\Barcode\Barcode();
    $qrcode = $barcode->getBarcodeObj(
        'QRCODE,H',
        'otpauth://totp/' . $user_name . '@' . $host . '?secret=' . $user_otpkey,
        -6,
        -6,
        'black',
    );
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . $l['w_otp_qrcode'] . '</span>' . K_NEWLINE;
    echo '<span class="formw" style="margin:30px 0px 30px 0px;">' . K_NEWLINE;
    echo
        '<img src="data:image/png;base64,'
            . base64_encode($qrcode->getPngData(false))
            . '" alt="OTP QR code" />'
            . K_NEWLINE
    ;
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

echo '<div class="row">' . K_NEWLINE;
// show buttons by case
if ((int) $user_id > 0) {
    if (
        (int) $user_level < $session_user_level
        || f_legacy_int_equals($user_id, $session_user_id)
        || $session_user_level >= $administrator_level
    ) {
        echo '<span style="background-color:#999999;">';
        echo
            '<input type="checkbox" name="confirmupdate" id="confirmupdate" value="1" title="'
                . $l['w_confirm']
                . ' &rarr; '
                . $l['w_update']
                . '" aria-label="'
                . $l['w_confirm']
                . ' &rarr; '
                . $l['w_update']
                . '" />'
        ;
        F_submit_button('update', $l['w_update'], $l['h_update']);
        echo '</span>';
    }

    if (
        (int) $user_id > 1
        && $session_user_level >= $delete_users_level
        && !f_legacy_int_equals($user_id, $session_user_id)
    ) {
        // your account and anonymous user can't be deleted
        F_submit_button('delete', $l['w_delete'], $l['h_delete']);
    }
} else {
    F_submit_button('add', $l['w_add'], $l['h_add']);
}

F_submit_button('clear', $l['w_clear'], $l['h_clear']);

echo '<input type="hidden" name="user_password" id="user_password" value="' . $user_password . '" />' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_user'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

function f_tce_edit_user_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_edit_user_bool(mixed $value): bool
{
    if (is_array($value)) {
        return $value !== [];
    }
    if (is_object($value) || is_resource($value)) {
        return true;
    }
    return is_bool($value) || is_int($value) || is_float($value) || is_string($value)
        ? (bool) $value
        : false;
}

/** @return list<string> */
function f_tce_edit_user_string_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    return array_values(array_map(f_tce_edit_user_string(...), $value));
}

/** @return list<int|string> */
function f_tce_edit_user_id_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    /** @var list<int|string> $value */
    return $value;
}

/** @return object|resource|bool */
function f_tce_edit_user_query_result(mixed $value): mixed
{
    /** @var object|resource|bool $value */
    return $value;
}

/**
 * @return array{
 *     user_id:int|string,
 *     user_regdate:string|null,
 *     user_ip:string|null,
 *     user_name:string|null,
 *     user_email:string|null,
 *     user_password:string|null,
 *     user_regnumber:string|null,
 *     user_firstname:string|null,
 *     user_lastname:string|null,
 *     user_birthdate:string|null,
 *     user_birthplace:string|null,
 *     user_ssn:string|null,
 *     user_note:string|null,
 *     user_schedule:string|null,
 *     user_level:int|string|null,
 *     user_otpkey:string|null
 * }|null
 */
function f_tce_edit_user_row(mixed $value): ?array
{
    /**
     * @var array{
     *     user_id:int|string,
     *     user_regdate:string|null,
     *     user_ip:string|null,
     *     user_name:string|null,
     *     user_email:string|null,
     *     user_password:string|null,
     *     user_regnumber:string|null,
     *     user_firstname:string|null,
     *     user_lastname:string|null,
     *     user_birthdate:string|null,
     *     user_birthplace:string|null,
     *     user_ssn:string|null,
     *     user_note:string|null,
     *     user_schedule:string|null,
     *     user_level:int|string|null,
     *     user_otpkey:string|null
     * }|null $value
     */
    return $value;
}

/**
 * @return array{
 *     user_id:int|string,
 *     user_name:string|null,
 *     user_email:string|null,
 *     user_firstname:string|null,
 *     user_lastname:string|null,
 *     user_level:int|string|null
 * }|null
 */
function f_tce_edit_user_search_row(mixed $value): ?array
{
    /**
     * @var array{
     *     user_id:int|string,
     *     user_name:string|null,
     *     user_email:string|null,
     *     user_firstname:string|null,
     *     user_lastname:string|null,
     *     user_level:int|string|null
     * }|null $value
     */
    return $value;
}

/** @return array{group_id:int|string,group_name:string}|null */
function f_tce_edit_user_group_row(mixed $value): ?array
{
    /** @var array{group_id:int|string,group_name:string}|null $value */
    return $value;
}

/** @return array{status:string,message:string} */
function f_tce_edit_user_photo_result(mixed $value): array
{
    /** @var array{status:string,message:string} $value */
    return $value;
}
