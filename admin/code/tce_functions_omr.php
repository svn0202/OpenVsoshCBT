<?php

//============================================================+
// File name   : tce_functions_omr.php
// Begin       : 2011-05-17
// Last Update : 2023-11-30
//
// Description : Functions to import test data from scanned
//               OMR (Optical Mark Recognition) sheets.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

/**
 * @file
 * Functions to import test data from scanned OMR (Optical Mark Recognition) sheets.
 * @package com.tecnick.tcexam.shared
 * @author Nicola Asuni
 * @since 2011-05-17
 */

/** @return resource|false */
function f_omr_open_dir_silently(string $directory): mixed
{
    set_error_handler(static fn(): bool => true);
    try {
        return opendir($directory);
    } finally {
        restore_error_handler();
    }
}

/**
 * Remove a best-effort OMR upload artifact without exposing cleanup races.
 */
function f_omr_unlink_silently(string $filename): bool
{
    set_error_handler(static fn(): bool => true);
    try {
        return unlink($filename);
    } finally {
        restore_error_handler();
    }
}

/**
 * Encode OMR test data array as a string to be printed on QR-Code.
 * @param $data (array) array to be encoded
 * @return string encoded data.
 */
function f_encode_omr_test_data(mixed $data): string
{
    $str = serialize($data);
    $str = gzcompress($str, 9); // requires php-zlib extension
    $str = base64_encode((string) $str);
    return urlencode($str);
}

/**
 * Decode OMR test data string (read from QR-Code) as array.
 * @param $str (string) string to be decoded.
 * @return array<array-key, mixed>|false decoded test data, or false in case of error.
 */
function f_decode_omr_test_data(mixed $str): array|false
{
    $max_encoded_bytes = 1_048_576;
    $max_decompressed_bytes = 4_194_304;
    $max_questions = 10_000;
    /** @return array<array-key,mixed>|null */
    $normalize_array = static fn (mixed $value): ?array => is_array($value) ? $value : null;

    if (!is_string($str) || $str === '' || strlen($str) > $max_encoded_bytes) {
        return false;
    }

    $encoded = urldecode($str);
    if (strlen($encoded) > $max_encoded_bytes) {
        return false;
    }
    $compressed = base64_decode($encoded, true);
    if (!is_string($compressed) || strlen($compressed) > $max_encoded_bytes) {
        return false;
    }
    set_error_handler(static fn(): bool => true);
    try {
        $data = gzuncompress($compressed, $max_decompressed_bytes);
    } finally {
        restore_error_handler();
    }
    if (!is_string($data) || strlen($data) > $max_decompressed_bytes) {
        return false;
    }
    try {
        $decoded = $normalize_array(unserialize($data, ['allowed_classes' => false]));
    } catch (Throwable) {
        return false;
    }
    if (!is_array($decoded) || count($decoded) < 1 || count($decoded) > ($max_questions + 1)) {
        return false;
    }
    if (!isset($decoded[0]) || !is_numeric($decoded[0]) || (int) $decoded[0] <= 0) {
        return false;
    }
    for ($index = 1, $count = count($decoded); $index < $count; ++$index) {
        $question = $normalize_array($decoded[$index] ?? null);
        if (
            $question === null
            || !array_key_exists(0, $question)
            || !array_key_exists(1, $question)
            || !is_numeric($question[0])
            || (int) $question[0] <= 0
            || !is_array($question[1])
            || count($question[1]) > 1_000
        ) {
            return false;
        }
        foreach (array_keys($question[1]) as $position) {
            if (
                !is_numeric($position)
                || (int) $position <= 0
                || !is_numeric($question[1][$position] ?? null)
                || (int) $question[1][$position] <= 0
            ) {
                return false;
            }
        }
    }
    return $decoded;
}

/**
 * Read QR-Code from OMR page and return Test data.
 * This function uses the external application zbarimg (http://zbar.sourceforge.net/).
 * @param $image (string) image file to be decoded (scanned OMR page).
 * @return array<array-key, mixed>|false test data, or false in case of error
 */
function f_decode_omr_test_data_qr_code(mixed $image): array|false
{
    require_once '../config/tce_config.php';
    if (empty($image)) {
        return false;
    }

    $command = K_OMR_PATH_ZBARIMG . ' --raw -Sdisable -Sqrcode.enable -q ' . escapeshellarg((string) $image);
    $str = exec($command);
    return f_decode_omr_test_data($str);
}

/**
 * Decode a single OMR Page and return data array.
 * This function requires ImageMagick library and zbarimg (http://zbar.sourceforge.net/).
 * @param $image (string) image file to be decoded (scanned OMR page at 200 DPI with full color range).
 * @return array<array-key,mixed>|false Array of answers data or false in case of error.
 */
function f_decode_omr_page(mixed $image): array|false
{
    require_once '../config/tce_config.php';
    // decode barcode containing first question number
    $image_path = (string) $image;
    $command = K_OMR_PATH_ZBARIMG . ' --raw -Sdisable -Scode128.enable -q ' . escapeshellarg($image_path);
    $qstart = exec($command);
    $qstart = (int) $qstart;
    if ($qstart === 0) {
        return false;
    }

    $img = new Imagick();
    $img->readImage($image_path);

    /** @var array{type:string,geometry:array{width:int,height:int}} $imginfo */
    $imginfo = $img->identifyImage();
    $image_type = $imginfo['type'];
    if ($image_type === 'TrueColor') {
        // remove red color
        $img->separateImageChannel(Imagick::CHANNEL_RED);
    } else {
        // desaturate image
        $img->modulateImage(100, 0, 100);
    }

    // get image width and height
    $w = $imginfo['geometry']['width'];
    $h = $imginfo['geometry']['height'];
    if ($h > $w) {
        // crop header and footer
        $y = (int) round(($h - $w) / 2);
        $img->cropImage($w, $w, 0, $y);
        $img->setImagePage(0, 0, 0, 0);
    }

    $img->normalizeImage(Imagick::CHANNEL_ALL);
    $img->enhanceImage();
    $img->despeckleImage();
    $img->blackthresholdImage('#808080');
    $img->whitethresholdImage('#808080');
    $img->trimImage(85);
    $img->deskewImage(15);
    $img->trimImage(85);
    $img->resizeImage(1028, 1052, Imagick::FILTER_CUBIC, 1);
    $img->setImagePage(0, 0, 0, 0);
    //$img->writeImage(K_PATH_CACHE.'_DEBUG_OMR_.PNG'); // DEBUG
    // scan block width
    $blkw = 16;
    // starting column in pixels
    $scol = 106;
    // starting row in pixels
    $srow = 49;
    // column distance in pixels between two answers
    $dcol = 75.364;
    // column distance in pixels between True/false circles
    $dtf = 25;
    // row distance in pixels between two questions
    $drow = 32.38;
    // verify image pattern
    $imgtmp = clone $img;
    $imgtmp->cropImage(1028, 10, 0, 10);
    $imgtmp->setImagePage(0, 0, 0, 0);
    // create reference block pattern
    $impref = new Imagick();
    $impref->newImage(3, 10, 'black');

    $psum = 0;
    for ($c = 0; $c < 12; ++$c) {
        $x = (int) round(112 + ($c * $dcol));
        // get square region inside the current grid position
        $imreg = $img->getImageRegion(3, 10, $x, 0);
        $imreg->setImagePage(0, 0, 0, 0);
        // get root-mean-square-error with reference image
        /** @var array{0:Imagick,1:float} $rmse */
        $rmse = $imreg->compareImages($impref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
        // count reference blocks
        $psum += round(1.25 - $rmse[1]);
    }

    $imreg->clear();
    $impref->clear();
    if ($psum !== 12.0) {
        return false;
    }

    // create reference block
    $imref = new Imagick();
    $imref->newImage($blkw, $blkw, 'black');
    // array to be returned
    $omrdata = [];
    // for each row (question)
    for ($r = 0; $r < 30; ++$r) {
        $omrdata[$r + $qstart] = [];
        $y = (int) round($srow + ($r * $drow));
        // for each column (answer)
        for ($c = 0; $c < 12; ++$c) {
            // read true option
            $x = (int) round($scol + ($c * $dcol));
            // get square region inside the current grid position
            $imreg = $img->getImageRegion($blkw, $blkw, $x, $y);
            $imreg->setImagePage(0, 0, 0, 0);
            // get root-mean-square-error with reference image
            /** @var array{0:Imagick,1:float} $rmse */
            $rmse = $imreg->compareImages($imref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
            // true option
            $opt_true = 2 * round(1.25 - $rmse[1]);
            // read false option
            $x += $dtf;
            // get square region inside the current grid position
            $imreg = $img->getImageRegion($blkw, $blkw, $x, $y);
            $imreg->setImagePage(0, 0, 0, 0);
            // get root-mean-square-error with reference image
            /** @var array{0:Imagick,1:float} $rmse */
            $rmse = $imreg->compareImages($imref, Imagick::METRIC_ROOTMEANSQUAREDERROR);
            // false option
            $opt_false = round(1.25 - $rmse[1]);
            // set array to be returned (-1 = unset, 0 = false, 1 = true)
            $val = $opt_true + $opt_false - 1;
            if ($val > 1) {
                $val = 1;
            }

            $omrdata[$r + $qstart][$c + 1] = $val;
        }
    }

    $imreg->clear();
    $imref->clear();
    return $omrdata;
}

/**
 * Import user's test data from OMR.
 * @param $user_id (int) user ID.
 * @param $date (string) date-time field.
 * @param $omr_testdata (array) Array containing test data.
 * @param $omr_answers (array) Array containing test answers (from OMR).
 * @param $overwrite (boolean) If true overwrites the previous answers on non-repeatable tests.
 * @return boolean TRUE in case of success, FALSE otherwise.
 */
function f_import_omr_test_data(
    mixed $user_id,
    mixed $date,
    mixed $omr_testdata,
    mixed $omr_answers,
    mixed $overwrite = false,
): bool
{
    require_once '../config/tce_config.php';
    require_once '../../shared/code/tce_functions_test.php';
    global $db, $l;
    /** @var array<int,mixed> $omr_testdata */
    /** @var array<int,array<int,mixed>> $omr_answers */
    /** @return array<array-key,mixed>|null */
    $normalize_row = static fn (mixed $row): ?array => is_array($row) ? $row : null;
    $normalize_query_result = static function (mixed $result): mixed {
        if (
            is_bool($result)
            || is_resource($result)
            || $result instanceof \mysqli_result
            || $result instanceof \PgSql\Result
        ) {
            return $result;
        }
        return false;
    };
    // check arrays
    if (count($omr_testdata) > (count($omr_answers) + 1)) {
        // arrays must contain the same amount of questions
        return false;
    }

    $test_id = (int) ($omr_testdata[0] ?? 0);
    $user_id = (int) $user_id;
    $time = (int) strtotime((string) $date);
    $date = date(K_TIMESTAMP_FORMAT, $time);
    $dateanswers = date(K_TIMESTAMP_FORMAT, $time + 1);
    // check user's group
    if (
        (int) F_count_rows(
            K_TABLE_USERGROUP
            . ', '
            . K_TABLE_TEST_GROUPS
            . ' WHERE usrgrp_group_id=tstgrp_group_id AND tstgrp_test_id='
            . $test_id
            . ' AND usrgrp_user_id='
            . $user_id
            . ' LIMIT 1',
        ) === 0
    ) {
        return false;
    }

    // get test data
    $testdata = f_get_test_data($test_id);
    /**
     * @var array{
     *     test_score_right:int|float|numeric-string,
     *     test_score_wrong:int|float|numeric-string,
     *     test_score_unanswered:int|float|numeric-string,
     *     test_mcma_partial_score:mixed
     * } $testdata
     */
    // 1. check if test is repeatable
    $sqls = 'SELECT test_id FROM ' . K_TABLE_TESTS . ' WHERE test_id=' . $test_id . " AND test_repeatable='1' LIMIT 1";
    if ($rs = $normalize_query_result(F_db_query($sqls, $db))) {
        if ($ms = $normalize_row(F_db_fetch_array($rs))) {
            // 1a. update previous test data if repeatable
            $sqld =
                'UPDATE '
                . K_TABLE_TEST_USER
                . ' SET testuser_status=testuser_status+1 WHERE testuser_test_id='
                . $test_id
                . ' AND testuser_user_id='
                . $user_id
                . ' AND testuser_status>3';
            if (!($rd = $normalize_query_result(F_db_query($sqld, $db)))) {
                F_display_db_error();
            }
        } elseif ($overwrite) {
            // 1b. delete previous test data if not repeatable
            $sqld =
                'DELETE FROM '
                . K_TABLE_TEST_USER
                . ' WHERE testuser_test_id='
                . $test_id
                . ' AND testuser_user_id='
                . $user_id
                . '';
            if (!($rd = $normalize_query_result(F_db_query($sqld, $db)))) {
                F_display_db_error();
            }
        } elseif (
            F_count_rows(
                K_TABLE_TEST_USER,
                'WHERE testuser_test_id=' . $test_id . ' AND testuser_user_id=' . $user_id . '',
            ) > 0
        ) {
            // 1c. check if this data already exist
            return false;
        }
    } else {
        F_display_db_error();
    }

    // 2. create new user's test entry
    // ------------------------------
    $sql = 'INSERT INTO ' . K_TABLE_TEST_USER . ' (
		testuser_test_id,
		testuser_user_id,
		testuser_status,
		testuser_creation_time,
		testuser_comment
		) VALUES (
		' . $test_id . ',
		' . $user_id . ',
		4,
		\'' . $date . '\',
		\'OMR\'
		)';
    if (!($r = $normalize_query_result(F_db_query($sql, $db)))) {
        F_display_db_error(false);
        return false;
    }

    // get inserted ID
    $testuser_id = F_db_insert_id($db, K_TABLE_TEST_USER, 'testuser_id');
    f_update_testuser_stat($date);

    // 3. create test log entries
    $num_questions = count($omr_testdata) - 1;
    // for each question on array
    for ($q = 1; $q <= $num_questions; ++$q) {
        $omr_question = $normalize_row($omr_testdata[$q] ?? null);
        $answer_ids = $normalize_row($omr_question[1] ?? null);
        if ($omr_question === null || $answer_ids === null) {
            return false;
        }
        $question_id = (int) ($omr_question[0] ?? 0);
        $num_answers = count($answer_ids);
        // get question data
        $sqlq =
            'SELECT question_type, question_difficulty FROM '
            . K_TABLE_QUESTIONS
            . ' WHERE question_id='
            . $question_id
            . ' LIMIT 1';
        if ($rq = $normalize_query_result(F_db_query($sqlq, $db))) {
            if ($mq = $normalize_row(F_db_fetch_array($rq))) {
                /** @var array{question_type:int|numeric-string,question_difficulty:int|float|numeric-string} $mq */
                // question scores
                $raw_question_type = $mq['question_type'];
                $question_type = (int) $raw_question_type;
                $question_difficulty = $mq['question_difficulty'];
                $test_score_right = $testdata['test_score_right'];
                $test_score_wrong = $testdata['test_score_wrong'];
                $test_score_unanswered = $testdata['test_score_unanswered'];
                $question_right_score = $test_score_right * $question_difficulty;
                $question_wrong_score = $test_score_wrong * $question_difficulty;
                $question_unanswered_score = $test_score_unanswered * $question_difficulty;
                // add question
                $sqll =
                    'INSERT INTO '
                    . K_TABLE_TESTS_LOGS
                    . ' (
					testlog_testuser_id,
					testlog_question_id,
					testlog_score,
					testlog_creation_time,
					testlog_display_time,
					testlog_reaction_time,
					testlog_order,
					testlog_num_answers
					) VALUES (
					'
                    . $testuser_id
                    . ',
					'
                    . $question_id
                    . ',
					'
                    . $question_unanswered_score
                    . ',
					\''
                    . $date
                    . '\',
					\''
                    . $date
                    . '\',
					1,
					'
                    . $q
                    . ',
					'
                    . $num_answers
                    . '
					)';
                if (!($rl = $normalize_query_result(F_db_query($sqll, $db)))) {
                    F_display_db_error(false);
                    return false;
                }

                $testlog_id = F_db_insert_id($db, K_TABLE_TESTS_LOGS, 'testlog_id');
                // set initial question score
                $qscore = $question_type === 1 ? $question_unanswered_score : 0;

                $unanswered = true;
                $numselected = 0; // count the number of MCSA selected answers
                // for each answer on array
                for ($a = 1; $a <= $num_answers; ++$a) {
                    $answer_id = (int) ($answer_ids[$a] ?? 0);
                    if (isset($omr_answers[$q][$a])) {
                        $answer_selected = (int) $omr_answers[$q][$a]; //-1, 0, 1
                    } else {
                        $answer_selected = -1;
                    }

                    // add answer
                    $sqli =
                        'INSERT INTO '
                        . K_TABLE_LOG_ANSWER
                        . ' (
						logansw_testlog_id,
						logansw_answer_id,
						logansw_selected,
						logansw_order
						) VALUES (
						'
                        . $testlog_id
                        . ',
						'
                        . $answer_id
                        . ',
						'
                        . $answer_selected
                        . ',
						'
                        . $a
                        . '
						)';
                    if (!($ri = $normalize_query_result(F_db_query($sqli, $db)))) {
                        F_display_db_error(false);
                        return false;
                    }

                    // calculate question score
                    if ($question_type < 3) { // MCSA or MCMA
                        // check if the answer is right
                        $answer_isright = false;
                        $sqla =
                            'SELECT answer_isright FROM '
                            . K_TABLE_ANSWERS
                            . ' WHERE answer_id='
                            . $answer_id
                            . ' LIMIT 1';
                        if ($ra = $normalize_query_result(F_db_query($sqla, $db))) {
                            if ($ma = $normalize_row(F_db_fetch_array($ra))) {
                                /** @var array{answer_isright:mixed} $ma */
                                $answer_isright = f_get_boolean($ma['answer_isright']);
                                switch ($question_type) {
                                    case 1: // MCSA - Multiple Choice Single Answer
                                        if ($answer_selected === 1) {
                                            ++$numselected;
                                            if ($numselected === 1) {
                                                $unanswered = false;
                                                $qscore = $answer_isright
                                                    ? $question_right_score
                                                    : $question_wrong_score;
                                            } else {
                                                // multiple answer selected
                                                $unanswered = true;
                                                $qscore = $question_unanswered_score;
                                            }
                                        }

                                        break;
                                    case 2: // MCMA - Multiple Choice Multiple Answer
                                        if ($answer_selected === -1) {
                                            $qscore += $question_unanswered_score;
                                        } elseif ($answer_selected === 0) {
                                            $unanswered = false;
                                            if ($answer_isright) {
                                                $qscore += $question_wrong_score;
                                            } else {
                                                $qscore += $question_right_score;
                                            }
                                        } elseif ($answer_selected === 1) {
                                            $unanswered = false;
                                            if ($answer_isright) {
                                                $qscore += $question_right_score;
                                            } else {
                                                $qscore += $question_wrong_score;
                                            }
                                        }

                                        break;
                                }
                            }
                        } else {
                            F_display_db_error(false);
                            return false;
                        }
                    }
                }

                // end for each answer
                if ($question_type === 2) { // MCMA
                    // normalize score
                    if (f_get_boolean($testdata['test_mcma_partial_score'])) {
                        // use partial scoring for MCMA and ORDER questions
                        $qscore = round($qscore / $num_answers, 3);
                    } elseif ($qscore >= ($question_right_score * $num_answers)) {
                        // all-or-nothing points
                        // right
                        $qscore = $question_right_score;
                    } elseif ((float) $qscore === (float) ($question_unanswered_score * $num_answers)) {
                        // unanswered
                        $qscore = $question_unanswered_score;
                    } else {
                        // wrong
                        $qscore = $question_wrong_score;
                    }
                }

                $change_time = $unanswered ? '' : $dateanswers;

                // update question score
                $sqll =
                    'UPDATE '
                    . K_TABLE_TESTS_LOGS
                    . ' SET
					testlog_score='
                    . $qscore
                    . ',
					testlog_change_time='
                    . f_empty_to_null($change_time)
                    . ',
					testlog_reaction_time=1000
					WHERE testlog_id='
                    . $testlog_id
                    . '';
                if (!($rl = $normalize_query_result(F_db_query($sqll, $db)))) {
                    F_display_db_error();
                    return false;
                }
            }
        } else {
            F_display_db_error(false);
            return false;
        }
    }

    // end for each question
    return true;
}
