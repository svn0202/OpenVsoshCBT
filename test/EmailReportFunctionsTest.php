<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EmailReportFunctionsTest extends TestCase
{
    public function testSummaryEmailKeepsRecipientBodiesAndCleanupSequence(): void
    {
        $script = <<<'PHP'
namespace Com\Tecnick\File {
    class Exception extends \Exception {}
    class File {
        public array $hosts = [];
        public function setAllowedHosts($hosts) { $this->hosts = $hosts; }
        public function getUrlData($url) { return 'PDF'; }
    }
}
namespace Harness {
define('K_TABLE_TESTS', 'tests');
define('K_TIMESTAMP_FORMAT', 'Y-m-d H:i:s');
define('K_NEWLINE', "\n");
define('K_FILE_ALLOWED_HOSTS', 'a:0:{}');
define('K_PATH_HOST', 'https://example.test');
define('K_PATH_TCEXAM', '/app/');
define('K_RANDOM_SECURITY', 'secret');
$db = 'db';
$l = [
    'a_meta_charset' => 'UTF-8', 'a_meta_language' => 'en', 'a_meta_dir' => 'ltr',
    't_result_user' => 'Test result', 'w_test' => 'Test', 'w_test_score_threshold' => 'Threshold',
    'w_passed' => 'Passed', 'w_not_passed' => 'Not passed', 'w_score' => 'Score',
    'w_answers_right' => 'Right', 'w_answers_wrong' => 'Wrong', 'w_questions_unanswered' => 'Unanswered',
    'w_questions_undisplayed' => 'Undisplayed', 'w_attachment' => 'Attachment', 't_error' => 'Error',
    'm_unknown_email' => 'Unknown email',
];
$GLOBALS['emailcfg'] = [
    'Priority' => 3, 'ContentType' => 'text/html', 'Encoding' => '8bit', 'WordWrap' => 78,
    'Mailer' => 'smtp', 'Sendmail' => '/usr/sbin/sendmail', 'Host' => 'smtp.example.test', 'Port' => 587,
    'Helo' => 'example.test', 'SMTPAuth' => true, 'SMTPSecure' => 'tls', 'Username' => 'mailer',
    'Password' => 'secret', 'Timeout' => 10, 'SMTPDebug' => 0, 'Sender' => 'bounce@example.test',
    'From' => 'reports@example.test', 'FromName' => 'Reports', 'Reply' => 'reply@example.test',
    'ReplyName' => 'Reply desk', 'CharSet' => 'ISO-8859-1', 'MsgHeader' => '<header>#LANG# #LANGDIR#</header>',
    'MsgFooter' => '<footer>#EMAIL# #USERNAME# #USERFIRSTNAME# #USERLASTNAME#</footer>',
    'AttachmentsEncoding' => 'base64',
];
$GLOBALS['mail'] = null;
class C_mailer {
    public mixed $Priority; public mixed $ContentType; public mixed $Encoding; public mixed $WordWrap;
    public mixed $Mailer; public mixed $Sendmail; public mixed $Host; public mixed $Port; public mixed $Helo;
    public mixed $SMTPAuth; public mixed $SMTPSecure; public mixed $Username; public mixed $Password;
    public mixed $Timeout; public mixed $SMTPDebug; public mixed $Sender; public mixed $From; public mixed $FromName;
    public mixed $CharSet; public mixed $Subject; public mixed $Body = ''; public mixed $AltBody = '';
    public array $calls = [];
    public function __construct() { $GLOBALS['mail'] = $this; }
    public function setLanguageData($data) { $this->calls[] = ['language', $data]; }
    public function addReplyTo(...$args) { $this->calls[] = ['reply', $args]; }
    public function isHTML($value) { $this->calls[] = ['html', $value]; }
    public function addAddress(...$args) { $this->calls[] = ['address', $args]; }
    public function addStringAttachment(...$args) { $this->calls[] = ['attachment', $args]; }
    public function send() { $this->calls[] = ['send', []]; return true; }
    public function clearAddresses() { $this->calls[] = ['clearAddresses', []]; }
    public function clearAttachments() { $this->calls[] = ['clearAttachments', []]; }
    public function clearCustomHeaders() { $this->calls[] = ['clearCustomHeaders', []]; }
    public function clearAllRecipients() { $this->calls[] = ['clearAllRecipients', []]; }
    public function clearReplyTos() { $this->calls[] = ['clearReplyTos', []]; }
}
function f_is_authorized_user(...$arguments) { return true; }
function f_get_all_users_test_stat(...$arguments) {
    $GLOBALS['stat_arguments'] = $arguments;
    return ['testuser' => [[
        'id' => 21, 'user_id' => 7, 'user_email' => 'sam@example.test', 'user_name' => 'sam',
        'user_firstname' => 'Sam', 'user_lastname' => 'Student', 'testuser_creation_time' => '2026-01-10 10:00:00',
        'total_score' => 8.5, 'total_score_perc' => 85, 'right' => 3, 'right_perc' => 60,
        'wrong' => 1, 'wrong_perc' => 20, 'unanswered' => 1, 'unanswered_perc' => 20,
        'undisplayed' => 0, 'undisplayed_perc' => 0,
        'test' => ['test_id' => 9, 'test_name' => 'Algebra', 'test_score_threshold' => 6],
    ]]];
}
function f_format_float($value) { return number_format((float) $value, 1, '.', ''); }
function f_format_percentage($value, $sign) { return (string) $value . '%'; }
function get_password_hash($value) { return 'hash'; }
$source = file_get_contents($argv[1]);
preg_match('/function f_send_report_emails\(/', $source, $match, PREG_OFFSET_CAPTURE);
$function = substr($source, $match[0][1]);
$function = preg_replace('/^\s*require_once [^;]+;\s*$/m', '', $function);
$function = str_replace('global $l, $db;', 'global $l, $db; $emailcfg = $GLOBALS["emailcfg"];', $function);
eval('namespace Harness; ' . $function);
ob_start();
f_send_report_emails(9, 7, 21, 4, '2026-01-01', '2026-01-31', 1, 5, false);
$progress = ob_get_clean();
$mail = $GLOBALS['mail'];
echo json_encode([
    'progress' => $progress, 'calls' => $mail->calls, 'subject' => $mail->Subject,
    'body' => $mail->Body, 'altBody' => $mail->AltBody, 'charset' => $mail->CharSet,
    'statArguments' => $GLOBALS['stat_arguments'],
], JSON_THROW_ON_ERROR);
}
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/admin/code/tce_functions_email_reports.php'],
            dirname(__DIR__) . '/admin/code',
        );

        self::assertSame(0, $status, $output);
        /**
         * @var array{
         *     progress:string,calls:list<array{string,mixed}>,subject:string,body:string,altBody:string,
         *     charset:string,statArguments:array{int,int,int,string,string,string,bool,int}
         * } $result
         */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([9, 4, 7, '2026-01-01 00:00:00', '2026-01-31 00:00:00', 'total_score', false, 5], $result['statArguments']);
        self::assertSame('Test result', $result['subject']);
        self::assertSame('UTF-8', $result['charset']);
        self::assertStringContainsString("Test result [2026-01-10 10:00:00]\n", $result['altBody']);
        self::assertStringContainsString("Score: 8.5 85% - Passed\n", $result['altBody']);
        self::assertStringContainsString("Right: 3&nbsp;60%\n", $result['altBody']);
        self::assertStringContainsString('<header>en ltr</header>', $result['body']);
        self::assertStringContainsString('<footer>sam@example.test sam Sam Student</footer>', $result['body']);
        self::assertSame('1. sam@example.test [sam]<br />' . "\n", $result['progress']);
        self::assertSame(
            ['language', 'reply', 'html', 'address', 'send', 'clearAddresses', 'clearAttachments',
                'clearAddresses', 'clearCustomHeaders', 'clearAllRecipients', 'clearAttachments', 'clearReplyTos'],
            array_column($result['calls'], 0),
        );
    }
}
