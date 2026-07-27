CREATE TABLE IF NOT EXISTS tce_openvsosh_settings (
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
