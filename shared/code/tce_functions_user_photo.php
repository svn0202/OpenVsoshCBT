<?php

function F_tmf_user_photo_path(int $user_id): string
{
    return K_PATH_MAIN . 'shared/config/user-photos/' . max(0, $user_id) . '.jpg';
}

/**
 * Validate, resize and re-encode a participant photo as a payload-free JPEG.
 *
 * @return array{status:string,message:string}
 */
function F_tmf_user_photo_store(array $upload, int $user_id): array
{
    $source = (string) ($upload['tmp_name'] ?? '');
    if (
        $user_id <= 0
        || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file($source)
        || (int) ($upload['size'] ?? 0) > 5_242_880
    ) {
        return ['status' => 'invalid', 'message' => 'Фотография не загружена или превышает 5 МБ.'];
    }
    set_error_handler(static fn (): bool => true);
    try {
        $info = getimagesize($source);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($source),
            'image/png' => imagecreatefrompng($source),
            default => false,
        };
    } finally {
        restore_error_handler();
    }
    if (!$image instanceof GdImage) {
        return ['status' => 'invalid', 'message' => 'Разрешены только корректные JPEG и PNG.'];
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, 1024 / max($width, $height));
    $target_width = max(1, (int) round($width * $scale));
    $target_height = max(1, (int) round($height * $scale));
    $target = imagecreatetruecolor($target_width, $target_height);
    $white = imagecolorallocate($target, 255, 255, 255);
    imagefill($target, 0, 0, $white);
    imagecopyresampled($target, $image, 0, 0, 0, 0, $target_width, $target_height, $width, $height);
    unset($image);

    $directory = dirname(F_tmf_user_photo_path($user_id));
    if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
        unset($target);
        return ['status' => 'error', 'message' => 'Хранилище фотографий недоступно.'];
    }
    $temporary = $directory . '/.' . $user_id . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $stored = imagejpeg($target, $temporary, 88);
    unset($target);
    if (!$stored || !rename($temporary, F_tmf_user_photo_path($user_id))) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
        return ['status' => 'error', 'message' => 'Не удалось сохранить фотографию.'];
    }
    chmod(F_tmf_user_photo_path($user_id), 0o600);
    return ['status' => 'stored', 'message' => 'Фотография сохранена.'];
}
