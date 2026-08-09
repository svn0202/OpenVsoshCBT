<?php

namespace Test;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class CustomAuthBasicTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testValidCredentialsReturnConfiguredUser(): void
    {
        $this->configure('secret');
        $_POST = [
            'logaction' => 'login',
            'xuser_name' => 'operator',
            'xuser_password' => 'secret',
        ];

        self::assertSame([
            'user_email' => '',
            'user_firstname' => '',
            'user_lastname' => '',
            'user_birthdate' => '',
            'user_birthplace' => '',
            'user_regnumber' => '',
            'user_ssn' => '',
            'user_level' => 5,
            'usrgrp_group_id' => 7,
        ], \custom_auth_basic_check_login());
    }

    #[RunInSeparateProcess]
    public function testStructuredPasswordIsRejected(): void
    {
        $this->configure('secret');
        $_POST = [
            'logaction' => 'login',
            'xuser_name' => 'operator',
            'xuser_password' => ['secret'],
        ];

        self::assertNull(\custom_auth_basic_check_login());
    }

    private function configure(#[\SensitiveParameter] string $password): void
    {
        define('K_CUSTOM_AUTH_BASIC_USERNAME', 'operator');
        define('K_CUSTOM_AUTH_BASIC_PASSWORD_HASH', password_hash($password, PASSWORD_DEFAULT));
        define('K_CUSTOM_AUTH_BASIC_USER_LEVEL', 5);
        define('K_CUSTOM_AUTH_BASIC_USER_GROUP_ID', 7);
        require __DIR__ . '/../shared/custom_auth/basic.php';
    }
}
