ALTER TABLE tce_tests_logs
    ADD COLUMN testlog_reviewed BOOLEAN NOT NULL DEFAULT FALSE;
