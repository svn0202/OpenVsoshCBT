<?php

//============================================================+
// File name   : tce_edit_subject.php
// Begin       : 2004-04-26
// Last Update : 2023-11-30
//
// Description : Display form to edit exam subject_id (topics).
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display form to edit exam subject_id (topics).
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2004-04-27
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_SUBJECTS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     t_subjects_editor:string,w_name:string,a_meta_charset:string,m_authorization_denied:string,
 *     m_disabled_vs_deleted:string,m_delete_confirm:string,w_delete:string,h_delete:string,w_cancel:string,
 *     h_cancel:string,m_deleted:string,m_form_missing_fields:string,w_confirm:string,w_update:string,
 *     m_update_restrict:string,w_record_status:string,w_enabled:string,w_disabled:string,m_duplicate_name:string,
 *     m_updated:string,t_modules_editor:string,hp_edit_subject:string,w_module:string,w_subject:string,
 *     h_subject:string,h_subject_name:string,w_description:string,h_preview:string,w_preview:string,
 *     h_subject_description:string,h_enabled:string,h_update:string,w_add:string,h_add:string,w_clear:string,
 *     h_clear:string,t_questions_editor:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/** @var bool $formstatus */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var array{session_user_id:int|string} $session */
$session = $_SESSION;
$session_user_id = (int) $session['session_user_id'];

$thispage_title = $l['t_subjects_editor'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../code/tce_functions_tcecode_editor.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

// upload multimedia files
$uploadedfile = [];
for ($id = 0; $id < 2; ++$id) {
    $userfile = $_FILES['userfile' . $id] ?? null;
    if (isset($_POST['sendfile' . $id]) && is_array($userfile) && !empty($userfile['name'])) {
        require_once '../code/tce_functions_upload.php';
        $uploadedfile["'" . $id . "'"] = f_upload_file('userfile' . $id, K_PATH_CACHE);
    }
}

// comma separated list of required fields
$_REQUEST['ff_required'] = 'subject_name';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_name'], ENT_COMPAT, $l['a_meta_charset']);

// set default values
if (!isset($_REQUEST['subject_enabled']) || empty($_REQUEST['subject_enabled'])) {
    $subject_enabled = false;
} else {
    $subject_enabled = f_get_boolean($_REQUEST['subject_enabled']);
}

$subject_id = isset($_REQUEST['subject_id']) ? (int) $_REQUEST['subject_id'] : 0;

$subject_module_id = isset($_REQUEST['subject_module_id']) ? (int) $_REQUEST['subject_module_id'] : 0;

$requested_change_category = $_REQUEST['changecategory'] ?? null;
if ($requested_change_category !== null && f_tce_edit_subject_is_positive($requested_change_category)) {
    $changecategory = 1;
} elseif (isset($_REQUEST['selectcategory'])) {
    $changecategory = 1;
} else {
    $changecategory = 0;
}

$subject_name = utrim(f_tce_edit_subject_string($_REQUEST['subject_name'] ?? ''));

$subject_description = utrim(f_tce_edit_subject_string($_REQUEST['subject_description'] ?? ''));

if (f_tce_edit_subject_is_positive($subject_id)) {
    if ($changecategory === 0) {
        $sql = 'SELECT subject_module_id FROM ' . K_TABLE_SUBJECTS . ' WHERE subject_id=' . $subject_id . ' LIMIT 1';
        $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
        if ($r) {
            $m = f_tce_edit_subject_row(F_db_fetch_array($r));
            if ($m) {
                /** @var array{subject_module_id:int|string} $m */
                $subject_module_id = (int) $m['subject_module_id'];
                // check user's authorization for parent module
                if (
                    !f_is_authorized_user(K_TABLE_MODULES, 'module_id', $subject_module_id, 'module_user_id')
                    && !f_is_authorized_user(K_TABLE_SUBJECTS, 'subject_id', $subject_id, 'subject_user_id')
                ) {
                    F_print_error('ERROR', $l['m_authorization_denied'], true);
                }
            }
        } else {
            F_display_db_error();
        }
    }
} else {
    $subject_id = 0;
}

switch ($menu_mode) {
    case 'delete':
            // check if this record is used (test_log)
            if (!F_check_unique(K_TABLE_SUBJECT_SET, 'subjset_subject_id=' . $subject_id . '')) {
                //this record will be only disabled and not deleted because it's used
                $sql = 'UPDATE ' . K_TABLE_SUBJECTS . ' SET
				subject_enabled=\'0\'
				WHERE subject_id=' . $subject_id . '';
                $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error();
                }

                F_print_error('WARNING', $l['m_disabled_vs_deleted']);
            } else {
                // ask confirmation
                F_print_error('WARNING', $l['m_delete_confirm']);
                ?>
            <div class="confirmbox">
            <form action="<?php echo
                htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
            ; ?>" method="post" enctype="multipart/form-data" id="form_delete">
            <div>
            <input type="hidden" name="subject_id" id="subject_id" value="<?php echo $subject_id; ?>" />
            <input type="hidden" name="subject_module_id" id="subject_module_id" value="<?php echo
                $subject_module_id
            ; ?>" />
            <input type="hidden" name="subject_name" id="subject_name" value="<?php echo
                htmlspecialchars($subject_name, ENT_COMPAT, $l['a_meta_charset'])
            ; ?>" />
            <?php

            F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
            F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
            echo f_get_csrf_token_field() . K_NEWLINE;
            ?>
            </div>
            </form>
            </div>
        <?php
            }

            break;

    case 'forcedelete':
            if (($_POST['forcedelete'] ?? '') === $l['w_delete']) { //check if delete button has been pushed (redundant check)
                $sql = 'DELETE FROM ' . K_TABLE_SUBJECTS . ' WHERE subject_id=' . $subject_id . '';
                $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    $subject_id = false;
                    F_print_error('MESSAGE', $subject_name . ': ' . $l['m_deleted']);
                }
            }

            break;

    case 'update':
        // Update
            // check if the confirmation chekbox has been selected
            if (!isset($_REQUEST['confirmupdate']) || !f_legacy_int_equals($_REQUEST['confirmupdate'], 1)) {
                F_print_error(
                    'WARNING',
                    $l['m_form_missing_fields'] . ': ' . $l['w_confirm'] . ' &rarr; ' . $l['w_update'],
                );

                break;
            }

            if ($formstatus = F_check_form_fields()) {
                // check referential integrity (NOTE: mysql do not support "ON UPDATE" constraint)
                if (!F_check_unique(K_TABLE_SUBJECT_SET, 'subjset_subject_id=' . (int) $subject_id . '')) {
                    F_print_error('WARNING', $l['m_update_restrict']);
                    // enable or disable record
                    $sql =
                        'UPDATE '
                        . K_TABLE_SUBJECTS
                        . ' SET
					subject_enabled=\''
                        . (int) $subject_enabled
                        . '\'
					WHERE subject_id='
                        . $subject_id
                        . '';
                    $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
                    if (!$r) {
                        F_display_db_error(false);
                    } else {
                        $strmsg = $l['w_record_status'] . ': ';
                        if ($subject_enabled) {
                            $strmsg .= $l['w_enabled'];
                        } else {
                            $strmsg .= $l['w_disabled'];
                        }

                        F_print_error('MESSAGE', $strmsg);
                    }

                    $formstatus = false;

                    break;
                }

                // check if name is unique for selected module
                if (!F_check_unique(
                    K_TABLE_SUBJECTS,
                    "subject_name='"
                    . f_tce_edit_subject_string(F_escape_sql($db, $subject_name))
                    . "' AND subject_module_id="
                    . $subject_module_id
                    . '',
                    'subject_id',
                    $subject_id,
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_SUBJECTS
                    . ' SET
				subject_name=\''
                    . f_tce_edit_subject_string(F_escape_sql($db, $subject_name))
                    . '\',
				subject_description='
                    . f_tce_edit_subject_string(f_empty_to_null($subject_description))
                    . ',
				subject_enabled=\''
                    . (int) $subject_enabled
                    . '\',
				subject_module_id='
                    . $subject_module_id
                    . '
				WHERE subject_id='
                    . $subject_id
                    . '';
                $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    F_print_error('MESSAGE', $l['m_updated']);
                }
            }

            break;

    case 'add':
        // Add
            if ($formstatus = F_check_form_fields()) {
                // check if name is unique
                if (!F_check_unique(
                    K_TABLE_SUBJECTS,
                    "subject_name='"
                    . f_tce_edit_subject_string(F_escape_sql($db, $subject_name))
                    . "' AND subject_module_id="
                    . $subject_module_id
                    . '',
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                $sql =
                    'INSERT INTO '
                    . K_TABLE_SUBJECTS
                    . ' (
				subject_name,
				subject_description,
				subject_enabled,
				subject_user_id,
				subject_module_id
				) VALUES (
				\''
                    . f_tce_edit_subject_string(F_escape_sql($db, $subject_name))
                    . '\',
				'
                    . f_tce_edit_subject_string(f_empty_to_null($subject_description))
                    . ',
				\''
                    . (int) $subject_enabled
                    . '\',
				\''
                    . $session_user_id
                    . '\',
				'
                    . $subject_module_id
                    . '
				)';
                $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    /** @var int|numeric-string $subject_id */
                    $subject_id = F_db_insert_id($db, K_TABLE_SUBJECTS, 'subject_id');
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $subject_name = '';
            $subject_description = '';
            $subject_enabled = true;
            break;

    default:
            break;
} //end of switch

// select default module (if not specified)
if ($subject_module_id <= 0) {
    $sql = f_tce_edit_subject_string(F_select_modules_sql()) . ' LIMIT 1';
    $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tce_edit_subject_row(F_db_fetch_array($r));
        if ($m) {
            /** @var array{module_id:int|string} $m */
            /** @var int|numeric-string $default_module_id */
            $default_module_id = $m['module_id'];
            $subject_module_id = (int) $default_module_id;
        } else {
            $subject_module_id = 0;
        }
    } else {
        F_display_db_error();
    }
}

// --- Initialize variables
if ($formstatus && $menu_mode !== 'clear') {
    if ($changecategory > 0 || $subject_id === 0) {
        $subject_id = 0;
        $subject_name = '';
        $subject_description = '';
        $subject_enabled = true;
    } else {
        $sql = f_tce_edit_subject_string(
            F_select_subjects_sql(
                'subject_id=' . f_tce_edit_subject_string($subject_id) . ' AND subject_module_id=' . $subject_module_id,
            ),
        ) . ' LIMIT 1';
        $r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
        if ($r) {
            $m = f_tce_edit_subject_row(F_db_fetch_array($r));
            if ($m) {
                /**
                 * @var array{
                 *     subject_id:int|string,subject_module_id:int|string,subject_name:string,
                 *     subject_description:string,subject_enabled:mixed
                 * } $m
                 */
                /** @var int|numeric-string $stored_subject_id */
                $stored_subject_id = $m['subject_id'];
                /** @var int|numeric-string $stored_module_id */
                $stored_module_id = $m['subject_module_id'];
                $subject_id = (int) $stored_subject_id;
                $subject_name = $m['subject_name'];
                $subject_description = $m['subject_description'];
                $subject_enabled = f_get_boolean($m['subject_enabled']);
                $subject_module_id = (int) $stored_module_id;
            } else {
                $subject_name = '';
                $subject_description = '';
                $subject_enabled = true;
            }
        } else {
            F_display_db_error();
        }
    }
}

if ($subject_module_id <= 0) {
    echo '<div class="container">' . K_NEWLINE;
    echo
        '<p><a href="tce_edit_module.php" title="'
            . $l['t_modules_editor']
            . '" class="xmlbutton">&lt; '
            . $l['t_modules_editor']
            . '</a></p>'
            . K_NEWLINE
    ;
    echo '<div class="pagehelp">' . $l['hp_edit_subject'] . '</div>' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;
    require_once '../code/tce_page_footer.php';
    exit();
}

echo '<script src="' . K_PATH_SHARED_JSCRIPTS . 'inserttag.js" type="text/javascript"></script>' . K_NEWLINE;

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_subjecteditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="subject_module_id">' . $l['w_module'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<input type="hidden" name="changecategory" id="changecategory" value="" />' . K_NEWLINE;
echo
    '<select name="subject_module_id" id="subject_module_id" onchange="document.getElementById(\'form_subjecteditor\').changecategory.value=1; document.getElementById(\'form_subjecteditor\').submit();" title="'
        . $l['w_module']
        . '">'
        . K_NEWLINE
;
$sql = f_tce_edit_subject_string(F_select_modules_sql());
$r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
if ($r) {
    $countitem = 1;
    while ($m = f_tce_edit_subject_row(F_db_fetch_array($r))) {
        /** @var array{module_id:int|string,module_name:string,module_enabled:mixed} $m */
        echo '<option value="' . $m['module_id'] . '"';
        /** @var int|numeric-string $listed_module_id */
        $listed_module_id = $m['module_id'];
        if ((int) $listed_module_id === $subject_module_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (f_get_boolean($m['module_enabled'])) {
            echo '+';
        } else {
            echo '-';
        }

        echo
            ' '
                . htmlspecialchars($m['module_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '&nbsp;</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }

    if ($countitem === 1) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectcategory');

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="subject_id">' . $l['w_subject'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="subject_id" id="subject_id" onchange="document.getElementById(\'form_subjecteditor\').submit()" title="'
        . $l['h_subject']
        . '">'
        . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if ($subject_id === 0) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
$sql = f_tce_edit_subject_string(F_select_subjects_sql('subject_module_id=' . $subject_module_id));
$r = f_tce_edit_subject_query_result(F_db_query($sql, $db));
if ($r) {
    $countitem = 1;
    while ($m = f_tce_edit_subject_row(F_db_fetch_array($r))) {
        /** @var array{subject_id:int|string,subject_name:string,subject_enabled:mixed} $m */
        echo '<option value="' . $m['subject_id'] . '"';
        /** @var int|numeric-string $listed_subject_id */
        $listed_subject_id = $m['subject_id'];
        if ((int) $listed_subject_id === $subject_id) {
            echo ' selected="selected"';
        }

        echo '>' . $countitem . '. ';
        if (f_get_boolean($m['subject_enabled'])) {
            echo '+';
        } else {
            echo '-';
        }

        echo
            ' '
                . htmlspecialchars($m['subject_name'], ENT_NOQUOTES, $l['a_meta_charset'])
                . '&nbsp;</option>'
                . K_NEWLINE
        ;
        ++$countitem;
    }

    if ($countitem === 1) {
        echo '<option value="0">&nbsp;</option>' . K_NEWLINE;
    }
} else {
    echo '</select></span></div>' . K_NEWLINE;
    F_display_db_error();
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo
    get_form_row_text_input(
        'subject_name',
        $l['w_name'],
        $l['h_subject_name'],
        '',
        $subject_name,
        '',
        255,
        false,
        false,
        false,
        '',
    )
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="subject_description">' . $l['w_description'] . '</label>' . K_NEWLINE;
echo '<br />' . K_NEWLINE;
echo
    '<button type="button" title="'
        . $l['h_preview']
        . '" class="xmlbutton" onclick="previewWindow=window.open(\'tce_preview_tcecode.php?tcexamcode=\'+encodeURIComponent(document.getElementById(\'form_subjecteditor\').subject_description.value),\'previewWindow\',\'dependent,height=500,width=500,menubar=no,resizable=yes,scrollbars=yes,status=no,toolbar=no\'); return false;">'
        . $l['w_preview']
        . '</button>'
        . K_NEWLINE
;

echo '</span>' . K_NEWLINE;
echo '<span class="formw" style="border:1px solid #808080;">' . K_NEWLINE;
echo
    '<textarea cols="50" rows="5" name="subject_description" id="subject_description" title="'
        . $l['h_subject_description']
        . '"'
;

echo '>' . htmlspecialchars($subject_description, ENT_NOQUOTES, $l['a_meta_charset']) . '</textarea>' . K_NEWLINE;
echo '<br />' . K_NEWLINE;
echo tcecode_editor_tag_buttons('form_subjecteditor', 'subject_description');
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_row_checkbox('subject_enabled', $l['w_enabled'], $l['h_enabled'], '', 1, $subject_enabled, false, '');

echo '<div class="row">' . K_NEWLINE;

// show buttons by case
if (f_tce_edit_subject_is_positive($subject_id)) {
    echo '<span style="background-color:#999999;">';
    echo
        '<input type="checkbox" name="confirmupdate" id="confirmupdate" value="1" title="'
            . $l['w_confirm']
            . ' &rarr; '
            . $l['w_update']
            . '" aria-label="'
            . $l['w_confirm']
            . ' &rarr; '
            . $l['w_update']
            . '" />'
    ;
    F_submit_button('update', $l['w_update'], $l['h_update']);
    echo '</span>';
    F_submit_button('add', $l['w_add'], $l['h_add']);
    F_submit_button('delete', $l['w_delete'], $l['h_delete']);
} else {
    F_submit_button('add', $l['w_add'], $l['h_add']);
}

F_submit_button('clear', $l['w_clear'], $l['h_clear']);

echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="left">' . K_NEWLINE;
echo '&nbsp;' . K_NEWLINE;

if (f_tce_edit_subject_is_positive($subject_module_id)) {
    echo
        '<a href="tce_edit_module.php?module_id='
            . $subject_module_id
            . '" title="'
            . $l['t_modules_editor']
            . '" class="xmlbutton">&lt; '
            . $l['t_modules_editor']
            . '</a>'
    ;
}

echo '</span>' . K_NEWLINE;
echo '<span class="right">' . K_NEWLINE;

if (f_tce_edit_subject_is_positive($subject_id)) {
    echo
        '<a href="tce_edit_question.php?subject_module_id='
            . $subject_module_id
            . '&amp;question_subject_id='
            . f_tce_edit_subject_string($subject_id)
            . '" title="'
            . $l['t_questions_editor']
            . '" class="xmlbutton">'
            . $l['t_questions_editor']
            . ' &gt;</a>'
    ;
}

echo '&nbsp;' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="rowl" title="' . $l['h_preview'] . '">' . K_NEWLINE;
echo $l['w_preview'] . K_NEWLINE;
echo '<div class="preview">' . K_NEWLINE;

echo F_decode_tcecode($subject_description);

echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_subject'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_edit_subject_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve legacy positive-value comparisons. */
function f_tce_edit_subject_is_positive(mixed $value): bool
{
    if (is_array($value) || is_object($value)) {
        return true;
    }

    if (is_resource($value)) {
        return (int) $value > 0;
    }

    if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
        return $value > 0;
    }

    return false;
}

/** @return object|resource|bool */
function f_tce_edit_subject_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key,mixed>|null */
function f_tce_edit_subject_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}
