ALTER TABLE tce_tests_users
    ADD COLUMN testuser_focus_loss_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN testuser_last_focus_event CHAR(32) NULL;
