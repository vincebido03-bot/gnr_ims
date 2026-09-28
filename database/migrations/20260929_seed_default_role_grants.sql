INSERT INTO role_permissions (role_id, permission_id)
SELECT role.id, permission.id
FROM roles role
INNER JOIN permissions permission
    ON permission.module <> 'role_permissions'
WHERE role.role_name = 'OWNER'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permissions existing
      WHERE existing.role_id = role.id
        AND existing.permission_id = permission.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.id, permission.id
FROM roles role
INNER JOIN permissions permission
    ON (permission.module = 'dashboard' AND permission.action = 'view')
    OR (permission.module = 'employees' AND permission.action IN ('view', 'create', 'edit'))
    OR (permission.module = 'attendance' AND permission.action IN ('view', 'create', 'edit'))
    OR (permission.module = 'payroll' AND permission.action IN ('view', 'create', 'edit'))
    OR (permission.module = 'notifications' AND permission.action = 'view')
    OR (permission.module = 'settings' AND permission.action = 'view')
    OR (permission.module = 'archive' AND permission.action IN ('view', 'restore'))
WHERE role.role_name = 'HR'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permissions existing
      WHERE existing.role_id = role.id
        AND existing.permission_id = permission.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.id, permission.id
FROM roles role
INNER JOIN permissions permission
    ON (permission.module = 'dashboard' AND permission.action = 'view')
    OR (permission.module IN ('customers', 'vehicles', 'inquiries') AND permission.action IN ('view', 'create', 'edit'))
    OR (permission.module = 'quotations' AND permission.action IN ('view', 'create', 'edit', 'approve'))
    OR (permission.module = 'orders' AND permission.action IN ('view', 'create', 'edit', 'approve'))
    OR (permission.module = 'jobs' AND permission.action IN ('view', 'create', 'edit'))
    OR (permission.module = 'inventory_requests' AND permission.action IN ('view', 'create'))
    OR (permission.module IN ('notifications', 'invoices', 'releases') AND permission.action = 'view')
WHERE role.role_name = 'FRONTDESK'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permissions existing
      WHERE existing.role_id = role.id
        AND existing.permission_id = permission.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.id, permission.id
FROM roles role
INNER JOIN permissions permission
    ON (permission.module = 'dashboard' AND permission.action = 'view')
    OR (permission.module = 'employees' AND permission.action IN ('view', 'edit'))
    OR (permission.module = 'attendance' AND permission.action IN ('view', 'time_in', 'time_out', 'edit'))
    OR (permission.module = 'payroll' AND permission.action = 'view')
    OR (permission.module = 'inventory_requests' AND permission.action IN ('view', 'create'))
    OR (permission.module = 'notifications' AND permission.action = 'view')
WHERE role.role_name = 'EMPLOYEE'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permissions existing
      WHERE existing.role_id = role.id
        AND existing.permission_id = permission.id
  );