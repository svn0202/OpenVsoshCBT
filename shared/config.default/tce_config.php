<?php

//============================================================+
// File name   : tce_config.php
// Begin       : 2002-02-24
// Last Update : 2025-06-12
//
// Description : Shared configuration file.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Shared configuration file.
 * @package com.tecnick.tcexam.shared.cfg
 * @brief TCExam Main Configuration
 * @author Nicola Asuni
 * @since 2002-02-24
 */

/**
 * TCExam version (do not change).
 */
define('K_TCEXAM_VERSION', file_get_contents('../../VERSION'));

/**
 * 2-letters code for default language.
 */
$openvsosh_bootstrap_language = 'ru';
$openvsosh_bootstrap_timezone = 'UTC';
$openvsosh_bootstrap_file = __DIR__ . '/openvsosh-bootstrap.json';
if (is_file($openvsosh_bootstrap_file)) {
    /** @var null|bool|int|float|string|array{language?: mixed, timezone?: mixed} $openvsosh_bootstrap */
    $openvsosh_bootstrap = json_decode((string) file_get_contents($openvsosh_bootstrap_file), true);
    $openvsosh_languages = [
        'ar', 'az', 'bg', 'br', 'cn', 'de', 'el', 'en', 'es', 'fa', 'fr', 'he', 'hi',
        'hu', 'id', 'it', 'jp', 'mr', 'ms', 'nl', 'pl', 'ro', 'ru', 'tr', 'ur', 'vn',
    ];
    if (is_array($openvsosh_bootstrap)) {
        $openvsosh_language_candidate = (string) ($openvsosh_bootstrap['language'] ?? '');
        if (in_array($openvsosh_language_candidate, $openvsosh_languages, true)) {
            $openvsosh_bootstrap_language = $openvsosh_language_candidate;
        }
        $openvsosh_timezone_candidate = (string) ($openvsosh_bootstrap['timezone'] ?? '');
        if (in_array($openvsosh_timezone_candidate, timezone_identifiers_list(), true)) {
            $openvsosh_bootstrap_timezone = $openvsosh_timezone_candidate;
        }
    }
}
define('K_LANGUAGE', $openvsosh_bootstrap_language);

/**
 * If true, display a language selector.
 */
define('K_LANGUAGE_SELECTOR', false);

/**
 * Defines a serialized array of available languages.
 * Each language is indexed using a 2-letters code (ISO 639).
 */
define('K_AVAILABLE_LANGUAGES', serialize([
    'ar' => 'Arabian',
    'az' => 'Azerbaijani',
    'bg' => 'Bulgarian',
    'br' => 'Brazilian Portuguese',
    'cn' => 'Chinese',
    'de' => 'German',
    'el' => 'Greek',
    'en' => 'English',
    'es' => 'Spanish',
    'fa' => 'Farsi',
    'fr' => 'French',
    'he' => 'Hebrew',
    'hi' => 'Hindi',
    'hu' => 'Hungarian',
    'id' => 'Indonesian',
    'it' => 'Italian',
    'jp' => 'Japanese',
    'mr' => 'Marathi',
    'ms' => 'Malay (Bahasa Melayu)',
    'nl' => 'Dutch',
    'pl' => 'Polish',
    'ro' => 'Romanian',
    'ru' => 'Russian',
    'tr' => 'Turkish',
    'ur' => 'Urdu',
    'vn' => 'Vietnamese',
]));

// -- INCLUDE files --
require_once '../../shared/config/tce_paths.php';
require_once '../../shared/config/tce_general_constants.php';

/**
 * If true enable One-Time-Password authentication on login.
 */
define('K_OTP_LOGIN', false);

/**
 * Ratio at which the delay will be increased after every failed login attempt.
 */
define('K_BRUTE_FORCE_DELAY_RATIO', 2);

/**
 * Number of difficulty levels for questions.
 */
define('K_QUESTION_DIFFICULTY_LEVELS', 10);

/**
 * Popup window height in pixels for test info.
 */
define('K_TEST_INFO_HEIGHT', 400);

/**
 * Popup window width in pixels for test info.
 */
define('K_TEST_INFO_WIDTH', 700);

/**
 * Number of columns for answer textarea.
 */
define('K_ANSWER_TEXTAREA_COLS', 70);

/**
 * Number of rows for answer textarea.
 */
define('K_ANSWER_TEXTAREA_ROWS', 15);

/**
 * If true enable explanation field for questions.
 */
define('K_ENABLE_QUESTION_EXPLANATION', true);

/**
 * If true enable explanation field for answers.
 */
define('K_ENABLE_ANSWER_EXPLANATION', true);

/**
 * If true display test description before executing the test.
 */
define('K_DISPLAY_TEST_DESCRIPTION', true);

/**
 * If true compare short answers in binary mode.
 */
define('K_SHORT_ANSWERS_BINARY', false);

/**
 * User's session life time in seconds.
 */
define('K_SESSION_LIFE', K_SECONDS_IN_HOUR);

/**
 * When an alternate authentication method is used,
 * if this constant is true the default user groups for the selected
 * authentication method are always added to the user.
 */
define('K_USER_GROUP_RSYNC', false);

/**
 * Define timestamp format using PHP notation (do not change).
 */
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');

/**
 * Define max line length in chars for question navigator on test execution interface.
 */
define('K_QUESTION_LINE_MAX_LENGTH', 70);

/**
 * If true, check for possible session hijacking (set to false if you have login problems).
 */
define('K_CHECK_SESSION_FINGERPRINT', true);

// Client Cookie settings

/**
 * Cookie domain.
 */
define('K_COOKIE_DOMAIN', '');

/**
 * Cookie path.
 */
define('K_COOKIE_PATH', '/');

/**
 * If true use secure cookies.
 */
// Send Secure cookies whenever the current request is HTTPS. Keeping this dynamic
// allows local HTTP development (notably Safari on localhost) without weakening
// cookies on the production HTTPS deployment, including behind a TLS proxy.
$https_forwarded = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
    && strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https';
$https_direct = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
define('K_COOKIE_SECURE', $https_direct || $https_forwarded);

/**
 * When true the cookie will be made accessible only through the HTTP protocol.
 */
define('K_COOKIE_HTTPONLY', true);

/**
 * The SameSite attribute lets servers specify whether/when cookies are sent with cross-site requests.
 */
define('K_COOKIE_SAMESITE', 'Strict');

/**
 * Expiration time for cookies.
 */
define('K_COOKIE_EXPIRE', K_SECONDS_IN_DAY);

/**
 * Various pages redirection modes after login (valid values are 1, 2, 3 and 4).
 * 1 = relative redirect.
 * 2 = absolute redirect.
 * 3 = html redirect.
 * 4 = full redirect.
 */
define('K_REDIRECT_LOGIN_MODE', 4);

/**
 * If true enable password reset feature.
 */
define('K_PASSWORD_RESET', false);

/**
 * URL to be redirected at logout (leave empty for default).
 */
define('K_LOGOUT_URL', '');

// Error settings

/**
 * Define error reporting types for debug.
 */
define('K_ERROR_TYPES', E_ALL);
//define ('K_ERROR_TYPES', E_ERROR | E_WARNING | E_PARSE);

/**
 * Enable error logs (../log/tce_errors.log).
 */
define('K_USE_ERROR_LOG', false);

/**
 * If true display messages and errors on Javascript popup window.
 */
define('K_ENABLE_JSERRORS', false);

/**
 * Set your own timezone here.
 * Possible values are listed on:
 * http://php.net/manual/en/timezones.php
 */
define('K_TIMEZONE', $openvsosh_bootstrap_timezone);
date_default_timezone_set((string) K_TIMEZONE);
unset(
    $openvsosh_bootstrap,
    $openvsosh_bootstrap_file,
    $openvsosh_bootstrap_language,
    $openvsosh_bootstrap_timezone,
    $openvsosh_language_candidate,
    $openvsosh_languages,
    $openvsosh_timezone_candidate,
);

/**
 * Default minutes used to extend test duration.
 */
define('K_EXTEND_TIME_MINUTES', 5);

// ---------- * ---------- * ---------- * ---------- * ----------

/**
 * Error handlers.
 */
require_once '../../shared/code/tce_functions_errmsg.php';

// load language resources

/** @var array<string, string> $available_languages */
$available_languages = unserialize((string) K_AVAILABLE_LANGUAGES);
/** @var bool $cookie_secure */
$cookie_secure = K_COOKIE_SECURE;
/** @var string|null $request_language */
$request_language = $_REQUEST['lang'] ?? null;
/** @var string|null $cookie_language */
$cookie_language = $_COOKIE['SessionUserLang'] ?? null;

// set user's selected language or default language
if (
    isset($request_language) and strlen($request_language) === 2
    and array_key_exists($request_language, $available_languages)
) {
    /**
     * Use requested language.
     * @ignore
     */
    define('K_USER_LANG', $request_language);
    // set client cookie
    setcookie(
        'SessionUserLang',
        (string) K_USER_LANG,
        time() + (int) K_COOKIE_EXPIRE,
        K_COOKIE_PATH,
        K_COOKIE_DOMAIN,
        $cookie_secure,
    );
} elseif (
    isset($cookie_language) and strlen($cookie_language) === 2
    and array_key_exists($cookie_language, $available_languages)
) {
    /**
     * Use session language.
     * @ignore
     */
    define('K_USER_LANG', $cookie_language);
} else {
    /**
     * Use default language.
     * @ignore
     */
    define('K_USER_LANG', K_LANGUAGE);
}
unset($available_languages, $cookie_language, $cookie_secure, $request_language);

// TMX class
require_once '../../shared/code/tce_tmx.php';
// instantiate new TMXResourceBundle object
$lang_resources = new TMXResourceBundle(
    K_PATH_TMX_FILE,
    K_USER_LANG,
    K_PATH_LANG_CACHE . basename(K_PATH_TMX_FILE, '.xml') . '_' . (string) K_USER_LANG . '.php',
);
$l = $lang_resources->getResource(); // language array

ini_set('arg_separator.output', '&amp;');
//date_default_timezone_set(K_TIMEZONE);

// NOTE: the legacy register-globals emulation (a foreach over $_POST that auto-created bare
// $variables from posted keys) was removed in the modernisation (plan Stage 8.2). Every
// controller now reads its inputs explicitly from $_POST/$_REQUEST, which also closes the
// variable-injection vector the emulation exposed.

/**
 * Lisf of enabled custom authentication methods.
 * Add your custom authentication methods here.
 */
define(
    'K_CUSTOM_AUTH_METHODS',
    serialize([
        // 'basic', // Uncomment to enable the simple custom authentication method.
        /**
         * Take a look at the following files to create your own custom authentication method.
         *
         * ../../shared/config/custom_auth/basic.php
         * ../../shared/custom_auth/basic.php
         */
    ]),
);
