<?php

//============================================================+
// File name   : tce_email_results.php
// Begin       : 2005-02-24
// Last Update : 2023-11-30
//
// Description : Interface to send test reports to users via
//               email.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Interface to send email test reports to users.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2005-02-24
 */

require_once '../config/tce_config.php';
/**
 * @var array{
 *     t_email_result: string,
 *     hp_email_result: string,
 *     m_authorization_denied: string,
 *     hp_sending_in_progress: string,
 *     m_process_completed: string
 * } $l
 */

$pagelevel = (int) constant('K_AUTH_ADMIN_RESULTS');
$thispage_title = $l['t_email_result'];
$thispage_description = $l['hp_email_result'];
require_once '../../shared/code/tce_authorization.php';
require_once 'tce_functions_user_select.php';

require_once '../code/tce_page_header.php';

echo '<div class="popupcontainer">' . K_NEWLINE;

$test_id = isset($_REQUEST['test_id']) && is_string($_REQUEST['test_id']) ? (int) $_REQUEST['test_id'] : 0;
if ($test_id > 0) {
    // check user's authorization
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied']);
        echo '</div>' . K_NEWLINE;
        require_once '../code/tce_page_footer.php';
        exit();
    }
}

$user_id = isset($_REQUEST['user_id']) && is_string($_REQUEST['user_id']) ? (int) $_REQUEST['user_id'] : 0;

$testuser_id = isset($_REQUEST['testuser_id']) && is_string($_REQUEST['testuser_id'])
    ? max(0, (int) $_REQUEST['testuser_id']) : 0;

$group_id = isset($_REQUEST['group_id']) && is_string($_REQUEST['group_id']) ? (int) $_REQUEST['group_id'] : 0;

// filtering options
if (isset($_REQUEST['startdate']) && is_string($_REQUEST['startdate'])) {
    $startdate = $_REQUEST['startdate'];
    $startdate_time = strtotime($startdate);
    $startdate = date(K_TIMESTAMP_FORMAT, $startdate_time === false ? 0 : $startdate_time);
} else {
    $startdate = '';
}

if (isset($_REQUEST['enddate']) && is_string($_REQUEST['enddate'])) {
    $enddate = $_REQUEST['enddate'];
    $enddate_time = strtotime($enddate);
    $enddate = date(K_TIMESTAMP_FORMAT, $enddate_time === false ? 0 : $enddate_time);
} else {
    $enddate = '';
}

$mode = isset($_REQUEST['mode']) && is_string($_REQUEST['mode']) ? max(0, (int) $_REQUEST['mode']) : 0;
$display_mode = isset($_REQUEST['display_mode']) && is_string($_REQUEST['display_mode'])
    ? max(0, min(5, (int) $_REQUEST['display_mode'])) : 0;

if (isset($_REQUEST['show_graph']) && is_string($_REQUEST['show_graph'])) {
    $show_graph = (int) $_REQUEST['show_graph'];
    if ($show_graph && $display_mode === 0) {
        $display_mode = 1;
    }
} else {
    $show_graph = 0;
}

require_once 'tce_functions_email_reports.php';
echo '<div class="pagehelp">' . $l['hp_sending_in_progress'] . '</div>' . K_NEWLINE;
flush(); // force browser output
f_send_report_emails(
    $test_id,
    $user_id,
    $testuser_id,
    $group_id,
    $startdate,
    $enddate,
    $mode,
    $display_mode,
    $show_graph,
);
F_print_error('MESSAGE', $l['m_process_completed']);

echo '</div>' . K_NEWLINE;
require_once '../code/tce_page_footer.php';
