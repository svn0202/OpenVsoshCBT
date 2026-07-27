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

if (isset($_POST['examtime'])) {
    $examtime = $_POST['examtime'];
}

$pagelevel = K_AUTH_PUBLIC_TEST_EXECUTE;
$thispage_title = $l['t_test_execute'];
$thispage_description = $l['hp_test_execute'];
require_once '../../shared/code/tce_authorization.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_test.php';

$formname = 'testform';

$test_id = 0;
$testlog_id = 0;
$answpos = [];
$answer_text = '';
$test_comment = '';
$reaction_time = 0;
$answer_save_error = '';

if (isset($_REQUEST['testid']) && $_REQUEST['testid'] > 0) {
    $test_id = (int) $_REQUEST['testid'];
    // check for test password
    $tph = F_getTestPassword($test_id);
    if (
        !empty($tph)
        && !F_tmf_test_session_is_unlocked($test_id)
        && !checkPassword(
            $tph . $test_id . $_SESSION['session_user_id'] . $_SESSION['session_user_ip'],
            $_SESSION['session_test_login'],
        )
    ) {
        // display login page
        require_once '../code/tce_page_header.php';
        echo F_testLoginForm($_SERVER['SCRIPT_NAME'], 'form_test_login', 'post', 'multipart/form-data', $test_id);
        require_once '../code/tce_page_footer.php';
        exit(); //break page here
    }

    if (isset($_REQUEST['repeat']) && $_REQUEST['repeat'] == 1) {
        // mark previous test attempts as repeated
        F_repeatTest($test_id);
    }

    if (F_executeTest($test_id)) {
        $execution_rules = F_getTestData($test_id);
        if (F_getBoolean($execution_rules['test_disable_previous'] ?? false)) {
            unset($_REQUEST['prevquestion'], $_POST['prevquestion']);
        }
        if (F_getBoolean($execution_rules['test_disable_next'] ?? false)) {
            unset($_REQUEST['nextquestion'], $_POST['nextquestion']);
        }
        if (!empty($_REQUEST['testlogid'])) {
            $testlog_id = (int) $_REQUEST['testlogid'];
        }

        if (!empty($_REQUEST['answpos'])) {
            $answpos = is_numeric($_REQUEST['answpos'])
                ? [
                    $_REQUEST['answpos'] => 1,
                ] : (array) $_REQUEST['answpos'];
        }

        // `empty()` treats the valid short answer "0" as missing.
        if (isset($_REQUEST['answertext']) && is_string($_REQUEST['answertext'])) {
            $answer_text = $_REQUEST['answertext'];
        }

        if (!empty($_REQUEST['reaction_time'])) {
            $reaction_time = (int) $_REQUEST['reaction_time'];
        }

        if (!empty($_REQUEST['forceterminate']) && F_isRightTestlogUser($test_id, $testlog_id)) {
            if ($_REQUEST['forceterminate'] == 'lasttimedquestion') {
                // update last question
                if (isset($_REQUEST['answer_version'])) {
                    F_tmf_save_question_answer(
                        $test_id,
                        $testlog_id,
                        $answpos,
                        $answer_text,
                        $reaction_time,
                        (int) $_REQUEST['answer_version'],
                        bin2hex(random_bytes(16)),
                    );
                } else {
                    F_updateQuestionLog($test_id, $testlog_id, $answpos, $answer_text, $reaction_time);
                }
            }

            $completion = $_REQUEST['forceterminate'] === 'lasttimedquestion'
                ? ['allowed' => true, 'reason' => 'timeout', 'details' => null]
                : F_tmf_test_completion_status($test_id, (int) $_SESSION['session_user_id']);
            if (!$completion['allowed']) {
                $labels = [
                    'minimum_duration' => 'Завершение пока недоступно. Осталось секунд: ',
                    'required_answers' => 'Ответьте на все обязательные вопросы. Пропущено: ',
                    'score_threshold' => 'Для завершения требуется проходной балл: ',
                ];
                $answer_save_error = ($labels[$completion['reason']] ?? 'Завершение пока недоступно.')
                    . ($completion['details'] ?? '');
                $_REQUEST['forceterminate'] = '';
            } else {
                // terminate the test (lock the test to status=4)
                $completion_message = trim((string) ($execution_rules['test_completion_message'] ?? ''));
                if ($completion_message !== '') {
                    $_SESSION['session_test_completion_message'] = $completion_message;
                }
                F_terminateUserTest($test_id);
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
        $thispage_title .= ': ' . F_getTestName($test_id);

        require_once '../code/tce_page_header.php';
        echo '<div class="container">' . K_NEWLINE;

        echo '<span class="infolink">' . F_testInfoLink($test_id, $l['w_info']) . '<br /><br /></span>' . K_NEWLINE;

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && !isset($_REQUEST['terminationform'])
            && F_isRightTestlogUser($test_id, $testlog_id)
        ) {
            // the form has been submitted, update testlogid data
            $answer_saved = true;
            if (isset($_REQUEST['answer_version'])) {
                $save_result = F_tmf_save_question_answer(
                    $test_id,
                    $testlog_id,
                    $answpos,
                    $answer_text,
                    $reaction_time,
                    (int) $_REQUEST['answer_version'],
                    bin2hex(random_bytes(16)),
                );
                $answer_saved = $save_result['status'] === 'saved';
                if (!$answer_saved) {
                    $answer_save_error = $save_result['status'] === 'conflict'
                        ? $l['ov_answer_save_conflict']
                        : $l['ov_answer_not_saved'];
                }
            } else {
                $answer_saved = F_updateQuestionLog(
                    $test_id,
                    $testlog_id,
                    $answpos,
                    $answer_text,
                    $reaction_time,
                );
            }
            if ($answer_saved && isset($_FILES['answer_attachments'])) {
                $attachment_result = F_tmf_attachment_store_uploads(
                    $test_id,
                    $testlog_id,
                    (array) $_FILES['answer_attachments'],
                );
                if (!in_array($attachment_result['status'], ['stored', 'empty'], true)) {
                    $answer_save_error = $attachment_result['message'];
                }
            }
            // update user's test comment
            if (isset($_REQUEST['testcomment']) && !empty($_REQUEST['testcomment'])) {
                $test_comment = $_REQUEST['testcomment'];
                F_updateTestComment($test_id, $test_comment);
            }

            if (
                $answer_saved
                &&
                (isset($_REQUEST['nextquestion']) || isset($_REQUEST['autonext']) && $_REQUEST['autonext'] == 1)
                && $_REQUEST['nextquestionid'] > 0
            ) {
                // go to next question
                $testlog_id = 0 + (int) $_REQUEST['nextquestionid'];
            } elseif ($answer_saved && isset($_REQUEST['prevquestion']) && $_REQUEST['prevquestionid'] > 0) {
                // go to previous question
                $testlog_id = (int) $_REQUEST['prevquestionid'];
            } elseif ($answer_saved) {
                // go to selected question
                foreach (array_keys($_POST) as $key) {
                    if (preg_match('/jumpquestion_(\d+)/', $key, $matches) > 0) {
                        $testlog_id = (int) $matches[1];
                        break;
                    }
                }
            }
        }

        if ($answer_save_error !== '') {
            F_print_error('ERROR', htmlspecialchars($answer_save_error, ENT_QUOTES, $l['a_meta_charset']));
        }

        // confirmation form to terminate the test
        if (isset($_REQUEST['terminatetest']) && !empty($_REQUEST['terminatetest'])) {
            // check if some questions were omitted (undisplayed or unanswered).
            $num_omitted_questions = F_getNumOmittedQuestions($test_id);
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
                htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
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
            echo F_getCSRFTokenField() . K_NEWLINE;
            ?>
            </div>
            </form>
            </div>
<?php
        } else {
            echo
                '<form action="'
                    . htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES)
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
            echo F_questionForm($test_id, $testlog_id, $formname);
            // the $finish variable is used to check if the form has been automatically submitted
            // at the end of the time.
            $finish = isset($_REQUEST['finish']) && $_REQUEST['finish'] > 0 ? 1 : 0;

            echo '<input type="hidden" name="finish" id="finish" value="' . $finish . '" />' . K_NEWLINE;
            echo '<input type="hidden" name="display_time" id="display_time" value="" />' . K_NEWLINE;
            echo '<input type="hidden" name="reaction_time" id="reaction_time" value="" />' . K_NEWLINE;

            // textarea field for user's comment
            echo '<span class="testcomment">' . F_testComment($test_id) . '</span>' . K_NEWLINE;

            // Hide termination while required answers are missing and identify the exact
            // question numbers, while keeping the server-side completion check authoritative.
            $completion = F_tmf_test_completion_status($test_id, (int) $_SESSION['session_user_id']);
            if (!$completion['allowed'] && $completion['reason'] === 'required_answers') {
                $missing_questions = F_tmf_unanswered_question_numbers(
                    $test_id,
                    (int) $_SESSION['session_user_id'],
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
            echo F_getCSRFTokenField() . K_NEWLINE;
            echo '</form>' . K_NEWLINE;
        }

        // start the countdown if disabled
        if (isset($examtime)) {
            $timeout_logout = isset($_REQUEST['timeout_logout']) && $_REQUEST['timeout_logout'] ? 'true' : 'false';

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
