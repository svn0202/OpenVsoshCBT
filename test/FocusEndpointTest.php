<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class FocusEndpointTest extends TestCase
{
    public function testFocusEndpointPreservesDatabaseOutcomeResponses(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/public/code/tce_test_focus.php');
        self::assertIsString($source);

        self::assertStringContainsString('testuser_focus_loss_count=testuser_focus_loss_count+1', $source);
        self::assertStringContainsString("F_tmf_focus_json(403, ['status' => 'forbidden']);", $source);
        self::assertStringContainsString("F_tmf_focus_json(500, ['status' => 'error']);", $source);
        self::assertStringContainsString("F_tmf_focus_json(409, ['status' => 'closed']);", $source);
        self::assertStringContainsString("F_tmf_focus_json(409, ['status' => 'conflict']);", $source);
        self::assertMatchesRegularExpression(
            '/F_tmf_focus_json\(200, \[\s*\'status\' => \'recorded\',\s*\'count\' =>/s',
            $source,
        );
    }
}
