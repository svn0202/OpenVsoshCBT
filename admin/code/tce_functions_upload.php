<?php

//============================================================+
// File name   : tce_functions_upload.php
// Begin       : 2001-11-19
// Last Update : 2023-11-30
//
// Description : Upload functions.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to upload files.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2001-11-19
 */

/**
 * Check if the uploaded file extension is allowed.
 * @author Nicola Asuni
 * @since 2001-11-19
 * @param string $filename the filename
 * @return bool whether the file type is allowed
 */
function f_is_allowed_upload(string $filename): bool
{
    if (!defined('K_ALLOWED_UPLOAD_EXTENSIONS')) {
        return false;
    }

    $normalize_string = static fn(mixed $value): ?string => is_string($value) ? $value : null;
    $normalize_array = static fn(mixed $value): ?array => is_array($value) ? $value : null;
    $serialized_extensions = $normalize_string(constant('K_ALLOWED_UPLOAD_EXTENSIONS'));
    if ($serialized_extensions === null) {
        return false;
    }
    $allowed_extensions = $normalize_array(unserialize($serialized_extensions, ['allowed_classes' => false]));
    if ($allowed_extensions === null) {
        return false;
    }
    $allowed_extensions = array_values(array_filter($allowed_extensions, is_string(...)));
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    return in_array(strtolower($extension), $allowed_extensions, true);
}

/**
 * Uploads image file to the server.
 * @author Nicola Asuni
 * @since 2010-06-12
 * @param string $fieldname form field name containing the source file path
 * @param string $uploaddir upload directory
 * @return mixed file name or false in case of error
 */
function f_upload_file(string $fieldname, string $uploaddir): mixed
{
    global $l;
    require_once '../config/tce_config.php';
    /** @var array{m_upload_yes: string, m_upload_not: string} $l */
    $upload = $_FILES[$fieldname] ?? null;
    $source_name = is_array($upload) && isset($upload['name']) && is_string($upload['name'])
        ? $upload['name'] : '';
    $temporary_name = is_array($upload) && isset($upload['tmp_name']) && is_string($upload['tmp_name'])
        ? $upload['tmp_name'] : '';
    // sanitize file name
    $filename = preg_replace('/[\s]/', '_', $source_name) ?? '';
    $filename = preg_replace('/[^a-zA-Z0-9_\.\-]/', '', $filename) ?? '';
    if (str_starts_with($filename, '.')) {
        // files starting with a '.' are rendered as HTML pages.
        return false;
    }

    $filepath = $uploaddir . $filename;
    if (f_is_allowed_upload($filename) && move_uploaded_file($temporary_name, $filepath)) {
        F_print_error('MESSAGE', htmlspecialchars($filename) . ': ' . $l['m_upload_yes']);
        return $filename;
    }

    F_print_error('ERROR', htmlspecialchars($filename) . ': ' . $l['m_upload_not'] . '');
    return false;
}

/**
 * returns the file size in bytes
 * @author Nicola Asuni
 * @since 2001-11-19
 * @param string $filetocheck file to check (local path or URL)
 * @return int|false file size in bytes, or false in case of error
 */
function f_read_file_size(string $filetocheck): int|false
{
    global $l;
    require_once '../config/tce_config.php';
    /** @var array{m_openfile_not: string} $l */
    $filesize = 0;
    $fp = fopen($filetocheck, 'rb');
    if ($fp !== false) {
        $s_array = fstat($fp);
        if ($s_array !== false && $s_array['size'] !== 0) {
            $filesize = $s_array['size'];
        } else {
            //read size from remote file (very slow function)
            while (!feof($fp)) {
                $content = fread($fp, 1);
                ++$filesize;
            }
        }

        fclose($fp);
        return $filesize;
    }

    F_print_error('ERROR', basename($filetocheck) . ': ' . $l['m_openfile_not']);
    return false;
}
