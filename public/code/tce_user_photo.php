<?php

require_once '../config/tce_config.php';

$pagelevel = K_AUTH_PUBLIC_INDEX;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_user_photo.php';

$user_id = isset($_GET['id']) ? (int) $_GET['id'] : (int) $_SESSION['session_user_id'];
if (
    $user_id <= 0
    || (
        $user_id !== (int) $_SESSION['session_user_id']
        && (int) $_SESSION['session_user_level'] < K_AUTH_ADMIN_USERS
    )
) {
    http_response_code(403);
    exit;
}
$path = F_tmf_user_photo_path($user_id);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
