<?php

$currentPage = basename($_SERVER["PHP_SELF"]);
$avatarStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
$avatarStmt->execute([$_SESSION["user_id"] ?? 0]);
$profilePicturePath = $avatarStmt->fetchColumn();
$profilePictureFile = is_string($profilePicturePath) && preg_match("#^uploads/profiles/[a-f0-9]{32}\\.(jpg|png|webp)$#", $profilePicturePath)
    ? basename($profilePicturePath)
    : "";

?>

<?php if ($profilePictureFile !== ""): ?>
<style>
.user-info .user-avatar {
    overflow: hidden;
    background-image: url("../uploads/profiles/<?= rawurlencode($profilePictureFile) ?>");
    background-position: center;
    background-size: cover;
    color: transparent;
}
</style>
<?php endif; ?>

<aside class="sidebar">

    <div class="sidebar-logo">

        <h2>
            GREASE <span>N'</span> RESIN
        </h2>

        <small>
            GNR IMS
        </small>

    </div>


    <nav class="sidebar-nav">

        <!-- =================================================
             DASHBOARD
             ================================================== -->

        <?php if (hasPermission("dashboard", "view")): ?>
        <a
            href="dashboard.php"
            class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span>▣</span>
            Dashboard
        </a>
        <?php endif; ?>


        <!-- =================================================
             OPERATIONS
             ================================================== -->

        <?php if (hasPermission("customers", "view") || hasPermission("vehicles", "view") || hasPermission("inquiries", "view") || hasPermission("quotations", "view") || hasPermission("orders", "view") || hasPermission("jobs", "view")): ?>
        <div class="nav-label">
            OPERATIONS
        </div>

        <?php if (hasPermission("customers", "view")): ?>
        <a href="customers.php" class="nav-item <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
            <span>👤</span>
            Customers
        </a>
        <?php endif; ?>

        <?php if (hasPermission("vehicles", "view")): ?>
        <a href="vehicles.php" class="nav-item <?= $currentPage === 'vehicles.php' ? 'active' : '' ?>">
            <span>🏍</span>
            Vehicles
        </a>
        <?php endif; ?>

        <?php if (hasPermission("inquiries", "view")): ?>
        <a href="inquiries.php" class="nav-item <?= $currentPage === 'inquiries.php' ? 'active' : '' ?>">
            <span>📋</span>
            Inquiries
        </a>
        <?php endif; ?>

        <?php if (hasPermission("quotations", "view")): ?>
        <a href="quotations.php" class="nav-item <?= $currentPage === 'quotations.php' ? 'active' : '' ?>">
            <span>💰</span>
            Quotations
        </a>
        <?php endif; ?>

        <?php if (hasPermission("orders", "view")): ?>
        <a href="orders.php" class="nav-item <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <span>🛒</span>
            Orders
        </a>
        <?php endif; ?>

        <?php if (hasPermission("jobs", "view")): ?>
        <a href="jobs.php" class="nav-item <?= $currentPage === 'jobs.php' ? 'active' : '' ?>">
            <span>🔧</span>
            Jobs
        </a>
        <?php endif; ?>
        <?php endif; ?>


        <!-- =================================================
             INVENTORY
             ================================================== -->

        <?php if (hasPermission("inventory", "view") || hasPermission("stock_movement", "view") || hasPermission("inventory_requests", "view")): ?>
        <div class="nav-label">
            INVENTORY
        </div>

        <?php if (hasPermission("inventory", "view")): ?>
        <a href="inventory.php" class="nav-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
            <span>📦</span>
            Inventory
        </a>
        <?php endif; ?>

        <?php if (hasPermission("stock_movement", "view")): ?>
        <a href="stock-movement.php" class="nav-item <?= $currentPage === 'stock-movement.php' ? 'active' : '' ?>">
            <span>↕</span>
            Stock Movement
        </a>
        <?php endif; ?>

        <?php if (hasPermission("inventory_requests", "view")): ?>
        <a href="inventory-requests.php" class="nav-item <?= $currentPage === 'inventory-requests.php' ? 'active' : '' ?>">
            <span>📝</span>
            Inventory Requests
        </a>
        <?php endif; ?>
        <?php endif; ?>


        <!-- =================================================
             PEOPLE
             ================================================== -->

        <?php if (hasPermission("employees", "view") || hasPermission("attendance", "view") || hasPermission("payroll", "view")): ?>
        <div class="nav-label">
            PEOPLE
        </div>

        <?php if (hasPermission("employees", "view")): ?>
        <a
            href="employees.php"
            class="nav-item <?= $currentPage === 'employees.php' ? 'active' : '' ?>"
        >
            <span>👥</span>
            Employees
        </a>
        <?php endif; ?>

        <?php if (hasPermission("attendance", "view")): ?>
        <a href="attendance.php" class="nav-item <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <span>🕒</span>
            Attendance
        </a>
        <?php endif; ?>

        <?php if (hasPermission("payroll", "view")): ?>
        <a href="payroll.php" class="nav-item <?= $currentPage === 'payroll.php' ? 'active' : '' ?>">
            <span>💵</span>
            Payroll
        </a>
        <?php endif; ?>
        <?php endif; ?>


        <!-- =================================================
             FINANCE
             ================================================== -->

        <?php if (hasPermission("quality_control", "view") || hasPermission("invoices", "view") || hasPermission("payments", "view") || hasPermission("releases", "view")): ?>
        <div class="nav-label">
            FINANCE
        </div>

        <?php if (hasPermission("quality_control", "view")): ?>
        <a href="quality-control.php" class="nav-item <?= $currentPage === 'quality-control.php' ? 'active' : '' ?>">
            <span>✓</span>
            Quality Control
        </a>
        <?php endif; ?>

        <?php if (hasPermission("invoices", "view")): ?>
        <a href="invoices.php" class="nav-item <?= $currentPage === 'invoices.php' ? 'active' : '' ?>">
            <span>🧾</span>
            Invoices
        </a>
        <?php endif; ?>

        <?php if (hasPermission("payments", "view")): ?>
        <a href="payments.php" class="nav-item <?= $currentPage === 'payments.php' ? 'active' : '' ?>">
            <span>₱</span>
            Payments
        </a>
        <?php endif; ?>

        <?php if (hasPermission("releases", "view")): ?>
        <a href="releases.php" class="nav-item <?= $currentPage === 'releases.php' ? 'active' : '' ?>">
            <span>🚚</span>
            Releases
        </a>
        <?php endif; ?>
        <?php endif; ?>


        <!-- =================================================
             SYSTEM
             ================================================== -->

        <?php if (hasPermission("notifications", "view") || hasPermission("settings", "view") || hasPermission("archive", "view") || hasPermission("audit_logs", "view") || hasRole("SUPER ADMIN")): ?>
        <div class="nav-label">
            SYSTEM
        </div>

        <?php if (hasPermission("notifications", "view")): ?>
        <a
            href="notifications.php"
            class="nav-item <?= $currentPage === 'notifications.php' ? 'active' : '' ?>"
        >
            <span>🔔</span>
            Notifications
        </a>
        <?php endif; ?>

        <?php if (hasPermission("settings", "view")): ?>
        <a href="settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <span>⚙</span>
            Settings
        </a>
        <?php endif; ?>

        <?php if (hasRole("SUPER ADMIN")): ?>
        <a href="create-admin.php" class="nav-item <?= $currentPage === 'create-admin.php' ? 'active' : '' ?>">
            <span>👤</span>
            Add Admin
        </a>
        <a href="role-permissions.php" class="nav-item <?= $currentPage === 'role-permissions.php' ? 'active' : '' ?>">
            <span>🔐</span>
            Roles &amp; Permissions
        </a>
        <?php endif; ?>

        <?php if (hasPermission("archive", "view")): ?>
        <a
            href="archive.php"
            class="nav-item <?= $currentPage === 'archive.php' ? 'active' : '' ?>"
        >
            <span>🗃</span>
            Archive
        </a>
        <?php endif; ?>

        <?php if (hasPermission("audit_logs", "view")): ?>
        <a
            href="audit-logs.php"
            class="nav-item <?= $currentPage === 'audit-logs.php' ? 'active' : '' ?>"
        >
            <span>📜</span>
            Audit Logs
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <a href="account-settings.php" class="nav-item <?= $currentPage === 'account-settings.php' ? 'active' : '' ?>">
            <span>👤</span>
            Account Settings
        </a>

    </nav>


    <!-- =====================================================
         LOGOUT
         ====================================================== -->

    <div class="sidebar-bottom">

        <a href="logout.php" class="logout-btn">
            <span>↪</span>
            Logout
        </a>

    </div>

</aside>