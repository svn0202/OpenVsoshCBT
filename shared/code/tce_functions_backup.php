<?php

/**
 * Checked, shell-free database backup and restore helpers.
 */

require_once __DIR__ . '/TmfBackupException.php';

/**
 * @param array{type:string,password:string,...<string,string>} $config
 * @return array<string,string>
 */
function f_tmf_backup_environment(array $config): array
{
    $environment = getenv();
    if ($config['type'] === 'POSTGRESQL') {
        $environment['PGPASSWORD'] = $config['password'];
    } else {
        $environment['MYSQL_PWD'] = $config['password'];
    }
    return $environment;
}

/**
 * @param list<string>         $command
 * @param array<string,string> $environment
 * @param array<array-key,mixed> $pipes
 * @return resource
 * @throws TmfBackupException When the database process cannot be started.
 */
function f_tmf_backup_start_process(
    array $command,
    array $environment,
    array &$pipes,
    ?string $stdin_file = null,
): mixed {
    $error_file = tempnam(sys_get_temp_dir(), 'openvsosh-db-command-');
    if ($error_file === false) {
        throw new TmfBackupException('Не удалось создать файл диагностики команды БД.');
    }
    $descriptors = [
        0 => $stdin_file === null ? ['file', '/dev/null', 'r'] : ['file', $stdin_file, 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $error_file, 'a'],
    ];
    $launch_error = '';
    set_error_handler(static function (int $_severity, string $message) use (&$launch_error): bool {
        $launch_error = $message;
        return true;
    });
    try {
        // @mago-expect analysis:possibly-invalid-argument -- proc_open validates an optional empty input path as before
        $process = proc_open($command, $descriptors, $pipes, null, $environment);
    } finally {
        restore_error_handler();
    }
    if (!is_resource($process)) {
        unlink($error_file);
        $message = 'Не удалось запустить утилиту резервного копирования.';
        if ($launch_error !== '') {
            $message .= ' ' . $launch_error;
        }
        throw new TmfBackupException($message);
    }
    $pipes['_tmf_error_file'] = $error_file;
    return $process;
}

/**
 * @param resource             $process
 * @param array<mixed>         $pipes
 * @param resource|null        $output
 * @throws TmfBackupException When the database utility or archive stream fails.
 */
function f_tmf_backup_finish_process(
    mixed $process,
    array $pipes,
    mixed $output = null,
    bool $allow_postgresql_version_mismatch = false,
): void
{
    if (isset($pipes[1]) && is_resource($pipes[1])) {
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 65_536);
            if ($chunk === false) {
                break;
            }
            if ($output !== null && $chunk !== '' && gzwrite($output, $chunk) === false) {
                fclose($pipes[1]);
                proc_terminate($process);
                proc_close($process);
                $error_file = isset($pipes['_tmf_error_file'])
                    ? (string) $pipes['_tmf_error_file']
                    : '';
                if ($error_file !== '' && is_file($error_file)) {
                    unlink($error_file);
                }
                throw new TmfBackupException('Не удалось записать резервную копию.');
            }
        }
        fclose($pipes[1]);
    }
    $status = proc_close($process);
    $error_file = isset($pipes['_tmf_error_file']) ? (string) $pipes['_tmf_error_file'] : '';
    $diagnostic = $error_file !== '' && is_file($error_file)
        ? trim((string) file_get_contents($error_file))
        : '';
    if ($error_file !== '' && is_file($error_file)) {
        unlink($error_file);
    }
    if (
        $status !== 0
        && (!$allow_postgresql_version_mismatch
            || !F_tmf_backup_ignorable_postgresql_restore_diagnostic($diagnostic))
    ) {
        $message = 'Утилита БД завершилась с кодом ' . $status . '.';
        if ($diagnostic !== '') {
            $message .= ' ' . mb_substr($diagnostic, 0, 1000);
        }
        throw new TmfBackupException($message);
    }
}

/**
 * PostgreSQL 17 pg_dump writes this harmless setting into archives even when
 * the target server is PostgreSQL 16, where the setting does not exist.
 * pg_restore continues successfully but returns 1. Ignore only that exact,
 * single-error diagnostic; every other restore error remains fatal.
 */
function f_tmf_backup_ignorable_postgresql_restore_diagnostic(string $diagnostic): bool
{
    return preg_match(
        '/\Apg_restore: error: could not execute query: ERROR:\s+'
        . 'unrecognized configuration parameter "transaction_timeout"\R'
        . 'Command was: SET transaction_timeout = 0;\R'
        . 'pg_restore: warning: errors ignored on restore: 1\z/',
        $diagnostic,
    ) === 1;
}

/**
 * @param array{type:string,host:string,port:string,user:string,name:string,pg_dump_binary?:string,
 *     mysqldump_binary?:string,...<string,string>} $config
 * @return list<string>
 */
function f_tmf_backup_dump_command(array $config): array
{
    if ($config['type'] === 'POSTGRESQL') {
        return [
            $config['pg_dump_binary'] ?? 'pg_dump',
            '-h', $config['host'],
            '-p', $config['port'],
            '-U', $config['user'],
            '-Ft',
            $config['name'],
        ];
    }
    return [
        $config['mysqldump_binary'] ?? 'mysqldump',
        '--opt',
        '-h', $config['host'],
        '-P', $config['port'],
        '-u', $config['user'],
        $config['name'],
    ];
}

/**
 * @param array{type:string,host:string,port:string,user:string,name:string,pg_restore_binary?:string,
 *     mysql_binary?:string,...<string,string>} $config
 * @return list<string>
 */
function f_tmf_backup_restore_command(array $config): array
{
    if ($config['type'] === 'POSTGRESQL') {
        return [
            $config['pg_restore_binary'] ?? 'pg_restore',
            '--clean',
            '--if-exists',
            '-h', $config['host'],
            '-p', $config['port'],
            '-U', $config['user'],
            '-d', $config['name'],
            '-Ft',
        ];
    }
    return [
        $config['mysql_binary'] ?? 'mysql',
        '-h', $config['host'],
        '-P', $config['port'],
        '-u', $config['user'],
        $config['name'],
    ];
}

/**
 * @param array{type:string,host:string,port:string,user:string,name:string,password:string,
 *     pg_dump_binary?:string,mysqldump_binary?:string,...<string,string>} $config
 * @throws TmfBackupException When validation, process execution, or archive publication fails.
 * @throws Throwable When an underlying operation fails after cleanup.
 */
function f_tmf_backup_create(array $config, string $backup_directory, ?string $timestamp = null): string
{
    $timestamp_is_fixed = $timestamp !== null;
    $timestamp ??= date('YmdHis');
    if (preg_match('/^\d{14}$/D', $timestamp) !== 1) {
        throw new TmfBackupException('Некорректная метка времени резервной копии.');
    }
    if (!is_dir($backup_directory) || !is_writable($backup_directory)) {
        throw new TmfBackupException('Каталог резервных копий недоступен для записи.');
    }
    $extension = $config['type'] === 'POSTGRESQL' ? 'tar' : 'sql';
    $partial_path = tempnam($backup_directory, '.openvsosh-backup-');
    if ($partial_path === false) {
        throw new TmfBackupException('Не удалось создать временный файл резервной копии.');
    }
    $archive = gzopen($partial_path, 'wb9');
    if ($archive === false) {
        unlink($partial_path);
        throw new TmfBackupException('Не удалось открыть файл резервной копии.');
    }

    try {
        $pipes = [];
        $process = F_tmf_backup_start_process(
            F_tmf_backup_dump_command($config),
            F_tmf_backup_environment($config),
            $pipes,
        );
        F_tmf_backup_finish_process($process, $pipes, $archive);
    } catch (Throwable $exception) {
        gzclose($archive);
        if (is_file($partial_path)) {
            unlink($partial_path);
        }
        throw $exception;
    }
    gzclose($archive);
    if (!is_file($partial_path) || filesize($partial_path) === 0) {
        if (is_file($partial_path)) {
            unlink($partial_path);
        }
        throw new TmfBackupException('Утилита БД создала пустую резервную копию.');
    }

    $base_time = DateTimeImmutable::createFromFormat('!YmdHis', $timestamp);
    if ($base_time === false) {
        unlink($partial_path);
        throw new TmfBackupException('Некорректная метка времени резервной копии.');
    }
    $attempts = $timestamp_is_fixed ? 1 : 60;
    for ($offset = 0; $offset < $attempts; ++$offset) {
        $candidate_timestamp = $base_time->modify('+' . $offset . ' seconds')->format('YmdHis');
        $path = rtrim($backup_directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $candidate_timestamp . '_tcexam_backup.' . $extension . '.gz';
        // A hard link publishes the complete archive atomically and never overwrites an existing one.
        set_error_handler(static fn (): bool => true);
        try {
            $published = link($partial_path, $path);
        } finally {
            restore_error_handler();
        }
        if ($published) {
            unlink($partial_path);
            return $path;
        }
    }
    unlink($partial_path);
    throw new TmfBackupException('Резервная копия с такой меткой времени уже существует.');
}

function f_tmf_backup_file_is_valid(string $filename): bool
{
    return preg_match('/^\d{14}_tcexam_backup\.(?:sql|tar)\.gz$/D', $filename) === 1;
}

/** @throws TmfBackupException When the requested archive path is invalid or unsafe. */
function f_tmf_backup_resolve_file(string $backup_directory, string $filename): string
{
    if (!F_tmf_backup_file_is_valid($filename)) {
        throw new TmfBackupException('Некорректное имя резервной копии.');
    }
    $directory = realpath($backup_directory);
    $path = realpath(rtrim($backup_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename);
    if (
        $directory === false
        || $path === false
        || dirname($path) !== $directory
        || !is_file($path)
        || is_link(rtrim($backup_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename)
    ) {
        throw new TmfBackupException('Файл резервной копии не найден или расположен вне каталога.');
    }
    return $path;
}

/**
 * @param array{type:string,host:string,port:string,user:string,name:string,password:string,
 *     pg_restore_binary?:string,mysql_binary?:string,...<string,string>} $config
 */
function f_tmf_backup_restore(array $config, string $archive_path): void
{
    if (!is_file($archive_path) || !is_readable($archive_path)) {
        // @mago-expect analysis:unhandled-thrown-type -- restore failures use the established exception API
        throw new TmfBackupException('Резервная копия недоступна для чтения.');
    }
    $temporary = tempnam(sys_get_temp_dir(), 'openvsosh-db-restore-');
    if ($temporary === false) {
        throw new TmfBackupException('Не удалось создать временный файл восстановления.');
    }
    $source = gzopen($archive_path, 'rb');
    $destination = fopen($temporary, 'wb');
    if ($source === false || $destination === false) {
        if (is_resource($source)) {
            gzclose($source);
        }
        if (is_resource($destination)) {
            fclose($destination);
        }
        unlink($temporary);
        throw new TmfBackupException('Резервная копия повреждена или недоступна.');
    }
    try {
        while (!gzeof($source)) {
            $chunk = gzread($source, 65_536);
            if ($chunk === false || ($chunk !== '' && fwrite($destination, $chunk) === false)) {
                throw new TmfBackupException('Не удалось распаковать резервную копию.');
            }
        }
        gzclose($source);
        fclose($destination);
        $source = null;
        $destination = null;

        $command = F_tmf_backup_restore_command($config);
        $stdin_file = null;
        if ($config['type'] === 'POSTGRESQL') {
            $command[] = $temporary;
        } else {
            $stdin_file = $temporary;
        }
        $pipes = [];
        $process = F_tmf_backup_start_process(
            $command,
            F_tmf_backup_environment($config),
            $pipes,
            $stdin_file,
        );
        F_tmf_backup_finish_process(
            $process,
            $pipes,
            null,
            $config['type'] === 'POSTGRESQL',
        );
    } finally {
        if (is_resource($source)) {
            gzclose($source);
        }
        if (is_resource($destination)) {
            fclose($destination);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

/**
 * @return array{type:string,host:string,port:string,name:string,user:string,password:string}
 */
function f_tmf_backup_config_from_constants(): array
{
    return [
        'type' => K_DATABASE_TYPE,
        'host' => K_DATABASE_HOST,
        'port' => K_DATABASE_PORT,
        'name' => K_DATABASE_NAME,
        'user' => K_DATABASE_USER_NAME,
        'password' => K_DATABASE_USER_PASSWORD,
    ];
}
