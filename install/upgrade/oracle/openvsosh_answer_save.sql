ALTER TABLE tce_tests_logs ADD (
    testlog_answer_version NUMBER(19,0) DEFAULT 0 NOT NULL,
    testlog_answer_operation VARCHAR2(32)
);
