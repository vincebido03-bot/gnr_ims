ALTER TABLE vehicles
    ADD COLUMN vehicle_no VARCHAR(30) NULL AFTER id,
    ADD UNIQUE KEY uq_vehicles_vehicle_no (vehicle_no);

UPDATE vehicles v
INNER JOIN customers c ON c.id = v.customer_id
SET v.vehicle_no = CONCAT('B', LPAD(CAST(SUBSTRING(c.customer_no, 2) AS UNSIGNED), 3, '0'))
WHERE c.customer_no REGEXP '^C[0-9]{3}$';