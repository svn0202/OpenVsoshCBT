<?php

//============================================================+
// File name   : tce_page_user.php
// Begin       : 2010-09-20
// Last Update : 2023-11-30
//
// Description : Output XHTML unordered list menu for user.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Output XHTML unordered list menu for user.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2010-09-20
 */

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PAGE_USER;
require_once '../../shared/code/tce_authorization.php';

/** @var array{w_user: string} $l Loaded language data. */
$thispage_title = $l['w_user'];
require_once '../code/tce_page_header.php';

echo '<div class="container">' . K_NEWLINE;

// print submenu
echo '<ul class="section-link-grid" aria-label="Настройки профиля">' . K_NEWLINE;
/** @var array{'tce_page_user.php': array{sub: array<string, array<string, mixed>>}} $menu */
foreach ($menu['tce_page_user.php']['sub'] as $link => $data) {
    echo F_menu_link($link, $data, 1);
}

echo '</ul>' . K_NEWLINE;

echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';
