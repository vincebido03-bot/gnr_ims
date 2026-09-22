ALTER TABLE releases
    ADD COLUMN released_by_name VARCHAR(255) NULL AFTER released_by;