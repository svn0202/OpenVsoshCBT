CREATE TABLE tce_testlog_attachments (
    attachment_id NUMBER(19,0) NOT NULL,
    attachment_testlog_id NUMBER(19,0) NOT NULL,
    attachment_user_id NUMBER(19,0) NOT NULL,
    attachment_stored_name CHAR(64) NOT NULL,
    attachment_original_name VARCHAR2(255) NOT NULL,
    attachment_mime VARCHAR2(64) NOT NULL,
    attachment_size NUMBER(19,0) NOT NULL,
    attachment_sha256 CHAR(64) NOT NULL,
    attachment_created_at DATE NOT NULL,
    CONSTRAINT pk_tce_testlog_attachments PRIMARY KEY (attachment_id),
    CONSTRAINT ak_tce_testlog_attachment_name UNIQUE (attachment_stored_name)
);

CREATE SEQUENCE tce_testlog_attachments_seq MINVALUE 1 START WITH 1 INCREMENT BY 1 CACHE 3;
CREATE OR REPLACE TRIGGER tce_testlog_attachments_trigger
BEFORE INSERT ON tce_testlog_attachments
FOR EACH ROW
BEGIN
    SELECT tce_testlog_attachments_seq.nextval INTO :new.attachment_id FROM DUAL;
END;;

CREATE INDEX idx_attachment_testlog
    ON tce_testlog_attachments (attachment_testlog_id);

COMMIT;
