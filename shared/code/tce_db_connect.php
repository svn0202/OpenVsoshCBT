<?php

//============================================================+
// File name   : tce_db_connect.php
// Begin       : 2001-09-02
// Last Update : 2023-11-30
//
// Description : open connection with active database
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Open a connection to a MySQL Server and select a database.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2001-09-02
 */

require_once '../../shared/code/tce_db_dal.php'; // Database Abstraction Layer for selected DATABASE type

if (
    !($db = F_db_connect(
        K_DATABASE_HOST,
        K_DATABASE_PORT,
        K_DATABASE_USER_NAME,
        K_DATABASE_USER_PASSWORD,
        K_DATABASE_NAME,
    ))
) {
    die('<h2>Unable to connect to the database!</h2>');
}

// Database timestamps are local wall-clock values. Apply the instance timezone
// before session handling or any test date parsing, including on installations
// whose preserved config predates the bootstrap JSON reader.
// Config may also be included from a function; settings helpers use global $db.
$GLOBALS['db'] = $db;
require_once __DIR__ . '/tce_functions_openvsosh_settings.php';
$openvsosh_timezone = openvsosh_get_setting('default_timezone') ?? (string) K_TIMEZONE;
date_default_timezone_set($openvsosh_timezone);
unset($openvsosh_timezone);
