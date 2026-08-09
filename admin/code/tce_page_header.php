<?php

//============================================================+
// File name   : tce_page_header.php
// Begin       : 2001-09-18
// Last Update : 2023-11-30
//
// Description : output default XHTML page header
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Outputs default XHTML page header.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2001-09-18
 */

require_once 'tce_xhtml_header.php';
/** @var string $thispage_title Set or normalized by tce_xhtml_header.php. */
require_once '../../shared/code/tce_functions_menu.php';
require_once '../../shared/code/tce_functions_site_assets.php';
$admin_site = openvsosh_get_site_settings();
$admin_header_charset = (string) ($l['a_meta_charset'] ?? 'UTF-8');
$admin_script = basename($_SERVER['SCRIPT_NAME']);
$admin_contexts = [
    'users' => [
        'files' => ['tce_edit_user.php', 'tce_edit_group.php', 'tce_select_users.php', 'tce_show_online_users.php',
            'tce_import_users.php', 'tce_users_xlsx.php', 'tce_self_profile.php'],
        'label' => 'Участники и роли',
        'description' => 'Учётные записи, группы, импорт и права доступа.',
        'icon' => 'users',
    ],
    'library' => [
        'files' => ['tce_edit_module.php', 'tce_edit_subject.php', 'tce_edit_question.php', 'tce_edit_answer.php',
            'tce_show_all_questions.php', 'tce_import_questions.php', 'tmf_word_import.php', 'tce_filemanager.php',
            'tce_edit_sslcerts.php'],
        'label' => 'Банк заданий',
        'description' => 'Материалы, вопросы, ответы и импорт содержимого.',
        'icon' => 'library',
    ],
    'tests' => [
        'files' => ['tce_test_access_rules.php', 'tce_monitor.php', 'tce_pregenerate.php', 'tce_offline.php',
            'tce_edit_test.php', 'tce_select_tests.php', 'tce_import_omr_answers.php', 'tce_import_omr_bulk.php',
            'tce_edit_rating.php', 'tce_show_result_allusers.php', 'tce_show_result_user.php'],
        'label' => 'Проведение и результаты',
        'description' => 'Настройка испытаний, наблюдение и обработка результатов.',
        'icon' => 'tests',
    ],
    'settings' => [
        'files' => ['tce_onboarding_settings.php', 'tce_edit_backup.php', 'tce_page_info.php', 'tce_page_help.php'],
        'label' => 'Система',
        'description' => 'Оформление, доступ, резервные копии и сведения о платформе.',
        'icon' => 'settings',
    ],
];
$admin_page_context = [
    'label' => 'Обзор площадки',
    'description' => 'Основные разделы управления олимпиадным тестированием.',
    'icon' => 'home',
];
foreach ($admin_contexts as $context) {
    if (in_array($admin_script, $context['files'], true)) {
        $admin_page_context = $context;
        break;
    }
}

// display header banner (logo + timer)
echo '<header class="header" role="banner">' . K_NEWLINE;
echo '<div class="left"><a class="site-brand" href="' . K_PATH_URL . 'public/code/" title="Вернуться на площадку тестирования">' . K_NEWLINE;
echo '<img src="../../images/vsosh-wordmark-white.png" alt="Всероссийская олимпиада школьников" width="246" height="54" />' . K_NEWLINE;
echo '<span class="site-brand-section"><strong>OpenVsoshCBT</strong><small>Панель администратора</small></span>' . K_NEWLINE;
echo '</a></div>' . K_NEWLINE;
echo '<div class="right" id="timersection">' . K_NEWLINE;
include '../../shared/code/tce_page_timer.php';
echo '</div>' . K_NEWLINE;
echo '</header>' . K_NEWLINE;

echo '<div class="admin-shell">' . K_NEWLINE;

// display navigation menu
echo
    '<nav id="scrollayer" class="scrollmenu" aria-label="'
        . htmlspecialchars((string) ($l['w_jump_menu'] ?? ''), ENT_QUOTES, $admin_header_charset)
        . '">'
        . K_NEWLINE
;
echo '<div class="admin-nav-heading">' . K_NEWLINE;
$admin_logo = openvsosh_site_asset_metadata('logo')
    ? '../../public/code/tce_site_asset.php?type=logo'
    : '../../images/vsosh-logo.png';
echo '<img src="' . $admin_logo . '" alt="" width="64" height="64" />' . K_NEWLINE;
echo '<span>Управление олимпиадой</span>' . K_NEWLINE;
echo '<strong>' . htmlspecialchars($admin_site['site_name'], ENT_QUOTES, $admin_header_charset) . '</strong>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
require_once __DIR__ . '/tce_page_menu.php';
echo '</nav>' . K_NEWLINE;
echo '<button type="button" class="admin-nav-backdrop" tabindex="-1" aria-hidden="true"></button>' . K_NEWLINE;

echo '<main id="maincontent" class="body">' . K_NEWLINE;

echo '<div class="content">' . K_NEWLINE;
echo '<div class="admin-page-heading">' . K_NEWLINE;
echo '<button type="button" class="admin-menu-toggle" aria-controls="scrollayer" aria-expanded="true">'
    . '<span aria-hidden="true"></span><span class="sr-only">Показать или скрыть меню</span></button>' . K_NEWLINE;
echo '<span class="admin-page-icon">' . f_menu_icon_svg($admin_page_context['icon']) . '</span>' . K_NEWLINE;
echo '<div class="admin-page-title">' . K_NEWLINE;
echo '<span class="admin-page-eyebrow">' . htmlspecialchars($admin_page_context['label'], ENT_QUOTES, $admin_header_charset)
    . '</span>' . K_NEWLINE;
echo '<h1>' . htmlspecialchars($thispage_title, ENT_NOQUOTES, $admin_header_charset) . '</h1>' . K_NEWLINE;
echo '<p>' . htmlspecialchars($admin_page_context['description'], ENT_QUOTES, $admin_header_charset) . '</p>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
