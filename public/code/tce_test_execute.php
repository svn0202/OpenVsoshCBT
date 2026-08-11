<?php

//============================================================+
// File name   : tce_test_execute.php
// Begin       : 2004-05-29
// Last Update : 2023-11-30
//
// Description : execute a specific test
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Execute a specific test.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2004-05-29
 */

require_once '../config/tce_config.php';

/**
 * @var array{
 *     a_meta_charset:string,
 *     a_meta_dir:string,
 *     a_meta_language:string,
 *     h_cancel:string,
 *     h_questions_unanswered:string,
 *     hp_test_execute:string,
 *     m_confirm_test_termination:string,
 *     m_exam_end_time:string,
 *     ov_answer_not_saved?:string,
 *     ov_answer_save_conflict?:string,
 *     t_test_execute:string,
 *     w_cancel:string,
 *     w_index:string,
 *     w_info:string,
 *     w_terminate:string,
 *     w_terminate_exam:string
 * } $l
 */
/**
 * @var array{
 *     answer_version?:int|string,
 *     answertext?:mixed,
 *     answpos?:int|string|array<array-key,mixed>,
 *     autonext?:string,
 *     finish?:int|string,
 *     forceterminate?:string,
 *     nextquestion?:mixed,
 *     nextquestionid?:int|string,
 *     prevquestion?:mixed,
 *     prevquestionid?:int|string,
 *     reaction_time?:int|string,
 *     repeat?:string,
 *     terminationform?:mixed,
 *     terminatetest?:mixed,
 *     testcomment?:string,
 *     testid?:int|string,
 *     testlogid?:int|string,
 *     timeout_logout?:bool|int|string
 * } $request
 */
/** @var array<array-key,mixed> $post */
/** @var array{answer_attachments?:array<array-key,mixed>} $files */
/** @var array{REQUEST_METHOD:string, SCRIPT_NAME:string} $server */
/**
 * @var array{
 *     session_test_completion_message?:string,
 *     session_test_login?:string,
 *     session_user_id:int,
 *     session_user_ip:string
 * } $session
 */
$request = &$_REQUEST;
$post = &$_POST;
$files = &$_FILES;
$server = &$_SERVER;

if (isset($post['examtime'])) {
    $examtime = $post['examtime'];
}

/** @var int $pagelevel */
$pagelevel = K_AUTH_PUBLIC_TEST_EXECUTE;
$thispage_title = $l['t_test_execute'];
$thispage_description = $l['hp_test_execute'];
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_test.php';
$session = &$_SESSION;

/** @var array{session_test_completion_message?:string, session_test_login?:string, session_user_id:int, session_user_ip:string} $session */
$formname = 'testform';

$test_id = 0;
$testlog_id = 0;
$answpos = [];
$answer_text = '';
$test_comment = '';
$reaction_time = 0;
$answer_save_error = '';

if (isset($request['testid']) && $request['testid'] > 0) {
    $test_id = (int) $request['testid'];
    // check for test password
    $tph = f_get_test_password($test_id);
    if (
        !empty($tph)
        && !F_tmf_test_session_is_unlocked($test_id)
        && !check_password(
            $tph . $test_id . $session['session_user_id'] . $session['session_user_ip'],
            $session['session_test_login'] ?? '',
        )
    ) {
        // display login page
        require_once '../code/tce_page_header.php';
        echo f_test_login_form($server['SCRIPT_NAME'], 'form_test_login', 'post', 'multipart/form-data', $test_id);
        require_once '../code/tce_page_footer.php';
        exit(); //break page here
    }

    if (isset($request['repeat']) && $request['repeat'] === '1') {
        // mark previous test attempts as repeated
        f_repeat_test($test_id);
    }

    if (f_execute_test($test_id)) {
        $execution_rules = f_get_test_data($test_id);
        if (f_get_boolean($execution_rules['test_disable_previous'] ?? false)) {
            unset($request['prevquestion'], $post['prevquestion']);
        }
        if (f_get_boolean($execution_rules['test_disable_next'] ?? false)) {
            unset($request['nextquestion'], $post['nextquestion']);
        }
        if (!empty($request['testlogid'])) {
            $testlog_id = (int) $request['testlogid'];
        }

        if (!empty($request['answpos'])) {
            $answpos = is_numeric($request['answpos'])
                ? [
                    $request['answpos'] => 1,
                ] : (array) $request['answpos'];
        }

        // `empty()` treats the valid short answer "0" as missing.
        if (isset($request['answertext']) && is_string($request['answertext'])) {
            $answer_text = $request['answertext'];
        }

        if (!empty($request['reaction_time'])) {
            $reaction_time = (int) $request['reaction_time'];
        }

        if (!empty($request['forceterminate']) && f_is_right_testlog_user($test_id, $testlog_id)) {
            if ($request['forceterminate'] === 'lasttimedquestion') {
                // update last question
                if (isset($request['answer_version'])) {
                    F_tmf_save_question_answer(
                        $test_id,
                        $testlog_id,
                        $answpos,
                        $answer_text,
                        $reaction_time,
                        (int) $request['answer_version'],
                        bin2hex(random_bytes(16)),
                    );
                } else {
                    f_update_question_log($test_id, $testlog_id, $answpos, $answer_text, $reaction_time);
                }
            }

            $completion = $request['forceterminate'] === 'lasttimedquestion'
                ? ['allowed' => true, 'reason' => 'timeout', 'details' => null]
                : F_tmf_test_completion_status($test_id, (int) $session['session_user_id']);
            if (!$completion['allowed']) {
                $labels = [
                    'minimum_duration' => 'Завершение пока недоступно. Осталось секунд: ',
                    'required_answers' => 'Ответьте на все обязательные вопросы. Пропущено: ',
                    'score_threshold' => 'Для завершения требуется проходной балл: ',
                ];
                $answer_save_error = ($labels[$completion['reason']] ?? 'Завершение пока недоступно.')
                    . ($completion['details'] ?? '');
                $request['forceterminate'] = '';
            } else {
                // terminate the test (lock the test to status=4)
                $completion_message = trim((string) ($execution_rules['test_completion_message'] ?? ''));
                if ($completion_message !== '') {
                    $session['session_test_completion_message'] = $completion_message;
                }
                f_terminate_user_test($test_id);
                // redirect the user to the index page
                header('Location: index.php');
                echo '<!DOCTYPE html>' . K_NEWLINE;
                echo '<html lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
                echo '<head>' . K_NEWLINE;
                echo '<meta charset="' . $l['a_meta_charset'] . '" />' . K_NEWLINE;
                echo '<title>' . htmlspecialchars($l['w_index'], ENT_COMPAT, $l['a_meta_charset']) . '</title>'
                    . K_NEWLINE;
                echo '<meta http-equiv="refresh" content="0;url=index.php" />' . K_NEWLINE; //reload page
                echo '</head>' . K_NEWLINE;
                echo '<body>' . K_NEWLINE;
                echo '<main id="maincontent">' . K_NEWLINE;
                echo '<a href="index.php">' . $l['w_index'] . '...</a>' . K_NEWLINE;
                echo '</main>' . K_NEWLINE;
                echo '</body>' . K_NEWLINE;
                echo '</html>' . K_NEWLINE;
                exit();
            }
        }

        // the user is authorized to execute the selected test
        $thispage_title .= ': ' . (string) f_get_test_name($test_id);

        require_once '../code/tce_page_header.php';
        echo '<div class="container">' . K_NEWLINE;

        echo '<span class="infolink">' . f_test_info_link($test_id, $l['w_info']) . '<br /><br /></span>' . K_NEWLINE;

        if (
            $server['REQUEST_METHOD'] === 'POST'
            && !isset($request['terminationform'])
            && f_is_right_testlog_user($test_id, $testlog_id)
        ) {
            // the form has been submitted, update testlogid data
            $answer_saved = true;
            if (isset($request['answer_version'])) {
                $save_result = F_tmf_save_question_answer(
                    $test_id,
                    $testlog_id,
                    $answpos,
                    $answer_text,
                    $reaction_time,
                    (int) $request['answer_version'],
                    bin2hex(random_bytes(16)),
                );
                $answer_saved = $save_result['status'] === 'saved';
                if (!$answer_saved) {
                    $answer_status_labels = f_tmf_answer_status_labels($l);
                    $answer_save_error = $save_result['status'] === 'conflict'
                        ? $answer_status_labels['conflict']
                        : $answer_status_labels['error'];
                }
            } else {
                $answer_saved = f_update_question_log(
                    $test_id,
                    $testlog_id,
                    $answpos,
                    $answer_text,
                    $reaction_time,
                );
            }
            if ($answer_saved && isset($files['answer_attachments'])) {
                $attachment_result = F_tmf_attachment_store_uploads(
                    $test_id,
                    $testlog_id,
                    $files['answer_attachments'],
                );
                if (!in_array($attachment_result['status'], ['stored', 'empty'], true)) {
                    $answer_save_error = $attachment_result['message'];
                }
            }
            // update user's test comment
            if (isset($request['testcomment']) && !empty($request['testcomment'])) {
                $test_comment = $request['testcomment'];
                f_update_test_comment($test_id, $test_comment);
            }

            if (
                $answer_saved
                &&
                (isset($request['nextquestion']) || isset($request['autonext']) && $request['autonext'] === '1')
                && isset($request['nextquestionid'])
                && $request['nextquestionid'] > 0
            ) {
                // go to next question
                $testlog_id = (int) $request['nextquestionid'];
            } elseif (
                $answer_saved
                && isset($request['prevquestion'], $request['prevquestionid'])
                && $request['prevquestionid'] > 0
            ) {
                // go to previous question
                $testlog_id = (int) $request['prevquestionid'];
            } elseif ($answer_saved) {
                // go to selected question
                foreach (array_keys($post) as $key) {
                    $matches = [];
                    if (preg_match('/jumpquestion_(\d+)/', (string) $key, $matches) === 1 && isset($matches[1])) {
                        $testlog_id = (int) $matches[1];
                        break;
                    }
                }
            }
        }

        if ($answer_save_error !== '') {
            echo '<div data-answer-save-error="1">' . K_NEWLINE;
            F_print_error('ERROR', htmlspecialchars($answer_save_error, ENT_QUOTES, $l['a_meta_charset']));
            echo '</div>' . K_NEWLINE;
        }

        // confirmation form to terminate the test
        if (isset($request['terminatetest']) && !empty($request['terminatetest'])) {
            // check if some questions were omitted (undisplayed or unanswered).
            $num_omitted_questions = f_get_num_omitted_questions($test_id);
            $omitted_msg = '';
            if ($num_omitted_questions > 0) {
                $omitted_msg =
                    '<br /><span style="color:#990000;font-size:120%;">[ '
                    . $l['h_questions_unanswered']
                    . ': '
                    . $num_omitted_questions
                    . ' ]</span><br />';
            }

            F_print_error('WARNING', $omitted_msg . '' . $l['m_confirm_test_termination']);
            ?>
            <div class="confirmbox">
            <form action="<?php echo
                htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
            ; ?>" method="post" enctype="multipart/form-data" id="form_test_terminate">
            <div>
            <input type="hidden" name="testid" id="testid" value="<?php echo $test_id; ?>" />
            <input type="hidden" name="testlogid" id="testlogid" value="<?php echo $testlog_id; ?>" />
            <input type="hidden" name="terminationform" id="terminationform" value="1" />
            <input type="hidden" name="display_time" id="display_time" value="" />
            <input type="hidden" name="reaction_time" id="reaction_time" value="" />
            <?php

            F_submit_button('forceterminate', $l['w_terminate'], $l['w_terminate_exam']);
            F_submit_button('cancel', $l['w_cancel'], $l['h_cancel']);
            echo f_get_csrf_token_field() . K_NEWLINE;
            ?>
            </div>
            </form>
            </div>
<?php
        } else {
            echo
                '<form action="'
                    . htmlspecialchars($server['SCRIPT_NAME'], ENT_QUOTES)
                    . '" method="post" enctype="multipart/form-data" id="'
                    . $formname
                    . '"'
            ;
            echo
                ' onsubmit="var submittime=new Date();document.getElementById(\'reaction_time\').value=submittime.getTime()-document.getElementById(\'display_time\').value;"'
            ;
            echo '>' . K_NEWLINE;
            echo '<div>' . K_NEWLINE;

            // display questions + navigation menu
            echo f_question_form($test_id, $testlog_id, $formname);
            // the $finish variable is used to check if the form has been automatically submitted
            // at the end of the time.
            $finish = isset($request['finish']) && $request['finish'] > 0 ? 1 : 0;

            echo '<input type="hidden" name="finish" id="finish" value="' . $finish . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="display_time" id="display_time" value="" />' . K_NEWLINE;
            echo '<input type="hidden" name="reaction_time" id="reaction_time" value="" />' . K_NEWLINE;

            // textarea field for user's comment
            echo '<span class="testcomment">' . f_test_comment($test_id) . '</span>' . K_NEWLINE;

            // Hide termination while required answers are missing and identify the exact
            // question numbers, while keeping the server-side completion check authoritative.
            $completion = F_tmf_test_completion_status($test_id, (int) $session['session_user_id']);
            if (!$completion['allowed'] && $completion['reason'] === 'required_answers') {
                $missing_questions = F_tmf_unanswered_question_numbers(
                    $test_id,
                    (int) $session['session_user_id'],
                );
                echo '<p class="warning" id="required-answers-notice" role="status">'
                    . 'Завершение появится после ответа на обязательные вопросы. Пропущены: '
                    . htmlspecialchars(implode(', ', $missing_questions), ENT_QUOTES, $l['a_meta_charset'])
                    . '.</p>' . K_NEWLINE;
            } else {
                F_submit_button('terminatetest', $l['w_terminate_exam'], $l['w_terminate_exam']);
            }

            echo K_NEWLINE;
            echo '</div>' . K_NEWLINE;
            echo f_get_csrf_token_field() . K_NEWLINE;
            echo '</form>' . K_NEWLINE;
        }

        // start the countdown if disabled
        if (isset($examtime)) {
            /** @var int $examtime */
            $timeout_logout = isset($request['timeout_logout']) && $request['timeout_logout'] ? 'true' : 'false';

            echo '<script type="text/javascript">' . K_NEWLINE;
            echo '//<![CDATA[' . K_NEWLINE;
            echo 'if(!enable_countdown) {' . K_NEWLINE;
            echo
                '	FJ_start_timer(\'true\', '
                    . (time() - $examtime)
                    . ", '"
                    . addslashes($l['m_exam_end_time'])
                    . "', "
                    . $timeout_logout
                    . ');'
                    . K_NEWLINE
            ;
            echo '}' . K_NEWLINE;
            echo 'var loadtime=new Date();' . K_NEWLINE;
            echo "document.getElementById('display_time').value=loadtime.getTime();" . K_NEWLINE;
            echo '//]]>' . K_NEWLINE;
            echo '</script>' . K_NEWLINE;
        }
    } else {
        // redirect the user to the index page
        header('Location: index.php');
        echo '<!DOCTYPE html>' . K_NEWLINE;
        echo '<html lang="' . $l['a_meta_language'] . '" dir="' . $l['a_meta_dir'] . '">' . K_NEWLINE;
        echo '<head>' . K_NEWLINE;
        echo '<meta charset="' . $l['a_meta_charset'] . '" />' . K_NEWLINE;
        echo '<title>' . htmlspecialchars($l['w_index'], ENT_COMPAT, $l['a_meta_charset']) . '</title>' . K_NEWLINE;
        echo '<meta http-equiv="refresh" content="0;url=index.php" />' . K_NEWLINE; //reload page
        echo '</head>' . K_NEWLINE;
        echo '<body>' . K_NEWLINE;
        echo '<main id="maincontent">' . K_NEWLINE;
        echo '<a href="index.php">' . $l['w_index'] . '...</a>' . K_NEWLINE;
        echo '</main>' . K_NEWLINE;
        echo '</body>' . K_NEWLINE;
        echo '</html>' . K_NEWLINE;
        exit();
    }
} else {
    require_once '../code/tce_page_header.php';
    echo '<div class="container">' . K_NEWLINE;
}

echo '<div class="pagehelp">' . $l['hp_test_execute'] . '</div>' . K_NEWLINE;

echo '</div>' . K_NEWLINE; // container

require_once '../code/tce_page_footer.php';
