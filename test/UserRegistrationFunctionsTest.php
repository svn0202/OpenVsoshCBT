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
}
