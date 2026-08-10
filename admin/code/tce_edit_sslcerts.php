<?php

//============================================================+
// File name   : tce_edit_sslcerts.php
// Begin       : 2013-07-04
// Last Update : 2023-11-30
//
// Description : Upload and edit SSL certificates.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Upload and edit SSL certificates.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2013-07-04
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_SSLCERT;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     t_sslcerts:string,
 *     m_authorization_denied:string,
 *     m_disabled_vs_deleted:string,
 *     m_delete_confirm:string,
 *     a_meta_charset:string,
 *     w_delete:string,
 *     h_delete:string,
 *     w_cancel:string,
 *     h_cancel:string,
 *     m_deleted:string,
 *     m_form_missing_fields:string,
 *     w_confirm:string,
 *     w_update:string,
 *     m_duplicate_name:string,
 *     m_updated:string,
 *     w_sslcert:string,
 *     w_upload_file:string,
 *     h_upload_file:string,
 *     w_name:string,
 *     w_enabled:string,
 *     h_enabled:string,
 *     h_update:string,
 *     w_add:string,
 *     h_add:string,
 *     w_clear:string,
 *     h_clear:string,
 *     hp_import_ssl_certificates:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/** @var bool $formstatus */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var array{session_user_id:int|string,session_user_level:int|string} $session */
$session = $_SESSION;

$thispage_title = $l['t_sslcerts'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_authorization.php';
// set default values
$ssl_enabled = !isset($_REQUEST['ssl_enabled']) || empty($_REQUEST['ssl_enabled'])
    ? false
    : f_get_boolean($_REQUEST['ssl_enabled']);

$ssl_name = isset($_REQUEST['ssl_name']) ? f_tce_ssl_string(utrim($_REQUEST['ssl_name'])) : '';

$ssl_user_id = isset($_REQUEST['ssl_user_id']) ? (int) $_REQUEST['ssl_user_id'] : (int) $session['session_user_id'];

$requested_ssl_id = $_REQUEST['ssl_id'] ?? null;
if (f_tce_ssl_is_positive($requested_ssl_id)) {
    $ssl_id = (int) $requested_ssl_id;
    // check user's authorization for this certificate
    if (!f_is_authorized_user(K_TABLE_SSLCERTS, 'ssl_id', $ssl_id, 'ssl_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
} else {
    $ssl_id = 0;
}

// extract hash and end date from uploaded file
$ssl_hash = '';
$ssl_end_date = '';
if (isset($_FILES['userfile']['name']) && !empty($_FILES['userfile']['name'])) {
    require_once '../code/tce_functions_upload.php';
    // upload file
    $uploadedfile = f_tce_ssl_uploaded_file(f_upload_file('userfile', K_PATH_CACHE));
    if ($uploadedfile !== false) {
        $cert = file_get_contents(K_PATH_CACHE . $uploadedfile);
        $pkcs12 = str_ends_with($uploadedfile, '.pfx');
        [$ssl_hash, $ssl_end_date] = f_tce_ssl_certificate_data(
            f_get_ssl_certificate_hash(f_tce_ssl_string($cert), $pkcs12),
        );
        //remove certificate file
        unlink(K_PATH_CACHE . $uploadedfile);
    }
}

switch ($menu_mode) {
    case 'delete':
            // check if this record is used
            if (!F_check_unique(K_TABLE_TEST_SSLCERTS, 'tstssl_ssl_id=' . $ssl_id . '')) {
                //this record will be only disabled and not deleted because it's used
                $sql = 'UPDATE ' . K_TABLE_QUESTIONS . ' SET
				ssl_enabled=\'0\'
				WHERE ssl_id=' . $ssl_id . '';
                $r = f_tce_ssl_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error();
                }

                F_print_error('WARNING', $l['m_disabled_vs_deleted']);
            } else {
                // ask confirmation
                F_print_error('WARNING', $l['m_delete_confirm']);
                echo '<div class="confirmbox">' . K_NEWLINE;
                echo
                    '<form action="'
                        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
                        . '" method="post" enctype="multipart/form-data" id="form_delete">'
                        . K_NEWLINE
                ;
                echo '<div>' . K_NEWLINE;
                echo '<input type="hidden" name="ssl_id" id="ssl_id" value="' . $ssl_id . '" />' . K_NEWLINE;
                echo
                    '<input type="hidden" name="ssl_name" id="ssl_name" value="'
                        . htmlspecialchars($ssl_name, ENT_QUOTES, $l['a_meta_charset'])
                        . '" />'
                        . K_NEWLINE
                ;
                F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
                F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
                echo '</div>' . K_NEWLINE;
                echo f_get_csrf_token_field() . K_NEWLINE;
                echo '</form>' . K_NEWLINE;
                echo '</div>' . K_NEWLINE;
            }

            break;

    case 'forcedelete':
            if (($_POST['forcedelete'] ?? '') === $l['w_delete']) { //check if delete button has been pushed (redundant check)
                $sql = 'DELETE FROM ' . K_TABLE_SSLCERTS . ' WHERE ssl_id=' . $ssl_id . '';
                $r = f_tce_ssl_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    $ssl_id = false;
                    F_print_error('MESSAGE', $ssl_name . ': ' . $l['m_deleted']);
                }
            }

            break;

    case 'update':
        // Update
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
                    K_TABLE_SSLCERTS,
                    "ssl_name='" . f_tce_ssl_string(F_escape_sql($db, $ssl_name)) . "'",
                    'ssl_id',
                    $ssl_id,
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                if ((int) $session['session_user_level'] >= f_tce_ssl_int(K_AUTH_ADMINISTRATOR)) {
                    $ssl_user_id = (int) $ssl_user_id;
                } else {
                    $ssl_user_id = (int) $session['session_user_id'];
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_SSLCERTS
                    . ' SET
				ssl_name=\''
                    . f_tce_ssl_string(F_escape_sql($db, $ssl_name))
                    . '\',
				ssl_enabled=\''
                    . (int) $ssl_enabled
                    . '\',
				ssl_user_id=\''
                    . $ssl_user_id
                    . '\'
				WHERE ssl_id='
                    . $ssl_id
                    . '';
                $r = f_tce_ssl_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', $l['m_updated']);
                }
            }

            break;

    case 'add':
        // Add
            if (($formstatus = F_check_form_fields()) && strlen($ssl_hash) === 32) {
                // check if name is unique
                if (!F_check_unique(
                    K_TABLE_SSLCERTS,
                    "ssl_name='" . f_tce_ssl_string(F_escape_sql($db, $ssl_name)) . "'",
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                if ((int) $session['session_user_level'] >= f_tce_ssl_int(K_AUTH_ADMINISTRATOR)) {
                    $ssl_user_id = (int) $ssl_user_id;
                } else {
                    $ssl_user_id = (int) $session['session_user_id'];
                }

                $sql =
                    'INSERT INTO '
                    . K_TABLE_SSLCERTS
                    . ' (
				ssl_name,
				ssl_hash,
				ssl_end_date,
				ssl_enabled,
				ssl_user_id
				) VALUES (
				\''
                    . f_tce_ssl_string(F_escape_sql($db, $ssl_name))
                    . '\',
				\''
                    . f_tce_ssl_string(F_escape_sql($db, $ssl_hash))
                    . '\',
				\''
                    . f_tce_ssl_string(F_escape_sql($db, $ssl_end_date))
                    . '\',
				\''
                    . (int) $ssl_enabled
                    . '\',
				\''
                    . (int) $ssl_user_id
                    . '\'
				)';
                $r = f_tce_ssl_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    /** @var int|numeric-string $ssl_id */
                    $ssl_id = F_db_insert_id($db, K_TABLE_SSLCERTS, 'ssl_id');
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $ssl_name = '';
            $ssl_hash = '';
            $ssl_end_date = '';
            $ssl_enabled = true;
            $ssl_user_id = (int) $session['session_user_id'];
            break;

    default:
            break;
} //end of switch

// --- Initialize variables
if ($formstatus && $menu_mode !== 'clear') {
    if ($ssl_id === 0) {
        $ssl_id = 0;
        $ssl_name = '';
        $ssl_hash = '';
        $ssl_end_date = '';
        $ssl_enabled = true;
        $ssl_user_id = (int) $session['session_user_id'];
    } else {
        $sql =
            'SELECT * FROM '
            . K_TABLE_SSLCERTS
            . ' WHERE ssl_id='
            . f_tce_ssl_string($ssl_id)
            . ' LIMIT 1';
        $r = f_tce_ssl_query_result(F_db_query($sql, $db));
        if ($r) {
            $m = f_tce_ssl_row(F_db_fetch_array($r));
            if ($m) {
                /** @var array{ssl_id:int|string,ssl_name:string,ssl_hash:string,ssl_end_date:string,ssl_enabled:mixed,ssl_user_id:int|string} $m */
                $ssl_id = (int) $m['ssl_id'];
                $ssl_name = $m['ssl_name'];
                $ssl_hash = $m['ssl_hash'];
                $ssl_end_date = $m['ssl_end_date'];
                $ssl_enabled = f_get_boolean($m['ssl_enabled']);
                $ssl_user_id = (int) $m['ssl_user_id'];
            } else {
                $ssl_name = '';
                $ssl_hash = '';
                $ssl_end_date = '';
                $ssl_enabled = true;
                $ssl_user_id = (int) $session['session_user_id'];
            }
        } else {
            F_display_db_error();
        }
    }
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_importsslcert">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="ssl_id">' . $l['w_sslcert'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="ssl_id" id="ssl_id" onchange="document.getElementById(\'form_importsslcert\').submit()" title="'
        . $l['w_sslcert']
        . '">'
        . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if ($ssl_id === 0) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
$sql = 'SELECT * FROM ' . K_TABLE_SSLCERTS . ' ORDER BY ssl_name';
$r = f_tce_ssl_query_result(F_db_query($sql, $db));
if ($r) {
    $countitem = 1;
    while ($m = f_tce_ssl_row(F_db_fetch_array($r))) {
        /** @var array{ssl_id:int|string,ssl_name:string,ssl_end_date:string} $m */
        echo '<option value="' . $m['ssl_id'] . '"';
        if ((int) $m['ssl_id'] === $ssl_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. [' . $m['ssl_id'] . ']';
        echo ' ' . htmlspecialchars($m['ssl_name'], ENT_NOQUOTES, $l['a_meta_charset']);
        echo ' (' . htmlspecialchars($m['ssl_end_date'], ENT_NOQUOTES, $l['a_meta_charset']) . ')';
        echo '&nbsp;</option>' . K_NEWLINE;
        ++$countitem;
    }

    if ($countitem === 1) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo get_form_row_text_input('ssl_name', $l['w_name'], $l['w_name'], '', $ssl_name, '', 255, false, false, false, '');

if (!f_tce_ssl_is_positive($ssl_id)) {
    echo '<div class="row">' . K_NEWLINE;
    echo '<span class="label">' . K_NEWLINE;
    echo '<label for="userfile">' . $l['w_upload_file'] . '</label>' . K_NEWLINE;
    echo '</span>' . K_NEWLINE;
    echo '<span class="formw">' . K_NEWLINE;
    echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . K_MAX_UPLOAD_SIZE . '" />' . K_NEWLINE;
    echo
        '<input type="file" name="userfile" id="userfile" size="20" title="' . $l['h_upload_file'] . '" />' . K_NEWLINE
    ;
    echo '</span>' . K_NEWLINE;
    echo '&nbsp;' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

echo get_form_row_checkbox('ssl_enabled', $l['w_enabled'], $l['h_enabled'], '', 1, $ssl_enabled, false, '');

echo '<div class="row">' . K_NEWLINE;

// show buttons by case
if (f_tce_ssl_is_positive($ssl_id)) {
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
    //F_submit_button('add', $l['w_add'], $l['h_add']);
    F_submit_button('delete', $l['w_delete'], $l['h_delete']);
} else {
    F_submit_button('add', $l['w_add'], $l['h_add']);
}

F_submit_button('clear', $l['w_clear'], $l['h_clear']);

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_import_ssl_certificates'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

// ---------------------------------------------------------------------

function f_tce_ssl_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_ssl_int(mixed $value): int
{
    return (int) $value;
}

function f_tce_ssl_is_positive(mixed $value): bool
{
    return f_tce_ssl_int($value) > 0;
}

function f_tce_ssl_uploaded_file(mixed $uploaded_file): string|false
{
    return is_string($uploaded_file) ? $uploaded_file : false;
}

/** @return array{0:string,1:string} */
function f_tce_ssl_certificate_data(mixed $certificate_data): array
{
    if (!is_array($certificate_data)) {
        return ['', ''];
    }

    return [
        f_tce_ssl_string($certificate_data[0] ?? ''),
        f_tce_ssl_string($certificate_data[1] ?? ''),
    ];
}

/** @return object|resource|bool */
function f_tce_ssl_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key,mixed>|null */
function f_tce_ssl_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}
