<?php

//============================================================+
// File name   : TmxResourceBundleTest.php
// Begin       : 2026-06-22
//
// Description : Unit tests for the TMX translation parser in
//               shared/code/tce_tmx.php
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test;

use PHPUnit\Framework\TestCase;
use TMXResourceBundle;

/**
 * @file
 * Tests for TMX (translation memory) parsing into the per-language resource array.
 * @package com.tecnick.tcexam.test
 */
final class TmxResourceBundleTest extends TestCase
{
    private function fixture(): string
    {
        return __DIR__ . '/fixtures/sample.tmx';
    }

    public function testParsesRequestedLanguage(): void
    {
        $bundle = new TMXResourceBundle($this->fixture(), 'IT', '');
        $res = $bundle->getResource();
        $this->assertSame('Ciao', $res['greeting']);
        $this->assertSame('Mondo', $res['world']);
    }

    public function testLanguageMatchIsCaseInsensitive(): void
    {
        $bundle = new TMXResourceBundle($this->fixture(), 'en', '');
        $res = $bundle->getResource();
        $this->assertSame('Hello', $res['greeting']);
        $this->assertSame('World', $res['world']);
    }

    public function testMissingLanguageFallsBackToEnglish(): void
    {
        $bundle = new TMXResourceBundle($this->fixture(), 'XX', '');
        $res = $bundle->getResource();
        $this->assertArrayHasKey('greeting', $res);
        $this->assertSame('Hello', $res['greeting']);
    }

    public function testParsesEntitiesAndAccents(): void
    {
        $bundle = new TMXResourceBundle($this->fixture(), 'IT', '');
        $res = $bundle->getResource();
        // the XML parser decodes &amp; to & and preserves the accented UTF-8 characters
        $this->assertSame('Caffè & Tè', $res['special']);
    }

    public function testRebuildsStaleCacheAfterSourceUpdate(): void
    {
        $dir = sys_get_temp_dir() . '/openvsosh-tmx-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o700, true);
        $source = $dir . '/language.tmx';
        $cache = $dir . '/language_it.php';
        copy($this->fixture(), $source);

        try {
            $first = new TMXResourceBundle($source, 'IT', $cache);
            $this->assertSame('Ciao', $first->getResource()['greeting']);

            $updated = str_replace('<seg>Ciao</seg>', '<seg>Salve</seg>', (string) file_get_contents($source));
            file_put_contents($source, $updated);
            touch($source, time() + 2);
            clearstatcache(true, $source);
            clearstatcache(true, $cache);

            $second = new TMXResourceBundle($source, 'IT', $cache);
            $this->assertSame('Salve', $second->getResource()['greeting']);
        } finally {
            if (is_file($cache)) {
                unlink($cache);
            }
            if (is_file($source)) {
                unlink($source);
            }
            rmdir($dir);
        }
    }

    public function testRebuildsCacheWhenUpdatedSourceKeepsAnOlderTimestamp(): void
    {
        $dir = sys_get_temp_dir() . '/openvsosh-tmx-' . bin2hex(random_bytes(6));
        mkdir($dir, 0o700, true);
        $source = $dir . '/language.tmx';
        $cache = $dir . '/language_it.php';
        copy($this->fixture(), $source);

        try {
            $first = new TMXResourceBundle($source, 'IT', $cache);
            $this->assertSame('Ciao', $first->getResource()['greeting']);

            $updated = str_replace('<seg>Ciao</seg>', '<seg>Buongiorno</seg>', (string) file_get_contents($source));
            file_put_contents($source, $updated);
            touch($source, time() - 10);
            touch($cache, time());
            clearstatcache(true, $source);
            clearstatcache(true, $cache);

            $second = new TMXResourceBundle($source, 'IT', $cache);
            $this->assertSame('Buongiorno', $second->getResource()['greeting']);
        } finally {
            if (is_file($cache)) {
                unlink($cache);
            }
            if (is_file($source)) {
                unlink($source);
            }
            rmdir($dir);
        }
    }
}
