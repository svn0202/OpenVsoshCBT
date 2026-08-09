<?php

//============================================================+
// File name   : tce_functions_form.php
// Begin       : 2001-11-07
// Last Update : 2023-11-30
//
// Description : Functions to handle XHTML Form Fields.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to handle XHTML Form Fields.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2001-11-07
 */

$formstatus = true; //reset form status

// check buttons actions
if (isset($_POST['update'])) {
    $menu_mode = 'update';
} elseif (isset($_POST['delete'])) {
    $menu_mode = 'delete';
} elseif (isset($_POST['forcedelete'])) {
    $menu_mode = 'forcedelete';
} elseif (isset($_POST['cancel'])) {
    $menu_mode = 'cancel';
} elseif (isset($_POST['add'])) {
    $menu_mode = 'add';
} elseif (isset($_POST['clear'])) {
    $menu_mode = 'clear';
} elseif (isset($_POST['upload'])) {
    $menu_mode = 'upload';
} elseif (isset($_POST['addquestion'])) {
    $menu_mode = 'addquestion';
} elseif (isset($_POST['deletesubject'])) {
    $menu_mode = 'deletesubject';
}

if (empty($menu_mode)) {
    $menu_mode = '';
}

// Every non-empty POST reaching the shared form controller is state-changing or participates in
// a state-changing workflow. Validate it independently of the button name: several controllers
// use custom actions (backup, restore, lock, unlock, exam navigation, and others) that are not in
// the legacy menu_mode list above.
if (
    PHP_SAPI !== 'cli'
    && strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && $_POST !== []
    && (
        empty($_POST['csrf_token'])
        || !is_string($_POST['csrf_token'])
        || !check_csrf_token($_POST['csrf_token'])
    )
) {
    http_response_code(403);
    exit();
}

define('K_EMAIL_RE_PATTERN', '^([a-zA-Z0-9_\.\-\+\%]+)@([a-zA-Z0-9\.\-]+)$');

/**
 * Return a scalar form translation as a string.
 */
function f_get_form_translation(string $key, string $fallback = ''): string
{
    global $l;
    if (!is_array($l) || !isset($l[$key]) || !is_scalar($l[$key])) {
        return $fallback;
    }

    return (string) $l[$key];
}

/**
 * Format a decimal value for a machine-readable form field.
 */
function f_format_form_currency(string $value, int $decimals): string
{
    if (!is_numeric($value)) {
        return number_format(0.0, $decimals, '.', '');
    }

    return number_format((float) $value, $decimals, '.', '');
}

/**
 * Returns an array containing form fields.
 * @return array<array-key, mixed> containing form fields
 */
function f_decode_form_fields(): array
{
    return $_REQUEST;
}

/**
 * Check Required Form Fields.<br>
 * Returns a string containing a list of missing fields (comma separated).
 * @param array<array-key, mixed> $formfields input array containing form fields
 * @return string|false comma-separated missing fields, or false when no fields are required
 */
function f_check_required_fields(array $formfields): string|false
{
    if (!is_string($formfields['ff_required'] ?? null) || $formfields['ff_required'] === '') {
        return false;
    }

    $required = $formfields['ff_required'];
    $missing_fields = '';
    $required_fields = explode(',', $required);
    $required_fields_labels = isset($formfields['ff_required_labels']) && is_string($formfields['ff_required_labels'])
        ? explode(',', $formfields['ff_required_labels'])
        : [];
    // form fields labels
    foreach ($required_fields as $i => $required_field) { //for each required field
        $fieldname = preg_replace('/[^a-z0-9_\[\]]/i', '', trim($required_field)) ?? '';
        if (
            !array_key_exists($fieldname, $formfields)
            || !is_scalar($formfields[$fieldname])
            || trim((string) $formfields[$fieldname]) === ''
        ) { //if is empty
            $label = $required_fields_labels[$i] ?? '';
            if ($label !== '' && $label !== '0') { // check if the field has a label
                $charset = f_get_form_translation('a_meta_charset', 'UTF-8');
                $fieldname = htmlspecialchars($label, ENT_NOQUOTES, $charset);
            }

            $missing_fields .= ', ' . stripslashes($fieldname);
        }
    }

    if (strlen($missing_fields) > 1) {
        $missing_fields = substr($missing_fields, 1); // cuts first comma
    }

    return $missing_fields;
}

/**
 * Server-side registry of canonical field-format patterns, keyed by form field name.
 *
 * This is the single source of truth used by F_check_fields_format() to validate submitted
 * values. The matching pattern is resolved here, on the server, by field name.
 *
 * Field names are static across the application, so a flat "name => un-delimited regex" map is
 * sufficient. Configurable constants are reused where they already exist.
 *
 * @return array<string,string> map of field name to un-delimited regular expression
 */
function f_get_field_format_registry(): array
{
    // Canonical, server-authored patterns (the password pattern is admin-configurable).
    $re_email = K_EMAIL_RE_PATTERN;
    $re_password = defined('K_USRREG_PASSWORD_RE') ? K_USRREG_PASSWORD_RE : '^(.{8,})$';
    $re_int = '^([0-9]*)$';
    $re_decimal = '^([0-9\+\-]*)([\.]?)([0-9]*)$';
    $re_iplist = '^([0-9a-fA-F,\:\.\*-]*)$';
    $re_date = '^([0-9]{4})([\-])([0-9]{2})([\-])([0-9]{2})$';
    $re_datetime = '^([0-9]{4})([\-])([0-9]{2})([\-])([0-9]{2})([ T])([0-9]{2})([\:])([0-9]{2})(([\:])([0-9]{2}))?$';

    return [
        // user identity / credentials
        'user_email' => $re_email,
        'newpassword' => $re_password,
        'new_test_password' => $re_password,
        'user_birthdate' => $re_date,
        // test definition
        'test_begin_time' => $re_datetime,
        'test_end_time' => $re_datetime,
        'test_duration_time' => $re_int,
        'test_ip_range' => $re_iplist,
        'test_score_right' => $re_decimal,
        'test_score_wrong' => $re_decimal,
        'test_score_unanswered' => $re_decimal,
        'test_score_threshold' => $re_decimal,
        'tsubset_quantity' => $re_int,
        'tsubset_answers' => $re_int,
        // question / rating
        'question_timer' => $re_int,
        'testlog_score' => $re_decimal,
    ];
}

/**
 * Check fields format against the server-side canonical pattern registry.<br>
 * Returns a string containing a list of wrong fields (comma separated).
 *
 * For every field present in F_get_field_format_registry() that was submitted with a non-empty
 * value, the value is matched against its canonical (server-authored) pattern. The client-supplied
 * 'x_<field>' value is ignored entirely, so a tampered/omitted/malicious pattern can neither bypass
 * validation nor be executed as a regular expression.
 *
 * @param mixed $formfields input value expected to contain form fields
 * @return string comma-separated list of wrong fields (empty when all valid)
 */
function f_check_fields_format(mixed $formfields): string
{
    if (!is_array($formfields) || empty($formfields)) {
        return '';
    }

    // Upper bound on the value length we will run a pattern against; bounds worst-case
    // backtracking cost so an over-long submitted value cannot stall the request (maxlength is
    // a client-only hint and is not enforced by the browser for a crafted POST).
    $maxvaluelen = 4096;

    $wrongfields = '';
    foreach (F_get_field_format_registry() as $fieldname => $pattern) {
        // only validate fields that were actually submitted with a non-empty scalar value
        if (!array_key_exists($fieldname, $formfields) || !is_scalar($formfields[$fieldname])) {
            continue;
        }

        $value = (string) $formfields[$fieldname];
        if ($value === '') {
            continue;
        }
        // an over-long value is treated as invalid rather than risk a costly match. Patterns are
        // server-authored constants, so preg_match needs no error suppression.
        $matches = strlen($value) <= $maxvaluelen ? preg_match('~' . $pattern . '~i', $value) : 0;
        // $matches === false means the (server-authored) pattern errored: treat as "skip" so a
        // bad pattern cannot silently reject every submission; only a clean 0 means "wrong format".
        if ($matches === 0) {
            $label = $fieldname;
            $xlabel_key = 'xl_' . $fieldname;
            if (isset($formfields[$xlabel_key]) && is_scalar($formfields[$xlabel_key])) {
                $xlabel = (string) $formfields[$xlabel_key]; // human label supplied by the form
                $charset = f_get_form_translation('a_meta_charset', 'UTF-8');
                if ($xlabel !== '') {
                    $label = htmlspecialchars($xlabel, ENT_NOQUOTES, $charset);
                }
            }

            $wrongfields .= ', ' . $label;
        }
    }

    if (strlen($wrongfields) > 1) {
        $wrongfields = substr($wrongfields, 2); // cuts first 2 chars (", ")
    }

    return $wrongfields;
}

/**
 * Check Form Fields.
 * see: F_check_required_fields, F_check_fields_format
 * @return bool false in case of error, true otherwise
 */
function f_check_form_fields(): bool
{
    require_once '../config/tce_config.php';
    $formfields = F_decode_form_fields(); //decode form fields
    //check missing fields
    if ($missing_fields = F_check_required_fields($formfields)) {
        $message = f_get_form_translation('m_form_missing_fields');
        F_print_error('WARNING', $message . ': ' . $missing_fields);

        return false;
    }

    //check fields format
    if ($wrong_fields = F_check_fields_format($formfields)) {
        $message = f_get_form_translation('m_form_wrong_fields');
        F_print_error('WARNING', $message . ': ' . $wrong_fields);

        return false;
    }

    return true;
}

/**
 * Returns XHTML code string to display a window close button
 * @param string $onclick additional javascript code to execute before closing the window.
 * @return string XHTML code
 */
function f_close_button(string $onclick = ''): string
{
    require_once '../config/tce_config.php';
    $str = '';
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<form action="' . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES) . '" id="closeform">' . K_NEWLINE;
    $str .= '<div>' . K_NEWLINE;
    $str .=
        '<input type="button" name="wclose" id="wclose" value="'
        . f_get_form_translation('w_close')
        . '" title="'
        . f_get_form_translation('h_close_window')
        . '" onclick="'
        . $onclick
        . 'window.close();" />'
        . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    $str .= '</form>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Prints the XHTML submit button.
 * @param mixed $name button name
 * @param mixed $value label for button
 * @param mixed $title button title, default=''
 * @param mixed $extra optional extra fields to add to the input tag, default=''
 *
 * @return void
 */
function f_submit_button(mixed $name, mixed $value, mixed $title = '', mixed $extra = ''): void
{
    $name = is_scalar($name) ? (string) $name : '';
    $value = is_scalar($value) ? (string) $value : '';
    $title = is_scalar($title) ? (string) $title : '';
    $extra = is_scalar($extra) ? (string) $extra : '';
    echo
        '<input type="submit" name="'
            . $name
            . '" id="'
            . $name
            . '" value="'
            . $value
            . '" title="'
            . $title
            . '" '
            . $extra
            . '/>'
    ;
}

/**
 * Returns XHTML code string to display the CSRF token field.
 * @return string XHTML code
 */
function f_get_csrf_token_field(): string
{
    return '<input type="hidden" name="csrf_token" id="csrf_token" value="' . f_get_csrf_token() . '" />';
}

/**
 * Returns the visual "required field" marker to append to a form label.
 * The control itself should also carry aria-required="true".
 * @param bool $required true if the field is required.
 * @return string XHTML code (empty string when the field is not required).
 */
function get_required_mark(bool $required = false): string
{
    if (!$required) {
        return '';
    }

    return (
        ' <abbr class="required" title="'
        . htmlspecialchars(
            f_get_form_translation('w_required'),
            ENT_QUOTES,
            f_get_form_translation('a_meta_charset', 'UTF-8'),
        )
        . '">*</abbr>'
    );
}

/**
 * Print input row form.
 * @param string $field_name Name of the form field.
 * @param mixed $name Label.
 * @param mixed $description Label description (tooltip).
 * @param mixed $tip Help to be displayed on the right of the input field.
 * @param mixed $value Initial value.
 * @param mixed $format Regular expression to check the format of the field.
 * @param int $maxlen Maximum input length.
 * @param bool $date True if the field is a date input.
 * @param bool $datetime True if the field is a date-time input.
 * @param bool $password True if the field is a password.
 * @param mixed $prefix code to be displayed after label.
 * @param bool $required If true the field is marked as required.
 * @param mixed $autocomplete HTML autocomplete token (e.g. 'email', 'username', 'current-password', 'new-password').
 * @param mixed $inputtype Override for the HTML input type (e.g. 'email', 'tel', 'number'); ignored for password/date/datetime fields.
 * @param mixed $placeholder Optional short hint displayed inside an empty input.
 * @return string
 */
function getFormRowTextInput(
    string $field_name,
    mixed $name,
    mixed $description = '',
    mixed $tip = '',
    mixed $value = '',
    mixed $format = '',
    int $maxlen = 255,
    bool $date = false,
    bool $datetime = false,
    #[\SensitiveParameter]
    bool $password = false,
    mixed $prefix = '',
    bool $required = false,
    mixed $autocomplete = '',
    mixed $inputtype = '',
    mixed $placeholder = '',
): string {
    require_once __DIR__ . '/../config/tce_config.php';
    $name = is_scalar($name) ? (string) $name : '';
    $description = is_scalar($description) ? (string) $description : '';
    $tip = is_scalar($tip) ? (string) $tip : '';
    $value = is_scalar($value) ? (string) $value : '';
    $format = is_scalar($format) ? (string) $format : '';
    $prefix = is_scalar($prefix) ? (string) $prefix : '';
    $autocomplete = is_scalar($autocomplete) ? (string) $autocomplete : '';
    $inputtype = is_scalar($inputtype) ? (string) $inputtype : '';
    $placeholder = is_scalar($placeholder) ? (string) $placeholder : '';
    $charset = f_get_form_translation('a_meta_charset', 'UTF-8');
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = ''; // string to return
    if ($date) {
        $format = '^([0-9]{4})([\-])([0-9]{2})([\-])([0-9]{2})$';
        $maxlen = 10;
        if (strlen($tip) === 0) {
            $tip = f_get_form_translation('w_date_format');
        }
    } elseif ($datetime) {
        // native datetime-local uses an ISO 'T' separator and may omit the seconds
        $format = '^([0-9]{4})([\-])([0-9]{2})([\-])([0-9]{2})([ T])([0-9]{2})([\:])([0-9]{2})(([\:])([0-9]{2}))?$';
        $maxlen = 19;
        if (strlen($tip) === 0) {
            $tip = f_get_form_translation('w_datetime_format');
        }
    }

    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    // the caller may supply its own required marker via $prefix; only add the default mark otherwise
    $str .=
        '<label for="'
        . $field_name
        . '" title="'
        . $description
        . '">'
        . $name
        . (empty($prefix) ? get_required_mark($required) : '')
        . '</label>'
        . K_NEWLINE;
    if (!empty($prefix)) {
        $str .= $prefix;
    }

    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .= '<input type="';
    if ($password) {
        $str .= 'password';
    } elseif ($date) {
        $str .= 'date';
    } elseif ($datetime) {
        $str .= 'datetime-local';
    } elseif (strlen($inputtype) > 0) {
        $str .= $inputtype;
    } else {
        $str .= 'text';
    }

    $str .= '"';
    if ($datetime) {
        $str .= ' step="1"';
    }

    if ($datetime) {
        // native datetime-local requires the ISO 'T' separator in the value attribute
        $value = str_replace(' ', 'T', $value);
    }

    $str .=
        ' name="'
        . $field_name
        . '" id="'
        . $field_name
        . '" value="'
        . htmlspecialchars($value, ENT_COMPAT, $charset)
        . '" size="20" maxlength="'
        . $maxlen
        . '" title="'
        . $description
        . '"';
    if ($required) {
        $str .= ' aria-required="true"';
    }

    if (strlen($autocomplete) > 0) {
        $str .= ' autocomplete="' . $autocomplete . '"';
    }

    if (strlen($placeholder) > 0) {
        $str .= ' placeholder="' . htmlspecialchars($placeholder, ENT_COMPAT, $charset) . '"';
    }

    if (strlen($tip) > 0) {
        $str .= ' aria-describedby="desc_' . $field_name . '"';
    }

    $str .= ' />';
    if (strlen($tip) > 0) {
        $str .= ' <span class="labeldesc" id="desc_' . $field_name . '">' . $tip . '</span>';
    }

    if (strlen($format) > 0) {
        // The value's format is validated server-side against a canonical pattern looked up by
        // field name (see F_get_field_format_registry()); the regex is no longer shipped to, nor
        // read back from, the client. Only the human label is emitted, used to name the field in
        // any "wrong format" error message.
        $str .=
            '<input type="hidden" name="xl_'
            . $field_name
            . '" id="xl_'
            . $field_name
            . '" value="'
            . $name
            . '" />'
            . K_NEWLINE;
    }

    $str .= '</span>' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;

    return $str;
}

/**
 * Print text box row form.
 * @param $field_name (string) Name of the form field.
 * @param string $name Label.
 * @param string $description Label description (tooltip).
 * @param string|null $value Initial value.
 * @param $disabled (boolean) If true disable the field.
 * @param $prefix (string) code to be displayed after label.
 * @param $required (boolean) If true the field is marked as required.
 * @return string
 */
function get_form_row_text_box(
    string $field_name,
    string $name,
    string $description = '',
    ?string $value = '',
    bool $disabled = false,
    string $prefix = '',
    bool $required = false,
): string {
    require_once __DIR__ . '/../config/tce_config.php';
    $charset = f_get_form_translation('a_meta_charset', 'UTF-8');
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = ''; // string to return
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    $str .=
        '<label for="'
        . $field_name
        . '" title="'
        . $description
        . '">'
        . $name
        . get_required_mark($required)
        . '</label>'
        . K_NEWLINE;
    if (!empty($prefix)) {
        $str .= $prefix;
    }

    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .=
        '<textarea cols="50" rows="5" name="' . $field_name . '" id="' . $field_name . '" title="' . $description . '"';
    if ($required) {
        $str .= ' aria-required="true"';
    }

    if ($disabled) {
        $str .= ' readonly="readonly" class="disabled"';
    }

    $str .= '>' . htmlspecialchars($value ?? '', ENT_NOQUOTES, $charset) . '</textarea>' . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Preserve the legacy loose comparison used for select option values without comparing mixed types.
 */
function f_form_option_is_selected(int|string $key, mixed $value): bool
{
    if (is_bool($value)) {
        return (bool) $key === $value;
    }

    if ($value === null) {
        return $key === 0 || $key === '';
    }

    if (is_int($value) || is_float($value) || is_string($value)) {
        return ($key <=> $value) === 0;
    }

    return false;
}

/**
 * Print select box row form.
 * @param $field_name (string) Name of the form field.
 * @param $name (string) Label.
 * @param $description (string) Label description (tooltip).
 * @param $tip (string) Help to be displayed on the right of the input field.
 * @param $value (string) Initial value.
 * @param array<array-key, mixed> $items array of items to print key => value.
 * @param $prefix (string) code to be displayed after label.
 * @return string
 */
function get_form_row_select_box(
    string $field_name,
    string $name,
    string $description = '',
    string $tip = '',
    mixed $value = '',
    array $items = [],
    string $prefix = '',
    bool $required = false,
): string {
    require_once __DIR__ . '/../config/tce_config.php';
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = ''; // string to return
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    $str .=
        '<label for="'
        . $field_name
        . '" title="'
        . $description
        . '">'
        . $name
        . get_required_mark($required)
        . '</label>'
        . K_NEWLINE;
    if (!empty($prefix)) {
        $str .= $prefix;
    }

    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .= '<select name="' . $field_name . '" id="' . $field_name . '" title="' . $description . '"';
    if ($required) {
        $str .= ' aria-required="true"';
    }

    if (strlen($tip) > 0) {
        $str .= ' aria-describedby="desc_' . $field_name . '"';
    }

    $str .= '>' . K_NEWLINE;
    foreach (array_keys($items) as $key) {
        $option_label = isset($items[$key]) && is_scalar($items[$key]) ? (string) $items[$key] : '';
        $str .= '<option value="' . $key . '"';
        if (f_form_option_is_selected($key, $value)) {
            $str .= ' selected="selected"';
        }

        $str .= '>' . $option_label . '</option>' . K_NEWLINE;
    }

    $str .= '</select>' . K_NEWLINE;
    if (strlen($tip) > 0) {
        $str .= ' <span class="labeldesc" id="desc_' . $field_name . '">' . $tip . '</span>';
    }

    $str .= '</span>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Print check box row form.
 * @param string $field_name Name of the form field.
 * @param mixed $name Label.
 * @param mixed $description Label description (tooltip).
 * @param mixed $tip Help to be displayed on the right of the input field.
 * @param mixed $value Initial value.
 * @param mixed $selected set to true if selected.
 * @param bool $disabled set to true to disable the field
 * @param mixed $prefix code to be displayed after label.
 * @return string
 */
function get_form_row_checkbox(
    string $field_name,
    mixed $name,
    mixed $description = '',
    mixed $tip = '',
    mixed $value = '',
    mixed $selected = false,
    bool $disabled = false,
    mixed $prefix = '',
): string {
    require_once __DIR__ . '/../config/tce_config.php';
    $charset = f_get_form_translation('a_meta_charset', 'UTF-8');
    $name = is_scalar($name) ? (string) $name : '';
    $description = is_scalar($description) ? (string) $description : '';
    $tip = is_scalar($tip) ? (string) $tip : '';
    $value = is_scalar($value) ? (string) $value : '';
    $prefix = is_scalar($prefix) ? (string) $prefix : '';
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = ''; // string to return
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    $hidden = '';
    if ($disabled) {
        // add hidden field to be submitted
        $hidden =
            '<input type="hidden" name="'
            . $field_name
            . '" id="'
            . $field_name
            . '" value="'
            . htmlspecialchars($value, ENT_COMPAT, $charset)
            . '" />'
            . K_NEWLINE;
        $field_name = 'DISABLED_' . $field_name;
    }

    $str .= '<label for="' . $field_name . '" title="' . $description . '">' . $name . '</label>' . K_NEWLINE;
    if (!empty($prefix)) {
        $str .= $prefix;
    }

    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .= '<input type="checkbox"';
    if ($disabled) {
        $str .= ' readonly="readonly" class="disabled"';
    }

    $str .= ' name="' . $field_name . '" id="' . $field_name . '" value="' . $value . '"';
    if (f_get_boolean($selected)) {
        $str .= ' checked="checked"';
    }

    $str .= ' title="' . $description . '"';
    if (strlen($tip) > 0) {
        $str .= ' aria-describedby="desc_' . $field_name . '"';
    }

    $str .= ' />';
    $str .= $hidden;
    if (strlen($tip) > 0) {
        $str .= ' <span class="labeldesc" id="desc_' . $field_name . '">' . $tip . '</span>';
    }

    $str .= '</span>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Print fixed value row form.
 * @param $field_name (string) Name of the form field.
 * @param $name (string) Label.
 * @param $description (string) Label description (tooltip).
 * @param $tip (string) Help to be displayed on the right of the input field.
 * @param $value (string) Initial value.
 * @param $currency (boolean) if true the value is a curency number.
 * @param $prefix (string) code to be displayed after label.
 * @return string
 */
function get_form_row_fixed_value(
    string $field_name,
    string $name,
    string $description = '',
    string $tip = '',
    string $value = '',
    bool $currency = false,
    string $prefix = '',
): string {
    require_once __DIR__ . '/../config/tce_config.php';
    $charset = f_get_form_translation('a_meta_charset', 'UTF-8');
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = ''; // string to return
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    $str .= '<label for="DISABLED_' . $field_name . '" title="' . $description . '">' . $name . '</label>' . K_NEWLINE;
    if (!empty($prefix)) {
        $str .= $prefix;
    }

    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .=
        '<input type="text" readonly="readonly" name="DISABLED_' . $field_name . '" id="DISABLED_' . $field_name . '"';
    if ($currency) {
        $value = f_format_form_currency($value, 2);
        $str .= ' class="disablednum"';
    } else {
        $str .= ' class="disabled"';
    }

    $size = 20; // default value
    if (strlen($value) > 20) {
        $size = 40;
    }

    $str .=
        ' value="'
        . htmlspecialchars($value, ENT_COMPAT, $charset)
        . '" size="'
        . $size
        . '" maxlength="255" title="'
        . $description
        . '" />';
    if (strlen($tip) > 0) {
        $str .= ' <span class="labeldesc">' . $tip . '</span>';
    }

    // add hidden field to be submitted
    $str .=
        '<input type="hidden" name="'
        . $field_name
        . '" id="'
        . $field_name
        . '" value="'
        . htmlspecialchars($value, ENT_COMPAT, $charset)
        . '" />'
        . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Print empty form row.
 * @return string
 */
function get_form_small_vert_space(): string
{
    return '<div class="row">&nbsp;</div>' . K_NEWLINE;
}

/**
 * Print empty form row.
 * @return string
 */
function get_form_small_div_space(): string
{
    return '<div style="clear:both;height:1px;font-size:1px;">&nbsp;</div>' . K_NEWLINE;
}

/**
 * Print empty form row.
 * @return string
 */
function get_form_row_vert_space(): string
{
    return '<div class="row" style="margin-bottom:5px;"><hr class="dashed"/></div>' . K_NEWLINE;
}

/**
 * Print form row with title.
 * @param string $title Title to be printed.
 * @return string
 */
function get_form_row_vert_div(string $title = ''): string
{
    return (
        '<div class="row"><hr class="dashed"/></div><div class="row"><div style="color:#666666;text-align:center;">'
        . $title
        . '</div></div>'
        . K_NEWLINE
    );
}

/**
 * Print form row with submit button when noscript is active.
 * @param string $name Name of the input form field.
 * @return string
 */
function get_form_noscript_select(string $name = 'selectrecord'): string
{
    require_once __DIR__ . '/../config/tce_config.php';
    $str = '<noscript>' . K_NEWLINE;
    $str .= '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">&nbsp;</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .=
        '<input type="submit" name="' . $name . '" id="' . $name . '" value="' . f_get_form_translation('w_select') . '" />' . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    $str .= '</div>' . K_NEWLINE;
    return $str . ('</noscript>' . K_NEWLINE);
}

/**
 * Print form row with label and description
 * @param mixed $name Label.
 * @param mixed $description Label description (tooltip).
 * @param mixed $value Initial value.
 * @return string
 */
function get_form_description_line(mixed $name, mixed $description = '', mixed $value = ''): string
{
    $name = is_scalar($name) ? (string) $name : '';
    $description = is_scalar($description) ? (string) $description : '';
    $value = is_scalar($value) ? (string) $value : '';
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = '<div class="row">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    $str .= '<span title="' . $description . '">' . $name . '</span>' . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .= $value . '&nbsp;' . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}

/**
 * Print input row form to upluad a file.
 * @param string $field_name Name of the form field.
 * @param string $field_id ID of the form field.
 * @param string $name Label.
 * @param string $description Label description (tooltip).
 * @param string $onchange Javascript code to execute at onchange event.
 * @return string
 */
function get_form_upload_file(
    string $field_name,
    string $field_id,
    string $name,
    string $description = '',
    string $onchange = '',
): string
{
    if (strlen($description) === 0) {
        $description = $name;
    }

    $str = '<div class="row" id="div' . $field_id . '">' . K_NEWLINE;
    $str .= '<span class="label">' . K_NEWLINE;
    $str .= '<label for="' . $field_id . '" title="' . $description . '">' . $name . '</label>' . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    $str .= '<span class="formw">' . K_NEWLINE;
    $str .=
        '<input type="file" name="' . $field_name . '" id="' . $field_id . '" size="20" title="' . $description . '"';
    if (!empty($onchange)) {
        $str .= ' onchange="' . $onchange . '"';
    }

    $str .= ' />' . K_NEWLINE;
    $str .= '</span>' . K_NEWLINE;
    $str .= '&nbsp;' . K_NEWLINE;
    return $str . ('</div>' . K_NEWLINE);
}
