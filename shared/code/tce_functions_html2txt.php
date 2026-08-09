<?php

//============================================================+
// File name   : tce_functions_html2txt.php
// Begin       : 2001-10-21
// Last Update : 2023-11-30
//
// Description : Function to convert HTML code to Text string.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Function to convert HTML code to Text string.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2003-03-31
 */

/**
 * Convert HTML code to Text string.
 * @param string $str HTML code string to convert.
 * @param bool $preserve_newlines If true convert newline characters to HTML line breaks.
 * @param bool $display_links If true gives a textual representation of links and images.
 * @return string Plain-text representation.
 */
function F_html_to_text(string $str, bool $preserve_newlines = false, bool $display_links = false): string
{
    require_once '../../shared/code/tce_functions_general.php';

    $dollar_replacement = ':.dlr.:'; //string replacement for dollar symbol

    //tags conversion table
    $tags2textTable = [
        "'<br[^>]*?>'i" => "\n",
        "'<p[^>]*?>'i" => "\n",
        "'</p>'i" => "\n",
        "'<div[^>]*?>'i" => "\n",
        "'</div>'i" => "\n",
        "'<table[^>]*?>'i" => "\n",
        "'</table>'i" => "\n",
        "'<tr[^>]*?>'i" => "\n",
        "'<th[^>]*?>'i" => "\t ",
        "'<td[^>]*?>'i" => "\t ",
        "'<li[^>]*?>\t'i" => "\n",
        "'<h[0-9][^>]*?>'i" => "\n\n",
        "'</h[0-9]>'i" => "\n",
        "'<head[^>]*?>.*?</head>'si" => "\n",
        // Strip out head
        "'<style[^>]*?>.*?</style>'si" => "\n",
        // Strip out style
        "'<script[^>]*?>.*?</script>'si" => "\n",
    ];

    $str = str_replace("\r\n", "\n", $str);

    $str = str_replace("\$", $dollar_replacement, $str); //replace special character

    //remove session variable PHPSESSID from links
    $str = preg_replace("/(\?|\&|%3F|%26|\&amp;|%26amp%3B)PHPSESSID(=|%3D)[a-z0-9]{32,32}/i", '', $str) ?? $str;

    //remove applet and get alternative content
    $str = preg_replace_callback(
        "/<applet[^>]*?>(.*?)<\/applet>/si",
        static fn(array $subs): string => preg_replace('/<param[^>]*>/i', '', $subs[1] ?? '') ?? ($subs[1] ?? ''),
        $str,
    ) ?? $str;

    //remove object and get alternative content
    $str = preg_replace_callback(
        "/<object[^>]*?>(.*?)<\/object>/si",
        static fn(array $subs): string => preg_replace('/<param[^>]*>/i', '', $subs[1] ?? '') ?? ($subs[1] ?? ''),
        $str,
    ) ?? $str;

    //indent list elements
    $firstposition = 0;
    while (($pos = strpos($str, '<ul')) !== false && $pos > $firstposition) {
        $str = preg_replace_callback(
            "/<ul[^>]*?>(.*?)<\/ul>/si",
            static fn(array $subs): string => preg_replace('/<li[^>]*>/i', "<li>\t", $subs[1] ?? '') ?? ($subs[1] ?? ''),
            $str,
        ) ?? $str;
        $firstposition = $pos;
    }

    $firstposition = 0;
    while (($pos = strpos($str, '<ol')) !== false && $pos > $firstposition) {
        $str = preg_replace_callback(
            "/<ol[^>]*?>(.*?)<\/ol>/si",
            static fn(array $subs): string => preg_replace('/<li[^>]*>/i', "<li>\t", $subs[1] ?? '') ?? ($subs[1] ?? ''),
            $str,
        ) ?? $str;
        $firstposition = $pos;
    }

    $str = preg_replace("'<img[^>]*alt[\s]*=[\s]*[\"\']*([^\"\'<>]*)[\"\'][^>]*>'i", "[IMAGE: \\1]", $str) ?? $str;

    // give a textual representation of links and images
    if ($display_links) {
        $str = preg_replace(
            "'<a[^>]*href[\s]*=[\s]*[\"\']*([^\"\'<>]*)[\"\'][^>]*>(.*?)</a>'si",
            "\\2 [LINK: \\1]",
            $str,
        ) ?? $str;
    }

    if (!$preserve_newlines) { //remove newlines
        $str = str_replace("\n", '', $str);
    }

    $str = preg_replace(array_keys($tags2textTable), array_values($tags2textTable), $str) ?? $str;

    $str = preg_replace("'<[^>]*?>'si", '', $str) ?? $str; //strip out remaining tags

    //remove some newlines in excess
    $str = preg_replace("'[ \t\f]+[\r\n]'si", "\n", $str) ?? $str;
    $str = preg_replace("'[\r\n][\r\n]+'si", "\n\n", $str) ?? $str;

    $str = unhtmlentities($str, false);

    $str = str_replace($dollar_replacement, "\$", $str); //restore special character

    return stripslashes(trim($str));
}
