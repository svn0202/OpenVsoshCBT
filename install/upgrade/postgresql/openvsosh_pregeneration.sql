ALTER TABLE tce_tests_users
    ADD COLUMN testuser_generation_hash CHAR(64) NULL,
    ADD COLUMN testuser_pregenerated BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX idx_testuser_pregenerated
    ON tce_tests_users (testuser_test_id, testuser_pregenerated, testuser_status);
