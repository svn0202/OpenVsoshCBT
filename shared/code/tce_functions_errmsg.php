<?php

//============================================================+
// File name   : tce_functions_errmsg.php
// Begin       : 2001-09-17
// Last Update : 2023-11-30
//
// Description : handle error messages
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Handle error/warning/system messages.<br>
 * messagetype:
 * <ul>
 * <li>message</li>
 * <li>warning</li>
 * <li>error</li>
 * </ul>
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2001-09-17
 */

/**
 * HTML tags that are allowed in an error message.
 */
define('K_ALLOWED_ERROR_TAGS', '<a><b><br><em><p><ol><ul><li><small><table><tr><th><td>');

/**
 * Handle error/warning/system messages.
 * Print a message
 * @param mixed $messagetype Type of message: message, warning or error.
 * @param mixed $messagetoprint Message to print.
 * @param mixed $exit If truthy, output a message and terminate the current script.
 */
function F_print_error(mixed $messagetype = 'MESSAGE', mixed $messagetoprint = '', mixed $exit = false): void
{
    require_once __DIR__ . '/../config/tce_config.php';
    require_once __DIR__ . '/tce_functions_general.php';
    global $l;
    $messagetype = strtolower($messagetype);
    // Strip any markup here; the message is escaped per output context (HTML/JS) below.
    // NOTE: do not re-decode entities (unhtmlentities) — doing so reconstructs tags from
    // encoded input and reintroduces XSS via attribute-carrying allow-listed tags.
    $messagetoprint = trim(strip_tags($messagetoprint));
    //message is appended to the log file
    if (K_USE_ERROR_LOG && !strcmp($messagetype, 'error')) {
        $logsttring = date(K_TIMESTAMP_FORMAT) . K_TAB;
        $logsttring .= $_SESSION['session_user_id'] . K_TAB;
        $logsttring .= $_SESSION['session_user_ip'] . K_TAB;
        $logsttring .= $messagetype . K_TAB;
        $logsttring .= $_SERVER['SCRIPT_NAME'] . K_TAB;
        $logsttring .= $messagetoprint . K_NEWLINE;
        error_log($logsttring, 3, '../log/tce_errors.log');
    }

    if (strlen($messagetoprint) > 0) {
        $msgtitle = match ($messagetype) {
            'message' => $l['t_message'],
            'warning' => $l['t_warning'],
            'error' => $l['t_error'],
            default => $messagetype,
        };
        // announce the message to assistive technologies: warnings/errors are
        // assertive (role="alert"), informational messages are polite (role="status")
        $msgrole = match ($messagetype) {
            'warning', 'error' => 'alert',
            default => 'status',
        };
        echo
            '<div class="'
                . $messagetype
                . '" role="'
                . $msgrole
                . '">'
                . $msgtitle
                . ': '
                . htmlspecialchars($messagetoprint, ENT_QUOTES, 'UTF-8')
                . '</div>'
                . K_NEWLINE
        ;
        if (K_ENABLE_JSERRORS) {
            //display message on JavaScript Alert Window.
            echo '<script type="text/javascript">' . K_NEWLINE;
            echo '//<![CDATA[' . K_NEWLINE;
            echo
                'alert('
                    . json_encode(
                        '[' . $msgtitle . ']: ' . $messagetoprint,
                        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE,
                    )
                    . ');'
                    . K_NEWLINE
            ;
            echo '//]]>' . K_NEWLINE;
            echo '</script>' . K_NEWLINE;
        }
    }

    if ($exit) {
        exit(); // terminate the current script
    }
}

/**
 * Print the database error message.
 * @param mixed $exit If truthy, output a message and terminate the current script.
 */
function F_display_db_error(mixed $exit = true): void
{
    global $db;
    $messagetype = 'ERROR';
    $messagetoprint = F_db_error($db);
    F_print_error($messagetype, $messagetoprint, $exit);
}

/**
 * Custom PHP error handler function.
 * @param int $errno Error level.
 * @param string $errstr Error message.
 * @param string $errfile File where the error was raised.
 * @param int $errline Line where the error was raised.
 */
function F_error_handler(int $errno, string $errstr, string $errfile, int $errline): void
{
    $error_reporting = ini_get('error_reporting');
    if ($error_reporting === false || (is_numeric($error_reporting) && (float) $error_reporting === 0.0)) {
        // this is required to ignore supressed error messages with '@'
        return;
    }

    $messagetoprint = '[' . $errno . '] ' . $errstr . ' | LINE: ' . $errline . ' | FILE: ' . $errfile . '';
    $messagetoprint = strip_tags($messagetoprint, K_ALLOWED_ERROR_TAGS);
    match ($errno) {
        E_ERROR, E_USER_ERROR => F_print_error('ERROR', $messagetoprint, true),
        E_WARNING, E_USER_WARNING => F_print_error('ERROR', $messagetoprint, false),
        default => F_print_error('WARNING', $messagetoprint, false),
    };
}

// Set the custom error handler function
$old_error_handler = set_error_handler('F_error_handler', (int) K_ERROR_TYPES);

/**
 * Check if the URL exist.
 * @param string $url URL to check.
 * @return bool True if the URL exists; false otherwise.
 */
function F_url_exists(string $url): bool
{
    $crs = curl_init();
    curl_setopt($crs, CURLOPT_URL, $url);
    curl_setopt($crs, CURLOPT_NOBODY, true);
    curl_setopt($crs, CURLOPT_FAILONERROR, true);
    $open_basedir = ini_get('open_basedir');
    if (($open_basedir === false || $open_basedir === '') && !ini_get('safe_mode')) {
        curl_setopt($crs, CURLOPT_FOLLOWLOCATION, true);
    }

    curl_setopt($crs, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($crs, CURLOPT_TIMEOUT, 30);
    curl_setopt($crs, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($crs, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($crs, CURLOPT_USERAGENT, 'tc-lib-file');
    curl_exec($crs);
    $code = curl_getinfo($crs, CURLINFO_HTTP_CODE);
    unset($crs);
    return $code === 200;
}

/**
 * Wrapper for file_exists.
 * Checks whether a file or directory exists.
 * Only allows some protocols and local files.
 * @param mixed $filename Path to the file or directory.
 * Returns true if the file or directory exists; false otherwise.
 */
function F_file_exists(mixed $filename): bool
{
    if (preg_match('|^https?://|', $filename) === 1) {
        return F_url_exists((string) $filename);
    }

    if (strpos($filename, '://')) {
        return false; // only support http and https wrappers for security reasons
    }

    return file_exists($filename);
}
