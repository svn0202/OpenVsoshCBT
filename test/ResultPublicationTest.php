<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_result_publication.php';

final class ResultPublicationTest extends TestCase
{
    public function testPublicationFlagAndWindowAreEnforced(): void
    {
        $now = strtotime('2026-07-27 12:00:00');
        self::assertFalse(\F_tmf_results_are_published(['test_results_to_users' => 0], $now));
        self::assertTrue(\F_tmf_results_are_published(['test_results_to_users' => 1], $now));
        self::assertFalse(\F_tmf_results_are_published([
            'test_results_to_users' => 1,
            'test_results_publish_at' => '2026-07-27 12:00:01',
        ], $now));
        self::assertFalse(\F_tmf_results_are_published([
            'test_results_to_users' => 1,
            'test_results_unpublish_at' => '2026-07-27 12:00:00',
        ], $now));
        self::assertTrue(\F_tmf_results_are_published([
            'test_results_to_users' => 1,
            'test_results_publish_at' => '2026-07-27 11:00:00',
            'test_results_unpublish_at' => '2026-07-27 13:00:00',
        ], $now));
    }

    public function testAnonymousIdentityDoesNotExposeAccountData(): void
    {
        $user = [
            'user_id' => 42,
            'user_lastname' => 'Иванов',
            'user_firstname' => 'Иван',
            'user_name' => 'ivan@example.test',
        ];
        self::assertSame('Участник #42', \F_tmf_result_identity($user, true));
        self::assertSame('Иванов Иван - ivan@example.test', \F_tmf_result_identity($user, false));
    }
}
