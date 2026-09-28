SET @has_display_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'display_name'
);

SET @add_display_name_sql = IF(
    @has_display_name = 0,
    'ALTER TABLE users ADD COLUMN display_name VARCHAR(200) NULL AFTER employee_id',
    'SELECT 1'
);

PREPARE add_display_name_statement FROM @add_display_name_sql;
EXECUTE add_display_name_statement;
DEALLOCATE PREPARE add_display_name_statement;