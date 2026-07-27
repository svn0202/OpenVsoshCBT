CREATE TABLE tce_offline_packages (
    offline_package_id CHAR(32) NOT NULL PRIMARY KEY,
    offline_testuser_id BIGINT NOT NULL,
    offline_test_id BIGINT NOT NULL,
    offline_user_id BIGINT NOT NULL,
    offline_issued_at TIMESTAMP NOT NULL,
    offline_expires_at TIMESTAMP NOT NULL,
    offline_payload_hash CHAR(64) NOT NULL,
    offline_status VARCHAR(16) NOT NULL DEFAULT 'issued',
    offline_imported_at TIMESTAMP NULL,
    offline_result_hash CHAR(64) NULL
);

CREATE INDEX idx_offline_test_status
    ON tce_offline_packages (offline_test_id, offline_status);
