<?php

//============================================================+
// File name   : tce_functions_general.php
// Begin       : 2001-09-08
// Last Update : 2023-11-30
//
// Description : General functions.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * General functions.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2001-09-08
 */

/**
 * Normalize a legacy scalar value for string operations.
 */
function f_general_string(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value) || is_bool($value) || $value === null || $value instanceof \Stringable) {
        return (string) $value;
    }
    return 'Array';
}

/**
 * Normalize a legacy numeric value for arithmetic operations.
 */
function f_general_float(mixed $value): float
{
    return is_numeric($value) ? (float) $value : 0.0;
}

/**
 * Normalize a legacy scalar value for integer operations.
 */
function f_general_int(mixed $value): int
{
    return is_scalar($value) || $value === null ? (int) $value : 0;
}

/**
 * Normalize a legacy value expected to contain an array.
 * @return array<array-key,array<array-key,mixed>|bool|float|int|string|null>
 */
function f_general_array(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $normalize_item = static function (mixed $item): array|bool|float|int|string|null {
        if (is_array($item) || is_scalar($item) || $item === null) {
            return $item;
        }
        return f_general_string($item);
    };
    $normalized = [];
    foreach (array_keys($value) as $key) {
        $normalized[$key] = $normalize_item($value[$key] ?? null);
    }
    return $normalized;
}

/**
 * Count rows of the given table.
 * @param $dbtable (string) database table name
 * @param $where (string) optional where SQL clause (including the WHERE keyword).
 * @return int number of rows
 */
function f_count_rows(string $dbtable, string $where = ''): int
{
    global $db;
    /** @var mixed $db */
    require_once '../config/tce_config.php';
    $normalize_query_result = static function (mixed $result): mixed {
        if (is_bool($result) || is_resource($result) || $result instanceof \mysqli_result || $result instanceof \PgSql\Result) {
            return $result;
        }
        return false;
    };
    /** @return array<array-key,mixed>|null */
    $normalize_row = static fn (mixed $row): ?array => is_array($row) ? $row : null;
    $numofrows = 0;
    $sql = 'SELECT COUNT(*) AS numrows FROM ' . $dbtable . ' ' . $where . '';
    if ($result = $normalize_query_result(F_db_query($sql, $db))) {
        if ($m = $normalize_row(F_db_fetch_array($result))) {
            $numofrows = (int) ($m['numrows'] ?? 0);
        }
    } else {
        F_display_db_error();
    }

    return $numofrows;
}

/**
 * Prepare field value for SQL query.<br>
 * Returns the quoted string if not empty, NULL otherwise.
 * @param $str (string) string to check.
 * @return string $str quoted if not empty, NULL otherwise
 */
function f_empty_to_null(mixed $str): string
{
    global $db;
    require_once '../../shared/code/tce_db_dal.php';
    $str = f_general_string($str);
    if (strlen($str) > 0) {
        return "'" . F_escape_sql($db, $str) . "'";
    }

    return 'NULL';
}

/**
 * Compare a legacy scalar value with an integer without PHP's loose comparison operator.
 */
function f_legacy_int_equals(mixed $value, int $expected): bool
{
    if (is_int($value)) {
        return $value === $expected;
    }

    if (is_float($value)) {
        return $value === (float) $expected;
    }

    if (is_string($value)) {
        return is_numeric($value) && (float) $value === (float) $expected;
    }

    if (is_bool($value)) {
        return $value === ($expected !== 0);
    }

    return $value === null && $expected === 0;
}

/**
 * Compare a legacy scalar value with a non-numeric string literal without PHP's loose comparison operator.
 */
function f_legacy_literal_equals(mixed $value, string $expected): bool
{
    if (is_string($value)) {
        return $value === $expected;
    }

    if (is_bool($value)) {
        return $value === ($expected !== '');
    }

    if ($value === null) {
        return $expected === '';
    }

    return (is_int($value) || is_float($value)) && (string) $value === $expected;
}

/**
 * Compare legacy values using PHP's ordering rules without the discouraged loose equality operator.
 */
function f_legacy_equals(mixed $left, mixed $right): bool
{
    // @mago-expect analysis:mixed-operand -- this compatibility boundary intentionally delegates PHP comparison rules
    // @mago-expect analysis:mixed-operand -- both legacy operands may have any request or database value type
    return ($left <=> $right) === 0;
}

/**
 * Prepare field value for SQL query.<br>
 * Returns the num if different from zero, NULL otherwise.
 * @param $num (string) string to check.
 * @return string $num if != 0, NULL otherwise
 */
function f_zero_to_null(mixed $num): mixed
{
    global $db;
    require_once '../../shared/code/tce_db_dal.php';
    if (f_legacy_int_equals($num, 0)) {
        return 'NULL';
    }

    return F_escape_sql($db, $num);
}

/**
 * Returns boolean value from string or integer.<br>
 * This function is needed to get the right boolean value from boolean field returned by PostgreSQL query.
 * @param $str (string) string to check.
 * @return boolean value.
 */
function f_get_boolean(mixed $str): bool
{
    if (is_bool($str)) {
        return $str;
    }

    if (is_string($str) && (strncasecmp($str, 't', 1) === 0 || strncasecmp($str, '1', 1) === 0)) {
        return true;
    }

    return is_int($str) && $str === 1;
}

/**
 * Normalize an untrusted request value to a positive integer identifier.
 */
function f_positive_request_int(mixed $value): int
{
    if (!is_numeric($value)) {
        return 0;
    }

    $value = (int) $value;
    return $value > 0 ? $value : 0;
}

/**
 * Remove duplicate positive positions from a matching answer.
 * The first occurrence is retained and later duplicates become unanswered.
 *
 * @param array $positions Submitted positions, keyed by displayed answer position.
 * @param bool $allow_repeated Whether positive positions may be used more than once.
 * @return array Normalized positions.
 */
/**
 * @param array<array-key,mixed> $positions
 * @return array<array-key,int>
 */
function f_normalize_matching_positions(array $positions, bool $allow_repeated = false): array
{
    $seen = [];
    $normalized_positions = [];
    foreach (array_keys($positions) as $key) {
        $position = f_general_int($positions[$key] ?? null);
        if ($position <= 0) {
            $normalized_positions[$key] = 0;
            continue;
        }

        if (!$allow_repeated && isset($seen[$position])) {
            $normalized_positions[$key] = 0;
            continue;
        }

        $seen[$position] = true;
        $normalized_positions[$key] = $position;
    }

    return $normalized_positions;
}

/**
 * Check if specified fields are unique on table.
 * @param $table (string) table name
 * @param $where (string) SQL where clause
 * @param $fieldname (mixed) name of table column to check
 * @param $fieldid (mixed) ID of table row to check
 * @return bool true if unique, false otherwise
 */
function f_check_unique(string $table, string $where, string|false $fieldname = false, mixed $fieldid = false): bool
{
    require_once '../config/tce_config.php';
    global $db;
    /** @var mixed $db */
    $normalize_query_result = static function (mixed $result): mixed {
        if (is_bool($result) || is_resource($result) || $result instanceof \mysqli_result || $result instanceof \PgSql\Result) {
            return $result;
        }
        return false;
    };
    /** @return array<array-key,mixed>|null */
    $normalize_row = static fn (mixed $row): ?array => is_array($row) ? $row : null;
    $sqlc = 'SELECT * FROM ' . $table . ' WHERE ' . $where . ' LIMIT 1';
    if ($result = $normalize_query_result(F_db_query($sqlc, $db))) {
        if ($fieldname === false && $fieldid === false && F_count_rows($table, 'WHERE ' . $where) > 0) {
            return false;
        }

        if ($mc = $normalize_row(F_db_fetch_array($result))) {
            $field_key = $fieldname === false ? 0 : $fieldname;
            if (f_legacy_equals($mc[$field_key] ?? null, $fieldid)) {
                return true; // the values are unchanged
            }
        } else {
            // the new values are not yet present on table
            return true;
        }
    } else {
        F_display_db_error();
    }

    // another table row contains the same values
    return false;
}

/**
 * Reverse function for htmlentities.
 * @param $text_to_convert (string) input string to convert
 * @param $preserve_tagsign (boolean) if true preserve <> symbols, default=FALSE
 * @return string Converted string.
 */
function unhtmlentities(mixed $text_to_convert, mixed $preserve_tagsign = false): string
{
    $text_to_convert = f_general_string($text_to_convert);
    if ($preserve_tagsign) {
        $text_to_convert = preg_replace('/\&([gl])t;/', '&amp;\\1t;', $text_to_convert) ?? $text_to_convert;
    }

    return html_entity_decode($text_to_convert, ENT_NOQUOTES | ENT_XHTML, 'UTF-8');
}

/**
 * Remove the following characters:
 * <ul>
 * <li>"\t" (ASCII 9 (0x09)), a tab.</li>
 * <li>"\n" (ASCII 10 (0x0A)), a new line (line feed)</li>
 * <li>"\r" (ASCII 13 (0x0D)), a carriage return</li>
 * <li>"\0" (ASCII 0 (0x00)), the NUL-byte</li>
 * <li>"\x0B" (ASCII 11 (0x0B)), a vertical tab</li>
 * </ul>
 * @param $string (string) input string to convert
 * @param $dquotes (boolean) If true add slash in fron of double quotes;
 * @return string Converted string.
 */
function f_compact_string(mixed $string, mixed $dquotes = false): string
{
    $string = f_general_string($string);
    $repTable = [
        "\t" => ' ',
        "\n" => ' ',
        "\r" => ' ',
        "\0" => ' ',
        "\x0B" => ' ',
    ];
    if ($dquotes) {
        $repTable['"'] = '&quot;';
    }

    return strtr($string, $repTable);
}

/**
 * Replace angular parenthesis with html equivalents (html entities).
 * @param $str (string) input string to convert
 * @return string Converted string.
 */
function f_replace_angulars(mixed $str): string
{
    $str = f_general_string($str);
    $replaceTable = [
        '<' => '&lt;',
        '>' => '&gt;',
    ];
    return strtr($str, $replaceTable);
}

/**
 * Performs a multi-byte safe substr() operation based on number of characters.
 * @param $str (string) input string
 * @param $start (int) substring start index
 * @param $length (int) substring max length
 * @return string Substring.
 */
function f_substr_utf8(mixed $str, mixed $start, mixed $length): string
{
    $str = f_general_string($str);
    $bytelen = strlen($str);
    $i = 0;
    $j = 0;
    $str_start = 0;
    $str_end = $bytelen;
    while ($i < $bytelen) {
        if (f_legacy_int_equals($start, $j)) {
            $str_start = $i;
        } elseif (f_legacy_int_equals($length, $j)) {
            $str_end = $i;
            break;
        }

        $char = ord($str[$i]); // get one string character at time
        if ($char <= 0x7F) {
            ++$i;
        } elseif (($char >> 0x05) === 0x06) { // 2 bytes character (0x06 = 110 BIN)
            $i += 2;
        } elseif (($char >> 0x04) === 0x0E) { // 3 bytes character (0x0E = 1110 BIN)
            $i += 3;
        } elseif (($char >> 0x03) === 0x1E) { // 4 bytes character (0x1E = 11110 BIN)
            $i += 4;
        } else {
            ++$i;
        }

        ++$j;
    }

    return substr($str, $str_start, $str_end);
}

/**
 * Escape some special characters (&lt; &gt; &amp;).
 * @param $str (string) input string to convert
 * @return string Converted string.
 */
function f_text_to_xml(mixed $str): string
{
    if (empty($str)) {
        return '';
    }
    $str = f_general_string($str);

    $replaceTable = [
        "\0" => '',
        '&' => '&amp;',
        '<' => '&lt;',
        '>' => '&gt;',
    ];
    return strtr($str, $replaceTable);
}

/**
 * Unescape some special characters (&lt; &gt; &amp;).
 * @param $str (string) input string to convert
 * @return string Converted string.
 */
function f_xml_to_text(mixed $str): string
{
    if (empty($str)) {
        return '';
    }

    $str = f_general_string($str);
    $replaceTable = [
        '&amp;' => '&',
        '&lt;' => '<',
        '&gt;' => '>',
    ];
    return strtr($str, $replaceTable);
}

/**
 * Escape some special characters for TSV output.
 * @param $str (string) input string to convert
 * @return string Converted string.
 */
function f_text_to_tsv(mixed $str): string
{
    if (empty($str)) {
        return '';
    }

    $str = f_general_string($str);
    $replaceTable = [
        "\0" => '',
        "\t" => '\t',
        "\n" => '\n',
        "\r" => '\r',
    ];
    return strtr($str, $replaceTable);
}

/**
 * Unescape some special characters from TSV format.
 * @param $str (string) input string to convert
 * @return string Converted string.
 */
function f_tsv_to_text(mixed $str): string
{
    if (empty($str)) {
        return '';
    }

    $str = f_general_string($str);
    $replaceTable = [
        '\t' => "\t",
        '\n' => "\n",
        '\r' => "\r",
    ];
    return strtr($str, $replaceTable);
}

/**
 * Return a string containing an HTML abbreviation for required/not required fields.
 * @param $mode (int) field mode: 1=not required; 2=required.
 * @return string HTML marker.
 */
function show_required_field(mixed $mode = 1): string
{
    global $l;
    /** @var array{w_not_required:string,w_required:string} $l */
    if (f_legacy_int_equals($mode, 2)) {
        return ' <abbr class="requiredonbox" title="' . $l['w_required'] . '">+</abbr>';
    }

    return ' <abbr class="requiredoffbox" title="' . $l['w_not_required'] . '">-</abbr>';
}

/**
 * Strip whitespace (or other characters) from the beginning and end of an UTF-8 string and replace the "\xA0" with normal space.
 * @param $txt (string) The string that will be trimmed.
 * @return string The trimmed string.
 */
function utrim(mixed $txt): string
{
    if (empty($txt)) {
        return '';
    }

    $txt = f_general_string($txt);
    $txt = preg_replace('/\xA0/u', ' ', $txt) ?? $txt;
    $txt = preg_replace('/^([\s]+)/u', '', $txt) ?? $txt;
    return preg_replace('/([\s]+)$/u', '', $txt) ?? $txt;
}

/**
 * Convert all IP addresses to IPv6 expanded notation.
 * @param $ip (string) IP address to normalize.
 * @return string|false IPv6 address in expanded notation or false in case of invalid input.
 * @since 7.1.000 (2009-02-13)
 */
function get_normalized_ip(mixed $ip): string|false
{
    $ip = strtolower(f_general_string($ip));
    if ($ip === '0000:0000:0000:0000:0000:0000:0000:0001' || $ip === '::1') {
        // fix localhost problem
        $ip = '127.0.0.1';
    }

    // remove unsupported parts
    if (($pos = strrpos($ip, '%')) !== false) {
        $ip = substr($ip, 0, $pos);
    }

    if (($pos = strrpos($ip, '/')) !== false) {
        $ip = substr($ip, 0, $pos);
    }

    $ip = preg_replace("/[^0-9a-f:\.]+/si", '', $ip) ?? '';
    // check address type
    $is_ipv6 = str_contains($ip, ':');
    $is_ipv4 = str_contains($ip, '.');
    if (!$is_ipv4 && !$is_ipv6) {
        return false;
    }

    if ($is_ipv6 && $is_ipv4) {
        // strip IPv4 compatibility notation from IPv6 address
        $ip = substr($ip, (int) strrpos($ip, ':') + 1);
        $is_ipv6 = false;
    }

    if ($is_ipv4) {
        // convert IPv4 to IPv6
        $ip_parts = array_pad(explode('.', $ip), 4, 0);
        if (count($ip_parts) > 4) {
            return false;
        }

        $ipv4_parts = [
            (int) $ip_parts[0],
            (int) ($ip_parts[1] ?? 0),
            (int) ($ip_parts[2] ?? 0),
            (int) ($ip_parts[3] ?? 0),
        ];
        foreach ($ipv4_parts as $ipv4_part) {
            if ($ipv4_part > 255) {
                return false;
            }
        }

        $part7 = base_convert((string) (($ipv4_parts[0] * 256) + $ipv4_parts[1]), 10, 16);
        $part8 = base_convert((string) (($ipv4_parts[2] * 256) + $ipv4_parts[3]), 10, 16);
        $ip = '::ffff:' . $part7 . ':' . $part8;
    }

    // expand IPv6 notation
    if (str_contains($ip, '::')) {
        $ip = str_replace('::', str_repeat(':0000', max(0, 8 - substr_count($ip, ':'))) . ':', $ip);
    }

    if (str_starts_with($ip, ':')) {
        $ip = '0000' . $ip;
    }

    // normalize parts to 4 bytes
    $ip_parts = explode(':', $ip);
    foreach ($ip_parts as $key => $num) {
        $ip_parts[$key] = sprintf('%04s', $num);
    }

    return implode(':', $ip_parts);
}

/**
 * Converts an IP address into its packed 16-byte binary representation (network byte order).
 * This preserves full 128-bit precision and is case-insensitive, so the returned fixed-width
 * byte strings can be ordered and range-compared losslessly with strcmp() (see f_is_valid_ip).
 * Input may use any notation accepted by get_normalized_ip() (IPv4, IPv6, or the expanded form
 * already stored in the database).
 * @param $ip (string) IP address to convert.
 * @return string|false 16-byte packed IPv6 address, or false on invalid input.
 * @since 17.1.0 (2026-06-23)
 */
function get_ip_as_bytes(mixed $ip): string|false
{
    $norm = get_normalized_ip($ip);
    if ($norm === false) {
        return false;
    }

    return inet_pton($norm);
}

/**
 * Returns a human-readable, compact string representation of an IP address.
 * The stored form is the expanded IPv6 produced by get_normalized_ip(); this compacts it
 * (and unwraps IPv4-mapped IPv6, e.g. ::ffff:127.0.0.1 -> 127.0.0.1) so it reads naturally
 * and fits report/result columns.
 * @param $ip (string) IP address to convert.
 * @return string IP address as a readable string.
 * @since 9.0.033 (2009-11-03)
 */
function get_ip_as_string(mixed $ip): string
{
    $norm = get_normalized_ip($ip);
    if ($norm === false || $norm === '') {
        return '';
    }

    $packed = inet_pton($norm);
    if ($packed === false) {
        return $norm;
    }

    $str = inet_ntop($packed);
    if ($str === false) {
        return $norm;
    }

    // Unwrap IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1 -> 127.0.0.1) for readability.
    $m = [];
    if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $str, $m)) {
        return $m[1] ?? $str;
    }

    return $str;
}

/**
 * Format a percentage number.
 * @param $num (float) number to be formatted
 * @return string Formatted number.
 */
function f_format_float(mixed $num): string
{
    return sprintf('%.03f', round(f_general_float($num), 3));
}

/**
 * Format a percentage number.
 * @param $num (float) Number to be formatted.
 * @param $ratio (boolean) Set to true if the number is a ratio between 0 and 1, false if is a percentage number between 0 an 100.
 * @return string Formatted percentage.
 */
function f_format_percentage(mixed $num, mixed $ratio = true): string
{
    $num = f_general_float($num);
    if ($ratio) {
        $num = 100 * $num;
    }

    return '(' . str_replace(' ', '&nbsp;', sprintf('% 3d', round($num))) . '%)';
}

/**
 * format a percentage number
 * @param $num (float) number to be formatted
 * @param $ratio (boolean) Set to true if the number is a ratio between 0 and 1, false if is a percentage number between 0 an 100.
 * @return string
 */
function f_format_pdf_percentage(mixed $num, mixed $ratio = true): string
{
    $num = f_general_float($num);
    if ($ratio) {
        $num = 100 * $num;
    }

    return sprintf('(% 3d%%)', round($num));
}

/**
 * format a percentage number for XML
 * @param $num (float) number to be formatted
 * @param $ratio (boolean) Set to true if the number is a ratio between 0 and 1, false if is a percentage number between 0 an 100.
 * @return string
 */
function f_format_xml_percentage(mixed $num, mixed $ratio = true): string
{
    $num = f_general_float($num);
    if ($ratio) {
        $num = 100 * $num;
    }

    return sprintf('%3d', round($num));
}

/**
 * Returns the UTC time offset in seconds
 * @param $timezone (string) current user timezone
 * @return int UTC time offset in seconds
 */
function f_get_utc_offset(mixed $timezone): int
{
    $timezone = f_general_string($timezone);
    $user_timezone = timezone_open($timezone);
    if ($user_timezone === false) {
        return 0;
    }
    $user_datetime = date_create('now', $user_timezone);
    if ($user_datetime === false) {
        return 0;
    }
    return $user_timezone->getOffset($user_datetime);
}

/**
 * Returns the UTC time offset yo be used with CONVERT_TZ function
 * @param $timezone (string) current user timezone
 * @return string UTC time offset (+HH:mm)
 */
function f_db_get_utc_offset(mixed $timezone): string
{
    $time_offset = f_get_utc_offset($timezone);
    $sign = $time_offset >= 0 ? '+' : '-';
    return $sign . gmdate('H:i', abs($time_offset));
}

/**
 * Get data array in XML format.
 * @param $data (array) Array of data (key => value).
 * @param $level (int) Indentation level.
 * @return string XML data
 */
function get_data_xml(mixed $data, mixed $level = 1): string
{
    $data = f_general_array($data);
    $level = is_numeric($level) ? (int) $level : 1;
    $xml = '';
    $tb = str_repeat("\t", max(0, $level));
    foreach ($data as $key => $value) {
        $key = strtolower((string) $key);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        if ($key === '' || is_numeric($key[0]) || $key[0] === '_') {
            $key = 'item' . $key;
        }

        $xml .= $tb . '<' . $key . '>';
        if (is_array($value)) {
            /** @var array<array-key,array<array-key,mixed>|bool|float|int|string|null> $nested_data */
            $nested_data = $value;
            $xml .= "\n" . get_data_xml($nested_data, $level + 1);
        } else {
            $xml .= f_text_to_xml((string) $value);
        }

        $xml .= '</' . $key . '>' . "\n";
    }

    return $xml;
}

/**
 * Get data headers (keys) in TSV header (tab separated text values).
 * @param $data (array) Array of data (key => value).
 * @param $prefix (string) Prefix to add to keys.
 * @return string data
 */
function get_data_tsv_header(mixed $data, mixed $prefix = ''): string
{
    $data = f_general_array($data);
    $prefix = f_general_string($prefix);
    $tsv = '';
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            /** @var array<array-key,array<array-key,mixed>|bool|float|int|string|null> $nested_data */
            $nested_data = $value;
            $tsv .= get_data_tsv_header($nested_data, $prefix . $key . '_');
        } else {
            $tsv .= "\t" . $prefix . $key;
        }
    }

    return $tsv;
}

/**
 * Get data in TSV format (tab separated text values).
 * @param $data (array) Array of data.
 * @return string XML data
 */
function get_data_tsv(mixed $data): string
{
    $data = f_general_array($data);
    $tsv = '';
    foreach ($data as $value) {
        if (is_array($value)) {
            /** @var array<array-key,array<array-key,mixed>|bool|float|int|string|null> $nested_data */
            $nested_data = $value;
            $tsv .= get_data_tsv($nested_data);
        } else {
            $tsv .= "\t" . f_text_to_tsv((string) $value);
        }
    }

    return $tsv;
}

/**
 * Convert HTML code to TSV string.
 * @param $str (string) HTML string to convert.
 * @return string TSV
 */
function f_html_to_tsv(mixed $str): string
{
    $str = f_general_string($str);
    $dollar_replacement = ':.dlr.:'; //string replacement for dollar symbol
    //tags conversion table
    $tags2textTable = [
        "'<br[^>]*?>'i" => ' ',
        "'<table[^>]*?>'i" => "\n",
        "'</table>'i" => "\n",
        "'<tr[^>]*?>'i" => "\n",
        "'<th[^>]*?>'i" => "\t",
        "'<td[^>]*?>'i" => "\t",
        "'<h[0-9][^>]*?>'i" => "\n\n",
        "'</h[0-9]>'i" => "\n",
    ];
    $str = str_replace('&nbsp;', ' ', $str);
    $str = str_replace('&rarr;', '-', $str);
    $str = str_replace('&darr;', '', $str);
    $str = str_replace("\t", ' ', $str);
    $str = preg_replace_callback(
        '/colspan="([0-9]*)"/x',
        static function (array $match): string {
            $colspan = (int) ($match[1] ?? 0);
            if ($colspan > 1) {
                return str_repeat('></td><td', $colspan - 1);
            }

            return '';
        },
        $str,
    ) ?? $str;
    $str = str_replace("\r\n", "\n", $str);
    $str = str_replace("\$", $dollar_replacement, $str); //replace special character
    //remove newlines
    $str = str_replace("\n", '', $str);
    $str = preg_replace(array_keys($tags2textTable), array_values($tags2textTable), $str) ?? $str;
    $str = preg_replace("'<[^>]*?>'si", '', $str) ?? $str; //strip out remaining tags
    //remove some newlines in excess
    $str = preg_replace("'[ \t\f]+[\r\n]'si", "\n", $str) ?? $str;
    $str = preg_replace("'[\r\n][\r\n]+'si", "\n\n", $str) ?? $str;
    $str = unhtmlentities($str, false);
    $str = str_replace($dollar_replacement, "\$", $str); //restore special character
    $str = rtrim($str);
    $str = ltrim($str, " \r\n\0\x0B");
    return stripslashes($str);
}

/**
 * Display table header element with order link.
 * @param $order_field (string) name of table field
 * @param $orderdir (string) order direction
 * @param $title title (string) field of anchor link
 * @param $name column (string) name
 * @param $current_order_field (string) current order field name
 * @param $filter (string) additional parameters to pass on URL
 * @return string table header element
 */
function f_select_table_header_element(mixed $order_field, mixed $orderdir, mixed $title, mixed $name, mixed $current_order_field = '', mixed $filter = ''): string
{
    global $l;
    /** @var array{w_ascent:string,w_descent:string} $l */
    /** @var array{SCRIPT_NAME:string} $server */
    $server = &$_SERVER;
    $order_field = f_general_string($order_field);
    $title = f_general_string($title);
    $name = f_general_string($name);
    $current_order_field = f_general_string($current_order_field);
    $filter = f_general_string($filter);
    $orderdir_value = f_general_string($orderdir);
    require_once '../config/tce_config.php';
    $ord = '';
    if ($order_field === $current_order_field) {
        if (f_legacy_int_equals($orderdir, 1)) {
            $ord = ' <abbr title="' . $l['w_ascent'] . '">&gt;</abbr>';
        } else {
            $ord = ' <abbr title="' . $l['w_descent'] . '">&lt;</abbr>';
        }
    }

    return (
        '<th scope="col"><a href="'
        . $server['SCRIPT_NAME']
        . '?'
        . $filter
        . '&amp;firstrow=0&amp;order_field='
        . $order_field
        . '&amp;orderdir='
        . $orderdir_value
        . '" title="'
        . $title
        . '">'
        . $name
        . '</a>'
        . $ord
        . '</th>'
        . "\n"
    );
}

/**
 * Get a black or white color that maximize contrast.
 * @param $color (string) color in HEX format.
 * @return (string) Color.
 */
function get_contrast_color(mixed $color): string
{
    $color = f_general_string($color);
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    // brightness of the selected color
    $br = ((299 * $r) + (587 * $g) + (114 * $b)) / 1000;
    if ($br < 128) {
        // white
        return 'ffffff';
    }

    // black
    return '000000';
}

/**
 * Returns true if the string is an URL.
 * @param $str (string) String to check.
 * @return boolean true or false.
 */
function f_is_url(mixed $str): bool
{
    $str = f_general_string($str);
    return (int) preg_match('/^(ftp|http|https|mail|sftp|ssh|telnet|vnc)[:][\/][\/]/', $str) > 0 && parse_url($str) !== false;
}

/**
 * Normalize the UTF-8 input string.
 * Modes greater than 0 requires php5-intl module.
 * Please edit this function to implement your custom normalization method.
 * @param $str (string) UTF-8 string to normalize.
 * @param $mode (int) Normalization type: NONE=None; C=Normalization Form C (NFC) - Canonical Decomposition followed by Canonical Composition; D=Normalization Form D (NFD) - Canonical Decomposition; KC=Normalization Form KC (NFKC) - Compatibility Decomposition, followed by Canonical Composition; KD=Normalization Form KD (NFKD) - Compatibility Decomposition; CUSTOM=Custom normalization using user defined function 'user_utf8_custom_normalizer'.
 * @return string|false normalized string, or false when the selected algorithm fails.
 */
function f_utf8_normalizer(mixed $str, mixed $mode = 'NONE'): string|false
{
    $str = f_general_string($str);
    $mode = f_general_string($mode);
    switch ($mode) {
        case 'CUSTOM':
            if (function_exists('user_utf8_custom_normalizer')) {
                $normalize_custom = static fn (mixed $normalized): string|false => is_string($normalized)
                    || $normalized === false
                    ? $normalized
                    : $str;
                return $normalize_custom(call_user_func('user_utf8_custom_normalizer', $str));
            }

            return $str;

        case 'C':
            // Normalization Form C (NFC) - Canonical Decomposition followed by Canonical Composition
            return normalizer_normalize($str, 16);
        case 'D':
            // Normalization Form D (NFD) - Canonical Decomposition
            return normalizer_normalize($str, 4);
        case 'KC':
            // Normalization Form KC (NFKC) - Compatibility Decomposition, followed by Canonical Composition
            return normalizer_normalize($str, 32);
        case 'KD':
            // Normalization Form KD (NFKD) - Compatibility Decomposition
            return normalizer_normalize($str, 8);
        case 'NONE':
        default:
            return $str;
    }
}

/**
 * Convert an long integer number to a Hexadecimal representation
 * @param string|int $dec Decimal number to convert.
 * @return string containing the HEX representation in uppercase.
 * @author Nicola Asuni
 * @since 2013-07-02
 */
function bcdechex(mixed $dec): string
{
    $dec = f_general_string($dec);
    /** @var numeric-string $dec */
    $last = bcmod($dec, '16');
    $remain = bcdiv(bcsub($dec, $last), '16');
    if ($remain === '0') {
        return strtoupper(dechex((int) $last));
    }

    return bcdechex($remain) . strtoupper(dechex((int) $last));
}
