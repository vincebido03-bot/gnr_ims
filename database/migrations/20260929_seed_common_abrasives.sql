INSERT INTO inventory_categories (category_name, description)
VALUES ('ABRASIVES', 'Sandpaper and surface preparation supplies')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);

SET @abrasives_category_id = LAST_INSERT_ID();

INSERT INTO inventory_items (item_code, item_name, category_id, unit, current_stock, reorder_level, unit_cost, status, notes)
SELECT seed.item_code, seed.item_name, @abrasives_category_id, 'pc', 0, 0, 0, 'ACTIVE', 'Seeded as a requestable shop supply; enter actual stock when received.'
FROM (
    SELECT 'ABR-100' AS item_code, 'Liha 100 Grit' AS item_name
    UNION ALL SELECT 'ABR-240', 'Liha 240 Grit'
    UNION ALL SELECT 'ABR-400', 'Liha 400 Grit'
    UNION ALL SELECT 'ABR-600', 'Liha 600 Grit'
    UNION ALL SELECT 'ABR-800', 'Liha 800 Grit'
    UNION ALL SELECT 'ABR-1000', 'Liha 1000 Grit'
    UNION ALL SELECT 'ABR-1500', 'Liha 1500 Grit'
    UNION ALL SELECT 'ABR-2000', 'Liha 2000 Grit'
    UNION ALL SELECT 'ABR-2500', 'Liha 2500 Grit'
    UNION ALL SELECT 'ABR-3000', 'Liha 3000 Grit'
    UNION ALL SELECT 'ABR-4000', 'Liha 4000 Grit'
    UNION ALL SELECT 'ABR-5000', 'Liha 5000 Grit'
) AS seed
LEFT JOIN inventory_items existing ON existing.item_code = seed.item_code
WHERE existing.id IS NULL;