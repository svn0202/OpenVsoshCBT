<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("migrate.php is a command-line tool and cannot be run over the web.\n");
}

require_once __DIR__ . '/tce_functions_migrate.php';

$options = getopt('', ['baseline', 'dry-run']);
$baseline = isset($options['baseline']);
$dry_run = isset($options['dry-run']);
$database_type = strtoupper((string) (getenv('TCEXAM_DB_TYPE') ?: 'MYSQL'));
$dialects = [
    'MYSQL' => ['dir' => 'mysql', 'dal' => 'mysqli', 'port' => '3306'],
    'POSTGRESQL' => ['dir' => 'postgresql', 'dal' => 'postgresql', 'port' => '5432'],
    'ORACLE' => ['dir' => 'oracle', 'dal' => 'oracle', 'port' => '1521'],
];
if (!isset($dialects[$database_type])) {
    fwrite(STDERR, "[openvsosh-migrate] Unsupported TCEXAM_DB_TYPE.\n");
    exit(2);
}
$dialect = $dialects[$database_type];
require_once __DIR__ . '/../shared/code/tce_db_dal_' . $dialect['dal'] . '.php';

$db = F_db_connect(
    (string) (getenv('TCEXAM_DB_HOST') ?: 'localhost'),
    (string) (getenv('TCEXAM_DB_PORT') ?: $dialect['port']),
    (string) (getenv('TCEXAM_DB_USER') ?: 'root'),
    (string) (getenv('TCEXAM_DB_PASSWORD') ?: ''),
    (string) (getenv('TCEXAM_DB_NAME') ?: 'tcexam'),
);
if (!$db) {
    fwrite(STDERR, "[openvsosh-migrate] Database connection failed.\n");
    exit(3);
}
$prefix = (string) (getenv('TCEXAM_TABLE_PREFIX') ?: 'tce_');
$table = $prefix . 'schema_migrations';
$create = match ($database_type) {
    'MYSQL' => 'CREATE TABLE IF NOT EXISTS ' . $table
        . ' (migration_name VARCHAR(191) NOT NULL PRIMARY KEY,migration_sha256 CHAR(64) NOT NULL,'
        . 'migration_applied_at DATETIME NOT NULL,migration_mode VARCHAR(16) NOT NULL)',
    'POSTGRESQL' => 'CREATE TABLE IF NOT EXISTS ' . $table
        . ' (migration_name VARCHAR(191) NOT NULL PRIMARY KEY,migration_sha256 CHAR(64) NOT NULL,'
        . 'migration_applied_at TIMESTAMP NOT NULL,migration_mode VARCHAR(16) NOT NULL)',
    default => 'CREATE TABLE ' . $table
        . ' (migration_name VARCHAR2(191) NOT NULL PRIMARY KEY,migration_sha256 CHAR(64) NOT NULL,'
        . 'migration_applied_at DATE NOT NULL,migration_mode VARCHAR2(16) NOT NULL)',
};
if ($database_type === 'ORACLE') {
    @F_db_query($create, $db);
} elseif (!F_db_query($create, $db)) {
    fwrite(STDERR, "[openvsosh-migrate] Cannot create migration journal.\n");
    exit(4);
}

$applied = [];
$result = F_db_query('SELECT migration_name,migration_sha256 FROM ' . $table, $db);
while ($result && ($row = F_db_fetch_array($result))) {
    $applied[(string) $row['migration_name']] = (string) $row['migration_sha256'];
}
$files = F_tmf_migration_files(__DIR__ . '/upgrade/' . $dialect['dir']);
$pending = 0;
foreach ($files as $path) {
    $name = basename($path);
    $sql = (string) file_get_contents($path);
    $sha = hash('sha256', $sql);
    if (isset($applied[$name])) {
        if (!hash_equals($applied[$name], $sha)) {
            fwrite(STDERR, "[openvsosh-migrate] Applied migration changed: {$name}\n");
            exit(5);
        }
        fwrite(STDOUT, "[openvsosh-migrate] already applied {$name}\n");
        continue;
    }
    ++$pending;
    if ($dry_run) {
        fwrite(STDOUT, "[openvsosh-migrate] pending {$name}\n");
        continue;
    }
    if (!$baseline) {
        if (!F_db_query('START TRANSACTION', $db)) {
            fwrite(STDERR, "[openvsosh-migrate] Cannot start {$name}\n");
            exit(6);
        }
        foreach (F_tmf_migration_statements($sql, $database_type) as $statement) {
            if (!F_db_query(str_replace('tce_', $prefix, $statement), $db)) {
                F_db_query('ROLLBACK', $db);
                fwrite(STDERR, "[openvsosh-migrate] Failed {$name}\n");
                exit(7);
            }
        }
    }
    $mode = $baseline ? 'baseline' : 'applied';
    $insert = 'INSERT INTO ' . $table
        . " (migration_name,migration_sha256,migration_applied_at,migration_mode) VALUES ('"
        . F_escape_sql($db, $name) . "','" . $sha . "',"
        . ($database_type === 'ORACLE' ? 'CURRENT_TIMESTAMP' : 'CURRENT_TIMESTAMP')
        . ",'" . $mode . "')";
    $journaled = F_db_query($insert, $db);
    $committed = $journaled && ($baseline
        ? ($database_type !== 'ORACLE' || F_db_query('COMMIT', $db))
        : F_db_query('COMMIT', $db));
    if (!$journaled || !$committed) {
        if (!$baseline) {
            F_db_query('ROLLBACK', $db);
        }
        fwrite(STDERR, "[openvsosh-migrate] Cannot journal {$name}\n");
        exit(8);
    }
    fwrite(STDOUT, "[openvsosh-migrate] {$mode} {$name}\n");
}
F_db_close($db);
fwrite(STDOUT, "[openvsosh-migrate] complete; pending handled: {$pending}\n");
