ALTER TABLE tce_tests
    ADD COLUMN test_results_publish_at TIMESTAMP,
    ADD COLUMN test_results_unpublish_at TIMESTAMP,
    ADD COLUMN test_results_anonymized BOOLEAN NOT NULL DEFAULT '0';
