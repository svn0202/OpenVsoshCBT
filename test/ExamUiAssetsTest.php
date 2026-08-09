<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;

final class ExamUiAssetsTest extends TestCase
{
    public function testAdministratorThemeHasIconsDashboardAndLiveAppearancePreview(): void
    {
        $menu = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_menu.php');
        $dashboard = (string) file_get_contents(__DIR__ . '/../admin/code/index.php');
        $settings = (string) file_get_contents(__DIR__ . '/../admin/code/tce_onboarding_settings.php');
        $script = (string) file_get_contents(__DIR__ . '/../admin/jscripts/appearance-preview.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../admin/styles/admin-responsive.css');
        $publicStylesheet = (string) file_get_contents(__DIR__ . '/../public/styles/tmf-reference.css');

        self::assertStringContainsString('function f_menu_icon_svg', $menu);
        self::assertStringContainsString('class="menu-icon"', $menu);
        self::assertStringContainsString('class="menu-description"', $menu);
        self::assertStringContainsString('class="admin-dashboard"', $dashboard);
        self::assertStringContainsString('class="settings-preview"', $settings);
        self::assertStringContainsString('name="admin_palette"', $settings);
        self::assertStringContainsString('name="login_background_overlay"', $settings);
        self::assertStringContainsString("preview.dataset.palette", $script);
        self::assertStringContainsString('body.admin-palette-forest', $stylesheet);
        self::assertStringContainsString('.dashboard-stats {', $stylesheet);
        self::assertStringContainsString('--login-background-position', $publicStylesheet);
    }

    public function testAdministratorCataloguesExposeUsefulNavigationAndBulkSelection(): void
    {
        $tests = (string) file_get_contents(__DIR__ . '/../admin/code/tce_functions_test_select.php');
        $users = (string) file_get_contents(__DIR__ . '/../admin/code/tce_functions_user_select.php');
        $script = (string) file_get_contents(__DIR__ . '/../admin/jscripts/admin-navigation.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../admin/styles/admin-responsive.css');

        foreach ([$tests, $users] as $catalogue) {
            self::assertStringContainsString('class="userselect record-table"', $catalogue);
            self::assertStringContainsString('data-record-href="', $catalogue);
            self::assertStringContainsString('data-bulk-toolbar', $catalogue);
            self::assertStringContainsString('$itemcount = $firstrow;', $catalogue);
        }
        self::assertStringContainsString('data-select-all="testid"', $tests);
        self::assertStringContainsString("\$status_key = 'active'", $tests);
        self::assertStringContainsString('data-select-all="userid"', $users);
        self::assertStringContainsString('>Результаты</a>', $users);
        self::assertStringContainsString("document.querySelectorAll('[data-record-href]')", $script);
        self::assertStringContainsString("document.querySelectorAll('[data-select-all]')", $script);
        self::assertStringContainsString('.record-bulk-toolbar.has-selection', $stylesheet);
    }

    public function testSectionLandingPagesUseNavigableTaskCards(): void
    {
        $pages = [
            __DIR__ . '/../admin/code/tce_menu_tests.php',
            __DIR__ . '/../admin/code/tce_menu_users.php',
            __DIR__ . '/../admin/code/tce_menu_modules.php',
            __DIR__ . '/../public/code/tce_page_user.php',
        ];
        foreach ($pages as $page) {
            self::assertStringContainsString('class="section-link-grid"', (string) file_get_contents($page));
        }

        $adminStylesheet = (string) file_get_contents(__DIR__ . '/../admin/styles/admin-responsive.css');
        $publicStylesheet = (string) file_get_contents(__DIR__ . '/../public/styles/tmf-reference.css');
        self::assertStringContainsString('.admin-shell .section-link-grid {', $adminStylesheet);
        self::assertStringContainsString('body.app-page .section-link-grid {', $publicStylesheet);
    }

    public function testTestWorkflowKeepsContextAcrossOperationalPages(): void
    {
        $helper = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_test.php');
        self::assertStringContainsString('function f_openvsosh_admin_test_context', $helper);
        self::assertStringContainsString('class="test-context-nav"', $helper);
        self::assertStringContainsString("'monitor' => ['tce_monitor.php'", $helper);
        self::assertStringContainsString("'results' => ['tce_show_result_allusers.php'", $helper);

        $pages = [
            'tce_edit_test.php' => "'settings'",
            'tce_test_access_rules.php' => "'access'",
            'tce_monitor.php' => "'monitor'",
            'tce_show_result_allusers.php' => "'results'",
            'tce_pregenerate.php' => "'generation'",
            'tce_offline.php' => "'offline'",
        ];
        foreach ($pages as $page => $section) {
            $source = (string) file_get_contents(__DIR__ . '/../admin/code/' . $page);
            self::assertStringContainsString('f_openvsosh_admin_test_context(', $source);
            self::assertStringContainsString($section, $source);
        }
    }

    public function testTestEditorAndMonitorSupportFocusedWorkflows(): void
    {
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_test.php');
        $monitor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_monitor.php');
        $script = (string) file_get_contents(__DIR__ . '/../admin/jscripts/admin-navigation.js');

        self::assertStringContainsString('class="editor-section-nav"', $editor);
        self::assertStringContainsString('class="row editor-sticky-actions"', $editor);
        self::assertStringContainsString('data-editor-save-state', $editor);
        self::assertStringContainsString('data-monitor-refresh="30"', $monitor);
        self::assertStringContainsString('data-relative-time', $monitor);
        self::assertStringContainsString('class="monitor-progress"', $monitor);
        self::assertStringContainsString('value="extend-15"', $monitor);
        self::assertStringContainsString("window.addEventListener('beforeunload'", $script);
        self::assertStringContainsString("document.querySelectorAll('[data-relative-time]')", $script);
    }

    public function testParticipantResultsHaveSummaryAndQuestionFilters(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../public/code/tce_test_results.php');
        $script = (string) file_get_contents(__DIR__ . '/../shared/jscripts/public-app-shell.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../public/styles/tmf-reference.css');

        self::assertStringContainsString('class="result-hero"', $controller);
        self::assertStringContainsString('data-result-filter="incorrect"', $controller);
        self::assertStringContainsString('data-result-state="', $controller);
        self::assertStringContainsString("document.querySelectorAll('[data-result-filter]')", $script);
        self::assertStringContainsString('.result-question-list > .result-question-incorrect', $stylesheet);
    }

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
            self::assertSame($playsLeft, $resource['ov_audio_plays_left'] ?? null);
            self::assertSame($limitExhausted, $resource['ov_audio_limit_exhausted'] ?? null);
        }
    }

    public function testTestEditorOffersBulkSubjectSelection(): void
    {
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_test.php');

        self::assertStringContainsString('id="select_all_subjects"', $editor);
        self::assertStringContainsString('id="clear_all_subjects"', $editor);
        self::assertStringContainsString('option.value.charAt(0)!=="#"', $editor);
        self::assertStringContainsString('type="search" id="subject_filter"', $editor);
        self::assertStringContainsString('filter.addEventListener("input",filterSubjects)', $editor);
        self::assertStringContainsString('option.text.toLocaleLowerCase().indexOf(query)!==-1', $editor);
    }

    public function testTestEditorFiltersGroupsByPartialName(): void
    {
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_test.php');

        self::assertStringContainsString('type="search" id="user_groups_filter"', $editor);
        self::assertStringContainsString("addEventListener('input', JF_filter_user_groups)", $editor);
        self::assertStringContainsString('option.text.toLocaleLowerCase().indexOf(query)!==-1', $editor);
        self::assertStringContainsString('option.hidden=!visible', $editor);
    }

    public function testTestEditorInitializesTheStoredPasswordBeforeAddingATest(): void
    {
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_test.php');

        self::assertStringContainsString("is_string(\$_POST['test_password'])", $editor);
        self::assertStringContainsString('F_empty_to_null($test_password)', $editor);
    }

    public function testExamRendererRemovesQuestionMetadataBeforeDisplay(): void
    {
        $renderer = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_test.php');

        self::assertStringContainsString(
            '$question_description = F_tmf_question_editor_description($question_description);',
            $renderer,
        );
        self::assertStringContainsString(
            "\$str .= F_decode_tcecode(\$question_description)",
            $renderer,
        );
    }

    public function testExamCountdownRemainsVisibleInTheApplicationTheme(): void
    {
        $timer = (string) file_get_contents(__DIR__ . '/../shared/code/tce_page_timer.php');
        $script = (string) file_get_contents(__DIR__ . '/../shared/jscripts/timer.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../public/styles/tmf-reference.css');

        self::assertStringContainsString("\$timer_label = \$is_exam_timer ? \$l['w_remaining']", $timer);
        self::assertStringContainsString('timer.js?v=20260729-1', $timer);
        self::assertStringContainsString(
            'body.app-page.exam-page .tmf-timer {',
            $stylesheet,
        );
        self::assertStringContainsString(
            'body.app-page.exam-page .tmf-timer #timer {',
            $stylesheet,
        );
        self::assertStringContainsString(
            '`${pad2(hours)}:${pad2(minutes)}:${pad2(seconds)}`',
            $script,
        );
    }

    public function testReviewControlReplacesConfirmAndHighlightsQuestionList(): void
    {
        $renderer = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_test.php');
        $script = (string) file_get_contents(__DIR__ . '/../shared/jscripts/mobile-exam.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../public/styles/tmf-reference.css');

        self::assertStringContainsString('exam-review-toggle exam-review-nav', $renderer);
        self::assertStringContainsString("array_unshift(\$item_classes, 'selected')", $renderer);
        self::assertStringNotContainsString('name="confirmanswer"', $renderer);
        self::assertStringContainsString('exam-question-menu-description', $renderer);
        self::assertStringContainsString(
            'class="tcecontentbox exam-question-list" open="open"',
            $renderer,
        );
        self::assertStringContainsString(
            "form.querySelector('[data-exam-review]')",
            $script,
        );
        self::assertStringContainsString(
            '.exam-question-list li.marked-for-review {',
            $stylesheet,
        );
        self::assertStringContainsString(
            '.navlink .exam-review-nav,',
            $stylesheet,
        );
        self::assertStringContainsString('flex: 1 1 0;', $stylesheet);
        self::assertStringContainsString('@media (min-width: 1000px)', $stylesheet);
        self::assertStringContainsString(
            '.exam-question-list li.marked-for-review > input[id^="jumpquestion_"]',
            $stylesheet,
        );
        self::assertStringContainsString('box-sizing: border-box;', $stylesheet);
        self::assertStringContainsString('width: 170px;', $stylesheet);
        self::assertStringContainsString('right: 24px;', $stylesheet);
        self::assertStringContainsString('justify-content: space-evenly;', $stylesheet);
        self::assertStringContainsString(
            '.answer-save-status:empty { display: none; }',
            $stylesheet,
        );
        self::assertStringContainsString('position: absolute;', $stylesheet);
    }

    public function testInterruptedExamCanBeResumedAndSaveConflictsDoNotPersist(): void
    {
        $renderer = (string) file_get_contents(__DIR__ . '/../shared/code/tce_functions_test.php');
        $controller = (string) file_get_contents(__DIR__ . '/../public/code/tce_test_execute.php');
        $script = (string) file_get_contents(__DIR__ . '/../shared/jscripts/mobile-exam.js');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../public/styles/tmf-reference.css');

        self::assertStringContainsString("class=\"xmlbutton\"", $renderer);
        self::assertStringContainsString(
            'table.testlist tr.test-card-progress td:nth-child(5)',
            $stylesheet,
        );
        self::assertStringContainsString('display: flex;', $stylesheet);
        self::assertStringContainsString('min-width: 132px;', $stylesheet);
        self::assertStringContainsString('white-space: nowrap;', $stylesheet);
        self::assertStringContainsString('data-answer-save-error="1"', $controller);
        self::assertStringContainsString(
            "document.querySelectorAll('[data-answer-save-error]')",
            $script,
        );
        self::assertStringContainsString("if (error.message === 'conflict')", $script);
        self::assertStringContainsString('return loadQuestion(target);', $script);
        self::assertStringContainsString('function bindQuestionMenu()', $script);
        self::assertStringContainsString("item.classList.add('question-menu-link')", $script);
        self::assertStringContainsString('form.requestSubmit(submitter)', $script);
        self::assertStringContainsString(
            '.exam-question-list li.question-menu-link {',
            $stylesheet,
        );
    }

    public function testParticipantPhotosAreServedThroughAnAuthorizedController(): void
    {
        $controller = (string) file_get_contents(__DIR__ . '/../public/code/tce_user_photo.php');
        $editor = (string) file_get_contents(__DIR__ . '/../admin/code/tce_edit_user.php');

        self::assertStringContainsString('K_AUTH_ADMIN_USERS', $controller);
        self::assertStringContainsString("header('Content-Type: image/jpeg')", $controller);
        self::assertStringContainsString('accept="image/jpeg,image/png"', $editor);
        self::assertStringContainsString('f_tmf_user_photo_store', $editor);
    }
}
