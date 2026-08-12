<?php

// Static-analysis declarations for the removed PHP 5 MySQL extension.
// This file is parsed through Mago's source.includes and is never loaded at runtime.

/** @param resource $link_identifier */
function mysql_close(mixed $link_identifier): bool
{
}

/** @param resource $result */
function mysql_num_rows(mixed $result): int
{
}

/** @param resource $link_identifier */
function mysql_affected_rows(mixed $link_identifier): int
{
}

/** @param resource $link_identifier */
function mysql_errno(mixed $link_identifier): int
{
}

/** @param resource $link_identifier */
function mysql_error(mixed $link_identifier): string
{
}

/**
 * @param resource $result
 * @return array<int|string, mixed>|false
 */
function mysql_fetch_array(mixed $result): array|false
{
}
