<?php

// Validate before authorization can change the session or render any output.
// Every non-empty POST reaching the shared form controller is state-changing or participates in
// a state-changing workflow. Validate it independently of the button name: several controllers
// use custom actions (backup, restore, lock, unlock, exam navigation, and others) that are not in
// the legacy menu_mode list above.
if (
    PHP_SAPI !== 'cli'
    && strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && $_POST !== []
    && (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !check_csrf_token($_POST['csrf_token'])
    )
) {
    require_once __DIR__ . '/tce_functions_auth_log.php';
    $csrf_reason = match (true) {
        !isset($_POST['csrf_token']) || $_POST['csrf_token'] === '' => 'missing_token',
        !is_string($_POST['csrf_token']) => 'malformed_token',
        default => 'token_mismatch',
    };
    openvsosh_log_auth_event('csrf.rejected', $csrf_reason);
    // Reject the stale form without replaying its POST. Browser navigations get a
    // fresh login page; API requests keep their explicit failure response.
    $script_name = $_SERVER['SCRIPT_NAME'];
    $fetch_dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (
        str_starts_with($script_name, '/public/code/')
        && (($_POST['logaction'] ?? '') === 'login'
            || in_array(basename($script_name), ['index.php', 'tce_login.php'], true))
        && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
        && (
            in_array($fetch_dest, ['document', 'iframe'], true)
            || ($fetch_dest === '' && str_contains($accept, 'text/html'))
        )
    ) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        // A unique GET URL also bypasses previously cached login responses.
        header('Location: /public/code/tce_login.php?login_error=expired_form&fresh='
            . bin2hex(random_bytes(16)), true, 303);
        exit();
    }
    http_response_code(403);
    if (basename($script_name) === 'tce_test_execute.php') {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="ru"><meta charset="UTF-8"><title>Ответ не отправлен</title>'
            . '<h1>Ответ не отправлен</h1><p>Данные формы устарели. Скопируйте ответ ниже, '
            . 'вернитесь к заданию и повторите сохранение. Автоматический переход отключён.</p>';
        $draft = array_intersect_key($_POST, array_flip([
            'answertext', 'answpos', 'testcomment', 'testid', 'testlogid',
        ]));
        echo '<label>Отправленный ответ<textarea readonly rows="15" cols="80">'
            . htmlspecialchars((string) json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</textarea></label><p>Прикреплённые файлы нужно выбрать повторно.</p></html>';
    }
    exit();
}

