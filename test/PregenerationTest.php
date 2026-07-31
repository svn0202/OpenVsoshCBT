<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_pregeneration.php';

final class PregenerationTest extends TestCase
{
    public function testPreparedAttemptStillLooksAvailableInTheCatalogue(): void
    {
        self::assertSame(0, F_tmf_catalog_test_status(1, true));
    }

    public function testStartedAttemptKeepsItsProgressStatus(): void
    {
        self::assertSame(1, F_tmf_catalog_test_status(1, false));
        self::assertSame(2, F_tmf_catalog_test_status(2, false));
        self::assertSame(3, F_tmf_catalog_test_status(3, false));
    }
}
