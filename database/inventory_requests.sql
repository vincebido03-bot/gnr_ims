CREATE TABLE IF NOT EXISTS inventory_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_item_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    quantity_requested DECIMAL(12,2) NOT NULL,
    reason TEXT NULL,
    status ENUM('PENDING','APPROVED','REJECTED','FULFILLED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inventory_requests_item (inventory_item_id),
    KEY idx_inventory_requests_employee (employee_id),
    CONSTRAINT fk_inventory_requests_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id),
    CONSTRAINT fk_inventory_requests_employee FOREIGN KEY (employee_id) REFERENCES employees (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;