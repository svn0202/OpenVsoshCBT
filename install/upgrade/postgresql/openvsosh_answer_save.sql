ALTER TABLE tce_tests_logs
    ADD COLUMN testlog_answer_version BIGINT NOT NULL DEFAULT 0,
    ADD COLUMN testlog_answer_operation VARCHAR(32);
