ALTER TABLE tce_tests_users ADD (
    testuser_last_activity DATE NULL,
    testuser_close_reason VARCHAR2(16) NULL
);

ALTER TABLE tce_tests_logs ADD (
    testlog_answer_saved_at DATE NULL
);

CREATE TABLE tce_monitor_audit (
    monitor_audit_id NUMBER(19,0) NOT NULL,
    monitor_audit_time DATE NOT NULL,
    monitor_actor_user_id NUMBER(19,0) NOT NULL,
    monitor_testuser_id NUMBER(19,0) NOT NULL,
    monitor_test_id NUMBER(19,0) NOT NULL,
    monitor_target_user_id NUMBER(19,0) NOT NULL,
    monitor_action VARCHAR2(32) NOT NULL,
    monitor_details VARCHAR2(255),
    monitor_ip VARCHAR2(39),
    CONSTRAINT pk_tce_monitor_audit PRIMARY KEY (monitor_audit_id)
);

CREATE SEQUENCE tce_monitor_audit_seq MINVALUE 1 START WITH 1 INCREMENT BY 1 CACHE 3;
CREATE OR REPLACE TRIGGER tce_monitor_audit_trigger BEFORE INSERT ON tce_monitor_audit FOR EACH ROW BEGIN SELECT tce_monitor_audit_seq.nextval INTO :new.monitor_audit_id FROM DUAL; END;;

CREATE INDEX idx_monitor_audit_test_time
    ON tce_monitor_audit (monitor_test_id, monitor_audit_time);
CREATE INDEX idx_monitor_audit_attempt
    ON tce_monitor_audit (monitor_testuser_id);

UPDATE tce_tests_users
SET testuser_last_activity=testuser_creation_time
WHERE testuser_last_activity IS NULL;

COMMIT;
