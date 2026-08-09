<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_user_registration.php';

final class UserRegistrationFunctionsTest extends TestCase
{
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
