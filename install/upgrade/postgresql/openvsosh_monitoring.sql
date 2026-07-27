ALTER TABLE "tce_tests_users"
    ADD COLUMN "testuser_last_activity" TIMESTAMP NULL,
    ADD COLUMN "testuser_close_reason" VARCHAR(16) NULL;

ALTER TABLE "tce_tests_logs"
    ADD COLUMN "testlog_answer_saved_at" TIMESTAMP NULL;

CREATE TABLE "tce_monitor_audit" (
    "monitor_audit_id" BIGSERIAL NOT NULL,
    "monitor_audit_time" TIMESTAMP NOT NULL,
    "monitor_actor_user_id" BIGINT NOT NULL,
    "monitor_testuser_id" BIGINT NOT NULL,
    "monitor_test_id" BIGINT NOT NULL,
    "monitor_target_user_id" BIGINT NOT NULL,
    "monitor_action" VARCHAR(32) NOT NULL,
    "monitor_details" VARCHAR(255),
    "monitor_ip" VARCHAR(39),
    CONSTRAINT "pk_tce_monitor_audit" PRIMARY KEY ("monitor_audit_id")
);

CREATE INDEX "idx_monitor_audit_test_time"
    ON "tce_monitor_audit" ("monitor_test_id", "monitor_audit_time");
CREATE INDEX "idx_monitor_audit_attempt"
    ON "tce_monitor_audit" ("monitor_testuser_id");

UPDATE "tce_tests_users"
SET "testuser_last_activity"="testuser_creation_time"
WHERE "testuser_last_activity" IS NULL;
