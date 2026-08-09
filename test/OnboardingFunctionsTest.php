<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_onboarding.php';

final class OnboardingFunctionsTest extends TestCase
{
    public function testConfigurationHasNormalizedTestIdentifiers(): void
    {
        $config = \F_getOnboardingConfig();

        self::assertSame(['instruction_test_id', 'demo_test_id'], array_keys($config));
        self::assertIsInt($config['instruction_test_id']);
        self::assertGreaterThanOrEqual(0, $config['instruction_test_id']);
        self::assertIsInt($config['demo_test_id']);
        self::assertGreaterThanOrEqual(0, $config['demo_test_id']);
    }
}
