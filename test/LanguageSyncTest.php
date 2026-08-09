<?php

namespace Test;

use PHPUnit\Framework\TestCase;

final class LanguageSyncTest extends TestCase
{
    /**
     * @throws \Random\RandomException
     * @throws \RuntimeException
     */
    public function testAddsMissingUnitsAndLanguagesWithoutReplacingRuntimeTranslations(): void
    {
        $directory = sys_get_temp_dir() . '/openvsosh-language-' . bin2hex(random_bytes(6));
        mkdir($directory, 0o700, true);
        $default = $directory . '/default.tmx';
        $runtime = $directory . '/runtime.tmx';
        file_put_contents($default, $this->tmx([
            'existing' => ['en' => 'Default', 'ru' => 'По умолчанию'],
            'new_key' => ['en' => 'New', 'ru' => 'Новый'],
        ]));
        file_put_contents($runtime, $this->tmx([
            'existing' => ['en' => 'Custom'],
        ]));

        try {
            $this->assertTrue(\F_sync_tmx_translations($default, $runtime));
            $document = new \DOMDocument();
            $this->assertTrue($document->load($runtime));
            $xpath = new \DOMXPath($document);
            $this->assertSame('Custom', $xpath->evaluate(
                'string(//tu[@tuid="existing"]/tuv[@xml:lang="en"]/seg)',
            ));
            $this->assertSame('По умолчанию', $xpath->evaluate(
                'string(//tu[@tuid="existing"]/tuv[@xml:lang="ru"]/seg)',
            ));
            $this->assertSame('Новый', $xpath->evaluate(
                'string(//tu[@tuid="new_key"]/tuv[@xml:lang="ru"]/seg)',
            ));
            $this->assertFalse(\F_sync_tmx_translations($default, $runtime));
        } finally {
            if (is_file($default)) {
                unlink($default);
            }
            if (is_file($runtime)) {
                unlink($runtime);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    /** @param array<string, array<string, string>> $units */
    private function tmx(array $units): string
    {
        $body = '';
        foreach ($units as $key => $translations) {
            $body .= '<tu tuid="' . $key . '">';
            foreach ($translations as $language => $text) {
                $body .= '<tuv xml:lang="' . $language . '"><seg>' . $text . '</seg></tuv>';
            }
            $body .= '</tu>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><tmx version="1.4"><body>' . $body . '</body></tmx>';
    }
}
