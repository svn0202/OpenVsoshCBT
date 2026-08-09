<?php

//============================================================+
// File name   : tce_paths.php
// Begin       : 2002-01-15
// Last Update : 2023-11-30
//
// Description : Configuration file for files and directories
//               paths.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Configuration file for files and directories paths.
 * @package com.tecnick.tcexam.shared.cfg
 * @author Nicola Asuni
 * @since 2002-01-15
 */

/**
 * Host URL (e.g.: "http://www.yoursite.com").
 */
define('K_PATH_HOST', '');

/**
 * Relative URL where this program is installed (e.g.: "/").
 */
define('K_PATH_TCEXAM', '');

/**
 * Real full path where this program is installed (e.g: "/var/www/html/TCExam/").
 */
define('K_PATH_MAIN', '');

/**
 * Constant used on TCPDF library.
 */
define('K_PATH_URL', K_PATH_HOST . K_PATH_TCEXAM);

/**
 * Standard port for http (80) or https (443).
 */
define('K_STANDARD_PORT', 80);

/**
 * Allowlist of filesystem path prefixes that the tc-lib-file safe file methods
 * (Com\Tecnick\File\File) are permitted to read. Keeps local file access (e.g. the
 * OMR bulk importer) contained within trusted directories and blocks path traversal.
 * Provide a serialized array of absolute path prefixes. The cache directory
 * (K_PATH_CACHE) is always trusted in addition to the entries listed here.
 * Example: define('K_FILE_ALLOWED_PATHS', serialize(array('/srv/tcexam-imports/')));
 */
define('K_FILE_ALLOWED_PATHS', serialize([]));

/**
 * Allowlist of hostnames that the tc-lib-file safe methods are permitted to fetch
 * remote URLs from (mitigates SSRF). Used e.g. by the email report generator, which
 * renders a PDF via an internal HTTP request to this installation. Provide a serialized
 * array of hostnames. The host of K_PATH_HOST is always trusted in addition to these.
 * Example: define('K_FILE_ALLOWED_HOSTS', serialize(array('reports.example.com')));
 */
define('K_FILE_ALLOWED_HOSTS', serialize([]));

// -----------------------------------------------------------------------------
// --- DO NOT CHANGE THE FOLLOWING VALUES --------------------------------------
// -----------------------------------------------------------------------------

/**
 * Path to public code.
 */
define('K_PATH_PUBLIC_CODE', K_PATH_HOST . K_PATH_TCEXAM . 'public/code/');

/**
 * Server path to public code.
 */
define('K_PATH_PUBLIC_CODE_REAL', K_PATH_MAIN . 'public/code/');

/**
 * Full path to cache directory.
 */
define('K_PATH_CACHE', K_PATH_MAIN . 'cache/');

/**
 * URL path to to cache directory.
 */
define('K_PATH_URL_CACHE', K_PATH_TCEXAM . 'cache/');

/**
 * Full path to cache directory used for language files.
 */
define('K_PATH_LANG_CACHE', K_PATH_CACHE . 'lang/');

/**
 * Full path to backup directory.
 * Please protect this directory with extra authentication to restrict access.
 */
define('K_PATH_BACKUP', K_PATH_MAIN . 'admin/backup/');

/**
 * Full path to the generated tc-lib-pdf fonts directory.
 * Guarded so a PDF entry point may predefine it; the default points at the fonts generated
 * by tc-lib-pdf-font (run `make fonts`, or the Composer post-install hook).
 */
if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', K_PATH_MAIN . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/');
}

/**
 * Relative path to stylesheets directory.
 */
define('K_PATH_STYLE_SHEETS', '../styles/');

/**
 * Relative path to javascript directory.
 */
define('K_PATH_JSCRIPTS', '../jscripts/');

/**
 * Relative path to shared javascript directory.
 */
define('K_PATH_SHARED_JSCRIPTS', '../../shared/jscripts/');

/**
 * Relative path to images directory.
 */
define('K_PATH_IMAGES', '../../images/');

/**
 * Full path to TMX language file.
 */
define('K_PATH_TMX_FILE', K_PATH_MAIN . 'shared/config/lang/language_tmx.xml');

/**
 * Full path to a blank image.
 */
define('K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png');

// DOCUMENT_ROOT fix for IIS Webserver
$server_string = static fn(string $key): string => isset($_SERVER[$key]) && is_string($_SERVER[$key])
    ? $_SERVER[$key]
    : '';
if ($server_string('DOCUMENT_ROOT') === '') {
    $script_filename = $server_string('SCRIPT_FILENAME');
    $path_translated = $server_string('PATH_TRANSLATED');
    $php_self = $server_string('PHP_SELF');
    if ($script_filename !== '') {
        $_SERVER['DOCUMENT_ROOT'] = str_replace(
            '\\',
            '/',
            substr($script_filename, 0, -strlen($php_self)),
        );
    } elseif ($path_translated !== '') {
        $_SERVER['DOCUMENT_ROOT'] = str_replace(
            '\\',
            '/',
            substr(str_replace('\\\\', '\\', $path_translated), 0, -strlen($php_self)),
        );
    } else {
        // define here your DOCUMENT_ROOT path if the previous fails
        $_SERVER['DOCUMENT_ROOT'] = '/var/www';
    }
}
unset($server_string, $script_filename, $path_translated, $php_self);
