<?php

//============================================================+
// File name   : tce_page_menu.php
// Begin       : 2010-09-16
// Last Update : 2023-11-30
//
// Description : Output XHTML unordered list menu.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Output XHTML unordered list menu.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2010-09-16
 */

require_once '../../shared/code/tce_functions_menu.php';

/**
 * @var array{
 *     h_index: string,
 *     w_index: string,
 *     t_all_results_user: string,
 *     w_results: string,
 *     w_user: string,
 *     h_admin_link: string,
 *     w_admin: string,
 *     h_logout_link: string,
 *     w_logout: string,
 *     h_login_link: string,
 *     w_login: string,
 *     t_user_change_email: string,
 *     w_change_email: string,
 *     t_user_change_password: string,
 *     w_change_password: string
 * } $l
 */
/** @var array{session_user_level: int} $_SESSION */
$menu = [
    'index.php' => [
        'link' => 'index.php',
        'title' => $l['h_index'],
        'name' => $l['w_index'],
        'level' => K_AUTH_PUBLIC_INDEX,
        'key' => 'i',
        'enabled' => true,
    ],
    'tce_test_allresults.php' => [
        'link' => 'tce_test_allresults.php',
        'title' => $l['t_all_results_user'],
        'name' => $l['w_results'],
        'level' => K_AUTH_PUBLIC_TEST_RESULTS,
        'key' => 'r',
        'enabled' => $_SESSION['session_user_level'] > K_AUTH_PUBLIC_TEST_RESULTS,
    ],
    'tce_page_user.php' => [
        'link' => 'tce_page_user.php',
        'title' => $l['w_user'],
        'name' => $l['w_user'],
        'level' => K_AUTH_PAGE_USER,
        'key' => 'u',
        'enabled' => $_SESSION['session_user_level'] > 0,
    ],
    'admin' => [
        'link' => '../../admin/code/index.php',
        'title' => $l['h_admin_link'],
        'name' => $l['w_admin'],
        'level' => K_ADMIN_LINK,
        'key' => 'a',
        'enabled' => $_SESSION['session_user_level'] >= K_ADMIN_LINK,
    ],
    'tce_logout.php' => [
        'link' => 'tce_logout.php',
        'title' => $l['h_logout_link'],
        'name' => $l['w_logout'],
        'level' => 1,
        'key' => 'q',
        'enabled' => $_SESSION['session_user_level'] > 0,
    ],
    'tce_login.php' => [
        'link' => 'tce_login.php',
        'title' => $l['h_login_link'],
        'name' => $l['w_login'],
        'level' => 0,
        'key' => 'l',
        'enabled' => $_SESSION['session_user_level'] < 1,
    ],
];

$menu['tce_page_user.php']['sub'] = [
    'tce_user_change_email.php' => [
        'link' => 'tce_user_change_email.php',
        'title' => $l['t_user_change_email'],
        'name' => $l['w_change_email'],
        'level' => K_AUTH_USER_CHANGE_EMAIL,
        'key' => '',
        'enabled' => true,
    ],
    'tce_user_change_password.php' => [
        'link' => 'tce_user_change_password.php',
        'title' => $l['t_user_change_password'],
        'name' => $l['w_change_password'],
        'level' => K_AUTH_USER_CHANGE_PASSWORD,
        'key' => '',
        'enabled' => true,
    ],
];

echo '<span id="menusection"></span>' . K_NEWLINE;

$menudata = '';
foreach ($menu as $link => $data) {
    $menudata .= F_menu_link($link, $data, 0) ?? '';
}

if ($menudata !== '') {
    echo '<ul class="menu">' . K_NEWLINE;
    echo $menudata;
    echo '</ul>' . K_NEWLINE; // end of menu
}
