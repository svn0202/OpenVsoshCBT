<?php

//============================================================+
// File name   : tce_functions_page.php
// Begin       : 2002-03-21
// Last Update : 2023-11-30
//
// Description : Functions for XHTML pages.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions for XHTML pages.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2002-03-21
 */

/**
 * Display Pages navigation index.
 * @param string $script_name url of the calling page
 * @param string $sql sql used to select records
 * @param int|string $firstrow first row number
 * @param int|string $rowsperpage number of max rows per page
 * @param string $param_array parameters to pass on url via GET
 * @return int|string|false the number of pages in case of success, false otherwise
 */
function f_show_page_navigator(
    string $script_name,
    string $sql,
    int|string $firstrow,
    int|string $rowsperpage,
    string $param_array,
): int|string|false
{
    global $l, $db;
    require_once '../config/tce_config.php';
    /** @var mixed $db */
    /** @var array{m_search_void: string, w_page: string, w_previous: string, w_next: string} $l */
    $max_pages = 4; // max pages to display on page selector
    $indexbar = ''; // string for selection page html code
    $firstrow = (int) $firstrow;
    $rowsperpage = (int) $rowsperpage;
    if (!$sql || $rowsperpage < 1) {
        return false;
    }

    $r = F_db_query($sql, $db);
    /** @var \mysqli_result|\PgSql\Result|false $r */
    if ($r === false) {
        F_display_db_error();
    }
    /** @var \mysqli_result|\PgSql\Result $r */

    // build base url for all links
    $baseaddress = $script_name;
    if (empty($param_array)) {
        $baseaddress .= '?';
    } else {
        $param_array = substr($param_array, 5); // remove first "&amp;"
        $baseaddress .= '?' . $param_array . '&amp;';
    }

    $count_rows = preg_match('/GROUP BY/i', $sql); //check if query contain a "GROUP BY"
    $all_updates = (int) F_db_num_rows($r);
    if ($all_updates === 1 && !$count_rows) {
        $normalize_row = static fn(mixed $row): ?array => is_array($row) ? $row : null;
        $normalize_count = static fn(mixed $count): int|string => is_int($count) || is_string($count) ? $count : 0;
        $row = $normalize_row(F_db_fetch_array($r));
        $all_updates = $normalize_count($row[0] ?? 0);
    }
    $total_updates = (int) $all_updates;

    if (!$total_updates) {
        //no records
        F_print_error('MESSAGE', $l['m_search_void']);
    } elseif ($total_updates > $rowsperpage) {
        $indexbar .= '<div class="pageselector">' . $l['w_page'] . ': ';
        $page_range = $max_pages * $rowsperpage;
        if ($firstrow <= $page_range) {
            $page_range = (2 * $page_range) - $firstrow + $rowsperpage;
        } elseif ($firstrow >= ($total_updates - $page_range)) {
            $page_range = (2 * $page_range) - ($total_updates - (2 * $rowsperpage) - $firstrow);
        }

        if ($firstrow >= $rowsperpage) {
            $indexbar .= '<a href="' . $baseaddress . 'firstrow=0">1</a> | ';
            $indexbar .=
                '<a href="'
                . $baseaddress
                . 'firstrow='
                . ($firstrow - $rowsperpage)
                . '" title="'
                . $l['w_previous']
                . '">&lt;</a> | ';
        } else {
            $indexbar .= '1 | &lt; | ';
        }

        $count = 2;
        $x = 0;
        for ($x = $rowsperpage; $x < ($total_updates - $rowsperpage); $x += $rowsperpage) {
            if ($x >= ($firstrow - $page_range) && $x <= ($firstrow + $page_range)) {
                if ($x === $firstrow) {
                    $indexbar .= $count . ' | ';
                } else {
                    $indexbar .=
                        '<a href="'
                        . $baseaddress
                        . 'firstrow='
                        . $x
                        . '" title="'
                        . $count
                        . '">'
                        . $count
                        . '</a> | ';
                }
            }

            ++$count;
        }

        if (($firstrow + $rowsperpage) < $total_updates) {
            $indexbar .=
                '<a href="'
                . $baseaddress
                . 'firstrow='
                . ($firstrow + $rowsperpage)
                . '" title="'
                . $l['w_next']
                . '">&gt;</a> | ';
            $indexbar .= '<a href="' . $baseaddress . 'firstrow=' . $x . '" title="' . $count . '">' . $count . '</a>';
        } else {
            $indexbar .= '&gt; | ' . $count;
        }

        $indexbar .= '</div>';
    }

    echo $indexbar; // display the page selector
    return $all_updates; //return number of records found
}
