<?php

//============================================================+
// File name   : tce_popup_test_info.php
// Begin       : 2004-05-28
// Last Update : 2023-11-30
//
// Description : Outputs test information using popup page
//               headers.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Outputs test information using popup page headers.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-05-28
 */

require_once '../config/tce_config.php';

$pagelevel = (int) K_AUTH_ADMIN_RESULTS;
/** @var array{t_test_info: string, hp_test_info: string, m_authorization_denied: string} $l Loaded language data. */
$thispage_title = $l['t_test_info'];
$thispage_description = $l['hp_test_info'];
require_once '../../shared/code/tce_authorization.php';

require_once '../code/tce_page_header_popup.php';

echo '<div class="popupcontainer">' . K_NEWLINE;

$test_id = f_positive_request_int($_REQUEST['testid'] ?? null);
if ($test_id > 0) {
    // check user's authorization
    if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }

    require_once '../../shared/code/tce_functions_test.php';
    echo f_print_test_info($test_id, true);
}

echo '<div class="row">' . K_NEWLINE;
require_once '../../shared/code/tce_functions_form.php';
echo F_close_button();
echo '</div>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer_popup.php';
