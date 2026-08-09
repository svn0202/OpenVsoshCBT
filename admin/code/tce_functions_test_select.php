<?php

//============================================================+
// File name   : tce_functions_test_select.php
// Begin       : 2012-12-02
// Last Update : 2023-11-30
//
// Description : Functions to display and select tests.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to display and select tests.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2012-12-02
 */

/**
 * Display test selection for using F_show_select_test function.
 * @author Nicola Asuni
 * @param $order_field (string) order by column name
 * @param $orderdir (string) oreder direction
 * @param $firstrow (string) number of first row to display
 * @param $rowsperpage (string) number of rows per page
 * @param $andwhere (string) additional SQL WHERE query conditions
 * @param $searchterms (string) search terms
 * @return true
 */
function f_select_test($order_field, $orderdir, $firstrow, $rowsperpage, $andwhere = '', $searchterms = '')
{
    global $l;
    require_once '../config/tce_config.php';
    F_show_select_test($order_field, $orderdir, $firstrow, $rowsperpage, $andwhere, $searchterms);
    return true;
}

/**
 * Display test selection XHTML table.
 * @author Nicola Asuni
 * @param $order_field (string) Order by column name.
 * @param $orderdir (int) Order direction.
 * @param $firstrow (int) Number of first row to display.
 * @param $rowsperpage (int) Number of rows per page.
 * @param $andwhere (string) Additional SQL WHERE query conditions.
 * @param $searchterms (string) Search terms.
 * @return false in case of empty database, true otherwise
 */
function F_show_select_test($order_field, $orderdir, $firstrow, $rowsperpage, $andwhere = '', $searchterms = '')
{
    global $l, $db;
    /**
     * @var array{
     *     a_meta_charset: string,
     *     a_meta_dir: string,
     *     h_delete: string,
     *     h_test_description: string,
     *     h_test_name: string,
     *     hp_select_tests: string,
     *     m_databasempty: string,
     *     m_delete_confirm: string,
     *     m_search_void: string,
     *     w_check_all: string,
     *     w_datetime_format: string,
     *     w_delete: string,
     *     w_description: string,
     *     w_edit: string,
     *     w_lock: string,
     *     w_name: string,
     *     w_select: string,
     *     w_tests: string,
     *     w_time_begin: string,
     *     w_time_end: string,
     *     w_unlock: string
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

    $order_field = F_escape_sql($db, $order_field);
    $orderdir = (int) $orderdir;
    $firstrow = (int) $firstrow;
    $rowsperpage = (int) $rowsperpage;
    if (
        empty($order_field)
        || !in_array($order_field, [
            'test_name',
            'test_description',
            'test_begin_time',
            'test_end_time',
            'test_duration_time',
            'test_ip_range',
            'test_results_to_users',
            'test_report_to_users',
            'test_score_right',
            'test_score_wrong',
            'test_score_unanswered',
            'test_max_score',
            'test_user_id',
            'test_score_threshold',
            'test_random_questions_select',
            'test_random_questions_order',
            'test_questions_order_mode',
            'test_random_answers_select',
            'test_random_answers_order',
            'test_answers_order_mode',
            'test_comment_enabled',
            'test_menu_enabled',
            'test_noanswer_enabled',
            'test_mcma_radio',
            'test_repeatable',
            'test_mcma_partial_score',
            'test_logout_on_timeout',
        ])
    ) {
        $order_field = 'test_begin_time DESC,test_name';
    }

    if ($orderdir === 0) {
        $nextorderdir = 1;
        $full_order_field = $order_field;
    } else {
        $nextorderdir = 0;
        $full_order_field = $order_field . ' DESC';
    }

    if (!F_count_rows(K_TABLE_TESTS)) { // if the table is void (no items) display message
        F_print_error('MESSAGE', $l['m_databasempty']);
        return false;
    }

    $wherequery = '';
    if ($wherequery === '') {
        $wherequery = ' WHERE';
    } else {
        $wherequery .= ' AND';
    }

    $wherequery .= ' (test_id>0)';
    if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
        $wherequery .= ' AND test_user_id IN (' . F_getAuthorizedUsers($_SESSION['session_user_id']) . ')';
    }

    if (!empty($andwhere)) {
        $wherequery .= ' AND (' . $andwhere . ')';
    }

    $sql = 'SELECT * FROM ' . K_TABLE_TESTS . $wherequery . ' ORDER BY ' . $full_order_field;
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

    if ($r = F_db_query($sql, $db)) {
        if ($m = F_db_fetch_array($r)) {
            // -- Table structure with links:
            echo '<div class="container">';
            echo '<table class="userselect record-table">' . K_NEWLINE;
            echo '<caption class="sr-only">' . $l['w_tests'] . '</caption>' . K_NEWLINE;
            // table header
            echo '<thead>' . K_NEWLINE;
            echo '<tr>' . K_NEWLINE;
            echo '<th scope="col" class="record-select"><input type="checkbox" data-select-all="testid" '
                . 'aria-label="' . $l['w_check_all'] . '" /></th>' . K_NEWLINE;
            if (strlen($searchterms) > 0) {
                $filter .= '&amp;searchterms=' . urlencode($searchterms);
            }

            echo
                F_select_table_header_element(
                    'test_begin_time',
                    $nextorderdir,
                    $l['w_time_begin'] . ' ' . $l['w_datetime_format'],
                    $l['w_time_begin'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'test_end_time',
                    $nextorderdir,
                    $l['w_time_end'] . ' ' . $l['w_datetime_format'],
                    $l['w_time_end'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'test_name',
                    $nextorderdir,
                    $l['h_test_name'],
                    $l['w_name'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'test_description',
                    $nextorderdir,
                    $l['h_test_description'],
                    $l['w_description'],
                    $order_field,
                    $filter,
                )
            ;
            echo '<th scope="col">Статус</th>' . K_NEWLINE;
            echo '</tr>' . K_NEWLINE;
            echo '</thead>' . K_NEWLINE;
            $itemcount = $firstrow;
            do {
                ++$itemcount;
                $edit_url = 'tce_edit_test.php?test_id=' . (int) $m['test_id'];
                $begin_time = strtotime((string) $m['test_begin_time']);
                $end_time = strtotime((string) $m['test_end_time']);
                $is_locked = substr((string) $m['test_end_time'], 0, 1) < substr(date('Y'), 0, 1);
                if ($is_locked) {
                    $status_key = 'locked';
                    $status_label = 'Заблокировано';
                } elseif ($begin_time !== false && $begin_time > time()) {
                    $status_key = 'upcoming';
                    $status_label = 'Запланировано';
                } elseif ($end_time !== false && $end_time < time()) {
                    $status_key = 'closed';
                    $status_label = 'Завершено';
                } else {
                    $status_key = 'active';
                    $status_label = 'Идёт сейчас';
                }
                echo '<tr class="record-row" data-record-href="'
                    . htmlspecialchars($edit_url, ENT_QUOTES, $l['a_meta_charset']) . '">' . K_NEWLINE;
                echo '<td>';
                echo
                    '<input type="checkbox" name="testid'
                        . $itemcount
                        . '" id="testid'
                        . $itemcount
                        . '" value="'
                        . $m['test_id']
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
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($m['test_begin_time'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($m['test_end_time'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo
                    '<td class="record-title" style="text-align:'
                        . $txtalign
                        . ';">&nbsp;<a href="'
                        . $edit_url
                        . '" title="'
                        . $l['w_edit']
                        . '">'
                        . htmlspecialchars($m['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</a></td>'
                        . K_NEWLINE
                ;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($m['test_description'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo '<td><span class="record-status record-status-' . $status_key . '">'
                    . $status_label . '</span></td>' . K_NEWLINE;
                echo '</tr>' . K_NEWLINE;
            } while ($m = F_db_fetch_array($r));

            echo '</table>' . K_NEWLINE;

            echo '<br />' . K_NEWLINE;

            echo '<input type="hidden" name="order_field" id="order_field" value="' . $order_field . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="orderdir" id="orderdir" value="' . $orderdir . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="firstrow" id="firstrow" value="' . $firstrow . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="rowsperpage" id="rowsperpage" value="' . $rowsperpage . '" />' . K_NEWLINE;

            echo '<div class="record-bulk-toolbar" data-bulk-toolbar>' . K_NEWLINE;
            echo '<strong><span data-selected-count>0</span> выбрано</strong>' . K_NEWLINE;
            // delete user
            echo '<div class="record-bulk-actions">';
            F_submit_button(
                'delete',
                $l['w_delete'],
                $l['h_delete'],
                'onclick="return confirm(\'' . $l['m_delete_confirm'] . '\')"',
            );
            F_submit_button('lock', $l['w_lock'], $l['w_lock']);
            F_submit_button('unlock', $l['w_unlock'], $l['w_unlock']);
            echo '</div></div>' . K_NEWLINE;
            echo '<div class="row"><hr /></div>' . K_NEWLINE;

            // ---------------------------------------------------------------
            // -- page jumper (menu for successive pages)
            if ($rowsperpage > 0) {
                $sql = 'SELECT count(*) AS total FROM ' . K_TABLE_TESTS . '' . $wherequery . '';
                if (!empty($order_field)) {
                    $param_array = '&amp;order_field=' . urlencode($order_field) . '';
                }

                if ($orderdir !== 0) {
                    $param_array .= '&amp;orderdir=' . $orderdir . '';
                }

                if (!empty($searchterms)) {
                    $param_array .= '&amp;searchterms=' . urlencode($searchterms) . '';
                }

                $param_array .= '&amp;submitted=1';
                F_show_page_navigator($_SERVER['SCRIPT_NAME'], $sql, $firstrow, $rowsperpage, $param_array);
            }

            echo '<div class="row">' . K_NEWLINE;
            echo '</div>' . K_NEWLINE;

            echo '<div class="pagehelp">' . $l['hp_select_tests'] . '</div>' . K_NEWLINE;
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
 * @param $andwhere (string) Additional SQL WHERE query conditions.
 * @param $searchterms (string) Search terms.
 * @param string $cid ID of the calling form field.
 * @return false in case of empty database, true otherwise
 */
function F_show_select_test_popup(
    $order_field,
    $orderdir,
    $firstrow,
    $rowsperpage,
    $andwhere = '',
    $searchterms = '',
    $cid = 0,
) {
    global $l, $db;
    /**
     * @var array{
     *     a_meta_charset: string,
     *     a_meta_dir: string,
     *     h_test_description: string,
     *     h_test_name: string,
     *     m_databasempty: string,
     *     m_search_void: string,
     *     w_datetime_format: string,
     *     w_description: string,
     *     w_name: string,
     *     w_select: string,
     *     w_tests: string,
     *     w_time_begin: string,
     *     w_time_end: string
     * } $l
     */
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_page.php';
    require_once '../../shared/code/tce_functions_form.php';
    $filter = 'cid=' . $cid;
    if (($l['a_meta_dir'] <=> 'rtl') === 0) {
        $txtalign = 'right';
        $numalign = 'left';
    } else {
        $txtalign = 'left';
        $numalign = 'right';
    }

    $order_field = F_escape_sql($db, $order_field);
    $orderdir = (int) $orderdir;
    $firstrow = (int) $firstrow;
    $rowsperpage = (int) $rowsperpage;
    if (
        empty($order_field)
        || !in_array($order_field, [
            'test_name',
            'test_description',
            'test_begin_time',
            'test_end_time',
            'test_duration_time',
            'test_ip_range',
            'test_results_to_users',
            'test_report_to_users',
            'test_score_right',
            'test_score_wrong',
            'test_score_unanswered',
            'test_max_score',
            'test_user_id',
            'test_score_threshold',
            'test_random_questions_select',
            'test_random_questions_order',
            'test_questions_order_mode',
            'test_random_answers_select',
            'test_random_answers_order',
            'test_answers_order_mode',
            'test_comment_enabled',
            'test_menu_enabled',
            'test_noanswer_enabled',
            'test_mcma_radio',
            'test_repeatable',
            'test_mcma_partial_score',
            'test_logout_on_timeout',
        ])
    ) {
        $order_field = 'test_begin_time DESC,test_name';
    }

    if ($orderdir === 0) {
        $nextorderdir = 1;
        $full_order_field = $order_field;
    } else {
        $nextorderdir = 0;
        $full_order_field = $order_field . ' DESC';
    }

    if (!F_count_rows(K_TABLE_TESTS)) { // if the table is void (no items) display message
        F_print_error('MESSAGE', $l['m_databasempty']);
        return false;
    }

    $wherequery = '';
    if ($wherequery === '') {
        $wherequery = ' WHERE';
    } else {
        $wherequery .= ' AND';
    }

    $wherequery .= ' (test_id>0)';
    if ($_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
        $wherequery .= ' AND test_user_id IN (' . F_getAuthorizedUsers($_SESSION['session_user_id']) . ')';
    }

    if (!empty($andwhere)) {
        $wherequery .= ' AND (' . $andwhere . ')';
    }

    $sql = 'SELECT * FROM ' . K_TABLE_TESTS . $wherequery . ' ORDER BY ' . $full_order_field;
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

    if ($r = F_db_query($sql, $db)) {
        if ($m = F_db_fetch_array($r)) {
            // -- Table structure with links:
            echo '<div class="container">';
            echo '<table class="userselect" style="font-size:80%;">' . K_NEWLINE;
            echo '<caption class="sr-only">' . $l['w_tests'] . '</caption>' . K_NEWLINE;
            // table header
            echo '<thead>' . K_NEWLINE;
            echo '<tr>' . K_NEWLINE;
            if (strlen($searchterms) > 0) {
                $filter .= '&amp;searchterms=' . urlencode($searchterms);
            }

            echo
                F_select_table_header_element(
                    'test_begin_time',
                    $nextorderdir,
                    $l['w_time_begin'] . ' ' . $l['w_datetime_format'],
                    $l['w_time_begin'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'test_end_time',
                    $nextorderdir,
                    $l['w_time_end'] . ' ' . $l['w_datetime_format'],
                    $l['w_time_end'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'test_name',
                    $nextorderdir,
                    $l['h_test_name'],
                    $l['w_name'],
                    $order_field,
                    $filter,
                )
            ;
            echo
                F_select_table_header_element(
                    'test_description',
                    $nextorderdir,
                    $l['h_test_description'],
                    $l['w_description'],
                    $order_field,
                    $filter,
                )
            ;
            echo '</tr>' . K_NEWLINE;
            echo '</thead>' . K_NEWLINE;
            $itemcount = 0;
            do {
                ++$itemcount;
                // on click the user ID will be returned on the calling form field
                $jsaction =
                    "javascript:window.opener.document.getElementById('" . $cid . "').value=" . $m['test_id'] . ';';
                $jsaction .= "window.opener.document.getElementById('" . $cid . "').onchange();";
                $jsaction .= 'window.close(); return false;';
                echo '<tr>' . K_NEWLINE;
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($m['test_begin_time'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                // @mago-expect analysis:invalid-array-access -- active DAL fetches test rows as arrays
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($m['test_end_time'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                // @mago-expect analysis:invalid-array-access -- active DAL fetches test rows as arrays
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;<button type="button" class="linkbtn" onclick="'
                        . $jsaction
                        . '" title="['
                        . $l['w_select']
                        . ']">'
                        . htmlspecialchars($m['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</button></td>'
                        . K_NEWLINE
                ;
                // @mago-expect analysis:invalid-array-access -- active DAL fetches test rows as arrays
                echo
                    '<td style="text-align:'
                        . $txtalign
                        . ';">&nbsp;'
                        . htmlspecialchars($m['test_description'], ENT_NOQUOTES, $l['a_meta_charset'])
                        . '</td>'
                        . K_NEWLINE
                ;
                echo '</tr>' . K_NEWLINE;
            } while ($m = F_db_fetch_array($r));

            echo '</table>' . K_NEWLINE;
            echo '<input type="hidden" name="order_field" id="order_field" value="' . $order_field . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="orderdir" id="orderdir" value="' . $orderdir . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="firstrow" id="firstrow" value="' . $firstrow . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="rowsperpage" id="rowsperpage" value="' . $rowsperpage . '" />' . K_NEWLINE;

            echo '<div class="row"><hr /></div>' . K_NEWLINE;

            // ---------------------------------------------------------------
            // -- page jumper (menu for successive pages)
            if ($rowsperpage > 0) {
                $sql = 'SELECT count(*) AS total FROM ' . K_TABLE_TESTS . '' . $wherequery . '';
                if (!empty($order_field)) {
                    $param_array = '&amp;order_field=' . urlencode($order_field) . '';
                }

                if ($orderdir !== 0) {
                    $param_array .= '&amp;orderdir=' . $orderdir . '';
                }

                if (!empty($searchterms)) {
                    $param_array .= '&amp;searchterms=' . urlencode($searchterms) . '';
                }

                $param_array .= '&amp;submitted=1';
                F_show_page_navigator($_SERVER['SCRIPT_NAME'], $sql, $firstrow, $rowsperpage, $param_array);
            }

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
 * Return true if the selected test is active for the selected SSL Certificate
 * @param $test_id (int) test ID
 * @param $ssl_id (int) SSL Certificate ID
 * @return boolean true/false
 * @since 12.1.000 (2013-07-09)
 */
function f_is_test_on_ssl_certs(mixed $test_id, mixed $ssl_id): mixed
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $sql =
        'SELECT tstssl_test_id FROM '
        . K_TABLE_TEST_SSLCERTS
        . ' WHERE tstssl_test_id='
        . (int) $test_id
        . ' AND tstssl_ssl_id='
        . (int) $ssl_id
        . ' LIMIT 1';
    return ($r = F_db_query($sql, $db)) && ($m = F_db_fetch_array($r));
}
