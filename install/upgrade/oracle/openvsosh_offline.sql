CREATE TABLE tce_offline_packages (
    offline_package_id CHAR(32) NOT NULL,
    offline_testuser_id NUMBER(19,0) NOT NULL,
    offline_test_id NUMBER(19,0) NOT NULL,
    offline_user_id NUMBER(19,0) NOT NULL,
    offline_issued_at DATE NOT NULL,
    offline_expires_at DATE NOT NULL,
    offline_payload_hash CHAR(64) NOT NULL,
    offline_status VARCHAR2(16) DEFAULT 'issued' NOT NULL,
    offline_imported_at DATE NULL,
    offline_result_hash CHAR(64) NULL,
    CONSTRAINT pk_tce_offline_packages PRIMARY KEY (offline_package_id)
);

CREATE INDEX idx_offline_test_status
    ON tce_offline_packages (offline_test_id, offline_status);

COMMIT;
