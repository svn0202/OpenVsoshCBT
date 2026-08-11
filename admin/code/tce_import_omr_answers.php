<?php

//============================================================+
// File name   : tce_import_omr_answers.php
// Begin       : 2011-05-20
// Last Update : 2023-11-30
//
// Description : Import test answers using OMR (Optical Mark Recognition)
//               technique applied to images of scanned answer sheets.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Import test answers using OMR (Optical Mark Recognition) technique applied to images of scanned answer sheets.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2011-05-20
 */

require_once '../config/tce_config.php';

/**
 * @var array{
 *     a_meta_charset:string,
 *     h_omr_data_page:string,
 *     h_omr_overwrite:string,
 *     h_submit_file:string,
 *     hp_omr_answers_importer:string,
 *     m_authorization_denied:string,
 *     m_import_error:string,
 *     m_import_ok:string,
 *     m_omr_wrong_answer_sheet:string,
 *     m_omr_wrong_test_data:string,
 *     t_omr_answers_importer:string,
 *     t_result_user:string,
 *     w_date:string,
 *     w_datetime_format:string,
 *     w_omr_answer_sheet:string,
 *     w_omr_data_page:string,
 *     w_overwrite:string,
 *     w_results:string,
 *     w_select:string,
 *     w_upload:string,
 *     w_user:string
 * } $l
 */
/** @var array{date?:string, overwrite?:mixed, user_id?:int|string} $request */
/** @var array{}|array{omrfile:array{error:list<int>, tmp_name:list<string>}} $files */
/** @var array{SCRIPT_NAME:string} $server */
/** @var array{session_user_id:int, session_user_level:int} $session */
/** @var mixed $db */
/** @var string|null $menu_mode */
$request = &$_REQUEST;
$files = &$_FILES;
$server = &$_SERVER;

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_OMR_IMPORT;
$max_omr_sheets = 10;
require_once '../../shared/code/tce_authorization.php';
$session = &$_SESSION;

$thispage_title = $l['t_omr_answers_importer'];
require_once 'tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_auth_sql.php';
require_once 'tce_functions_omr.php';
require_once 'tce_functions_user_select.php';

/** @var array{session_user_id:int, session_user_level:int} $session */
if (isset($request['user_id'])) {
    $user_id = (int) $request['user_id'];
    if (!f_is_authorized_editor_for_user($user_id)) {
        F_print_error('ERROR', $l['m_authorization_denied']);
        exit();
    }
} else {
    $user_id = 0;
}

if (isset($request['date'])) {
    $date = $request['date'];
    $date_time = strtotime($date);
    $date = date(K_TIMESTAMP_FORMAT, (int) $date_time);
} else {
    $date = date(K_TIMESTAMP_FORMAT);
}

if (!isset($request['overwrite']) || empty($request['overwrite'])) {
    $overwrite = false;
} else {
    $overwrite = f_get_boolean($request['overwrite']);
}

// process uploaded files
if (isset($menu_mode) && $menu_mode === 'upload' && $user_id > 0 && isset($files['omrfile'])) {
    $omr_file = $files['omrfile'];
    /** @var array{error:list<int>, tmp_name:list<string>} $omr_file */
    // read OMR DATA page
    $omr_testdata = f_decode_omr_test_data_qr_code($omr_file['tmp_name'][0] ?? '');
    if ($omr_testdata === false) {
        F_print_error('ERROR', $l['m_omr_wrong_test_data']);
    } else {
        /** @var array{0:int, ...<int,mixed>} $omr_testdata */
        // read OMR ANSWER SHEET pages
        $num_questions = count($omr_testdata) - 1;
        $num_pages = (int) ceil($num_questions / 30);
        $omr_answers = [];
        for ($i = 1; $i <= $num_pages; ++$i) {
            if ((int) ($omr_file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $answers_page = f_decode_omr_page($omr_file['tmp_name'][$i] ?? '');
                /** @var array<int,int>|false $answers_page */
                if ($answers_page !== false && !empty($answers_page)) {
                    $omr_answers += $answers_page;
                } else {
                    F_print_error('ERROR', '[OMR ANSWER SHEET ' . $i . '] ' . $l['m_omr_wrong_answer_sheet']);
                }
            } else {
                F_print_error('ERROR', '[OMR ANSWER SHEET ' . $i . '] ' . $l['m_omr_wrong_answer_sheet']);
            }
        }

        // sort answers
        ksort($omr_answers);
        // import answers
        if (f_import_omr_test_data($user_id, $date, $omr_testdata, $omr_answers, $overwrite)) {
            F_print_error(
                'MESSAGE',
                $l['m_import_ok']
                . ': <a href="tce_show_result_user.php?testuser_id=32&test_id='
                . $omr_testdata[0]
                . '&user_id='
                . $user_id
                . '" title="'
                . $l['t_result_user']
                . '" style="text-decoration:underline;color:#0000ff;">'
                . $l['w_results']
                . '</a>',
            );
        } else {
            F_print_error('ERROR', $l['m_import_error']);
        }
    }

    // remove uploaded files
    for ($i = 0; $i <= $max_omr_sheets; ++$i) {
        if ((int) ($omr_file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            f_omr_unlink_silently($omr_file['tmp_name'][$i] ?? '');
        }
    }
}

// -----------------------------------------------------------------------------

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_omrimport">'
        . K_NEWLINE
;

// select user
echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="user_id">' . $l['w_user'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="user_id" id="user_id" onchange="">' . K_NEWLINE;
$normalize_query_result = static function (mixed $result): mixed {
    if (
        is_bool($result)
        || is_resource($result)
        || $result instanceof \mysqli_result
        || $result instanceof \PgSql\Result
    ) {
        return $result;
    }
    return false;
};
/** @return array<array-key,mixed>|null */
$normalize_row = static fn (mixed $row): ?array => is_array($row) ? $row : null;
$sql = 'SELECT user_id, user_lastname, user_firstname, user_name FROM ' . K_TABLE_USERS . ' WHERE (user_id>1)';
if ($session['session_user_level'] < K_AUTH_ADMINISTRATOR) {
    // filter for level
    $sql .=
        ' AND ((user_level<' . $session['session_user_level'] . ') OR (user_id=' . $session['session_user_id'] . '))';
    // filter for groups
    $sql .=
        ' AND user_id IN (SELECT tb.usrgrp_user_id
		FROM '
        . K_TABLE_USERGROUP
        . ' AS ta, '
        . K_TABLE_USERGROUP
        . ' AS tb
		WHERE ta.usrgrp_group_id=tb.usrgrp_group_id
			AND ta.usrgrp_user_id='
        . $session['session_user_id']
        . '
			AND tb.usrgrp_user_id=user_id)';
}

$sql .= ' ORDER BY user_lastname, user_firstname, user_name';
if ($r = $normalize_query_result(F_db_query($sql, $db))) {
    $countitem = 1;
    echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    while ($m = $normalize_row(F_db_fetch_array($r))) {
        /** @var array{user_firstname:string, user_id:int|string, user_lastname:string, user_name:string} $m */
        echo '<option value="' . $m['user_id'] . '"';
        //if ($m['user_id'] == $user_id) {
        //	echo ' selected="selected"';
        //}
        echo
            '>'
                . $countitem
                . '. '
                . htmlspecialchars(
                    $m['user_lastname'] . ' ' . $m['user_firstname'] . ' - ' . $m['user_name'] . '',
                    ENT_NOQUOTES,
                    $l['a_meta_charset'],
                )
                . '</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;

// link for user selection popup
$jsaction = "selectWindow=window.open('tce_select_users_popup.php?cid=user_id', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no'); return false;";
echo '<button type="button" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</button>';

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

// -----------------------------------------------------------------------------
// date
echo
    get_form_row_text_input(
        'date',
        $l['w_date'],
        $l['w_date'] . ' ' . $l['w_datetime_format'],
        '',
        $date,
        '',
        19,
        false,
        true,
        false,
    )
;

// OMR DATA page
echo get_form_upload_file('omrfile[]', 'omrdata', $l['w_omr_data_page'], $l['h_omr_data_page'], '');

// OMR ANSWER SHEET pages
for ($i = 1; $i < $max_omr_sheets; ++$i) {
    echo
        get_form_upload_file(
            'omrfile[]',
            'omrsheet' . $i,
            $l['w_omr_answer_sheet'] . ' ' . $i,
            '',
            "document.getElementById('divomrsheet" . ($i + 1) . "').style.display='block';",
        )
    ;
}

echo
    get_form_upload_file(
        'omrfile[]',
        'omrsheet' . $max_omr_sheets,
        $l['w_omr_answer_sheet'] . ' ' . $max_omr_sheets,
        '',
        '',
    )
;

echo get_form_row_checkbox('overwrite', $l['w_overwrite'], $l['h_omr_overwrite'], '', 1, $overwrite, false, '');

// -----------------------------------------------------------------------------

echo '<div class="row">' . K_NEWLINE;
echo '<br />' . K_NEWLINE;
// show upload button
F_submit_button('upload', $l['w_upload'], $l['h_submit_file']);
echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

// hide unused file upload fields
echo '<script type="text/javascript">' . K_NEWLINE;
echo '//<![CDATA[' . K_NEWLINE;
echo
    'for (i=2; i<='
        . $max_omr_sheets
        . "; i++) {document.getElementById('divomrsheet'+i).style.display='none';}"
        . K_NEWLINE
;
echo '//]]>' . K_NEWLINE;
echo '</script>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_omr_answers_importer'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';
