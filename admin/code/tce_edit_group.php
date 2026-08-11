<?php

//============================================================+
// File name   : tce_edit_group.php
// Begin       : 2006-03-11
// Last Update : 2026-03-01
//
// Description : Edit users' groups.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display form to edit users' groups.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2006-03-11
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_GROUPS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     t_group_editor:string,
 *     m_authorization_denied:string,
 *     w_name:string,
 *     a_meta_charset:string,
 *     m_delete_confirm:string,
 *     w_delete:string,
 *     h_delete:string,
 *     w_cancel:string,
 *     h_cancel:string,
 *     m_group_deleted:string,
 *     m_form_missing_fields:string,
 *     w_confirm:string,
 *     w_update:string,
 *     m_duplicate_name:string,
 *     m_group_updated:string,
 *     w_group:string,
 *     w_search:string,
 *     h_group_name:string,
 *     h_update:string,
 *     w_add:string,
 *     h_add:string,
 *     w_clear:string,
 *     h_clear:string,
 *     hp_edit_group:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/** @var bool $formstatus */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var array{session_user_id:int|string,session_user_ip:string,session_user_level:int|string} $session */
$session = $_SESSION;

$thispage_title = $l['t_group_editor'];
require_once '../code/tce_page_header.php';

require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_roles.php';
require_once '../code/tce_functions_user_select.php';

$user_id = (int) $session['session_user_id'];
$userip = $session['session_user_ip'];
$userlevel = (int) $session['session_user_level'];

if (isset($_REQUEST['group_id'])) {
    $group_id = (int) $_REQUEST['group_id'];
    if (!f_is_authorized_editor_for_group($group_id)) {
        F_print_error('ERROR', $l['m_authorization_denied']);
        exit();
    }
} else {
    $group_id = 0;
}

$group_name = f_tce_edit_group_request_string($_REQUEST['group_name'] ?? '');
$group_searchterms = trim(f_tce_edit_group_request_string($_REQUEST['group_searchterms'] ?? ''));
$group_name_sl = stripslashes($group_name);
$group_name_db = f_tce_edit_group_string(F_escape_sql($db, $group_name));

// comma separated list of required fields
$_REQUEST['ff_required'] = 'group_name';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_name'], ENT_COMPAT, $l['a_meta_charset']);

switch ($menu_mode) { // process submitted data
    case 'delete':
            // ask confirmation
            if ((int) $session['session_user_level'] < f_tce_edit_group_int(K_AUTH_DELETE_GROUPS)) {
                F_print_error('ERROR', $l['m_authorization_denied']);
                break;
            }
            if (openvsosh_is_default_group($group_id)) {
                F_print_error('ERROR', 'Группа default является системной и не может быть удалена.');
                break;
            }

            F_print_error('WARNING', $l['m_delete_confirm']);
            echo '<div class="confirmbox">' . K_NEWLINE;
            echo
                '<form action="'
                    . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
                    . '" method="post" enctype="multipart/form-data" id="form_delete">'
                    . K_NEWLINE
            ;
            echo '<div>' . K_NEWLINE;
            echo '<input type="hidden" name="group_id" id="group_id" value="' . $group_id . '" />' . K_NEWLINE;
            echo
                '<input type="hidden" name="group_name" id="group_name" value="'
                    . htmlspecialchars($group_name_sl, ENT_QUOTES, $l['a_meta_charset'])
                    . '" />'
                    . K_NEWLINE
            ;
            F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
            F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
            echo '</div>' . K_NEWLINE;
            echo f_get_csrf_token_field() . K_NEWLINE;
            echo '</form>' . K_NEWLINE;
            echo '</div>' . K_NEWLINE;
            break;

    case 'forcedelete':
            // Delete specified user
            if ((int) $session['session_user_level'] < f_tce_edit_group_int(K_AUTH_DELETE_GROUPS)) {
                F_print_error('ERROR', $l['m_authorization_denied']);
                break;
            }
            if (openvsosh_is_default_group($group_id)) {
                F_print_error('ERROR', 'Группа default является системной и не может быть удалена.');
                break;
            }

            if (($_POST['forcedelete'] ?? '') === $l['w_delete']) { //check if delete button has been pushed (redundant check)
                $sql = 'DELETE FROM ' . K_TABLE_GROUPS . ' WHERE group_id=' . $group_id . '';
                $r = f_tce_edit_group_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    $group_id = 0;
                    F_print_error('MESSAGE', '[' . $group_name_sl . '] ' . $l['m_group_deleted']);
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
                if (!F_check_unique(K_TABLE_GROUPS, "group_name='" . $group_name_db . "'", 'group_id', $group_id)) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_GROUPS
                    . ' SET
				group_name=\''
                    . $group_name_db
                    . '\'
				WHERE group_id='
                    . $group_id
                    . '';
                $r = f_tce_edit_group_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', '[' . $group_name_sl . '] ' . $l['m_group_updated']);
                }
            }

            break;

    case 'add':
        // Add user
            if ($formstatus = F_check_form_fields()) { // check submitted form fields
                // check if name is unique
                if (!F_check_unique(K_TABLE_GROUPS, "group_name='" . $group_name_db . "'")) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                $sql = 'INSERT INTO ' . K_TABLE_GROUPS . ' (
				group_name
				) VALUES (
				\'' . $group_name_db . "')";
                $r = f_tce_edit_group_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    /** @var int|numeric-string $group_id */
                    $group_id = F_db_insert_id($db, K_TABLE_GROUPS, 'group_id');
                }

                // add current user to the new group
                $sql =
                    'INSERT INTO '
                    . K_TABLE_USERGROUP
                    . ' (
				usrgrp_user_id,
				usrgrp_group_id
				) VALUES (
				\''
                    . $session['session_user_id']
                    . '\',
				\''
                    . $group_id
                    . '\'
				)';
                $r = f_tce_edit_group_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $group_name = '';
            $group_name_sl = '';
            $group_name_db = '';
            break;

    default:
            break;
} //end of switch

// --- Initialize variables
if ($formstatus && $menu_mode !== 'clear') {
    if ($group_id === 0) {
        $group_id = 0;
        $group_name = '';
        $group_name_sl = '';
        $group_name_db = '';
    } else {
        $sql = f_tce_edit_group_string(
            F_user_group_select_sql('group_id=' . f_tce_edit_group_string($group_id)),
        ) . ' LIMIT 1';
        $r = f_tce_edit_group_query_result(F_db_query($sql, $db));
        if ($r) {
            $m = f_tce_edit_group_row(F_db_fetch_array($r));
            if ($m) {
                /** @var array{group_id:int|string,group_name:string} $m */
                $group_id = (int) $m['group_id'];
                $group_name = $m['group_name'];
            } else {
                $group_name = '';
                $group_name_sl = '';
                $group_name_db = '';
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
        . '" method="post" enctype="multipart/form-data" id="form_groupeditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="group_id">' . $l['w_group'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="group_id" id="group_id" onchange="document.getElementById(\'form_groupeditor\').submit()">'
        . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if ($group_id === 0) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
// Keep only the current group in the initial response. Additional groups are
// loaded in bounded search results instead of rendering the entire table.
if (f_tce_edit_group_is_positive($group_id)) {
    echo
        '<option value="'
            . f_tce_edit_group_string($group_id)
            . '" selected="selected">'
            . htmlspecialchars($group_name, ENT_NOQUOTES, $l['a_meta_charset'])
            . '</option>'
            . K_NEWLINE
    ;
}

if ($group_searchterms !== '') {
    $where = "group_name LIKE '%" . f_tce_edit_group_string(F_escape_sql($db, $group_searchterms)) . "%'";
    $sql = f_tce_edit_group_string(F_user_group_select_sql($where));
    if (f_legacy_literal_equals(f_tce_edit_group_database_type(K_DATABASE_TYPE), 'ORACLE')) {
        $sql = 'SELECT * FROM (' . $sql . ') WHERE rownum <= ' . f_tce_edit_group_int(K_MAX_ROWS_PER_PAGE);
    } else {
        $sql .= ' LIMIT ' . f_tce_edit_group_int(K_MAX_ROWS_PER_PAGE);
    }

    $r = f_tce_edit_group_query_result(F_db_query($sql, $db));
    if ($r) {
        while ($m = f_tce_edit_group_row(F_db_fetch_array($r))) {
            /** @var array{group_id:int|string,group_name:string} $m */
            if ((int) $m['group_id'] === $group_id) {
                continue;
            }

            echo
                '<option value="'
                    . $m['group_id']
                    . '">'
                    . htmlspecialchars($m['group_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                    . '</option>'
                    . K_NEWLINE
            ;
        }
    } else {
        echo '</select></span></div>' . K_NEWLINE;
        F_display_db_error();
    }
}

echo '</select>' . K_NEWLINE;
echo
    '<input type="text" name="group_searchterms" id="group_searchterms" value="'
        . htmlspecialchars($group_searchterms, ENT_COMPAT, $l['a_meta_charset'])
        . '" size="20" maxlength="255" title="'
        . $l['w_search']
        . '" aria-label="'
        . $l['w_search']
        . '" />'
;
F_submit_button('search', $l['w_search'], $l['w_search']);
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo
    get_form_row_text_input(
        'group_name',
        $l['w_name'],
        $l['h_group_name'],
        '',
        $group_name,
        '',
        255,
        false,
        false,
        false,
        '',
    )
;

echo '<div class="row">' . K_NEWLINE;

// show buttons by case
if (f_tce_edit_group_is_positive($group_id)) {
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
    F_submit_button('add', $l['w_add'], $l['h_add']);
    if ((int) $session['session_user_level'] >= f_tce_edit_group_int(K_AUTH_DELETE_GROUPS)) {
        // your account and anonymous user can't be deleted
        F_submit_button('delete', $l['w_delete'], $l['h_delete']);
    }
} else {
    F_submit_button('add', $l['w_add'], $l['h_add']);
}

F_submit_button('clear', $l['w_clear'], $l['h_clear']);

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_group'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve the configured database type without specializing it during static analysis. */
function f_tce_edit_group_database_type(mixed $database_type): string
{
    return (string) $database_type;
}

/** Normalize scalar request fields while rejecting array-shaped input. */
function f_tce_edit_group_request_string(mixed $value): string
{
    return is_scalar($value) ? (string) $value : '';
}

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_edit_group_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve legacy integer conversion without specializing configured constants. */
function f_tce_edit_group_int(mixed $value): int
{
    return (int) $value;
}

/** Preserve legacy numeric comparison for group identifiers, including `false`. */
function f_tce_edit_group_is_positive(int|string|bool $group_id): bool
{
    return $group_id > 0;
}

/**
 * Preserve the active DAL result type across mutually exclusive database implementations.
 *
 * @return object|resource|bool
 */
function f_tce_edit_group_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key, mixed>|null */
function f_tce_edit_group_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}
