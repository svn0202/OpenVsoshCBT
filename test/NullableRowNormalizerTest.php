<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NullableRowNormalizerTest extends TestCase
{
    /** @return array<string,array{string,string,string}> */
    public static function nullableRowNormalizers(): array
    {
        /** @var array<string,array{string,string,string}> $cases */
        $cases = [];
        foreach (['admin/code', 'public/code', 'shared/code'] as $directory) {
            $files = glob(dirname(__DIR__) . '/' . $directory . '/*.php');
            self::assertNotFalse($files);
            foreach ($files as $file) {
                $source = (string) file_get_contents($file);
                $matches = [];
                preg_match_all(
                    '/function\s+(\w+)\(mixed \$(row|value)\): \?array\s*\{(.*?)\n\}/s',
                    $source,
                    $matches,
                    PREG_SET_ORDER,
                );
                foreach ($matches as $match) {
                    if (!isset($match[1], $match[2], $match[3])) {
                        continue;
                    }

                    $name = $match[1];
                    $variable = $match[2];
                    $cases[$directory . '/' . basename($file) . ':' . $name] = [
                        $match[3],
                        $variable,
                        $directory . '/' . basename($file),
                    ];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('nullableRowNormalizers')]
    public function testMixedNullableRowNormalizersRejectNonArrays(
        string $body,
        string $variable,
        string $file,
    ): void {
        self::assertStringContainsString(
            'is_array($' . $variable . ')',
            $body,
            $file . ' must normalize false and other non-array database results to null',
        );
    }
}
