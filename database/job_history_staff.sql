ALTER TABLE jobs
    ADD COLUMN assigned_staff_name VARCHAR(255) NULL AFTER created_by;