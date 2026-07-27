<?php

namespace Test;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_offline.php';

final class OfflinePackageTest extends TestCase
{
    public function testPayloadSignatureDetectsTampering(): void
    {
        $payload = \F_tmf_offline_payload_encode(['package_id' => 'abc', 'questions' => []]);
        $secret = str_repeat('a', 64);
        $signature = \F_tmf_offline_sign($payload, $secret);

        self::assertTrue(\F_tmf_offline_signature_is_valid($payload, $signature, $secret));
        self::assertFalse(\F_tmf_offline_signature_is_valid($payload . 'x', $signature, $secret));
        self::assertFalse(\F_tmf_offline_signature_is_valid($payload, str_repeat('0', 64), $secret));
    }

    public function testOfflineHtmlHasNoNetworkOrPersistentBrowserCache(): void
    {
        $payload = \F_tmf_offline_payload_encode([
            'package_id' => str_repeat('a', 32),
            'test_name' => 'Test',
            'user_name' => 'student',
            'user_display_name' => 'Student',
            'expires_at' => '2030-01-01T00:00:00Z',
            'questions' => [],
        ]);
        $html = \F_tmf_offline_html([
            'format' => \TMF_OFFLINE_FORMAT,
            'payload_b64' => $payload,
            'signature' => str_repeat('b', 64),
        ]);

        self::assertStringContainsString("default-src 'none'", $html);
        self::assertStringContainsString('Скачать черновик / результат', $html);
        self::assertStringNotContainsString('fetch(', $html);
        self::assertStringNotContainsString('localStorage', $html);
        self::assertStringNotContainsString('serviceWorker', $html);
    }
}
