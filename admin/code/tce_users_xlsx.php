<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_IMPORT_USERS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_users_xlsx.php';

function f_tmf_users_xlsx_send(string $bytes, string $name): never
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
    exit();
}

function f_tmf_users_xlsx_groups_for_user(int $user_id): string
{
    global $db;
    $groups = [];
    $result = F_db_query(
        'SELECT g.group_name FROM ' . K_TABLE_GROUPS . ' g'
        . ' INNER JOIN ' . K_TABLE_USERGROUP . ' ug ON ug.usrgrp_group_id=g.group_id'
        . ' WHERE ug.usrgrp_user_id=' . $user_id . ' ORDER BY g.group_name',
        $db,
    );
    while ($result && ($row = F_db_fetch_array($result))) {
        $groups[] = (string) $row['group_name'];
    }
    return implode(', ', $groups);
}

if (isset($_GET['download']) && $_GET['download'] === 'template') {
    F_tmf_users_xlsx_send(
        F_tmf_xlsx_build([[
            'name' => 'Пользователи',
            'widths' => [22, 18, 28, 20, 20, 14, 20, 22, 20, 10, 28],
            'rows' => [
                TMF_USERS_XLSX_HEADERS,
                ['student-001', 'change-me-123', 'student@example.test', 'Иван', 'Иванов',
                    '2010-05-17', 'Екатеринбург', 'A-001', '', 1, 'default'],
            ],
        ]]),
        'openvsosh-users-template.xlsx',
    );
}

if (isset($_GET['download']) && $_GET['download'] === 'export') {
    $rows = [[
        'id', 'login', 'email', 'first_name', 'last_name', 'birth_date', 'birth_place',
        'registration_number', 'ssn', 'level', 'registration_date', 'groups',
    ]];
    $sql = 'SELECT * FROM ' . K_TABLE_USERS . ' WHERE user_id>1';
    if ((int) $_SESSION['session_user_level'] < K_AUTH_ADMINISTRATOR) {
        $sql .= ' AND user_level<' . (int) $_SESSION['session_user_level'];
    }
    $sql .= ' ORDER BY user_lastname,user_firstname,user_name';
    $result = F_db_query($sql, $db);
    while ($result && ($user = F_db_fetch_array($result))) {
        $rows[] = [
            ['value' => (int) $user['user_id'], 'type' => 'number'],
            $user['user_name'],
            $user['user_email'],
            $user['user_firstname'],
            $user['user_lastname'],
            substr((string) ($user['user_birthdate'] ?? ''), 0, 10),
            $user['user_birthplace'],
            $user['user_regnumber'],
            $user['user_ssn'],
            ['value' => (int) $user['user_level'], 'type' => 'number'],
            $user['user_regdate'],
            F_tmf_users_xlsx_groups_for_user((int) $user['user_id']),
        ];
    }
    F_tmf_users_xlsx_send(
        F_tmf_xlsx_build([[
            'name' => 'Пользователи',
            'widths' => [10, 22, 28, 20, 20, 14, 20, 22, 20, 10, 22, 28],
            'rows' => $rows,
        ]]),
        'openvsosh-users-' . date('Ymd-His') . '.xlsx',
    );
}

$message = '';
$preview = null;
if (isset($_POST['xlsx_action'])) {
    if (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !check_csrf_token($_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit();
    }
    if ($_POST['xlsx_action'] === 'preview') {
        try {
            $xlsx_file = $_FILES['xlsx_file'] ?? null;
            if (
                !is_array($xlsx_file)
                || (int) ($xlsx_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file((string) ($xlsx_file['tmp_name'] ?? ''))
            ) {
                throw new RuntimeException('Выберите XLSX-файл без ошибок загрузки.');
            }
            $temporary = (string) $xlsx_file['tmp_name'];
            $signature = file_get_contents($temporary, false, null, 0, 4);
            if ($signature !== "PK\x03\x04") {
                throw new RuntimeException('Файл не является XLSX-архивом.');
            }
            $groups = [];
            $group_result = F_db_query(
                'SELECT group_id,group_name FROM ' . K_TABLE_GROUPS . ' ORDER BY group_name',
                $db,
            );
            while ($group_result && ($group = F_db_fetch_array($group_result))) {
                $groups[mb_strtolower((string) $group['group_name'])] = (int) $group['group_id'];
            }
            $logins = [];
            $registration_numbers = [];
            $ssns = [];
            $login_result = F_db_query(
                'SELECT user_name,user_regnumber,user_ssn FROM ' . K_TABLE_USERS,
                $db,
            );
            while ($login_result && ($user = F_db_fetch_array($login_result))) {
                $logins[mb_strtolower((string) $user['user_name'])] = true;
                if (!empty($user['user_regnumber'])) {
                    $registration_numbers[mb_strtolower((string) $user['user_regnumber'])] = true;
                }
                if (!empty($user['user_ssn'])) {
                    $ssns[mb_strtolower((string) $user['user_ssn'])] = true;
                }
            }
            $preview = F_tmf_users_xlsx_validate(
                F_tmf_xlsx_read($temporary),
                $logins,
                $groups,
                (int) $_SESSION['session_user_level'] >= K_AUTH_ADMINISTRATOR
                    ? K_AUTH_ADMINISTRATOR
                    : max(1, (int) $_SESSION['session_user_level'] - 1),
                $registration_numbers,
                $ssns,
            );
            if ($preview['errors'] === [] && $preview['records'] !== []) {
                $token = bin2hex(random_bytes(24));
                $_SESSION['tmf_users_xlsx_preview'] = [
                    'token' => $token,
                    'created_at' => time(),
                    'records' => $preview['records'],
                ];
                $preview['token'] = $token;
            } else {
                unset($_SESSION['tmf_users_xlsx_preview']);
            }
        } catch (Throwable $exception) {
            $preview = ['records' => [], 'errors' => [1 => [$exception->getMessage()]]];
            unset($_SESSION['tmf_users_xlsx_preview']);
        }
    } elseif ($_POST['xlsx_action'] === 'import') {
        $pending = $_SESSION['tmf_users_xlsx_preview'] ?? null;
        unset($_SESSION['tmf_users_xlsx_preview']);
        if (
            !is_array($pending)
            || time() - (int) ($pending['created_at'] ?? 0) > 900
            || !hash_equals((string) ($pending['token'] ?? ''), (string) ($_POST['preview_token'] ?? ''))
        ) {
            $message = 'Предпросмотр истёк. Загрузите файл повторно.';
        } else {
            try {
                $count = F_tmf_users_xlsx_import((array) $pending['records']);
                $message = 'Импорт завершён. Создано пользователей: ' . $count . '.';
            } catch (Throwable $exception) {
                $message = 'Импорт отменён целиком: ' . $exception->getMessage();
            }
        }
    }
}

$thispage_title = 'Импорт и экспорт пользователей XLSX';
require_once 'tce_page_header.php';

function f_tmf_users_xlsx_html(mixed $value): string
{
    global $l;
    return htmlspecialchars((string) $value, ENT_QUOTES, $l['a_meta_charset']);
}

echo '<div class="container"><div class="tceformbox">';
echo '<p><a class="xmlbutton" href="?download=template">Скачать шаблон XLSX</a> '
    . '<a class="xmlbutton" href="?download=export">Экспортировать пользователей XLSX</a></p>';
echo '<p class="pagehelp">Экспорт не содержит паролей. Импорт создаёт только новых пользователей; '
    . 'весь файл сначала проверяется и показывается без записи в базу.</p>';
if ($message !== '') {
    echo '<p role="status">' . F_tmf_users_xlsx_html($message) . '</p>';
}
echo '<form method="post" enctype="multipart/form-data" action="tce_users_xlsx.php">'
    . '<div class="row"><span class="label"><label for="xlsx_file">XLSX-файл</label></span>'
    . '<span class="formw"><input type="file" name="xlsx_file" id="xlsx_file" '
    . 'accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required="required" />'
    . '</span></div><div class="row"><button type="submit" name="xlsx_action" value="preview">'
    . 'Проверить и показать</button>' . F_getCSRFTokenField() . '</div></form>';

if (is_array($preview)) {
    if ($preview['errors'] !== []) {
        echo '<h2>Ошибки</h2><table><thead><tr><th>Строка</th><th>Причина</th></tr></thead><tbody>';
        foreach ($preview['errors'] as $row => $errors) {
            echo '<tr><td>' . (int) $row . '</td><td>' . F_tmf_users_xlsx_html(implode(' ', $errors))
                . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<h2>Предпросмотр</h2><table><thead><tr><th>Строка</th><th>Логин</th>'
            . '<th>Имя</th><th>Уровень</th><th>Группы</th></tr></thead><tbody>';
        foreach ($preview['records'] as $row => $record) {
            echo '<tr><td>' . (int) $row . '</td><td>' . F_tmf_users_xlsx_html($record['login'])
                . '</td><td>' . F_tmf_users_xlsx_html(trim($record['last_name'] . ' ' . $record['first_name']))
                . '</td><td>' . (int) $record['level'] . '</td><td>'
                . F_tmf_users_xlsx_html(implode(', ', $record['group_names'])) . '</td></tr>';
        }
        echo '</tbody></table><form method="post" action="tce_users_xlsx.php">'
            . '<input type="hidden" name="preview_token" value="'
            . F_tmf_users_xlsx_html($preview['token']) . '" />'
            . '<button type="submit" name="xlsx_action" value="import">Импортировать '
            . count($preview['records']) . '</button>' . F_getCSRFTokenField() . '</form>';
    }
}
echo '</div></div>';

require_once 'tce_page_footer.php';
