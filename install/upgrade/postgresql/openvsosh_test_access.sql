ALTER TABLE tce_tests
    ADD COLUMN test_required_finished_id BIGINT NULL,
    ADD COLUMN test_required_passed_id BIGINT NULL,
    ADD COLUMN test_minimum_duration_time SMALLINT NOT NULL DEFAULT 0,
    ADD COLUMN test_require_all_answers BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN test_block_finish_below_threshold BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN test_disable_previous BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN test_disable_next BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN test_hide_editor BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN test_completion_message TEXT NULL;
