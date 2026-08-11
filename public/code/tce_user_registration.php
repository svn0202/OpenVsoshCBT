<?php

//============================================================+
// File name   : tce_user_registration.php
// Begin       : 2008-03-30
// Last Update : 2026-03-08
//
// Description : User registration form.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display user registration form.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2008-03-30
 */

require_once '../config/tce_config.php';

$newpassword = isset($_POST['newpassword']) && is_string($_POST['newpassword']) ? $_POST['newpassword'] : '';
$newpassword_repeat = isset($_POST['newpassword_repeat']) && is_string($_POST['newpassword_repeat'])
    ? $_POST['newpassword_repeat']
    : '';

// read submitted form inputs (used by the registration processing path below)
$user_name = f_tce_user_registration_string($_REQUEST['user_name'] ?? '');
$user_email = f_tce_user_registration_string($_REQUEST['user_email'] ?? '');
$user_regnumber = f_tce_user_registration_string($_REQUEST['user_regnumber'] ?? '');
$user_firstname = f_tce_user_registration_string($_REQUEST['user_firstname'] ?? '');
$user_lastname = f_tce_user_registration_string($_REQUEST['user_lastname'] ?? '');
$user_birthdate = f_tce_user_registration_string($_REQUEST['user_birthdate'] ?? '');
$user_birthplace = f_tce_user_registration_string($_REQUEST['user_birthplace'] ?? '');
$user_ssn = f_tce_user_registration_string($_REQUEST['user_ssn'] ?? '');
$user_groups = f_tce_user_registration_groups($_REQUEST['user_groups'] ?? []);

require_once '../../shared/config/tce_user_registration.php';
require_once '../../shared/code/tce_functions_openvsosh_settings.php';
$access_settings = f_tce_user_registration_access_settings(openvsosh_get_access_settings());
if (!$access_settings['registration_enabled']) {
    // user registration is disabled, redirect to main page
    header('Location: ' . K_PATH_HOST . K_PATH_TCEXAM);
    exit();
}

/** @var int $pagelevel */
$pagelevel = 0;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_otp.php';

/**
 * @var array{
 *     a_meta_charset:string,d_password_length:string,h_add:string,h_birth_date:string,h_birth_place:string,
 *     h_firstname:string,h_fiscal_code:string,h_index:string,h_lastname:string,h_login_name:string,
 *     h_password_repeat:string,h_password:string,h_regcode:string,h_usered_email:string,hp_user_registration:string,
 *     m_different_passwords:string,m_duplicate_name:string,m_duplicate_regnumber:string,m_duplicate_ssn:string,
 *     m_empty_password:string,m_new_window_link:string,m_user_registration_ok:string,m_user_verification_sent:string,
 *     t_user_registration:string,w_add:string,w_birth_date:string,w_birth_place:string,w_date_format:string,
 *     w_email:string,w_firstname:string,w_fiscal_code:string,w_groups:string,w_i_agree:string,w_lastname:string,
 *     w_name:string,w_password:string,w_regcode:string,w_repeat:string,w_username:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/**
 * @var array{
 *     user_name:int|string|bool,newpassword:int|string|bool,newpassword_repeat:int|string|bool,
 *     user_email:int|string|bool,user_regnumber:int|string|bool,user_firstname:int|string|bool,
 *     user_lastname:int|string|bool,user_birthdate:int|string|bool,user_birthplace:int|string|bool,
 *     user_ssn:int|string|bool,user_groups:int|string|bool,user_agreement:int|string|bool
 * } $regfields
 */
/** @var array{SCRIPT_NAME:string,REMOTE_ADDR:string} $server */
$server = $_SERVER;

$thispage_title = $l['t_user_registration'];
$thispage_description = $l['hp_user_registration'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';

// set fields descriptions for error messages
$fielddesc = [
    'user_name' => $l['w_name'],
    'newpassword' => $l['w_password'],
    'newpassword_repeat' => $l['w_password'],
    'user_email' => $l['w_email'],
    'user_regnumber' => $l['w_regcode'],
    'user_firstname' => $l['w_firstname'],
    'user_lastname' => $l['w_lastname'],
    'user_birthdate' => $l['w_birth_date'],
    'user_birthplace' => $l['w_birth_place'],
    'user_ssn' => $l['w_fiscal_code'],
    'user_groups' => $l['w_groups'],
    'user_agreement' => $l['w_i_agree'],
];
$reqfields = [];
$reqdesc = [];
foreach ($regfields as $field => $required) {
    if (f_legacy_int_equals($required, 2)) {
        $reqfields[] = $field;
        $reqdesc[] = htmlspecialchars($fielddesc[$field] ?? '', ENT_COMPAT, $l['a_meta_charset']);
    }
}

$_REQUEST['ff_required'] = implode(',', $reqfields);
$_REQUEST['ff_required_labels'] = implode(',', $reqdesc);

if ($menu_mode === 'add') { // process submitted data
    foreach ($regfields as $name => $enabled) {
        // disable unauthorized fields
        if ($enabled) {
            continue;
        }

        switch ($name) {
            case 'user_email':
                $user_email = '';
                break;
            case 'user_regnumber':
                $user_regnumber = '';
                break;
            case 'user_firstname':
                $user_firstname = '';
                break;
            case 'user_lastname':
                $user_lastname = '';
                break;
            case 'user_birthdate':
                $user_birthdate = '';
                break;
            case 'user_birthplace':
                $user_birthplace = '';
                break;
            case 'user_ssn':
                $user_ssn = '';
                break;
            case 'user_groups':
                $user_groups = [];
                break;
        }
    }

    $formstatus = F_check_form_fields();
    if ($formstatus) { // check submitted form fields
        // check if name is unique
        if (!F_check_unique(K_TABLE_USERS, "user_name='" . F_escape_sql($db, $user_name) . "'")) {
            F_print_error('WARNING', $l['m_duplicate_name']);
            $formstatus = false;
        }

        // check if registration number is unique
        if (
            strlen($user_regnumber) > 0
            && !F_check_unique(K_TABLE_USERS, "user_regnumber='" . F_escape_sql($db, $user_regnumber) . "'")
        ) {
            F_print_error('WARNING', $l['m_duplicate_regnumber']);
            $formstatus = false;
        }

        // check if ssn is unique
        if (
            strlen($user_ssn) > 0
            && !F_check_unique(K_TABLE_USERS, "user_ssn='" . F_escape_sql($db, $user_ssn) . "'")
        ) {
            F_print_error('WARNING', $l['m_duplicate_ssn']);
            $formstatus = false;
        }

        $user_password = '';
        $user_otpkey = '';
        // check password
        if (!empty($newpassword) || !empty($newpassword_repeat)) {
            // update password
            if (hash_equals($newpassword, $newpassword_repeat)) {
                $user_password = f_tce_user_registration_string(get_password_hash($newpassword));
                // update OTP key
                $user_otpkey = f_tce_user_registration_string(f_get_random_otp_key());
            } else { //print message and exit
                F_print_error('WARNING', $l['m_different_passwords']);
                $formstatus = false;
            }
        } else { //print message and exit
            F_print_error('WARNING', $l['m_empty_password']);
            $formstatus = false;
        }

        if ($formstatus) {
            $user_verifycode = md5(uniqid((string) random_int(0, mt_getrandmax()), true)); // verification code
            $user_ip = f_tce_user_registration_string(get_normalized_ip($server['REMOTE_ADDR'])); // get the user's IP number
            $user_regdate = date(K_TIMESTAMP_FORMAT);
            // get the registration date and time
            $usrlevel = f_tce_user_registration_bool(K_USRREG_EMAIL_CONFIRM) ? 0 : 1;

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
				user_level,
				user_verifycode,
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
				\''
                . $usrlevel
                . '\',
				\''
                . $user_verifycode
                . '\',
				'
                . f_empty_to_null($user_otpkey)
                . '
				)';
            $r = f_tce_user_registration_query_result(F_db_query($sql, $db));
            $user_id = 0;
            if (!$r) {
                F_display_db_error(false);
            } else {
                $user_id = (int) F_db_insert_id($db, K_TABLE_USERS, 'user_id');
            }

            // add user's groups
            if (empty($user_groups)) {
                $user_groups = [K_USRREG_GROUP];
            } elseif (!in_array(K_USRREG_GROUP, $user_groups)) {
                $user_groups[] = K_USRREG_GROUP;
            }

            $allowed_groups = f_tce_user_registration_allowed_groups(K_USRREG_ALLOWED_GROUPS);
            foreach ($user_groups as $group_id) {
                if (!in_array($group_id, $allowed_groups)) {
                    continue;
                }

                $sql =
                    'INSERT INTO '
                    . K_TABLE_USERGROUP
                    . ' (
					usrgrp_user_id,
					usrgrp_group_id
					) VALUES (
					\''
                    . (int) $user_id
                    . '\',
					\''
                    . (int) $group_id
                    . '\'
					)';
                $r = f_tce_user_registration_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                }
            }

            if (f_tce_user_registration_bool(K_USRREG_EMAIL_CONFIRM)) {
                // require email confirmation
                require_once '../../shared/code/tce_functions_user_registration.php';
                F_send_user_reg_email($user_id, $user_email, $user_verifycode);
                F_print_error('MESSAGE', $user_email . ': ' . $l['m_user_verification_sent']);
            } else {
                F_print_error('MESSAGE', $l['m_user_registration_ok']);
                echo K_NEWLINE;
            }

            echo '<div class="container">' . K_NEWLINE;
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
} //end of add

// --- Initialize variables
if (isset($_REQUEST['user_name'])) {
    $user_name = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_name']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_name = '';
}

if (isset($_REQUEST['user_email'])) {
    $user_email = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_email']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_email = '';
}

$user_password = f_tce_user_registration_string($_REQUEST['user_password'] ?? '');

if (isset($_REQUEST['user_regnumber'])) {
    $user_regnumber = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_regnumber']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_regnumber = '';
}

if (isset($_REQUEST['user_firstname'])) {
    $user_firstname = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_firstname']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_firstname = '';
}

if (isset($_REQUEST['user_lastname'])) {
    $user_lastname = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_lastname']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_lastname = '';
}

if (isset($_REQUEST['user_birthdate'])) {
    $user_birthdate = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_birthdate']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_birthdate = '';
}

if (isset($_REQUEST['user_birthplace'])) {
    $user_birthplace = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_birthplace']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_birthplace = '';
}

if (isset($_REQUEST['user_ssn'])) {
    $user_ssn = htmlspecialchars(f_tce_user_registration_string($_REQUEST['user_ssn']), ENT_COMPAT, $l['a_meta_charset']);
} else {
    $user_ssn = '';
}

$user_groups = f_tce_user_registration_groups($_REQUEST['user_groups'] ?? []);

// some fields are always required
foreach (['user_name', 'newpassword', 'newpassword_repeat'] as $required_field) {
    $regfields[$required_field] = 2;
}
if (f_tce_user_registration_bool(K_USRREG_EMAIL_CONFIRM)) {
    $regfields['user_email'] = 2;
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_usereditor">'
        . K_NEWLINE
;

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
        show_required_field($regfields['user_name']),
        f_legacy_int_equals($regfields['user_name'], 2),
        'username',
    )
;
if (
    f_tce_user_registration_bool(K_USRREG_EMAIL_CONFIRM)
    || f_tce_user_registration_bool($regfields['user_email'])
) {
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
            show_required_field($regfields['user_email']),
            f_legacy_int_equals($regfields['user_email'], 2),
            'email',
            'email',
        )
    ;
}

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
        show_required_field(2),
        true,
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
        show_required_field(2),
        true,
        'new-password',
    )
;
if ($regfields['user_regnumber']) {
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
            show_required_field($regfields['user_regnumber']),
            f_legacy_int_equals($regfields['user_regnumber'], 2),
        )
    ;
}

if ($regfields['user_firstname']) {
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
            show_required_field($regfields['user_firstname']),
            f_legacy_int_equals($regfields['user_firstname'], 2),
            'given-name',
        )
    ;
}

if ($regfields['user_lastname']) {
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
            show_required_field($regfields['user_lastname']),
            f_legacy_int_equals($regfields['user_lastname'], 2),
            'family-name',
        )
    ;
}

if ($regfields['user_birthdate']) {
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
            show_required_field($regfields['user_birthdate']),
            f_legacy_int_equals($regfields['user_birthdate'], 2),
            'bday',
        )
    ;
}

if ($regfields['user_birthplace']) {
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
            show_required_field($regfields['user_birthplace']),
            f_legacy_int_equals($regfields['user_birthplace'], 2),
        )
    ;
}

if ($regfields['user_ssn']) {
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
            show_required_field($regfields['user_ssn']),
            f_legacy_int_equals($regfields['user_ssn'], 2),
        )
    ;
}

if ($regfields['user_groups']) {
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<label for="user_groups">' . $l['w_groups'] . '</label>' . K_NEWLINE;
    echo show_required_field($regfields['user_groups']);
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo '<select name="user_groups[]" id="user_groups" size="5" multiple="multiple">' . K_NEWLINE;
    $sql = 'SELECT *
		FROM ' . K_TABLE_GROUPS . '
		ORDER BY group_name';
    $r = f_tce_user_registration_query_result(F_db_query($sql, $db));
    if ($r) {
        while (($m = f_tce_user_registration_group_row(F_db_fetch_array($r))) !== null) {
            echo '<option value="' . $m['group_id'] . '"';
            if (in_array($m['group_id'], $user_groups) || f_legacy_int_equals($m['group_id'], K_USRREG_GROUP)) {
                echo ' selected="selected"';
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
}

if ($regfields['user_agreement'] > 0) {
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">&nbsp;</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo
        '<input type="checkbox" name="user_agreement" id="user_agreement" value="1" title="'
            . $l['w_i_agree']
            . '" />'
            . K_NEWLINE
    ;
    echo
        '<label for="user_agreement"><a href="'
            . K_USRREG_AGREEMENT
            . '" title="'
            . $l['m_new_window_link']
            . '">'
            . $l['w_i_agree']
            . '</a></label></span>'
            . K_NEWLINE
    ;
    echo '</div>' . K_NEWLINE;
}

echo '<div class="row">' . K_NEWLINE;

F_submit_button('add', $l['w_add'], $l['h_add']);

echo '</div>' . K_NEWLINE;
echo f_tce_user_registration_string(f_get_csrf_token_field()) . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_user_registration'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_user_registration_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** @return list<int|string> */
function f_tce_user_registration_groups(mixed $groups): array
{
    /** @var list<int|string> $groups */
    return $groups;
}

function f_tce_user_registration_bool(mixed $value): bool
{
    if (is_array($value)) {
        return $value !== [];
    }

    if (is_object($value) || is_resource($value)) {
        return true;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value) || is_string($value)) {
        return (bool) $value;
    }

    return false;
}

/** @return list<int|string> */
function f_tce_user_registration_allowed_groups(mixed $groups): array
{
    /** @var list<int|string> $groups */
    return $groups;
}

/** @return array{registration_enabled:bool} */
function f_tce_user_registration_access_settings(mixed $settings): array
{
    /** @var array{registration_enabled:bool} $settings */
    return $settings;
}

/** @return object|resource|bool */
function f_tce_user_registration_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array{group_id:int|string,group_name:string}|null */
function f_tce_user_registration_group_row(mixed $row): ?array
{
    /** @var array{group_id:int|string,group_name:string}|null $row */
    return is_array($row) ? $row : null;
}
