<?php

//============================================================+
// File name   : tce_functions_user_select.php
// Begin       : 2001-09-13
// Last Update : 2023-11-30
//
// Description : Functions to display and select registered user.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to display and select registered user.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2001-09-13
 */

/**
 * Display user selection for using F_show_select_user function.
 * @author Nicola Asuni
 * @since 2001-09-13
 * @param $order_field (string) order by column name
 * @param $orderdir (string) oreder direction
 * @param $firstrow (string) number of first row to display
 * @param $rowsperpage (string) number of rows per page
 * @param $group_id (int) id of the group (default = 0 = no specific group selected)
 * @param $andwhere (string) additional SQL WHERE query conditions
 * @param $searchterms (string) search terms
 * @return true
 */
function f_select_user(
    mixed $order_field,
    mixed $orderdir,
    mixed $firstrow,
    mixed $rowsperpage,
    mixed $group_id = 0,
    mixed $andwhere = '',
    mixed $searchterms = '',
): bool {
    global $l;
    require_once '../config/tce_config.php';
    F_show_select_user($order_field, $orderdir, $firstrow, $rowsperpage, $group_id, $andwhere, $searchterms);
    return true;
}

/**
 * Display user selection XHTML table.
 * @author Nicola Asuni
 * @since 2001-09-13
 * @param $order_field (string) Order by column name.
 * @param $orderdir (int) Order direction.
 * @param $firstrow (int) Number of first row to display.
 * @param $rowsperpage (int) Number of rows per page.
 * @param $group_id (int) ID of the group (default = 0 = no specific group selected).
 * @param $andwhere (string) Additional SQL WHERE query conditions.
 * @param $searchterms (string) Search terms.
 * @return bool False in case of an empty database, true otherwise.
 */
function f_show_select_user(
    mixed $order_field,
    mixed $orderdir,
    mixed $firstrow,
    mixed $rowsperpage,
    mixed $group_id = 0,
    mixed $andwhere = '',
    mixed $searchterms = '',
): bool {
    global $l, $db;
    $stringify = static fn(mixed $value): string => is_array($value) ? 'Array' : (string) $value;
    /** @return array<array-key,mixed>|null */
    $row_result = static fn(mixed $row): ?array => is_array($row) ? $row : null;
    /**
     * @var array{
     *     a_meta_charset: string,
     *     a_meta_dir: string,
     *     h_delete: string,
     *     h_firstname: string,
     *     h_group_name: string,
     *     h_lastname: string,
     *     h_level: string,
     *     h_login_name: string,
     *     h_regcode: string,
     *     h_regdate: string,
     *     h_tsv_export: string,
     *     h_xml_export: string,
     *     hp_select_users: string,
     *     m_databasempty: string,
     *     m_delete_confirm: string,
     *     m_search_void: string,
     *     t_all_results_user: string,
     *     w_add: string,
     *     w_check_all: string,
     *     w_delete: string,
     *     w_edit: string,
     *     w_firstname: string,
     *     w_groups: string,
     *     w_lastname: string,
     *     w_level: string,
     *     w_move: string,
     *     w_regcode: string,
     *     w_regdate: string,
     *     w_select: string,
     *     w_tests: string,
     *     w_user: string,
     *     w_users: string
     * } $l
     */
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_page.php';
    require_once '../../shared/code/tce_functions_form.php';
    $filter = '';
    if (($l['a_meta_dir'] <=> 'rtl') === 0) {
        $txtalign = 'right';
        $numalign = 'left';
    } else {
        $txtalign = 'left';
        $numalign = 'right';
    }

    $order_field = $stringify(F_escape_sql($db, $order_field));
    $orderdir = (int) $orderdir;
    $firstrow = (int) $firstrow;
    $rowsperpage = (int) $rowsperpage;
    $group_id = (int) $group_id;
    $andwhere = $stringify($andwhere);
    $searchterms = $stringify($searchterms);
    if (
        empty($order_field)
        || !in_array($order_field, [
            'user_id',
            'user_name',
            'user_password',
            'user_email',
            'user_regdate',
            'user_ip',
            'user_firstname',
            'user_lastname',
            'user_birthdate',
            'user_birthplace',
            'user_regnumber',
            'user_ssn',
            'user_level',
            'user_verifycode',
        ])
    ) {
        $order_field = 'user_lastname,user_firstname';
    }

    if ($orderdir === 0) {
        $nextorderdir = 1;
        $full_order_field = $order_field;
    } else {
        $nextorderdir = 0;
        $full_order_field = $order_field . ' DESC';
    }

    if (!F_count_rows(K_TABLE_USERS)) { // if the table is void (no items) display message
        F_print_error('MESSAGE', $l['m_databasempty']);
        return false;
    }

    /** @var array{session_user_id:int|string,session_user_level:int|string} $session */
    $session = $_SESSION;
    $wherequery = '';
    if ($group_id > 0) {
        $wherequery = ', ' . K_TABLE_USERGROUP . ' WHERE user_id=usrgrp_user_id	AND usrgrp_group_id=' . $group_id . '';
        $filter .= '&amp;group_id=' . $group_id . '';
    }

    if ($wherequery === '') {
        $wherequery = ' WHERE';
    } else {
        $wherequery .= ' AND';
    }

    $wherequery .= ' (user_id>1)';
    if ((int) $session['session_user_level'] < (int) K_AUTH_ADMINISTRATOR) {
        // filter for level
        $wherequery .=
            ' AND ((user_level<'
            . $session['session_user_level']
            . ') OR (user_id='
            . $session['session_user_id']
            . '))';
        // filter for groups
        $wherequery .=
            ' AND user_id IN (SELECT tb.usrgrp_user_id
			FROM '
            . K_TABLE_USERGROUP
            . ' AS ta, '
            . K_TABLE_USERGROUP
            . ' AS tb
			WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
				AND ta.usrgrp_user_id='
            . (int) $session['session_user_id']
            . '
				AND tb.usrgrp_user_id=user_id)';
    }

    if (!empty($andwhere)) {
        $wherequery .= ' AND (' . $andwhere . ')';
    }

    $sql = 'SELECT * FROM ' . K_TABLE_USERS . $wherequery . ' ORDER BY ' . $full_order_field;
    if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
        $sql =
            'SELECT * FROM ('
            . $sql
            . ') WHERE rownum BETWEEN '
            . $firstrow
            . ' AND '
            . ($firstrow + $rowsperpage)
            . '';
    } else {
        $sql .= ' LIMIT ' . $rowsperpage . ' OFFSET ' . $firstrow . '';
    }

    $r = F_db_query($sql, $db);
    if ($r) {
        $m = $row_result(F_db_fetch_array($r));
        if ($m) {
            /** @var array{user_id?:int|string,user_name?:mixed,user_lastname?:mixed,user_firstname?:mixed,user_regnumber?:mixed,user_level?:mixed,user_regdate?:mixed} $m */
            // -- Table structure with links:
            echo '<div class="container">';
            echo '<table class="userselect record-table">' . K_NEWLINE;
            echo '<caption class="sr-only">' . $l['w_users'] . '</caption>' . K_NEWLINE;
            // table header
            echo '<thead>' . K_NEWLINE;
            echo '<tr>' . K_NEWLINE;
            echo '<th scope="col" class="record-select"><input type="checkbox" data-select-all="userid" '
                . 'aria-label="' . $l['w_check_all'] . '" /></th>' . K_NEWLINE;
            if (strlen($searchterms) > 0) {
                $filter .= '&amp;searchterms=' . urlencode($searchterms);
            }

            echo
                F_select_table_header_element(
                    'user_name',
                    $nextorderdir,
                    $l['h_login_name'],
                    $l['w_user'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_lastname',
                    $nextorderdir,
                    $l['h_lastname'],
                    $l['w_lastname'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_firstname',
                    $nextorderdir,
                    $l['h_firstname'],
                    $l['w_firstname'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_regnumber',
                    $nextorderdir,
                    $l['h_regcode'],
                    $l['w_regcode'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_level',
                    $nextorderdir,
                    $l['h_level'],
                    $l['w_level'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_regdate',
                    $nextorderdir,
                    $l['h_regdate'],
                    $l['w_regdate'],
                    $order_field,
                    $filter,
                )
            ;
            echo '<th scope="col" title="' . $l['h_group_name'] . '">' . $l['w_groups'] . '</th>' . K_NEWLINE;
            echo '<th scope="col" title="' . $l['t_all_results_user'] . '">' . $l['w_tests'] . '</th>' . K_NEWLINE;
            echo '</tr>' . K_NEWLINE;
            echo '</thead>' . K_NEWLINE;
            $itemcount = $firstrow;
            do {
                ++$itemcount;
                $user_id = $stringify($m['user_id'] ?? '');
                $edit_url = 'tce_edit_user.php?user_id=' . (int) $user_id;
                echo '<tr class="record-row" data-record-href="'
                    . htmlspecialchars($edit_url, ENT_QUOTES, $l['a_meta_charset']) . '">' . K_NEWLINE;
                echo '<td>';
                echo
                    '<input type="checkbox" name="userid'
                        . $itemcount
                        . '" id="userid'
                        . $itemcount
                        . '" value="'
                        . $user_id
                        . '" title="'
                        . $l['w_select']
                        . '"'
                ;
                if (isset($_REQUEST['checkall']) && f_legacy_int_equals($_REQUEST['checkall'], 1)) {
                    echo ' checked="checked"';
                }

                echo ' />';
                echo '</td>' . K_NEWLINE;
                echo
                    '<td class="record-title" style="text-align:'
                        . $txtalign
                        . ';">&nbsp;<a href="'
                        . $edit_url
                        . '" title="'
                        . $l['w_edit']
                        . '">'
                        . htmlspecialchars($stringify($m['user_name'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</a></td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_lastname'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_firstname'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_regnumber'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo '<td>&nbsp;' . $stringify($m['user_level'] ?? '') . '</td>' . K_NEWLINE;
                echo
                    '<td>&nbsp;'
                        . htmlspecialchars($stringify($m['user_regdate'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                // comma separated list of user's groups
                $grp = '';
                $sqlg =
                    'SELECT *
					FROM '
                    . K_TABLE_GROUPS
                    . ', '
                    . K_TABLE_USERGROUP
                    . '
					WHERE usrgrp_group_id=group_id
						AND usrgrp_user_id='
                    . $user_id
                    . '
					ORDER BY group_name';
                $rg = F_db_query($sqlg, $db);
                if ($rg) {
                    while ($mg = $row_result(F_db_fetch_array($rg))) {
                        /** @var array{group_name:string} $mg */
                        $grp .= $mg['group_name'] . ', ';
                    }
                } else {
                    F_display_db_error();
                }

                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars(substr($grp, 0, -2), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;

                echo
                    '<td><a href="tce_show_result_allusers.php?user_id='
                        . $user_id
                        . '" class="xmlbutton" title="'
                        . $l['t_all_results_user']
                        . '">Результаты</a></td>'
                        . K_NEWLINE
                ;

                echo '</tr>' . K_NEWLINE;
                $m = $row_result(F_db_fetch_array($r));
                if ($m) {
                    /** @var array{user_id?:int|string,user_name?:mixed,user_lastname?:mixed,user_firstname?:mixed,user_regnumber?:mixed,user_level?:mixed,user_regdate?:mixed} $m */
                }
            } while ($m);

            echo '</table>' . K_NEWLINE;

            echo '<br />' . K_NEWLINE;

            echo '<input type="hidden" name="order_field" id="order_field" value="' . $order_field . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="orderdir" id="orderdir" value="' . $orderdir . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="firstrow" id="firstrow" value="' . $firstrow . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="rowsperpage" id="rowsperpage" value="' . $rowsperpage . '" />' . K_NEWLINE;

            echo '<div class="record-bulk-toolbar" data-bulk-toolbar>' . K_NEWLINE;
            echo '<strong><span data-selected-count>0</span> выбрано</strong>' . K_NEWLINE;
            echo '<ul class="record-bulk-actions">';
            if ((int) $session['session_user_level'] >= (int) K_AUTH_DELETE_USERS) {
                // delete user
                echo '<li>';
                F_submit_button(
                    'delete',
                    $l['w_delete'],
                    $l['h_delete'],
                    'onclick="return confirm(\'' . $l['m_delete_confirm'] . '\')"',
                );
                echo '</li>' . K_NEWLINE;
            }

            if ((int) $session['session_user_level'] >= (int) K_AUTH_ADMIN_GROUPS) {
                echo '<li>';
                // add/delete group
                echo F_user_group_select('new_group_id');
                F_submit_button('addgroup', $l['w_add'], $l['w_add']);
                if ((int) $session['session_user_level'] >= (int) K_AUTH_DELETE_GROUPS) {
                    F_submit_button(
                        'delgroup',
                        $l['w_delete'],
                        $l['h_delete'],
                        'onclick="return confirm(\'' . $l['m_delete_confirm'] . '\')"',
                    );
                }

                echo '</li>' . K_NEWLINE;
                if ((int) $session['session_user_level'] >= (int) K_AUTH_MOVE_GROUPS) {
                    // move group
                    echo '<li>';
                    $arr = (($l['a_meta_dir'] <=> 'rtl') === 0) ? '&larr;' : '&rarr;';

                    echo F_user_group_select('from_group_id');
                    echo $arr;
                    echo F_user_group_select('to_group_id');
                    F_submit_button('move', $l['w_move'], $l['w_move']);
                    echo '</li>' . K_NEWLINE;
                }
            }

            echo '</ul></div>' . K_NEWLINE;
            echo '<div class="row"><hr /></div>' . K_NEWLINE;

            // ---------------------------------------------------------------
            // -- page jumper (menu for successive pages)
            if ($rowsperpage > 0) {
                $sql = 'SELECT count(*) AS total FROM ' . K_TABLE_USERS . '' . $wherequery . '';
                $param_array = '&amp;order_field=' . urlencode($order_field) . '';

                if ($orderdir !== 0) {
                    $param_array .= '&amp;orderdir=' . $orderdir . '';
                }

                if ($group_id !== 0) {
                    $param_array .= '&amp;group_id=' . $group_id . '';
                }

                if (!empty($searchterms)) {
                    $param_array .= '&amp;searchterms=' . urlencode($searchterms) . '';
                }

                $param_array .= '&amp;submitted=1';
                F_show_page_navigator($_SERVER['SCRIPT_NAME'], $sql, $firstrow, $rowsperpage, $param_array);
            }

            echo '<div class="row">' . K_NEWLINE;
            echo '<br />';
            echo '<a href="tce_xml_users.php" class="xmlbutton" title="' . $l['h_xml_export'] . '">XML</a> ';
            echo '<a href="tce_xml_users.php?format=JSON" class="xmlbutton" title="JSON">JSON</a> ';
            echo '<a href="tce_tsv_users.php" class="xmlbutton" title="' . $l['h_tsv_export'] . '">TSV</a>';
            echo '</div>' . K_NEWLINE;

            echo '<div class="pagehelp">' . $l['hp_select_users'] . '</div>' . K_NEWLINE;
            echo '</div>' . K_NEWLINE;
        } else {
            F_print_error('MESSAGE', $l['m_search_void']);
        }
    } else {
        F_display_db_error();
    }

    return true;
}

/**
 * Display user selection XHTML table (popup mode).
 * @author Nicola Asuni
 * @since 2012-04-14
 * @param $order_field (string) Order by column name.
 * @param $orderdir (int) Order direction.
 * @param $firstrow (int) Number of first row to display.
 * @param $rowsperpage (int) Number of rows per page.
 * @param $group_id (int) ID of the group (default = 0 = no specific group selected).
 * @param $andwhere (string) Additional SQL WHERE query conditions.
 * @param $searchterms (string) Search terms.
 * @param mixed $cid ID of the calling form field.
 * @return bool False in case of an empty database, true otherwise.
 */
function f_show_select_user_popup(
    mixed $order_field,
    mixed $orderdir,
    mixed $firstrow,
    mixed $rowsperpage,
    mixed $group_id = 0,
    mixed $andwhere = '',
    mixed $searchterms = '',
    mixed $cid = 0,
): bool {
    global $l, $db;
    $stringify = static fn(mixed $value): string => is_array($value) ? 'Array' : (string) $value;
    /** @return array<array-key,mixed>|null */
    $row_result = static fn(mixed $row): ?array => is_array($row) ? $row : null;
    /**
     * @var array{
     *     a_meta_charset: string,
     *     a_meta_dir: string,
     *     h_email: string,
     *     h_firstname: string,
     *     h_group_name: string,
     *     h_lastname: string,
     *     h_level: string,
     *     h_login_name: string,
     *     h_regcode: string,
     *     h_regdate: string,
     *     m_databasempty: string,
     *     m_search_void: string,
     *     w_email: string,
     *     w_firstname: string,
     *     w_groups: string,
     *     w_lastname: string,
     *     w_level: string,
     *     w_regcode: string,
     *     w_regdate: string,
     *     w_select: string,
     *     w_user: string,
     *     w_users: string
     * } $l
     */
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_page.php';
    require_once '../../shared/code/tce_functions_form.php';
    $cid = $stringify($cid);
    $filter = 'cid=' . $cid;
    if (($l['a_meta_dir'] <=> 'rtl') === 0) {
        $txtalign = 'right';
        $numalign = 'left';
    } else {
        $txtalign = 'left';
        $numalign = 'right';
    }

    $order_field = $stringify(F_escape_sql($db, $order_field));
    $orderdir = (int) $orderdir;
    $firstrow = (int) $firstrow;
    $rowsperpage = (int) $rowsperpage;
    $group_id = (int) $group_id;
    $andwhere = $stringify($andwhere);
    $searchterms = $stringify($searchterms);
    if (
        empty($order_field)
        || !in_array($order_field, [
            'user_id',
            'user_name',
            'user_password',
            'user_email',
            'user_regdate',
            'user_ip',
            'user_firstname',
            'user_lastname',
            'user_birthdate',
            'user_birthplace',
            'user_regnumber',
            'user_ssn',
            'user_level',
            'user_verifycode',
        ])
    ) {
        $order_field = 'user_lastname,user_firstname';
    }

    if ($orderdir === 0) {
        $nextorderdir = 1;
        $full_order_field = $order_field;
    } else {
        $nextorderdir = 0;
        $full_order_field = $order_field . ' DESC';
    }

    if (!F_count_rows(K_TABLE_USERS)) { // if the table is void (no items) display message
        F_print_error('MESSAGE', $l['m_databasempty']);
        return false;
    }

    /** @var array{session_user_id:int|string,session_user_level:int|string} $session */
    $session = $_SESSION;
    $wherequery = '';
    if ($group_id > 0) {
        $wherequery = ', ' . K_TABLE_USERGROUP . ' WHERE user_id=usrgrp_user_id	AND usrgrp_group_id=' . $group_id . '';
        $filter .= '&amp;group_id=' . $group_id . '';
    }

    if ($wherequery === '') {
        $wherequery = ' WHERE';
    } else {
        $wherequery .= ' AND';
    }

    $wherequery .= ' (user_id>1)';
    if ((int) $session['session_user_level'] < (int) K_AUTH_ADMINISTRATOR) {
        // filter for level
        $wherequery .=
            ' AND ((user_level<'
            . $session['session_user_level']
            . ') OR (user_id='
            . $session['session_user_id']
            . '))';
        // filter for groups
        $wherequery .=
            ' AND user_id IN (SELECT tb.usrgrp_user_id
			FROM '
            . K_TABLE_USERGROUP
            . ' AS ta, '
            . K_TABLE_USERGROUP
            . ' AS tb
			WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
				AND ta.usrgrp_user_id='
            . (int) $session['session_user_id']
            . '
				AND tb.usrgrp_user_id=user_id)';
    }

    if (!empty($andwhere)) {
        $wherequery .= ' AND (' . $andwhere . ')';
    }

    $sql = 'SELECT * FROM ' . K_TABLE_USERS . $wherequery . ' ORDER BY ' . $full_order_field;
    if (f_legacy_literal_equals(K_DATABASE_TYPE, 'ORACLE')) {
        $sql =
            'SELECT * FROM ('
            . $sql
            . ') WHERE rownum BETWEEN '
            . $firstrow
            . ' AND '
            . ($firstrow + $rowsperpage)
            . '';
    } else {
        $sql .= ' LIMIT ' . $rowsperpage . ' OFFSET ' . $firstrow . '';
    }

    $r = F_db_query($sql, $db);
    if ($r) {
        $m = $row_result(F_db_fetch_array($r));
        if ($m) {
            /** @var array{user_id?:int|string,user_name?:string,user_lastname?:mixed,user_firstname?:mixed,user_email?:mixed,user_regnumber?:mixed,user_level?:mixed,user_regdate?:mixed} $m */
            // -- Table structure with links:
            echo '<div class="container">';
            echo '<table class="userselect" style="font-size:80%;">' . K_NEWLINE;
            echo '<caption class="sr-only">' . $l['w_users'] . '</caption>' . K_NEWLINE;
            // table header
            echo '<thead>' . K_NEWLINE;
            echo '<tr>' . K_NEWLINE;
            if (strlen($searchterms) > 0) {
                $filter .= '&amp;searchterms=' . urlencode($searchterms);
            }

            echo
                F_select_table_header_element(
                    'user_name',
                    $nextorderdir,
                    $l['h_login_name'],
                    $l['w_user'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_lastname',
                    $nextorderdir,
                    $l['h_lastname'],
                    $l['w_lastname'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_firstname',
                    $nextorderdir,
                    $l['h_firstname'],
                    $l['w_firstname'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_email',
                    $nextorderdir,
                    $l['h_email'],
                    $l['w_email'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_regnumber',
                    $nextorderdir,
                    $l['h_regcode'],
                    $l['w_regcode'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_level',
                    $nextorderdir,
                    $l['h_level'],
                    $l['w_level'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'user_regdate',
                    $nextorderdir,
                    $l['h_regdate'],
                    $l['w_regdate'],
                    $order_field,
                    $filter,
                )
            ;
            //echo '<th title="'.$l['h_group_name'].'">'.$l['w_groups'].'</th>'.K_NEWLINE;
            echo '</tr>' . K_NEWLINE;
            echo '</thead>' . K_NEWLINE;
            $itemcount = 0;
            do {
                ++$itemcount;
                $user_id = $stringify($m['user_id'] ?? '');
                // on click the user ID will be returned on the calling form field
                $jsaction = "javascript:var target=window.opener.document.getElementById('" . $cid . "');";
                $jsaction .= 'target.value=' . $user_id . ';';
                // A paginated caller may not have an option for this user loaded yet.
                // Add a temporary one so assigning the value works before onchange submits the form.
                $jsaction .=
                    "if(target.tagName==='SELECT'&&target.value!='"
                    . $user_id
                    . "'){target.add(new Option('', '"
                    . $user_id
                    . "'));target.value='"
                    . $user_id
                    . "';}"
                ;
                $jsaction .= 'target.onchange();';
                $jsaction .= 'window.close(); return false;';
                echo '<tr>' . K_NEWLINE;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;<button type="button" class="linkbtn" onclick="'
                        . $jsaction
                        . '" title="['
                        . $l['w_select']
                        . ']">'
                        . htmlspecialchars($stringify($m['user_name'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</button></td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_lastname'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_firstname'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_email'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($stringify($m['user_regnumber'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo '<td>&nbsp;' . $stringify($m['user_level'] ?? '') . '</td>' . K_NEWLINE;
                echo
                    '<td>&nbsp;'
                        . htmlspecialchars($stringify($m['user_regdate'] ?? ''), ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                /*
                 * // comma separated list of user's groups
                 * $grp = '';
                 * $sqlg = 'SELECT *
                 * FROM '.K_TABLE_GROUPS.', '.K_TABLE_USERGROUP.'
                 * WHERE usrgrp_group_id=group_id
                 * AND usrgrp_user_id='.$m['user_id'].'
                 * ORDER BY group_name';
                 * if ($rg = F_db_query($sqlg, $db)) {
                 * while ($mg = F_db_fetch_array($rg)) {
                 * $grp .= $mg['group_name'].', ';
                 * }
                 * } else {
                 * F_display_db_error();
                 * }
                 * echo '<td style="text-align:'.$txtalign.';">&nbsp;'.htmlspecialchars(substr($grp,0,-2), ENT_NOQUOTES, $l['a_meta_charset']).'</td>'.K_NEWLINE;
                 */

                echo '</tr>' . K_NEWLINE;
                $m = $row_result(F_db_fetch_array($r));
                if ($m) {
                    /** @var array{user_id?:int|string,user_name?:string,user_lastname?:mixed,user_firstname?:mixed,user_email?:mixed,user_regnumber?:mixed,user_level?:mixed,user_regdate?:mixed} $m */
                }
            } while ($m);

            echo '</table>' . K_NEWLINE;
            echo '<input type="hidden" name="order_field" id="order_field" value="' . $order_field . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="orderdir" id="orderdir" value="' . $orderdir . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="firstrow" id="firstrow" value="' . $firstrow . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="rowsperpage" id="rowsperpage" value="' . $rowsperpage . '" />' . K_NEWLINE;

            echo '<div class="row"><hr /></div>' . K_NEWLINE;

            // ---------------------------------------------------------------
            // -- page jumper (menu for successive pages)
            if ($rowsperpage > 0) {
                $sql = 'SELECT count(*) AS total FROM ' . K_TABLE_USERS . '' . $wherequery . '';
                $param_array = '&amp;order_field=' . urlencode($order_field) . '';

                if ($orderdir !== 0) {
                    $param_array .= '&amp;orderdir=' . $orderdir . '';
                }

                if ($group_id !== 0) {
                    $param_array .= '&amp;group_id=' . $group_id . '';
                }

                if (!empty($searchterms)) {
                    $param_array .= '&amp;searchterms=' . urlencode($searchterms) . '';
                }

                $param_array .= '&amp;submitted=1';
                F_show_page_navigator($_SERVER['SCRIPT_NAME'], $sql, $firstrow, $rowsperpage, $param_array);
            }

            //echo '<div class="pagehelp">'.$l['hp_select_users'].'</div>'.K_NEWLINE;
            echo '</div>' . K_NEWLINE;
        } else {
            F_print_error('MESSAGE', $l['m_search_void']);
        }
    } else {
        F_display_db_error();
    }

    return true;
}

/**
 * Return true if the selected test is active for the selected group
 * @param $test_id (int) test ID
 * @param $group_id (int) group ID
 * @return boolean true/false
 * @since 11.1.003 (2010-10-05)
 */
function f_is_test_on_group(mixed $test_id, mixed $group_id): bool
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $sql =
        'SELECT tstgrp_test_id FROM '
        . K_TABLE_TEST_GROUPS
        . ' WHERE tstgrp_test_id='
        . (int) $test_id
        . ' AND tstgrp_group_id='
        . (int) $group_id
        . ' LIMIT 1';
    $r = F_db_query($sql, $db);
    return $r && (bool) F_db_fetch_array($r);
}

/**
 * Return true if the selected user belongs to the selected group
 * @param $user_id (int) user ID
 * @param $group_id (int) group ID
 * @return boolean true/false
 * @since 11.1.003 (2010-10-05)
 */
function f_is_user_on_group(mixed $user_id, mixed $group_id): bool
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $sql =
        'SELECT usrgrp_user_id FROM '
        . K_TABLE_USERGROUP
        . ' WHERE usrgrp_user_id='
        . (int) $user_id
        . ' AND usrgrp_group_id='
        . (int) $group_id
        . ' LIMIT 1';
    $r = F_db_query($sql, $db);
    return $r && (bool) F_db_fetch_array($r);
}

/**
 * Return true if the current user is an administrator or belongs to the group, false otherwise
 * @param $group_id (int) group ID
 * @return boolean true/false
 * @since 11.1.003 (2010-10-05)
 */
function f_is_authorized_editor_for_group(mixed $group_id): mixed
{
    global $l, $db;
    require_once '../config/tce_config.php';
    /** @var array{session_user_id:int|string,session_user_level:int|string} $session */
    $session = $_SESSION;
    if ((int) $session['session_user_level'] >= (int) K_AUTH_ADMINISTRATOR || empty($group_id)) {
        // user is an administrator (belongs to all groups) or empty group
        return true;
    }

    return f_is_user_on_group($session['session_user_id'], $group_id);
}

/**
 * Return true if the current user is authorized to edit the specified user
 * @param $user_id (int) user ID
 * @return boolean true/false
 * @since 11.1.003 (2010-10-05)
 */
function f_is_authorized_editor_for_user(mixed $user_id): bool
{
    global $l, $db;
    require_once '../config/tce_config.php';
    // administrators can edit any user; an empty user ID means a new (not yet persisted) record
    /** @var array{session_user_id:int|string,session_user_level:int|string} $session */
    $session = $_SESSION;
    if ((int) $session['session_user_level'] >= (int) K_AUTH_ADMINISTRATOR || empty($user_id)) {
        return true;
    }

    // a non-administrator editor can only act on a user that shares at least one group
    // with them (mirrors the authorship/group check in f_is_authorized_user); this prevents
    // horizontal-privilege / multi-tenant IDOR on user edit and result import/export.
    $user_id = (int) $user_id;
    $editor_id = (int) $session['session_user_id'];
    return (
        F_count_rows(
            K_TABLE_USERGROUP
            . ' AS ta, '
            . K_TABLE_USERGROUP
            . ' AS tb
		WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
			AND ta.usrgrp_user_id='
            . $user_id
            . '
			AND tb.usrgrp_user_id='
            . $editor_id
            . '
			LIMIT 1',
        ) > 0
    );
}

/**
 * Return the SQL selection query for user groups
 * @param $where (string) filters to add on WHERE clause
 * @return string SQL selection query.
 * @since 11.1.003 (2010-10-05)
 */
function f_user_group_select_sql(mixed $where = ''): string
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $where = is_array($where) ? 'Array' : (string) $where;
    /** @var array{session_user_id:int|string,session_user_level:int|string} $session */
    $session = $_SESSION;
    if ((int) $session['session_user_level'] >= (int) K_AUTH_ADMINISTRATOR) {
        // administrator access to all groups
        $sql = 'SELECT * FROM ' . K_TABLE_GROUPS . '';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
    } else {
        // non-administrator can access only to his/her groups
        $sql = 'SELECT group_id,group_name FROM ' . K_TABLE_GROUPS . ', ' . K_TABLE_USERGROUP . '';
        $sql .= ' WHERE group_id=usrgrp_group_id AND usrgrp_user_id=' . $session['session_user_id'] . '';
        if ($where !== '') {
            $sql .= ' AND ' . $where;
        }
    }

    return $sql . ' ORDER BY group_name';
}

/**
 * Display select box for user groups
 * @param $name (string) name of the select field
 * @return string Select element markup.
 */
function f_user_group_select(mixed $name = 'group_id'): string
{
    global $l, $db;
    /** @return array<array-key,mixed>|null */
    $row_result = static fn(mixed $row): ?array => is_array($row) ? $row : null;
    require_once '../config/tce_config.php';
    /** @var array{a_meta_charset:string,w_group:string} $l */
    $name = is_array($name) ? 'Array' : (string) $name;
    $charset = $l['a_meta_charset'];
    $str = '';
    $str .=
        '<select name="'
        . $name
        . '" id="'
        . $name
        . '" title="'
        . $l['w_group']
        . '" aria-label="'
        . $l['w_group']
        . '">'
        . K_NEWLINE;
    $sql = F_user_group_select_sql();
    $r = F_db_query($sql, $db);
    if ($r) {
        $str .= '<option value="0" style="color:gray" selected="selected">' . $l['w_group'] . '</option>' . K_NEWLINE;
        while ($m = $row_result(F_db_fetch_array($r))) {
            /** @var array{group_id:int|string,group_name:string} $m */
            $str .= '<option value="' . $m['group_id'] . '">';
            $str .=
                ' '
                . htmlspecialchars($m['group_name'], ENT_NOQUOTES, $charset)
                . '&nbsp;</option>'
                . K_NEWLINE;
        }
    } else {
        $str .= '</select>' . K_NEWLINE;
        F_display_db_error();
    }

    return $str . ('</select>' . K_NEWLINE);
}

/**
 * Returns an array containing groups IDs to which the specified user belongs
 * @param $user_id (int) user ID
 * @return array containing user's groups IDs
 */
function f_get_user_groups(mixed $user_id): array
{
    global $l, $db;
    /** @return array<array-key,mixed>|null */
    $row_result = static fn(mixed $row): ?array => is_array($row) ? $row : null;
    require_once '../config/tce_config.php';
    $user_id = (int) $user_id;
    $groups = [];
    $sql = 'SELECT usrgrp_group_id
		FROM ' . K_TABLE_USERGROUP . '
		WHERE usrgrp_user_id=' . $user_id . '';
    $r = F_db_query($sql, $db);
    if ($r) {
        while ($m = $row_result(F_db_fetch_array($r))) {
            /** @var array{usrgrp_group_id:mixed} $m */
            $groups[] = $m['usrgrp_group_id'];
        }
    } else {
        F_display_db_error();
    }

    return $groups;
}

/**
 * Return the user ID from registration number.
 * @return int|string User ID or 0 in case of error.
 * @since 11.3.005 (2012-07-31)
 */
function f_get_uid_from_regnum(mixed $regnum): int|string
{
    global $l, $db;
    /** @return array{user_id:int|string}|null */
    $row_result = static function (mixed $row): ?array {
        if (
            !is_array($row)
            || (!is_int($row['user_id'] ?? null) && !is_string($row['user_id'] ?? null))
        ) {
            return null;
        }

        /** @var array{user_id:int|string} $row */
        return $row;
    };
    require_once '../config/tce_config.php';
    $sql = 'SELECT user_id FROM ' . K_TABLE_USERS . " WHERE user_regnumber='" . F_escape_sql($db, $regnum) . "' LIMIT 1";
    $r = F_db_query($sql, $db);
    if (!$r) {
        return 0;
    }

    $m = $row_result(F_db_fetch_array($r));
    if (!$m) {
        return 0;
    }

    return $m['user_id'];
}
