<?php

//============================================================+
// File name   : tce_select_tests.php
// Begin       : 2012-12-02
// Last Update : 2023-11-30
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
 * @since 2012-12-02
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_TESTS;
require_once '../../shared/code/tce_authorization.php';

/** @var array{t_test_select:string,a_meta_charset:string,w_search:string,m_updated:string} $l */
/** @var mixed $db */
$thispage_title = $l['t_test_select'];

require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once 'tce_functions_test_select.php';

$order_field = $_REQUEST['order_field'] ?? 'user_lastname,user_firstname';
$orderdir = isset($_REQUEST['orderdir']) ? (int) $_REQUEST['orderdir'] : 0;
$firstrow = isset($_REQUEST['firstrow']) ? (int) $_REQUEST['firstrow'] : 0;
$rowsperpage = isset($_REQUEST['rowsperpage']) ? (int) $_REQUEST['rowsperpage'] : K_MAX_ROWS_PER_PAGE;
/** @var string $searchterms */
$searchterms = $_REQUEST['searchterms'] ?? '';

if (isset($_POST['lock'])) {
    $menu_mode = 'lock';
} elseif (isset($_POST['unlock'])) {
    $menu_mode = 'unlock';
}

echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_testselect">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
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
        $wherequery .= ' AND (';
        $wherequery .= " (test_name LIKE '%" . $word . "%')";
        $wherequery .= " OR (test_description LIKE '%" . $word . "%')";
        $wd = [];
        if (preg_match('/^(\d{4})[\-](\d{2})[\-](\d{2})$/', $word, $wd) === 1) {
            /** @var array{0:string,1:numeric-string,2:numeric-string,3:numeric-string} $wd */
            if (checkdate((int) $wd[2], (int) $wd[3], (int) $wd[1])) {
                $wherequery .= " OR ((test_begin_time <= '" . $word . "')";
                $wherequery .= " AND (test_end_time >= '" . $word . "'))";
            }
        }

        $wherequery .= ')';
    }

    $wherequery = '(' . substr($wherequery, 5) . ')';
}

echo get_form_noscript_select();

echo '<div class="row"><hr /></div>' . K_NEWLINE;

if (isset($menu_mode) && !empty($menu_mode)) {
    $istart = 1 + $firstrow;
    $iend = $rowsperpage + $firstrow;
    for ($i = $istart; $i <= $iend; ++$i) {
        // for each selected user
        $keyname = 'testid' . $i;
        if (isset($_POST[$keyname])) {
            $test_id = (int) $_POST[$keyname];
            if (f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
                switch ($menu_mode) {
                    case 'lock': // lock test by changing end date (subtract 1000 years)
                        $sql = 'UPDATE ' . K_TABLE_TESTS . ' SET
							test_end_time=test_end_time-10000000000000
							WHERE test_id=' . $test_id . '';
                        $r = f_tmf_select_tests_query_result(F_db_query($sql, $db));
                        if (!$r) {
                            F_display_db_error(false);
                        }

                        break;

                    case 'unlock': // unlock test by restoring original end date (add 1000 years)
                        $sql = 'UPDATE ' . K_TABLE_TESTS . ' SET
							test_end_time=test_end_time+10000000000000
							WHERE test_id=' . $test_id . '';
                        $r = f_tmf_select_tests_query_result(F_db_query($sql, $db));
                        if (!$r) {
                            F_display_db_error(false);
                        }

                        break;

                    case 'delete':
                        $sql = 'DELETE FROM ' . K_TABLE_TESTS . '
							WHERE test_id=' . $test_id . '';
                        $r = f_tmf_select_tests_query_result(F_db_query($sql, $db));
                        if (!$r) {
                            F_display_db_error();
                        }

                        break;
                } // end of switch
            }
        }
    }

    F_print_error('MESSAGE', $l['m_updated']);
}

F_select_test($order_field, $orderdir, $firstrow, $rowsperpage, $wherequery, $searchterms);
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** @return \mysqli_result|\PgSql\Result|resource|bool */
function f_tmf_select_tests_query_result(mixed $result): mixed
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
