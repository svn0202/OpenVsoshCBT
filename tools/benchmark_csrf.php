<?php

// Synthetic CLI-only comparison; never load installation credentials or connect to a database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
define('K_COOKIE_SECURE', true);
define('K_COOKIE_HTTPONLY', true);
define('K_COOKIE_SAMESITE', 'Strict');
define('K_RANDOM_SECURITY', bin2hex(random_bytes(32)));
require dirname(__DIR__) . '/shared/code/TCExamSessionHandler.php';
session_id(bin2hex(random_bytes(16)));
putenv('OPENVSOSH_CSRF_ISSUE_LEGACY');
putenv('OPENVSOSH_CSRF_LEGACY_UNTIL');
$_SERVER = [];
$scope = '/synthetic/execute.php';
$plain = get_plain_csrf_token_for_script($scope);
$legacy = get_password_hash($plain);
$token = f_get_csrf_token_for_script($scope);
$cpu = static function (): float {
    $r = getrusage();
    return $r['ru_utime.tv_sec'] + $r['ru_utime.tv_usec'] / 1e6
        + $r['ru_stime.tv_sec'] + $r['ru_stime.tv_usec'] / 1e6;
};
$results = ['php' => PHP_VERSION];
foreach ([
    'bcrypt_generate' => [3, static fn() => get_password_hash($plain)],
    'bcrypt_verify' => [3, static fn() => check_password($plain, $legacy)],
    'v2_generate' => [10000, static fn() => f_get_csrf_token_for_script($scope)],
    'v2_verify' => [10000, static fn() => check_csrf_token_for_script($token, $scope)],
    'v2_invalid' => [10000, static fn() => check_csrf_token_for_script($token . 'x', $scope)],
] as $name => [$count, $operation]) {
    $started = hrtime(true);
    $cpuStarted = $cpu();
    for ($i = 0; $i < $count; ++$i) {
        $operation();
    }
    $results[$name] = [
        'iterations' => $count,
        'cpu_ms_per_operation' => 1000 * ($cpu() - $cpuStarted) / $count,
        'wall_ms_per_operation' => (hrtime(true) - $started) / 1e6 / $count,
    ];
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
