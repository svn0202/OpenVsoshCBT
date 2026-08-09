<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class EmailConfigTest extends TestCase
{
    public function testDefaultConfigurationPreservesCustomEntries(): void
    {
        $emailcfg = ['CustomOption' => 'preserved'];

        require __DIR__ . '/../shared/config.default/tce_email_config.php';

        $expected = [
            'CustomOption' => 'preserved',
            'CharSet' => 'UTF-8',
            'Mailer' => 'smtp',
            'Host' => 'smtp.gmail.com',
            'Port' => 465,
            'SMTPAuth' => true,
            'SMTPSecure' => 'ssl',
            'Timeout' => 10,
            'SMTPDebug' => false,
        ];
        /** @var array<string, mixed> $emailcfg */
        self::assertSame($expected, array_intersect_key($emailcfg, $expected));
    }
}
