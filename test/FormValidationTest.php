<?php

//============================================================+
// File name   : FormValidationTest.php
// Begin       : 2026-06-23
//
// Description : Unit tests for the server-side form-field format validation
//               (shared/code/tce_functions_form.php) — Option C registry.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * @file
 * Tests for F_check_fields_format() and the canonical pattern registry.
 * @package com.tecnick.tcexam.test
 */
final class FormValidationTest extends TestCase
{
    public function testFormFieldDecoderReturnsCurrentRequestData(): void
    {
        $request = ['user_email' => 'student@example.com', 'group_id' => '7'];
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$_REQUEST = ["user_email" => "student@example.com", "group_id" => "7"]; '
                    . 'require $argv[1]; echo json_encode(F_decode_form_fields());',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertSame($request, json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRequiredFieldCheckPreservesNoRulesAndCompleteFormResults(): void
    {
        $this->assertFalse(\F_check_required_fields([]));
        $this->assertSame('', \F_check_required_fields([
            'ff_required' => 'user_email',
            'ff_required_labels' => 'Email',
            'user_email' => 'student@example.com',
        ]));
    }

    public function testRequiredFieldCheckPreservesMissingFieldMessage(): void
    {
        $this->assertSame(' user_email', \F_check_required_fields([
            'ff_required' => 'user_email',
            'ff_required_labels' => '',
        ]));
    }

    public function testRequiredFieldCheckIgnoresNonStringLabels(): void
    {
        $this->assertSame(' user_email', \F_check_required_fields([
            'ff_required' => 'user_email',
            'ff_required_labels' => ['Email'],
        ]));
    }

    public function testSubmitButtonPrintsExistingMarkup(): void
    {
        ob_start();
        \F_submit_button('save', 'Save', 'Save item', 'disabled ');
        $markup = ob_get_clean();

        $this->assertSame(
            '<input type="submit" name="save" id="save" value="Save" title="Save item" disabled />',
            $markup,
        );
    }

    public function testCsrfFieldContainsAValidToken(): void
    {
        $markup = \f_get_csrf_token_field();
        $matches = [];

        $this->assertSame(1, preg_match('/ value="([^"]+)"/', $markup, $matches));
        $token = $matches[1] ?? '';
        $this->assertNotSame('', $token);
        $this->assertTrue(\check_csrf_token($token));
    }

    public function testOptionalFieldHasNoRequiredMarker(): void
    {
        $this->assertSame('', \get_required_mark(false));
    }

    public function testRequiredFieldMarkerPreservesTranslatedAccessibleMarkup(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$GLOBALS["l"] = ["w_required" => "Required", "a_meta_charset" => "UTF-8"]; '
                    . 'require $argv[1]; echo get_required_mark(true);',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $markup);
        self::assertSame(' <abbr class="required" title="Required">*</abbr>', $markup);
    }

    public function testDescriptionLinePreservesExistingMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        $markup = \get_form_description_line('Score:', 'Total score', '42');

        $this->assertStringContainsString('<span title="Total score">Score:</span>', $markup);
        $this->assertStringContainsString('<span class="formw">' . K_NEWLINE . '42&nbsp;', $markup);
    }

    public function testNoscriptSelectPreservesFieldNameAndMarkup(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require $argv[1]; echo get_form_noscript_select("selectcategory");',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        $this->assertSame(0, $status, $markup);
        $this->assertStringStartsWith("<noscript>\n", $markup);
        $this->assertStringContainsString('name="selectcategory" id="selectcategory"', $markup);
        $this->assertStringEndsWith("</noscript>\n", $markup);
    }

    public function testFixedValueRowPreservesReadonlyValueAndHiddenField(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require $argv[1]; '
                    . '$name = function_exists("getFormRowFixedValue") '
                    . '? "getFormRowFixedValue" : "get_form_row_fixed_value"; '
                    . 'echo $name("user_ip", "IP address", "Client IP", "Read only", '
                    . '"127.0.0.1&local", false, "PREFIX");',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        $this->assertSame(0, $status, $markup);
        $this->assertStringContainsString(
            '<label for="DISABLED_user_ip" title="Client IP">IP address</label>',
            $markup,
        );
        $this->assertStringContainsString(
            'name="DISABLED_user_ip" id="DISABLED_user_ip" class="disabled" '
                . 'value="127.0.0.1&amp;local" size="20"',
            $markup,
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="user_ip" id="user_ip" value="127.0.0.1&amp;local" />',
            $markup,
        );
    }

    public function testDisabledCheckboxRowPreservesCheckedAndHiddenValues(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require $argv[1]; require $argv[2]; '
                    . '$name = function_exists("get_form_row_checkbox") '
                    . '? "get_form_row_checkbox" : "get_form_row_checkbox"; '
                    . 'echo $name("enabled", "Enabled", "Account status", "Locked", '
                    . '"1&x", true, true, "PREFIX");',
                dirname(__DIR__) . '/shared/code/tce_functions_general.php',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        $this->assertSame(0, $status, $markup);
        $this->assertStringContainsString(
            'readonly="readonly" class="disabled" name="DISABLED_enabled" id="DISABLED_enabled" '
                . 'value="1&x" checked="checked"',
            $markup,
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="enabled" id="enabled" value="1&amp;x" />',
            $markup,
        );
        $this->assertStringContainsString('id="desc_DISABLED_enabled">Locked</span>', $markup);
    }

    public function testSmallVerticalSpacePreservesExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        $this->assertSame('<div class="row">&nbsp;</div>' . K_NEWLINE, \get_form_small_vert_space());
    }

    public function testSmallDividerSpacePreservesExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        $this->assertSame(
            '<div style="clear:both;height:1px;font-size:1px;">&nbsp;</div>' . K_NEWLINE,
            \get_form_small_div_space(),
        );
    }

    public function testRowVerticalSpacePreservesExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        $this->assertSame(
            '<div class="row" style="margin-bottom:5px;"><hr class="dashed"/></div>' . K_NEWLINE,
            \get_form_row_vert_space(),
        );
    }

    public function testRowVerticalDividerPreservesTitleAndExactMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        $this->assertSame(
            '<div class="row"><hr class="dashed"/></div>'
                . '<div class="row"><div style="color:#666666;text-align:center;">Section</div></div>'
                . K_NEWLINE,
            \get_form_row_vert_div('Section'),
        );
    }

    public function testUploadFileRowPreservesNamesAndOnChangeMarkup(): void
    {
        if (!defined('K_NEWLINE')) {
            define('K_NEWLINE', "\n");
        }

        $markup = \get_form_upload_file('upload[]', 'upload_id', 'Upload', 'Upload a file', 'preview(this)');

        $this->assertStringContainsString('<div class="row" id="divupload_id">', $markup);
        $this->assertStringContainsString(
            '<label for="upload_id" title="Upload a file">Upload</label>',
            $markup,
        );
        $this->assertStringContainsString(
            'type="file" name="upload[]" id="upload_id" size="20" title="Upload a file" '
                . 'onchange="preview(this)"',
            $markup,
        );
    }

    public function testTextBoxRowPreservesRequiredReadonlyAndEscapedValue(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require $argv[1]; $GLOBALS["l"] = ["w_required" => "Required", '
                    . '"a_meta_charset" => "UTF-8"]; '
                    . '$name = function_exists("get_form_row_text_box") '
                    . '? "get_form_row_text_box" : "get_form_row_text_box"; '
                    . 'echo $name("comment", "Comment", "Review comment", "<b>&", true, "PREFIX", true);',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        $this->assertSame(0, $status, $markup);
        $this->assertStringContainsString(
            '<label for="comment" title="Review comment">Comment '
                . '<abbr class="required" title="Required">*</abbr></label>',
            $markup,
        );
        $this->assertStringContainsString(
            'name="comment" id="comment" title="Review comment" aria-required="true" '
                . 'readonly="readonly" class="disabled">&lt;b&gt;&amp;</textarea>',
            $markup,
        );
    }

    public function testSelectBoxRowPreservesSelectionRequiredStateAndTip(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require $argv[1]; $GLOBALS["l"] = ["w_required" => "Required", '
                    . '"a_meta_charset" => "UTF-8"]; '
                    . '$name = function_exists("get_form_row_select_box") '
                    . '? "get_form_row_select_box" : "get_form_row_select_box"; '
                    . 'echo $name("level", "Level", "User level", "Choose", "01", '
                    . '[0 => "Zero", 1 => "One", 2 => ["invalid"]], "PREFIX", true);',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        $this->assertSame(0, $status, $markup);
        $this->assertStringContainsString(
            'name="level" id="level" title="User level" aria-required="true" '
                . 'aria-describedby="desc_level"',
            $markup,
        );
        $this->assertStringContainsString('<option value="1" selected="selected">One</option>', $markup);
        $this->assertStringContainsString('<option value="2"></option>', $markup);
        $this->assertStringContainsString('<span class="labeldesc" id="desc_level">Choose</span>', $markup);
    }

    public function testTextInputRowPreservesDatetimeAttributesAndFormatLabel(): void
    {
        [$status, $markup] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                'require $argv[1]; $GLOBALS["l"] = ["w_required" => "Required", '
                    . '"w_datetime_format" => "Date and time", "a_meta_charset" => "UTF-8"]; '
                    . '$name = function_exists("get_form_row_text_input") '
                    . '? "get_form_row_text_input" : "get_form_row_text_input"; '
                    . 'echo $name("starts_at", "Starts", "Start time", "", "2026-08-10 12:34:56", '
                    . '"", 255, false, true, false, "", true, "off", "email", "Choose & confirm");',
                dirname(__DIR__) . '/shared/code/tce_functions_form.php',
            ],
            dirname(__DIR__) . '/public/code',
        );

        $this->assertSame(0, $status, $markup);
        $this->assertStringContainsString(
            'type="datetime-local" step="1" name="starts_at" id="starts_at" '
                . 'value="2026-08-10T12:34:56" size="20" maxlength="19" title="Start time" '
                . 'aria-required="true" autocomplete="off" placeholder="Choose &amp; confirm" '
                . 'aria-describedby="desc_starts_at"',
            $markup,
        );
        $this->assertStringContainsString(
            '<span class="labeldesc" id="desc_starts_at">Date and time</span>',
            $markup,
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="xl_starts_at" id="xl_starts_at" value="Starts" />',
            $markup,
        );
    }

    public function testSelectOptionMatchingPreservesLegacyScalarCoercion(): void
    {
        $this->assertTrue(\f_form_option_is_selected(1, '1'));
        $this->assertTrue(\f_form_option_is_selected('01', 1));
        $this->assertTrue(\f_form_option_is_selected('0', false));
        $this->assertTrue(\f_form_option_is_selected('', null));
        $this->assertFalse(\f_form_option_is_selected(1, ['1']));
    }

    public function testFormCurrencyUsesMachineReadableDecimalFormat(): void
    {
        $this->assertSame('1234.50', \f_format_form_currency('1234.5', 2));
    }

    public function testValidValuesPass(): void
    {
        $fields = [
            'user_email' => 'john.doe+tag@example.com',
            'newpassword' => 'longenough',
            'user_birthdate' => '1990-12-31',
            'test_begin_time' => '2026-01-15T09:30',
            'test_end_time' => '2026-01-15 09:30:00',
            'test_duration_time' => '3600',
            'test_ip_range' => '192.168.0.1,10.0.0.*',
            'test_score_right' => '1.5',
            'test_score_wrong' => '-0.25',
            'tsubset_quantity' => '10',
            'question_timer' => '60',
            'testlog_score' => '+2',
        ];
        $this->assertSame('', \F_check_fields_format($fields));
    }

    public function testInvalidValuesAreFlagged(): void
    {
        $this->assertSame('user_email', \F_check_fields_format(['user_email' => 'not-an-email']));
        $this->assertSame('newpassword', \F_check_fields_format(['newpassword' => 'short'])); // < 8 chars
        $this->assertSame('user_birthdate', \F_check_fields_format(['user_birthdate' => '1990-1-5']));
        $this->assertSame('test_begin_time', \F_check_fields_format(['test_begin_time' => '2026-01-15']));
        $this->assertSame('question_timer', \F_check_fields_format(['question_timer' => '12a']));
        $this->assertSame('test_score_right', \F_check_fields_format(['test_score_right' => 'abc']));
        $this->assertSame('test_ip_range', \F_check_fields_format(['test_ip_range' => '192.168.0.1/24']));
    }

    public function testMultipleWrongFieldsAreListed(): void
    {
        $result = \F_check_fields_format(['user_email' => 'bad', 'question_timer' => 'x']);
        $this->assertStringContainsString('user_email', $result);
        $this->assertStringContainsString('question_timer', $result);
        $this->assertStringContainsString(', ', $result); // comma-separated
    }

    public function testEmptyValueIsSkipped(): void
    {
        $this->assertSame('', \F_check_fields_format(['user_email' => '']));
    }

    public function testNonScalarValueIsSkipped(): void
    {
        // an array-valued field must not crash strlen()/preg_match() (PHP 8 throws on array args)
        $this->assertSame('', \F_check_fields_format(['user_email' => ['a', 'b']]));
    }

    public function testUnknownFieldIsNotValidated(): void
    {
        // fields absent from the registry are ignored entirely
        $this->assertSame('', \F_check_fields_format(['some_random_field' => '!!! not validated !!!']));
    }

    public function testLabelFromXlIsUsedInErrorMessage(): void
    {
        $result = \F_check_fields_format(['user_email' => 'bad', 'xl_user_email' => 'Email Address']);
        $this->assertSame('Email Address', $result);
    }

    // --- Security properties (Option C) ---

    public function testTamperedClientPatternIsIgnored(): void
    {
        // attacker swaps the round-tripped regex for a permissive one to smuggle a bad email;
        // the server uses its own canonical pattern, so the field is still flagged.
        $result = \F_check_fields_format(['user_email' => 'not-an-email', 'x_user_email' => '^.*$']);
        $this->assertSame('user_email', $result);
    }

    public function testOmittedClientPatternStillValidates(): void
    {
        // dropping x_<field> no longer skips the check (the old bypass) — the registry decides.
        $result = \F_check_fields_format(['user_email' => 'not-an-email']);
        $this->assertSame('user_email', $result);
    }

    public function testCatastrophicClientPatternIsNeverExecuted(): void
    {
        // a classic catastrophic-backtracking regex supplied by the client must be ignored; the
        // value is matched against the safe canonical integer pattern instead (and fails fast).
        $fields = [
            'question_timer' => str_repeat('a', 40) . '!',
            'x_question_timer' => '^(a+)+$',
        ];
        $this->assertSame('question_timer', \F_check_fields_format($fields));
    }

    public function testOverLongValueIsRejected(): void
    {
        // an all-digits value would satisfy the integer pattern, but exceeding the length cap is
        // treated as invalid so an unbounded POST cannot drive worst-case matching cost.
        $this->assertSame('question_timer', \F_check_fields_format(['question_timer' => str_repeat('1', 5000)]));
        // ...while a long-but-bounded valid value still passes.
        $this->assertSame('', \F_check_fields_format(['question_timer' => str_repeat('1', 100)]));
    }

    public function testRegistryCoversTheKnownValidatedFields(): void
    {
        $registry = \F_get_field_format_registry();
        foreach (['user_email', 'newpassword', 'user_birthdate', 'test_begin_time', 'test_ip_range', 'question_timer'] as $field) {
            $this->assertArrayHasKey($field, $registry);
        }
    }
}
