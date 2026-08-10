<?php

//============================================================+
// File name   : tce_select_users.php
// Begin       : 2001-09-13
// Last Update : 2026-03-01
//
// Description : Display user selection table.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display user selection table.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2001-09-13
 */

require_once '../config/tce_config.php';

$from_group_id = isset($_POST['from_group_id']) ? (int) $_POST['from_group_id'] : 0;
$to_group_id = isset($_POST['to_group_id']) ? (int) $_POST['to_group_id'] : 0;

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_USERS;
require_once '../../shared/code/tce_authorization.php';

/** @var array{
 *     t_user_select:string,
 *     m_authorization_denied:string,
 *     w_group:string,
 *     a_meta_charset:string,
 *     w_search:string,
 *     m_updated:string
 * } $l
 */
/** @var mixed $db */
$thispage_title = $l['t_user_select'];

require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once 'tce_functions_user_select.php';

// set default values
$new_group_id = isset($_REQUEST['new_group_id']) ? (int) $_REQUEST['new_group_id'] : 0;
$group_id = isset($_REQUEST['group_id']) ? (int) $_REQUEST['group_id'] : 0;
$orderdir = isset($_REQUEST['orderdir']) ? (int) $_REQUEST['orderdir'] : 0;
$firstrow = isset($_REQUEST['firstrow']) ? (int) $_REQUEST['firstrow'] : 0;
$rowsperpage = isset($_REQUEST['rowsperpage']) ? (int) $_REQUEST['rowsperpage'] : K_MAX_ROWS_PER_PAGE;
/** @var string $order_field */
$order_field = $_REQUEST['order_field'] ?? 'user_lastname,user_firstname';
/** @var string $searchterms */
$searchterms = $_REQUEST['searchterms'] ?? '';

if (!f_is_authorized_editor_for_group($group_id)) {
    F_print_error('ERROR', $l['m_authorization_denied'], true);
}

echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_userselect">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="group_id">' . $l['w_group'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="group_id" id="group_id" onchange="document.getElementById(\'form_userselect\').submit()">'
        . K_NEWLINE
;

echo '<option value="0"';
if ($group_id === 0) {
    echo ' selected="selected"';
}

echo '>&nbsp;</option>' . K_NEWLINE;
$sql = F_user_group_select_sql();
$r = f_tmf_select_users_query_result(F_db_query($sql, $db));
if ($r) {
    while ($m = f_tmf_select_users_row(F_db_fetch_array($r))) {
        /** @var array{group_id:int|string,group_name:string} $m */
        echo '<option value="' . $m['group_id'] . '"';
        if (f_form_option_is_selected($group_id, $m['group_id'])) {
            echo ' selected="selected"';
        }

        echo '>' . htmlspecialchars($m['group_name'], ENT_NOQUOTES, $l['a_meta_charset']) . '</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

echo
    '<input type="text" name="searchterms" id="searchterms" value="'
        . htmlspecialchars($searchterms, ENT_COMPAT, $l['a_meta_charset'])
        . '" size="20" maxlength="255" title="'
        . $l['w_search']
        . '" aria-label="'
        . $l['w_search']
        . '" />'
;
F_submit_button('search', $l['w_search'], $l['w_search']);
echo '</span></div>' . K_NEWLINE;
// build a search query
$wherequery = '';
if (strlen($searchterms) > 0) {
    $wherequery = '';
    $terms = preg_split("/[\s]+/i", $searchterms); // Get all the words into an array
    if ($terms === false) {
        $terms = [];
    }
    foreach ($terms as $word) {
        $word = F_escape_sql($db, $word);
        $wherequery .= " AND ((user_name LIKE '%" . $word . "%')";
        $wherequery .= " OR (user_email LIKE '%" . $word . "%')";
        $wherequery .= " OR (user_firstname LIKE '%" . $word . "%')";
        $wherequery .= " OR (user_lastname LIKE '%" . $word . "%')";
        $wherequery .= " OR (user_regnumber LIKE '%" . $word . "%')";
        $wherequery .= " OR (user_ssn LIKE '%" . $word . "%'))";
    }

    $wherequery = '(' . substr($wherequery, 5) . ')';
}

echo get_form_noscript_select();

echo '<div class="row"><hr /></div>' . K_NEWLINE;

if (isset($_POST['addgroup'])) {
    $menu_mode = 'addgroup';
} elseif (isset($_POST['delgroup'])) {
    $menu_mode = 'delgroup';
} elseif (isset($_POST['move'])) {
    $menu_mode = 'move';
}

if (isset($menu_mode) && !empty($menu_mode)) {
    /** @var array{session_user_level:int,session_user_id:int} $session */
    $session = $_SESSION;
    $session_user_level = $session['session_user_level'];
    $session_user_id = $session['session_user_id'];
    /** @var int $auth_delete_users */
    $auth_delete_users = K_AUTH_DELETE_USERS;
    /** @var int $auth_admin_groups */
    $auth_admin_groups = K_AUTH_ADMIN_GROUPS;
    /** @var int $auth_delete_groups */
    $auth_delete_groups = K_AUTH_DELETE_GROUPS;
    /** @var int $auth_move_groups */
    $auth_move_groups = K_AUTH_MOVE_GROUPS;
    $istart = 1 + $firstrow;
    $iend = $rowsperpage + $firstrow;
    for ($i = $istart; $i <= $iend; ++$i) {
        // for each selected user
        $keyname = 'userid' . $i;
        if (isset($_POST[$keyname])) {
            $user_id = (int) $_POST[$keyname];
            switch ($menu_mode) {
                case 'delete':
                    if (
                        $session_user_level >= $auth_delete_users
                        && $user_id > 1
                        && !f_form_option_is_selected($user_id, $session_user_id)
                        && f_is_authorized_editor_for_user($user_id)
                    ) {
                        $sql = 'DELETE FROM ' . K_TABLE_USERS . '
							WHERE user_id=' . $user_id . '';
                        $r = f_tmf_select_users_query_result(F_db_query($sql, $db));
                        if (!$r) {
                            F_display_db_error();
                        }
                    }

                    break;
                case 'addgroup':
                    if (
                        $session_user_level >= $auth_admin_groups
                        && $new_group_id > 0
                        && f_is_authorized_editor_for_group($new_group_id)
                    ) {
                        $groups = F_get_user_groups($user_id);
                        if (!in_array($new_group_id, $groups)) {
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
                                . $new_group_id
                                . '\'
								)';
                            $r = f_tmf_select_users_query_result(F_db_query($sql, $db));
                            if (!$r) {
                                F_display_db_error();
                            }
                        }
                    }

                    break;
                case 'delgroup':
                    if (
                        $session_user_level >= $auth_delete_groups
                        && $new_group_id > 0
                        && f_is_authorized_editor_for_group($new_group_id)
                    ) {
                        $sql =
                            'DELETE FROM '
                            . K_TABLE_USERGROUP
                            . '
							WHERE usrgrp_user_id='
                            . $user_id
                            . '
								AND usrgrp_group_id='
                            . $new_group_id
                            . '';
                        $r = f_tmf_select_users_query_result(F_db_query($sql, $db));
                        if (!$r) {
                            F_display_db_error();
                        }
                    }

                    break;
                case 'move':
                    if (
                        $session_user_level >= $auth_move_groups
                        && $from_group_id > 0
                        && f_is_authorized_editor_for_group($from_group_id)
                        && $to_group_id > 0
                        && f_is_authorized_editor_for_group($to_group_id)
                    ) {
                        $groups = F_get_user_groups($user_id);
                        if (!in_array($to_group_id, $groups)) {
                            $sql =
                                'UPDATE '
                                . K_TABLE_USERGROUP
                                . ' SET
								usrgrp_group_id='
                                . $to_group_id
                                . '
								WHERE usrgrp_user_id='
                                . $user_id
                                . '
									AND usrgrp_group_id='
                                . $from_group_id
                                . '
								LIMIT 1';
                            $r = f_tmf_select_users_query_result(F_db_query($sql, $db));
                            if (!$r) {
                                F_display_db_error();
                            }
                        } else {
                            $sql =
                                'DELETE FROM '
                                . K_TABLE_USERGROUP
                                . '
							WHERE usrgrp_user_id='
                                . $user_id
                                . '
								AND usrgrp_group_id='
                                . $from_group_id
                                . '';
                            $r = f_tmf_select_users_query_result(F_db_query($sql, $db));
                            if (!$r) {
                                F_display_db_error();
                            }
                        }
                    }

                    break;
            } // end of switch
        }
    }

    F_print_error('MESSAGE', $l['m_updated']);
}

F_select_user($order_field, $orderdir, $firstrow, $rowsperpage, $group_id, $wherequery, $searchterms);
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** @return array<array-key,mixed>|null */
function f_tmf_select_users_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool */
function f_tmf_select_users_query_result(mixed $result): mixed
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
