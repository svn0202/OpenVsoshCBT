<?php

// Static-analysis declarations for the optional OCI8 extension.
// This file is parsed through Mago's source.includes and is never loaded at runtime.

const OCI_NO_AUTO_COMMIT = 0;
const OCI_COMMIT_ON_SUCCESS = 32;
const OCI_BOTH = 3;
const OCI_RETURN_NULLS = 4;
const OCI_RETURN_LOBS = 8;
const OCI_NUM = 2;

/** @return object|resource|false */
function oci_connect(
    string $username,
    string $password,
    ?string $connection_string = null,
    string $encoding = '',
): mixed {
}

function oci_close(mixed $connection): bool
{
}

/** @return array{code:int,message:string}|false */
function oci_error(mixed $connection = null): array|false
{
}

function oci_commit(mixed $connection): bool
{
}

function oci_rollback(mixed $connection): bool
{
}

/** @return object|resource|false */
function oci_parse(mixed $connection, mixed $sql): mixed
{
}

function oci_execute(mixed $statement, int $mode = OCI_COMMIT_ON_SUCCESS): bool
{
}

/** @return array<array-key,int|string|null>|false */
function oci_fetch_array(mixed $statement, int $mode = OCI_BOTH): array|false
{
}

/** @return array<string,string|null>|false */
function oci_fetch_assoc(mixed $statement): array|false
{
}

function oci_num_rows(mixed $statement): int
{
}

/** @param array<string,list<int|string|null>> $output */
function oci_fetch_all(mixed $statement, array &$output): int|false
{
}
