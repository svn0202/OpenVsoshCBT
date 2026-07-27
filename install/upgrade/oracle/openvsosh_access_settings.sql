CREATE TABLE tce_openvsosh_settings (
    setting_key VARCHAR2(64) NOT NULL,
    setting_value NCLOB NOT NULL,
    CONSTRAINT pk_openvsosh_settings PRIMARY KEY (setting_key)
);
