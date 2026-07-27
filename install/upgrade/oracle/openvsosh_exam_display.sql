ALTER TABLE tce_tests ADD (
    test_live_score NUMBER(1) DEFAULT 0 NOT NULL,
    test_auto_fullscreen NUMBER(1) DEFAULT 0 NOT NULL,
    test_hide_exam_info NUMBER(1) DEFAULT 0 NOT NULL
);
