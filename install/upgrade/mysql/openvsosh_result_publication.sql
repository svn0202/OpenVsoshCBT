ALTER TABLE tce_tests
    ADD test_results_publish_at DATETIME NULL,
    ADD test_results_unpublish_at DATETIME NULL,
    ADD test_results_anonymized BOOL NOT NULL DEFAULT '0';
