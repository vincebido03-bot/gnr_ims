<?php

require_once "includes/header.php";
requireRole("SUPER ADMIN");

if (empty($_SESSION["create_admin_token"])) {
    $_SESSION["create_admin_token"] = bin2hex(random_bytes(32));
}

$error = "";
$displayName = "";
$roleId = 0;
$username = "";
$email = "";

if (isset($_SESSION["create_admin_message"])) {
    $success = $_SESSION["create_admin_message"];
    unset($_SESSION["create_admin_message"]);
} else {
    $success = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $displayName = trim((string) ($_POST["display_name"] ?? ""));
    $roleId = (int) ($_POST["role_id"] ?? 0);
    $username = trim((string) ($_POST["username"] ?? ""));
    $email = trim((string) ($_POST["email"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");
    $token = $_POST["create_admin_token"] ?? "";

    if (!is_string($token) || !hash_equals($_SESSION["create_admin_token"], $token)) {
        $error = "This form has expired. Reload the page and try again.";
    } elseif ($displayName === "" || $roleId <= 0 || $username === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $error = "Complete every required field.";
    } elseif (strlen($displayName) > 200 || strlen($username) > 100) {
        $error = "Name or username is too long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = "Enter a valid email address up to 150 characters.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Password and confirmation do not match.";
    } else {
        $roleStmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id = ? LIMIT 1");
        $roleStmt->execute([$roleId]);
        $role = $roleStmt->fetch();

        if (!$role || in_array(strtoupper($role["role_name"]), ["SUPER ADMIN", "EMPLOYEE"], true)) {
            $error = "Select an admin role other than SUPER ADMIN.";
        } else {
            $duplicateStmt = $pdo->prepare("SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
            $duplicateStmt->execute([$username, $email]);
            $duplicate = $duplicateStmt->fetch();

            if ($duplicate && $duplicate["username"] === $username) {
                $error = "Username is already taken.";
            } elseif ($duplicate) {
                $error = "Email is already registered.";
            } else {
                try {
                    $pdo->beginTransaction();
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    if ($passwordHash === false) {
                        throw new RuntimeException("Unable to secure the password.");
                    }

                    $insert = $pdo->prepare("INSERT INTO users (employee_id, display_name, role_id, username, email, password, status) VALUES (NULL, ?, ?, ?, ?, ?, 'ACTIVE')");
                    $insert->execute([$displayName, $roleId, $username, $email, $passwordHash]);
                    $newUserId = (int) $pdo->lastInsertId();
                    logAudit("USER MANAGEMENT", "CREATE_ADMIN", "USER", $newUserId, null, [
                        "display_name" => $displayName,
                        "role_id" => $roleId,
                        "role" => $role["role_name"],
                        "username" => $username,
                        "email" => $email,
                        "employee_id" => null,
                        "status" => "ACTIVE",
                    ]);
                    $pdo->commit();
                    $_SESSION["create_admin_message"] = "Admin account created for " . $displayName . ".";
                    header("Location: create-admin.php");
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = $exception instanceof PDOException
                        ? "Unable to create the account. Check that the username and email are unique."
                        : $exception->getMessage();
                }
            }
        }
    }
}

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE UPPER(role_name) NOT IN ('SUPER ADMIN', 'EMPLOYEE') ORDER BY id ASC")->fetchAll();
$adminAccounts = $pdo->query("SELECT u.display_name, u.username, u.email, u.status, r.role_name FROM users u INNER JOIN roles r ON r.id = u.role_id LEFT JOIN employees e ON e.id = u.employee_id WHERE UPPER(r.role_name) <> 'EMPLOYEE' ORDER BY r.role_name, COALESCE(u.display_name, CONCAT_WS(' ', e.first_name, e.last_name), u.username)")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Add Admin</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/create-user.css">
    <link rel="stylesheet" href="css/create-admin.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Admin Accounts</h1><p>Create portal accounts for owners and admin staff.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="dashboard-content admin-account-content">
            <div class="form-page">
                <?php if ($error !== ""): ?><div class="form-message error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success !== ""): ?><div class="form-message success" role="status"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <section class="form-card">
                    <div class="form-card-header"><span>ADMIN ACCESS</span><h2>Add Admin</h2></div>
                    <form method="post" class="admin-account-form">
                        <input type="hidden" name="create_admin_token" value="<?= htmlspecialchars($_SESSION["create_admin_token"], ENT_QUOTES) ?>">
                        <div class="admin-account-form-grid">
                            <div class="form-group">
                                <label for="display_name">Full name</label>
                                <input id="display_name" name="display_name" type="text" maxlength="200" value="<?= htmlspecialchars($displayName) ?>" autocomplete="name" required>
                            </div>
                            <div class="form-group">
                                <label for="role_id">Admin role</label>
                                <select id="role_id" name="role_id" required>
                                    <option value="">Select role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= (int) $role["id"] ?>" <?= $roleId === (int) $role["id"] ? "selected" : "" ?>><?= htmlspecialchars($role["role_name"]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input id="username" name="username" type="text" maxlength="100" value="<?= htmlspecialchars($username) ?>" autocomplete="username" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" maxlength="150" value="<?= htmlspecialchars($email) ?>" autocomplete="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                                <small>Minimum 8 characters.</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm password</label>
                                <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>
                        <div class="form-actions"><button class="save-btn" type="submit">Create Admin Account</button></div>
                    </form>
                </section>
                <section class="admin-account-list">
                    <div class="admin-account-list-heading"><span>ACCESS ROSTER</span><h2>Admin Accounts</h2></div>
                    <div class="admin-account-table-wrap">
                        <table class="admin-account-table">
                            <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($adminAccounts as $account): ?>
                                <tr>
                                    <td><?= htmlspecialchars($account["display_name"] ?: $account["username"]) ?></td>
                                    <td><?= htmlspecialchars($account["username"]) ?></td>
                                    <td><?= htmlspecialchars($account["email"] ?? "-") ?></td>
                                    <td><?= htmlspecialchars($account["role_name"]) ?></td>
                                    <td><?= htmlspecialchars($account["status"]) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>
    </main>
</div>
</body>
</html>