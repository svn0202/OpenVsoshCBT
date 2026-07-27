CREATE TABLE tce_offline_packages (
    offline_package_id CHAR(32) NOT NULL,
    offline_testuser_id BIGINT UNSIGNED NOT NULL,
    offline_test_id BIGINT UNSIGNED NOT NULL,
    offline_user_id BIGINT UNSIGNED NOT NULL,
    offline_issued_at DATETIME NOT NULL,
    offline_expires_at DATETIME NOT NULL,
    offline_payload_hash CHAR(64) NOT NULL,
    offline_status VARCHAR(16) NOT NULL DEFAULT 'issued',
    offline_imported_at DATETIME NULL,
    offline_result_hash CHAR(64) NULL,
    PRIMARY KEY (offline_package_id),
    INDEX idx_offline_test_status (offline_test_id, offline_status)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
