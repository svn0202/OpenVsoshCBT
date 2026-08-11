<?php

//============================================================+
// File name   : tce_functions_filemanager.php
// Begin       : 2010-09-20
// Last Update : 2023-11-30
//
// Description : Functions for TCExam file manager.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions for TCExam file manager.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2010-09-20
 */

/** @return list<string> */
function f_filemanager_allowed_extensions(): array
{
    /** @var array<array-key, mixed>|false $extensions */
    $extensions = unserialize((string) K_ALLOWED_UPLOAD_EXTENSIONS);
    if (!is_array($extensions)) {
        return [];
    }

    return array_values(array_map(static fn(mixed $extension): string => (string) $extension, $extensions));
}

/**
 * Create a directory without exposing an expected filesystem failure to the
 * application's error handler.
 */
function f_filemanager_mkdir_silently(string $directory, int $permissions, bool $recursive): bool
{
    set_error_handler(static fn(): bool => true);
    try {
        return mkdir($directory, $permissions, $recursive);
    } finally {
        restore_error_handler();
    }
}

/**
 * Delete the selected media file
 * @author Nicola Asuni
 * @param $filename (string) the file name
 * @return bool whether the file was deleted
 */
function f_delete_media_file(mixed $filename): bool
{
    require_once '../config/tce_config.php';
    $filename = (string) $filename;
    if ((int) ($_SESSION['session_user_level'] ?? 0) < (int) K_AUTH_DELETE_MEDIAFILE) {
        // insufficient user level
        return false;
    }

    $allowed_extensions = f_filemanager_allowed_extensions();
    $normalized_filename = str_replace('\\', '/', $filename);
    if (preg_match('#(^|/)\.\.(/|$)#', $normalized_filename) === 1) {
        return false;
    }

    $path_parts = pathinfo($filename);
    $source_dirname = $path_parts['dirname'] ?? '';
    $source_extension = $path_parts['extension'] ?? '';
    if (!str_contains($source_dirname . '/', K_PATH_CACHE)) {
        return false;
    }

    if (!in_array(strtolower($source_extension), $allowed_extensions)) {
        return false;
    }

    if (str_contains($filename . '/', K_PATH_LANG_CACHE)) {
        return false;
    }

    if (str_contains($filename . '/', K_PATH_BACKUP)) {
        return false;
    }

    return unlink($filename);
}

/**
 * Rename the selected media file
 * @author Nicola Asuni
 * @param $filename (string) old file name
 * @param $newname (string) new file name
 * @return bool whether the file was renamed
 */
function f_rename_media_file(mixed $filename, mixed $newname): bool
{
    require_once '../config/tce_config.php';
    $filename = (string) $filename;
    $newname = (string) $newname;
    if ((int) ($_SESSION['session_user_level'] ?? 0) < (int) K_AUTH_RENAME_MEDIAFILE) {
        // insufficient user level
        return false;
    }

    $allowed_extensions = f_filemanager_allowed_extensions();
    $normalized_filename = str_replace('\\', '/', $filename);
    $normalized_newname = str_replace('\\', '/', $newname);
    if (
        preg_match('#(^|/)\.\.(/|$)#', $normalized_filename) === 1
        || preg_match('#(^|/)\.\.(/|$)#', $normalized_newname) === 1
    ) {
        return false;
    }

    $path_parts = pathinfo($filename);
    $path_parts_new = pathinfo($newname);
    $source_dirname = $path_parts['dirname'] ?? '';
    $source_extension = $path_parts['extension'] ?? '';
    $target_dirname = $path_parts_new['dirname'] ?? '';
    $target_extension = $path_parts_new['extension'] ?? '';
    if (!str_contains($source_dirname . '/', K_PATH_CACHE)) {
        return false;
    }

    if (!in_array(strtolower($source_extension), $allowed_extensions)) {
        return false;
    }

    if (str_contains($filename . '/', K_PATH_LANG_CACHE)) {
        return false;
    }

    if (str_contains($filename . '/', K_PATH_BACKUP)) {
        return false;
    }

    if (!str_contains($target_dirname . '/', K_PATH_CACHE)) {
        return false;
    }

    if (!in_array(strtolower($target_extension), $allowed_extensions)) {
        return false;
    }

    if (str_contains($newname . '/', K_PATH_LANG_CACHE)) {
        return false;
    }

    if (str_contains($newname . '/', K_PATH_BACKUP)) {
        return false;
    }

    return rename($filename, $newname);
}

/**
 * Create a new media directory inside the cache
 * @author Nicola Asuni
 * @param $dirname (string) the directory name
 * @return bool whether the directory was created
 */
function f_create_media_dir(mixed $dirname): bool
{
    require_once '../config/tce_config.php';
    $dirname = (string) $dirname;
    if ((int) ($_SESSION['session_user_level'] ?? 0) < (int) K_AUTH_ADMIN_DIRS) {
        // insufficient user level
        return false;
    }

    $normalized_dirname = str_replace('\\', '/', $dirname);
    if (preg_match('#(^|/)\.\.(/|$)#', $normalized_dirname) === 1) {
        return false;
    }

    if (str_contains($dirname . '/', K_PATH_CACHE)) {
        $oldumask = umask(0);
        try {
            $ret = f_filemanager_mkdir_silently($dirname, 0o744, false);
        } finally {
            umask($oldumask);
        }
        return $ret;
    }

    return false;
}

/**
 * Delete the specified media directory
 * @author Nicola Asuni
 * @param $dirname (string) the directory name
 * @return bool whether the directory was deleted
 */
function f_delete_media_dir(mixed $dirname): bool
{
    require_once '../config/tce_config.php';
    $dirname = (string) $dirname;
    if ((int) ($_SESSION['session_user_level'] ?? 0) < (int) K_AUTH_ADMIN_DIRS) {
        // insufficient user level
        return false;
    }

    if (!str_contains($dirname . '/', K_PATH_CACHE)) {
        return false;
    }

    if (!is_dir($dirname)) {
        return false;
    }

    $entries = scandir($dirname);
    if ($entries === false) {
        return false;
    }

    if (count($entries) > 2) {
        return false;
    }

    // @mago-expect lint:no-error-control-operator -- deletion races are reported through the boolean return value
    return @rmdir($dirname);
}

/**
 * Get file information
 * @author Nicola Asuni
 * @param $file (string) the file name
 * @return array{
 *     dirname?: string,
 *     basename: string,
 *     extension: string,
 *     filename: string,
 *     dir: bool,
 *     lastmod: string,
 *     owner: int|false,
 *     perms: int|false,
 *     aperms: string,
 *     size: int|false,
 *     link: bool,
 *     linkto?: string|false,
 *     tcefile: string,
 *     tcename: string
 * }
 */
function f_get_file_info(mixed $file): array
{
    require_once '../config/tce_config.php';
    $file = (string) $file;
    $info = pathinfo($file);
    $info['dir'] = is_dir($file);
    set_error_handler(static fn(): bool => true);
    try {
        $stat = stat($file);
    } finally {
        restore_error_handler();
    }

    $info['lastmod'] = date('Y-m-d H:i:s', is_array($stat) ? (int) $stat['mtime'] : 0);
    $info['owner'] = is_array($stat) ? (int) $stat['uid'] : false;
    $info['perms'] = is_array($stat) ? (int) $stat['mode'] : false;
    $info['aperms'] = $info['dir'] ? 'd' : '-';

    $info['aperms'] .= ($info['perms'] & 0o0400) !== 0 ? 'r' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0200) !== 0 ? 'w' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0100) !== 0 ? 'x' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0040) !== 0 ? 'r' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0020) !== 0 ? 'w' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0010) !== 0 ? 'x' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0004) !== 0 ? 'r' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0002) !== 0 ? 'w' : '-';
    $info['aperms'] .= ($info['perms'] & 0o0001) !== 0 ? 'x' : '-';
    $info['size'] = is_array($stat) ? (int) $stat['size'] : false;
    $info['link'] = is_link($file);
    if ($info['link']) {
        $info['linkto'] = readlink($file);
    }

    if (!isset($info['extension'])) {
        $info['extension'] = '';
    }

    $info['tcefile'] = substr($file, strlen(K_PATH_CACHE));
    $info['tcename'] = substr($info['tcefile'], 0, -(strlen($info['extension']) + 1));
    return $info;
}

/**
 * Return a formatted file size
 * @author Nicola Asuni
 * @param $size (int) size in bytes
 * @return string formatted size
 */
function f_format_file_size(mixed $size): string
{
    $out = ''; // string to be returned
    $mult = ['B ', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']; // multipliers
    $is_zero_size = $size === 0
        || $size === 0.0
        || $size === false
        || $size === null
        || (is_string($size) && is_numeric($size) && (float) $size === 0.0);
    if ($is_zero_size) {
        $out = '0';
    } else {
        /** @var int|float|numeric-string $size */
        $numeric_size = (float) $size;
        $i = min(8, max(0, (int) floor(log($numeric_size, 1024))));
        $out .= round($numeric_size / (1024 ** $i), $i > 1 ? 2 : 0);
        $unit = $mult[$i] ?? 'YB';
        $out .= ' ' . $unit;
    }

    return $out;
}

/**
 * Get an html string containing active path of the specified directory with links to subdirectories.
 * @author Nicola Asuni
 * @param $dirpath (string) the directory path
 * @param $viewmode (boolean) true=table, false=visual
 * @return string HTML directory path
 */
function f_get_media_dir_path_link(mixed $dirpath, mixed $viewmode = true): string
{
    global $l, $db;
    /** @var array{w_change_dir: string} $l */
    require_once '../config/tce_config.php';
    $dirpath = (string) $dirpath;
    $mode = (int) $viewmode;
    $out = ''; //string to be returned
    // write root link
    $out .=
        '<a href="'
        . $_SERVER['SCRIPT_NAME']
        . '?d='
        . urlencode(K_PATH_CACHE)
        . '&amp;v='
        . $mode
        . '" title="CACHE ROOT">[CACHE]</a> /';
    // conform windows-style directories
    $dirpath = str_replace("\\", '/', $dirpath); // Windows compatibility
    // remove cache root from path
    $dirpath = substr($dirpath, strlen(K_PATH_CACHE));
    $dirs = preg_split('/[\/]+/', $dirpath, -1, PREG_SPLIT_NO_EMPTY);
    if ($dirs !== false) {
        $current_dir = K_PATH_CACHE;
        foreach ($dirs as $dir) {
            $current_dir .= $dir . '/';
            $out .=
                ' <a href="'
                . $_SERVER['SCRIPT_NAME']
                . '?d='
                . urlencode($current_dir)
                . '&amp;v='
                . $mode
                . '" title="'
                . $l['w_change_dir']
                . '">'
                . $dir
                . '</a> /';
        }
    }

    return $out;
}

/**
 * Get an associative array of directories and folder inside the specified dir.
 * @author Nicola Asuni
 * @param $dir (string) the starting directory path
 * @param $rootdir (string) the user root dir.
 * @param $authdirs (string) regular expression containing the authorized dirs.
 * @return array{dirs: array<int, string>, files: array<int, string>} sorted directories and files
 */
function f_get_dir_files(mixed $dir, mixed $rootdir = K_PATH_CACHE, mixed $authdirs = ''): array
{
    $dir = (string) $dir;
    $rootdir = (string) $rootdir;
    $authdirs = (string) $authdirs;
    $data = ['dirs' => [], 'files' => []];
    // open dir
    set_error_handler(static fn(): bool => true);
    try {
        $dirhdl = opendir($dir);
    } finally {
        restore_error_handler();
    }
    if ($dirhdl === false) {
        return $data;
    }

    while ($file = readdir($dirhdl)) {
        if ($file !== '.' && $file !== '..') {
            $filename = $dir . $file;
            if (f_is_authorized_dir($filename . '/', $rootdir, $authdirs)) {
                if (is_dir($filename)) {
                    if (
                        !str_contains($filename . '/', K_PATH_LANG_CACHE)
                        && !str_contains($filename . '/', K_PATH_BACKUP)
                    ) {
                        $data['dirs'][] = $filename;
                    }
                } else {
                    $data['files'][] = $filename;
                }
            }
        }
    }

    // sort files alphabetically
    natcasesort($data['dirs']);
    natcasesort($data['files']);
    return $data;
}

/**
 * Return true if the file is used on question or answer descriptions
 * @author Nicola Asuni
 * @param $file (string) the file to search
 * @return bool true if the file is used, false otherwise
 */
function f_is_used_media_file(mixed $file): bool
{
    global $l, $db;
    require_once '../config/tce_config.php';
    $file = (string) $file;
    // remove cache root from file path
    $file = F_escape_sql($db, substr($file, strlen(K_PATH_CACHE)));
    // search on questions
    $sql =
        'SELECT question_id FROM '
        . K_TABLE_QUESTIONS
        . " WHERE question_description LIKE '%"
        . $file
        . "[/object%' OR question_explanation LIKE '%"
        . $file
        . "[/object%' LIMIT 1";
    /** @var mixed $r */
    $r = F_db_query($sql, $db);
    if ($r) {
        /** @var mixed $m */
        $m = F_db_fetch_array($r);
        if (is_array($m)) {
            return true;
        }
    } else {
        F_display_db_error();
    }

    // search on answers
    $sql =
        'SELECT answer_id FROM '
        . K_TABLE_ANSWERS
        . " WHERE answer_description LIKE '%"
        . $file
        . "[/object%' OR answer_explanation LIKE '%"
        . $file
        . "[/object%' LIMIT 1";
    /** @var mixed $r */
    $r = F_db_query($sql, $db);
    if ($r) {
        /** @var mixed $m */
        $m = F_db_fetch_array($r);
        if (is_array($m)) {
            return true;
        }
    } else {
        F_display_db_error();
    }

    return false;
}

/**
 * Get an html table containing files and subdirs
 * @author Nicola Asuni
 * @param $dir (string) the starting directory path
 * @param $selected (string) the selected file
 * @param $params (string) additional parameters to add on links
 * @param $rootdir (string) the user root dir.
 * @param $authdirs (string) regular expression containing the authorized dirs.
 * @return string HTML table
 */
function f_get_dir_table(
    mixed $dir,
    mixed $selected = '',
    mixed $params = '',
    mixed $rootdir = K_PATH_CACHE,
    mixed $authdirs = '',
): string
{
    global $l;
    /** @var array{
     *   w_directory: string, w_name: string, w_size: string, w_datetime_format: string,
     *   w_date: string, w_permissions: string, w_user: string, w_change_dir: string,
     *   w_selection: string, w_select: string
     * } $l
     */
    require_once '../config/tce_config.php';
    $dir = (string) $dir;
    $selected = (string) $selected;
    $params = (string) $params;
    $rootdir = (string) $rootdir;
    $authdirs = (string) $authdirs;
    $allowed_extensions = f_filemanager_allowed_extensions();
    $out = ''; // html string to be returned
    $out .= '<table class="filemanager">' . K_NEWLINE;
    $out .= '<caption class="sr-only">' . $l['w_directory'] . '</caption>';
    // header
    $out .= '<thead>';
    $out .= '<tr>';
    $out .= '<th scope="col">' . $l['w_name'] . '</th>';
    $out .= '<th scope="col">' . $l['w_size'] . '</th>';
    $out .= '<th scope="col" title="' . $l['w_datetime_format'] . '">' . $l['w_date'] . '</th>';
    $out .= '<th scope="col">' . $l['w_permissions'] . '</th>';
    $out .= '</tr>' . K_NEWLINE;
    $out .= '</thead>';
    $data = f_get_dir_files($dir, $rootdir, $authdirs);
    $usrdir = $rootdir . (int) ($_SESSION['session_user_id'] ?? 0);
    // dirs
    foreach ($data['dirs'] as $file) {
        $info = f_get_file_info($file);
        $current_dir = urlencode($dir . $info['basename'] . '/');
        $usrdir_cue = '';
        if ($file === $usrdir) {
            $out .= '<tr style="background-color:#ddffdd;font-family:monospace;color:#660000;">';
            $usrdir_cue = '<span class="sr-only">(' . $l['w_user'] . ' ' . $l['w_directory'] . ') </span>';
        } else {
            $out .= '<tr style="background-color:#dddddd;font-family:monospace;color:#660000;">';
        }

        $out .=
            '<td>'
            . $usrdir_cue
            . '<strong><a href="'
            . $_SERVER['SCRIPT_NAME']
            . '?d='
            . $current_dir
            . '&amp;v=1'
            . $params
            . '" title="'
            . $l['w_change_dir']
            . '" style="text-decoration:underline;">'
            . $info['basename']
            . '</a></strong></td>';
        $out .= '<td style="text-align:right;">' . f_format_file_size($info['size']) . '</td>';
        $out .= '<td>' . $info['lastmod'] . '</td>';
        $out .= '<td>' . $info['aperms'] . '</td>';
        $out .= '</tr>' . K_NEWLINE;
    }

    // files
    $current_dir = urlencode($dir);
    foreach ($data['files'] as $file) {
        $info = f_get_file_info($file);
        if (
            in_array(strtolower($info['extension']), $allowed_extensions)
            && !str_starts_with($info['basename'], 'latex_')
        ) {
            $current_file = urlencode($dir . $info['basename']);
            $selected_cue = '';
            if ($info['basename'] === $selected) {
                $out .= '<tr style="background-color:#ffffcc;font-family:monospace;">';
                $selected_cue = '<span class="sr-only">(' . $l['w_selection'] . ') </span>';
            } else {
                $out .= '<tr style="font-family:monospace;">';
            }

            $out .=
                '<td>'
                . $selected_cue
                . '<a href="'
                . $_SERVER['SCRIPT_NAME']
                . '?d='
                . $current_dir
                . '&amp;f='
                . urlencode($current_file)
                . '&amp;v=1'
                . $params
                . '" title="'
                . $l['w_select']
                . '">'
                . $info['basename']
                . '</a></td>';
            $out .= '<td style="text-align:right;">' . f_format_file_size($info['size']) . '</td>';
            //$out .= '<td style="text-align:right;">'.$info['size'].'</td>';
            $out .= '<td>' . $info['lastmod'] . '</td>';
            $out .= '<td>' . $info['aperms'] . '</td>';
            $out .= '</tr>' . K_NEWLINE;
        }
    }

    return $out . ('</table>' . K_NEWLINE);
}

/**
 * Get an html visual list of files and subdirs
 * @author Nicola Asuni
 * @param $dir (string) the starting directory path
 * @param $selected (string) the selected file
 * @param $params (string) additional parameters to add on links
 * @param $rootdir (string) the user root dir.
 * @param $authdirs (string) regular expression containing the authorized dirs.
 * @return string HTML visual file list
 */
function f_get_dir_visual_table(
    mixed $dir,
    mixed $selected = '',
    mixed $params = '',
    mixed $rootdir = K_PATH_CACHE,
    mixed $authdirs = '',
): string
{
    global $l;
    /** @var array{w_change_dir: string, w_selection: string, w_preview: string, w_select: string} $l */
    require_once '../config/tce_config.php';
    $dir = (string) $dir;
    $selected = (string) $selected;
    $params = (string) $params;
    $rootdir = (string) $rootdir;
    $authdirs = (string) $authdirs;
    $imgformats = ['gif', 'jpg', 'jpeg', 'png', 'svg'];
    $allowed_extensions = f_filemanager_allowed_extensions();
    $out = ''; // html string to be returned
    $data = f_get_dir_files($dir, $rootdir, $authdirs);
    // dirs
    foreach ($data['dirs'] as $file) {
        $info = f_get_file_info($file);
        $current_dir = urlencode($dir . $info['basename'] . '/');
        $out .= '<table role="presentation" style="float:left;border:none;margin:1px;padding:0;width:158px;background-color:#007fff;">';
        $out .= '<tr style="height:16px;font-family:monospace;font-size:12px;font-weight:bold;color:white;"><td>';
        $filename = $info['basename'];
        if (strlen($filename) > 20) {
            $filename = substr($filename, 0, 20) . '...';
        }

        $out .= $filename;
        $out .= '</td></tr>';
        $out .= '<tr style="height:160px;"><td style="text-align:center;vertical-align:middle;background-color:white;">';
        $out .=
            '<a href="'
            . $_SERVER['SCRIPT_NAME']
            . '?d='
            . $current_dir
            . '&amp;v=0'
            . $params
            . '" title="'
            . $l['w_change_dir']
            . ' : '
            . $info['basename']
            . '" style="text-decoration:underline;"><img src="'
            . K_PATH_IMAGES
            . 'dir.png" width="50" height="50" alt="'
            . $l['w_change_dir']
            . ' : '
            . $info['basename']
            . '" style="border:none;" /></a>';
        $out .= '</td></tr>';
        $out .= '</table>';
    }

    // files
    $current_dir = urlencode($dir);
    foreach ($data['files'] as $file) {
        $info = f_get_file_info($file);
        if (
            in_array(strtolower($info['extension']), $allowed_extensions)
            && !str_starts_with($info['basename'], 'latex_')
        ) {
            $current_file = urlencode($dir . $info['basename']);
            $is_selected = $info['basename'] === $selected;
            $bgcolor = $is_selected ? '#009900' : '#333333';
            $selected_cue = $is_selected ? '<span class="sr-only">(' . $l['w_selection'] . ') </span>' : '';

            if (in_array(strtolower($info['extension']), $imgformats)) {
                $w = 150;
                $h = 150;
                $imgicon = F_objects_replacement(
                    $info['tcename'],
                    $info['extension'],
                    0,
                    0,
                    $l['w_preview'],
                    $w,
                    $h,
                );
            } else {
                $imgicon =
                    '<img src="'
                    . K_PATH_IMAGES
                    . 'file.png" width="39" height="50" alt="'
                    . $l['w_select']
                    . ' : '
                    . $info['basename']
                    . ' ('
                    . f_format_file_size($info['size'])
                    . ')'
                    . '" style="border:none;" />';
            }

            $out .=
                '<table role="presentation" style="float:left;border:none;margin:1px;padding:0;width:158px;background-color:'
                . $bgcolor
                . ';">';
            $out .= '<tr style="height:16px;font-family:monospace;font-size:12px;color:white;"><td>';
            $out .= $selected_cue;
            $filename = $info['basename'];
            if (strlen($filename) > 20) {
                $filename =
                    substr(substr($filename, 0, -(strlen($info['extension']) + 1)), 0, 15)
                    . '&rarr;.'
                    . $info['extension'];
            }

            $out .= $filename;
            $out .= '</td></tr>';
            $out .= '<tr style="height:160px;"><td style="text-align:center;vertical-align:middle;background-color:white;">';
            $out .=
                '<a href="'
                . $_SERVER['SCRIPT_NAME']
                . '?d='
                . $current_dir
                . '&amp;f='
                . urlencode($current_file)
                . '&amp;v=0'
                . $params
                . '" title="'
                . $l['w_select']
                . ' : '
                . $info['basename']
                . ' ('
                . f_format_file_size($info['size'])
                . ')'
                . '">'
                . $imgicon
                . '</a>';
            $out .= '</td></tr>';
            $out .= '</table>';
        }
    }

    return $out . '<br style="clear:both;" />';
}

/**
 * Returns a regular expression to match authorised directories.
 * @return string regular expression matching authorised directories.
 */
function f_get_authorized_dirs(): string
{
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_authorization.php';
    if ((int) ($_SESSION['session_user_level'] ?? 0) >= K_AUTH_ADMINISTRATOR) {
        return '[^/]*';
    }

    $reg = f_get_authorized_users((int) ($_SESSION['session_user_id'] ?? 0));
    return str_replace(',', '|', $reg);
}

/**
 * Returns true if the user is authorized to use the specified directory, false otherwise.
 * @param $dir (string) the directory to check.
 * @param $rootdir (string) the user root dir.
 * @param $authdirs (string) regular expression containing the authorized dirs.
 * @return bool whether the user is authorized to use the specified directory.
 */
function f_is_authorized_dir(mixed $dir, mixed $rootdir, mixed $authdirs = ''): bool
{
    require_once '../config/tce_config.php';
    $dir = (string) $dir;
    $rootdir = (string) $rootdir;
    $authdirs = (string) $authdirs;
    if (empty($authdirs)) {
        $authdirs = f_get_authorized_dirs();
    }

    return preg_match('#^' . $rootdir . '(' . $authdirs . ')/#', $dir) === 1;
}
