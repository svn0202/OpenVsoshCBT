<?php

//============================================================+
// File name   : tce_edit_module.php
// Begin       : 2008-11-28
// Last Update : 2023-11-30
//
// Description : Display form to edit modules.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display form to edit modules.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2008-11-28
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_MODULES;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     t_modules_editor:string,m_authorization_denied:string,w_name:string,a_meta_charset:string,
 *     m_disabled_vs_deleted:string,m_delete_confirm:string,w_delete:string,h_delete:string,
 *     w_cancel:string,h_cancel:string,m_deleted:string,m_form_missing_fields:string,w_confirm:string,
 *     w_update:string,m_update_restrict:string,w_record_status:string,w_enabled:string,w_disabled:string,
 *     m_duplicate_name:string,m_updated:string,w_module:string,h_module_name:string,w_owner:string,
 *     h_module_owner:string,w_select:string,w_groups:string,h_enabled:string,h_update:string,w_add:string,
 *     h_add:string,w_clear:string,h_clear:string,t_subjects_editor:string,hp_edit_module:string
 * } $l
 */
/** @var mixed $db */
/** @var string $menu_mode */
/** @var bool $formstatus */
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;
/** @var array{session_user_id:int|string,session_user_level:int|string} $session */
$session = $_SESSION;
$session_user_id = (int) $session['session_user_id'];
$session_user_level = (int) $session['session_user_level'];

$thispage_title = $l['t_modules_editor'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_auth_sql.php';

// set default values
if (!isset($_REQUEST['module_enabled']) || empty($_REQUEST['module_enabled'])) {
    $module_enabled = false;
} else {
    $module_enabled = f_get_boolean($_REQUEST['module_enabled']);
}

$module_name = utrim(f_tce_edit_module_string($_REQUEST['module_name'] ?? ''));

if (isset($_REQUEST['module_user_id'])) {
    $module_user_id = (int) $_REQUEST['module_user_id'];
} else {
    $module_user_id = $session_user_id;
}

$requested_module_id = $_REQUEST['module_id'] ?? null;
if ($requested_module_id !== null && f_tce_edit_module_is_positive($requested_module_id)) {
    $module_id = (int) $requested_module_id;
    // check user's authorization for module
    if (!f_is_authorized_user(K_TABLE_MODULES, 'module_id', $module_id, 'module_user_id')) {
        F_print_error('ERROR', $l['m_authorization_denied'], true);
    }
} else {
    $module_id = 0;
}

// comma separated list of required fields
$_REQUEST['ff_required'] = 'module_name';
$_REQUEST['ff_required_labels'] = htmlspecialchars($l['w_name'], ENT_COMPAT, $l['a_meta_charset']);

switch ($menu_mode) {
    case 'delete':
            // check if this record is used (test_log)
            if (!F_check_unique(
                K_TABLE_SUBJECTS . ',' . K_TABLE_SUBJECT_SET,
                'subjset_subject_id=subject_id AND subject_module_id=' . $module_id . '',
            )) {
                //this record will be only disabled and not deleted because it's used
                $sql = 'UPDATE ' . K_TABLE_MODULES . ' SET
				module_enabled=\'0\'
				WHERE module_id=' . $module_id . '';
                $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
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
            <input type="hidden" name="module_id" id="module_id" value="<?php echo $module_id; ?>" />
            <input type="hidden" name="module_name" id="module_name" value="<?php echo
                htmlspecialchars($module_name, ENT_COMPAT, $l['a_meta_charset'])
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
                $sql = 'DELETE FROM ' . K_TABLE_MODULES . ' WHERE module_id=' . $module_id . '';
                $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    $module_id = false;
                    F_print_error('MESSAGE', $module_name . ': ' . $l['m_deleted']);
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
                if (!F_check_unique(
                    K_TABLE_SUBJECTS . ',' . K_TABLE_SUBJECT_SET,
                    'subjset_subject_id=subject_id AND subject_module_id=' . $module_id . '',
                )) {
                    F_print_error('WARNING', $l['m_update_restrict']);

                    // enable or disable record
                    $sql =
                        'UPDATE '
                        . K_TABLE_MODULES
                        . ' SET
					module_enabled=\''
                        . (int) $module_enabled
                        . '\'
					WHERE module_id='
                        . $module_id
                        . '';
                    $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
                    if (!$r) {
                        F_display_db_error(false);
                    } else {
                        $strmsg = $l['w_record_status'] . ': ';
                        if ($module_enabled) {
                            $strmsg .= $l['w_enabled'];
                        } else {
                            $strmsg .= $l['w_disabled'];
                        }

                        F_print_error('MESSAGE', $strmsg);
                    }

                    $formstatus = false;

                    break;
                }

                // check if name is unique
                if (!F_check_unique(
                    K_TABLE_MODULES,
                    "module_name='" . f_tce_edit_module_string(F_escape_sql($db, $module_name)) . "'",
                    'module_id',
                    $module_id,
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                if ($session_user_level >= f_tce_edit_module_int(K_AUTH_ADMINISTRATOR)) {
                    $module_user_id = (int) $module_user_id;
                } else {
                    $module_user_id = $session_user_id;
                }

                $sql =
                    'UPDATE '
                    . K_TABLE_MODULES
                    . ' SET
				module_name=\''
                    . f_tce_edit_module_string(F_escape_sql($db, $module_name))
                    . '\',
				module_enabled=\''
                    . (int) $module_enabled
                    . '\',
				module_user_id=\''
                    . $module_user_id
                    . '\'
				WHERE module_id='
                    . $module_id
                    . '';
                $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
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
                    K_TABLE_MODULES,
                    "module_name='" . f_tce_edit_module_string(F_escape_sql($db, $module_name)) . "'",
                )) {
                    F_print_error('WARNING', $l['m_duplicate_name']);
                    $formstatus = false;

                    break;
                }

                if ($session_user_level >= f_tce_edit_module_int(K_AUTH_ADMINISTRATOR)) {
                    $module_user_id = (int) $module_user_id;
                } else {
                    $module_user_id = $session_user_id;
                }

                $sql =
                    'INSERT INTO '
                    . K_TABLE_MODULES
                    . ' (
				module_name,
				module_enabled,
				module_user_id
				) VALUES (
				\''
                    . f_tce_edit_module_string(F_escape_sql($db, $module_name))
                    . '\',
				\''
                    . (int) $module_enabled
                    . '\',
				\''
                    . $module_user_id
                    . '\'
				)';
                $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
                if (!$r) {
                    F_display_db_error(false);
                } else {
                    /** @var int|numeric-string $module_id */
                    $module_id = F_db_insert_id($db, K_TABLE_MODULES, 'module_id');
                }
            }

            break;

    case 'clear':
        // Clear form fields
            $module_name = '';
            $module_enabled = true;
            $module_user_id = $session_user_id;
            break;

    default:
            break;
} //end of switch

// --- Initialize variables
if ($formstatus && $menu_mode !== 'clear') {
    if ($module_id === 0) {
        $module_id = 0;
        $module_name = '';
        $module_enabled = true;
        $module_user_id = $session_user_id;
    } else {
        $sql = f_tce_edit_module_string(
            F_select_modules_sql('module_id=' . f_tce_edit_module_string($module_id)),
        ) . ' LIMIT 1';
        $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
        if ($r) {
            $m = f_tce_edit_module_row(F_db_fetch_array($r));
            if ($m) {
                /** @var array{module_id:int|string,module_name:string,module_enabled:mixed,module_user_id:int|string} $m */
                /** @var int|numeric-string $stored_module_id */
                $stored_module_id = $m['module_id'];
                $module_id = (int) $stored_module_id;
                $module_name = $m['module_name'];
                $module_enabled = f_get_boolean($m['module_enabled']);
                $module_user_id = (int) $m['module_user_id'];
            } else {
                $module_name = '';
                $module_enabled = true;
                $module_user_id = $session_user_id;
            }
        } else {
            F_display_db_error();
        }
    }
}

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_moduleeditor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="module_id">' . $l['w_module'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo
    '<select name="module_id" id="module_id" onchange="document.getElementById(\'form_moduleeditor\').submit()" title="'
        . $l['h_module_name']
        . '">'
        . K_NEWLINE
;
echo '<option value="0" style="background-color:#009900;color:white;"';
if ($module_id === 0) {
    echo ' selected="selected"';
}

echo '>+</option>' . K_NEWLINE;
$sql = f_tce_edit_module_string(F_select_modules_sql());
$r = f_tce_edit_module_query_result(F_db_query($sql, $db));
if ($r) {
    $countitem = 1;
    while ($m = f_tce_edit_module_row(F_db_fetch_array($r))) {
        /** @var array{module_id:int|string,module_name:string,module_enabled:mixed} $m */
        echo '<option value="' . $m['module_id'] . '"';
        /** @var int|numeric-string $listed_module_id */
        $listed_module_id = $m['module_id'];
        if ((int) $listed_module_id === $module_id) {
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

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo
    get_form_row_text_input(
        'module_name',
        $l['w_name'],
        $l['h_module_name'],
        '',
        $module_name,
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
echo '<label for="module_user_id">' . $l['w_owner'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
$userids = [];
if ($session_user_level >= f_tce_edit_module_int(K_AUTH_ADMINISTRATOR)) {
    echo
        '<select name="module_user_id" id="module_user_id" title="'
            . $l['h_module_owner']
            . '" onchange="">'
            . K_NEWLINE
    ;
    $sql =
        'SELECT user_id, user_lastname, user_firstname, user_name FROM '
        . K_TABLE_USERS
        . ' WHERE (user_level>5) ORDER BY user_lastname, user_firstname, user_name';
    $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
    if ($r) {
        while ($m = f_tce_edit_module_row(F_db_fetch_array($r))) {
            /** @var array{user_id:int|string,user_name:string,user_lastname:string,user_firstname:string} $m */
            $userids[] = $m['user_id'];
            echo '<option value="' . $m['user_id'] . '"';
            /** @var int|numeric-string $listed_user_id */
            $listed_user_id = $m['user_id'];
            if ((int) $listed_user_id === $module_user_id) {
                echo ' selected="selected"';
            }

            echo
                '>'
                    . htmlspecialchars(
                        '(' . $m['user_name'] . ') ' . $m['user_lastname'] . ' ' . $m['user_firstname'] . '',
                        ENT_NOQUOTES,
                        $l['a_meta_charset'],
                    )
                    . '</option>'
                    . K_NEWLINE
            ;
        }
    } else {
        echo '</select></span></div>' . K_NEWLINE;
        F_display_db_error();
    }

    echo '</select>' . K_NEWLINE;
} else {
    $userdata = '';
    $userids[] = $module_user_id;
    $sql =
        'SELECT user_id, user_lastname, user_firstname, user_name FROM '
        . K_TABLE_USERS
        . ' WHERE user_id='
        . $module_user_id
        . '';
    $r = f_tce_edit_module_query_result(F_db_query($sql, $db));
    if ($r) {
        $m = f_tce_edit_module_row(F_db_fetch_array($r));
        if ($m) {
            /** @var array{user_name:string,user_lastname:string,user_firstname:string} $m */
            echo
                '<span style="font-style:italic;color:#333333;">('
                    . unhtmlentities(strip_tags(
                        $m['user_name'] . ') ' . $m['user_lastname'] . ' ' . $m['user_firstname'],
                    ))
                    . '</span>'
                    . K_NEWLINE
            ;
        }
    } else {
        echo '</select></span></div>' . K_NEWLINE;
        F_display_db_error();
    }
}

// link for user selection popup
$jslink = 'tce_select_users_popup.php?cid=module_user_id';
if ($userids !== []) {
    $uids = implode('x', $userids);
    if (strlen(K_PATH_PUBLIC_CODE . $jslink . $uids) < 512) {
        // add this filter only if the URL is short
        $jslink .= '&amp;uids=' . $uids;
    }
}

$jsaction =
    "selectWindow=window.open('"
    . $jslink
    . "', 'selectWindow', 'dependent, height=600, width=800, menubar=no, resizable=yes, scrollbars=yes, status=no, toolbar=no');return false;";
echo '<button type="button" onclick="' . $jsaction . '" class="xmlbutton" title="' . $l['w_select'] . '">...</button>';

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label>' . $l['w_groups'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
$sqlg =
    'SELECT *
	FROM '
    . K_TABLE_GROUPS
    . ', '
    . K_TABLE_USERGROUP
    . '
	WHERE usrgrp_group_id=group_id
		AND usrgrp_user_id='
    . $module_user_id
    . '
	ORDER BY group_name';
$rg = f_tce_edit_module_query_result(F_db_query($sqlg, $db));
if ($rg) {
    echo '<span style="font-style:italic;color#333333;font-size:small;">';
    while ($mg = f_tce_edit_module_row(F_db_fetch_array($rg))) {
        /** @var array{group_name:string} $mg */
        echo ' · ' . unhtmlentities(strip_tags($mg['group_name'])) . '';
    }

    echo '</span>';
} else {
    F_display_db_error();
}

echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_row_checkbox('module_enabled', $l['w_enabled'], $l['h_enabled'], '', 1, $module_enabled, false, '');

echo '<div class="row">' . K_NEWLINE;

// show buttons by case
if (f_tce_edit_module_is_positive($module_id)) {
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
echo '<span class="right">' . K_NEWLINE;

if (f_tce_edit_module_is_positive($module_id)) {
    echo
        '<a href="tce_edit_subject.php?subject_module_id='
            . f_tce_edit_module_string($module_id)
            . '" title="'
            . $l['t_subjects_editor']
            . '" class="xmlbutton">'
            . $l['t_subjects_editor']
            . ' &gt;</a>'
    ;
}

echo '&nbsp;' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '&nbsp;' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_module'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_edit_module_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve legacy integer conversion without specializing configured constants. */
function f_tce_edit_module_int(mixed $value): int
{
    return (int) $value;
}

/**
 * Preserve legacy numeric comparison for request and stored identifiers.
 *
 * @param int|string|float|bool|array<array-key, mixed>|null $value
 */
function f_tce_edit_module_is_positive(int|string|float|bool|array|null $value): bool
{
    if (is_array($value)) {
        return true;
    }

    if ($value === null) {
        return false;
    }

    return $value > 0;
}

/**
 * Preserve the active DAL result type across mutually exclusive database implementations.
 *
 * @return object|resource|bool
 */
function f_tce_edit_module_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<array-key, mixed>|null */
function f_tce_edit_module_row(mixed $row): ?array
{
    return is_array($row) ? $row : null;
}
