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

// display header banner (logo + timer)
echo '<header class="header" role="banner">' . K_NEWLINE;
echo '<div class="left">' . K_NEWLINE;
if (!$is_app_page) {
    echo '<button class="login-menu-toggle" type="button" aria-controls="scrollayer" '
        . 'aria-expanded="false" aria-label="Открыть меню"><span aria-hidden="true">☰</span></button>' . K_NEWLINE;
} else {
    echo '<button class="app-menu-toggle" type="button" aria-controls="scrollayer" '
        . 'aria-expanded="false" aria-label="Открыть меню" title="Открыть меню">'
        . '<span aria-hidden="true"></span></button>' . K_NEWLINE;
}
echo '<a class="site-brand" href="' . K_PATH_URL . 'public/code/">' . K_NEWLINE;
if ($is_app_page) {
    echo '<img class="vsosh-wordmark" src="../../images/vsosh-wordmark-header.svg?v=20260718-2" '
        . 'alt="ВСОШ — Всероссийская олимпиада школьников" />' . K_NEWLINE;
    echo '<img class="tmf-engine-wordmark" '
        . 'src="../../images/logo_tcexam_white_noborder_transparent_114x21.png" alt="TCExam" />' . K_NEWLINE;
} else {
    echo '<img class="login-vsosh-wordmark" src="../../images/vsosh-wordmark-transparent.png?v=20260718-1" '
        . 'alt="ВСОШ — Всероссийская олимпиада школьников" />' . K_NEWLINE;
    echo '<img class="login-engine-wordmark" src="../../images/logo_tcexam_white_noborder_transparent_114x21.png" '
        . 'alt="TCExam" />' . K_NEWLINE;
}
echo '</a>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '<div class="right" id="timersection">' . K_NEWLINE;
if ($is_app_page) {
    if ($_SESSION['session_user_level'] >= K_ADMIN_LINK) {
        echo '<a class="tmf-admin-shortcut" href="../../admin/code/index.php"><span aria-hidden="true">⚙</span> Admin</a>' . K_NEWLINE;
    }
    echo '<button class="tmf-user-toggle" type="button" aria-controls="tmf-user-panel" aria-expanded="false">'
        . '<span>Пользователь</span><i aria-hidden="true"></i></button>' . K_NEWLINE;
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
    echo '<button class="app-menu-close" type="button" aria-label="Закрыть меню" title="Закрыть меню">×</button>' . K_NEWLINE;
    echo '<img src="../../images/vsosh-logo.png" alt="РЦОИ и ОКО" />' . K_NEWLINE;
    echo '<strong>Платформа тестирования</strong>' . K_NEWLINE;
    echo '<span>ГАОУ ДПО СО «ИРО»</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}
require_once __DIR__ . '/tce_page_menu.php';
if (!$is_app_page) {
    echo '<div class="login-menu-links">' . K_NEWLINE;
    echo '<a href="https://vsoshlk.irro.ru">Результаты олимпиад</a>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}
echo '</nav>' . K_NEWLINE;
if ($is_app_page) {
    echo '<button class="app-menu-scrim" type="button" aria-label="Закрыть меню" tabindex="-1"></button>' . K_NEWLINE;
    echo '<aside class="tmf-user-panel" id="tmf-user-panel" aria-label="Информация о пользователе">' . K_NEWLINE;
    echo '<div class="tmf-user-panel-title"><strong>Пользователь</strong>'
        . '<button class="tmf-user-close" type="button" aria-label="Закрыть">×</button></div>' . K_NEWLINE;
    echo '<dl>' . K_NEWLINE;
    echo '<dt>Уровень</dt><dd>' . (int) $_SESSION['session_user_level'] . '</dd>' . K_NEWLINE;
    echo '<dt>Логин</dt><dd>' . htmlspecialchars($_SESSION['session_user_name'], ENT_QUOTES, $l['a_meta_charset']) . '</dd>' . K_NEWLINE;
    echo '<dt>Имя</dt><dd>' . htmlspecialchars(urldecode((string) $_SESSION['session_user_firstname']), ENT_QUOTES, $l['a_meta_charset']) . '</dd>' . K_NEWLINE;
    echo '</dl>' . K_NEWLINE;
    echo '<a class="tmf-panel-logout" href="tce_logout.php" onclick="return confirm(\'' . $l['w_logout'] . ' ?\')">'
        . '<span aria-hidden="true">⏻</span> ВЫЙТИ ИЗ СИСТЕМЫ?</a>' . K_NEWLINE;
    echo '</aside>' . K_NEWLINE;
}

echo '<main id="maincontent" class="body">' . K_NEWLINE;

echo '<h1>' . htmlspecialchars($thispage_title, ENT_NOQUOTES, $l['a_meta_charset']) . '</h1>' . K_NEWLINE;
