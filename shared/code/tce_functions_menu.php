<?php

//============================================================+
// File name   : tce_functions_menu.php
// Begin       : 2001-09-08
// Last Update : 2023-11-30
//
// Description : Functions for Web menu.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions for Web menu.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2010-09-16
 */

/**
 * Returns a menu element link wit subitems.
 * If the link refers to the current page, only the name will be returned.
 * @param $link (string) URL
 * @param $data (array) link data
 * @param $level (int) item level
 */
function F_menu_link($link, $data, $level = 0): ?string
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $level = (int) $level;
    if (!$data['enabled'] || $_SESSION['session_user_level'] < $data['level']) {
        // this item is disabled
        return null;
    }

    $description = '';
    if ($level > 0 && !empty($data['title']) && trim((string) $data['title']) !== trim((string) $data['name'])) {
        $description = '<small class="menu-description">' . $data['title'] . '</small>';
    }
    $str = '<li>';
    if ((string) $link !== basename($_SERVER['SCRIPT_NAME'])) {
        $str .= '<a href="' . $data['link'] . '" title="' . $data['title'] . '"';
        if (!empty($data['key'])) {
            $str .= ' accesskey="' . (string) $data['key'] . '"';
        }

        if (F_menu_isChildActive($data)) {
            $str .= ' class="active"';
        }

        $str .= '>' . f_menu_icon_svg((string) ($data['icon'] ?? ''))
            . '<span class="menu-label">' . $data['name'] . $description . '</span></a>';
    } else {
        // current page (active link): mark it for assistive technologies
        $str .= '<span class="active" data-menu-link="'
            . htmlspecialchars($data['link'], ENT_QUOTES, $l['a_meta_charset'])
            . '" aria-current="page">' . f_menu_icon_svg((string) ($data['icon'] ?? ''))
            . '<span class="menu-label">' . $data['name'] . $description . '</span></span>';
    }

    if (isset($data['sub']) && !empty($data['sub'])) {
        // print sub-items
        $sublevel = $level + 1;
        $str .= K_NEWLINE;
        $str .= '<ul>' . K_NEWLINE;
        foreach ($data['sub'] as $sublink => $subdata) {
            $str .= F_menu_link($sublink, $subdata, $sublevel) ?? '';
        }

        $str .= '</ul>' . K_NEWLINE;
    }

    return $str . ('</li>' . K_NEWLINE);
}

/**
 * Return a small dependency-free line icon for trusted menu definitions.
 */
function f_menu_icon_svg(string $icon): string
{
    $paths = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/><path d="M9.5 20v-6h5v6"/>',
        'users' => '<path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'library' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'tests' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m8 12 2.5 2.5L16 9"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.1h-4V21a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4h-.1v-4H3A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1v-.1h4V3A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.14.38.35.72.6 1 .28.3.64.45 1 .4h.1v4H21a1.7 1.7 0 0 0-1.6.6z"/>',
        'backup' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'external' => '<path d="M14 3h7v7"/><path d="m10 14 11-11"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.5 2.5 0 1 1 4.2 1.8c-1 .8-1.7 1.3-1.7 2.7"/><path d="M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 11v6"/><path d="M12 7h.01"/>',
        'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'login' => '<path d="m14 8 4 4-4 4"/><path d="M18 12H7"/><path d="M10 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
    ];
    $path = $paths[$icon] ?? '';
    if ($path === '') {
        return '';
    }
    return '<svg class="menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . $path . '</svg>';
}

/**
 * Returns true if the menu item has an active child, false otherwise.
 * @param $data (array) link data
 */
function F_menu_isChildActive($data): bool
{
    if (isset($data['sub']) && !empty($data['sub'])) {
        if (array_key_exists(basename($_SERVER['SCRIPT_NAME']), $data['sub'])) {
            // key found
            return true;
        }

        // try sub-trees
        foreach ($data['sub'] as $submenu) {
            if (F_menu_isChildActive($submenu)) {
                return true;
            }
        }
    }

    return false;
}
