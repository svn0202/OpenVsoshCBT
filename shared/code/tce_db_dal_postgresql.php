<?php

//============================================================+
// File name   : tce_db_dal_postgresql.php
// Begin       : 2003-10-12
// Last Update : 2023-11-30
//
// Description : PostgreSQL driver for TCExam Database
//               Abstraction Layer (DAL).
//               This abstraction use the same SQL syntax
//               of MySQL.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * PostgreSQL driver for TCExam Database Abstraction Layer (DAL).
 * This abstraction layer uses the same SQL syntax of MySQL.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2004-12-21
 */

/**
 * Open a connection to a PostgreSQL Server and select a database.
 * If a second call is made to this function with the same arguments, no new link will be established, but instead, the link identifier of the already opened link will be returned.
 * @param string $host Database server host name.
 * @param string $port Database connection port.
 * @param string $username Name of the user that owns the server process.
 * @param string $password Password of the user that owns the server process.
 * @param string $database Database name.
 * @return \PgSql\Connection|false PostgreSQL connection on success, or false on failure.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_connect(
    mixed $host = 'localhost',
    mixed $port = '5432',
    mixed $username = 'postgres',
    #[\SensitiveParameter]
    mixed $password = '',
    mixed $database = 'template1',
): mixed {
    /** @var string $host */
    /** @var string $port */
    /** @var string $username */
    /** @var string $password */
    /** @var string $database */
    $connection_string =
        "host='"
        . $host
        . "' port='"
        . $port
        . "' dbname='"
        . $database
        . "' user='"
        . $username
        . "' password='"
        . $password
        . "'";
    set_error_handler(static fn(): bool => true);
    try {
        $db = pg_connect($connection_string);
    } finally {
        restore_error_handler();
    }
    if (!$db) {
        return false;
    }

    return $db;
}

/**
 * Closes the non-persistent connection to a database associated with the given connection resource.
 * @param \PgSql\Connection|null $link_identifier Database connection.
 * @return bool True on success or false on failure.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_close(mixed $link_identifier): mixed
{
    /** @var \PgSql\Connection|null $link_identifier */
    return pg_close($link_identifier);
}

/**
 * Returns the text of the error message from previous database operation
 * @param mixed $link_identifier Legacy shared DAL parameter; PostgreSQL uses the current connection.
 * @return string error message.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_error(mixed $link_identifier = null): mixed
{
    unset($link_identifier);
    return pg_last_error();
}

/**
 * Sends a query to the currently active database on the server that's associated with the specified link identifier.<br>
 * NOTE: Convert MySQL RAND() function to PostgreSQL RANDOM() on ORDER BY clause of selection queries.
 * @param string $query Query string without a trailing semicolon.
 * @param \PgSql\Connection $link_identifier Database connection.
 * @return \PgSql\Result|false Query result on success, false on error.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_query(mixed $query, mixed $link_identifier): mixed
{
    /** @var string $query */
    /** @var \PgSql\Connection $link_identifier */
    // convert MySQL RAND() function to PostgreSQL RANDOM()
    $query = preg_replace('/ORDER BY RAND\(\)/si', 'ORDER BY RANDOM()', $query) ?? $query;
    return pg_query($link_identifier, $query);
}

/**
 * Fetch a result row as an associative and numeric array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param PgSql\Result $result result resource to the query result.
 * @return array<int|string, mixed>|false row data, or false if there are no more rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_fetch_array(mixed $result): mixed
{
    /** @var \PgSql\Result $result */
    return pg_fetch_array($result);
}

/**
 * Fetch a result row as an associative array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param \PgSql\Result $result Query result.
 * @return array<string, string|null>|false Associative row, or false if there are no more rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_fetch_assoc(mixed $result): mixed
{
    /** @var \PgSql\Result $result */
    return pg_fetch_assoc($result);
}

/**
 * Returns number of rows (tuples) affected by the last INSERT, UPDATE or DELETE query associated with link_identifier.
 * @param mixed $link_identifier Legacy shared DAL parameter; unused by PostgreSQL.
 * @param \PgSql\Result $result Query result.
 * @return int Number of affected rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_affected_rows(mixed $link_identifier, mixed $result): mixed
{
    unset($link_identifier);
    /** @var \PgSql\Result $result */
    return pg_affected_rows($result);
}

/**
 * Get number of rows in result.
 * @param \PgSql\Result $result Query result.
 * @return int Number of rows.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_num_rows(mixed $result): mixed
{
    /** @var \PgSql\Result $result */
    return pg_num_rows($result);
}

/**
 * Get the ID generated from the previous INSERT operation
 * @param \PgSql\Connection $link_identifier Database connection.
 * @param string $tablename Table name.
 * @param string $fieldname Field name (column name).
 * @return int|string ID generated from the last INSERT operation, or 0 when unavailable.
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_insert_id(mixed $link_identifier, mixed $tablename = '', mixed $fieldname = ''): mixed
{
    /** @var \PgSql\Connection $link_identifier */
    /** @var string $tablename */
    /** @var string $fieldname */
    set_error_handler(static fn(): bool => true);
    try {
        $r = pg_query(
            $link_identifier,
            "SELECT CURRVAL('" . $tablename . '_' . $fieldname . "_seq')",
        );
    } finally {
        restore_error_handler();
    }
    if ($r) {
        $m = pg_fetch_row($r, 0);
        if ($m !== false) {
            /** @var array{0:string} $m */
            return $m[0];
        }
    }

    return 0;
}

/**
 * Returns the SQL string to calculate the difference in seconds between to datetime fields.
 * @return SQL query string
 */
// @mago-expect analysis:duplicate-definition -- only one configured DAL implementation is loaded at runtime
function f_db_datetime_diff_seconds($start_date_field, $end_date_field)
{
    return 'EXTRACT(EPOCH FROM (' . $end_date_field . ' - ' . $start_date_field . '))';
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
function f_escape_sql($link_identifier, $str, $stripslashes = true)
{
    // Reverse magic_quotes_gpc/magic_quotes_sybase effects if ON.
    if ($stripslashes) {
        $str = stripslashes($str);
    }

    return pg_escape_string($link_identifier, $str);
}
