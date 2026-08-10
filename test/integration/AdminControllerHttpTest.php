<?php

//============================================================+
// File name   : AdminControllerHttpTest.php
// Begin       : 2026-06-22
//
// Description : Authenticated HTTP-level integration tests for the admin
//               controllers converted off the register-globals emulation
//               (plan Stage 8.2). Logs in as an administrator against the
//               app-under-test container and exercises the controllers.
//
// License:
//    Copyright (C) 2004-2026 Nicola Asuni - Tecnick.com LTD
//    See LICENSE file for more information.
//============================================================+

namespace Test\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @file
 * Authenticated HTTP-level integration tests. Establishes an admin session against the
 * app-under-test container, then verifies the Stage 8.2-converted admin controllers load and
 * process input correctly through the real auth/session/CSRF machinery.
 * @package com.tecnick.tcexam.test
 */
final class AdminControllerHttpTest extends AppHttpTestCase
{
    /** Known password seeded for the 'admin' user so the test can authenticate. */
    private const ADMIN_PW = 'itest-admin-pw-7Q2cl8k8ec';

    /** @var array<string,string> Cached authenticated cookies (log in once for the whole class). */
    private static array $authCookies = [];

    /** Guard so the admin password is seeded only once. */
    private static bool $seeded = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAdminPassword();
    }

    /** Open a direct database connection via the DAL (for seeding/assertions). */
    private function dbConnect(): mixed
    {
        $type = (string) getenv('TCEXAM_DB_TYPE');
        $dal = $type === 'POSTGRESQL'
            ? __DIR__ . '/../../shared/code/tce_db_dal_postgresql.php'
            : __DIR__ . '/../../shared/code/tce_db_dal_mysqli.php';
        require_once $dal;

        return \F_db_connect(
            (string) getenv('TCEXAM_DB_HOST'),
            (string) getenv('TCEXAM_DB_PORT'),
            (string) getenv('TCEXAM_DB_USER'),
            (string) getenv('TCEXAM_DB_PASSWORD'),
            (string) getenv('TCEXAM_DB_NAME')
        );
    }

    /** Set the 'admin' user's password to a known value via the DAL (portable bcrypt hash). */
    private function seedAdminPassword(): void
    {
        if (self::$seeded) {
            return;
        }

        /** @var \mysqli|\PgSql\Connection|false $db */
        $db = $this->dbConnect();
        $this->assertNotFalse($db, 'seed: database connection should open');

        $hash = password_hash(self::ADMIN_PW, PASSWORD_DEFAULT);
        $ok = \F_db_query("UPDATE tce_users SET user_password='" . $hash . "' WHERE user_name='admin'", $db);
        $this->assertNotFalse($ok, 'seed: admin password update should succeed');
        \F_db_close($db);

        self::$seeded = true;
    }

    /** Run a write/DDL statement via the DAL. */
    private function dbExec(string $sql): void
    {
        /** @var \mysqli|\PgSql\Connection|false $db */
        $db = $this->dbConnect();
        \F_db_query($sql, $db);
        \F_db_close($db);
    }

    /** Return the first column of the first row of a query, or null if no row. */
    private function dbScalar(string $sql): ?string
    {
        /** @var \mysqli|\PgSql\Connection|false $db */
        $db = $this->dbConnect();
        /** @var \mysqli_result|\PgSql\Result|true|false $res */
        $res = \F_db_query($sql, $db);
        $val = null;
        if ($res !== false) {
            $val = self::scalarFromRow(\F_db_fetch_assoc($res));
        }
        \F_db_close($db);

        return $val;
    }

    private static function scalarFromRow(mixed $row): ?string
    {
        return is_array($row) ? (string) reset($row) : null;
    }

    /** Return the id of the group with the given name, or 0 if absent. */
    private function groupIdByName(string $name): int
    {
        return (int) ($this->dbScalar("SELECT group_id FROM tce_user_groups WHERE group_name='" . $name . "'") ?? '0');
    }

    /** Ensure a group with the given name exists; return its id. */
    private function ensureGroup(string $name): int
    {
        $id = $this->groupIdByName($name);
        if ($id === 0) {
            $this->dbExec("INSERT INTO tce_user_groups (group_name) VALUES ('" . $name . "')");
            $id = $this->groupIdByName($name);
        }

        return $id;
    }

    /** Return the id of the user with the given name, or 0 if absent. */
    private function userIdByName(string $name): int
    {
        return (int) ($this->dbScalar("SELECT user_id FROM tce_users WHERE user_name='" . $name . "'") ?? '0');
    }

    /** True when the given user is linked to the given group. */
    private function userInGroup(int $userId, int $groupId): bool
    {
        $n = (int) ($this->dbScalar(
            'SELECT COUNT(*) FROM tce_usrgroups WHERE usrgrp_user_id=' . $userId . ' AND usrgrp_group_id=' . $groupId
        ) ?? '0');

        return $n > 0;
    }

    /** Remove a test user (and its group links) by name. */
    private function deleteUserByName(string $name): void
    {
        $id = $this->userIdByName($name);
        if ($id > 0) {
            $this->dbExec('DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $id);
            $this->dbExec('DELETE FROM tce_users WHERE user_id=' . $id);
        }
    }

    /** Remove a test group (and its links) by id. */
    private function deleteGroupById(int $id): void
    {
        if ($id > 0) {
            $this->dbExec('DELETE FROM tce_usrgroups WHERE usrgrp_group_id=' . $id);
            $this->dbExec('DELETE FROM tce_user_groups WHERE group_id=' . $id);
        }
    }

    /**
     * Log in as admin once and cache the authenticated session cookies.
     *
     * @return array<string,string>
     */
    private function login(): array
    {
        if (self::$authCookies !== []) {
            return self::$authCookies;
        }

        // GET an admin page to obtain a session cookie, then POST the login credentials to it.
        [, , $cookies] = $this->http('GET', '/admin/code/index.php');
        [, , $cookies] = $this->http('POST', '/admin/code/index.php', $cookies, [
            'logaction' => 'login',
            'xuser_name' => 'admin',
            'xuser_password' => self::ADMIN_PW,
        ]);

        self::$authCookies = $cookies;
        return self::$authCookies;
    }

    /**
     * Start a fresh authenticated session for a specific integration-test account.
     *
     * @return array<string,string>
     */
    private function loginCredentials(string $username, #[\SensitiveParameter] string $password): array
    {
        [, , $cookies] = $this->http('GET', '/admin/code/index.php');
        [, , $cookies] = $this->http('POST', '/admin/code/index.php', $cookies, [
            'logaction' => 'login',
            'xuser_name' => $username,
            'xuser_password' => $password,
        ]);
        return $cookies;
    }

    public function testAdminLoginSucceeds(): void
    {
        $cookies = $this->login();
        [$status, $body] = $this->http('GET', '/admin/code/index.php', $cookies);

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('form_login', $body, 'an authenticated session should not see the login form');
    }

    public function testCumulativeRoleLevelsProtectAdministrativeAreas(): void
    {
        $credential = self::ADMIN_PW . '-role';
        $hash = password_hash($credential, PASSWORD_DEFAULT);
        $accounts = [];
        try {
            foreach ([5, 6, 7, 8, 9] as $level) {
                $name = 'itest_role_' . $level;
                $this->dbExec(
                    "INSERT INTO tce_users (user_regdate,user_ip,user_name,user_password,user_level) VALUES "
                    . "('2026-01-01 00:00:00','127.0.0.1','{$name}','{$hash}',{$level})"
                );
                $accounts[$level] = [
                    'id' => (int) $this->dbScalar("SELECT user_id FROM tce_users WHERE user_name='{$name}'"),
                    'cookies' => $this->loginCredentials($name, $credential),
                ];
            }

            $matrix = [
                5 => ['/admin/code/tce_monitor.php', '/admin/code/tce_self_profile.php'],
                6 => ['/admin/code/tce_show_result_allusers.php'],
                7 => ['/admin/code/tce_edit_question.php'],
                8 => ['/admin/code/tce_edit_test.php'],
                9 => ['/admin/code/tce_import_omr_bulk.php'],
            ];
            foreach ($matrix as $minimumLevel => $paths) {
                foreach ($paths as $path) {
                    foreach ($accounts as $level => $account) {
                        [, $body] = $this->http('GET', $path, $account['cookies']);
                        if ($level >= $minimumLevel) {
                            $this->assertStringNotContainsString(
                                'form_login',
                                $body,
                                "level {$level} should be allowed to open {$path}"
                            );
                        } else {
                            $this->assertStringContainsString(
                                'form_login',
                                $body,
                                "level {$level} should be denied access to {$path}"
                            );
                        }
                    }
                }
            }
            foreach ($accounts as $level => $account) {
                [, $body] = $this->http('GET', '/admin/code/tce_onboarding_settings.php', $account['cookies']);
                $this->assertStringContainsString(
                    'form_login',
                    $body,
                    "level {$level} must not change instance-wide settings"
                );
            }

            [, $body] = $this->http(
                'GET',
                '/public/code/tce_user_change_email.php',
                $accounts[5]['cookies']
            );
            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token);
            [$status] = $this->http(
                'POST',
                '/public/code/tce_user_change_email.php',
                $accounts[5]['cookies'],
                [
                    'update' => '1',
                    'currentpassword' => $credential,
                    'user_email' => 'role5@example.test',
                    'user_email_repeat' => 'role5@example.test',
                    'csrf_token' => $token,
                ]
            );
            $this->assertSame(200, $status);
            $this->assertSame(
                '5',
                $this->dbScalar('SELECT user_level FROM tce_users WHERE user_id=' . $accounts[5]['id']),
                'changing a privileged profile email must preserve the assigned role'
            );
        } finally {
            foreach ($accounts as $account) {
                $this->dbExec('DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $account['id']);
                $this->dbExec('DELETE FROM tce_users WHERE user_id=' . $account['id']);
            }
        }
    }

    public function testDefaultGroupCannotBeDeletedAndAdminMembershipIsRepairedAtLogin(): void
    {
        $cookies = $this->login();
        $defaultId = $this->groupIdByName('default');
        $adminId = (int) $this->dbScalar("SELECT user_id FROM tce_users WHERE user_name='admin'");
        $this->assertGreaterThan(0, $defaultId);
        $this->assertGreaterThan(0, $adminId);

        [$status, $body] = $this->http(
            'GET',
            '/admin/code/tce_edit_group.php?group_id=' . $defaultId,
            $cookies
        );
        $this->assertSame(200, $status);
        $token = self::extractCsrfToken($body);
        $this->assertNotNull($token);
        [, $body] = $this->http('POST', '/admin/code/tce_edit_group.php', $cookies, [
            'delete' => '1',
            'group_id' => (string) $defaultId,
            'csrf_token' => $token,
        ]);
        $this->assertStringNotContainsString('name="forcedelete"', $body);
        $this->assertSame($defaultId, $this->groupIdByName('default'));

        $this->dbExec(
            'DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $adminId
            . ' AND usrgrp_group_id=' . $defaultId
        );
        $this->loginCredentials('admin', self::ADMIN_PW);
        $membership = $this->dbScalar(
            'SELECT COUNT(*) FROM tce_usrgroups WHERE usrgrp_user_id=' . $adminId
            . ' AND usrgrp_group_id=' . $defaultId
        );
        $this->assertSame('1', $membership);
    }

    /**
     * @throws \Random\RandomException
     */
    public function testTeacherQuestionBanksAreIsolatedAndSharedOnlyThroughGroups(): void
    {
        $credential = self::ADMIN_PW . '-bank';
        $hash = password_hash($credential, PASSWORD_DEFAULT);
        $suffix = bin2hex(random_bytes(4));
        $sharedGroup = $this->ensureGroup('itest_shared_' . $suffix);
        $otherGroup = $this->ensureGroup('itest_other_' . $suffix);
        $userIds = [];
        $moduleNames = [
            'shared' => 'itest_shared_module_' . $suffix,
            'private' => 'itest_private_module_' . $suffix,
        ];
        try {
            foreach (['owner', 'colleague', 'outsider'] as $name) {
                $username = 'itest_' . $name . '_' . $suffix;
                $this->dbExec(
                    "INSERT INTO tce_users (user_regdate,user_ip,user_name,user_password,user_level) VALUES "
                    . "('2026-01-01 00:00:00','127.0.0.1','{$username}','{$hash}',7)"
                );
                $userIds[$name] = (int) $this->dbScalar(
                    "SELECT user_id FROM tce_users WHERE user_name='{$username}'"
                );
            }
            foreach (['owner', 'colleague'] as $name) {
                $this->dbExec(
                    'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                    . $userIds[$name] . ',' . $sharedGroup . ')'
                );
            }
            $this->dbExec(
                'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                . $userIds['outsider'] . ',' . $otherGroup . ')'
            );
            $this->dbExec(
                "INSERT INTO tce_modules (module_name,module_enabled,module_user_id) VALUES "
                . "('{$moduleNames['shared']}',TRUE,{$userIds['owner']}),"
                . "('{$moduleNames['private']}',TRUE,{$userIds['outsider']})"
            );

            $cookies = $this->loginCredentials('itest_colleague_' . $suffix, $credential);
            [$status, $body] = $this->http('GET', '/admin/code/tce_edit_module.php', $cookies);
            $this->assertSame(200, $status);
            $this->assertStringContainsString($moduleNames['shared'], $body);
            $this->assertStringNotContainsString($moduleNames['private'], $body);
        } finally {
            foreach ($moduleNames as $moduleName) {
                $this->dbExec("DELETE FROM tce_modules WHERE module_name='{$moduleName}'");
            }
            foreach ($userIds as $userId) {
                $this->dbExec('DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $userId);
                $this->dbExec('DELETE FROM tce_users WHERE user_id=' . $userId);
            }
            $this->deleteGroupById($sharedGroup);
            $this->deleteGroupById($otherGroup);
        }
    }

    public function testPublicPwaManifestAndWorkerExcludePrivateResponses(): void
    {
        [$manifestStatus, $manifest] = $this->http('GET', '/public/manifest.webmanifest');
        $this->assertSame(200, $manifestStatus);
        /** @var array{display:string,start_url:string} $decoded */
        $decoded = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('standalone', $decoded['display']);
        $this->assertSame('./code/', $decoded['start_url']);

        [$workerStatus, $worker] = $this->http('GET', '/public/sw.js');
        $this->assertSame(200, $workerStatus);
        $this->assertStringContainsString("PUBLIC_SCOPE + 'code/'", $worker);
        $this->assertStringContainsString("APP_ROOT + 'admin/'", $worker);
        $this->assertStringContainsString("APP_ROOT + 'cache/'", $worker);
        $this->assertStringContainsString("url.search !== ''", $worker);
        $this->assertStringContainsString("fetch(request, {cache: 'no-store'})", $worker);
    }

    public function testEssayRatingOffersFractionalQuickScores(): void
    {
        $cookies = $this->login();
        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_rating.php', $cookies);

        $this->assertSame(200, $status);
        $this->assertStringContainsString('data-fraction="3/4"', $body);
        $this->assertStringContainsString('data-fraction="1/2"', $body);
        $this->assertStringContainsString('data-fraction="1/4"', $body);
    }

    public function testRegradeUpdatesObjectiveScoreAndPreservesEssayScore(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='itest_regrade_test'");
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time,"
            . "test_begin_time,test_end_time,test_score_right,test_score_wrong,test_score_unanswered) "
            . "VALUES ('itest_regrade_test','d'," . $adminId
            . ",60,'2020-01-01 00:00:00','2035-01-01 00:00:00',4,-1,0)"
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='itest_regrade_test'"
        ) ?? '0');
        $this->dbExec("DELETE FROM tce_modules WHERE module_name='itest_regrade_module'");
        $this->dbExec(
            "INSERT INTO tce_modules (module_name,module_enabled,module_user_id) "
            . "VALUES ('itest_regrade_module','1'," . $adminId . ')'
        );
        $moduleId = (int) ($this->dbScalar(
            "SELECT module_id FROM tce_modules WHERE module_name='itest_regrade_module'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_subjects (subject_module_id,subject_name,subject_description,"
            . "subject_enabled,subject_user_id) VALUES ("
            . $moduleId . ",'itest_regrade_subject','d','1'," . $adminId . ')'
        );
        $subjectId = (int) ($this->dbScalar(
            "SELECT subject_id FROM tce_subjects WHERE subject_name='itest_regrade_subject'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
            . "question_enabled,question_position,question_difficulty) VALUES ("
            . $subjectId . ",'Objective',1,'1',1,1)"
        );
        $objectiveId = (int) ($this->dbScalar(
            'SELECT question_id FROM tce_questions WHERE question_subject_id=' . $subjectId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
            . "question_enabled,question_position,question_difficulty) VALUES ("
            . $subjectId . ",'Essay',3,'1',2,1)"
        );
        $essayId = (int) ($this->dbScalar(
            'SELECT MAX(question_id) FROM tce_questions WHERE question_subject_id=' . $subjectId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
            . "question_enabled,question_position,question_difficulty) VALUES ("
            . $subjectId . ",'Short<!--TMF_SIMILARITY:85-->',3,'1',3,1)"
        );
        $shortId = (int) ($this->dbScalar(
            'SELECT MAX(question_id) FROM tce_questions WHERE question_subject_id=' . $subjectId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
            . "question_enabled,question_position,question_difficulty) VALUES ("
            . $subjectId . ",'Matching<!--TMF_MATCH_POSITIONS:2-->',5,'1',4,1)"
        );
        $matchingId = (int) ($this->dbScalar(
            'SELECT MAX(question_id) FROM tce_questions WHERE question_subject_id=' . $subjectId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_answers (answer_question_id,answer_description,answer_isright,"
            . "answer_enabled,answer_position) VALUES (" . $objectiveId . ",'Right','1','1',1)"
        );
        $answerId = (int) ($this->dbScalar(
            'SELECT answer_id FROM tce_answers WHERE answer_question_id=' . $objectiveId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_answers (answer_question_id,answer_description,answer_isright,"
            . "answer_enabled,answer_position,answer_weight) VALUES ("
            . $shortId . ",'Свердловск','1','1',1,100)"
        );
        $shortAnswerId = (int) ($this->dbScalar(
            'SELECT answer_id FROM tce_answers WHERE answer_question_id=' . $shortId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_answers (answer_question_id,answer_description,answer_isright,"
            . "answer_enabled,answer_position) VALUES (" . $matchingId . ",'Pair A','1','1',1)"
        );
        $matchingAnswerOne = (int) ($this->dbScalar(
            'SELECT MIN(answer_id) FROM tce_answers WHERE answer_question_id=' . $matchingId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_answers (answer_question_id,answer_description,answer_isright,"
            . "answer_enabled,answer_position) VALUES (" . $matchingId . ",'Pair B','1','1',2)"
        );
        $matchingAnswerTwo = (int) ($this->dbScalar(
            'SELECT MAX(answer_id) FROM tce_answers WHERE answer_question_id=' . $matchingId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_users (testuser_test_id,testuser_user_id,testuser_status,"
            . 'testuser_creation_time,testuser_last_activity) VALUES ('
            . $testId . ',' . $adminId . ',4,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $attemptId = (int) ($this->dbScalar(
            'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
            . "testlog_creation_time,testlog_order,testlog_answer_text) VALUES ("
            . $attemptId . ',' . $objectiveId . ",99,CURRENT_TIMESTAMP,1,NULL)"
        );
        $objectiveLogId = (int) ($this->dbScalar(
            'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
            . ' AND testlog_question_id=' . $objectiveId
        ) ?? '0');
        $this->dbExec(
            'INSERT INTO tce_tests_logs_answers (logansw_testlog_id,logansw_answer_id,'
            . 'logansw_selected,logansw_order) VALUES ('
            . $objectiveLogId . ',' . $answerId . ',1,1)'
        );
        $this->dbExec(
            "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
            . "testlog_creation_time,testlog_order,testlog_answer_text) VALUES ("
            . $attemptId . ',' . $essayId . ",2.5,CURRENT_TIMESTAMP,2,'Manual essay')"
        );
        $essayLogId = (int) ($this->dbScalar(
            'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
            . ' AND testlog_question_id=' . $essayId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
            . "testlog_creation_time,testlog_change_time,testlog_order,testlog_answer_text) VALUES ("
            . $attemptId . ',' . $shortId
            . ",99,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,3,'Свердловскк')"
        );
        $shortLogId = (int) ($this->dbScalar(
            'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
            . ' AND testlog_question_id=' . $shortId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
            . "testlog_creation_time,testlog_change_time,testlog_order) VALUES ("
            . $attemptId . ',' . $matchingId . ',99,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,4)'
        );
        $matchingLogId = (int) ($this->dbScalar(
            'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
            . ' AND testlog_question_id=' . $matchingId
        ) ?? '0');
        $this->dbExec(
            'INSERT INTO tce_tests_logs_answers (logansw_testlog_id,logansw_answer_id,'
            . 'logansw_selected,logansw_order,logansw_position) VALUES '
            . '(' . $matchingLogId . ',' . $matchingAnswerOne . ',1,1,1),'
            . '(' . $matchingLogId . ',' . $matchingAnswerTwo . ',1,2,2)'
        );

        try {
            [, $form] = $this->http(
                'GET',
                '/admin/code/tce_show_result_allusers.php?test_id=' . $testId,
                $cookies
            );
            $token = self::extractCsrfToken($form);
            $this->assertNotNull($token);
            [$status, $body] = $this->http(
                'POST',
                '/admin/code/tce_show_result_allusers.php',
                $cookies,
                [
                    'test_id' => (string) $testId,
                    'regrade' => '1',
                    'csrf_token' => $token,
                ]
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('Пересчитано автоматических ответов: 3', $body);
            $this->assertSame('4.000', $this->dbScalar(
                'SELECT testlog_score FROM tce_tests_logs WHERE testlog_id=' . $objectiveLogId
            ));
            $this->assertSame('2.500', $this->dbScalar(
                'SELECT testlog_score FROM tce_tests_logs WHERE testlog_id=' . $essayLogId
            ));
            $this->assertSame('4.000', $this->dbScalar(
                'SELECT testlog_score FROM tce_tests_logs WHERE testlog_id=' . $shortLogId
            ));
            $this->assertSame('4.000', $this->dbScalar(
                'SELECT testlog_score FROM tce_tests_logs WHERE testlog_id=' . $matchingLogId
            ));
            $startedAt = (string) $this->dbScalar(
                'SELECT testuser_creation_time FROM tce_tests_users WHERE testuser_id=' . $attemptId
            );
            foreach (['XML' => 'xml', 'JSON' => 'json'] as $format => $extension) {
                [$exportStatus, $exportBody] = $this->http(
                    'GET',
                    '/admin/code/tce_xml_results.php?test_id=' . $testId . '&format=' . $format,
                    $cookies,
                );
                $this->assertSame(200, $exportStatus, $extension);
                $this->assertStringContainsString('14.500', $exportBody);
                $this->assertStringContainsString((string) $attemptId, $exportBody);
                $this->assertStringContainsString($startedAt, $exportBody);
            }
            [$tsvStatus, $tsvBody] = $this->http(
                'GET',
                '/admin/code/tce_tsv_result_allusers.php?test_id=' . $testId,
                $cookies,
            );
            $this->assertSame(200, $tsvStatus);
            $this->assertStringContainsString('14.500', $tsvBody);

            require_once __DIR__ . '/../../shared/code/tce_functions_xlsx.php';
            [$xlsxStatus, $xlsxBody] = $this->http(
                'GET',
                '/admin/code/tce_xlsx_result_allusers.php?test_id=' . $testId,
                $cookies,
            );
            $this->assertSame(200, $xlsxStatus);
            $xlsxFile = tempnam(sys_get_temp_dir(), 'openvsosh-result-contract-');
            $this->assertNotFalse($xlsxFile);
            file_put_contents($xlsxFile, $xlsxBody);
            try {
                $xlsxRows = \F_tmf_xlsx_read($xlsxFile);
            } finally {
                if (is_file($xlsxFile)) {
                    unlink($xlsxFile);
                }
            }
            $this->assertSame((string) $attemptId, $xlsxRows[1][0]);
            $this->assertSame($startedAt, $xlsxRows[1][5]);
            $this->assertSame('14.500', $xlsxRows[1][8]);

            [$pdfStatus, $pdfBody] = $this->http(
                'GET',
                '/admin/code/tce_pdf_results.php?mode=1&test_id=' . $testId,
                $cookies,
            );
            $this->assertSame(200, $pdfStatus);
            $this->assertStringStartsWith('%PDF-', $pdfBody);
        } finally {
            $this->dbExec(
                'DELETE FROM tce_tests_logs_answers WHERE logansw_testlog_id IN ('
                . $objectiveLogId . ',' . $matchingLogId . ')'
            );
            $this->dbExec('DELETE FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId);
            $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_id=' . $attemptId);
            $this->dbExec(
                'DELETE FROM tce_answers WHERE answer_id IN (' . $answerId . ',' . $shortAnswerId . ','
                . $matchingAnswerOne . ',' . $matchingAnswerTwo . ')'
            );
            $this->dbExec(
                'DELETE FROM tce_questions WHERE question_id IN ('
                . $objectiveId . ',' . $essayId . ',' . $shortId . ',' . $matchingId . ')'
            );
            $this->dbExec('DELETE FROM tce_subjects WHERE subject_id=' . $subjectId);
            $this->dbExec('DELETE FROM tce_modules WHERE module_id=' . $moduleId);
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
        }
    }

    /** @throws \Random\RandomException */
    public function testAccessSettingsControlPublicLinksAndStoreHelpInDatabase(): void
    {
        $cookies = $this->login();
        $this->dbExec('DELETE FROM tce_openvsosh_settings');

        try {
            [$status, $body] = $this->http('GET', '/admin/code/tce_onboarding_settings.php', $cookies);
            $this->assertSame(200, $status);
            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token, 'the instance settings form should expose a CSRF token');

            [$status, $body] = $this->http('POST', '/admin/code/tce_onboarding_settings.php', $cookies, [
                'save_access' => '1',
                'disable_password_reset' => '1',
                'access_help' => 'Для доступа напишите координатору <access@example.test>.',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $this->assertStringContainsString('Настройки доступа сохранены', $body);
            $this->assertSame(
                '1',
                $this->dbScalar(
                    "SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='registration_enabled'"
                )
            );
            $this->assertSame(
                '0',
                $this->dbScalar(
                    "SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='password_reset_enabled'"
                )
            );
            $this->assertSame(
                'Для доступа напишите координатору <access@example.test>.',
                $this->dbScalar("SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='access_help'")
            );

            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token);
            [$status, $body] = $this->http('POST', '/admin/code/tce_onboarding_settings.php', $cookies, [
                'save_site' => '1',
                'site_name' => 'Тестовая олимпиадная площадка',
                'site_description' => 'Описание <не HTML>',
                'site_contact' => 'Координатор: +7 000 000-00-00',
                'welcome' => 'Добро пожаловать!',
                'login_instruction' => 'Используйте логин из карточки.',
                'default_language' => 'ru',
                'default_timezone' => 'UTC',
                'timer_warning_seconds' => '600',
                'timer_critical_seconds' => '180',
                'timer_warning_color' => '#9a4f00',
                'timer_critical_color' => '#a40000',
                'admin_palette' => 'forest',
                'admin_density' => 'compact',
                'ui_font' => 'humanist',
                'login_background_position' => 'top',
                'login_background_size' => 'cover',
                'login_background_overlay' => '42',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $this->assertStringContainsString('Настройки площадки сохранены', $body);
            $this->assertSame(
                'Тестовая олимпиадная площадка',
                $this->dbScalar("SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='site_name'")
            );
            $this->assertSame(
                'UTC',
                $this->dbScalar(
                    "SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='default_timezone'"
                )
            );
            $this->assertSame(
                'forest',
                $this->dbScalar("SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='admin_palette'")
            );
            $this->assertSame(
                '42',
                $this->dbScalar(
                    "SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='login_background_overlay'"
                )
            );
            [$bootstrapStatus] = $this->http('GET', '/shared/config/openvsosh-bootstrap.json');
            $this->assertContains($bootstrapStatus, [403, 404]);

            $image = file_get_contents(__DIR__ . '/../../images/vsosh-logo.png');
            $this->assertNotFalse($image);
            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token);
            [$status, $body] = $this->httpUpload(
                '/admin/code/tce_onboarding_settings.php',
                $cookies,
                [
                    'save_site' => '1',
                    'site_name' => 'Тестовая олимпиадная площадка',
                    'site_description' => 'Описание <не HTML>',
                    'site_contact' => 'Координатор: +7 000 000-00-00',
                    'welcome' => 'Добро пожаловать!',
                    'login_instruction' => 'Используйте логин из карточки.',
                    'default_language' => 'ru',
                    'default_timezone' => 'UTC',
                    'timer_warning_seconds' => '600',
                    'timer_critical_seconds' => '180',
                    'timer_warning_color' => '#9a4f00',
                    'timer_critical_color' => '#a40000',
                    'csrf_token' => $token,
                ],
                'site_logo',
                'logo.png',
                $image
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('Настройки площадки сохранены', $body);
            $storedLogo = $this->dbScalar(
                "SELECT setting_value FROM tce_openvsosh_settings WHERE setting_key='site_logo_stored'"
            ) ?? '';
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $storedLogo);
            [$directStatus] = $this->http('GET', '/cache/site-assets/' . $storedLogo);
            $this->assertContains($directStatus, [403, 404]);
            [$logoStatus, $logo] = $this->http('GET', '/public/code/tce_site_asset.php?type=logo');
            $this->assertSame(200, $logoStatus);
            $this->assertSame($image, $logo);
            [$invalidAssetStatus] = $this->http('GET', '/public/code/tce_site_asset.php?type[]=logo');
            $this->assertSame(404, $invalidAssetStatus);

            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token);
            [$status, $body] = $this->httpUpload(
                '/admin/code/tce_onboarding_settings.php',
                $cookies,
                [
                    'save_site' => '1',
                    'site_name' => 'Тестовая олимпиадная площадка',
                    'site_description' => 'Описание <не HTML>',
                    'site_contact' => 'Координатор: +7 000 000-00-00',
                    'welcome' => 'Добро пожаловать!',
                    'login_instruction' => 'Используйте логин из карточки.',
                    'default_language' => 'ru',
                    'default_timezone' => 'UTC',
                    'timer_warning_seconds' => '600',
                    'timer_critical_seconds' => '180',
                    'timer_warning_color' => '#9a4f00',
                    'timer_critical_color' => '#a40000',
                    'csrf_token' => $token,
                ],
                'site_background',
                'background.png',
                $image
            );
            $this->assertSame(200, $status);

            [$status, $body] = $this->http('GET', '/public/code/index.php');
            $this->assertSame(200, $status);
            $this->assertStringContainsString('--login-background-image', $body);
            $this->assertStringContainsString('tce_site_asset.php?type=logo', $body);
            $this->assertStringContainsString('Тестовая олимпиадная площадка', $body);
            $this->assertStringContainsString('Описание &lt;не HTML&gt;', $body);
            $this->assertStringContainsString('Используйте логин из карточки.', $body);
            $this->assertStringContainsString('Координатор: +7 000 000-00-00', $body);
            $this->assertStringContainsString('FJ_configure_timer(600,180', $body);
            $this->assertStringContainsString('--timer-critical-bg:#a40000', $body);
            $this->assertStringContainsString('href="tce_user_registration.php"', $body);
            $this->assertStringNotContainsString('href="tce_password_reset.php"', $body);
            $this->assertStringContainsString(
                'Для доступа напишите координатору &lt;access@example.test&gt;.',
                $body,
                'administrator-entered help must be rendered as safe plain text'
            );
        } finally {
            $this->dbExec('DELETE FROM tce_openvsosh_settings');
        }
    }

    public function testProtectedAdminPageUsesTheRegularLoginAndReturnsAfterAuthentication(): void
    {
        // Stop at the first redirect so the lightweight test client can retain the
        // session cookie set by the admin endpoint before it opens the public login.
        [$status, , $cookies] = $this->http('GET', '/admin/code/tce_edit_user.php', [], [], false);

        $this->assertSame(302, $status);

        [$status, $body, $cookies] = $this->http('GET', '/public/code/index.php', $cookies);

        $this->assertSame(200, $status);
        $this->assertStringContainsString('form_login', $body);
        $this->assertStringNotContainsString('Панель администратора', $body);

        [$status, $body] = $this->http('POST', '/public/code/index.php', $cookies, [
            'logaction' => 'login',
            'xuser_name' => 'admin',
            'xuser_password' => self::ADMIN_PW,
        ]);

        $this->assertSame(200, $status);
        $this->assertStringContainsString('form_usereditor', $body);
        $this->assertStringNotContainsString('form_login', $body);
    }

    /**
     * The admin controllers converted off the register-globals emulation in Stage 8.2.
     *
     * @return array<string,array{0:string}>
     */
    public static function convertedAdminControllers(): array
    {
        $files = [
            'tce_edit_answer.php', 'tce_edit_group.php', 'tce_edit_module.php', 'tce_edit_question.php',
            'tce_edit_subject.php', 'tce_edit_sslcerts.php', 'tce_filemanager.php', 'tce_select_mediafile.php',
            'tce_edit_backup.php', 'tce_edit_user.php', 'tce_edit_test.php', 'tce_edit_rating.php',
            'tce_import_users.php', 'tce_select_users.php', 'tce_select_tests.php', 'tce_show_all_questions.php',
            'tce_show_result_allusers.php', 'tce_show_result_user.php', 'tce_monitor.php',
            'tce_pregenerate.php', 'tce_offline.php', 'tce_users_xlsx.php', 'tce_test_access_rules.php',
            'tce_attachment.php', 'tce_attempt_archive.php', 'tmf_word_import.php',
        ];
        $cases = [];
        foreach ($files as $f) {
            $cases[$f] = ['/admin/code/' . $f];
        }

        return $cases;
    }

    public function testUsersXlsxTemplateIsARealWorkbook(): void
    {
        $cookies = $this->login();
        [$status, $body] = $this->http(
            'GET',
            '/admin/code/tce_users_xlsx.php?download=template',
            $cookies,
        );

        $this->assertSame(200, $status);
        $this->assertStringStartsWith("PK\x03\x04", $body);
        $this->assertGreaterThan(1000, strlen($body));
    }

    public function testWordImportTemplateIsARealDocx(): void
    {
        $cookies = $this->login();
        [$status, $body] = $this->http(
            'GET',
            '/admin/code/tmf_word_import.php?download=template',
            $cookies,
        );

        $this->assertSame(200, $status);
        $this->assertStringStartsWith("PK\x03\x04", $body);
        $this->assertGreaterThan(1000, strlen($body));
    }

    public function testAdvancedTestAccessRulesPersistThroughController(): void
    {
        $testName = 'itest_access_rules';
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='" . $testName . "'");
        $adminId = (int) ($this->dbScalar("SELECT user_id FROM tce_users WHERE user_name='admin'") ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id) VALUES ('"
            . $testName . "','integration test'," . $adminId . ')',
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='" . $testName . "'",
        ) ?? '0');
        $this->assertGreaterThan(0, $testId);
        $cookies = $this->login();
        [$status, $body] = $this->http(
            'GET',
            '/admin/code/tce_test_access_rules.php?test_id=' . $testId,
            $cookies,
        );
        $this->assertSame(200, $status);
        $token = self::extractCsrfToken($body);
        $this->assertNotNull($token);

        try {
            [$status, $body] = $this->http(
                'POST',
                '/admin/code/tce_test_access_rules.php',
                $cookies,
                [
                    'save_rules' => '1',
                    'test_id' => (string) $testId,
                    'required_finished' => '0',
                    'required_passed' => '0',
                    'minimum_duration' => '7',
                    'require_all_answers' => '1',
                    'block_below_threshold' => '1',
                    'live_score' => '1',
                    'auto_fullscreen' => '1',
                    'hide_exam_info' => '1',
                    'results_to_users' => '1',
                    'results_anonymized' => '1',
                    'results_publish_at' => '2026-07-27T10:00',
                    'results_unpublish_at' => '2026-07-28T10:00',
                    'disable_previous' => '1',
                    'completion_message' => 'Готово безопасно',
                    'csrf_token' => $token,
                ],
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('Настройки сохранены', $body);
            $this->assertSame(
                '7',
                $this->dbScalar('SELECT test_minimum_duration_time FROM tce_tests WHERE test_id=' . $testId),
            );
            $this->assertSame(
                'Готово безопасно',
                $this->dbScalar('SELECT test_completion_message FROM tce_tests WHERE test_id=' . $testId),
            );
            foreach (['test_live_score', 'test_auto_fullscreen', 'test_hide_exam_info'] as $field) {
                $this->assertTrue(\f_get_boolean(
                    $this->dbScalar('SELECT ' . $field . ' FROM tce_tests WHERE test_id=' . $testId),
                ));
            }
            foreach (['test_results_to_users', 'test_results_anonymized'] as $field) {
                $this->assertTrue(\f_get_boolean(
                    $this->dbScalar('SELECT ' . $field . ' FROM tce_tests WHERE test_id=' . $testId),
                ));
            }
            $this->assertSame(
                '2026-07-27 10:00:00',
                $this->dbScalar('SELECT test_results_publish_at FROM tce_tests WHERE test_id=' . $testId),
            );
            $this->assertSame(
                '2026-07-28 10:00:00',
                $this->dbScalar('SELECT test_results_unpublish_at FROM tce_tests WHERE test_id=' . $testId),
            );
        } finally {
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
        }
    }

    #[DataProvider('convertedAdminControllers')]
    public function testConvertedAdminControllerLoadsAuthenticated(string $path): void
    {
        $cookies = $this->login();
        [$status, $body] = $this->http('GET', $path, $cookies);

        // A converted controller's explicit $_POST reads run at load time; a fatal there (e.g. a
        // bad conversion) would surface as a 500 (display_errors is off) or a PHP error in the body.
        $this->assertLessThan(500, $status, $path . ' should load without a server error');
        $this->assertStringNotContainsStringIgnoringCase('Parse error', $body, $path . ' should have no PHP parse error');
        $this->assertStringNotContainsStringIgnoringCase('Fatal error', $body, $path . ' should have no PHP fatal error');
        $this->assertStringNotContainsStringIgnoringCase('Uncaught', $body, $path . ' should have no uncaught exception');
        // Authenticated: the page must not have bounced us back to the login form.
        $this->assertStringNotContainsString('form_login', $body, $path . ' should be reachable while authenticated');
    }

    /**
     * End-to-end exercise of a converted POST path: add a group, then delete it through the
     * confirm/forcedelete flow (which runs the Stage 8.2-converted `$_POST['forcedelete']` read),
     * going through the real menu_mode dispatch + CSRF validation.
     */
    public function testGroupAddAndForceDeleteFlow(): void
    {
        $cookies = $this->login();
        $name = 'itest_grp_http';

        // A CSRF token rendered in any form is valid for the whole session (it verifies against the
        // session's plaintext token), so one extracted token serves all the POSTs below.
        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_group.php', $cookies);
        $this->assertSame(200, $status);
        $token = self::extractCsrfToken($body);
        $this->assertNotNull($token, 'the group editor should expose a CSRF token');

        // 1) Add the group (menu_mode 'add' → INSERT). The button value is irrelevant; presence
        //    of the 'add' key drives the dispatch.
        [$status] = $this->http('POST', '/admin/code/tce_edit_group.php', $cookies, [
            'add' => '1',
            'group_name' => $name,
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status, 'the add submission should be accepted');
        $id = $this->groupIdByName($name);
        $this->assertGreaterThan(0, $id, 'the group should have been created via the add POST');

        // 2) Request the delete confirmation (menu_mode 'delete') to obtain the forcedelete button
        //    value (the localized "delete" word the converted code compares against).
        [$status, $body] = $this->http('POST', '/admin/code/tce_edit_group.php', $cookies, [
            'delete' => '1',
            'group_id' => (string) $id,
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status);
        $m = [];
        $this->assertSame(1, preg_match('/name="forcedelete"[^>]*value="([^"]+)"/', $body, $m), 'the confirm form should render a forcedelete button');
        $forceValue = $m[1] ?? '';

        // 3) Confirm the deletion (menu_mode 'forcedelete' → runs the converted $_POST read).
        [$status] = $this->http('POST', '/admin/code/tce_edit_group.php', $cookies, [
            'forcedelete' => $forceValue,
            'group_id' => (string) $id,
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status, 'the forcedelete submission should be accepted');
        $this->assertSame(0, $this->groupIdByName($name), 'the group should have been deleted via the forcedelete POST');
    }

    /** The group editor must load groups through a bounded search, not all at once. */
    public function testGroupEditorLoadsGroupsOnlyOnSearch(): void
    {
        $cookies = $this->login();
        $name = 'itest_group_selector';
        $id = $this->ensureGroup($name);
        $this->assertGreaterThan(0, $id);

        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_group.php', $cookies);
        $this->assertSame(200, $status);
        $this->assertStringNotContainsString($name . '</option>', $body);

        [$status, $body] = $this->http(
            'GET',
            '/admin/code/tce_edit_group.php?group_searchterms=' . urlencode($name),
            $cookies
        );
        $this->assertSame(200, $status);
        $this->assertStringContainsString($name . '</option>', $body);

        $this->deleteGroupById($id);
    }

    /**
     * Regression test for the Stage 8.2 array bug fix: the `user_groups[]` multi-select was never
     * set by the old register-globals emulation (it skipped arrays via is_string), so group
     * assignment was silently broken. With the explicit `$_POST['user_groups'] ?? []` read, adding
     * a user with a selected group must actually link the user to that group.
     */
    public function testAddUserAssignsGroups(): void
    {
        $cookies = $this->login();
        $userName = 'itest_user_http';
        $groupId = $this->ensureGroup('itest_ug_grp');
        $this->assertGreaterThan(0, $groupId);
        $this->deleteUserByName($userName); // start clean

        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_user.php', $cookies);
        $this->assertSame(200, $status);
        $token = self::extractCsrfToken($body);
        $this->assertNotNull($token, 'the user editor should expose a CSRF token');

        // Add a user with the group selected (user_groups[] is the multi-select array field).
        [$status] = $this->http('POST', '/admin/code/tce_edit_user.php', $cookies, [
            'add' => '1',
            'user_name' => $userName,
            'newpassword' => 'Itest-pw-123456',
            'newpassword_repeat' => 'Itest-pw-123456',
            'user_level' => '1',
            'user_groups' => [(string) $groupId],
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status, 'the add-user submission should be accepted');

        $userId = $this->userIdByName($userName);
        $this->assertGreaterThan(0, $userId, 'the user should have been created');
        $this->assertTrue(
            $this->userInGroup($userId, $groupId),
            'the user_groups[] array must be read and persisted (Stage 8.2 array bug fix)'
        );

        // Cleanup.
        $this->deleteUserByName($userName);
        $this->deleteGroupById($groupId);
    }

    /** The editor must not render every account in its user selector. */
    public function testUserEditorOnlyLoadsTheSelectedUser(): void
    {
        $cookies = $this->login();
        $name = 'itest_user_selector';
        $this->deleteUserByName($name);

        $hash = password_hash('x', PASSWORD_DEFAULT);
        $this->dbExec(
            "INSERT INTO tce_users (user_regdate,user_ip,user_name,user_password,user_level) "
            . "VALUES ('2020-01-01 00:00:00','1.2.3.4','" . $name . "','" . $hash . "',1)"
        );
        $id = $this->userIdByName($name);
        $this->assertGreaterThan(0, $id);

        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_user.php', $cookies);
        $this->assertSame(200, $status);
        $this->assertStringNotContainsString($name . '</option>', $body);

        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_user.php?user_id=' . $id, $cookies);
        $this->assertSame(200, $status);
        $this->assertStringContainsString($name . '</option>', $body);

        $this->deleteUserByName($name);
    }

    public function testUserEditorSearchesByLoginNameAndEmail(): void
    {
        $cookies = $this->login();
        $login = 'itest_multi_field_search';
        $email = 'selector-search@example.test';
        $firstName = 'UniqueSelectorFirst';
        $lastName = 'UniqueSelectorLast';
        $this->deleteUserByName($login);

        $hash = password_hash('x', PASSWORD_DEFAULT);
        $this->dbExec(
            "INSERT INTO tce_users (user_regdate,user_ip,user_name,user_email,user_password,"
            . "user_firstname,user_lastname,user_level) VALUES "
            . "('2020-01-01 00:00:00','1.2.3.4','" . $login . "','" . $email . "','" . $hash
            . "','" . $firstName . "','" . $lastName . "',1)"
        );

        foreach ([$login, $firstName, $lastName, $email] as $term) {
            [$status, $body] = $this->http(
                'GET',
                '/admin/code/tce_edit_user.php?user_searchterms=' . urlencode($term),
                $cookies
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString($login, $body, 'the user should be found by ' . $term);
        }

        $this->deleteUserByName($login);
    }

    /**
     * Regression test: editing a user must preserve the round-tripped system fields user_regdate
     * and user_ip (hidden form fields). They were emulation-provided; without the explicit reads
     * the UPDATE would blank them.
     */
    public function testEditUserUpdatePreservesRoundTrippedFields(): void
    {
        $cookies = $this->login();
        $name = 'itest_user_upd';
        $newName = 'itest_user_upd2';
        $this->deleteUserByName($name);
        $this->deleteUserByName($newName);

        $hash = password_hash('x', PASSWORD_DEFAULT);
        $this->dbExec(
            "INSERT INTO tce_users (user_regdate,user_ip,user_name,user_password,user_level) "
            . "VALUES ('2020-01-01 00:00:00','1.2.3.4','" . $name . "','" . $hash . "',1)"
        );
        $id = $this->userIdByName($name);
        $this->assertGreaterThan(0, $id);

        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_user.php?user_id=' . $id, $cookies);
        $this->assertSame(200, $status);
        $token = self::extractCsrfToken($body);
        $this->assertNotNull($token);

        // Update the name, round-tripping the hidden system fields exactly as the browser form does.
        [$status] = $this->http('POST', '/admin/code/tce_edit_user.php', $cookies, [
            'update' => '1',
            'confirmupdate' => '1',
            'user_id' => (string) $id,
            'user_name' => $newName,
            'user_level' => '1',
            'user_regdate' => '2020-01-01 00:00:00',
            'user_ip' => '1.2.3.4',
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status);

        $this->assertSame($newName, $this->dbScalar('SELECT user_name FROM tce_users WHERE user_id=' . $id), 'user_name should be updated');
        $this->assertStringStartsWith('2020-01-01', (string) $this->dbScalar('SELECT user_regdate FROM tce_users WHERE user_id=' . $id), 'user_regdate must be preserved');
        $this->assertSame('1.2.3.4', $this->dbScalar('SELECT user_ip FROM tce_users WHERE user_id=' . $id), 'user_ip must be preserved');

        $this->deleteUserByName($newName);
        $this->deleteUserByName($name);
    }

    /** Regression test: adding a test via the converted edit_test controller persists it. */
    public function testEditTestAddPersists(): void
    {
        $cookies = $this->login();
        $name = 'itest_test_http';
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='" . $name . "'");

        [$status, $body] = $this->http('GET', '/admin/code/tce_edit_test.php', $cookies);
        $this->assertSame(200, $status);
        $token = self::extractCsrfToken($body);
        $this->assertNotNull($token);

        // Satisfy ff_required = test_name,test_description,test_ip_range,test_duration_time,test_score_right.
        [$status] = $this->http('POST', '/admin/code/tce_edit_test.php', $cookies, [
            'add' => '1',
            'test_name' => $name,
            'test_description' => 'itest description',
            'test_ip_range' => '0.0.0.0',
            'test_duration_time' => '60',
            'test_score_right' => '1',
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status, 'the add-test submission should be accepted');

        $id = (int) ($this->dbScalar("SELECT test_id FROM tce_tests WHERE test_name='" . $name . "'") ?? '0');
        $this->assertGreaterThan(0, $id, 'edit_test add must create the test (converted form fields read from $_REQUEST)');

        $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $id);
    }

    /**
     * Regression test for the dynamic `${$keyname}` → `$_POST[$keyname]` conversion plus the
     * self-referential `$new_group_id` read: the select_users 'addgroup' action reads the selected
     * userid<N> checkbox and the target group id, both formerly emulation-provided.
     */
    public function testSelectUsersAddGroupReadsSelection(): void
    {
        $cookies = $this->login();
        $userName = 'itest_su_user';
        $this->deleteUserByName($userName);
        $groupId = $this->ensureGroup('itest_su_group');

        $hash = password_hash('x', PASSWORD_DEFAULT);
        $this->dbExec(
            "INSERT INTO tce_users (user_regdate,user_ip,user_name,user_password,user_level) "
            . "VALUES ('2020-01-01 00:00:00','0.0.0.0','" . $userName . "','" . $hash . "',1)"
        );
        $userId = $this->userIdByName($userName);
        $this->assertGreaterThan(0, $userId);
        $this->assertFalse($this->userInGroup($userId, $groupId), 'precondition: user is not yet in the group');

        // The CSRF token is bound to the entry script, so fetch it from this controller's own page.
        [, $form] = $this->http('GET', '/admin/code/tce_select_users.php', $cookies);
        $token = self::extractCsrfToken($form) ?? '';

        // 'addgroup': the position-1 checkbox (userid1) selects our user; new_group_id is the target.
        [$status] = $this->http('POST', '/admin/code/tce_select_users.php', $cookies, [
            'addgroup' => '1',
            'new_group_id' => (string) $groupId,
            'userid1' => (string) $userId,
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status);
        $this->assertTrue(
            $this->userInGroup($userId, $groupId),
            'addgroup must read the userid<N> selection ($_POST[$keyname]) and the self-ref $new_group_id'
        );

        $this->deleteUserByName($userName);
        $this->deleteGroupById($groupId);
    }

    /**
     * Regression test for the `$itemcount` count-bound loop + the dynamic `${$keyname}` →
     * `$_POST[$keyname]` conversion: show_result_allusers deletes the selected test-result rows.
     */
    public function testDeleteSelectedTestResult(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');

        // A real test row is required (testuser_test_id has a FK to tce_tests); none are seeded.
        // Owned by admin so the results page is authorized to render.
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id) VALUES ('itest_res_test','d'," . $adminId . ')'
        );
        $testId = (int) ($this->dbScalar("SELECT test_id FROM tce_tests WHERE test_name='itest_res_test'") ?? '0');
        $this->assertGreaterThan(0, $testId);

        $this->dbExec(
            "INSERT INTO tce_tests_users (testuser_test_id,testuser_user_id,testuser_creation_time) "
            . 'VALUES (' . $testId . ',' . $adminId . ",'2020-01-01 00:00:00')"
        );
        $tid = (int) ($this->dbScalar(
            'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId . ' ORDER BY testuser_id DESC'
        ) ?? '0');
        $this->assertGreaterThan(0, $tid);

        // CSRF token is entry-script-bound: fetch it from this controller's own results page.
        [, $form] = $this->http('GET', '/admin/code/tce_show_result_allusers.php?test_id=' . $testId, $cookies);
        $token = self::extractCsrfToken($form);
        $this->assertNotNull($token, 'the results page should expose a CSRF token');

        // 'delete': $itemcount bounds the loop; testuserid1 is the selected row.
        [$status] = $this->http('POST', '/admin/code/tce_show_result_allusers.php', $cookies, [
            'delete' => '1',
            'itemcount' => '1',
            'testuserid1' => (string) $tid,
            'csrf_token' => $token,
        ]);
        $this->assertSame(200, $status);
        $this->assertSame(
            '0',
            $this->dbScalar('SELECT COUNT(*) FROM tce_tests_users WHERE testuser_id=' . $tid),
            'delete must read $itemcount and the testuserid<N> selection'
        );
    }

    public function testMonitoringActionsUpdateAttemptAndWriteAudit(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');
        $groupId = $this->ensureGroup('itest_monitor_group');
        if (!$this->userInGroup($adminId, $groupId)) {
            $this->dbExec(
                'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                . $adminId . ',' . $groupId . ')'
            );
        }

        $this->dbExec("DELETE FROM tce_tests WHERE test_name='itest_monitor_test'");
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time) "
            . "VALUES ('itest_monitor_test','d'," . $adminId . ',60)'
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='itest_monitor_test'"
        ) ?? '0');
        $this->assertGreaterThan(0, $testId);
        $this->dbExec(
            'INSERT INTO tce_testgroups (tstgrp_test_id,tstgrp_group_id) VALUES ('
            . $testId . ',' . $groupId . ')'
        );
        $this->dbExec(
            "INSERT INTO tce_tests_users (testuser_test_id,testuser_user_id,testuser_status,"
            . 'testuser_creation_time,testuser_last_activity) VALUES ('
            . $testId . ',' . $adminId . ",1,'2026-07-27 12:00:00','2026-07-27 12:00:00')"
        );
        $attemptId = (int) ($this->dbScalar(
            'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
        ) ?? '0');

        try {
            [$status, $body] = $this->http(
                'GET',
                '/admin/code/tce_monitor.php?test_id=' . $testId,
                $cookies
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('itest_monitor_test', $body);
            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token);

            [$status] = $this->http('POST', '/admin/code/tce_monitor.php', $cookies, [
                'test_id' => (string) $testId,
                'testuser_id' => (string) $attemptId,
                'monitor_action' => 'block',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $this->assertSame(
                'blocked',
                $this->dbScalar(
                    'SELECT testuser_close_reason FROM tce_tests_users WHERE testuser_id=' . $attemptId
                )
            );
            $this->assertSame(
                '1',
                $this->dbScalar(
                    "SELECT COUNT(*) FROM tce_monitor_audit WHERE monitor_testuser_id="
                    . $attemptId . " AND monitor_action='block'"
                )
            );

            [, $body] = $this->http(
                'GET',
                '/admin/code/tce_monitor.php?test_id=' . $testId,
                $cookies
            );
            $token = self::extractCsrfToken($body) ?? '';
            [$status] = $this->http('POST', '/admin/code/tce_monitor.php', $cookies, [
                'test_id' => (string) $testId,
                'testuser_id' => (string) $attemptId,
                'monitor_action' => 'unblock',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $this->assertSame(
                '1',
                $this->dbScalar(
                    'SELECT testuser_status FROM tce_tests_users WHERE testuser_id=' . $attemptId
                )
            );
            $this->assertSame(
                '2',
                $this->dbScalar(
                    'SELECT COUNT(*) FROM tce_monitor_audit WHERE monitor_testuser_id=' . $attemptId
                )
            );

            [, $body] = $this->http(
                'GET',
                '/admin/code/tce_monitor.php?test_id=' . $testId,
                $cookies
            );
            $token = self::extractCsrfToken($body) ?? '';
            [$status] = $this->http('POST', '/admin/code/tce_monitor.php', $cookies, [
                'test_id' => (string) $testId,
                'testuser_id' => (string) $attemptId,
                'monitor_action' => 'reset',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $this->assertSame(
                'reset',
                $this->dbScalar(
                    'SELECT testuser_close_reason FROM tce_tests_users WHERE testuser_id=' . $attemptId
                )
            );
            $this->assertSame(
                '1',
                $this->dbScalar(
                    'SELECT COUNT(*) FROM tce_tests_users WHERE testuser_test_id=' . $testId
                    . ' AND testuser_user_id=' . $adminId . ' AND testuser_status<5'
                )
            );
            $this->assertSame(
                '3',
                $this->dbScalar(
                    'SELECT COUNT(*) FROM tce_monitor_audit WHERE monitor_testuser_id=' . $attemptId
                )
            );
        } finally {
            $this->dbExec('DELETE FROM tce_monitor_audit WHERE monitor_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_testgroups WHERE tstgrp_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
            $this->dbExec(
                'DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $adminId
                . ' AND usrgrp_group_id=' . $groupId
            );
            $this->deleteGroupById($groupId);
        }
    }

    public function testPregenerationCreatesAndInvalidatesUnopenedVariant(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');
        $groupId = $this->ensureGroup('itest_pregen_group');
        if (!$this->userInGroup($adminId, $groupId)) {
            $this->dbExec(
                'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                . $adminId . ',' . $groupId . ')'
            );
        }
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='itest_pregen_test'");
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time) "
            . "VALUES ('itest_pregen_test','before'," . $adminId . ',60)'
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='itest_pregen_test'"
        ) ?? '0');
        $this->dbExec(
            'INSERT INTO tce_testgroups (tstgrp_test_id,tstgrp_group_id) VALUES ('
            . $testId . ',' . $groupId . ')'
        );

        try {
            [, $body] = $this->http(
                'GET',
                '/admin/code/tce_pregenerate.php?test_id=' . $testId,
                $cookies
            );
            $token = self::extractCsrfToken($body);
            $this->assertNotNull($token);
            [$status] = $this->http('POST', '/admin/code/tce_pregenerate.php', $cookies, [
                'test_id' => (string) $testId,
                'pregenerate' => '1',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $firstId = (int) ($this->dbScalar(
                'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
                . " AND testuser_pregenerated='1'"
            ) ?? '0');
            $firstHash = $this->dbScalar(
                'SELECT testuser_generation_hash FROM tce_tests_users WHERE testuser_id=' . $firstId
            );
            $this->assertGreaterThan(0, $firstId);
            $this->assertSame(64, strlen((string) $firstHash));

            $this->dbExec(
                "UPDATE tce_tests SET test_description='after' WHERE test_id=" . $testId
            );
            [, $body] = $this->http(
                'GET',
                '/admin/code/tce_pregenerate.php?test_id=' . $testId,
                $cookies
            );
            $token = self::extractCsrfToken($body) ?? '';
            [$status] = $this->http('POST', '/admin/code/tce_pregenerate.php', $cookies, [
                'test_id' => (string) $testId,
                'pregenerate' => '1',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $secondId = (int) ($this->dbScalar(
                'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
                . " AND testuser_pregenerated='1'"
            ) ?? '0');
            $secondHash = $this->dbScalar(
                'SELECT testuser_generation_hash FROM tce_tests_users WHERE testuser_id=' . $secondId
            );
            $this->assertNotSame($firstId, $secondId);
            $this->assertNotSame($firstHash, $secondHash);
            $this->assertSame(
                '0',
                $this->dbScalar(
                    'SELECT COUNT(*) FROM tce_tests_users WHERE testuser_id=' . $firstId
                )
            );
        } finally {
            $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_testgroups WHERE tstgrp_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
            $this->dbExec(
                'DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $adminId
                . ' AND usrgrp_group_id=' . $groupId
            );
            $this->deleteGroupById($groupId);
        }
    }

    /** @throws \Random\RandomException */
    public function testSignedOfflinePackageImportsIdempotentlyAndRejectsTampering(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='itest_offline_test'");
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time,test_end_time) "
            . "VALUES ('itest_offline_test','d'," . $adminId . ",60,'2035-01-01 00:00:00')"
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='itest_offline_test'"
        ) ?? '0');
        $this->dbExec("DELETE FROM tce_modules WHERE module_name='itest_offline_module'");
        $this->dbExec(
            "INSERT INTO tce_modules (module_name,module_enabled,module_user_id) "
            . "VALUES ('itest_offline_module','1'," . $adminId . ')'
        );
        $moduleId = (int) ($this->dbScalar(
            "SELECT module_id FROM tce_modules WHERE module_name='itest_offline_module'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_subjects (subject_module_id,subject_name,subject_description,"
            . "subject_enabled,subject_user_id) VALUES ("
            . $moduleId . ",'itest_offline_subject','d','1'," . $adminId . ')'
        );
        $subjectId = (int) ($this->dbScalar(
            "SELECT subject_id FROM tce_subjects WHERE subject_name='itest_offline_subject'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
            . "question_enabled,question_position) VALUES ("
            . $subjectId . ",'Offline essay',3,'1',1)"
        );
        $questionId = (int) ($this->dbScalar(
            'SELECT question_id FROM tce_questions WHERE question_subject_id=' . $subjectId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_users (testuser_test_id,testuser_user_id,testuser_status,"
            . 'testuser_creation_time,testuser_last_activity) VALUES ('
            . $testId . ',' . $adminId . ",1,'2026-07-27 12:00:00','2026-07-27 12:00:00')"
        );
        $attemptId = (int) ($this->dbScalar(
            'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
            . "testlog_creation_time,testlog_order) VALUES ("
            . $attemptId . ',' . $questionId . ",0,'2026-07-27 12:00:00',1)"
        );
        $testlogId = (int) ($this->dbScalar(
            'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
        ) ?? '0');

        try {
            [, $page] = $this->http(
                'GET',
                '/admin/code/tce_offline.php?test_id=' . $testId,
                $cookies
            );
            $token = self::extractCsrfToken($page);
            $this->assertNotNull($token);
            [$status, $html] = $this->http('POST', '/admin/code/tce_offline.php', $cookies, [
                'test_id' => (string) $testId,
                'testuser_id' => (string) $attemptId,
                'export_offline' => '1',
                'csrf_token' => $token,
            ]);
            $this->assertSame(200, $status);
            $this->assertStringContainsString('default-src', $html);
            $this->assertMatchesRegularExpression('/var envelope = (\\{[^;]+\\});/', $html);
            $match = [];
            preg_match('/var envelope = (\\{[^;]+\\});/', $html, $match);
            /** @var array{payload_b64:string,signature:string} $envelope */
            $envelope = json_decode($match[1] ?? '', true, 16, JSON_THROW_ON_ERROR);
            $this->assertIsArray($envelope);

            $offlineResult = json_encode([
                'format' => 'OpenVsoshCBT-offline-result-v1',
                'payload_b64' => $envelope['payload_b64'],
                'signature' => $envelope['signature'],
                'submitted_at' => '2026-07-27T12:30:00Z',
                'answers' => [[
                    'testlog_id' => $testlogId,
                    'positions' => [],
                    'text' => 'offline answer',
                    'reaction_time' => 1200,
                ]],
            ], JSON_THROW_ON_ERROR);
            /** @var array<string,mixed> $tampered */
            $tampered = json_decode($offlineResult, true, 16, JSON_THROW_ON_ERROR);
            $tampered['signature'] = str_repeat('0', 64);
            [$status, $body] = $this->httpUpload(
                '/admin/code/tce_offline.php?test_id=' . $testId,
                $cookies,
                ['import_offline' => '1', 'csrf_token' => $token],
                'result_file',
                'tampered.json',
                json_encode($tampered, JSON_THROW_ON_ERROR),
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('signature_failed', $body);

            [$status, $body] = $this->httpUpload(
                '/admin/code/tce_offline.php?test_id=' . $testId,
                $cookies,
                ['import_offline' => '1', 'csrf_token' => $token],
                'result_file',
                'result.json',
                $offlineResult,
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('Результат принят: imported', $body);
            $this->assertSame(
                '4',
                $this->dbScalar(
                    'SELECT testuser_status FROM tce_tests_users WHERE testuser_id=' . $attemptId
                )
            );
            $this->assertSame(
                'offline answer',
                $this->dbScalar(
                    'SELECT testlog_answer_text FROM tce_tests_logs WHERE testlog_id=' . $testlogId
                )
            );

            [, $body] = $this->httpUpload(
                '/admin/code/tce_offline.php?test_id=' . $testId,
                $cookies,
                ['import_offline' => '1', 'csrf_token' => $token],
                'result_file',
                'result.json',
                $offlineResult,
            );
            $this->assertStringContainsString('Результат принят: duplicate', $body);
            $this->assertSame(
                '1',
                $this->dbScalar(
                    "SELECT COUNT(*) FROM tce_offline_packages WHERE offline_testuser_id="
                    . $attemptId . " AND offline_status='imported'"
                )
            );
        } finally {
            $this->dbExec('DELETE FROM tce_offline_packages WHERE offline_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_questions WHERE question_id=' . $questionId);
            $this->dbExec('DELETE FROM tce_subjects WHERE subject_id=' . $subjectId);
            $this->dbExec('DELETE FROM tce_modules WHERE module_id=' . $moduleId);
        }
    }

    /**
     * @throws \Random\RandomException
     */
    public function testEveryQuestionTypePersistsThroughAnswerSaveAndReload(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');
        $groupId = $this->ensureGroup('itest_answer_types_group');
        if (!$this->userInGroup($adminId, $groupId)) {
            $this->dbExec(
                'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                . $adminId . ',' . $groupId . ')'
            );
        }
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='itest_answer_types_test'");
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time,"
            . "test_begin_time,test_end_time,test_mcma_radio) VALUES "
            . "('itest_answer_types_test','d'," . $adminId
            . ",60,'2020-01-01 00:00:00','2035-01-01 00:00:00','0')"
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='itest_answer_types_test'"
        ) ?? '0');
        $this->dbExec(
            'INSERT INTO tce_testgroups (tstgrp_test_id,tstgrp_group_id) VALUES ('
            . $testId . ',' . $groupId . ')'
        );
        $this->dbExec("DELETE FROM tce_modules WHERE module_name='itest_answer_types_module'");
        $this->dbExec(
            "INSERT INTO tce_modules (module_name,module_enabled,module_user_id) "
            . "VALUES ('itest_answer_types_module','1'," . $adminId . ')'
        );
        $moduleId = (int) ($this->dbScalar(
            "SELECT module_id FROM tce_modules WHERE module_name='itest_answer_types_module'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_subjects (subject_module_id,subject_name,subject_description,"
            . "subject_enabled,subject_user_id) VALUES ("
            . $moduleId . ",'itest_answer_types_subject','d','1'," . $adminId . ')'
        );
        $subjectId = (int) ($this->dbScalar(
            "SELECT subject_id FROM tce_subjects WHERE subject_name='itest_answer_types_subject'"
        ) ?? '0');

        $questions = [
            1 => 'Persist single choice',
            2 => 'Persist multiple choice<!--TMF_CHECKBOX-->',
            3 => 'Persist text',
            4 => 'Persist ordering',
            5 => 'Persist matching<!--TMF_MATCH_POSITIONS:2-->',
        ];
        $questionIds = [];
        $answerIds = [];
        foreach ($questions as $type => $description) {
            $this->dbExec(
                "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
                . "question_enabled,question_position,question_difficulty) VALUES ("
                . $subjectId . ",'" . $description . "'," . $type . ",'1'," . $type . ',1)'
            );
            $questionId = (int) ($this->dbScalar(
                'SELECT MAX(question_id) FROM tce_questions WHERE question_subject_id=' . $subjectId
            ) ?? '0');
            $questionIds[$type] = $questionId;
            if ($type === 3) {
                continue;
            }
            $answerIds[$type] = [];
            foreach ([1, 2] as $position) {
                $isRight = $position === 1 ? 1 : 0;
                $this->dbExec(
                    "INSERT INTO tce_answers (answer_question_id,answer_description,answer_isright,"
                    . "answer_enabled,answer_position) VALUES ("
                    . $questionId . ",'Type " . $type . ' answer ' . $position . "',"
                    . "'" . $isRight . "','1'," . $position . ')'
                );
                $answerIds[$type][$position] = (int) ($this->dbScalar(
                    'SELECT MAX(answer_id) FROM tce_answers WHERE answer_question_id=' . $questionId
                ) ?? '0');
            }
        }

        $this->dbExec(
            "INSERT INTO tce_tests_users (testuser_test_id,testuser_user_id,testuser_status,"
            . 'testuser_creation_time,testuser_last_activity) VALUES ('
            . $testId . ',' . $adminId . ',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $attemptId = (int) ($this->dbScalar(
            'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
        ) ?? '0');
        $logIds = [];
        foreach ($questionIds as $type => $questionId) {
            $this->dbExec(
                "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
                . "testlog_creation_time,testlog_order) VALUES ("
                . $attemptId . ',' . $questionId . ',0,CURRENT_TIMESTAMP,' . $type . ')'
            );
            $logId = (int) ($this->dbScalar(
                'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
                . ' AND testlog_question_id=' . $questionId
            ) ?? '0');
            $logIds[$type] = $logId;
            if ($type === 3) {
                continue;
            }
            foreach ([1, 2] as $order) {
                $this->dbExec(
                    'INSERT INTO tce_tests_logs_answers (logansw_testlog_id,logansw_answer_id,'
                    . 'logansw_selected,logansw_order,logansw_position) VALUES ('
                    . $logId . ',' . $answerIds[$type][$order] . ',-1,' . $order . ',0)'
                );
            }
        }

        try {
            $payloads = [
                1 => ['answpos' => '1'],
                2 => ['answpos' => ['1' => '1', '2' => '1']],
                3 => ['answertext' => 'Text survives navigation and reload'],
                4 => ['answpos' => ['1' => '2', '2' => '1']],
                5 => ['answpos' => ['1' => '2', '2' => '1']],
            ];
            foreach ($payloads as $type => $answerPayload) {
                [$pageStatus, $page] = $this->http(
                    'GET',
                    '/public/code/tce_test_execute.php?testid=' . $testId
                    . '&testlogid=' . $logIds[$type],
                    $cookies,
                );
                $this->assertSame(200, $pageStatus, 'initial page for type ' . $type);
                $token = self::extractCsrfToken($page);
                $this->assertNotNull($token);
                [$saveStatus, $saveBody] = $this->http(
                    'POST',
                    '/public/code/tce_test_answer_save.php',
                    $cookies,
                    array_merge(
                        [
                            'testid' => (string) $testId,
                            'testlogid' => (string) $logIds[$type],
                            'answer_version' => '0',
                            'answer_operation' => bin2hex(random_bytes(16)),
                            'csrf_token' => $token,
                        ],
                        $answerPayload,
                    ),
                );
                $this->assertSame(200, $saveStatus, 'save for type ' . $type . ': ' . $saveBody);
                $this->assertSame(
                    ['status' => 'saved', 'version' => 1],
                    json_decode($saveBody, true, 8, JSON_THROW_ON_ERROR),
                );

                // Navigate to another question before loading the saved one again.
                $nextType = $type === 5 ? 1 : $type + 1;
                [$nextStatus] = $this->http(
                    'GET',
                    '/public/code/tce_test_execute.php?testid=' . $testId
                    . '&testlogid=' . $logIds[$nextType],
                    $cookies,
                );
                $this->assertSame(200, $nextStatus);
                [$reloadStatus, $reloadPage] = $this->http(
                    'GET',
                    '/public/code/tce_test_execute.php?testid=' . $testId
                    . '&testlogid=' . $logIds[$type],
                    $cookies,
                );
                $this->assertSame(200, $reloadStatus, 'reload for type ' . $type);
                $visibleDescription = explode('<!--', $questions[$type], 2)[0];
                $this->assertStringContainsString($visibleDescription, $reloadPage);
                $this->assertMatchesRegularExpression(
                    '/name="answer_version"[^>]*value="1"/',
                    $reloadPage,
                );
                if ($type === 1) {
                    $this->assertMatchesRegularExpression(
                        '/id="answpos_1" value="1" checked="checked"/',
                        $reloadPage,
                    );
                } elseif ($type === 2) {
                    $this->assertMatchesRegularExpression(
                        '/id="answpos_1" value="1"[^>]*checked="checked"/',
                        $reloadPage,
                    );
                    $this->assertMatchesRegularExpression(
                        '/id="answpos_2" value="1"[^>]*checked="checked"/',
                        $reloadPage,
                    );
                } elseif ($type === 3) {
                    $this->assertStringContainsString(
                        'Text survives navigation and reload',
                        $reloadPage,
                    );
                } else {
                    $this->assertMatchesRegularExpression(
                        '/id="answpos_1">.*?value="2" selected="selected"/s',
                        $reloadPage,
                    );
                    $this->assertMatchesRegularExpression(
                        '/id="answpos_2">.*?value="1" selected="selected"/s',
                        $reloadPage,
                    );
                }
            }

            $this->assertSame(
                'Text survives navigation and reload',
                $this->dbScalar(
                    'SELECT testlog_answer_text FROM tce_tests_logs WHERE testlog_id=' . $logIds[3]
                ),
            );
            foreach ([1 => [1, 0], 2 => [1, 1]] as $type => $selectedValues) {
                foreach ($selectedValues as $offset => $selected) {
                    $order = $offset + 1;
                    $this->assertSame(
                        (string) $selected,
                        $this->dbScalar(
                            'SELECT logansw_selected FROM tce_tests_logs_answers WHERE '
                            . 'logansw_testlog_id=' . $logIds[$type] . ' AND logansw_order=' . $order
                        ),
                    );
                }
            }
            foreach ([4, 5] as $type) {
                foreach ([1 => 2, 2 => 1] as $order => $position) {
                    $this->assertSame(
                        (string) $position,
                        $this->dbScalar(
                            'SELECT logansw_position FROM tce_tests_logs_answers WHERE '
                            . 'logansw_testlog_id=' . $logIds[$type] . ' AND logansw_order=' . $order
                        ),
                    );
                }
            }
        } finally {
            $this->dbExec(
                'DELETE FROM tce_tests_logs_answers WHERE logansw_testlog_id IN ('
                . implode(',', $logIds) . ')'
            );
            $this->dbExec('DELETE FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId);
            $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_id=' . $attemptId);
            $this->dbExec('DELETE FROM tce_testgroups WHERE tstgrp_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
            $this->dbExec(
                'DELETE FROM tce_answers WHERE answer_question_id IN ('
                . implode(',', $questionIds) . ')'
            );
            $this->dbExec('DELETE FROM tce_questions WHERE question_subject_id=' . $subjectId);
            $this->dbExec('DELETE FROM tce_subjects WHERE subject_id=' . $subjectId);
            $this->dbExec('DELETE FROM tce_modules WHERE module_id=' . $moduleId);
            $this->dbExec(
                'DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $adminId
                . ' AND usrgrp_group_id=' . $groupId
            );
            $this->deleteGroupById($groupId);
        }
    }

    /** @throws \Random\RandomException */
    public function testEssayAttachmentUploadDownloadAndArchiveFlow(): void
    {
        $cookies = $this->login();
        $adminId = $this->userIdByName('admin');
        $groupId = $this->ensureGroup('itest_attachment_group');
        if (!$this->userInGroup($adminId, $groupId)) {
            $this->dbExec(
                'INSERT INTO tce_usrgroups (usrgrp_user_id,usrgrp_group_id) VALUES ('
                . $adminId . ',' . $groupId . ')'
            );
        }
        $this->dbExec("DELETE FROM tce_tests WHERE test_name='itest_attachment_test'");
        $this->dbExec(
            "INSERT INTO tce_tests (test_name,test_description,test_user_id,test_duration_time,"
            . "test_begin_time,test_end_time,test_require_all_answers) VALUES "
            . "('itest_attachment_test','d'," . $adminId
            . ",60,'2020-01-01 00:00:00','2035-01-01 00:00:00','1')"
        );
        $testId = (int) ($this->dbScalar(
            "SELECT test_id FROM tce_tests WHERE test_name='itest_attachment_test'"
        ) ?? '0');
        $this->dbExec(
            'INSERT INTO tce_testgroups (tstgrp_test_id,tstgrp_group_id) VALUES ('
            . $testId . ',' . $groupId . ')'
        );
        $this->dbExec("DELETE FROM tce_modules WHERE module_name='itest_attachment_module'");
        $this->dbExec(
            "INSERT INTO tce_modules (module_name,module_enabled,module_user_id) "
            . "VALUES ('itest_attachment_module','1'," . $adminId . ')'
        );
        $moduleId = (int) ($this->dbScalar(
            "SELECT module_id FROM tce_modules WHERE module_name='itest_attachment_module'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_subjects (subject_module_id,subject_name,subject_description,"
            . "subject_enabled,subject_user_id) VALUES ("
            . $moduleId . ",'itest_attachment_subject','d','1'," . $adminId . ')'
        );
        $subjectId = (int) ($this->dbScalar(
            "SELECT subject_id FROM tce_subjects WHERE subject_name='itest_attachment_subject'"
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_questions (question_subject_id,question_description,question_type,"
            . "question_enabled,question_position) VALUES ("
            . $subjectId . ",'Essay with evidence',3,'1',1)"
        );
        $questionId = (int) ($this->dbScalar(
            'SELECT question_id FROM tce_questions WHERE question_subject_id=' . $subjectId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_users (testuser_test_id,testuser_user_id,testuser_status,"
            . 'testuser_creation_time,testuser_last_activity) VALUES ('
            . $testId . ',' . $adminId . ',1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
        );
        $attemptId = (int) ($this->dbScalar(
            'SELECT testuser_id FROM tce_tests_users WHERE testuser_test_id=' . $testId
        ) ?? '0');
        $this->dbExec(
            "INSERT INTO tce_tests_logs (testlog_testuser_id,testlog_question_id,testlog_score,"
            . "testlog_creation_time,testlog_order) VALUES ("
            . $attemptId . ',' . $questionId . ',0,CURRENT_TIMESTAMP,1)'
        );
        $testlogId = (int) ($this->dbScalar(
            'SELECT testlog_id FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId
        ) ?? '0');

        try {
            [$status, $page] = $this->http(
                'GET',
                '/public/code/tce_test_execute.php?testid=' . $testId . '&testlogid=' . $testlogId,
                $cookies
            );
            $this->assertSame(200, $status);
            $this->assertStringContainsString('id="required-answers-notice"', $page);
            $this->assertStringContainsString('Пропущены: 1', $page);
            $this->assertStringNotContainsString('name="terminatetest"', $page);
            $token = self::extractCsrfToken($page);
            $this->assertNotNull($token);
            $versionMatch = [];
            preg_match('/name="answer_version"[^>]*value="(\\d+)"/', $page, $versionMatch);
            $this->assertNotEmpty($versionMatch);
            $answerVersion = $versionMatch[1] ?? '';
            $operation = bin2hex(random_bytes(16));
            [$saveStatus, $saveBody] = $this->http(
                'POST',
                '/public/code/tce_test_answer_save.php',
                $cookies,
                [
                    'testid' => (string) $testId,
                    'testlogid' => (string) $testlogId,
                    'answertext' => 'Confirmed before lost response',
                    'answer_version' => $answerVersion,
                    'answer_operation' => $operation,
                    'csrf_token' => $token,
                ],
            );
            $this->assertSame(200, $saveStatus);
            $this->assertSame(
                ['status' => 'saved', 'version' => 1],
                json_decode($saveBody, true, 8, JSON_THROW_ON_ERROR),
            );

            // Repeating the same operation models a response lost after the
            // server commit: it must be acknowledged without a second write.
            [$duplicateStatus, $duplicateBody] = $this->http(
                'POST',
                '/public/code/tce_test_answer_save.php',
                $cookies,
                [
                    'testid' => (string) $testId,
                    'testlogid' => (string) $testlogId,
                    'answertext' => 'Confirmed before lost response',
                    'answer_version' => $answerVersion,
                    'answer_operation' => $operation,
                    'csrf_token' => $token,
                ],
            );
            $this->assertSame(200, $duplicateStatus);
            $this->assertSame(
                ['status' => 'saved', 'version' => 1],
                json_decode($duplicateBody, true, 8, JSON_THROW_ON_ERROR),
            );

            [$staleStatus, $staleBody] = $this->http(
                'POST',
                '/public/code/tce_test_answer_save.php',
                $cookies,
                [
                    'testid' => (string) $testId,
                    'testlogid' => (string) $testlogId,
                    'answertext' => 'Must not overwrite',
                    'answer_version' => $answerVersion,
                    'answer_operation' => bin2hex(random_bytes(16)),
                    'csrf_token' => $token,
                ],
            );
            $this->assertSame(409, $staleStatus);
            $this->assertSame(
                ['status' => 'conflict', 'version' => 1],
                json_decode($staleBody, true, 8, JSON_THROW_ON_ERROR),
            );
            $this->assertSame(
                'Confirmed before lost response',
                $this->dbScalar('SELECT testlog_answer_text FROM tce_tests_logs WHERE testlog_id=' . $testlogId),
            );

            [$reloadStatus, $reloadPage] = $this->http(
                'GET',
                '/public/code/tce_test_execute.php?testid=' . $testId . '&testlogid=' . $testlogId,
                $cookies,
            );
            $this->assertSame(200, $reloadStatus);
            $this->assertStringContainsString('Confirmed before lost response', $reloadPage);
            $this->assertMatchesRegularExpression(
                '/name="answer_version"[^>]*value="1"/',
                $reloadPage,
            );
            $png = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true
            );
            [$status, $body] = $this->httpUpload(
                '/public/code/tce_test_execute.php',
                $cookies,
                [
                    'testid' => (string) $testId,
                    'testlogid' => (string) $testlogId,
                    'answertext' => 'Evidence attached',
                    'answer_version' => '1',
                    'confirmanswer' => '1',
                    'csrf_token' => $token,
                ],
                'answer_attachments[]',
                'evidence.png',
                (string) $png
            );
            $this->assertSame(200, $status);
            $this->assertStringNotContainsString('не был загружен', $body);
            $this->assertStringContainsString('name="terminatetest"', $body);
            $attachmentId = (int) ($this->dbScalar(
                'SELECT attachment_id FROM tce_testlog_attachments WHERE attachment_testlog_id=' . $testlogId
            ) ?? '0');
            $this->assertGreaterThan(0, $attachmentId);
            $storedName = $this->dbScalar(
                'SELECT attachment_stored_name FROM tce_testlog_attachments WHERE attachment_id=' . $attachmentId
            ) ?? '';
            [$directStatus] = $this->http('GET', '/cache/attachments/' . $storedName, $cookies);
            $this->assertContains($directStatus, [403, 404]);

            [$status, $download] = $this->http(
                'GET',
                '/admin/code/tce_attachment.php?id=' . $attachmentId,
                $cookies
            );
            $this->assertSame(200, $status);
            $this->assertSame($png, $download);

            [$status, $archive] = $this->http(
                'GET',
                '/admin/code/tce_attempt_archive.php?testuser_id=' . $attemptId,
                $cookies
            );
            $this->assertSame(200, $status);
            $this->assertStringStartsWith("PK\x03\x04", $archive);

            [$status, $pdf] = $this->http(
                'GET',
                '/admin/code/tce_pdf_results.php?mode=3&test_id=' . $testId
                . '&user_id=' . $adminId . '&testuser_id=' . $attemptId,
                $cookies
            );
            $this->assertSame(200, $status);
            $this->assertStringStartsWith('%PDF-', $pdf);
        } finally {
            $this->dbExec('DELETE FROM tce_testlog_attachments WHERE attachment_testlog_id=' . $testlogId);
            $this->dbExec('DELETE FROM tce_tests_logs WHERE testlog_testuser_id=' . $attemptId);
            $this->dbExec('DELETE FROM tce_tests_users WHERE testuser_id=' . $attemptId);
            $this->dbExec('DELETE FROM tce_testgroups WHERE tstgrp_test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_tests WHERE test_id=' . $testId);
            $this->dbExec('DELETE FROM tce_questions WHERE question_id=' . $questionId);
            $this->dbExec('DELETE FROM tce_subjects WHERE subject_id=' . $subjectId);
            $this->dbExec('DELETE FROM tce_modules WHERE module_id=' . $moduleId);
            $this->dbExec(
                'DELETE FROM tce_usrgroups WHERE usrgrp_user_id=' . $adminId
                . ' AND usrgrp_group_id=' . $groupId
            );
            $this->deleteGroupById($groupId);
        }
    }

}
