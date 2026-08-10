<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_user_registration.php';

final class UserRegistrationFunctionsTest extends TestCase
{
    public function testRegistrationEmailCompositionRemainsUnchanged(): void
    {
        $script = <<<'PHP'
namespace Harness;
define('K_PATH_PUBLIC_CODE', '/public/code/');
define('K_PATH_HOST', 'https://exam.example');
define('K_PATH_TCEXAM', '/cbt/');
define('K_USRREG_ADMIN_EMAIL', 'admin@example.test');
$_SERVER['REMOTE_ADDR'] = '192.0.2.15';
$l = [
    'a_meta_charset' => 'UTF-8',
    'w_registration_verification' => 'Verify registration',
    'm_email_registration' => 'Email=#EMAIL# IP=#USERIP# URL=#SUBSCRIBEURL# APP=#TCEXAMURL#',
];
$emailcfg = [
    'Priority' => 3, 'ContentType' => 'text/plain', 'Encoding' => '8bit', 'WordWrap' => 72,
    'Mailer' => 'smtp', 'Sendmail' => '/usr/sbin/sendmail', 'Host' => 'smtp.example.test',
    'Port' => 465, 'Helo' => 'exam.example', 'SMTPAuth' => true, 'SMTPSecure' => 'ssl',
    'Username' => 'mailer', 'Password' => 'secret', 'Timeout' => 10, 'SMTPDebug' => 0,
    'Sender' => 'sender@example.test', 'From' => 'from@example.test', 'FromName' => 'Exam',
    'Reply' => 'reply@example.test', 'ReplyName' => 'Support', 'CharSet' => 'ISO-8859-1',
];
class C_mailer {
    public static ?self $last = null;
    public mixed $Priority; public mixed $ContentType; public mixed $Encoding; public mixed $WordWrap;
    public mixed $Mailer; public mixed $Sendmail; public mixed $Host; public mixed $Port; public mixed $Helo;
    public mixed $SMTPAuth; public mixed $SMTPSecure; public mixed $Username; public mixed $Password;
    public mixed $Timeout; public mixed $SMTPDebug; public mixed $Sender; public mixed $From;
    public mixed $FromName; public mixed $CharSet; public mixed $Subject; public mixed $Body; public mixed $AltBody;
    public array $events = [];
    public function __construct() { self::$last = $this; }
    public function setLanguageData($language) { $this->events[] = ['language', $language]; }
    public function addReplyTo($address, $name) { $this->events[] = ['reply', $address, $name]; }
    public function isHTML($enabled) { $this->events[] = ['html', $enabled]; }
    public function addAddress($address, $name) { $this->events[] = ['address', $address, $name]; }
    public function addBCC($address) { $this->events[] = ['bcc', $address]; }
    public function send() { $this->events[] = ['send']; return true; }
    public function clearAddresses() { $this->events[] = ['clearAddresses']; }
    public function clearCustomHeaders() { $this->events[] = ['clearCustomHeaders']; }
    public function clearAllRecipients() { $this->events[] = ['clearAllRecipients']; }
    public function clearAttachments() { $this->events[] = ['clearAttachments']; }
    public function clearReplyTos() { $this->events[] = ['clearReplyTos']; }
}
function F_html_to_text($html, $showlinks, $showimages) { return 'TEXT:' . $html; }
function F_print_error($type, $message) { echo "[[$type:$message]]"; }
$source = file_get_contents($argv[1]);
preg_match('/function (f_send_user_reg_email)\(/', $source, $match, PREG_OFFSET_CAPTURE);
$function = substr($source, $match[0][1]);
$function = preg_replace('/^\s*require_once [^;]+;\n/m', '', $function);
$function = str_replace('global $l, $db;', 'global $l, $db, $emailcfg;', $function);
eval('namespace Harness; ' . $function);
f_send_user_reg_email('42', 'user@example.test', 'verify-code');
$mail = C_mailer::$last;
echo json_encode([
    'subject' => $mail?->Subject, 'body' => $mail?->Body, 'alt' => $mail?->AltBody,
    'charset' => $mail?->CharSet, 'events' => $mail?->events,
], JSON_THROW_ON_ERROR);
PHP;

        [$status, $output] = \F_tcecode_run_process(
            [PHP_BINARY, '-r', $script, dirname(__DIR__) . '/shared/code/tce_functions_user_registration.php'],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertJson($output);
        /** @var array{subject:string,body:string,alt:string,charset:string,events:list<array<int,mixed>>} $result */
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Verify registration', $result['subject']);
        self::assertSame('UTF-8', $result['charset']);
        self::assertSame(
            'Email=user@example.test IP=192.0.2.15 '
                . 'URL=/public/code/tce_user_verification.php?a=user@example.test&amp;b=verify-code&amp;c=42 '
                . 'APP=https://exam.example/cbt/',
            $result['body'],
        );
        self::assertSame('TEXT:' . $result['body'], $result['alt']);
        self::assertContains(['reply', 'reply@example.test', 'Support'], $result['events']);
        self::assertContains(['address', 'user@example.test', ''], $result['events']);
        self::assertContains(['bcc', 'admin@example.test'], $result['events']);
        self::assertContains(['html', true], $result['events']);
        self::assertSame(
            [
                ['clearAddresses'], ['clearCustomHeaders'], ['clearAllRecipients'],
                ['clearAttachments'], ['clearReplyTos'],
            ],
            array_slice($result['events'], -5),
        );
    }

    public function testLegacyRegistrationMailerNameRemainsCallable(): void
    {
        self::assertTrue(function_exists('F_send_user_reg_email'));
        self::assertTrue(is_callable('F_send_user_reg_email'));
    }

    public function testLegacyMailerClassKeepsItsPublicName(): void
    {
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require "tce_class_mailer.php"; '
                    . '$mailer = new C_mailer(); '
                    . 'echo json_encode([get_class($mailer), is_a($mailer, PHPMailer\\PHPMailer\\PHPMailer::class)]);',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status);
        self::assertSame('["C_mailer",true]', $output);
    }
}
