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


function requireRole($roleName)
{
    requireLogin();
    global $pdo;

    $stmt = $pdo->prepare("SELECT r.role_name, u.status FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch();

    if (!$user || strtoupper($user["status"]) !== "ACTIVE") {
        session_unset();
        session_destroy();
        header("Location: /grease-n-resin/admin/index.php");
        exit;
    }
    if (strtoupper($user["role_name"]) !== strtoupper($roleName)) {
        http_response_code(403);
        die("Access Denied.");
    }
}


function currentUserPermissions()
{
    global $pdo;
    static $cachedUserId = null;
    static $permissions = null;

    $userId = (int) ($_SESSION["user_id"] ?? 0);
    if (!$userId) {
        return [];
    }
    if ($cachedUserId === $userId && $permissions !== null) {
        return $permissions;
    }

    $cachedUserId = $userId;
    $permissions = [];
    $userStmt = $pdo->prepare("SELECT u.status, r.role_name, u.role_id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user || strtoupper($user["status"]) !== "ACTIVE") {
        return $permissions;
    }
    if (strtoupper($user["role_name"]) === "SUPER ADMIN") {
        $permissions["*"]["*"] = true;
        return $permissions;
    }

    $permissionStmt = $pdo->prepare("SELECT p.module, p.action FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?");
    $permissionStmt->execute([$user["role_id"]]);
    foreach ($permissionStmt->fetchAll() as $permission) {
        $permissions[$permission["module"]][$permission["action"]] = true;
    }
    return $permissions;
}


/* =========================================================
   PERMISSION CHECK
   ========================================================== */

function hasPermission($module, $action)
{
    $permissions = currentUserPermissions();
    return isset($permissions["*"]["*"]) || !empty($permissions[$module][$action]);
}


function hasAnyPermission()
{
    return currentUserPermissions() !== [];
}


function requireAdminPagePermission()
{
    $moduleByPage = [
        "dashboard.php" => "dashboard",
        "customers.php" => "customers",
        "vehicles.php" => "vehicles",
        "inquiries.php" => "inquiries",
        "quotations.php" => "quotations",
        "orders.php" => "orders",
        "jobs.php" => "jobs",
        "job-details.php" => "jobs",
        "inventory.php" => "inventory",
        "stock-movement.php" => "stock_movement",
        "inventory-requests.php" => "inventory_requests",
        "employees.php" => "employees",
        "employee-record.php" => "employees",
        "create-user.php" => "employees",
        "attendance.php" => "attendance",
        "time-in.php" => "attendance",
        "time-out.php" => "attendance",
        "payroll.php" => "payroll",
        "quality-control.php" => "quality_control",
        "invoices.php" => "invoices",
        "payments.php" => "payments",
        "releases.php" => "releases",
        "notifications.php" => "notifications",
        "settings.php" => "settings",
        "archive.php" => "archive",
        "audit-logs.php" => "audit_logs",
        "role-permissions.php" => "role_permissions",
        "create-admin.php" => "role_permissions",
    ];

    $page = basename($_SERVER["SCRIPT_NAME"] ?? "");
    if (!isset($moduleByPage[$page])) {
        return;
    }

    $action = "view";
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        switch ($page) {
            case "archive.php":
                $action = isset($_POST["delete_archive_id"]) ? "delete_permanently" : (isset($_POST["restore_archive_id"]) ? "restore" : "view");
                break;
            case "customers.php":
                $action = isset($_POST["archive_customer_id"]) ? "delete" : (isset($_POST["customer_submit"]) ? (empty($_POST["customer_id"]) ? "create" : "edit") : "view");
                break;
            case "vehicles.php":
                $action = isset($_POST["archive_vehicle_id"]) ? "delete" : (isset($_POST["vehicle_submit"]) ? (empty($_POST["vehicle_id"]) ? "create" : "edit") : "view");
                break;
            case "inquiries.php":
                $action = isset($_POST["archive_inquiry_id"]) ? "delete" : (isset($_POST["create_inquiry"]) ? "create" : "view");
                break;
            case "create-user.php":
                $action = "create";
                break;
            case "employees.php":
                $action = ($_POST["form_action"] ?? "") === "add_employee"
                    ? "create"
                    : (($_POST["employee_action"] ?? "") === "archive" ? "delete" : (isset($_POST["employee_action"]) ? "edit" : "view"));
                break;
            case "employee-record.php":
                $action = "edit";
                break;
            case "inventory-requests.php":
                $action = isset($_POST["request_status"]) ? "approve" : "view";
                break;
            case "job-details.php":
                $action = isset($_POST["create_job_detail"])
                    ? "create"
                    : (isset($_POST["update_job_status"]) ? "approve" : (isset($_POST["update_job_detail"]) ? "edit" : "view"));
                break;
            case "notifications.php":
                $action = "view";
                break;
            case "orders.php":
                $action = isset($_POST["convert_quotation"]) ? "create" : "view";
                break;
            case "quotations.php":
                $action = isset($_POST["create_quotation"]) ? "create" : "view";
                break;
            case "time-in.php":
                $attendanceAction = $_POST["attendance_action"] ?? "";
                $action = $attendanceAction === "time_in"
                    ? "time_in"
                    : ($attendanceAction === "time_out" ? "time_out" : (isset($_POST["attendance_action"]) ? "edit" : "view"));
                break;
            case "role-permissions.php":
            case "create-admin.php":
                $action = "edit";
                break;
        }
    }

    requirePermission($moduleByPage[$page], $action);
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

    if (!in_array($role, $adminRoles, true) && !hasAnyPermission()) {
        http_response_code(403);
        die("Access Denied.");
    }
}