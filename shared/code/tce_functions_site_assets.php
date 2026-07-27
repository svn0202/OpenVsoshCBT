<?php

require_once __DIR__ . '/tce_functions_openvsosh_settings.php';

function openvsosh_site_asset_directory(): string
{
    return rtrim(K_PATH_CACHE, '/\\') . DIRECTORY_SEPARATOR . 'site-assets' . DIRECTORY_SEPARATOR;
}

function openvsosh_site_asset_metadata(string $type): array|false
{
    if (!in_array($type, ['logo', 'background'], true)) {
        return false;
    }
    $stored = openvsosh_get_setting('site_' . $type . '_stored');
    $mime = openvsosh_get_setting('site_' . $type . '_mime');
    $sha = openvsosh_get_setting('site_' . $type . '_sha256');
    if (
        !is_string($stored) || preg_match('/^[a-f0-9]{64}$/', $stored) !== 1
        || !in_array($mime, ['image/jpeg', 'image/png'], true)
        || !is_string($sha) || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1
    ) {
        return false;
    }
    return ['stored' => $stored, 'mime' => $mime, 'sha256' => $sha];
}

/**
 * @return array{stored:bool,message:string}
 */
function openvsosh_store_site_asset(string $type, array $upload): array
{
    if (!in_array($type, ['logo', 'background'], true)) {
        return ['stored' => false, 'message' => 'Неизвестный тип изображения.'];
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['stored' => true, 'message' => ''];
    }
    $path = (string) ($upload['tmp_name'] ?? '');
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($path)) {
        return ['stored' => false, 'message' => 'Изображение не было загружено полностью.'];
    }
    $size = filesize($path);
    if ($size === false || $size < 1 || $size > 5_242_880) {
        return ['stored' => false, 'message' => 'Размер изображения должен быть от 1 байта до 5 МБ.'];
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    set_error_handler(static fn (): bool => true);
    try {
        $image = getimagesize($path);
    } finally {
        restore_error_handler();
    }
    if (
        !is_array($image)
        || !in_array($mime, ['image/jpeg', 'image/png'], true)
        || ($image['mime'] ?? '') !== $mime
        || (int) $image[0] < 32 || (int) $image[1] < 32
        || (int) $image[0] > 8192 || (int) $image[1] > 8192
    ) {
        return ['stored' => false, 'message' => 'Разрешены корректные JPEG/PNG размером от 32×32 до 8192×8192.'];
    }
    $directory = openvsosh_site_asset_directory();
    if (!is_dir($directory) && !mkdir($directory, 0o750, true) && !is_dir($directory)) {
        return ['stored' => false, 'message' => 'Хранилище изображений недоступно.'];
    }
    $old = openvsosh_site_asset_metadata($type);
    $stored = bin2hex(random_bytes(32));
    $target = $directory . $stored;
    if (!move_uploaded_file($path, $target)) {
        return ['stored' => false, 'message' => 'Не удалось сохранить изображение.'];
    }
    chmod($target, 0o640);
    $values = [
        'site_' . $type . '_stored' => $stored,
        'site_' . $type . '_mime' => $mime,
        'site_' . $type . '_sha256' => hash_file('sha256', $target),
    ];
    foreach ($values as $key => $value) {
        if (!openvsosh_save_setting($key, $value)) {
            unlink($target);
            return ['stored' => false, 'message' => 'Не удалось записать сведения об изображении.'];
        }
    }
    if ($old) {
        $old_path = $directory . $old['stored'];
        if (is_file($old_path)) {
            unlink($old_path);
        }
    }
    return ['stored' => true, 'message' => 'Изображение сохранено.'];
}

function openvsosh_send_site_asset(string $type): never
{
    $metadata = openvsosh_site_asset_metadata($type);
    $path = $metadata ? openvsosh_site_asset_directory() . $metadata['stored'] : '';
    if (
        !$metadata || !is_file($path)
        || !hash_equals($metadata['sha256'], hash_file('sha256', $path))
    ) {
        http_response_code(404);
        exit();
    }
    header('Content-Type: ' . $metadata['mime']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit();
}
