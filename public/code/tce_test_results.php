<?php

//============================================================+
// File name   : tce_test_results.php
// Begin       : 2004-06-10
// Last Update : 2023-11-30
//
// Description : Display test results to the current user.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Display test results to the current user.
 * @package com.tecnick.tcexam.public
 * @author Nicola Asuni
 * @since 2004-06-10
 */

require_once '../config/tce_config.php';

/** @var int $pagelevel */
$pagelevel = K_AUTH_PUBLIC_TEST_RESULTS;
require_once '../../shared/code/tce_authorization.php';

/**
 * @var array{
 *     a_meta_charset:string,h_answer_right:string,h_answer_wrong:string,h_answers_right:string,h_index:string,
 *     h_pdf:string,h_score_total:string,h_testcomment:string,h_time_begin:string,h_time_end:string,
 *     hp_result_user:string,m_unanswered:string,t_test_results:string,w_answers_right:string,w_answers_wrong:string,
 *     w_comment:string,w_explanation:string,w_not_passed:string,w_passed:string,w_pdf:string,w_position:string,
 *     w_score:string,w_subjects:string,w_test:string,w_test_time:string,w_time_begin:string,w_time_end:string,
 *     w_user:string
 * } $l
 */
/** @var mixed $db */
/** @var array{session_user_id:int|string} $session */
$session = $_SESSION;

$thispage_title = $l['t_test_results'];
require_once '../code/tce_page_header.php';
require_once '../../shared/code/tce_functions_form.php';
require_once '../../shared/code/tce_functions_tcecode.php';
require_once '../../shared/code/tce_functions_test.php';
require_once '../../shared/code/tce_functions_test_stats.php';
require_once '../../shared/code/tce_functions_result_publication.php';

$user_id = (int) $session['session_user_id'];

$requested_test_id = $_REQUEST['testid'] ?? null;
if ($requested_test_id !== null && f_tce_test_results_is_positive($requested_test_id)) {
    $test_id = (int) $requested_test_id;
} else {
    header('Location: index.php'); //redirect browser to public main page
    exit();
}

// get test basic score
$test_basic_score = 1;

$testdata = f_tce_test_results_test_data(f_get_test_data($test_id));
if (!F_tmf_results_are_published($testdata)) {
    exit();
}

$test_basic_score = $testdata['test_score_right'];
//lock user's test
f_lock_user_test($test_id, $session['session_user_id']);
// get user's test stats
$usrtestdata = f_tce_test_results_user_test_data(f_get_user_test_stat($test_id, $user_id, 0, true));
$userdata = f_tce_test_results_user_data(f_get_user_data($user_id));

echo '<div class="container">' . K_NEWLINE;

echo '<div class="tceformbox">' . K_NEWLINE;
echo '<div class="result-meta">' . K_NEWLINE;

$usr_all = htmlspecialchars(
    F_tmf_result_identity($userdata, f_get_boolean($testdata['test_results_anonymized'] ?? false)),
    ENT_NOQUOTES,
    $l['a_meta_charset'],
);
echo get_form_description_line($l['w_user'] . ':', $l['w_user'], $usr_all);

$test_all =
    '<strong>'
    . htmlspecialchars($testdata['test_name'], ENT_NOQUOTES, $l['a_meta_charset'])
    . '</strong><br />'
    . K_NEWLINE;
$test_all .= htmlspecialchars($testdata['test_description'], ENT_NOQUOTES, $l['a_meta_charset']);
echo get_form_description_line($l['w_test'] . ':', $l['w_test'], $test_all);

echo get_form_description_line($l['w_time_begin'] . ':', $l['h_time_begin'], $usrtestdata['test_start_time']);
echo get_form_description_line($l['w_time_end'] . ':', $l['h_time_end'], $usrtestdata['test_end_time']);

$test_start_time = f_tce_test_results_string($usrtestdata['test_start_time']);
$test_end_time = $usrtestdata['test_end_time'];
if (!f_tce_test_results_is_positive($test_end_time)) {
    $time_diff = $testdata['test_duration_time'] * 60;
} else {
    $time_diff = (int) strtotime(f_tce_test_results_string($test_end_time)) - (int) strtotime($test_start_time); //sec
}

$time_diff = gmdate('H:i:s', (int) $time_diff);
echo get_form_description_line($l['w_test_time'] . ':', $l['w_test_time'], $time_diff);

$passmsg = '';
if ($usrtestdata['score_threshold'] > 0) {
    if ($usrtestdata['score'] >= $usrtestdata['score_threshold']) {
        $passmsg = ' - ' . $l['w_passed'];
    } else {
        $passmsg = ' - ' . $l['w_not_passed'];
    }
}

$score_all =
    $usrtestdata['score']
    . ' / '
    . $usrtestdata['max_score']
    . ' ('
    . round((100 * $usrtestdata['score']) / $usrtestdata['max_score'])
    . '%)'
    . $passmsg;
echo get_form_description_line($l['w_score'] . ':', $l['h_score_total'], $score_all);

$score_right_all =
    $usrtestdata['right']
    . ' / '
    . $usrtestdata['all']
    . ' ('
    . round((100 * $usrtestdata['right']) / $usrtestdata['all'])
    . '%)';
echo get_form_description_line($l['w_answers_right'] . ':', $l['h_answers_right'], $score_right_all);

echo get_form_description_line($l['w_comment'] . ':', $l['h_testcomment'], F_decode_tcecode($usrtestdata['comment']));
echo '</div>' . K_NEWLINE;

$result_charset = $l['a_meta_charset'];
$result_index_title = $l['h_index'];
$result_page_help = $l['hp_result_user'];
$result_pdf_title = $l['h_pdf'];
$result_pdf_label = $l['w_pdf'];
$result_score_label = $l['w_score'];
$result_right_label = $l['w_answers_right'];
$result_score = f_tce_test_results_numeric_float($usrtestdata['score']);
$result_max_score = f_tce_test_results_numeric_float($usrtestdata['max_score']);
$result_threshold = f_tce_test_results_numeric_float($usrtestdata['score_threshold']);
$result_right = (int) $usrtestdata['right'];
$result_all = (int) $usrtestdata['all'];
$score_percent = $result_max_score > 0
    ? (int) round(100 * $result_score / $result_max_score)
    : 0;
$right_percent = $result_all > 0
    ? (int) round(100 * $result_right / $result_all)
    : 0;
$result_passed = $result_threshold <= 0 || $result_score >= $result_threshold;
echo '<section class="result-hero" aria-labelledby="result-summary-title">'
    . '<div class="result-score"><span id="result-summary-title">Итоговый результат</span><strong>'
    . $score_percent . '%</strong><small class="' . ($result_passed ? 'is-passed' : 'is-failed') . '">'
    . ($result_passed ? $l['w_passed'] : $l['w_not_passed']) . '</small></div>'
    . '<div class="result-kpis">'
    . '<div><strong>' . htmlspecialchars((string) $usrtestdata['score'], ENT_QUOTES, $result_charset)
    . ' / ' . htmlspecialchars((string) $usrtestdata['max_score'], ENT_QUOTES, $result_charset)
    . '</strong><span>Баллы</span></div>'
    . '<div><strong>' . $result_right . ' / ' . $result_all
    . '</strong><span>Правильные ответы</span></div>'
    . '<div><strong>' . $right_percent . '%</strong><span>Точность</span></div>'
    . '<div><strong>' . htmlspecialchars($time_diff, ENT_QUOTES, $result_charset)
    . '</strong><span>Время выполнения</span></div></div></section>' . K_NEWLINE;

if (f_get_boolean($testdata['test_report_to_users'])) {
    echo '<div class="rowl">' . K_NEWLINE;

    /**
     * @var array<int|string,array{
     *     name:string,num:int,right:int,wrong:int,unanswered:int,undisplayed:int,unrated:int,
     *     score:int|float,maxscore:int|float,
     *     subjects:array<int|string,array{
     *         name:string,num:int,right:int,wrong:int,unanswered:int,undisplayed:int,unrated:int,
     *         score:int|float,maxscore:int|float
     *     }>
     * }> $topicresults
     */
    $topicresults = []; // per-topic results
    $testuser_id = $usrtestdata['testuser_id'];
    if (!empty($testuser_id)) {
        // display user questions
        $sql =
            'SELECT *
			FROM '
            . K_TABLE_QUESTIONS
            . ', '
            . K_TABLE_TESTS_LOGS
            . ', '
            . K_TABLE_SUBJECTS
            . ', '
            . K_TABLE_MODULES
            . '
			WHERE question_id=testlog_question_id
				AND testlog_testuser_id='
            . $testuser_id
            . '
				AND question_subject_id=subject_id
				AND subject_module_id=module_id
			ORDER BY testlog_id';
        $r = f_tce_test_results_query_result(F_db_query($sql, $db));
        if ($r) {
            echo '<div class="result-question-toolbar" aria-label="Фильтр вопросов">'
                . '<strong>Разбор ответов</strong><div role="group">'
                . '<button type="button" class="active" data-result-filter="all">Все</button>'
                . '<button type="button" data-result-filter="incorrect">С ошибками</button>'
                . '<button type="button" data-result-filter="unanswered">Без ответа</button>'
                . '</div></div>' . K_NEWLINE;
            echo '<ol class="question result-question-list">' . K_NEWLINE;
            while (($m = f_tce_test_results_question_row(F_db_fetch_array($r))) !== null) {
                $raw_question_type = $m['question_type'];
                $question_type = (int) $raw_question_type;
                // create per-topic results array
                if (!array_key_exists($m['module_id'], $topicresults)) {
                    $topicresults[$m['module_id']] = [
                        'name' => $m['module_name'],
                        'num' => 0,
                        'right' => 0,
                        'wrong' => 0,
                        'unanswered' => 0,
                        'undisplayed' => 0,
                        'unrated' => 0,
                        'score' => 0,
                        'maxscore' => 0,
                        'subjects' => [],
                    ];
                }

                if (!array_key_exists($m['subject_id'], $topicresults[$m['module_id']]['subjects'])) {
                    $topicresults[$m['module_id']]['subjects'][$m['subject_id']] = [
                        'name' => $m['subject_name'],
                        'num' => 0,
                        'right' => 0,
                        'wrong' => 0,
                        'unanswered' => 0,
                        'undisplayed' => 0,
                        'unrated' => 0,
                        'score' => 0,
                        'maxscore' => 0,
                    ];
                }

                $question_max_score = $m['question_difficulty'] * $test_basic_score;
                // total number of questions
                ++$topicresults[$m['module_id']]['num'];
                ++$topicresults[$m['module_id']]['subjects'][$m['subject_id']]['num'];
                // number of right answers
                if ($m['testlog_score'] > ($question_max_score / 2)) {
                    ++$topicresults[$m['module_id']]['right'];
                    ++$topicresults[$m['module_id']]['subjects'][$m['subject_id']]['right'];
                } else {
                    // number of wrong answers
                    ++$topicresults[$m['module_id']]['wrong'];
                    ++$topicresults[$m['module_id']]['subjects'][$m['subject_id']]['wrong'];
                }

                // total number of unanswered questions
                if (strlen(f_tce_test_results_string($m['testlog_change_time'])) <= 0) {
                    ++$topicresults[$m['module_id']]['unanswered'];
                    ++$topicresults[$m['module_id']]['subjects'][$m['subject_id']]['unanswered'];
                }

                // total number of undisplayed questions
                if (strlen(f_tce_test_results_string($m['testlog_display_time'])) <= 0) {
                    ++$topicresults[$m['module_id']]['undisplayed'];
                    ++$topicresults[$m['module_id']]['subjects'][$m['subject_id']]['undisplayed'];
                }

                // number of free-text unrated questions
                if (strlen(f_tce_test_results_string($m['testlog_score'])) <= 0) {
                    ++$topicresults[$m['module_id']]['unrated'];
                    ++$topicresults[$m['module_id']]['subjects'][$m['subject_id']]['unrated'];
                }

                // score
                $topicresults[$m['module_id']]['score'] += $m['testlog_score'];
                $topicresults[$m['module_id']]['subjects'][$m['subject_id']]['score'] += $m['testlog_score'];
                // max score
                $topicresults[$m['module_id']]['maxscore'] += $question_max_score;
                $topicresults[$m['module_id']]['subjects'][$m['subject_id']]['maxscore'] += $question_max_score;

                $question_score = f_tce_test_results_numeric_float($m['testlog_score']);
                if (strlen(f_tce_test_results_string($m['testlog_change_time'])) <= 0) {
                    $result_state = 'unanswered';
                } elseif ($question_score > (f_tce_test_results_numeric_float($question_max_score) / 2)) {
                    $result_state = 'correct';
                } else {
                    $result_state = 'incorrect';
                }
                echo '<li class="result-question result-question-' . $result_state
                    . '" data-result-state="' . $result_state . '">' . K_NEWLINE;
                // display question stats
                echo '<strong>[' . $m['testlog_score'] . ']' . K_NEWLINE;
                echo ' (';
                echo 'IP:' . get_ip_as_string($m['testlog_user_ip']) . K_NEWLINE;
                if (isset($m['testlog_display_time']) && strlen($m['testlog_display_time']) > 0) {
                    echo ' | ' . substr($m['testlog_display_time'], 11, 8) . K_NEWLINE;
                } else {
                    echo ' | --:--:--' . K_NEWLINE;
                }

                if (isset($m['testlog_change_time']) && strlen($m['testlog_change_time']) > 0) {
                    echo ' | ' . substr($m['testlog_change_time'], 11, 8) . K_NEWLINE;
                } else {
                    echo ' | --:--:--' . K_NEWLINE;
                }

                if (isset($m['testlog_display_time']) && isset($m['testlog_change_time'])) {
                    echo
                        ' | '
                            . date(
                                'i:s',
                                (int) strtotime($m['testlog_change_time']) - (int) strtotime($m['testlog_display_time']),
                            )
                            . ''
                    ;
                } else {
                    echo ' | --:--' . K_NEWLINE;
                }

                if (isset($m['testlog_reaction_time']) && $m['testlog_reaction_time'] > 0) {
                    echo ' | ' . ($m['testlog_reaction_time'] / 1000) . '';
                } else {
                    echo ' | ------' . K_NEWLINE;
                }

                echo ')</strong>' . K_NEWLINE;
                echo '<br />' . K_NEWLINE;
                // display question description
                echo F_decode_tcecode($m['question_description']) . K_NEWLINE;
                if (f_tce_test_results_bool(K_ENABLE_QUESTION_EXPLANATION) && !empty($m['question_explanation'])) {
                    echo
                        '<br /><span class="explanation">'
                            . $l['w_explanation']
                            . ':</span><br />'
                            . F_decode_tcecode($m['question_explanation'])
                            . ''
                            . K_NEWLINE
                    ;
                }

                if ($question_type === 3) {
                    // TEXT
                    echo '<ul class="answer"><li>' . K_NEWLINE;
                    echo F_decode_tcecode($m['testlog_answer_text']);
                    echo '&nbsp;</li></ul>' . K_NEWLINE;
                } else {
                    echo '<ol class="answer">' . K_NEWLINE;
                    // display each answer option
                    $sqla =
                        'SELECT *
						FROM '
                        . K_TABLE_LOG_ANSWER
                        . ', '
                        . K_TABLE_ANSWERS
                        . '
						WHERE logansw_answer_id=answer_id
							AND logansw_testlog_id=\''
                        . $m['testlog_id']
                        . '\'
						ORDER BY logansw_order';
                    $ra = f_tce_test_results_query_result(F_db_query($sqla, $db));
                    if ($ra) {
                        while (($ma = f_tce_test_results_answer_row(F_db_fetch_array($ra))) !== null) {
                            $raw_log_position = $ma['logansw_position'];
                            $raw_answer_position = $ma['answer_position'];
                            $raw_selected = $ma['logansw_selected'];
                            $log_position = (int) $raw_log_position;
                            $answer_position = (int) $raw_answer_position;
                            $selected = (int) $raw_selected;
                            echo '<li>';
                            if (in_array($question_type, [4, 5], true)) {
                                // ORDER / MATCHING
                                if ($log_position > 0) {
                                    if ($log_position === $answer_position) {
                                        echo
                                            '<abbr title="'
                                                . $l['h_answer_right']
                                                . '" class="okbox">'
                                                . $log_position
                                                . '</abbr>'
                                        ;
                                    } else {
                                        echo
                                            '<abbr title="'
                                                . $l['h_answer_wrong']
                                                . '" class="nobox">'
                                                . $log_position
                                                . '</abbr>'
                                        ;
                                    }
                                } else {
                                    echo '<abbr title="' . $l['m_unanswered'] . '" class="offbox">&nbsp;</abbr>';
                                }
                            } elseif ($selected > 0) {
                                if (f_get_boolean($ma['answer_isright'])) {
                                    echo '<abbr title="' . $l['h_answer_right'] . '" class="okbox">x</abbr>';
                                } else {
                                    echo '<abbr title="' . $l['h_answer_wrong'] . '" class="nobox">x</abbr>';
                                }
                            } elseif ($question_type === 1) {
                                // MCSA
                                echo '<abbr title="-" class="offbox">&nbsp;</abbr>';
                            } elseif ($selected === 0) {
                                if (f_get_boolean($ma['answer_isright'])) {
                                    echo '<abbr title="' . $l['h_answer_wrong'] . '" class="nobox">&nbsp;</abbr>';
                                } else {
                                    echo '<abbr title="' . $l['h_answer_right'] . '" class="okbox">&nbsp;</abbr>';
                                }
                            } else {
                                echo '<abbr title="' . $l['m_unanswered'] . '" class="offbox">&nbsp;</abbr>';
                            }

                            echo '&nbsp;';
                            if (in_array($question_type, [4, 5], true)) {
                                echo
                                    '<abbr title="'
                                        . $l['w_position']
                                        . '" class="onbox">'
                                        . $answer_position
                                        . '</abbr>'
                                ;
                            } elseif (f_get_boolean($ma['answer_isright'])) {
                                echo '<abbr title="' . $l['w_answers_right'] . '" class="onbox">&reg;</abbr>';
                            } else {
                                echo '<abbr title="' . $l['w_answers_wrong'] . '" class="offbox">&nbsp;</abbr>';
                            }

                            echo ' ';
                            echo F_decode_tcecode($ma['answer_description']);
                            if (f_tce_test_results_bool(K_ENABLE_ANSWER_EXPLANATION) && !empty($ma['answer_explanation'])) {
                                echo
                                    '<br /><span class="explanation">'
                                        . $l['w_explanation']
                                        . ':</span><br />'
                                        . F_decode_tcecode($ma['answer_explanation'])
                                        . ''
                                        . K_NEWLINE
                                ;
                            }

                            echo '</li>' . K_NEWLINE;
                        }
                    } else {
                        F_display_db_error();
                    }

                    echo '</ol>' . K_NEWLINE;
                } // end multiple answers
                // display teacher/supervisor comment to the question
                if (isset($m['testlog_comment']) && !empty($m['testlog_comment'])) {
                    echo '<ul class="answer"><li class="comment">' . K_NEWLINE;
                    echo F_decode_tcecode($m['testlog_comment']);
                    echo '&nbsp;</li></ul>' . K_NEWLINE;
                }

                echo '<br /><br />' . K_NEWLINE;
                echo '</li>' . K_NEWLINE;
            }

            echo '</ol>' . K_NEWLINE;
        } else {
            F_display_db_error();
        }
    }

    echo '</div>' . K_NEWLINE;

    // print per-topic results
    echo '<div class="rowl">' . K_NEWLINE;
    echo '<hr />' . K_NEWLINE;
    echo '<h2>' . $l['w_subjects'] . '</h2>';
    echo '<ul>';
    foreach ($topicresults as $res_module) {
        echo '<li>';
        $score_percent = round((100 * $res_module['score']) / $res_module['maxscore']);
        echo '<abbr title="' . $result_score_label . '" class="';
        if ($score_percent > 50) {
            echo 'okbox';
        } else {
            echo 'nobox';
        }

        echo '">' . $res_module['score'] . ' / ' . $res_module['maxscore'] . ' (' . $score_percent . '%)</abbr>';
        $score_percent = round((100 * $res_module['right']) / $res_module['num']);
        echo ' <abbr title="' . $result_right_label . '" class="';
        if ($score_percent > 50) {
            echo 'okbox';
        } else {
            echo 'nobox';
        }

        echo '">' . $res_module['right'] . ' / ' . $res_module['num'] . ' (' . $score_percent . '%)</abbr>';
        echo ' <strong>' . $res_module['name'] . '</strong>';
        echo '<ul>';
        foreach ($res_module['subjects'] as $res_subject) {
            echo '<li>';
            $score_percent = round((100 * $res_subject['score']) / $res_subject['maxscore']);
            echo '<abbr title="' . $result_score_label . '" class="';
            if ($score_percent > 50) {
                echo 'okbox';
            } else {
                echo 'nobox';
            }

            echo '">' . $res_subject['score'] . ' / ' . $res_subject['maxscore'] . ' (' . $score_percent . '%)</abbr>';
            $score_percent = round((100 * $res_subject['right']) / $res_subject['num']);
            echo ' <abbr title="' . $result_right_label . '" class="';
            if ($score_percent > 50) {
                echo 'okbox';
            } else {
                echo 'nobox';
            }

            echo '">' . $res_subject['right'] . ' / ' . $res_subject['num'] . ' (' . $score_percent . '%)</abbr>';
            echo ' ' . $res_subject['name'];
            echo '</li>' . K_NEWLINE;
        }

        echo '</ul>';
        echo '</li>' . K_NEWLINE;
    }

    echo '</ul>';
    echo '<hr />' . K_NEWLINE;
    echo '</div>' . K_NEWLINE;

    if (f_tce_test_results_bool(K_ENABLE_PUBLIC_PDF)) {
        echo '<div class="row">' . K_NEWLINE;
        // PDF button
        echo
            '<a href="'
                . f_tce_test_results_pdf_link($test_id, $user_id)
                . '" class="xmlbutton" title="'
                . $result_pdf_title
                . '">'
                . $result_pdf_label
                . '</a> '
        ;
        echo '</div>' . K_NEWLINE;
    }
}

echo '</div>' . K_NEWLINE;

echo '<div class="result-page-actions"><a href="index.php" title="' . $result_index_title
    . '">← Вернуться к испытаниям</a></div>' . K_NEWLINE;

echo '<div class="pagehelp">' . $result_page_help . '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;

require_once '../code/tce_page_footer.php';

/** Preserve legacy string conversion at explicitly string-based boundaries. */
function f_tce_test_results_string(mixed $value): string
{
    return is_array($value) ? 'Array' : (string) $value;
}

/** Preserve legacy positive-value comparisons. */
function f_tce_test_results_is_positive(mixed $value): bool
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

function f_tce_test_results_bool(bool $value): bool
{
    return $value;
}

function f_tce_test_results_numeric_float(mixed $value): float
{
    return is_numeric($value) ? (float) $value : 0.0;
}

/** @return object|resource|bool */
function f_tce_test_results_query_result(mixed $result): mixed
{
    /** @var object|resource|bool $result */
    return $result;
}

/** @return array<string,mixed> */
function f_tce_test_results_user_data(mixed $data): array
{
    /** @var array<string,mixed> $data */
    return $data;
}

/**
 * @return array{
 *     test_name:string,test_description:string,test_score_right:int|float|numeric-string,
 *     test_results_anonymized?:mixed,test_duration_time:int|float|numeric-string,test_report_to_users:mixed
 * }
 */
function f_tce_test_results_test_data(mixed $data): array
{
    /**
     * @var array{
     *     test_name:string,test_description:string,test_score_right:int|float|numeric-string,
     *     test_results_anonymized?:mixed,test_duration_time:int|float|numeric-string,test_report_to_users:mixed
     * } $data
     */
    return $data;
}

/**
 * @return array{
 *     test_start_time:string,test_end_time:int|string|null,score_threshold:int|float|numeric-string,
 *     score:int|float|numeric-string,max_score:int|float|numeric-string,right:int|numeric-string,
 *     all:int|numeric-string,comment:string,testuser_id:int|string
 * }
 */
function f_tce_test_results_user_test_data(mixed $data): array
{
    /**
     * @var array{
     *     test_start_time:string,test_end_time:int|string|null,score_threshold:int|float|numeric-string,
     *     score:int|float|numeric-string,max_score:int|float|numeric-string,right:int|numeric-string,
     *     all:int|numeric-string,comment:string,testuser_id:int|string
     * } $data
     */
    return $data;
}

/**
 * @return array{
 *     question_type:int|numeric-string,module_id:int|string,module_name:string,subject_id:int|string,
 *     subject_name:string,question_difficulty:int|float|numeric-string,testlog_score:numeric-string,
 *     testlog_change_time:string|null,testlog_display_time:string|null,testlog_user_ip:int|string,
 *     testlog_reaction_time:int|float|numeric-string|null,question_description:string,
 *     question_explanation:string|null,testlog_answer_text:string,testlog_id:int|string,testlog_comment:string|null
 * }|null
 */
function f_tce_test_results_question_row(mixed $row): ?array
{
    /**
     * @var array{
     *     question_type:int|numeric-string,module_id:int|string,module_name:string,subject_id:int|string,
     *     subject_name:string,question_difficulty:int|float|numeric-string,testlog_score:numeric-string,
     *     testlog_change_time:string|null,testlog_display_time:string|null,testlog_user_ip:int|string,
     *     testlog_reaction_time:int|float|numeric-string|null,question_description:string,
     *     question_explanation:string|null,testlog_answer_text:string,testlog_id:int|string,testlog_comment:string|null
     * }|null $row
     */
    return $row;
}

/**
 * @return array{
 *     logansw_position:int|numeric-string,answer_position:int|numeric-string,logansw_selected:int|numeric-string,
 *     answer_isright:mixed,answer_description:string,answer_explanation:string|null
 * }|null
 */
function f_tce_test_results_answer_row(mixed $row): ?array
{
    /**
     * @var array{
     *     logansw_position:int|numeric-string,answer_position:int|numeric-string,logansw_selected:int|numeric-string,
     *     answer_isright:mixed,answer_description:string,answer_explanation:string|null
     * }|null $row
     */
    return $row;
}

function f_tce_test_results_pdf_link(int $test_id, int $user_id): string
{
    $pdf_link = ltrim(__NAMESPACE__ . '\\pdfLink', '\\');
    /** @var callable(int,int,int,int,string,int):string $pdf_link */
    return $pdf_link(3, $test_id, 0, $user_id, '', 0);
}
