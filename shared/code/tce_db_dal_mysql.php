<?php

//============================================================+
// File name   : tce_db_dal_mysql.php
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

/**
 * Open a connection to a MySQL Server and select a database.
 * @param $host (string) database server host name.
 * @param $port (string) database connection port
 * @param $username (string) Name of the user that owns the server process.
 * @param $password (string) Password of the user that owns the server process.
 * @param $database (string) Database name.
 * @return resource|false Link identifier on success, or false on failure.
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
    /** @var string $port */
    /** @var string $username */
    /** @var string $database */
    set_error_handler(static fn(): bool => true);
    try {
        $db = mysql_connect($host . ':' . $port, $username, $password);
    } finally {
        restore_error_handler();
    }
    if (!$db) {
        return false;
    }

    if (strlen($database) > 0) {
        set_error_handler(static fn(): bool => true);
        try {
            $selected = mysql_select_db($database, $db);
        } finally {
            restore_error_handler();
        }
        if (!$selected) {
            return false;
        }
    }

    // set the correct charset encoding
    mysql_query($db, "SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
    mysql_query($db, "SET CHARACTER SET 'utf8'");
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
    /** @var resource $link_identifier */
    return mysql_close($link_identifier);
}

/**
 * Returns the text of the error message from previous database operation
 * @return string error message.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_error(mixed $link_identifier = null): mixed
{
    /** @var resource|null $link_identifier */
    if (empty($link_identifier)) {
        return '';
    }

    return '[' . mysql_errno($link_identifier) . ']: ' . mysql_error($link_identifier) . '';
}

/**
 * Sends a query to the currently active database on the server that's associated with the specified link identifier.<br>
 * @param $query (string) The query tosend. The query string should not end with a semicolon.
 * @param $link_identifier (resource) database link identifier.
 * @return false in case of error, TRUE or resource-identifier in case of success.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_query(mixed $query, mixed $link_identifier): mixed
{
    /** @var string $query */
    /** @var resource $link_identifier */
    // convert PostgreSQL RANDOM() function to MySQL RAND()
    //$query = preg_replace("/ORDER BY RANDOM\(\)/i", "ORDER BY RAND()", $query);
    return mysql_query($query, $link_identifier);
}

/**
 * Fetch a result row as an associative and numeric array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param $result (resource) result resource to the query result.
 * @return array<int|string, mixed>|false Row data, or false if there are no more rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_fetch_array(mixed $result): mixed
{
    /** @var resource $result */
    return mysql_fetch_array($result);
}

/**
 * Fetch a result row as an associative array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param $result (resource) result resource to the query result.
 * @return array<string, mixed>|false Associative row, or false if there are no more rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_fetch_assoc(mixed $result): mixed
{
    /** @var resource $result */
    return mysql_fetch_assoc($result);
}

/**
 * Returns number of rows (tuples) affected by the last INSERT, UPDATE or DELETE query associated with link_identifier.
 * @param $link_identifier (resource) database link identifier.
 * @param $result (resource) result resource to the query result [UNUSED].
 * @return int Number of rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_affected_rows(mixed $link_identifier, mixed $result): mixed
{
    /** @var resource $link_identifier */
    unset($result);
    return mysql_affected_rows($link_identifier);
}

/**
 * Get number of rows in result.
 * @param $result (resource) result resource to the query result.
 * @return int Number of affected rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_num_rows(mixed $result): mixed
{
    /** @var resource $result */
    return mysql_num_rows($result);
}

/**
 * Get the ID generated from the previous INSERT operation
 * @param $link_identifier (resource) database link identifier.
 * @param $tablename (string) Table name.
 * @param $fieldname (string) Field name (column name).
 * @return int ID generated from the last INSERT operation.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_insert_id(mixed $link_identifier, mixed $tablename = '', mixed $fieldname = ''): mixed
{
    /** @var resource $link_identifier */
    /** @var string $tablename */
    /** @var string $fieldname */
    unset($fieldname);
    /*
     * NOTE : mysql_insert_id() converts the return type of the
     * native MySQL C API function mysql_insert_id() to a type
     * of long (named int in PHP). If your AUTO_INCREMENT column
     * has a column type of BIGINT, the value returned by
     * mysql_insert_id() will be incorrect.
     */
    //return mysql_insert_id($link_identifier);
    if (
        ($r = mysql_query('SELECT LAST_INSERT_ID() FROM ' . $tablename . '', $link_identifier)) && ($m =
            mysql_fetch_row($r))
    ) {
        return $m[0];
    }

    return 0;
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
    /** @var resource $link_identifier */
    /** @var string $str */
    /** @var bool $stripslashes */
    // Reverse magic_quotes_gpc/magic_quotes_sybase effects if ON.
    if ($stripslashes) {
        $str = stripslashes($str);
    }

    return mysql_real_escape_string($str, $link_identifier);
}
