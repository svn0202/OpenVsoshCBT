ALTER TABLE tce_answers
    ADD (answer_weight NUMBER(5,0) NULL);

ALTER TABLE tce_answers
    ADD CONSTRAINT chk_tce_answers_weight
    CHECK (answer_weight IS NULL OR (answer_weight >= 0 AND answer_weight <= 100));
