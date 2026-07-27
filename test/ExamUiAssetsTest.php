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
        self::assertStringContainsString("toolbar.dataset.autoFullscreen === '1'", $script);
        self::assertStringContainsString("payload, 'live_score'", $script);
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

    public function testTestEditorOffersBulkSubjectSelection(): void
    {
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_test.php');

        self::assertStringContainsString('id="select_all_subjects"', $editor);
        self::assertStringContainsString('id="clear_all_subjects"', $editor);
        self::assertStringContainsString('option.value.charAt(0)!=="#"', $editor);
    }

    public function testParticipantPhotosAreServedThroughAnAuthorizedController(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../public/code/tce_user_photo.php');
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_user.php');

        self::assertStringContainsString('K_AUTH_ADMIN_USERS', $controller);
        self::assertStringContainsString("header('Content-Type: image/jpeg')", $controller);
        self::assertStringContainsString('accept="image/jpeg,image/png"', $editor);
        self::assertStringContainsString('F_tmf_user_photo_store', $editor);
    }
}
