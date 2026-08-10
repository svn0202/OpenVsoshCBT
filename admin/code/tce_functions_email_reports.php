<?php

//============================================================+
// File name   : tce_functions_email_reports.php
// Begin       : 2005-02-24
// Last Update : 2023-11-30
//
// Description : Sends email test reports to users.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to send email reports to users.
 * @package com.tecnick.tcexam.admin
 * @author Nicola Asuni
 * @since 2005-02-24
 */

/**
 * Sends email test reports to users.
 * @author Nicola Asuni
 * @since 2005-02-24
 * @param $test_id (int) TEST ID
 * @param $user_id (int) USER ID (0 means all users)
 * @param $testuser_id (int) test-user ID - if greater than zero, filter stats for the specified test-user.
 * @param $group_id (int) GROUP ID (0 means all groups)
 * @param $startdate (int) start date ID - if greater than zero, filter stats for the specified starting date
 * @param $enddate (int) end date ID - if greater than zero, filter stats for the specified ending date
 * @param $mode (int) type of report to send: 0=detailed report; 1=summary report (without questions)
 * @param $display_mode display (int) mode: 0 = disabled; 1 = minimum; 2 = module; 3 = subject; 4 = question; 5 = answer.
 * @param $show_graph (boolean) If true display the score graph.
 * @throws \PHPMailer\PHPMailer\Exception
 */
function f_send_report_emails(
    mixed $test_id,
    mixed $user_id = 0,
    mixed $testuser_id = 0,
    mixed $group_id = 0,
    mixed $startdate = 0,
    mixed $enddate = 0,
    mixed $mode = 0,
    mixed $display_mode = 1,
    mixed $show_graph = false,
): void {
    global $l, $db;
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_test.php';
    require_once '../../shared/code/tce_functions_test_stats.php';
    require_once '../../shared/code/tce_class_mailer.php';
    require_once 'tce_functions_user_select.php';

    /** @var mixed $db */
    /**
     * @var array{
     *     a_meta_charset:string,a_meta_language:string,a_meta_dir:string,t_result_user:string,w_test:string,
     *     w_test_score_threshold:string,w_passed:string,w_not_passed:string,w_score:string,w_answers_right:string,
     *     w_answers_wrong:string,w_questions_unanswered:string,w_questions_undisplayed:string,w_attachment:string,
     *     t_error:string,m_unknown_email:string
     * } $l
     */
    /**
     * @var array{
     *     Priority:int,ContentType:string,Encoding:string,WordWrap:int,Mailer:string,Sendmail:string,Host:string,
     *     Port:int,Helo:string,SMTPAuth:bool,SMTPSecure:string,Username:string,Password:string,Timeout:int,
     *     SMTPDebug:int,Sender:string,From:string,FromName:string,Reply:string,ReplyName:string,CharSet:string,
     *     MsgHeader:string,MsgFooter:string,AttachmentsEncoding:string
     * } $emailcfg
     */

    $mode = (int) $mode;
    $display_mode = (int) $display_mode;
    if (f_tce_email_report_is_positive($test_id)) {
        $test_id = (int) $test_id;
        if (!f_is_authorized_user(K_TABLE_TESTS, 'test_id', $test_id, 'test_user_id')) {
            return;
        }
    } else {
        $test_id = 0;
    }

    $user_id = f_tce_email_report_is_positive($user_id) ? (int) $user_id : 0;

    $testuser_id = f_tce_email_report_is_positive($testuser_id) ? (int) $testuser_id : 0;

    $group_id = f_tce_email_report_is_positive($group_id) ? (int) $group_id : 0;

    if (!empty($startdate)) {
        $startdate_time = (int) strtotime(f_tce_email_report_string($startdate));
        $startdate = date(K_TIMESTAMP_FORMAT, $startdate_time);
    } else {
        $startdate = '';
    }

    if (!empty($enddate)) {
        $enddate_time = (int) strtotime(f_tce_email_report_string($enddate));
        $enddate = date(K_TIMESTAMP_FORMAT, $enddate_time);
    } else {
        $enddate = '';
    }

    // Instantiate C_mailer class
    $mail = new C_mailer();

    //Load default values
    $mail->setLanguageData($l);

    $mail->Priority = $emailcfg['Priority'];
    $mail->ContentType = $emailcfg['ContentType'];
    $mail->Encoding = $emailcfg['Encoding'];
    $mail->WordWrap = $emailcfg['WordWrap'];
    $mail->Mailer = $emailcfg['Mailer'];
    $mail->Sendmail = $emailcfg['Sendmail'];
    // $mail->UseMSMailHeaders = $emailcfg['UseMSMailHeaders'];
    $mail->Host = $emailcfg['Host'];
    $mail->Port = $emailcfg['Port'];
    $mail->Helo = $emailcfg['Helo'];
    $mail->SMTPAuth = $emailcfg['SMTPAuth'];
    $mail->SMTPSecure = $emailcfg['SMTPSecure'];
    $mail->Username = $emailcfg['Username'];
    $mail->Password = $emailcfg['Password'];
    $mail->Timeout = $emailcfg['Timeout'];
    $mail->SMTPDebug = $emailcfg['SMTPDebug'];
    $mail->Sender = $emailcfg['Sender'];
    $mail->From = $emailcfg['From'];
    $mail->FromName = $emailcfg['FromName'];
    if ($emailcfg['Reply']) {
        $mail->addReplyTo($emailcfg['Reply'], $emailcfg['ReplyName']);
    }

    $mail->CharSet = $l['a_meta_charset'];
    if (!$mail->CharSet) {
        $mail->CharSet = $emailcfg['CharSet'];
    }

    $mail->Subject = $l['t_result_user'];
    $mail->isHTML(true); // Set message type to HTML.

    $email_num = 0; // count emails;

    // get all data
    $data = f_tce_email_report_data(f_get_all_users_test_stat(
        $test_id,
        $group_id,
        $user_id,
        $startdate,
        $enddate,
        'total_score',
        false,
        $display_mode,
    ));

    // SECURITY: the per-user report PDF is rendered by an internal HTTP request to this
    // installation. Fetch it through tc-lib-file's safe HTTP reader (TLS-verified, size-capped,
    // restricted to allow-listed hosts) rather than a raw file_get_contents() on a URL. The
    // install's own host is always trusted; extra hosts can be configured via K_FILE_ALLOWED_HOSTS.
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (!defined('FORCE_CURL')) {
        // force the cURL transport so the safe reader is used even when allow_url_fopen is enabled
        define('FORCE_CURL', true);
    }

    // defined() guard keeps pre-existing installs working until they merge the new config defaults
    $allowed_hosts = f_tce_email_report_allowed_hosts(
        defined('K_FILE_ALLOWED_HOSTS')
            ? unserialize(f_tce_email_report_string(K_FILE_ALLOWED_HOSTS))
            : [],
    );
    $self_host = f_tce_email_report_host(parse_url(f_tce_email_report_string(K_PATH_HOST), PHP_URL_HOST));
    if ($self_host !== null) {
        $allowed_hosts[] = $self_host;
    }

    $pdf_reader = new \Com\Tecnick\File\File();
    $pdf_reader->setAllowedHosts($allowed_hosts);

    foreach ($data['testuser'] as $tu) {
        if (strlen($tu['user_email']) > 3) {
            // set HTML header
            $mail->Body = $emailcfg['MsgHeader'];
            // compose alternate TEXT message
            $mail->AltBody = '' . $l['t_result_user'] . ' [' . $tu['testuser_creation_time'] . ']' . K_NEWLINE;
            $mail->AltBody .= $l['w_test'] . ': ' . $tu['test']['test_name'] . K_NEWLINE;

            $passmsg = '';
            if ($tu['test']['test_score_threshold'] > 0) {
                $mail->AltBody .= $l['w_test_score_threshold'] . ': ' . $tu['test']['test_score_threshold'];
                if ($tu['total_score'] >= $tu['test']['test_score_threshold']) {
                    $passmsg = ' - ' . $l['w_passed'];
                } else {
                    $passmsg = ' - ' . $l['w_not_passed'];
                }

                $mail->AltBody .= K_NEWLINE;
            }

            $mail->AltBody .=
                $l['w_score']
                . ': '
                . f_format_float($tu['total_score'])
                . ' '
                . f_format_percentage($tu['total_score_perc'], false)
                . $passmsg
                . K_NEWLINE;
            if ($display_mode > 0) {
                $mail->AltBody .=
                    $l['w_answers_right']
                    . ': '
                    . $tu['right']
                    . '&nbsp;'
                    . f_format_percentage($tu['right_perc'], false)
                    . K_NEWLINE;
                $mail->AltBody .=
                    $l['w_answers_wrong']
                    . ': '
                    . $tu['wrong']
                    . '&nbsp;'
                    . f_format_percentage($tu['wrong_perc'], false)
                    . K_NEWLINE;
                $mail->AltBody .=
                    $l['w_questions_unanswered']
                    . ': '
                    . $tu['unanswered']
                    . '&nbsp;'
                    . f_format_percentage($tu['unanswered_perc'], false)
                    . K_NEWLINE;
                $mail->AltBody .=
                    $l['w_questions_undisplayed']
                    . ': '
                    . $tu['undisplayed']
                    . '&nbsp;'
                    . f_format_percentage($tu['undisplayed_perc'], false)
                    . K_NEWLINE;
            }

            if ($mode === 0) {
                $pdfkey = get_password_hash(
                    date('Y') . $tu['id'] . K_RANDOM_SECURITY . $tu['test']['test_id'] . date('m') . $tu['user_id'],
                );
                // create PDF doc (fetched via tc-lib-file's safe, host-allow-listed HTTP reader)
                $pdf_url =
                    K_PATH_HOST
                    . K_PATH_TCEXAM
                    . 'admin/code/tce_pdf_results.php?mode=3&diplay_mode='
                    . $display_mode
                    . '&show_graph='
                    . f_tce_email_report_string($show_graph)
                    . '&test_id='
                    . $tu['test']['test_id']
                    . '&user_id='
                    . $tu['user_id']
                    . '&testuser_id='
                    . $tu['id']
                    . '&email='
                    . urlencode($pdfkey);
                try {
                    $pdf_content = f_tce_email_report_pdf_content($pdf_reader->getUrlData($pdf_url));
                } catch (\Com\Tecnick\File\Exception $e) {
                    $pdf_content = false;
                }

                if ($pdf_content === false) {
                    $pdf_content = '';
                }
                // set PDF document file name
                $doc_name = 'tcexam_report';
                $doc_name .= '_3';
                $doc_name .= '_0';
                $doc_name .= '_' . $tu['test']['test_id'];
                $doc_name .= '_0';
                $doc_name .= '_' . $tu['user_id'];
                $doc_name .= '_' . $tu['id'];
                $doc_name .= '.pdf';

                // attach document
                $mail->addStringAttachment(
                    $pdf_content,
                    $doc_name,
                    $emailcfg['AttachmentsEncoding'],
                    'application/octet-stream',
                );
                $mail->AltBody .= K_NEWLINE . $l['w_attachment'] . ': ' . $doc_name . K_NEWLINE;
            }

            // convert alternate text to HTML
            $mail->Body .= str_replace(K_NEWLINE, '<br />' . K_NEWLINE, $mail->AltBody);

            // add HTML footer
            $mail->Body .= $emailcfg['MsgFooter'];

            //--- Elaborate user Templates ---
            $mail->Body = str_replace('#CHARSET#', $l['a_meta_charset'], $mail->Body);
            $mail->Body = str_replace('#LANG#', $l['a_meta_language'], $mail->Body);
            $mail->Body = str_replace('#LANGDIR#', $l['a_meta_dir'], $mail->Body);
            $mail->Body = str_replace('#EMAIL#', $tu['user_email'], $mail->Body);
            $mail->Body = str_replace(
                '#USERNAME#',
                htmlspecialchars($tu['user_name'], ENT_NOQUOTES, $l['a_meta_charset']),
                $mail->Body,
            );
            $mail->Body = str_replace(
                '#USERFIRSTNAME#',
                htmlspecialchars($tu['user_firstname'] ?? '', ENT_NOQUOTES, $l['a_meta_charset']),
                $mail->Body,
            );
            $mail->Body = str_replace(
                '#USERLASTNAME#',
                htmlspecialchars($tu['user_lastname'] ?? '', ENT_NOQUOTES, $l['a_meta_charset']),
                $mail->Body,
            );

            // add a "To" address
            $mail->addAddress($tu['user_email'], $tu['user_name']);

            ++$email_num;
            $progresslog = '' . $email_num . '. ' . $tu['user_email'] . ' [' . $tu['user_name'] . ']'; //output user data

            if (!$mail->send()) { //send email to user
                $progresslog .= ' [' . $l['t_error'] . ']'; //display error message
            }

            $mail->clearAddresses(); // Clear all addresses for next loop
            $mail->clearAttachments(); // Clears all previously set filesystem, string, and binary attachments
        } else {
            $progresslog = '[' . $l['t_error'] . '] ' . $tu['user_name'] . ': ' . $l['m_unknown_email'] . ''; //output user data
        }

        echo $progresslog . '<br />' . K_NEWLINE; //output processed emails
        flush(); // force browser output
    }

    $mail->clearAddresses(); // Clear all addresses for next loop
    $mail->clearCustomHeaders(); // Clears all custom headers
    $mail->clearAllRecipients(); // Clears all recipients assigned in the TO, CC and BCC
    $mail->clearAttachments(); // Clears all previously set filesystem, string, and binary attachments
    $mail->clearReplyTos(); // Clears all recipients assigned in the ReplyTo array
    return;
}

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_email_report_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve legacy positive-value comparisons before integer normalization. */
function f_tce_email_report_is_positive(mixed $value): bool
{
    if (is_array($value) || is_object($value)) {
        return true;
    }

    if (is_resource($value)) {
        return (int) $value > 0;
    }

    if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
        return $value > 0;
    }

    return false;
}

/** @return list<string> */
function f_tce_email_report_allowed_hosts(mixed $hosts): array
{
    if (!is_array($hosts)) {
        return [];
    }

    /** @var list<string> $hosts */
    return $hosts;
}

function f_tce_email_report_host(mixed $host): ?string
{
    return is_string($host) && $host !== '' ? $host : null;
}

/**
 * @return array{testuser:array<array-key,array{
 *     id:int|string,user_id:int|string,user_email:string,user_name:string,user_firstname?:string,user_lastname?:string,
 *     testuser_creation_time:string,total_score:int|float,total_score_perc:int|float,right:int|float,
 *     right_perc:int|float,wrong:int|float,wrong_perc:int|float,unanswered:int|float,
 *     unanswered_perc:int|float,undisplayed:int|float,undisplayed_perc:int|float,
 *     test:array{test_id:int|string,test_name:string,test_score_threshold:int|float}
 * }>}
 */
function f_tce_email_report_data(mixed $data): array
{
    /**
     * @var array{testuser:array<array-key,array{
     *     id:int|string,user_id:int|string,user_email:string,user_name:string,user_firstname?:string,user_lastname?:string,
     *     testuser_creation_time:string,total_score:int|float,total_score_perc:int|float,right:int|float,
     *     right_perc:int|float,wrong:int|float,wrong_perc:int|float,unanswered:int|float,
     *     unanswered_perc:int|float,undisplayed:int|float,undisplayed_perc:int|float,
     *     test:array{test_id:int|string,test_name:string,test_score_threshold:int|float}
     * }>} $data
     */
    return $data;
}

function f_tce_email_report_pdf_content(mixed $content): string|false
{
    /** @var string|false $content */
    return $content;
}
