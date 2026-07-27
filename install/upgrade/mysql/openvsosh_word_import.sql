ALTER TABLE tce_answers
    ADD COLUMN answer_weight SMALLINT NULL,
    ADD CONSTRAINT chk_tce_answers_weight
        CHECK (answer_weight IS NULL OR (answer_weight >= 0 AND answer_weight <= 100));
