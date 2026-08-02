ALTER TABLE tce_tests_users ADD (
    testuser_focus_loss_count NUMBER(10,0) DEFAULT 0 NOT NULL,
    testuser_last_focus_event CHAR(32) NULL
);

COMMIT;
