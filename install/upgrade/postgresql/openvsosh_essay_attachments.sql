CREATE TABLE tce_testlog_attachments (
    attachment_id BIGSERIAL NOT NULL PRIMARY KEY,
    attachment_testlog_id BIGINT NOT NULL,
    attachment_user_id BIGINT NOT NULL,
    attachment_stored_name CHAR(64) NOT NULL UNIQUE,
    attachment_original_name VARCHAR(255) NOT NULL,
    attachment_mime VARCHAR(64) NOT NULL,
    attachment_size BIGINT NOT NULL,
    attachment_sha256 CHAR(64) NOT NULL,
    attachment_created_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_attachment_testlog
    ON tce_testlog_attachments (attachment_testlog_id);
