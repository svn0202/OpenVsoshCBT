<?php

//============================================================+
// File name   : tce_upload_clipboard_image.php
// Begin       : 2026-09-02
// Description : Upload an image pasted into the rich editor.
//============================================================+

declare(strict_types=1);

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_ADMIN_FILEMANAGER;
require_once '../../shared/code/tce_authorization.php';
require_once 'tce_functions_upload.php';

header('Content-Type: application/json; charset=UTF-8');

/** @param array<string,mixed> $payload */
function f_clipboard_image_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$upload = $_FILES['image'] ?? null;
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    f_clipboard_image_response(['error' => 'Не удалось получить изображение из буфера.'], 400);
}

$temporary_name = $upload['tmp_name'] ?? null;
$size = $upload['size'] ?? null;
if (!is_string($temporary_name) || !is_int($size)) {
    f_clipboard_image_response(['error' => 'Не удалось получить изображение из буфера.'], 400);
}
if (!is_uploaded_file($temporary_name) || $size < 1 || $size > K_MAX_UPLOAD_SIZE) {
    f_clipboard_image_response(['error' => 'Размер изображения превышает допустимый.'], 413);
}

set_error_handler(static fn(): bool => true);
$image_info = getimagesize($temporary_name);
restore_error_handler();
if (!is_array($image_info)) {
    f_clipboard_image_response(['error' => 'Файл из буфера не является изображением.'], 415);
}
$mime_to_extension = [
    'image/gif' => 'gif',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];
$extension = $mime_to_extension[$image_info['mime']] ?? null;
if ($extension === null) {
    f_clipboard_image_response(['error' => 'Можно вставить изображение в формате PNG, JPEG или GIF.'], 415);
}

$session = $_SESSION;
$user_level = (int) ($session['session_user_level'] ?? 0);
$user_id = (int) ($session['session_user_id'] ?? 0);
$directory = K_PATH_CACHE;
if ($user_level < K_AUTH_ADMINISTRATOR) {
    $directory .= 'uid/' . $user_id . '/';
    if (!is_dir($directory) && !mkdir($directory, 0o744, true) && !is_dir($directory)) {
        f_clipboard_image_response(['error' => 'Не удалось подготовить папку для изображения.'], 500);
    }
}

$filename = 'clipboard_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
$target = $directory . $filename;
if (!move_uploaded_file($temporary_name, $target)) {
    f_clipboard_image_response(['error' => 'Не удалось сохранить изображение.'], 500);
}

$relative_path = $user_level < K_AUTH_ADMINISTRATOR ? 'uid/' . $user_id . '/' . $filename : $filename;
f_clipboard_image_response([
    'file' => $relative_path,
    'width' => $image_info[0],
    'height' => $image_info[1],
]);
