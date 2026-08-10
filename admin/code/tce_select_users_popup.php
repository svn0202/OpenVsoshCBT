<?php

//============================================================+
// File name   : tce_select_users_popup.php
// Begin       : 2012-04-14
// Last Update : 2023-11-30
//
// Description : Display user selection table on popup window.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display user selection table on popup window.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2012-04-14
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_USERS;
require_once '../../shared/code/tce_authorization.php';

/** @var array{
 *     t_user_select:string,
 *     m_authorization_denied:string,
 *     w_group:string,
 *     a_meta_charset:string,
 *     w_search:string
 * } $l
 */
/** @var mixed $db */
$thispage_title = $l['t_user_select'];

require_once '../code/tce_page_header_popup.php';
require_once '../../shared/code/tce_functions_form.php';
require_once 'tce_functions_user_select.php';

$order_field = $_REQUEST['order_field'] ?? 'user_lastname,user_firstname';
$orderdir = isset($_REQUEST['orderdir']) ? (int) $_REQUEST['orderdir'] : 0;
$firstrow = isset($_REQUEST['firstrow']) ? (int) $_REQUEST['firstrow'] : 0;
$rowsperpage = isset($_REQUEST['rowsperpage']) ? (int) $_REQUEST['rowsperpage'] : K_MAX_ROWS_PER_PAGE;
/** @var string $searchterms */
$searchterms = $_REQUEST['searchterms'] ?? '';

/** @var string $cid_request */
$cid_request = $_REQUEST['cid'] ?? '';
$cid = preg_replace('/[^a-z0-9_]/', '', $cid_request) ?? '';

// ID of the calling form field
/** @var string $uids_request */
$uids_request = $_REQUEST['uids'] ?? '';
$uids = preg_replace('/[^x0-9]/', '', $uids_request) ?? '';

// selected user IDs
$group_id = isset($_REQUEST['group_id']) ? (int) $_REQUEST['group_id'] : 0;

if (!f_is_authorized_editor_for_group($group_id)) {
    F_print_error('ERROR', $l['m_authorization_denied'], true);
}

echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_userselect">'
        . K_NEWLINE
;

echo '<input type="hidden" name="cid" id="cid" value="' . $cid . '" />' . K_NEWLINE;
echo '<input type="hidden" name="uids" id="uids" value="' . $uids . '" />' . K_NEWLINE;

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
$r = f_tmf_select_users_popup_query_result(F_db_query($sql, $db));
if ($r) {
    while ($m = f_tmf_select_users_popup_row(F_db_fetch_array($r))) {
        /** @var array{group_id:int|string,group_name:string} $m */
        echo '<option value="' . $m['group_id'] . '"';
        if ((int) $m['group_id'] === $group_id) {
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

// select only specified User IDs
if (!empty($uids)) {
    $uid_list = '';
    $uids = explode('x', $uids);
    foreach ($uids as $id) {
        $uid_list .= ',' . (int) $id;
    }

    if ($wherequery !== '') {
        $wherequery .= ' AND ';
    }

    $wherequery .= '(user_id IN (' . substr($uid_list, 1) . '))';
}

echo get_form_noscript_select();

echo '<div class="row"><hr /></div>' . K_NEWLINE;

F_show_select_user_popup($order_field, $orderdir, $firstrow, $rowsperpage, $group_id, $wherequery, $searchterms, $cid);
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;

require_once '../code/tce_page_footer_popup.php';

/** @return array<array-key,mixed>|null */
function f_tmf_select_users_popup_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}

/** @return \mysqli_result|\PgSql\Result|resource|bool */
function f_tmf_select_users_popup_query_result(mixed $result): mixed
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
