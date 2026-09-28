DELETE rp
FROM role_permissions rp
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE (p.module = 'attendance' AND p.action = 'create')
   OR (p.module = 'inquiries' AND p.action = 'edit')
   OR (p.module = 'inventory' AND p.action IN ('create', 'edit', 'delete'))
   OR (p.module = 'inventory_requests' AND p.action = 'edit')
   OR (p.module = 'invoices' AND p.action IN ('create', 'edit'))
   OR (p.module = 'jobs' AND p.action = 'delete')
   OR (p.module = 'orders' AND p.action IN ('edit', 'delete', 'approve'))
   OR (p.module = 'payments' AND p.action IN ('create', 'edit', 'approve'))
   OR (p.module = 'payroll' AND p.action IN ('create', 'edit', 'approve'))
   OR (p.module = 'quality_control' AND p.action IN ('edit', 'approve'))
   OR (p.module = 'quotations' AND p.action IN ('edit', 'delete'))
   OR (p.module = 'releases' AND p.action IN ('create', 'edit', 'approve'))
   OR (p.module = 'role_permissions')
   OR (p.module = 'stock_movement' AND p.action = 'create');

DELETE FROM permissions
WHERE (module = 'attendance' AND action = 'create')
   OR (module = 'inquiries' AND action = 'edit')
   OR (module = 'inventory' AND action IN ('create', 'edit', 'delete'))
   OR (module = 'inventory_requests' AND action = 'edit')
   OR (module = 'invoices' AND action IN ('create', 'edit'))
   OR (module = 'jobs' AND action = 'delete')
   OR (module = 'orders' AND action IN ('edit', 'delete', 'approve'))
   OR (module = 'payments' AND action IN ('create', 'edit', 'approve'))
   OR (module = 'payroll' AND action IN ('create', 'edit', 'approve'))
   OR (module = 'quality_control' AND action IN ('edit', 'approve'))
   OR (module = 'quotations' AND action IN ('edit', 'delete'))
   OR (module = 'releases' AND action IN ('create', 'edit', 'approve'))
   OR (module = 'role_permissions')
   OR (module = 'stock_movement' AND action = 'create');