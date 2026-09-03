ALTER TABLE tce_tests ADD (
    test_ai_trap_enabled NUMBER(1) DEFAULT 0 NOT NULL
);

COMMIT;
