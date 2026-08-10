<?php

//============================================================+
// File name   : tce_xhtml_header.php
// Begin       : 2004-04-24
// Last Update : 2023-11-30
//
// Description : Output defaults XHTML header (doctype + head).
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Outputs default XHTML header (doctype + head).
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2004-04-24
 * int $pagelevel page access level (0-10), default 0
 * string $thispage_title page title, default K_SITE_TITLE
 * string $thispage_description page description, default K_SITE_DESCRIPTION
 * string $thispage_author page author, default K_SITE_AUTHOR
 * string $thispage_reply page reply to, default K_SITE_REPLY_TO
 * string $thispage_keywords page keywords, default K_SITE_KEYWORDS
 * string $thispage_icon page icon, default K_SITE_ICON
 * string $thispage_style page CSS file name, default K_SITE_STYLE
 */

/** @var array{
 *     a_meta_dir:string,
 *     a_meta_language:string,
 *     a_meta_charset:string,
 *     t_login_form?:string,
 *     ov_open_menu:string,
 *     ov_close_menu:string,
 *     ov_show_password:string,
 *     ov_hide_password:string,
 *     ov_theme_dark:string,
 *     ov_theme_light:string,
 *     ov_enable_dark_theme:string,
 *     ov_enable_light_theme:string,
 *     w_skip_navigation:string,
 *     m_login_wrong:string
 * } $l
 */
// if necessary load default values
/** @var mixed $pagelevel */
if (!isset($pagelevel) || empty($pagelevel)) {
    $pagelevel = 0;
}
/** @var int|string $pagelevel */

/** @var mixed $thispage_title */
if (!isset($thispage_title) || empty($thispage_title)) {
    $thispage_title = K_SITE_TITLE;
}
/** @var string $thispage_title */

/** @var mixed $thispage_description */
if (!isset($thispage_description) || empty($thispage_description)) {
    $thispage_description = K_SITE_DESCRIPTION;
}
/** @var string $thispage_description */

/** @var mixed $thispage_author */
if (!isset($thispage_author) || empty($thispage_author)) {
    $thispage_author = K_SITE_AUTHOR;
}
/** @var string $thispage_author */

/** @var mixed $thispage_reply */
if (!isset($thispage_reply) || empty($thispage_reply)) {
    $thispage_reply = K_SITE_REPLY;
}
/** @var string $thispage_reply */

/** @var mixed $thispage_keywords */
if (!isset($thispage_keywords) || empty($thispage_keywords)) {
    $thispage_keywords = K_SITE_KEYWORDS;
}
/** @var string $thispage_keywords */

/** @var mixed $thispage_icon */
if (!isset($thispage_icon) || empty($thispage_icon)) {
    $thispage_icon = K_SITE_ICON;
}
/** @var string $thispage_icon */

/** @var mixed $thispage_style */
if (!isset($thispage_style) || empty($thispage_style)) {
    $thispage_style = strcasecmp($l['a_meta_dir'], 'rtl') === 0 ? K_SITE_STYLE_RTL : K_SITE_STYLE;
}
/** @var string $thispage_style */

echo '<!DOCTYPE html>' . K_NEWLINE;
echo '<html lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;

echo '<head>' . K_NEWLINE;
echo '<meta charset="' . $l['a_meta_charset'] . '" />' . K_NEWLINE;
echo '<title>' . htmlspecialchars($thispage_title, ENT_NOQUOTES, $l['a_meta_charset']) . '</title>' . K_NEWLINE;
echo '<meta name="viewport" content="width=device-width, initial-scale=1" />' . K_NEWLINE;
echo '<meta name="language" content="' . $l['a_meta_language'] . '" />' . K_NEWLINE;
echo '<meta name="tcexam_level" content="' . $pagelevel . '" />' . K_NEWLINE;
echo
    '<meta name="description" content="'
        . htmlspecialchars($thispage_description, ENT_COMPAT, $l['a_meta_charset'])
        . ' ['
        . (string) base64_decode(K_KEY_SECURITY)
        . ']" />'
        . K_NEWLINE
;
echo
    '<meta name="author" content="'
        . htmlspecialchars($thispage_author, ENT_COMPAT, $l['a_meta_charset'])
        . '" />'
        . K_NEWLINE
;
echo
    '<meta name="reply-to" content="'
        . htmlspecialchars($thispage_reply, ENT_COMPAT, $l['a_meta_charset'])
        . '" />'
        . K_NEWLINE
;
echo
    '<meta name="keywords" content="'
        . htmlspecialchars($thispage_keywords, ENT_COMPAT, $l['a_meta_charset'])
        . '" />'
        . K_NEWLINE
;
$theme_stylesheet = ($l['a_meta_dir'] === 'rtl') ? 'picoman_rtl.css' : 'picoman.css';
echo '<link rel="stylesheet" href="' . K_PATH_STYLE_SHEETS . $theme_stylesheet . '?v=20260718-2" />' . K_NEWLINE;
echo '<link rel="stylesheet" href="' . K_PATH_STYLE_SHEETS . 'tmf-reference.css?v=20260809-2" />' . K_NEWLINE;
echo '<link rel="icon" href="' . $thispage_icon . '" />' . K_NEWLINE;
echo '<link rel="manifest" href="../manifest.webmanifest" />' . K_NEWLINE;
echo '<meta name="theme-color" content="#183b64" />' . K_NEWLINE;
echo '<script type="text/javascript">'
    . 'if("serviceWorker" in navigator){window.addEventListener("load",function(){'
    . 'navigator.serviceWorker.register("../sw.js",{scope:"../"}).catch(function(){return null;});'
    . '});}</script>' . K_NEWLINE;
echo '<!-- TCExam19730104 -->' . K_NEWLINE;
echo '</head>' . K_NEWLINE;

$script_name = f_tmf_public_header_script_name($_SERVER);
$is_login_page = (basename($script_name) === 'tce_login.php'
    || $thispage_title === ($l['t_login_form'] ?? null));
/** @var array{session_user_level:int} $session */
$session = $_SESSION;
$body_classes = ($session['session_user_level'] < 1 || $is_login_page)
    ? ['login-page']
    : ['app-page', 'theme-light'];
require_once '../../shared/code/tce_functions_openvsosh_settings.php';
/** @var array{
 *     ui_font:string,
 *     login_background_overlay:int|numeric-string,
 *     login_background_position:string,
 *     login_background_size:string
 * } $appearance
 */
$appearance = openvsosh_get_appearance_settings();
$body_classes[] = 'ui-font-' . $appearance['ui_font'];
if (basename($script_name) === 'tce_test_execute.php') {
    $body_classes[] = 'exam-page';
}

$body_attributes = ' class="' . implode(' ', $body_classes) . '"';
require_once '../../shared/code/tce_functions_site_assets.php';
if ($is_login_page && openvsosh_site_asset_metadata('background')) {
    $overlay = max(0, min(80, (int) $appearance['login_background_overlay'])) / 100;
    $body_attributes .= ' style="--login-background-image:url(&quot;tce_site_asset.php?type=background&quot;);'
        . '--login-background-position:' . $appearance['login_background_position'] . ';'
        . '--login-background-size:' . $appearance['login_background_size'] . ';'
        . '--login-background-overlay:' . $overlay . '"';
}
$shell_translations = [
    'open-menu' => $l['ov_open_menu'],
    'close-menu' => $l['ov_close_menu'],
    'show-password' => $l['ov_show_password'],
    'hide-password' => $l['ov_hide_password'],
    'theme-dark' => $l['ov_theme_dark'],
    'theme-light' => $l['ov_theme_light'],
    'enable-dark-theme' => $l['ov_enable_dark_theme'],
    'enable-light-theme' => $l['ov_enable_light_theme'],
];
foreach ($shell_translations as $name => $translation) {
    $body_attributes .= ' data-' . $name . '="'
        . htmlspecialchars($translation, ENT_QUOTES, $l['a_meta_charset']) . '"';
}
echo '<body' . $body_attributes . '>' . K_NEWLINE;
// accessibility: skip link to the main content (must be the first focusable element)
echo
    '<a href="#maincontent" class="skiplink" accesskey="2" title="[2] '
        . htmlspecialchars($l['w_skip_navigation'], ENT_QUOTES, $l['a_meta_charset'])
        . '">'
        . $l['w_skip_navigation']
        . '</a>'
        . K_NEWLINE
;

if (!empty($GLOBALS['login_error'])) {
    F_print_error('WARNING', $l['m_login_wrong']);
}

/** @param array<array-key,mixed> $server */
function f_tmf_public_header_script_name(array $server): string
{
    if (!isset($server['SCRIPT_NAME']) || !is_string($server['SCRIPT_NAME'])) {
        return '';
    }
    return $server['SCRIPT_NAME'];
}
