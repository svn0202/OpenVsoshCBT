<?php

//============================================================+
// File name   : tce_db_dal_mysqli.php
// Begin       : 2003-10-12
// Last Update : 2023-11-30
//
// Description : MySQL driver for TCExam Database Abstraction
//               Layer (DAL).
//               This abstraction use the same SQL syntax
//               of MySQL.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * MySQL driver for TCExam Database Abstraction Layer (DAL).
 * This abstraction layer uses the same SQL syntax of MySQL.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2003-10-12
 */

// PHP 8.1+ enables mysqli exceptions (MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) by default.
// This DAL and all its callers are written against the historical "return false / empty on
// failure" contract (connection and query errors are surfaced via F_db_error(), not thrown), so
// disable exception reporting here to preserve that behaviour. Without this, any database error at
// runtime would raise an uncaught mysqli_sql_exception and crash the request.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

/**
 * Open a connection to a MySQL Server and select a database.
 * @param $host (string) database server host name.
 * @param $port (string) database connection port
 * @param $username (string) Name of the user that owns the server process.
 * @param $password (string) Password of the user that owns the server process.
 * @param $database (string) Database name.
 * @return mysqli|false Link identifier on success, or false on failure.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_connect(
    mixed $host = 'localhost',
    mixed $port = '3306',
    mixed $username = 'root',
    #[\SensitiveParameter]
    mixed $password = '',
    mixed $database = '',
): mixed {
    /** @var string $host */
    /** @var int $port */
    /** @var string $username */
    /** @var string $password */
    /** @var string $database */
    set_error_handler(static fn(): bool => true);
    try {
        $db = mysqli_connect($host, $username, $password, $database, $port);
    } finally {
        restore_error_handler();
    }
    if (!$db) {
        return false;
    }

    // set the correct charset encoding
    mysqli_query($db, "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
    mysqli_query($db, "SET CHARACTER SET 'utf8'");
    return $db;
}

/**
 * Closes the non-persistent connection to a database associated with the given connection resource.
 * @param $link_identifier (resource) database link identifier.
 * @return bool TRUE on success or FALSE on failure
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_close(mixed $link_identifier): mixed
{
    /** @var mysqli $link_identifier */
    return mysqli_close($link_identifier);
}

/**
 * Returns the text of the error message from previous database operation
 * @return string error message.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_error(mixed $link_identifier = null): mixed
{
    if (empty($link_identifier)) {
        return '';
    }

    /** @var mysqli $link_identifier */
    return '[' . mysqli_errno($link_identifier) . ']: ' . mysqli_error($link_identifier) . '';
}

/**
 * Sends a query to the currently active database on the server that's associated with the specified link identifier.<br>
 * @param $query (string) The query tosend. The query string should not end with a semicolon.
 * @param $link_identifier (resource) database link identifier.
 * @return mysqli_result|bool Result object or true on success, false on error.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_query(mixed $query, mixed $link_identifier): mixed
{
    /** @var string $query */
    /** @var mysqli $link_identifier */
    // convert PostgreSQL RANDOM() function to MySQL RAND()
    //$query = preg_replace("/ORDER BY RANDOM\(\)/i", "ORDER BY RAND()", $query);
    return mysqli_query($link_identifier, $query);
}

/**
 * Fetch a result row as an associative and numeric array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param $result (resource) result resource to the query result.
 * @return array<int|string, mixed>|false|null Row data, or false/null if there are no more rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_fetch_array(mixed $result): mixed
{
    /** @var mysqli_result $result */
    return mysqli_fetch_array($result);
}

/**
 * Fetch a result row as an associative array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param $result (resource) result resource to the query result.
 * @return array<int|string, mixed>|false|null Associative row, or false/null if there are no more rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_fetch_assoc(mixed $result): mixed
{
    /** @var mysqli_result $result */
    return mysqli_fetch_assoc($result);
}

/**
 * Returns number of rows (tuples) affected by the last INSERT, UPDATE or DELETE query associated with link_identifier.
 * @param $link_identifier (resource) database link identifier.
 * @param $result (resource) result resource to the query result [UNUSED].
 * @return int|string Number of rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_affected_rows(mixed $link_identifier, mixed $result): mixed
{
    /** @var mysqli $link_identifier */
    unset($result);
    return mysqli_affected_rows($link_identifier);
}

/**
 * Get number of rows in result.
 * @param $result (resource) result resource to the query result.
 * @return int|string Number of affected rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_num_rows(mixed $result): mixed
{
    /** @var mysqli_result $result */
    return mysqli_num_rows($result);
}

/**
 * Returns the auto generated id used in the last query.
 * @param $link_identifier (resource) database link identifier.
 * @param $tablename (string) Table name. (unused here but required for other DAL).
 * @param $fieldname (string) Field name (column name). (unused here but required for other DAL).
 * @return int|string ID generated from the last INSERT operation.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_insert_id(mixed $link_identifier, mixed $tablename = '', mixed $fieldname = ''): mixed
{
    /** @var mysqli $link_identifier */
    unset($tablename);
    unset($fieldname);
    return mysqli_insert_id($link_identifier);
}

/**
 * Returns the SQL string to calculate the difference in seconds between to datetime fields.
 * @return string SQL query string.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_datetime_diff_seconds(mixed $start_date_field, mixed $end_date_field): mixed
{
    /** @var string $start_date_field */
    /** @var string $end_date_field */
    return 'TIMESTAMPDIFF(SECOND, ' . $start_date_field . ', ' . $end_date_field . ')';
}

/**
 * Escape a string for insertion into a SQL text field (avoiding SQL injection).
 * @param $link_identifier (resource) database link identifier.
 * @param $str (string) The string that is to be escaped.
 * @param $stripslashes (boolean) if true strip slashes from string
 * @return string Returns the escaped string, or FALSE on error.
 * @since 5.0.005 2007-12-05
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_escape_sql(mixed $link_identifier, mixed $str, mixed $stripslashes = true): mixed
{
    /** @var mysqli $link_identifier */
    /** @var string $str */
    /** @var bool $stripslashes */
    // Reverse magic_quotes_gpc/magic_quotes_sybase effects if ON.
    if ($stripslashes) {
        $str = stripslashes($str);
    }

    return mysqli_real_escape_string($link_identifier, $str);
}
