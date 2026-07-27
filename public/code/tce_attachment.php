<?php

require_once '../config/tce_config.php';
$pagelevel = K_AUTH_PUBLIC_TEST_RESULTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_attachments.php';

$attachment = F_tmf_attachment_find(isset($_GET['id']) ? (int) $_GET['id'] : 0);
if (
    !$attachment
    || (int) $attachment['attachment_user_id'] !== (int) $_SESSION['session_user_id']
) {
    http_response_code(404);
    exit();
}
F_tmf_attachment_send($attachment, isset($_GET['inline']));
