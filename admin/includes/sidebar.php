<?php

$currentPage = basename($_SERVER["PHP_SELF"]);

?>

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

        <a
            href="dashboard.php"
            class="nav-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span>▣</span>
            Dashboard
        </a>


        <!-- =================================================
             OPERATIONS
             ================================================== -->

        <div class="nav-label">
            OPERATIONS
        </div>

        <a href="customers.php" class="nav-item <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
            <span>👤</span>
            Customers
        </a>

        <a href="vehicles.php" class="nav-item <?= $currentPage === 'vehicles.php' ? 'active' : '' ?>">
            <span>🏍</span>
            Vehicles
        </a>

        <a href="inquiries.php" class="nav-item <?= $currentPage === 'inquiries.php' ? 'active' : '' ?>">
            <span>📋</span>
            Inquiries
        </a>

        <a href="quotations.php" class="nav-item <?= $currentPage === 'quotations.php' ? 'active' : '' ?>">
            <span>💰</span>
            Quotations
        </a>

        <a href="orders.php" class="nav-item <?= $currentPage === 'orders.php' ? 'active' : '' ?>">
            <span>🛒</span>
            Orders
        </a>

        <a href="jobs.php" class="nav-item <?= $currentPage === 'jobs.php' ? 'active' : '' ?>">
            <span>🔧</span>
            Jobs
        </a>


        <!-- =================================================
             INVENTORY
             ================================================== -->

        <div class="nav-label">
            INVENTORY
        </div>

        <a href="inventory.php" class="nav-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
            <span>📦</span>
            Inventory
        </a>

        <a href="stock-movement.php" class="nav-item <?= $currentPage === 'stock-movement.php' ? 'active' : '' ?>">
            <span>↕</span>
            Stock Movement
        </a>


        <!-- =================================================
             PEOPLE
             ================================================== -->

        <div class="nav-label">
            PEOPLE
        </div>

        <a
            href="employees.php"
            class="nav-item <?= $currentPage === 'employees.php' ? 'active' : '' ?>"
        >
            <span>👥</span>
            Employees
        </a>

        <a href="attendance.php" class="nav-item <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <span>🕒</span>
            Attendance
        </a>

        <a href="payroll.php" class="nav-item <?= $currentPage === 'payroll.php' ? 'active' : '' ?>">
            <span>💵</span>
            Payroll
        </a>


        <!-- =================================================
             FINANCE
             ================================================== -->

        <div class="nav-label">
            FINANCE
        </div>

        <a href="quality-control.php" class="nav-item <?= $currentPage === 'quality-control.php' ? 'active' : '' ?>">
            <span>✓</span>
            Quality Control
        </a>

        <a href="invoices.php" class="nav-item <?= $currentPage === 'invoices.php' ? 'active' : '' ?>">
            <span>🧾</span>
            Invoices
        </a>

        <a href="payments.php" class="nav-item <?= $currentPage === 'payments.php' ? 'active' : '' ?>">
            <span>₱</span>
            Payments
        </a>

        <a href="releases.php" class="nav-item <?= $currentPage === 'releases.php' ? 'active' : '' ?>">
            <span>🚚</span>
            Releases
        </a>


        <!-- =================================================
             SYSTEM
             ================================================== -->

        <div class="nav-label">
            SYSTEM
        </div>

        <a
            href="notifications.php"
            class="nav-item <?= $currentPage === 'notifications.php' ? 'active' : '' ?>"
        >
            <span>🔔</span>
            Notifications
        </a>

        <a href="settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <span>⚙</span>
            Settings
        </a>

        <a
            href="archive.php"
            class="nav-item <?= $currentPage === 'archive.php' ? 'active' : '' ?>"
        >
            <span>🗃</span>
            Archive
        </a>

        <a
            href="audit-logs.php"
            class="nav-item <?= $currentPage === 'audit-logs.php' ? 'active' : '' ?>"
        >
            <span>📜</span>
            Audit Logs
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