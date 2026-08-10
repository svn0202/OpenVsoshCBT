<?php

//============================================================+
// File name   : tce_page_userbar.php
// Begin       : 2004-04-24
// Last Update : 2024-03-22
//
// Description : Display user's bar containing copyright
//               information, user status and language
//               selector.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display user's bar containing copyright information, user status and language selector.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2004-04-24
 */

// IMPORTANT: DO NOT REMOVE OR ALTER THIS PAGE!

/**
 * @var array{
 *     w_jump_timer:string,
 *     w_jump_menu:string,
 *     h_user_info:string,
 *     w_user:string,
 *     h_logout_link:string,
 *     w_logout:string,
 *     h_login_button:string,
 *     w_login:string,
 *     w_language:string,
 *     a_meta_charset:string,
 *     ov_institution_copyright:string,
 *     ov_support_service:string,
 *     ov_based_on:string,
 *     ov_no_warranty:string
 * } $l
 */
/** @var array{session_user_level:int,session_user_name:string} $session */
$session = $_SESSION;
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var bool $language_selector */
$language_selector = K_LANGUAGE_SELECTOR;
/** @return array<array-key,mixed>|null */
$normalize_array = static fn (mixed $value): ?array => is_array($value) ? $value : null;

// skip links
echo '<div class="minibutton" dir="ltr">' . K_NEWLINE;
echo
    '<a href="#timersection" accesskey="3" title="[3] '
        . $l['w_jump_timer']
        . '" class="white">'
        . $l['w_jump_timer']
        . '</a> <span style="color:white;">|</span>'
        . K_NEWLINE
;
echo
    '<a href="#menusection" accesskey="4" title="[4] '
        . $l['w_jump_menu']
        . '" class="white">'
        . $l['w_jump_menu']
        . '</a>'
        . K_NEWLINE
;
echo '</div>' . K_NEWLINE;

echo '<div class="userbar">' . K_NEWLINE;
if ($session['session_user_level'] > 0) {
    // display user information
    echo
        '<span title="'
            . $l['h_user_info']
            . '">'
            . $l['w_user']
            . ': '
            . htmlspecialchars($session['session_user_name'], ENT_QUOTES)
            . '</span>'
    ;
    // display logout link
    echo
        ' <a href="tce_logout.php" class="logoutbutton" title="'
            . $l['h_logout_link']
            . '" onclick="return confirm(\''
            . $l['w_logout']
            . ' ?\')">'
            . $l['w_logout']
            . '</a>'
            . K_NEWLINE
    ;
} else {
    // display login link
    echo
        ' <a href="tce_login.php" class="loginbutton" title="'
            . $l['h_login_button']
            . '">'
            . $l['w_login']
            . '</a>'
            . K_NEWLINE
    ;
}

echo '</div>' . K_NEWLINE;

// language selector
if ($language_selector && stristr($server['SCRIPT_NAME'], 'tce_test_execute.php') === false) {
    echo '<div class="minibutton" dir="ltr">' . K_NEWLINE;
    echo
        '<span class="langselector" role="group" aria-label="'
            . htmlspecialchars($l['w_language'], ENT_QUOTES, $l['a_meta_charset'])
            . '">'
            . K_NEWLINE
    ;
    $lang_array = $normalize_array(unserialize((string) K_AVAILABLE_LANGUAGES, ['allowed_classes' => false])) ?? [];
    /** @var array<string,string> $lang_array */
    $lngstr = '';
    foreach ($lang_array as $lang_code => $lang_name) {
        $lngstr .= ' | ';
        if ($lang_code === K_USER_LANG) {
            $lngstr .=
                '<span class="selected" title="'
                . $lang_name
                . '" aria-current="true">'
                . strtoupper($lang_code)
                . '</span>';
        } else {
            // query string was removed because unnecessary
            //if (isset($_SERVER['QUERY_STRING']) AND (strlen($_SERVER['QUERY_STRING'])>0)) {
            //	$querystr = preg_replace("/([\?|\&]?)lang=([a-z]{2,3})/si", '', $_SERVER['QUERY_STRING']);
            //}
            //if (isset($querystr) AND (strlen($querystr)>0)) {
            //	$langlink = $_SERVER['SCRIPT_NAME'].'?'.str_replace('&', '&amp;', $querystr).'&amp;lang='.$lang_code;
            //} else {
            $langlink = $server['SCRIPT_NAME'] . '?lang=' . $lang_code;
            //}
            $lngstr .=
                '<a href="'
                . $langlink
                . '" class="langselector" title="'
                . $lang_name
                . '">'
                . strtoupper($lang_code)
                . '</a>';
        }
    }

    echo substr($lngstr, 3);
    echo '</span>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
}

echo '<div class="minibutton" dir="ltr">';
echo
    '<span class="copyright"><span class="institution-credit">&copy; '
        . htmlspecialchars($l['ov_institution_copyright'], ENT_QUOTES, $l['a_meta_charset'])
        . '</span><br />'
        . '<span class="support-credit">'
        . htmlspecialchars($l['ov_support_service'], ENT_QUOTES, $l['a_meta_charset'])
        . ': olymp@gia66.ru</span><br />'
        . '<span class="upstream-credit"><a href="'
        . htmlspecialchars((string) K_OPENVSOSHCBT_SOURCE_URL, ENT_QUOTES, $l['a_meta_charset'])
        . '" rel="noopener">OpenVsoshCBT</a> '
        . htmlspecialchars($l['ov_based_on'], ENT_QUOTES, $l['a_meta_charset'])
        . ' TCExam ver. '
        . (string) K_TCEXAM_VERSION
        . ' · Copyright &copy; 2004–2026 Nicola Asuni, Tecnick.com LTD · <a href="'
        . K_PATH_URL
        . 'LICENSE">AGPL-3.0-or-later</a> · '
        . htmlspecialchars($l['ov_no_warranty'], ENT_QUOTES, $l['a_meta_charset'])
        . '</span></span>'
;
echo '</div>' . K_NEWLINE;
