<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class UserVerificationEndpointTest extends TestCase
{
    public function testRegistrationVerificationPreservesSanitizingUpdateAndPage(): void
    {
        $configSource = <<<'PHP'
<?php
define('K_TABLE_USERS', 'users');
define('K_OTP_LOGIN', false);
define('K_NEWLINE', "\n");
$db = 'db-link';
$l = [
    't_user_registration' => 'Register',
    'w_new_password' => 'New password',
    'm_user_registration_ok' => 'Registration complete',
    'm_otp_qrcode' => 'OTP code',
    'h_index' => 'Home',
];
function F_escape_sql($db, $value): string { return 'ESC[' . $value . ']'; }
function F_db_query($sql, $db)
{
    $normalized = preg_replace('/\s+/', ' ', trim($sql));
    $GLOBALS['queries'][] = [$normalized, $db];
    return str_starts_with($normalized, 'SELECT ') ? 'select-result' : true;
}
function F_db_fetch_array($result): array
{
    return ['user_name' => 'student', 'user_otpkey' => 'OTPKEY'];
}
function F_display_db_error($terminate): void { $GLOBALS['db_errors'][] = $terminate; }
function F_print_error($type, $message): void
{
    $GLOBALS['messages'][] = [$type, $message];
    echo '<' . $type . ':' . $message . '>';
}
function get_password_hash(string $password): string { return 'HASH[' . $password . ']'; }
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-user-verification-" . uniqid(); '
                    . 'mkdir($root . "/public/code", 0700, true); mkdir($root . "/public/config", 0700); '
                    . 'mkdir($root . "/shared/code", 0700, true); mkdir($root . "/shared/config", 0700); '
                    . 'copy($argv[1], $root . "/public/code/tce_user_verification.php"); '
                    . 'file_put_contents($root . "/public/config/tce_config.php", base64_decode($argv[2], true)); '
                    . 'file_put_contents($root . "/shared/config/tce_user_registration.php", "<?php"); '
                    . 'file_put_contents($root . "/shared/code/tce_authorization.php", "<?php"); '
                    . 'file_put_contents($root . "/public/code/tce_page_header.php", '
                    . '"<?php \\$GLOBALS[\\"header_context\\"] = [\\$pagelevel, \\$thispage_title, '
                    . '\\$thispage_description]; echo \\"<HEADER>\\\\n\\";"); '
                    . 'file_put_contents($root . "/public/code/tce_page_footer.php", "<?php echo \\"<FOOTER>\\\\n\\";"); '
                    . 'register_shutdown_function(function () use ($root): void { $page = ob_get_clean(); '
                    . '$result = [$page, $GLOBALS["queries"], $GLOBALS["messages"], '
                    . '$GLOBALS["header_context"], $GLOBALS["db_errors"] ?? []]; '
                    . 'foreach (["/public/code/tce_user_verification.php", "/public/code/tce_page_header.php", '
                    . '"/public/code/tce_page_footer.php", "/public/config/tce_config.php", '
                    . '"/shared/config/tce_user_registration.php", "/shared/code/tce_authorization.php"] '
                    . 'as $file) { unlink($root . $file); } rmdir($root . "/public/code"); '
                    . 'rmdir($root . "/public/config"); rmdir($root . "/public"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared/config"); '
                    . 'rmdir($root . "/shared"); rmdir($root); echo json_encode($result); }); '
                    . '$_REQUEST = ["a" => "student+bad@example.com", "b" => "abCD-12", "c" => "17"]; '
                    . 'chdir($root . "/public/code"); ob_start(); require "tce_user_verification.php";',
                dirname(__DIR__) . '/public/code/tce_user_verification.php',
                base64_encode($configSource),
            ],
            dirname(__DIR__) . '/public/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     0: string,
         *     1: array{array{string, string}, array{string, string}},
         *     2: array{array{string, string}},
         *     3: array{int, string, string},
         *     4: list<mixed>
         * } $decoded
         */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            "<HEADER>\n<MESSAGE:Registration complete>\n<div class=\"container\">\n"
                . '<strong><a href="index.php" title="Home">Home &gt;</a></strong>' . "\n"
                . "</div>\n<FOOTER>\n",
            $decoded[0],
        );
        self::assertSame(
            "SELECT * FROM users WHERE (user_verifycode='ESC[abCD12]' AND user_id='17' "
                . "AND user_email='ESC[studentbad@example.com]') LIMIT 1",
            $decoded[1][0][0],
        );
        self::assertSame("UPDATE users SET user_level='1', user_verifycode=NULL WHERE user_id=17", $decoded[1][1][0]);
        self::assertSame([['MESSAGE', 'Registration complete']], $decoded[2]);
        self::assertSame([0, 'Register', ''], $decoded[3]);
        self::assertSame([], $decoded[4]);
    }
}
