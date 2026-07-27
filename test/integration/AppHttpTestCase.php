<?php

//============================================================+
// File name   : AppHttpTestCase.php
// Begin       : 2026-06-22
//
// Description : Base class for HTTP-level controller integration tests.
//               Provides a minimal HTTP client (over the stream wrapper, no
//               curl extension required) with cookie tracking and CSRF-token
//               extraction, driving the app-under-test container.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @file
 * Base class for HTTP-level controller integration tests. Subclasses drive the real controllers
 * running in the app-under-test container (see docker/test-app-entrypoint.sh). Tests self-skip
 * when TCEXAM_APP_URL is not set (i.e. outside `make dockertest`).
 * @package com.tecnick.tcexam.test
 */
abstract class AppHttpTestCase extends TestCase
{
    /** Base URL of the app-under-test (no trailing slash). */
    protected string $base = '';

    protected function setUp(): void
    {
        $url = (string) getenv('TCEXAM_APP_URL');
        if ($url === '') {
            $this->markTestSkipped('App-under-test not configured: set TCEXAM_APP_URL (run via `make dockertest`).');
        }

        $this->base = rtrim($url, '/');
    }

    /**
     * Perform an HTTP request over the stream wrapper (no curl extension needed in the runner).
     *
     * @param array<string,string> $cookies Cookies to send (name => value).
     * @param array<string,mixed>  $post    Form fields for a POST request (values may be arrays,
     *                                       e.g. multi-select `name[]` fields).
     *
     * @return array{0:int,1:string,2:array<string,string>} [status, body, cookies(sent+received)]
     */
    protected function http(
        string $method,
        string $path,
        array $cookies = [],
        array $post = [],
        bool $followRedirects = true,
    ): array
    {
        $header = "Accept: text/html\r\n";
        if ($cookies !== []) {
            $pairs = [];
            foreach ($cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }

            $header .= 'Cookie: ' . implode('; ', $pairs) . "\r\n";
        }

        $opts = [
            'method' => $method,
            'header' => $header,
            'ignore_errors' => true,
            'timeout' => 20,
            'follow_location' => $followRedirects ? 1 : 0,
            'max_redirects' => $followRedirects ? 20 : 0,
        ];
        if ($method === 'POST') {
            $opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $opts['content'] = http_build_query($post);
        }

        $body = file_get_contents($this->base . $path, false, stream_context_create(['http' => $opts]));
        $headers = $http_response_header ?? [];

        return [self::statusFrom($headers), (string) $body, $cookies + self::cookiesFrom($headers)];
    }

    /**
     * Submit one multipart form with an in-memory file.
     *
     * @param array<string,string> $cookies
     * @param array<string,string> $fields
     * @return array{0:int,1:string,2:array<string,string>}
     */
    protected function httpUpload(
        string $path,
        array $cookies,
        array $fields,
        string $fieldName,
        string $filename,
        string $contents,
    ): array {
        $boundary = '----OpenVsoshCBT' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
                . $value . "\r\n";
        }
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $filename . '"' . "\r\n"
            . "Content-Type: application/json\r\n\r\n"
            . $contents . "\r\n"
            . '--' . $boundary . "--\r\n";
        $cookieHeader = '';
        if ($cookies !== []) {
            $pairs = [];
            foreach ($cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $cookieHeader = 'Cookie: ' . implode('; ', $pairs) . "\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Accept: text/html\r\n" . $cookieHeader
                . 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\n",
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 20,
            'follow_location' => 1,
            'max_redirects' => 20,
        ]]);
        $response = file_get_contents($this->base . $path, false, $context);
        $headers = $http_response_header ?? [];
        return [self::statusFrom($headers), (string) $response, $cookies + self::cookiesFrom($headers)];
    }

    /** Extract the CSRF token embedded in a form, or null when absent. */
    protected static function extractCsrfToken(string $body): ?string
    {
        return preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $body, $m) === 1 ? $m[1] : null;
    }

    /** Extract the HTTP status code from a response header list (last status line wins). */
    private static function statusFrom(array $headers): int
    {
        $status = 0;
        foreach ($headers as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return $status;
    }

    /**
     * Parse Set-Cookie response headers into a name => value map.
     *
     * @return array<string,string>
     */
    private static function cookiesFrom(array $headers): array
    {
        $cookies = [];
        foreach ($headers as $h) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m) === 1) {
                $cookies[trim($m[1])] = $m[2];
            }
        }

        return $cookies;
    }
}
