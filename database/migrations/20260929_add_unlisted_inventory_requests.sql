ALTER TABLE inventory_requests
    MODIFY inventory_item_id INT(10) UNSIGNED NULL DEFAULT NULL;

SET @has_requested_item_name = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_requests'
      AND COLUMN_NAME = 'requested_item_name'
);
SET @add_requested_item_name_sql = IF(
    @has_requested_item_name = 0,
    'ALTER TABLE inventory_requests ADD COLUMN requested_item_name VARCHAR(255) NULL AFTER inventory_item_id',
    'SELECT 1'
);
PREPARE add_requested_item_name_statement FROM @add_requested_item_name_sql;
EXECUTE add_requested_item_name_statement;
DEALLOCATE PREPARE add_requested_item_name_statement;

SET @has_requested_category = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_requests'
      AND COLUMN_NAME = 'requested_category'
);
SET @add_requested_category_sql = IF(
    @has_requested_category = 0,
    'ALTER TABLE inventory_requests ADD COLUMN requested_category VARCHAR(100) NULL AFTER requested_item_name',
    'SELECT 1'
);
PREPARE add_requested_category_statement FROM @add_requested_category_sql;
EXECUTE add_requested_category_statement;
DEALLOCATE PREPARE add_requested_category_statement;

SET @has_requested_unit = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_requests'
      AND COLUMN_NAME = 'requested_unit'
);
SET @add_requested_unit_sql = IF(
    @has_requested_unit = 0,
    'ALTER TABLE inventory_requests ADD COLUMN requested_unit VARCHAR(30) NULL AFTER requested_category',
    'SELECT 1'
);
PREPARE add_requested_unit_statement FROM @add_requested_unit_sql;
EXECUTE add_requested_unit_statement;
DEALLOCATE PREPARE add_requested_unit_statement;