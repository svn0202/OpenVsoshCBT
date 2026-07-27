ALTER TABLE tce_tests_users
    ADD COLUMN testuser_last_activity DATETIME NULL,
    ADD COLUMN testuser_close_reason VARCHAR(16) NULL;

ALTER TABLE tce_tests_logs
    ADD COLUMN testlog_answer_saved_at DATETIME NULL;

CREATE TABLE tce_monitor_audit (
    monitor_audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitor_audit_time DATETIME NOT NULL,
    monitor_actor_user_id BIGINT UNSIGNED NOT NULL,
    monitor_testuser_id BIGINT UNSIGNED NOT NULL,
    monitor_test_id BIGINT UNSIGNED NOT NULL,
    monitor_target_user_id BIGINT UNSIGNED NOT NULL,
    monitor_action VARCHAR(32) NOT NULL,
    monitor_details VARCHAR(255) NULL,
    monitor_ip VARCHAR(39) NULL,
    PRIMARY KEY (monitor_audit_id),
    INDEX idx_monitor_audit_test_time (monitor_test_id, monitor_audit_time),
    INDEX idx_monitor_audit_attempt (monitor_testuser_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;

UPDATE tce_tests_users
SET testuser_last_activity=testuser_creation_time
WHERE testuser_last_activity IS NULL;
