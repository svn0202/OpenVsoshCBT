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
/**
 * @var array{
 *     a_meta_charset:string,
 *     d_admin_index?:string,
 *     w_day:string,
 *     w_executed:string,
 *     w_limit:string,
 *     w_max:string,
 *     w_month:string,
 *     w_over_limit:string,
 *     w_remaining:string,
 *     w_remaining_tests:string,
 *     w_total:string,
 *     w_under_limit:string,
 *     w_year:string
 * } $l
 */
/** @var int $pagelevel */
$pagelevel = K_AUTH_INDEX;
require_once '../../shared/code/tce_authorization.php';
$thispage_title = 'Панель управления';
require_once 'tce_page_header.php';
$dashboard_user_level = (int) ($_SESSION['session_user_level'] ?? 0);
$dashboard_charset = $l['a_meta_charset'];

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

$read_test_limit = static fn (string $constant_name): int|false => (
    static fn (mixed $value): int|false => is_int($value) ? $value : false
)(constant($constant_name));
$count_tests = static fn (mixed $count): int => (int) $count;
$remaining_tests = $read_test_limit('K_REMAINING_TESTS');
$max_tests_day = $read_test_limit('K_MAX_TESTS_DAY');
$max_tests_month = $read_test_limit('K_MAX_TESTS_MONTH');
$max_tests_year = $read_test_limit('K_MAX_TESTS_YEAR');

$limits = '';
if ($remaining_tests !== false) {
    // count
    $limits .= '<tr';
    if ($remaining_tests <= 0) {
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
        . $remaining_tests
        . '</td></tr>';
}

$now = time();
$enddate = date(K_TIMESTAMP_FORMAT, $now);
if ($max_tests_day !== false) {
    // day limit (last 24 hours)
    $startdate = date(K_TIMESTAMP_FORMAT, (int) ($now - K_SECONDS_IN_DAY));
    $numtests = $count_tests(F_count_rows(
        K_TABLE_TESTUSER_STAT,
        "WHERE tus_date>='" . $startdate . "' AND tus_date<='" . $enddate . "'",
    ));
    $limits .= '<tr';
    if (($max_tests_day - $numtests) <= 0) {
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
        . $max_tests_day
        . '</td><td>'
        . $numtests
        . '</td><td><strong>'
        . $limitstatus
        . ($max_tests_day - $numtests)
        . '</strong></td></tr>';
}

if ($max_tests_month !== false) {
    // month limit (last 30 days)
    $startdate = date(K_TIMESTAMP_FORMAT, (int) ($now - K_SECONDS_IN_MONTH));
    $numtests = $count_tests(F_count_rows(
        K_TABLE_TESTUSER_STAT,
        "WHERE tus_date>='" . $startdate . "' AND tus_date<='" . $enddate . "'",
    ));
    $limits .= '<tr';
    if (($max_tests_month - $numtests) <= 0) {
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
        . $max_tests_month
        . '</td><td>'
        . $numtests
        . '</td><td><strong>'
        . $limitstatus
        . ($max_tests_month - $numtests)
        . '</strong></td></tr>';
}

if ($max_tests_year !== false) {
    // year limit (last 365 days)
    $startdate = date(K_TIMESTAMP_FORMAT, (int) ($now - K_SECONDS_IN_YEAR));
    $numtests = $count_tests(F_count_rows(
        K_TABLE_TESTUSER_STAT,
        "WHERE tus_date>='" . $startdate . "' AND tus_date<='" . $enddate . "'",
    ));
    $limits .= '<tr';
    if (($max_tests_year - $numtests) <= 0) {
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
        . $max_tests_year
        . '</td><td>'
        . $numtests
        . '</td><td><strong>'
        . $limitstatus
        . ($max_tests_year - $numtests)
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
    . ($l['d_admin_index'] ?? '') . '</section>';

require_once 'tce_page_footer.php';
