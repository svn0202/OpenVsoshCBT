<?php

//============================================================+
// File name   : tce_functions_tcecode_editor.php
// Begin       : 2002-02-20
// Last Update : 2023-11-30
//
// Description : TCExam Code Editor (editor for special mark-up
//               code used to add some text formatting)
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions for custom mark-up language editor.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2002-02-20
 */

/**
 * Display TCExam Code EDITOR Tag Buttons
 * @author Nicola Asuni
 * @since 2006-03-07
 * @param string $callingform name of calling xhtml form
 * @param string $callingfield name of calling form field (textarea where output code will be sent)
 * @return string XHTML string
 */
function tcecode_editor_tag_buttons(string $callingform, string $callingfield): string
{
    global $l, $db;
    global $uploadedfile;
    require_once '../config/tce_config.php';

    // sanitize input parameters
    $callingform = preg_replace('/[^a-z0-9_]/', '', $callingform) ?? '';
    $callingfield = preg_replace('/[^a-z0-9_]/', '', $callingfield) ?? '';

    $buttons = '';

    // --- buttons

    $onclick = "FJ_undo(document.getElementById('" . $callingform . "')." . $callingfield . ')';
    $buttons .= get_image_button((string) $l['w_undo'], '', K_PATH_IMAGES . 'buttons/undo.gif', $onclick, 'z');

    $onclick = "FJ_redo(document.getElementById('" . $callingform . "')." . $callingfield . ')';
    $buttons .= get_image_button((string) $l['w_redo'], '', K_PATH_IMAGES . 'buttons/redo.gif', $onclick, 'y');

    $onclick = "FJ_insert_tag(document.getElementById('" . $callingform . "')." . $callingfield . '';
    $buttons .= get_image_button('bold', '[b]', K_PATH_IMAGES . 'buttons/bold.gif', $onclick, 'b');
    $buttons .= get_image_button('italic', '[i]', K_PATH_IMAGES . 'buttons/italic.gif', $onclick, 'i');
    $buttons .= get_image_button('underline', '[u]', K_PATH_IMAGES . 'buttons/under.gif', $onclick, 'u');
    $buttons .= get_image_button('strikethrough', '[s]', K_PATH_IMAGES . 'buttons/strike.gif', $onclick, 'd');
    $buttons .= get_image_button('small', '[small]', K_PATH_IMAGES . 'buttons/small.gif', $onclick, 's');
    $buttons .= get_image_button('subscript', '[sub]', K_PATH_IMAGES . 'buttons/subscr.gif', $onclick, 'v');
    $buttons .= get_image_button('superscript', '[sup]', K_PATH_IMAGES . 'buttons/superscr.gif', $onclick, 'a');
    $buttons .= get_image_button('link', '[url]', K_PATH_IMAGES . 'buttons/link.gif', $onclick, 'k');
    $buttons .= get_image_button('unordered list', '[ulist]', K_PATH_IMAGES . 'buttons/bullist.gif', $onclick, 'l');
    $buttons .= get_image_button('ordered list', '[olist]', K_PATH_IMAGES . 'buttons/numlist.gif', $onclick, 'o');
    $buttons .= get_image_button('list item', '[li]', K_PATH_IMAGES . 'buttons/li.gif', $onclick, 't');
    $buttons .= get_image_button('LRT', '[dir=ltr]', K_PATH_IMAGES . 'buttons/ltrdir.gif', $onclick, '');
    $buttons .= get_image_button('RTL', '[dir=rtl]', K_PATH_IMAGES . 'buttons/rtldir.gif', $onclick, '');

    // HTML5 native color pickers shown behind the legacy toolbar icons:
    // a transparent <input type="color"> is overlaid on the icon, so the
    // button looks like the others but still opens the native picker.
    $editfield = "document.getElementById('" . $callingform . "')." . $callingfield;

    $onchange = 'FJ_insert_tag(' . $editfield . ", '[bgcolor='+this.value+']')";
    $buttons .= '<span class="tcecodecolorwrap">';
    $buttons .=
        '<img src="'
        . K_PATH_IMAGES
        . 'buttons/bgcolor.gif" alt="background-color" class="button" width="23" height="22" />';
    $buttons .=
        '<input type="color" class="tcecodecolor" value="#000000" title="background-color" aria-label="background-color" onchange="'
        . $onchange
        . '" />';
    $buttons .= '</span>';

    $onchange = 'FJ_insert_tag(' . $editfield . ", '[color='+this.value+']')";
    $buttons .= '<span class="tcecodecolorwrap">';
    $buttons .=
        '<img src="' . K_PATH_IMAGES . 'buttons/color.gif" alt="color" class="button" width="23" height="22" />';
    $buttons .=
        '<input type="color" class="tcecodecolor" value="#000000" title="color" aria-label="color" onchange="'
        . $onchange
        . '" />';
    $buttons .= '</span>';

    $onclick = "FJ_insert_tag(document.getElementById('" . $callingform . "')." . $callingfield . '';
    $buttons .= get_image_button('code', '[code]', K_PATH_IMAGES . 'buttons/code.gif', $onclick, 'c');
    $buttons .= get_image_button('latex', '[tex]', K_PATH_IMAGES . 'buttons/latex.gif', $onclick, 'm');

    $buttons .= get_image_button('mathml', '[mathml]', K_PATH_IMAGES . 'buttons/mathml.gif', $onclick, 'h');

    $onclick =
        "window.open('tce_select_mediafile.php?frm="
        . $callingform
        . '&amp;fld='
        . $callingfield
        . "','mediaselect','height=600,width=680,resizable=yes,menubar=no,scrollbars=yes,toolbar=no,directories=no,status=no,modal=yes')";
    $buttons .= get_image_button('object', '', K_PATH_IMAGES . 'buttons/image.gif', $onclick, '');

    $buttons .= '<br />' . K_NEWLINE;

    // font size
    $onselect = "FJ_insert_tag(document.getElementById('" . $callingform . "')." . $callingfield . ', ';
    $onselect .=
        "document.getElementById('font_size_"
        . $callingfield
        . "').options[document.getElementById('font_size_"
        . $callingfield
        . "').selectedIndex].value";
    $onselect .= ')';
    $buttons .=
        '<select name="font_size_'
        . $callingfield
        . '" id="font_size_'
        . $callingfield
        . '" title="'
        . $l['w_font_size']
        . '" aria-label="'
        . $l['w_font_size']
        . '" style="margin:0;padding:0;" onchange="'
        . $onselect
        . '">';
    $buttons .=
        '<option value="" selected="selected" style="background-color:gray;color:white;">'
        . $l['w_font_size']
        . '</option>';
    $buttons .= '<option value="[size=xx-small]">xx-small</option>';
    $buttons .= '<option value="[size=x-small]">x-small</option>';
    $buttons .= '<option value="[size=small]">small</option>';
    $buttons .= '<option value="[size=medium]">medium</option>';
    $buttons .= '<option value="[size=large]">large</option>';
    $buttons .= '<option value="[size=x-large]">x-large</option>';
    $buttons .= '<option value="[size=xx-large]">xx-large</option>';
    for ($i = 10; $i <= 400; $i += 10) {
        $buttons .= '<option value="[size=' . $i . '%]">' . $i . '%</option>';
    }

    $buttons .= '</select>' . K_NEWLINE;

    // font
    $tce_fonts = unserialize(K_AVAILABLE_FONTS);
    if (!empty($tce_fonts)) {
        $onselect = "FJ_insert_tag(document.getElementById('" . $callingform . "')." . $callingfield . ', ';
        $onselect .=
            "document.getElementById('font_"
            . $callingfield
            . "').options[document.getElementById('font_"
            . $callingfield
            . "').selectedIndex].value";
        $onselect .= ')';
        $buttons .=
            '<select name="font_'
            . $callingfield
            . '" id="font_'
            . $callingfield
            . '" title="'
            . $l['w_font']
            . '" aria-label="'
            . $l['w_font']
            . '" style="margin:0;padding:0;" onchange="'
            . $onselect
            . '">';
        $buttons .=
            '<option value="" selected="selected" style="background-color:gray;color:white;">'
            . $l['w_font']
            . '</option>';
        foreach ($tce_fonts as $fname => $font) {
            $buttons .= '<option value="[font=' . $font . ']">' . $fname . '</option>';
        }

        $buttons .= '</select>' . K_NEWLINE;
    }

    return $buttons;
}

/**
 * Return a button that toggles the visual HTML editor for a textarea.
 * The editor itself is initialized by rich-content-editor.js.
 *
 * @param string $field_id textarea ID
 * @return string button HTML
 */
function get_rich_content_editor_button(string $field_id): string
{
    $field_id = preg_replace('/[^a-z0-9_]/', '', $field_id) ?? '';
    return '<button type="button" class="xmlbutton rich-editor-toggle" data-rich-editor-for="'
        . $field_id
        . '" data-open-label="Открыть редактор" data-close-label="Закрыть редактор"'
        . ' aria-controls="' . $field_id . '" aria-expanded="false">Открыть редактор</button>';
}

/**
 * Display one tag button
 * @param string $name name of the button
 * @param string $tag tag value
 * @param string $image image file of button
 * @param string $onclick default onclick action
 * @param string $accesskey accesskey: character for keyboard shortcut
 * @return string XHTML string
 * @author Nicola Asuni
 * @since 2006-03-07
 */
function get_image_button(string $name, string $tag, string $image, string $onclick = '', string $accesskey = ''): string
{
    if (strlen($tag) > 0) {
        $onclick = $onclick . ", '" . $tag . "')";
    }

    $str =
        '<button type="button" class="tcecodebtn" onclick="'
        . $onclick
        . '" title="'
        . $name
        . ' ['
        . $accesskey
        . ']"';
    if (strlen($accesskey) > 0) {
        $str .= ' accesskey="' . $accesskey . '"';
    }

    $str .= '>';
    $str .=
        '<img src="' . $image . '" alt="' . $name . ' [' . $accesskey . ']" class="button" width="23" height="22" />';
    return $str . '</button>';
}
