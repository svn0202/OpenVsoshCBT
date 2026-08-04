<?php

//============================================================+
// File name   : index.php
// Begin       : 2004-04-29
// Last Update : 2024-03-22
//
// Description : Main page of administrator section.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Main page of TCExam Administration Area.
 * @package com.tecnick.tcexam.admin
 * @brief TCExam Administration Area
 * @author Nicola Asuni
 * @since 2004-04-20
 */

require_once '../config/tce_config.php';
$pagelevel = K_AUTH_INDEX;
require_once '../../shared/code/tce_authorization.php';
$thispage_title = 'Панель управления';
require_once 'tce_page_header.php';
$dashboard_user_level = (int) ($_SESSION['session_user_level'] ?? 0);
$dashboard_charset = (string) ($l['a_meta_charset'] ?? 'UTF-8');

$dashboard_stats = [
    ['label' => 'Участники', 'value' => F_count_rows(K_TABLE_USERS), 'icon' => 'users', 'href' => 'tce_select_users.php', 'level' => K_AUTH_ADMIN_USERS],
    ['label' => 'Вопросы', 'value' => F_count_rows(K_TABLE_QUESTIONS), 'icon' => 'library', 'href' => 'tce_show_all_questions.php', 'level' => K_AUTH_ADMIN_RESULTS],
    ['label' => 'Испытания', 'value' => F_count_rows(K_TABLE_TESTS), 'icon' => 'tests', 'href' => 'tce_select_tests.php', 'level' => K_AUTH_ADMIN_TESTS],
    ['label' => 'Активные сессии', 'value' => F_count_rows(K_TABLE_SESSIONS), 'icon' => 'profile', 'href' => 'tce_show_online_users.php', 'level' => K_AUTH_ADMIN_USERS],
];
echo '<section class="admin-dashboard" aria-label="Обзор площадки">' . K_NEWLINE;
echo '<div class="dashboard-welcome"><div><span>OpenVsoshCBT</span><h2>Всё для проведения олимпиады — в одном месте</h2>'
    . '<p>Следите за участниками, готовьте задания и управляйте проведением без перехода к конфигурационным файлам.</p></div>'
    . '<a href="tce_monitor.php">Открыть наблюдение <span aria-hidden="true">→</span></a></div>' . K_NEWLINE;
echo '<div class="dashboard-stats">' . K_NEWLINE;
foreach ($dashboard_stats as $stat) {
    if ($dashboard_user_level < (int) $stat['level']) {
        continue;
    }
    echo '<a class="dashboard-stat" href="' . $stat['href'] . '"><span class="dashboard-stat-icon">'
        . f_menu_icon_svg($stat['icon']) . '</span><span><strong>' . (int) $stat['value'] . '</strong><small>'
        . htmlspecialchars($stat['label'], ENT_QUOTES, $dashboard_charset) . '</small></span></a>' . K_NEWLINE;
}
echo '</div>' . K_NEWLINE;
echo '<div class="dashboard-actions"><h2>Быстрые действия</h2><div>' . K_NEWLINE;
/** @var list<array{string, string, string, int}> $dashboard_actions */
$dashboard_actions = [
    ['tce_edit_test.php', 'Создать испытание', 'Подготовить расписание и набор вопросов', K_AUTH_ADMIN_TESTS],
    ['tmf_word_import.php', 'Импортировать Word', 'Загрузить задания с предварительной проверкой', K_AUTH_ADMIN_IMPORT],
    ['tce_users_xlsx.php', 'Загрузить участников', 'Импортировать или выгрузить XLSX', K_AUTH_IMPORT_USERS],
    ['tce_onboarding_settings.php', 'Настроить площадку', 'Оформление, доступ и вводные тесты', K_AUTH_ADMINISTRATOR],
];
foreach ($dashboard_actions as [$href, $title, $description, $required_level]) {
    if ($dashboard_user_level < (int) $required_level) {
        continue;
    }
    echo '<a href="' . $href . '"><strong>' . $title . '</strong><span>' . $description
        . '</span><i aria-hidden="true">→</i></a>' . K_NEWLINE;
}
echo '</div></div>' . K_NEWLINE;
echo '</section>' . K_NEWLINE;

// Display test limits (if any)

$limits = '';
if (K_REMAINING_TESTS !== false) {
    // count
    $limits .= '<tr';
    if (K_REMAINING_TESTS <= 0) {
        $limits .= ' style="text-align:right;background-color:#FFCCCC;" title="' . $l['w_over_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_over_limit'] . '</span> ';
    } else {
        $limits .= ' style="text-align:right;background-color:#CCFFCC;" title="' . $l['w_under_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_under_limit'] . '</span> ';
    }

    $limits .=
        '><td style="text-align:left;">'
        . $l['w_total']
        . '</td><td>&nbsp;</td><td>&nbsp;</td><td>'
        . $limitstatus
        . K_REMAINING_TESTS
        . '</td></tr>';
}

$now = time();
$enddate = date(K_TIMESTAMP_FORMAT, $now);
if (K_MAX_TESTS_DAY !== false) {
    // day limit (last 24 hours)
    $startdate = date(K_TIMESTAMP_FORMAT, $now - K_SECONDS_IN_DAY);
    $numtests = F_count_rows(
        K_TABLE_TESTUSER_STAT,
        "WHERE tus_date>='" . $startdate . "' AND tus_date<='" . $enddate . "'",
    );
    $limits .= '<tr';
    if ((K_MAX_TESTS_DAY - $numtests) <= 0) {
        $limits .= ' style="text-align:right;background-color:#FFCCCC;" title="' . $l['w_over_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_over_limit'] . '</span> ';
    } else {
        $limits .= ' style="text-align:right;background-color:#CCFFCC;" title="' . $l['w_under_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_under_limit'] . '</span> ';
    }

    $limits .=
        '><td style="text-align:left;">'
        . $l['w_day']
        . '</td><td>'
        . K_MAX_TESTS_DAY
        . '</td><td>'
        . $numtests
        . '</td><td><strong>'
        . $limitstatus
        . (K_MAX_TESTS_DAY - $numtests)
        . '</strong></td></tr>';
}

if (K_MAX_TESTS_MONTH !== false) {
    // month limit (last 30 days)
    $startdate = date(K_TIMESTAMP_FORMAT, $now - K_SECONDS_IN_MONTH);
    $numtests = F_count_rows(
        K_TABLE_TESTUSER_STAT,
        "WHERE tus_date>='" . $startdate . "' AND tus_date<='" . $enddate . "'",
    );
    $limits .= '<tr';
    if ((K_MAX_TESTS_MONTH - $numtests) <= 0) {
        $limits .= ' style="text-align:right;background-color:#FFCCCC;" title="' . $l['w_over_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_over_limit'] . '</span> ';
    } else {
        $limits .= ' style="text-align:right;background-color:#CCFFCC;" title="' . $l['w_under_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_under_limit'] . '</span> ';
    }

    $limits .=
        '><td style="text-align:left;">'
        . $l['w_month']
        . '</td><td>'
        . K_MAX_TESTS_MONTH
        . '</td><td>'
        . $numtests
        . '</td><td><strong>'
        . $limitstatus
        . (K_MAX_TESTS_MONTH - $numtests)
        . '</strong></td></tr>';
}

if (K_MAX_TESTS_YEAR !== false) {
    // year limit (last 365 days)
    $startdate = date(K_TIMESTAMP_FORMAT, $now - K_SECONDS_IN_YEAR);
    $numtests = F_count_rows(
        K_TABLE_TESTUSER_STAT,
        "WHERE tus_date>='" . $startdate . "' AND tus_date<='" . $enddate . "'",
    );
    $limits .= '<tr';
    if ((K_MAX_TESTS_YEAR - $numtests) <= 0) {
        $limits .= ' style="text-align:right;background-color:#FFCCCC;" title="' . $l['w_over_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_over_limit'] . '</span> ';
    } else {
        $limits .= ' style="text-align:right;background-color:#CCFFCC;" title="' . $l['w_under_limit'] . '"';
        $limitstatus = '<span class="sr-only">' . $l['w_under_limit'] . '</span> ';
    }

    $limits .=
        '><td style="text-align:left;">'
        . $l['w_year']
        . '</td><td>'
        . K_MAX_TESTS_YEAR
        . '</td><td>'
        . $numtests
        . '</td><td><strong>'
        . $limitstatus
        . (K_MAX_TESTS_YEAR - $numtests)
        . '</strong></td></tr>';
}

if (strlen($limits) > 0) {
    echo
        '<table style="border: 1px solid #808080;margin-left:auto; margin-right:auto;"><caption class="sr-only">'
            . $l['w_remaining_tests']
            . '</caption><thead><tr><th colspan="4" style="text-align:center;">'
            . $l['w_remaining_tests']
            . '</th></tr><tr style="background-color:#CCCCCC;"><th scope="col">'
            . $l['w_limit']
            . '</th><th scope="col">'
            . $l['w_max']
            . '</th><th scope="col">'
            . $l['w_executed']
            . '</th><th scope="col">'
            . $l['w_remaining']
            . '</th></tr></thead><tbody>'
            . $limits
            . '</tbody></table><br />'
            . K_NEWLINE
    ;
}

echo '<section class="dashboard-guide"><h2>Справка администратора</h2>'
    . (string) ($l['d_admin_index'] ?? '') . '</section>';

require_once 'tce_page_footer.php';
