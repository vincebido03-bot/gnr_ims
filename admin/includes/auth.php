<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/functions.php";


/* =========================================================
   AUTH CHECK
   ========================================================== */

function requireLogin()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /grease-n-resin/admin/index.php");
        exit;
    }
}


/* =========================================================
   CURRENT USER
   ========================================================== */

function currentUser()
{
    return $_SESSION['user'] ?? null;
}


/* =========================================================
   ROLE CHECK
   ========================================================== */

function hasRole($roleName)
{
    if (!isset($_SESSION['role_name'])) {
        return false;
    }

    return strtoupper($_SESSION['role_name']) === strtoupper($roleName);
}


/* =========================================================
   PERMISSION CHECK
   ========================================================== */

function hasPermission($module, $action)
{
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $sql = "
        SELECT COUNT(*)
        FROM users u
        INNER JOIN role_permissions rp
            ON u.role_id = rp.role_id
        INNER JOIN permissions p
            ON rp.permission_id = p.id
        WHERE u.id = ?
          AND p.module = ?
          AND p.action = ?
          AND u.status = 'ACTIVE'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION['user_id'],
        $module,
        $action
    ]);

    return $stmt->fetchColumn() > 0;
}


/* =========================================================
   REQUIRE PERMISSION
   ========================================================== */

function requirePermission($module, $action)
{
    requireLogin();

    if (!hasPermission($module, $action)) {
        http_response_code(403);
        die("Access Denied.");
    }
}


/* =========================================================
   ADMIN PORTAL ACCESS
   ========================================================== */

function requireAdminAccess()
{
    requireLogin();

    $role = strtoupper(trim($_SESSION['role_name'] ?? ''));

    if ($role === 'EMPLOYEE') {
        header("Location: /grease-n-resin/employee/dashboard.php");
        exit;
    }

    $adminRoles = [
        'SUPER ADMIN',
        'OWNER',
        'HR',
        'FRONTDESK'
    ];

    if (!in_array($role, $adminRoles, true)) {
        http_response_code(403);
        die("Access Denied.");
    }
}