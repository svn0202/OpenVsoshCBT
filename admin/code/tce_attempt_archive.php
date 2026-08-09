<?php

require_once '../config/tce_config.php';
/** @var mixed $db Database connection initialized by tce_config.php. */
$pagelevel = (int) K_AUTH_ADMIN_RESULTS;
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_attachments.php';

$testuser_id = isset($_GET['testuser_id']) ? (int) $_GET['testuser_id'] : 0;
$result = F_db_query(
    'SELECT testuser_test_id FROM ' . K_TABLE_TEST_USER
    . ' WHERE testuser_id=' . $testuser_id . ' LIMIT 1',
    $db,
);
$attempt = $result ? F_db_fetch_array($result) : false;
if (
    !$attempt
    || !F_isAuthorizedUser(
        K_TABLE_TESTS,
        'test_id',
        (int) $attempt['testuser_test_id'],
        'test_user_id',
    )
) {
    http_response_code(404);
    exit();
}
$bytes = F_tmf_attempt_archive($testuser_id);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="openvsosh-attempt-' . $testuser_id . '.zip"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $bytes;
