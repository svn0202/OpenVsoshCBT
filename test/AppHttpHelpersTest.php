<?php

namespace Test;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Test\Integration\AppHttpTestCase;

require_once __DIR__ . '/integration/AppHttpTestCase.php';

final class AppHttpHelpersTest extends TestCase
{
    public function testStatusParserUsesLastResponseStatus(): void
    {
        $method = new ReflectionMethod(AppHttpTestCase::class, 'statusFrom');

        self::assertSame(200, $method->invoke(null, ['HTTP/1.1 302 Found', 'HTTP/1.1 200 OK']));
    }

    public function testCookieParserExtractsNamesAndValues(): void
    {
        $method = new ReflectionMethod(AppHttpTestCase::class, 'cookiesFrom');

        self::assertSame(
            ['session' => 'abc123', 'theme' => 'dark'],
            $method->invoke(null, ['Set-Cookie: session=abc123; Path=/', 'Set-Cookie: theme=dark']),
        );
    }
}
