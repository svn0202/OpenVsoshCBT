ALTER TABLE tce_tests
    ADD test_live_score BOOL NOT NULL DEFAULT '0',
    ADD test_auto_fullscreen BOOL NOT NULL DEFAULT '0',
    ADD test_hide_exam_info BOOL NOT NULL DEFAULT '0';
