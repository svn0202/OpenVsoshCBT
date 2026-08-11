<?php

//============================================================+
// File name   : tce_edit_backup.php
// Begin       : 2009-04-06
// Last Update : 2023-11-30
//
// Description : Backup and Restore TCExam Database.
//               ONLY FOR POSIX SYSTEMS
//               SOME POSIX COMMANDS ARE HARD-CODED
//               ONLY FOR MySQL and PostgreSQL
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Backup and Restore TCExam Database (works only on POSIX systems with MySQL or PostgreSQL).
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2010-02-12
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_BACKUP;
require_once '../../shared/code/tce_authorization.php';

/** @var array{
 *     t_backup_editor: string,
 *     m_restore_confirm: string,
 *     w_restore: string,
 *     h_restore: string,
 *     w_cancel: string,
 *     h_cancel: string,
 *     m_restore_completed: string,
 *     a_meta_charset: string,
 *     m_backup_completed: string,
 *     w_backup_file: string,
 *     w_backup: string,
 *     h_backup: string,
 *     w_download: string,
 *     h_download: string,
 *     hp_edit_backups: string
 * } $l
 */
$thispage_title = $l['t_backup_editor'];

require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_backup.php';

$menu_mode = '';
if (isset($_POST['backup'])) {
    $menu_mode = 'backup';
} elseif (isset($_POST['restore'])) {
    $menu_mode = 'restore';
} elseif (isset($_POST['forcerestore'])) {
    $menu_mode = 'forcerestore';
} elseif (isset($_POST['download'])) {
    $menu_mode = 'download';
}

function f_is_valid_backup_file(mixed $file): bool
{
    return is_string($file) && F_tmf_backup_file_is_valid($file);
}

// explicitly read submitted form input (selected backup filename)
$backup_file = $_REQUEST['backup_file'] ?? '';

// check backup filename
if (!is_string($backup_file) || $backup_file !== '' && !f_is_valid_backup_file($backup_file)) {
    F_print_error('ERROR', 'SECURITY ERROR', true);
}
/** @var string $backup_file */
$downloads_enabled = constant('K_DOWNLOAD_BACKUPS') === true;

switch ($menu_mode) { // process submitted data
    case 'restore':
        if (!empty($backup_file)) {
            F_print_error('WARNING', $l['m_restore_confirm'] . ': ' . $backup_file);
            echo '<div class="confirmbox">' . K_NEWLINE;
            echo
                '<form action="'
                    . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
                    . '" method="post" enctype="multipart/form-data" id="form_delete">'
                    . K_NEWLINE
            ;
            echo '<div>' . K_NEWLINE;
            echo
                '<input type="hidden" name="backup_file" id="backup_file" value="'
                    . stripslashes($backup_file)
                    . '" />'
                    . K_NEWLINE
            ;
            F_submit_button('forcerestore', $l['w_restore'], $l['h_restore']);
            F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
            echo '</div>' . K_NEWLINE;
            echo f_get_csrf_token_field() . K_NEWLINE;
            echo '</form>' . K_NEWLINE;
            echo '</div>' . K_NEWLINE;
        }

        break;

    case 'forcerestore':
        if (($_POST['forcerestore'] ?? '') === $l['w_restore'] && $backup_file !== '') {
            try {
                $config = F_tmf_backup_config_from_constants();
                // Always take a checked safety backup immediately before restore.
                F_tmf_backup_create($config, K_PATH_BACKUP);
                $restore_path = F_tmf_backup_resolve_file(K_PATH_BACKUP, $backup_file);
                F_tmf_backup_restore($config, $restore_path);
                F_print_error('MESSAGE', $l['m_restore_completed'] . ': ' . $backup_file);
            } catch (TmfBackupException $exception) {
                F_print_error('ERROR', htmlspecialchars($exception->getMessage(), ENT_QUOTES, $l['a_meta_charset']));
            }
        }

        break;

    case 'backup':
        // backup
        try {
            F_tmf_backup_create(F_tmf_backup_config_from_constants(), K_PATH_BACKUP);
            F_print_error('MESSAGE', $l['m_backup_completed']);
        } catch (TmfBackupException $exception) {
            F_print_error('ERROR', htmlspecialchars($exception->getMessage(), ENT_QUOTES, $l['a_meta_charset']));
        }
        break;

    case 'download':
        if ($downloads_enabled && $backup_file !== '') {
            $file_to_download = '';
            try {
                $file_to_download = F_tmf_backup_resolve_file(K_PATH_BACKUP, $backup_file);
            } catch (TmfBackupException $exception) {
                F_print_error(
                    'ERROR',
                    htmlspecialchars($exception->getMessage(), ENT_QUOTES, $l['a_meta_charset']),
                    true,
                );
            }
            // send headers
            header('Content-Description: File Transfer');
            header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
            header('Pragma: public');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            // force download dialog
            header('Content-Type: application/force-download');
            header('Content-Type: application/octet-stream', false);
            header('Content-Type: application/download', false);
            header('Content-Type: application/x-gzip', false);
            // use the Content-Disposition header to supply a recommended filename
            header('Content-Disposition: attachment; filename=' . $backup_file . ';');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . (string) filesize($file_to_download));
            echo file_get_contents($file_to_download);
            exit();
        }

        break;

    default:
        break;
} //end of switch

require_once '../code/tce_page_header.php';

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo
    '<form action="'
        . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
        . '" method="post" enctype="multipart/form-data" id="form_editor">'
        . K_NEWLINE
;

echo '<div class="row">' . K_NEWLINE;
echo '<span class="label">' . K_NEWLINE;
echo '<label for="backup_file">' . $l['w_backup_file'] . '</label>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '<span class="formw">' . K_NEWLINE;
echo '<select name="backup_file" id="backup_file">' . K_NEWLINE;

// read directory for backup files.
/** @var resource $handle */
$handle = opendir(K_PATH_BACKUP);
echo '<option value="">&nbsp;</option>' . K_NEWLINE;
// get backup files
/** @var list<string> $files_list */
$files_list = [];
while (false !== ($file = readdir($handle))) {
    if (f_is_valid_backup_file($file) && is_file(K_PATH_BACKUP . $file)) {
        $files_list[] = $file;
    }
}

closedir($handle);
// sort alphabetically
sort($files_list);
$files_list = array_reverse($files_list);
foreach ($files_list as $file) {
    echo '<option value="' . $file . '">' . $file . '</option>' . K_NEWLINE;
}

echo '</select>' . K_NEWLINE;
echo '</span>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo get_form_noscript_select('selectrecord');

echo '<div class="row"><hr /></div>' . K_NEWLINE;

echo '<div class="row">' . K_NEWLINE;

F_submit_button('backup', $l['w_backup'], $l['h_backup']);
F_submit_button('restore', $l['w_restore'], $l['h_restore']);
if ($downloads_enabled) {
    F_submit_button('download', $l['w_download'], $l['h_download']);
}

echo '</div>' . K_NEWLINE;
echo f_get_csrf_token_field() . K_NEWLINE;
echo '</form>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $l['hp_edit_backups'] . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';
