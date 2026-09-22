<?php

require_once "includes/header.php";

$roleCount = (int) $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUserCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'")->fetchColumn();
$permissionCount = (int) $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Settings</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/settings.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Settings</h1><p>Review system configuration and access control.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="settings-content">
            <div class="settings-heading"><span>SYSTEM CONTROL</span><h2>System Settings</h2><p>Core configuration and access summaries for GNR IMS.</p></div>
            <div class="settings-grid">
                <article class="settings-card">
                    <div class="settings-card-heading"><span>APPLICATION</span><h3>System Information</h3></div>
                    <dl class="settings-list">
                        <div><dt>System name</dt><dd>GNR IMS</dd></div>
                        <div><dt>Company</dt><dd>Grease N' Resin</dd></div>
                        <div><dt>Timezone</dt><dd><?= htmlspecialchars(date_default_timezone_get()) ?></dd></div>
                        <div><dt>Current date</dt><dd><?= htmlspecialchars(date("F d, Y h:i A")) ?></dd></div>
                        <div><dt>Database</dt><dd>gnr_ims</dd></div>
                    </dl>
                </article>
                <article class="settings-card">
                    <div class="settings-card-heading"><span>YOUR ACCOUNT</span><h3>Signed-in Account</h3></div>
                    <dl class="settings-list">
                        <div><dt>Username</dt><dd><?= htmlspecialchars($user["username"]) ?></dd></div>
                        <div><dt>Role</dt><dd><?= htmlspecialchars($user["role_name"]) ?></dd></div>
                        <div><dt>Access</dt><dd><span class="account-badge">Admin portal</span></dd></div>
                    </dl>
                </article>
                <article class="settings-card settings-card-wide">
                    <div class="settings-card-heading"><span>ACCESS CONTROL</span><h3>Roles and Permissions</h3></div>
                    <div class="settings-stats">
                        <div><strong><?= number_format($roleCount) ?></strong><span>Roles</span></div>
                        <div><strong><?= number_format($userCount) ?></strong><span>Total users</span></div>
                        <div><strong><?= number_format($activeUserCount) ?></strong><span>Active users</span></div>
                        <div><strong><?= number_format($permissionCount) ?></strong><span>Permissions</span></div>
                    </div>
                    <div class="settings-actions"><a href="employees.php">Manage employees</a><a href="create-user.php">Manage user accounts</a></div>
                </article>
            </div>
        </section>
    </main>
</div>
</body>
</html>