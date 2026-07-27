ALTER TABLE tce_tests
    ADD test_required_finished_id BIGINT UNSIGNED NULL,
    ADD test_required_passed_id BIGINT UNSIGNED NULL,
    ADD test_minimum_duration_time SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ADD test_require_all_answers BOOL NOT NULL DEFAULT '0',
    ADD test_block_finish_below_threshold BOOL NOT NULL DEFAULT '0',
    ADD test_disable_previous BOOL NOT NULL DEFAULT '0',
    ADD test_disable_next BOOL NOT NULL DEFAULT '0',
    ADD test_hide_editor BOOL NOT NULL DEFAULT '0',
    ADD test_completion_message TEXT NULL;
