ALTER TABLE tce_tests ADD (
    test_required_finished_id NUMBER(19,0) NULL,
    test_required_passed_id NUMBER(19,0) NULL,
    test_minimum_duration_time NUMBER(5,0) DEFAULT 0 NOT NULL,
    test_require_all_answers NUMBER(1) DEFAULT 0 NOT NULL,
    test_block_finish_below_threshold NUMBER(1) DEFAULT 0 NOT NULL,
    test_disable_previous NUMBER(1) DEFAULT 0 NOT NULL,
    test_disable_next NUMBER(1) DEFAULT 0 NOT NULL,
    test_hide_editor NUMBER(1) DEFAULT 0 NOT NULL,
    test_completion_message NCLOB NULL
);

COMMIT;
