<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_test_access.php';

final class TestAccessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['session_unlocked_tests'], $_SESSION['session_user_id']);
    }

    public function testPasswordUnlockIsScopedToTestAndCurrentUser(): void
    {
        $_SESSION['session_user_id'] = 42;
        \F_tmf_test_session_unlock(7);

        self::assertTrue(\F_tmf_test_session_is_unlocked(7));
        self::assertFalse(\F_tmf_test_session_is_unlocked(8));

        $_SESSION['session_user_id'] = 43;
        self::assertFalse(\F_tmf_test_session_is_unlocked(7));
    }
}
