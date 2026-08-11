<?php

//============================================================+
// File name   : tce_db_dal_oracle.php
// Begin       : 2009-10-09
// Last Update : 2023-11-30
//
// Description : Oracle driver for TCExam Database
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
 * Oracle driver for TCExam Database Abstraction Layer (DAL).
 * This abstraction layer uses the same SQL syntax of MySQL.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2009-10-09
 */

/**
 * Open a connection to a Oracle Server and select a database.
 * If a second call is made to this function with the same arguments, no new link will be established, but instead, the link identifier of the already opened link will be returned.
 * @param $host (string) database server host name.
 * @param $port (string) database connection port
 * @param $username (string) Name of the user that owns the server process.
 * @param $password (string) Password of the user that owns the server process.
 * @param $database (string) Database name.
 * @return object|resource|false Oracle link identifier on success, or false on failure.
 */
function f_db_connect(
    mixed $host = 'localhost',
    mixed $port = '1521',
    mixed $username = 'root',
    #[\SensitiveParameter]
    mixed $password = '',
    mixed $database = '',
): mixed {
    /** @var string $host */
    /** @var string $port */
    /** @var string $username */
    /** @var string $password */
    /** @var string $database */
    $dbstring = '//' . $host . ':' . $port;
    if (!empty($database)) {
        $dbstring .= '/' . $database;
    }

    set_error_handler(static fn(): bool => true);
    try {
        $db = oci_connect($username, $password, $dbstring, 'UTF8');
    } finally {
        restore_error_handler();
    }
    if (!$db) {
        return false;
    }

    // change date format
    F_db_query("ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'", $db);
    return $db;
}

/**
 * Closes the non-persistent connection to a database associated with the given connection resource.
 * @param $link_identifier (resource) database link identifier.
 * @return bool True on success or false on failure.
 */
function f_db_close(mixed $link_identifier): mixed
{
    return oci_close($link_identifier);
}

/**
 * Returns the text of the error message from previous database operation
 * @return string Error message.
 */
function f_db_error(mixed $link_identifier = null): mixed
{
    unset($link_identifier);
    $e = oci_error();
    /** @var array{code:int|string,message:string} $e */
    return '[' . $e['code'] . ']: ' . $e['message'] . '';
}

/**
 * Sends a query to the currently active database on the server that's associated with the specified link identifier.<br>
 * NOTE: Convert MySQL RAND() function to Oracle RANDOM() on ORDER BY clause of selection queries.
 * @param $query (string) The query tosend. The query string should not end with a semicolon.
 * @param $link_identifier (resource) database link identifier.
 * @return false in case of error, TRUE or resource-identifier in case of success.
 * @mago-expect analysis:invalid-return-statement(2),less-specific-return-statement(2) -- legacy shared DAL contract
 */
function f_db_query(mixed $query, mixed $link_identifier): mixed
{
    /** @var string $query */
    /** @var object|resource $link_identifier */
    /** @var array<int,bool> $transactions */
    static $transactions = [];
    $connection_id = is_object($link_identifier)
        ? spl_object_id($link_identifier)
        : (int) $link_identifier;

    if ($query === 'START TRANSACTION') {
        $transactions[$connection_id] = true;
        return true;
    }
    if ($query === 'COMMIT') {
        set_error_handler(static fn(): bool => true);
        try {
            $committed = oci_commit($link_identifier);
        } finally {
            restore_error_handler();
        }
        unset($transactions[$connection_id]);
        return $committed;
    }
    if ($query === 'ROLLBACK') {
        set_error_handler(static fn(): bool => true);
        try {
            $rolled_back = oci_rollback($link_identifier);
        } finally {
            restore_error_handler();
        }
        unset($transactions[$connection_id]);
        return $rolled_back;
    }

    // convert MySQL RAND() function to Oracle dbms_random.random
    $query = preg_replace('/ORDER BY RAND\(\)/si', 'ORDER BY dbms_random.random', $query) ?? $query;
    // remove last limit clause
    $query = preg_replace("/LIMIT 1([\s]*)$/si", '', $query) ?? $query;

    set_error_handler(static fn(): bool => true);
    try {
        $stid = oci_parse($link_identifier, $query);
    } finally {
        restore_error_handler();
    }
    if (!$stid) {
        return false;
    }

    $mode = isset($transactions[$connection_id]) ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;
    set_error_handler(static fn(): bool => true);
    try {
        $executed = oci_execute($stid, $mode);
    } finally {
        restore_error_handler();
    }
    if ($executed) {
        return $stid;
    }

    return false;
}

/**
 * Fetch a result row as an associative and numeric array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param $result (resource) result resource to the query result.
 * @return array<int|string, mixed>|false Row data, or false if there are no more rows.
 */
function f_db_fetch_array(mixed $result): mixed
{
    $arr = oci_fetch_array($result, OCI_BOTH + OCI_RETURN_NULLS + OCI_RETURN_LOBS);
    if ($arr !== false) {
        /** @var array<int|string, bool|float|int|string|null> $arr */
        $normalized = [];
        foreach ($arr as $key => $value) {
            $normalized[is_string($key) ? strtolower($key) : $key] = stripslashes((string) ($value ?? ''));
        }
        $arr = $normalized;
    }

    return $arr;
}

/**
 * Fetch a result row as an associative array.
 * Note: This function sets NULL fields to PHP NULL value.
 * @param $result (resource) result resource to the query result.
 * @return array<string, mixed>|false Row data, or false if there are no more rows.
 */
function f_db_fetch_assoc(mixed $result): mixed
{
    $arr = oci_fetch_assoc($result);
    if ($arr !== false) {
        $arr = array_change_key_case($arr, CASE_LOWER);
        $arr = array_map(static fn(mixed $value): string => f_db_oracle_strip_slashes($value), $arr);
    }

    return $arr;
}

function f_db_oracle_strip_slashes(mixed $value): string
{
    if ($value !== null && !is_scalar($value)) {
        throw new TypeError('Oracle row values must be scalar or null.');
    }

    return stripslashes((string) ($value ?? ''));
}

/**
 * Returns number of rows (tuples) affected by the last INSERT, UPDATE or DELETE query associated with link_identifier.
 * @param $link_identifier (resource) database link identifier [UNUSED].
 * @param $result (resource) result resource to the query result.
 * @return int|false Number of rows, or false on failure.
 */
function f_db_affected_rows(mixed $link_identifier, mixed $result): mixed
{
    unset($link_identifier);
    return oci_num_rows($result);
}

/**
 * Get number of rows in result.
 * @param $result (resource) result resource to the query result.
 * @return int|string|false Number of affected rows, or false on failure.
 */
function f_db_num_rows(mixed $result): mixed
{
    $output = [];
    set_error_handler(static fn (): bool => true);
    try {
        oci_fetch_all($result, $output);
    } finally {
        restore_error_handler();
    }
    return $output['TOTAL'][0] ?? oci_num_rows($result);
}

/**
 * Get the ID generated from the previous INSERT operation
 * @param $link_identifier (resource) database link identifier.
 * @param $tablename (string) Table name.
 * @param $fieldname (string) Field name (column name).
 * @return int ID generated from the last INSERT operation.
 * @mago-expect analysis:nullable-return-statement,invalid-return-statement -- Oracle may return a numeric string
 */
function f_db_insert_id(mixed $link_identifier, mixed $tablename = '', mixed $fieldname = ''): mixed
{
    /** @var string $tablename */
    unset($fieldname);
    $query = 'SELECT ' . $tablename . '_seq.currval FROM dual';
    set_error_handler(static fn (): bool => true);
    try {
        /** @var object|resource|bool $r */
        $r = F_db_query($query, $link_identifier);
    } finally {
        restore_error_handler();
    }
    if ($r) {
        $m = oci_fetch_array($r, OCI_NUM);
        if ($m !== false) {
            /** @var array{0:int|string|null} $m */
            return $m[0];
        }
    }

    return 0;
}

/**
 * Returns the SQL string to calculate the difference in seconds between to datetime fields.
 * @return string SQL query string.
 */
function f_db_datetime_diff_seconds(mixed $start_date_field, mixed $end_date_field): mixed
{
    /** @var string $start_date_field */
    /** @var string $end_date_field */
    return '(' . $end_date_field . ' – ' . $start_date_field . ')*86400';
}

/**
 * Escape a string for insertion into a SQL text field (avoiding SQL injection).
 * @param $link_identifier (resource) database link identifier.
 * @param $str (string) The string that is to be escaped.
 * @param $stripslashes (boolean) if true strip slashes from string
 * @return string Escaped value, or false on error.
 * @since 5.0.005 2007-12-05
 */
function f_escape_sql(mixed $link_identifier, mixed $str, mixed $stripslashes = true): mixed
{
    /** @var string $str */
    /** @var bool $stripslashes */
    unset($link_identifier);
    // Reverse magic_quotes_gpc/magic_quotes_sybase effects if ON.
    if ($stripslashes) {
        $str = stripslashes($str);
    }

    return pg_escape_string($str);
}
