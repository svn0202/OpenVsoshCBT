<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;

final class ExamUiAssetsTest extends TestCase
{
    public function testImagePreviewIsKeyboardAccessibleAndSurvivesAjaxNavigation(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../shared/jscripts/mobile-exam.js');

        self::assertStringContainsString("source.addEventListener('keydown'", $script);
        self::assertStringContainsString("event.key === 'Enter' || event.key === ' '", $script);
        self::assertStringContainsString("dialog.showModal()", $script);
        self::assertStringContainsString('bindImagePreviews();', $script);
        self::assertStringContainsString('bindAudioLimits();', $script);
        self::assertStringContainsString("audio.addEventListener('play'", $script);
    }

    public function testBothDirectionsKeepMatchingAndMediaResponsive(): void
    {
        foreach (['picoman.css', 'picoman_rtl.css'] as $stylesheet) {
            $css = (string) file_get_contents(__DIR__ . '/../public/styles/' . $stylesheet);

            self::assertStringContainsString('.exam-page ol.answer audio', $css);
            self::assertStringContainsString('.exam-page ol.answer video', $css);
            self::assertStringContainsString('@media (max-width: 575px)', $css);
            self::assertStringContainsString('overflow-wrap: anywhere', $css);
            self::assertStringContainsString('.exam-image-preview::backdrop', $css);
        }
    }
}
