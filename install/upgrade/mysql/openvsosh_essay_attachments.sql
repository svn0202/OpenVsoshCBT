CREATE TABLE tce_testlog_attachments (
    attachment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attachment_testlog_id BIGINT UNSIGNED NOT NULL,
    attachment_user_id BIGINT UNSIGNED NOT NULL,
    attachment_stored_name CHAR(64) NOT NULL,
    attachment_original_name VARCHAR(255) NOT NULL,
    attachment_mime VARCHAR(64) NOT NULL,
    attachment_size BIGINT UNSIGNED NOT NULL,
    attachment_sha256 CHAR(64) NOT NULL,
    attachment_created_at DATETIME NOT NULL,
    PRIMARY KEY (attachment_id),
    UNIQUE (attachment_stored_name),
    INDEX idx_attachment_testlog (attachment_testlog_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_unicode_ci;
