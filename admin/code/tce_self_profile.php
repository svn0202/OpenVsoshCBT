<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_OPERATOR;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';

$thispage_title = 'Мой профиль';
require_once 'tce_page_header.php';

$user_id = (int) $_SESSION['session_user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    if (empty($_POST['csrf_token']) || !checkCSRFToken((string) $_POST['csrf_token'])) {
        http_response_code(403);
        exit();
    }
    $firstname = trim((string) ($_POST['user_firstname'] ?? ''));
    $lastname = trim((string) ($_POST['user_lastname'] ?? ''));
    $password = (string) ($_POST['currentpassword'] ?? '');
    if (mb_strlen($firstname) > 255 || mb_strlen($lastname) > 255) {
        F_print_error('WARNING', 'Имя и фамилия не должны превышать 255 символов.');
    } else {
        $result = F_db_query(
            'SELECT user_password FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id . ' LIMIT 1',
            $db,
        );
        $user = $result ? F_db_fetch_array($result) : false;
        if (!$user || !checkPassword($password, (string) $user['user_password'])) {
            F_print_error('WARNING', $l['m_login_wrong']);
        } else {
            $sql = 'UPDATE ' . K_TABLE_USERS . "
                SET user_firstname='" . F_escape_sql($db, $firstname) . "',
                    user_lastname='" . F_escape_sql($db, $lastname) . "'
                WHERE user_id=" . $user_id;
            if (F_db_query($sql, $db)) {
                $_SESSION['session_user_firstname'] = urlencode($firstname);
                $_SESSION['session_user_lastname'] = urlencode($lastname);
                F_print_error('MESSAGE', 'Профиль обновлён.');
            } else {
                F_display_db_error(false);
            }
        }
    }
}

$result = F_db_query(
    'SELECT user_name,user_email,user_firstname,user_lastname,user_level
    FROM ' . K_TABLE_USERS . ' WHERE user_id=' . $user_id . ' LIMIT 1',
    $db,
);
$user = $result ? F_db_fetch_array($result) : [];
$groups = [];
$group_result = F_db_query(
    'SELECT g.group_name
    FROM ' . K_TABLE_GROUPS . ' g
    INNER JOIN ' . K_TABLE_USERGROUP . ' ug ON ug.usrgrp_group_id=g.group_id
    WHERE ug.usrgrp_user_id=' . $user_id . '
    ORDER BY g.group_name',
    $db,
);
while ($group_result && ($group = F_db_fetch_array($group_result))) {
    $groups[] = (string) $group['group_name'];
}

echo '<div class="container"><h1>Мой профиль</h1>' . K_NEWLINE;
echo '<form action="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
    . '" method="post"><fieldset><legend>Основные данные</legend>' . K_NEWLINE;
echo '<p><strong>Логин:</strong> '
    . htmlspecialchars((string) ($user['user_name'] ?? ''), ENT_QUOTES, $l['a_meta_charset'])
    . '</p><p><strong>Уровень:</strong> ' . (int) ($user['user_level'] ?? 0) . '</p>' . K_NEWLINE;
echo '<p><strong>Группы:</strong> '
    . htmlspecialchars($groups === [] ? '—' : implode(', ', $groups), ENT_QUOTES, $l['a_meta_charset'])
    . '</p>' . K_NEWLINE;
echo getFormRowTextInput(
    'user_firstname',
    $l['w_firstname'],
    '',
    '',
    (string) ($user['user_firstname'] ?? ''),
    '',
    255,
);
echo getFormRowTextInput(
    'user_lastname',
    $l['w_lastname'],
    '',
    '',
    (string) ($user['user_lastname'] ?? ''),
    '',
    255,
);
echo getFormRowTextInput(
    'currentpassword',
    $l['w_current_password'],
    $l['h_password'],
    '',
    '',
    '',
    255,
    false,
    false,
    true,
    '',
    true,
    'current-password',
);
echo '</fieldset><p><button type="submit" name="save_profile" value="1">Сохранить профиль</button></p>'
    . F_getCSRFTokenField() . '</form>' . K_NEWLINE;
echo '<p><a href="../../public/code/tce_user_change_email.php">' . $l['w_change_email'] . '</a> · '
    . '<a href="../../public/code/tce_user_change_password.php">' . $l['w_change_password'] . '</a></p>';
echo '</div>' . K_NEWLINE;

require_once 'tce_page_footer.php';

