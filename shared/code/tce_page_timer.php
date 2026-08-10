<?php

//============================================================+
// File name   : tce_page_timer.php
// Begin       : 2004-04-29
// Last Update : 2023-11-30
//
// Description : Display timer (date-time + countdown).
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display client timer (date-time + countdown).
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2004-04-29
 */

if (!isset($_REQUEST['examtime'])) {
    $examtime = 0; // remaining exam time in seconds
    $enable_countdown = 'false';
    $timeout_logout = 'false';
} else {
    $examtime = floatval($_REQUEST['examtime']);
    $enable_countdown = 'true';
    $timeout_logout = isset($_REQUEST['timeout_logout']) && $_REQUEST['timeout_logout'] ? 'true' : 'false';
}
require_once __DIR__ . '/tce_functions_openvsosh_settings.php';
$timer_settings = openvsosh_get_runtime_settings();
$timer_warning_text = openvsosh_contrast_text($timer_settings['timer_warning_color']);
$timer_critical_text = openvsosh_contrast_text($timer_settings['timer_critical_color']);
/** @var array{w_remaining: string, w_time: string, w_clock_timer: string, m_exam_end_time: string} $l */
$is_exam_timer = basename($_SERVER['SCRIPT_NAME']) === 'tce_test_execute.php';
$timer_label = $is_exam_timer ? $l['w_remaining'] : $l['w_time'];

echo '<form action="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) . '" id="timerform" style="'
    . '--timer-warning-bg:' . $timer_settings['timer_warning_color'] . ';'
    . '--timer-warning-text:' . $timer_warning_text . ';'
    . '--timer-critical-bg:' . $timer_settings['timer_critical_color'] . ';'
    . '--timer-critical-text:' . $timer_critical_text . '">' . K_NEWLINE;
// role="timer" identifies the region to assistive technologies; aria-live stays "off"
// (the default for the timer role) on purpose, so the per-second updates are not announced.
echo '<div role="timer" aria-live="off">' . K_NEWLINE;
echo '<label for="timer" class="timerlabel">' . $timer_label . ':</label>' . K_NEWLINE;
echo
    '<input type="text" name="timer" id="timer" value="" size="29" maxlength="29" title="'
        . $l['w_clock_timer']
        . '" readonly="readonly"/>'
        . K_NEWLINE
;
echo '<span id="timer-status" class="timer-status" aria-live="polite"></span>' . K_NEWLINE;
echo '&nbsp;</div>' . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '<script src="' . K_PATH_SHARED_JSCRIPTS . 'timer.js?v=20260729-1" type="text/javascript"></script>' . K_NEWLINE;
echo '<script type="text/javascript">' . K_NEWLINE;
echo '//<![CDATA[' . K_NEWLINE;
echo 'FJ_configure_timer('
    . (int) $timer_settings['timer_warning_seconds'] . ','
    . (int) $timer_settings['timer_critical_seconds'] . ','
    . json_encode(
        'Внимание: времени осталось мало',
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
    ) . ','
    . json_encode(
        'Критически мало времени',
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
    )
    . ');' . K_NEWLINE;
echo
    'FJ_start_timer('
        . $enable_countdown
        . ', '
        . (time() - $examtime)
        . ", '"
        . addslashes($l['m_exam_end_time'])
        . "', "
        . $timeout_logout
        . ', '
        . round(microtime(true) * 1000)
        . ');'
        . K_NEWLINE
;
echo '//]]>' . K_NEWLINE;
echo '</script>' . K_NEWLINE;
