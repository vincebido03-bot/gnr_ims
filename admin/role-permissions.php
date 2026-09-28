<?php

require_once "includes/header.php";
requireRole("SUPER ADMIN");

if (empty($_SESSION["role_permissions_token"])) {
    $_SESSION["role_permissions_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "success";
$selectedRoleId = (int) ($_GET["role_id"] ?? $_POST["role_id"] ?? 0);
$employeePermissionScope = [
    "dashboard" => ["view"],
    "attendance" => ["view", "time_in", "time_out", "edit"],
    "employees" => ["view", "edit"],
    "inventory_requests" => ["view", "create"],
    "notifications" => ["view"],
    "payroll" => ["view"],
];
$allPermissions = $pdo->query("SELECT id, module, action, description FROM permissions ORDER BY module ASC, FIELD(action, 'view', 'create', 'edit', 'approve', 'delete', 'restore', 'delete_permanently', 'time_in', 'time_out'), action ASC")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $submittedToken = $_POST["role_permissions_token"] ?? "";
        if (!is_string($submittedToken) || !hash_equals($_SESSION["role_permissions_token"], $submittedToken)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }

        if (isset($_POST["create_role"])) {
            $roleName = strtoupper(trim((string) ($_POST["role_name"] ?? "")));
            $description = trim((string) ($_POST["description"] ?? ""));
            if (!preg_match("/^[A-Z][A-Z0-9 _-]{1,49}$/", $roleName)) {
                throw new RuntimeException("Enter a role name between 2 and 50 characters using letters, numbers, spaces, hyphens, or underscores.");
            }
            if (in_array($roleName, ["SUPER ADMIN", "OWNER", "HR", "FRONTDESK", "EMPLOYEE"], true)) {
                throw new RuntimeException("That role name is reserved.");
            }

            $insertRole = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $insertRole->execute([$roleName, $description !== "" ? $description : null]);
            $selectedRoleId = (int) $pdo->lastInsertId();
            logAudit("ROLE PERMISSIONS", "CREATE_ROLE", "ROLE", $selectedRoleId, null, [
                "role_name" => $roleName,
                "description" => $description,
            ]);
            $message = "Role created. Assign its permissions below.";
        } elseif (isset($_POST["save_permissions"])) {
            $roleStmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ? LIMIT 1");
            $roleStmt->execute([$selectedRoleId]);
            $selectedRole = $roleStmt->fetch();
            if (!$selectedRole) {
                throw new RuntimeException("Select a valid role.");
            }
            if (strtoupper($selectedRole["role_name"]) === "SUPER ADMIN") {
                throw new RuntimeException("The SUPER ADMIN role always has full access and cannot be changed here.");
            }

            $submittedIds = $_POST["permission_ids"] ?? [];
            if (!is_array($submittedIds)) {
                throw new RuntimeException("Invalid permission selection.");
            }
            $permissionIds = array_values(array_unique(array_filter(array_map("intval", $submittedIds), function ($id) {
                return $id > 0;
            })));
            $knownIds = array_map("intval", array_column($allPermissions, "id"));
            if (array_diff($permissionIds, $knownIds)) {
                throw new RuntimeException("One or more selected permissions are invalid.");
            }
            if (strtoupper($selectedRole["role_name"]) === "EMPLOYEE") {
                $allowedIds = [];
                foreach ($allPermissions as $permission) {
                    if (in_array($permission["action"], $employeePermissionScope[$permission["module"]] ?? [], true)) {
                        $allowedIds[] = (int) $permission["id"];
                    }
                }
                if (array_diff($permissionIds, $allowedIds)) {
                    throw new RuntimeException("Employee roles can only receive Employee portal permissions.");
                }
            }

            $oldStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ? ORDER BY permission_id");
            $oldStmt->execute([$selectedRoleId]);
            $oldIds = array_map("intval", $oldStmt->fetchAll(PDO::FETCH_COLUMN));

            $pdo->beginTransaction();
            $delete = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $delete->execute([$selectedRoleId]);
            $insert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permissionId) {
                $insert->execute([$selectedRoleId, $permissionId]);
            }
            $pdo->commit();

            logAudit("ROLE PERMISSIONS", "UPDATE", "ROLE", $selectedRoleId, [
                "permission_ids" => $oldIds,
            ], [
                "permission_ids" => $permissionIds,
            ]);
            $message = "Permissions saved for " . $selectedRole["role_name"] . ".";
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $exception instanceof PDOException
            ? "Unable to save. The role name may already be in use."
            : $exception->getMessage();
        $messageType = "error";
    }
}

$roles = $pdo->query("SELECT id, role_name, description FROM roles ORDER BY id ASC")->fetchAll();
if (!$selectedRoleId && $roles) {
    foreach ($roles as $role) {
        if (strtoupper($role["role_name"]) !== "SUPER ADMIN") {
            $selectedRoleId = (int) $role["id"];
            break;
        }
    }
}
$selectedRole = null;
foreach ($roles as $role) {
    if ((int) $role["id"] === $selectedRoleId) {
        $selectedRole = $role;
        break;
    }
}

$permissions = $allPermissions;
if ($selectedRole && strtoupper($selectedRole["role_name"]) === "EMPLOYEE") {
    $permissions = array_values(array_filter($permissions, function ($permission) use ($employeePermissionScope) {
        return in_array($permission["action"], $employeePermissionScope[$permission["module"]] ?? [], true);
    }));
} else {
    $permissions = array_values(array_filter($permissions, function ($permission) {
        return $permission["module"] !== "role_permissions";
    }));
}
$permissionGroups = [];
foreach ($permissions as $permission) {
    $permissionGroups[$permission["module"]][] = $permission;
}
$assignedIds = [];
if ($selectedRole && strtoupper($selectedRole["role_name"]) !== "SUPER ADMIN") {
    $assignedStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    $assignedStmt->execute([$selectedRoleId]);
    $assignedIds = array_map("intval", $assignedStmt->fetchAll(PDO::FETCH_COLUMN));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Roles &amp; Permissions</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/role-permissions.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Roles &amp; Permissions</h1><p>Control which portal modules each role can access.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="role-permissions-content">
            <?php if ($message !== ""): ?><div class="role-message <?= htmlspecialchars($messageType) ?>" role="status"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="role-permissions-layout">
                <section class="role-panel">
                    <div class="role-panel-heading"><span>ACCESS CONTROL</span><h2>Choose a role</h2></div>
                    <nav class="role-list" aria-label="Roles">
                        <?php foreach ($roles as $role): ?>
                            <a href="role-permissions.php?role_id=<?= (int) $role["id"] ?>" class="<?= (int) $role["id"] === $selectedRoleId ? "active" : "" ?>">
                                <strong><?= htmlspecialchars($role["role_name"]) ?></strong>
                                <small><?= htmlspecialchars($role["description"] ?? "") ?></small>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <form method="post" class="new-role-form">
                        <input type="hidden" name="role_permissions_token" value="<?= htmlspecialchars($_SESSION["role_permissions_token"], ENT_QUOTES) ?>">
                        <h3>Add role</h3>
                        <label>Role name<input type="text" name="role_name" maxlength="50" placeholder="e.g. INVENTORY" required></label>
                        <label>Description<input type="text" name="description" maxlength="255" placeholder="Optional"></label>
                        <button type="submit" name="create_role">Create role</button>
                    </form>
                </section>
                <section class="permission-panel">
                    <?php if (!$selectedRole): ?>
                        <div class="role-empty">No roles are available.</div>
                    <?php elseif (strtoupper($selectedRole["role_name"]) === "SUPER ADMIN"): ?>
                        <div class="permission-heading"><span>FULL ACCESS</span><h2>SUPER ADMIN</h2><p>This built-in role always has access to every admin module.</p></div>
                    <?php else: ?>
                        <form method="post" class="permission-form">
                            <input type="hidden" name="role_permissions_token" value="<?= htmlspecialchars($_SESSION["role_permissions_token"], ENT_QUOTES) ?>">
                            <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                            <div class="permission-heading"><span>ROLE ACCESS</span><h2><?= htmlspecialchars($selectedRole["role_name"]) ?></h2><p>Select the modules and actions this role may use.</p></div>
                            <div class="permission-groups">
                                <?php foreach ($permissionGroups as $module => $modulePermissions): ?>
                                    <fieldset class="permission-group">
                                        <?php $moduleLabel = $module === "dashboard"
                                            ? (strtoupper($selectedRole["role_name"]) === "EMPLOYEE" ? "Employee Dashboard" : "Admin Dashboard")
                                            : ucwords(str_replace("_", " ", $module)); ?>
                                        <legend><?= htmlspecialchars($moduleLabel) ?></legend>
                                        <?php foreach ($modulePermissions as $permission): ?>
                                            <label class="permission-option" title="<?= htmlspecialchars($permission["description"] ?? "") ?>">
                                                <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission["id"] ?>" <?= in_array((int) $permission["id"], $assignedIds, true) ? "checked" : "" ?>>
                                                <span><?= htmlspecialchars(ucwords(str_replace("_", " ", $permission["action"]))) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php endforeach; ?>
                            </div>
                            <div class="permission-actions"><button type="submit" name="save_permissions">Save permissions</button></div>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </main>
</div>
</body>
</html>