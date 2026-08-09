<?php

require_once __DIR__ . '/tce_functions_xlsx.php';

const TMF_USERS_XLSX_HEADERS = [
    'login',
    'password',
    'email',
    'first_name',
    'last_name',
    'birth_date',
    'birth_place',
    'registration_number',
    'ssn',
    'level',
    'groups',
];

/**
 * Validate imported rows without changing the database.
 *
 * @param array<int,array<int,string>> $rows
 * @param array<string,bool> $existing_logins
 * @param array<string,int> $available_groups
 * @return array{records:array<int,array<string,mixed>>,errors:array<int,array<int,string>>}
 */
function f_tmf_users_xlsx_validate(
    array $rows,
    array $existing_logins,
    array $available_groups,
    int $maximum_level,
    array $existing_registration_numbers = [],
    array $existing_ssns = [],
): array {
    $records = [];
    $errors = [];
    if ($rows === []) {
        return ['records' => [], 'errors' => [1 => ['Файл пуст.']]];
    }
    $headers = array_map(
        static fn (mixed $value): string => strtolower(trim($value)),
        $rows[0],
    );
    if ($headers !== TMF_USERS_XLSX_HEADERS) {
        return [
            'records' => [],
            'errors' => [1 => [
                'Заголовки не соответствуют шаблону: ' . implode(', ', TMF_USERS_XLSX_HEADERS),
            ]],
        ];
    }
    $seen = [];
    $seen_registration_numbers = [];
    $seen_ssns = [];
    foreach (array_slice($rows, 1, null, true) as $offset => $row) {
        $sheet_row = $offset + 1;
        $row = array_pad(array_values($row), count(TMF_USERS_XLSX_HEADERS), '');
        $values = array_combine(TMF_USERS_XLSX_HEADERS, array_slice($row, 0, count(TMF_USERS_XLSX_HEADERS)));
        if ($values === false || count(array_filter($values, static fn ($value): bool => trim($value) !== '')) === 0) {
            continue;
        }
        $login = trim((string) $values['login']);
        $password = (string) $values['password'];
        $email = trim((string) $values['email']);
        $level = filter_var(
            trim((string) $values['level']),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => max(1, $maximum_level)]],
        );
        $row_errors = [];
        if ($login === '' || preg_match('/^[^\s\x00-\x1F]{2,255}$/u', $login) !== 1) {
            $row_errors[] = 'Логин должен содержать от 2 до 255 символов без пробелов.';
        }
        $login_key = mb_strtolower($login);
        if (isset($existing_logins[$login_key])) {
            $row_errors[] = 'Логин уже существует.';
        }
        if (isset($seen[$login_key])) {
            $row_errors[] = 'Логин повторяется в файле (первая строка: ' . $seen[$login_key] . ').';
        } else {
            $seen[$login_key] = $sheet_row;
        }
        if (strlen($password) < 8 || strlen($password) > 1024) {
            $row_errors[] = 'Пароль должен содержать от 8 до 1024 символов.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $row_errors[] = 'Некорректный адрес электронной почты.';
        }
        if ($level === false) {
            $row_errors[] = 'Уровень должен быть целым числом от 1 до ' . max(1, $maximum_level) . '.';
        }
        $birth_date = trim((string) $values['birth_date']);
        if ($birth_date !== '' && is_numeric($birth_date)) {
            $serial = (float) $birth_date;
            if ($serial >= 1 && $serial <= 100_000) {
                $birth_date = gmdate('Y-m-d', (int) round(($serial - 25_569) * 86_400));
            }
        }
        if ($birth_date !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birth_date);
            if ($date === false || $date->format('Y-m-d') !== $birth_date) {
                $row_errors[] = 'Дата рождения должна иметь формат ГГГГ-ММ-ДД.';
            }
        }
        foreach ([
            'registration_number' => [
                'existing' => $existing_registration_numbers,
                'seen' => &$seen_registration_numbers,
                'label' => 'Регистрационный номер',
            ],
            'ssn' => [
                'existing' => $existing_ssns,
                'seen' => &$seen_ssns,
                'label' => 'SSN',
            ],
        ] as $field => &$identifier) {
            $identifier_value = trim((string) $values[$field]);
            if ($identifier_value === '') {
                continue;
            }
            $identifier_key = mb_strtolower($identifier_value);
            if (isset($identifier['existing'][$identifier_key])) {
                $row_errors[] = $identifier['label'] . ' уже существует.';
            }
            if (isset($identifier['seen'][$identifier_key])) {
                $row_errors[] = $identifier['label'] . ' повторяется в файле.';
            } else {
                $identifier['seen'][$identifier_key] = $sheet_row;
            }
        }
        unset($identifier);
        foreach ([
            'email', 'first_name', 'last_name', 'birth_place', 'registration_number', 'ssn',
        ] as $field) {
            if (mb_strlen((string) $values[$field]) > 255) {
                $row_errors[] = 'Поле ' . $field . ' длиннее 255 символов.';
            }
        }
        $group_ids = [];
        $groups = preg_split('/[,;]+/u', (string) $values['groups']);
        $group_names = array_values(array_filter(array_map(
            'trim',
            $groups === false ? [] : $groups,
        )));
        if ($group_names === []) {
            $row_errors[] = 'Укажите хотя бы одну существующую группу.';
        }
        foreach ($group_names as $group_name) {
            $group_key = mb_strtolower($group_name);
            if (!isset($available_groups[$group_key])) {
                $row_errors[] = 'Неизвестная группа: ' . $group_name . '.';
            } else {
                $group_ids[] = $available_groups[$group_key];
            }
        }
        if ($row_errors !== []) {
            $errors[$sheet_row] = $row_errors;
            continue;
        }
        $records[$sheet_row] = [
            'login' => $login,
            'password_hash' => getPasswordHash($password),
            'email' => $email,
            'first_name' => trim((string) $values['first_name']),
            'last_name' => trim((string) $values['last_name']),
            'birth_date' => $birth_date,
            'birth_place' => trim((string) $values['birth_place']),
            'registration_number' => trim((string) $values['registration_number']),
            'ssn' => trim((string) $values['ssn']),
            'level' => (int) $level,
            'group_ids' => array_values(array_unique($group_ids)),
            'group_names' => $group_names,
        ];
    }
    if ($records === [] && $errors === []) {
        $errors[2] = ['Файл не содержит строк пользователей.'];
    }
    return ['records' => $records, 'errors' => $errors];
}

/**
 * @param array<int,array<string,mixed>> $records
 */
function f_tmf_users_xlsx_import(array $records): int
{
    global $db;
    if (!F_db_query('START TRANSACTION', $db)) {
        throw new RuntimeException('Не удалось начать импорт.');
    }
    try {
        foreach ($records as $record) {
            $fields = [
                'user_regdate' => date(K_TIMESTAMP_FORMAT),
                'user_ip' => get_normalized_ip($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
                'user_name' => $record['login'],
                'user_email' => $record['email'],
                'user_password' => $record['password_hash'],
                'user_regnumber' => $record['registration_number'],
                'user_firstname' => $record['first_name'],
                'user_lastname' => $record['last_name'],
                'user_birthdate' => $record['birth_date'],
                'user_birthplace' => $record['birth_place'],
                'user_ssn' => $record['ssn'],
                'user_level' => (int) $record['level'],
            ];
            $values = [];
            foreach ($fields as $name => $value) {
                if ($value === '' && !in_array($name, ['user_ip', 'user_name', 'user_password'], true)) {
                    $values[] = 'NULL';
                } elseif ($name === 'user_level') {
                    $values[] = (string) (int) $value;
                } else {
                    $values[] = "'" . F_escape_sql($db, (string) $value) . "'";
                }
            }
            $sql = 'INSERT INTO ' . K_TABLE_USERS . ' (' . implode(',', array_keys($fields))
                . ') VALUES (' . implode(',', $values) . ')';
            if (!F_db_query($sql, $db)) {
                throw new RuntimeException('Не удалось создать пользователя ' . $record['login'] . '.');
            }
            $user_id = F_db_insert_id($db, K_TABLE_USERS, 'user_id');
            foreach ($record['group_ids'] as $group_id) {
                $sql = 'INSERT INTO ' . K_TABLE_USERGROUP
                    . ' (usrgrp_user_id,usrgrp_group_id) VALUES ('
                    . (int) $user_id . ',' . (int) $group_id . ')';
                if (!F_db_query($sql, $db)) {
                    throw new RuntimeException('Не удалось назначить группу пользователю ' . $record['login'] . '.');
                }
            }
        }
        if (!F_db_query('COMMIT', $db)) {
            throw new RuntimeException('Не удалось завершить импорт.');
        }
    } catch (Throwable $exception) {
        F_db_query('ROLLBACK', $db);
        throw $exception;
    }
    return count($records);
}
