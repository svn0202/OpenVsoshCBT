<?php

require_once '../config/tce_config.php';
$pagelevel = (int) K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_attachments.php';

$attachment = F_tmf_attachment_find(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if (
    !$attachment
    || !f_is_authorized_user(
        K_TABLE_TESTS,
        'test_id',
        (int) $attachment['testuser_test_id'],
        'test_user_id',
    )
) {
    http_response_code(404);
    exit();
}
F_tmf_attachment_send($attachment, isset($_GET['inline']));
