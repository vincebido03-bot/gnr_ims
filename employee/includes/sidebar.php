<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<aside class="employee-sidebar">

    <!-- BRAND -->
    <div class="employee-sidebar-brand">

        <div class="employee-brand-mark">
            GNR
        </div>

        <div class="employee-brand-text">
            <strong>GREASE N' RESIN</strong>
            <span>GNR IMS</span>
        </div>

    </div>


    <!-- NAVIGATION -->
    <nav class="employee-nav">


        <!-- MY WORK -->
        <?php if (hasPermission("dashboard", "view") || hasPermission("attendance", "view") || hasPermission("payroll", "view") || hasPermission("employees", "view") || hasPermission("employees", "edit") || hasPermission("inventory_requests", "view") || hasPermission("notifications", "view")): ?>
        <div class="employee-nav-section">

            <div class="employee-nav-label">
                MY WORK
            </div>

                <?php if (hasPermission("dashboard", "view")): ?>
                <a href="dashboard.php"
                    class="employee-nav-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">▣</span>
                <span>Dashboard</span>

            </a>
                <?php endif; ?>


                <?php if (hasPermission("attendance", "view")): ?><a href="attendance.php"
                    class="employee-nav-item <?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">🕒</span>
                <span>Attendance</span>

            </a>
                <?php endif; ?>


                <?php if (hasPermission("payroll", "view")): ?><a href="payroll.php"
                    class="employee-nav-item <?php echo $currentPage === 'payroll.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">💵</span>
                <span>My Payslips</span>

            </a>
                <?php endif; ?>


                <?php if (hasPermission("employees", "view")): ?><a href="profile.php"
                    class="employee-nav-item <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">👤</span>
                <span>My Profile</span>

            </a>
                <?php endif; ?>

            <?php if (hasPermission("employees", "edit")): ?>
            <a href="account-settings.php"
                class="employee-nav-item <?php echo $currentPage === 'account-settings.php' ? 'active' : ''; ?>">
                <span class="employee-nav-icon">⚙</span>
                <span>Account Settings</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission("inventory_requests", "view")): ?>
            <a href="inventory-request.php"
                class="employee-nav-item <?php echo $currentPage === 'inventory-request.php' ? 'active' : ''; ?>">
                <span class="employee-nav-icon">📦</span>
                <span>Inventory Request</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission("notifications", "view")): ?>
            <a href="notifications.php" class="employee-nav-item <?php echo $currentPage === 'notifications.php' ? 'active' : ''; ?>">
                <span class="employee-nav-icon">🔔</span>
                <span>Notifications</span>
            </a>
            <?php endif; ?>

        </div>
        <?php endif; ?>


    </nav>


    <!-- SIDEBAR FOOTER -->
    <div class="employee-sidebar-footer">

        <div class="employee-nav-label employee-nav-label-account">
            ACCOUNT
        </div>

        <a href="../admin/logout.php" class="employee-logout">

            <span class="employee-nav-icon">↪</span>

            <span>Logout</span>

        </a>

    </div>

</aside>