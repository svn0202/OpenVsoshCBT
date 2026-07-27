ALTER TABLE tce_tests_users ADD (
    testuser_generation_hash CHAR(64) NULL,
    testuser_pregenerated NUMBER(1) DEFAULT '0' NOT NULL
);

CREATE INDEX idx_testuser_pregenerated
    ON tce_tests_users (testuser_test_id, testuser_pregenerated, testuser_status);

COMMIT;
