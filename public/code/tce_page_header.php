<?php

//============================================================+
// File name   : tce_page_header.php
// Begin       : 2001-09-18
// Last Update : 2023-11-30
//
// Description : Outputs default XHTML page header.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Outputs default XHTML page header.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2001-09-18
 */

require_once 'tce_xhtml_header.php';
$is_app_page = ($_SESSION['session_user_level'] > 0 && empty($is_login_page));
$esc = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, $l['a_meta_charset']);
$wordmark = [
    'name' => $l['ov_vsosh_name'] ?? 'ВСОШ — Всероссийская олимпиада школьников',
    'prefix' => $l['ov_vsosh_abbreviation_prefix'] ?? 'ВС',
    'suffix' => $l['ov_vsosh_abbreviation_suffix'] ?? 'Ш',
    'line1' => $l['ov_vsosh_caption_line_1'] ?? 'Всероссийская',
    'line2' => $l['ov_vsosh_caption_line_2'] ?? 'олимпиада',
    'line3' => $l['ov_vsosh_caption_line_3'] ?? 'школьников',
];

// display header banner (logo + timer)
echo '<header class="header" role="banner">' . K_NEWLINE;
echo '<div class="left">' . K_NEWLINE;
if (!$is_app_page) {
    echo '<button class="login-menu-toggle" type="button" aria-controls="scrollayer" '
        . 'aria-expanded="false" aria-label="' . $esc($l['ov_open_menu'])
        . '"><span aria-hidden="true">☰</span></button>' . K_NEWLINE;
} else {
    echo '<button class="app-menu-toggle" type="button" aria-controls="scrollayer" '
        . 'aria-expanded="false" aria-label="' . $esc($l['ov_open_menu']) . '" title="'
        . $esc($l['ov_open_menu']) . '">'
        . '<span aria-hidden="true"></span></button>' . K_NEWLINE;
}
echo '<a class="site-brand" href="' . K_PATH_URL . 'public/code/">' . K_NEWLINE;
if ($is_app_page) {
    echo '<span class="app-vsosh-wordmark" aria-label="' . $esc($wordmark['name']) . '">'
        . '<span class="app-vsosh-abbr"><b>' . $esc($wordmark['prefix'])
        . '</b><i><em>{</em><em>}</em></i><b>' . $esc($wordmark['suffix']) . '</b></span>'
        . '<span class="app-vsosh-caption">' . $esc($wordmark['line1']) . '<br />'
        . $esc($wordmark['line2']) . '<br />' . $esc($wordmark['line3']) . '</span>'
        . '</span>' . K_NEWLINE;
    echo '<img class="tmf-engine-wordmark" '
        . 'src="../../images/logo_tcexam_white_noborder_transparent_114x21.png" alt="TCExam" />' . K_NEWLINE;
} else {
    echo '<span class="login-vsosh-wordmark" aria-label="' . $esc($wordmark['name']) . '">'
        . '<span class="login-vsosh-abbr"><b>' . $esc($wordmark['prefix'])
        . '</b><i><em>{</em><em>}</em></i><b>' . $esc($wordmark['suffix']) . '</b></span>'
        . '<span class="login-vsosh-caption">' . $esc($wordmark['line1']) . '<br />'
        . $esc($wordmark['line2']) . '<br />' . $esc($wordmark['line3']) . '</span>'
        . '</span>' . K_NEWLINE;
    echo '<img class="login-engine-wordmark" src="../../images/logo_tcexam_white_noborder_transparent_114x21.png" '
        . 'alt="TCExam" />' . K_NEWLINE;
}
echo '</a>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '<div class="right" id="timersection">' . K_NEWLINE;
if ($is_app_page) {
    echo '<button class="tmf-theme-toggle" type="button" aria-pressed="false" title="'
        . $esc($l['ov_switch_theme']) . '"><i aria-hidden="true">☾</i><span>'
        . $esc($l['ov_theme_dark']) . '</span></button>' . K_NEWLINE;
    if ($_SESSION['session_user_level'] >= K_ADMIN_LINK) {
        echo '<a class="tmf-admin-shortcut" href="../../admin/code/index.php"><span aria-hidden="true">⚙</span> Admin</a>' . K_NEWLINE;
    }
    echo '<button class="tmf-user-toggle" type="button" aria-controls="tmf-user-panel" aria-expanded="false">'
        . '<span>' . $esc($l['w_user']) . '</span><i aria-hidden="true"></i></button>' . K_NEWLINE;
    echo '<span class="tmf-timer">' . K_NEWLINE;
    include '../../shared/code/tce_page_timer.php';
    echo '</span>' . K_NEWLINE;
} else {
    include '../../shared/code/tce_page_timer.php';
}
echo '</div>' . K_NEWLINE;
echo '</header>' . K_NEWLINE;

// display navigation menu
echo
    '<nav id="scrollayer" class="scrollmenu" aria-label="'
        . htmlspecialchars($l['w_jump_menu'], ENT_QUOTES, $l['a_meta_charset'])
        . '">'
        . K_NEWLINE
;
if ($is_app_page) {
    echo '<div class="app-menu-heading">' . K_NEWLINE;
    echo '<button class="app-menu-close" type="button" aria-label="' . $esc($l['ov_close_menu'])
        . '" title="' . $esc($l['ov_close_menu']) . '">×</button>' . K_NEWLINE;
    echo '<img src="../../images/vsosh-logo.png" alt="' . $esc($l['ov_rcoko_alt']) . '" />' . K_NEWLINE;
    echo '<strong>' . $esc($l['ov_testing_platform']) . '</strong>' . K_NEWLINE;
    echo '<span>' . $esc($l['ov_organization_name']) . '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}
require_once __DIR__ . '/tce_page_menu.php';
if (!$is_app_page) {
    echo '<div class="login-menu-links">' . K_NEWLINE;
    echo '<a href="https://vsoshlk.irro.ru">' . $esc($l['ov_olympiad_results']) . '</a>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}
echo '<div class="legal-menu-links">' . K_NEWLINE;
echo '<strong>' . $esc($l['ov_about_platform']) . '</strong>' . K_NEWLINE;
echo '<a href="' . htmlspecialchars(K_OPENVSOSHCBT_SOURCE_URL, ENT_QUOTES, $l['a_meta_charset'])
    . '" rel="noopener">' . $esc($l['ov_source_code']) . ' OpenVsoshCBT</a>' . K_NEWLINE;
echo '<a href="' . K_PATH_URL . 'LICENSE">' . $esc($l['w_license']) . ' AGPL-3.0-or-later</a>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</nav>' . K_NEWLINE;
if ($is_app_page) {
    echo '<button class="app-menu-scrim" type="button" aria-label="' . $esc($l['ov_close_menu'])
        . '" tabindex="-1"></button>' . K_NEWLINE;
    echo '<aside class="tmf-user-panel" id="tmf-user-panel" aria-label="'
        . $esc($l['ov_user_information']) . '">' . K_NEWLINE;
    echo '<div class="tmf-user-panel-title"><strong>' . $esc($l['w_user']) . '</strong>'
        . '<button class="tmf-user-close" type="button" aria-label="' . $esc($l['ov_close'])
        . '">×</button></div>' . K_NEWLINE;
    echo '<dl>' . K_NEWLINE;
    echo '<dt>' . $esc($l['w_level']) . '</dt><dd>' . (int) $_SESSION['session_user_level'] . '</dd>' . K_NEWLINE;
    echo '<dt>' . $esc($l['w_username']) . '</dt><dd>' . $esc($_SESSION['session_user_name']) . '</dd>' . K_NEWLINE;
    echo '<dt>' . $esc($l['w_name']) . '</dt><dd>' . $esc(urldecode((string) $_SESSION['session_user_firstname'])) . '</dd>' . K_NEWLINE;
    echo '</dl>' . K_NEWLINE;
    echo '<a class="tmf-panel-logout" href="tce_logout.php" onclick="return confirm(\'' . $l['w_logout'] . ' ?\')">'
        . '<span aria-hidden="true">⏻</span> ' . $esc($l['ov_logout_question']) . '</a>' . K_NEWLINE;
    echo '</aside>' . K_NEWLINE;
}

echo '<main id="maincontent" class="body">' . K_NEWLINE;

echo '<h1>' . htmlspecialchars($thispage_title, ENT_NOQUOTES, $l['a_meta_charset']) . '</h1>' . K_NEWLINE;
