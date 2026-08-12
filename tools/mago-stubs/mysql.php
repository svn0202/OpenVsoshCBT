<?php

// Static-analysis declarations for the removed PHP 5 MySQL extension.
// This file is parsed through Mago's source.includes and is never loaded at runtime.

/** @param resource $link_identifier */
function mysql_close(mixed $link_identifier): bool
{
}
