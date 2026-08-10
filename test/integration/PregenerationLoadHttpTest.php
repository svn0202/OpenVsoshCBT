<?php

namespace Test\Integration;

/**
 * Opt-in, authenticated concurrent start benchmark.
 *
 * Run with TMF_PREGEN_LOAD_PARTICIPANTS set to the planned cohort size.
 */
final class PregenerationLoadHttpTest extends AppHttpTestCase
{
    private const PASSWORD = 'itest-load-password';

    private mixed $db = null;

    protected function setUp(): void
    {
        parent::setUp();
        $type = (string) getenv('TCEXAM_DB_TYPE');
        require_once $type === 'POSTGRESQL'
            ? __DIR__ . '/../../shared/code/tce_db_dal_postgresql.php'
            : __DIR__ . '/../../shared/code/tce_db_dal_mysqli.php';
        $this->db = \F_db_connect(
            (string) getenv('TCEXAM_DB_HOST'),
            (string) getenv('TCEXAM_DB_PORT'),
            (string) getenv('TCEXAM_DB_USER'),
            (string) getenv('TCEXAM_DB_PASSWORD'),
            (string) getenv('TCEXAM_DB_NAME'),
        );
        self::assertNotFalse($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            \F_db_close($this->db);
            $this->db = null;
        }
        parent::tearDown();
    }

    private function dbExec(string $sql): void
    {
        self::assertNotFalse(\F_db_query($sql, $this->db), $sql);
    }

    private function dbScalar(string $sql): ?string
    {
        $result = \F_db_query($sql, $this->db);
        self::assertNotFalse($result, $sql);
        $row = \F_db_fetch_array($result);
        return is_array($row) ? (string) $row[0] : null;
    }

    /**
     * @return array<string,string>
     */
    private function loginCredentials(
        string $username,
        #[\SensitiveParameter] string $password = self::PASSWORD,
        string $entrypoint = '/public/code/index.php',
    ): array
    {
        [, , $cookies] = $this->http('GET', $entrypoint);
        [, , $cookies] = $this->http('POST', $entrypoint, $cookies, [
            'logaction' => 'login',
            'xuser_name' => $username,
            'xuser_password' => $password,
        ]);
        return $cookies;
    }

    /**
     * @param list<array<string,string>> $cookieSets
     * @return array{statuses:list<int>,milliseconds:list<float>,wall_milliseconds:float}
     */
    private function concurrentGet(array $cookieSets, string $path): array
    {
        if (!function_exists('curl_multi_init')) {
            self::markTestSkipped('The cURL extension is required for the load profile.');
        }
        $multi = curl_multi_init();
        $handles = [];
        foreach ($cookieSets as $index => $cookies) {
            $handle = curl_init($this->base . $path);
            self::assertNotFalse($handle);
            $pairs = [];
            foreach ($cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_COOKIE => implode('; ', $pairs),
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$index] = $handle;
        }

        $started = hrtime(true);
        $running = 0;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($status !== CURLM_OK) {
                break;
            }
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0);
        $wall = (hrtime(true) - $started) / 1_000_000;

        $statuses = [];
        $milliseconds = [];
        foreach ($handles as $handle) {
            $statuses[] = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $milliseconds[] = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000;
            curl_multi_remove_handle($multi, $handle);
        }
        curl_multi_close($multi);
        return [
            'statuses' => $statuses,
            'milliseconds' => $milliseconds,
            'wall_milliseconds' => $wall,
        ];
    }

    /**
     * @param list<float> $values
     */
    private static function percentile(array $values, float $percentile): float
    {
        sort($values, SORT_NUMERIC);
        if ($values === []) {
            return 0.0;
        }
        $index = (int) ceil((count($values) - 1) * $percentile);
        return round($values[$index], 3);
    }

    public function testConcurrentStartsWithAndWithoutPregeneration(): void
    {
        $participantCount = (int) getenv('TMF_PREGEN_LOAD_PARTICIPANTS');
        if ($participantCount === 0) {
            self::markTestSkipped(
                'Set TMF_PREGEN_LOAD_PARTICIPANTS to run the pregeneration load profile.',
            );
        }
        self::assertGreaterThanOrEqual(2, $participantCount);
        self::assertLessThanOrEqual(500, $participantCount);
        $maximumP95Setting = getenv('TMF_PREGEN_LOAD_MAX_P95_MS');
        if (!$maximumP95Setting) {
            $maximumP95Setting = 10_000;
        }
        $maximumP95 = (float) $maximumP95Setting;

        $suffix = bin2hex(random_bytes(5));
        $adminPassword = 'itest-load-admin-' . $suffix;
        $adminId = (int) ($this->dbScalar(
            "SELECT user_id FROM tce_users WHERE user_name='admin'"
        ) ?? '0');
        $adminOriginalHash = $this->dbScalar(
            "SELECT user_password FROM tce_users WHERE user_name='admin'"
        );
        $groupName = 'itest_load_group_' . $suffix;
        $testName = 'itest_load_test_' . $suffix;
        $moduleName = 'itest_load_module_' . $suffix;
        $subjectName = 'itest_load_subject_' . $suffix;
        $groupId = 0;
        $testId = 0;
        $moduleId = 0;
        $subjectId = 0;
        $subsetId = 0;
        $userIds = [];
        $directCookies = [];
        $preparedCookies = [];

        try {
            self::assertGreaterThan(0, $adminId, 'The integration fixture must contain an admin user.');
            self::assertNotNull($adminOriginalHash);
            $adminHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 4]);
            self::assertIsString($adminHash);
            $this->dbExec(
                "UPDATE tce_users SET user_password='"
                . \F_escape_sql($this->db, $adminHash)
                . "' WHERE user_id=" . $adminId
            );

            $this->dbExec("INSERT INTO tce_user_groups (group_name) VALUES ('" . $groupName . "')");
            $groupId = (int) ($this->dbScalar(
                "SELECT group_id FROM tce_user_groups WHERE group_name='" . $groupName . "'"
            ) ?? '0');
            $this->dbExec(
                "INSERT INTO tce_modules (module_name,module_enabled,module_user_id) VALUES ('"
                . $moduleName . "','1'," . $adminId . ')'
            );
            $moduleId = (int) ($this->dbScalar(
                "SELECT module_id FROM tce_modules WHERE module_name='" . $moduleName . "'"
            ) ?? '0');
            $this->dbExec(
                "INSERT INTO tce_subjects (subject_module_id,subject_name,subject_description,"
                . "subject_enabled,subject_user_id) VALUES (" . $moduleId . ",'"
                . $subjectName . "','load','1'," . $adminId . ')'
            );
            $subjectId = (int) ($this->dbScalar(
                "SELECT subject_id FROM tce_subjects WHERE subject_name='" . $subjectName . "'"
            ) ?? '0');
            for ($question = 1; $question <= 40; ++$question) {
                $this->dbExec(
                    "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
                    . "question_difficulty,question_enabled,question_position) VALUES ("
                    . $subjectId . ",'Load question " . $question . "',1,1,'1'," . $question . ')'
                );
                $questionId = (int) ($this->dbScalar(
                    'SELECT MAX(question_id) FROM tce_questions WHERE question_subject_id=' . $subjectId
                ) ?? '0');
                for ($answer = 1; $answer <= 4; ++$answer) {
                    $this->dbExec(
                        "INSERT INTO tce_answers (answer_question_id,answer_description,answer_isright,"
                        . "answer_enabled,answer_position) VALUES (" . $questionId . ",'Answer "
                        . $answer . "','" . ($answer === 1 ? '1' : '0') . "','1'," . $answer . ')'
                    );
                }
            }
            $this->dbExec(
                "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time,"
                . "test_begin_time,test_end_time,test_random_questions_select,test_random_questions_order,"
                . "test_random_answers_select,test_random_answers_order) VALUES ('"
                . $testName . "','load'," . $adminId
                . ",60,'2020-01-01 00:00:00','2035-01-01 00:00:00','1','1','1','1')"
            );
            $testId = (int) ($this->dbScalar(
                "SELECT test_id FROM tce_tests WHERE test_name='" . $testName . "'"
            ) ?? '0');
            $this->dbExec(
                'INSERT INTO tce_testgroups (tstgrp_test_id,tstgrp_group_id) VALUES ('
                . $testId . ',' . $groupId . ')'
            );
            $this->dbExec(
                'INSERT INTO tce_test_subject_set (tsubset_test_id,tsubset_type,tsubset_difficulty,'
                . 'tsubset_quantity,tsubset_answers) VALUES (' . $testId . ',1,1,20,4)'
            );
            $subsetId = (int) ($this->dbScalar(
                'SELECT MAX(tsubset_id) FROM tce_test_subject_set WHERE tsubset_test_id=' . $testId
            ) ?? '0');
            $this->dbExec(
                'INSERT INTO tce_test_subjects (subjset_tsubset_id,subjset_subject_id) VALUES ('
                . $subsetId . ',' . $subjectId . ')'
            );

            $passwordHash = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
            for ($index = 1; $index <= ($participantCount * 2); ++$index) {
                $username = 'itest_load_' . $suffix . '_' . $index;
                $this->dbExec(
                    "INSERT INTO tce_users (user_name,user_password,user_regdate,user_ip,user_level) VALUES ('"
                    . $username . "','" . $passwordHash . "','2026-01-01 00:00:00','127.0.0.1',1)"
                );
                $userId = (int) ($this->dbScalar(
                    "SELECT user_id FROM tce_users WHERE user_name='" . $username . "'"
                ) ?? '0');
                $userIds[] = $userId;
                $this->dbExec(
                    'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                    . $userId . ',' . $groupId . ')'
                );
                $cookies = $this->loginCredentials($username);
                if ($index <= $participantCount) {
                    $directCookies[] = $cookies;
                } else {
                    $preparedCookies[] = $cookies;
                }
            }

            $direct = $this->concurrentGet(
                $directCookies,
                '/public/code/tce_test_execute.php?testid=' . $testId,
            );
            self::assertSame(
                array_fill(0, $participantCount, 200),
                $direct['statuses'],
                'Every direct start must succeed.',
            );

            $adminCookies = $this->loginCredentials(
                'admin',
                $adminPassword,
                '/admin/code/index.php',
            );
            $generationStarted = hrtime(true);
            $prepared = 0;
            $maximumBatches = (int) ceil($participantCount / 25) + 1;
            for ($batch = 1; $prepared < $participantCount && $batch <= $maximumBatches; ++$batch) {
                $preparedBefore = $prepared;
                [, $generationPage] = $this->http(
                    'GET',
                    '/admin/code/tce_pregenerate.php?test_id=' . $testId,
                    $adminCookies,
                );
                $token = self::extractCsrfToken($generationPage);
                self::assertNotNull($token);
                [$generationStatus] = $this->http(
                    'POST',
                    '/admin/code/tce_pregenerate.php',
                    $adminCookies,
                    [
                        'test_id' => (string) $testId,
                        'pregenerate' => '1',
                        'csrf_token' => $token,
                    ],
                );
                self::assertSame(200, $generationStatus);
                $prepared = (int) ($this->dbScalar(
                    'SELECT COUNT(*) FROM tce_tests_users WHERE testuser_test_id=' . $testId
                    . " AND testuser_pregenerated='1'"
                ) ?? '0');
                self::assertGreaterThan(
                    $preparedBefore,
                    $prepared,
                    'Each pregeneration batch must make progress.',
                );
            }
            self::assertSame($participantCount, $prepared);
            $generationMilliseconds = (hrtime(true) - $generationStarted) / 1_000_000;

            $pregenerated = $this->concurrentGet(
                $preparedCookies,
                '/public/code/tce_test_execute.php?testid=' . $testId,
            );
            self::assertSame(
                array_fill(0, $participantCount, 200),
                $pregenerated['statuses'],
                'Every pregenerated start must succeed.',
            );
            self::assertSame(
                (string) ($participantCount * 2),
                $this->dbScalar(
                    'SELECT COUNT(*) FROM tce_tests_users WHERE testuser_test_id=' . $testId
                ),
            );
            self::assertSame(
                '0',
                $this->dbScalar(
                    'SELECT COUNT(*) FROM tce_tests_users WHERE testuser_test_id=' . $testId
                    . " AND testuser_pregenerated='1'"
                ),
            );

            $directP95 = self::percentile($direct['milliseconds'], 0.95);
            $pregeneratedP95 = self::percentile($pregenerated['milliseconds'], 0.95);
            $report = [
                'database' => (string) getenv('TCEXAM_DB_TYPE'),
                'participants_per_cohort' => $participantCount,
                'concurrent_requests' => $participantCount,
                'questions_available' => 40,
                'questions_per_variant' => 20,
                'answers_per_question' => 4,
                'direct_start' => [
                    'p50_ms' => self::percentile($direct['milliseconds'], 0.50),
                    'p95_ms' => $directP95,
                    'wall_ms' => round($direct['wall_milliseconds'], 3),
                ],
                'pregeneration_batch_ms' => round($generationMilliseconds, 3),
                'pregenerated_start' => [
                    'p50_ms' => self::percentile($pregenerated['milliseconds'], 0.50),
                    'p95_ms' => $pregeneratedP95,
                    'wall_ms' => round($pregenerated['wall_milliseconds'], 3),
                ],
                'maximum_p95_ms' => $maximumP95,
                'p95_improvement_percent' => $directP95 > 0
                    ? round((1 - ($pregeneratedP95 / $directP95)) * 100, 2)
                    : 0,
            ];
            $reportDirectory = __DIR__ . '/../../target/report';
            if (!is_dir($reportDirectory)) {
                self::assertTrue(mkdir($reportDirectory, 0o770, true));
            }
            self::assertNotFalse(file_put_contents(
                $reportDirectory . '/pregeneration-load.json',
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ));
            self::assertLessThanOrEqual($maximumP95, $pregeneratedP95);
            self::assertLessThan($directP95, $pregeneratedP95);
        } finally {
            if ($testId > 0) {
                $this->dbExec(
                    'DELETE FROM tce_tests_logs_answers WHERE logansw_testlog_id IN (SELECT testlog_id '
                    . 'FROM tce_tests_logs WHERE testlog_testuser_id IN (SELECT testuser_id '
                    . 'FROM tce_tests_users WHERE testuser_test_id=' . $testId . '))'
                );
                $this->dbExec(
                    'DELETE FROM tce_tests_logs WHERE testlog_testuser_id IN (SELECT testuser_id '
                    . 'FROM tce_tests_users WHERE testuser_test_id=' . $testId . ')'
                );
                $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_test_id=' . $testId);
            }
            if ($subsetId > 0) {
                $this->dbExec('DELETE FROM tce_test_subjects WHERE subjset_tsubset_id=' . $subsetId);
                $this->dbExec('DELETE FROM tce_test_subject_set WHERE tsubset_id=' . $subsetId);
            }
            if ($testId > 0) {
                $this->dbExec('DELETE FROM tce_testgroups WHERE tstgrp_test_id=' . $testId);
                $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
            }
            if ($subjectId > 0) {
                $this->dbExec(
                    'DELETE FROM tce_answers WHERE answer_question_id IN (SELECT question_id '
                    . 'FROM tce_questions WHERE question_subject_id=' . $subjectId . ')'
                );
                $this->dbExec('DELETE FROM tce_questions WHERE question_subject_id=' . $subjectId);
                $this->dbExec('DELETE FROM tce_subjects WHERE subject_id=' . $subjectId);
            }
            if ($moduleId > 0) {
                $this->dbExec('DELETE FROM tce_modules WHERE module_id=' . $moduleId);
            }
            if ($groupId > 0) {
                $this->dbExec('DELETE FROM tce_usrgroups WHERE usrgrp_group_id=' . $groupId);
            }
            if ($userIds !== []) {
                $this->dbExec('DELETE FROM tce_users WHERE user_id IN (' . implode(',', $userIds) . ')');
            }
            if ($groupId > 0) {
                $this->dbExec('DELETE FROM tce_user_groups WHERE group_id=' . $groupId);
            }
            if ($adminId > 0 && $adminOriginalHash !== null) {
                $this->dbExec(
                    "UPDATE tce_users SET user_password='"
                    . \F_escape_sql($this->db, $adminOriginalHash)
                    . "' WHERE user_id=" . $adminId
                );
            }
        }
    }
}
