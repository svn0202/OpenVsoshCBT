<?php

//============================================================+
// File name   : tce_popup_test_info.php
// Begin       : 2004-05-28
// Last Update : 2023-11-30
//
// Description : Output test information using popup page
//               headers.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Output test information using popup page headers.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2004-05-28
 */

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PUBLIC_TEST_INFO;
/** @var array{t_test_info: string, hp_test_info: string} $l Loaded language data. */
$thispage_title = $l['t_test_info'];
$thispage_description = $l['hp_test_info'];
require_once '../../shared/code/tce_authorization.php';

require_once '../code/tce_page_header_popup.php';

echo '<div class="popupcontainer">' . K_NEWLINE;
$test_id = f_positive_request_int($_REQUEST['testid'] ?? null);
if ($test_id > 0) {
    require_once '../../shared/code/tce_functions_test.php';
    echo f_print_test_info($test_id, false);
}

echo '<div class="row">' . K_NEWLINE;
require_once '../../shared/code/tce_functions_form.php';
echo F_close_button();
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer_popup.php';
