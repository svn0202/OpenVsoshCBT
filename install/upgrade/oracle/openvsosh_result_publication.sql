ALTER TABLE tce_tests ADD (
    test_results_publish_at DATE,
    test_results_unpublish_at DATE,
    test_results_anonymized NUMBER(1) DEFAULT '0' NOT NULL
);
