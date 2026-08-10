<?php

//============================================================+
// File name   : tce_select_mediafile.php
// Begin       : 2010-09-20
// Last Update : 2023-11-30
//
// Description : Select media file for questions or answer description
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Select media file for questions or answer description
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2010-09-22
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_FILEMANAGER;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once 'tce_functions_filemanager.php';

/**
 * @var array{
 *     t_select_media_file:string,m_directory_create_error:string,m_authorization_denied:string,
 *     m_delete_confirm:string,w_delete:string,h_delete:string,w_cancel:string,h_cancel:string,m_used_file:string,
 *     m_deleted:string,m_delete_file_error:string,m_form_missing_fields:string,m_file_already_exist:string,
 *     m_file_renamed:string,m_file_rename_error:string,m_directory_created:string,w_action:string,w_preview:string,
 *     w_name:string,w_rename:string,w_width:string,w_height:string,w_description:string,h_object_width:string,
 *     h_object_height:string,w_add:string,h_add_object:string,w_upload_file:string,h_upload_file:string,w_upload:string,
 *     a_meta_dir:string,w_mode:string,w_visual:string,w_table:string,w_position:string,w_new_directory:string,
 *     w_create_directory:string,hp_select_media_file:string
 * } $l
 */
/** @var string $menu_mode */
/** @var array{session_user_level:int|string,session_user_id:int|string} $session */
$session = $_SESSION;
$session_user_level = (int) $session['session_user_level'];
$session_user_id = (int) $session['session_user_id'];
/** @var array{SCRIPT_NAME:string} $server */
$server = $_SERVER;

$thispage_title = $l['t_select_media_file'];
require_once '../code/tce_page_header_popup.php';

// Non-administrators may access to their cache folder or the cache folders of the users in their groups
if ($session_user_level < f_tce_select_media_int(K_AUTH_ADMINISTRATOR)) {
    $root_dir = f_tce_select_media_string(K_PATH_CACHE) . 'uid/';
    $usr_dir = $root_dir . $session_user_id . '/';
    // create user directory if missing
    if (!F_file_exists($usr_dir)) {
        $oldumask = umask(0);
        // @mago-expect lint:no-error-control-operator -- replace the filesystem warning with a localized error
        if (!@mkdir($usr_dir, 0o744, true)) {
            F_print_error('ERROR', $l['m_directory_create_error']);
        }

        umask($oldumask);
    }
} else {
    $root_dir = f_tce_select_media_string(K_PATH_CACHE);
    $usr_dir = $root_dir;
}

$params = '';
if (isset($_REQUEST['frm'])) {
    $callingform = f_tce_select_media_string($_REQUEST['frm']);
    $callingform = preg_replace('/[^a-z0-9_]/', '', $callingform) ?? '';
    $params .= '&amp;frm=' . $callingform;
} else {
    $callingform = '';
}

if (isset($_REQUEST['fld'])) {
    $callingfield = f_tce_select_media_string($_REQUEST['fld']);
    $callingfield = preg_replace('/[^a-z0-9_]/', '', $callingfield) ?? '';
    $params .= '&amp;fld=' . $callingfield;
} else {
    $callingfield = '';
}

if (isset($_REQUEST['v'])) {
    $viewmode = (bool) $_REQUEST['v'];
} elseif (isset($_REQUEST['viewmodet'])) {
    $viewmode = true;
} elseif (isset($_REQUEST['viewmodev'])) {
    $viewmode = false;
} else {
    // default visual mode
    $viewmode = false;
}

// select current dir
$dir = $usr_dir;
if (isset($_REQUEST['d'])) {
    $dir = urldecode(f_tce_select_media_string($_REQUEST['d']));
} elseif (isset($_REQUEST['dir'])) {
    $dir = f_tce_select_media_string($_REQUEST['dir']);
}

// sanitize dir
$dir = f_tce_select_media_realpath(realpath($dir)) . '/';
// get the authorized dirs
$authdirs = f_tce_select_media_authorized_dirs(f_get_authorized_dirs());
// check if the user is authorized to use this directory
if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
    $dir = $root_dir;
}

// select file
$file = '';
if (isset($_REQUEST['f'])) {
    $file = urldecode(f_tce_select_media_string($_REQUEST['f']));
} elseif (isset($_REQUEST['file'])) {
    $file = f_tce_select_media_string($_REQUEST['file']);
}

// sanitize file
$file = f_tce_select_media_realpath(realpath($file));
// check if the user is authorized to use this file
if (!f_is_authorized_dir($file . '/', $root_dir, $authdirs)) {
    $file = '';
}

// upload multimedia file
$uploaded_userfile = $_FILES['userfile'] ?? null;
if (isset($_POST['sendfile']) && is_array($uploaded_userfile) && !empty($uploaded_userfile['name'])) {
    require_once '../code/tce_functions_upload.php';
    if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
        $dir = $usr_dir;
    }

    $file = f_tce_select_media_string(f_upload_file('userfile', $dir));
    if (!empty($file)) {
        $file = $dir . $file;
    }
}

if (isset($_POST['rename'])) {
    $menu_mode = 'rename';
} elseif (isset($_POST['newdir'])) {
    $menu_mode = 'newdir';
} elseif (isset($_POST['deldir'])) {
    $menu_mode = 'deldir';
}

// switch actions
switch ($menu_mode) {
    case 'delete':
        if ($session_user_level < f_tce_select_media_int(K_AUTH_DELETE_MEDIAFILE)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        // ask confirmation
        F_print_error('WARNING', $l['m_delete_confirm'] . ' [ ' . basename($file) . ' ]');
        echo '<div class="confirmbox">' . K_NEWLINE;
        echo
            '<form action="'
                . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
                . '" method="post" enctype="multipart/form-data" id="form_delete">'
                . K_NEWLINE
        ;
        echo '<div>' . K_NEWLINE;
        echo '<input type="hidden" name="dir" id="dir" value="' . $dir . '" />' . K_NEWLINE;
        echo '<input type="hidden" name="file" id="file" value="' . $file . '" />' . K_NEWLINE;
        F_submit_button('forcedelete', $l['w_delete'], $l['h_delete']);
        F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
        echo '</div>' . K_NEWLINE;
        echo f_get_csrf_token_field() . K_NEWLINE;
        echo '</form>' . K_NEWLINE;
        echo '</div>' . K_NEWLINE;
        break;

    case 'forcedelete':
        // Delete
        if ($session_user_level < f_tce_select_media_int(K_AUTH_DELETE_MEDIAFILE)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (f_form_option_is_selected($l['w_delete'], $_POST['forcedelete'] ?? '')) {
            // check if this record is used (test_log)
            if (f_is_used_media_file($file)) {
                F_print_error('WARNING', $l['m_used_file']);
            } elseif (f_delete_media_file($file)) {
                $file = '';
                F_print_error('MESSAGE', $l['m_deleted']);
            } else {
                F_print_error('ERROR', $l['m_delete_file_error']);
            }
        }

        break;

    case 'rename':
        if ($session_user_level < f_tce_select_media_int(K_AUTH_RENAME_MEDIAFILE)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        $newname = isset($_REQUEST['newname']) ? basename(f_tce_select_media_string($_REQUEST['newname'])) : '';
        // check if this record is used (test_log)
        if ($newname === '' || $newname === '.' || $newname === '..') {
            F_print_error('WARNING', $l['m_form_missing_fields']);
        } elseif (F_file_exists($dir . $newname)) {
            F_print_error('WARNING', $l['m_file_already_exist']);
        } elseif (f_is_used_media_file($file)) {
            F_print_error('WARNING', $l['m_used_file']);
        } elseif (isset($_REQUEST['newname'])) {
            if (f_rename_media_file($file, $dir . $newname)) {
                $file = $dir . $newname;
                F_print_error('MESSAGE', $l['m_file_renamed']);
            } else {
                F_print_error('ERROR', $l['m_file_rename_error']);
            }
        }

        break;

    case 'newdir':
        if ($session_user_level < f_tce_select_media_int(K_AUTH_ADMIN_DIRS)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        $newdirname = isset($_REQUEST['newdirname']) ? basename(f_tce_select_media_string($_REQUEST['newdirname'])) : '';
        // check if this record is used (test_log)
        if ($newdirname === '' || $newdirname === '.' || $newdirname === '..') {
            F_print_error('WARNING', $l['m_form_missing_fields']);
        } elseif (F_file_exists($dir . $newdirname)) {
            F_print_error('WARNING', $l['m_file_already_exist']);
        } elseif (isset($_REQUEST['newdirname'])) {
            if (f_create_media_dir($dir . $newdirname)) {
                $dir = $dir . $newdirname . '/';
                F_print_error('MESSAGE', $l['m_directory_created']);
            } else {
                F_print_error('ERROR', $l['m_directory_create_error']);
            }
        }

        break;

    case 'deldir':
        // Delete
        if ($session_user_level < f_tce_select_media_int(K_AUTH_ADMIN_DIRS)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (!f_is_authorized_dir($dir, $root_dir, $authdirs)) {
            F_print_error('WARNING', $l['m_authorization_denied']);
            break;
        }

        if (f_delete_media_dir($dir)) {
            $dir = $root_dir;
            F_print_error('MESSAGE', $l['m_deleted']);
        } else {
            F_print_error('ERROR', $l['m_delete_file_error']);
        }

        break;

    default:
        break;
} //end of switch

echo '<div class="container">' . K_NEWLINE;

echo '<div class="contentbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_filemanager">'
        . K_NEWLINE
;
echo '<div>' . K_NEWLINE;

echo '<input type="hidden" name="frm" id="frm" value="' . $callingform . '" />' . K_NEWLINE;
echo '<input type="hidden" name="fld" id="fld" value="' . $callingfield . '" />' . K_NEWLINE;

// current dir
echo '<input type="hidden" name="d" id="d" value="' . $dir . '" />' . K_NEWLINE;

echo '<fieldset>' . K_NEWLINE;
echo '<legend title="' . $l['w_action'] . '">' . $l['w_action'] . '</legend>' . K_NEWLINE;

if (!empty($file)) {
    // file mode
    // preview
    $filedata = f_tce_select_media_file_info(f_get_file_info($file));
    $w = 500;
    $h = 250;
    echo f_tce_select_media_string(
        F_objects_replacement($filedata['tcename'], $filedata['extension'], 0, 0, $l['w_preview'], $w, $h),
    );
    echo '<br />' . K_NEWLINE;
    // display basic info
    echo
        '<span style="font-size:80%;color:#333333;">'
            . $w
            . ' x '
            . $h
            . ' px ( '
            . f_tce_select_media_string(f_format_file_size($filedata['size']))
            . ' ) '
            . $filedata['lastmod']
            . '</span>'
    ;
    echo '<br />' . K_NEWLINE;
    // action buttons
    echo '<input type="hidden" name="file" id="file" value="' . $file . '" />' . K_NEWLINE;
    echo '<input type="hidden" name="tcefile" id="tcefile" value="' . $filedata['tcefile'] . '" />' . K_NEWLINE;
    echo
        '<input type="text" name="newname" id="newname" value="'
            . basename($file)
            . '" size="30" maxlength="255" title="'
            . $l['w_name']
            . '" />'
            . K_NEWLINE
    ;
    if ($session_user_level >= f_tce_select_media_int(K_AUTH_RENAME_MEDIAFILE)) {
        F_submit_button('rename', $l['w_rename'], $l['w_rename']);
    }

    if ($session_user_level >= f_tce_select_media_int(K_AUTH_DELETE_MEDIAFILE)) {
        F_submit_button('delete', $l['w_delete'], $l['w_delete']);
    }

    // description fields
    // --- insert image/object
    echo '<br />' . K_NEWLINE;

    echo '<script src="' . f_tce_select_media_string(K_PATH_SHARED_JSCRIPTS) . 'inserttag.js" type="text/javascript"></script>' . K_NEWLINE;

    // layout-only table (positions the object form fields): role="presentation" so
    // assistive technologies do not announce spurious table/row/column semantics
    echo '<table role="presentation">' . K_NEWLINE;
    echo '<tr>';
    echo '<td><label for="object_width">' . $l['w_width'] . '</label></td>';
    echo '<td><label for="object_height">' . $l['w_height'] . '</label></td>';
    echo '<td><label for="object_alt">' . $l['w_description'] . '</label></td>';
    echo '<td>&nbsp;</td>';
    echo '</tr>' . K_NEWLINE;
    echo '<tr>';
    echo
        '<td><input type="text" name="object_width" id="object_width" value="'
            . $w
            . '" size="3" maxlength="5" title="'
            . $l['h_object_width']
            . '"/></td>'
    ;
    echo
        '<td><input type="text" name="object_height" id="object_height" value="'
            . $h
            . '" size="3" maxlength="5" title="'
            . $l['h_object_height']
            . '"/></td>'
    ;
    echo
        '<td><input type="text" name="object_alt" id="object_alt" value="" size="30" maxlength="255" title="'
            . $l['w_description']
            . '"/></td>'
    ;
    $onclick =
        "FJ_insert_text(window.opener.document.getElementById('"
        . $callingform
        . "')."
        . $callingfield
        . ", '[object]'+document.getElementById('tcefile').value+'[/object:'+document.getElementById('object_width').value+':'+document.getElementById('object_height').value+':'+document.getElementById('object_alt').value+']');";
    echo
        '<td><input type="button" name="addobject" id="addobject" value="'
            . $l['w_add']
            . '" title="'
            . $l['h_add_object']
            . '" onclick="'
            . $onclick
            . 'self.close();" /></td>'
    ;
    echo '</tr>' . K_NEWLINE;
    echo '</table>' . K_NEWLINE;
} else {
    // upload a new file
    echo '<label for="userfile">' . $l['w_upload_file'] . '</label>' . K_NEWLINE;
    echo '<input type="hidden" name="MAX_FILE_SIZE" value="' . f_tce_select_media_string(K_MAX_UPLOAD_SIZE) . '" />' . K_NEWLINE;
    echo
        '<input type="file" name="userfile" id="userfile" size="20" title="' . $l['h_upload_file'] . '" />' . K_NEWLINE
    ;
    echo
        '<input type="submit" name="sendfile" id="sendfile" value="'
            . $l['w_upload']
            . '" title="'
            . $l['h_upload_file']
            . '" />'
            . K_NEWLINE
    ;
}

echo '</fieldset>' . K_NEWLINE;

// change view mode
echo '<div style="text-align:'
    . (f_form_option_is_selected('ltr', $l['a_meta_dir']) ? 'right' : 'left')
    . ';font-size:75%;">';
if ($viewmode) {
    // table mode
    echo '<label for="viewmodev">' . $l['w_mode'] . ': </label>';
    F_submit_button('viewmodev', $l['w_visual'], $l['w_mode']);
} else {
    // visual mode
    echo '<label for="viewmodet">' . $l['w_mode'] . ': </label>';
    F_submit_button('viewmodet', $l['w_table'], $l['w_mode']);
}

echo '</div>' . K_NEWLINE;

// directory link path
echo '<br />' . K_NEWLINE;
echo '<strong>' . $l['w_position'] . ': ' . f_tce_select_media_string(f_get_media_dir_path_link($dir, $viewmode)) . '</strong>';

if ($session_user_level >= f_tce_select_media_int(K_AUTH_ADMIN_DIRS)) {
    // directory mode
    echo
        ' <input type="text" name="newdirname" id="newdirname" value="" size="15" maxlength="255" title="'
            . $l['w_new_directory']
            . '" />'
            . K_NEWLINE
    ;
    F_submit_button('newdir', $l['w_create_directory'], $l['w_new_directory']);
    if (count(f_tce_select_media_directory_entries(scandir($dir))) <= 2) {
        F_submit_button('deldir', $l['w_delete'], $l['w_delete']);
    }
}

echo '<br />' . K_NEWLINE;

// list files
if ($viewmode) {
    // table mode
    echo f_tce_select_media_string(f_get_dir_table($dir, basename($file), $params, $root_dir, $authdirs));
} else {
    // visual mode
    echo f_tce_select_media_string(f_get_dir_visual_table($dir, basename($file), $params, $root_dir, $authdirs));
}

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_select_media_file'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer_popup.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_select_media_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

function f_tce_select_media_int(mixed $value): int
{
    return (int) $value;
}

/** Convert a failed realpath exactly as the subsequent legacy concatenation did. */
function f_tce_select_media_realpath(string|false $path): string
{
    return $path === false ? '' : $path;
}

/** @return list<string> */
function f_tce_select_media_authorized_dirs(mixed $directories): array
{
    /** @var list<string> $directories */
    return $directories;
}

/**
 * @return array{tcename:string,extension:string,size:int|float,lastmod:string,tcefile:string}
 */
function f_tce_select_media_file_info(mixed $file_info): array
{
    /** @var array{tcename:string,extension:string,size:int|float,lastmod:string,tcefile:string} $file_info */
    return $file_info;
}

/**
 * @param array<array-key,string>|false $entries
 * @return array<array-key,string>
 */
function f_tce_select_media_directory_entries(array|false $entries): array
{
    if ($entries === false) {
        throw new TypeError('Unable to read media directory.');
    }

    return $entries;
}
