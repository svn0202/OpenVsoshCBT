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
        self::assertStringContainsString('toolbar.dataset.audioPlaysLeft', $script);
        self::assertStringContainsString('toolbar.dataset.audioLimitExhausted', $script);
        self::assertStringContainsString("toolbar.dataset.autoFullscreen === '1'", $script);
        self::assertStringContainsString("payload, 'live_score'", $script);

        $renderer = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_test.php');
        self::assertStringContainsString('data-audio-plays-left="', $renderer);
        self::assertStringContainsString('data-audio-limit-exhausted="', $renderer);
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
            self::assertStringContainsString('"Noto Sans Arabic"', $css);
            self::assertStringContainsString('unicode-bidi: plaintext', $css);
            self::assertStringContainsString(
                '../../shared/fonts/noto-sans-arabic.woff2',
                $css,
            );
        }
    }

    public function testArabicWebFontIsBundledAndLicensed(): void
    {
        $font = (string) file_get_contents(
            __DIR__ . '/../shared/fonts/noto-sans-arabic.woff2',
        );
        self::assertStringStartsWith('wOF2', $font);
        self::assertGreaterThan(100_000, strlen($font));

        $license = (string) file_get_contents(
            __DIR__ . '/../shared/fonts/OFL-NotoSansArabic.txt',
        );
        self::assertStringContainsString('SIL OPEN FONT LICENSE Version 1.1', $license);

        foreach (['default.css', 'default_rtl.css'] as $stylesheet) {
            $css = (string) file_get_contents(__DIR__ . '/../admin/styles/' . $stylesheet);
            self::assertStringContainsString(
                '../../shared/fonts/noto-sans-arabic.woff2',
                $css,
            );
        }
    }

    public function testListeningMessagesHaveEnglishRussianAndArabicTranslations(): void
    {
        require_once __DIR__ . '/../shared/code/tce_tmx.php';
        $tmx = __DIR__ . '/../shared/config.default/lang/language_tmx.xml';
        $expected = [
            'EN' => ['Plays remaining: {count}', 'Audio play limit reached'],
            'RU' => ['Осталось воспроизведений: {count}', 'Лимит воспроизведений исчерпан'],
            'AR' => ['مرات التشغيل المتبقية: {count}', 'تم استنفاد حد تشغيل الصوت'],
        ];
        foreach ($expected as $language => [$playsLeft, $limitExhausted]) {
            $resource = (new \TMXResourceBundle($tmx, $language, ''))->getResource();
            self::assertSame($playsLeft, $resource['ov_audio_plays_left']);
            self::assertSame($limitExhausted, $resource['ov_audio_limit_exhausted']);
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
