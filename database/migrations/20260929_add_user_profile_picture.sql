SET @has_profile_picture = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'profile_picture'
);

SET @add_profile_picture_sql = IF(
    @has_profile_picture = 0,
    'ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER display_name',
    'SELECT 1'
);

PREPARE add_profile_picture_statement FROM @add_profile_picture_sql;
EXECUTE add_profile_picture_statement;
DEALLOCATE PREPARE add_profile_picture_statement;