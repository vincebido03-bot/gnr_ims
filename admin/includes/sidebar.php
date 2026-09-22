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

        <a href="#" class="nav-item">
            <span>👤</span>
            Customers
        </a>

        <a href="#" class="nav-item">
            <span>🏍</span>
            Vehicles
        </a>

        <a href="#" class="nav-item">
            <span>📋</span>
            Inquiries
        </a>

        <a href="#" class="nav-item">
            <span>💰</span>
            Quotations
        </a>

        <a href="#" class="nav-item">
            <span>🛒</span>
            Orders
        </a>

        <a href="#" class="nav-item">
            <span>🔧</span>
            Jobs
        </a>


        <!-- =================================================
             INVENTORY
             ================================================== -->

        <div class="nav-label">
            INVENTORY
        </div>

        <a href="#" class="nav-item">
            <span>📦</span>
            Inventory
        </a>

        <a href="#" class="nav-item">
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

        <a href="#" class="nav-item">
            <span>🕒</span>
            Attendance
        </a>

        <a href="#" class="nav-item">
            <span>💵</span>
            Payroll
        </a>


        <!-- =================================================
             FINANCE
             ================================================== -->

        <div class="nav-label">
            FINANCE
        </div>

        <a href="#" class="nav-item">
            <span>✓</span>
            Quality Control
        </a>

        <a href="#" class="nav-item">
            <span>🧾</span>
            Invoices
        </a>

        <a href="#" class="nav-item">
            <span>₱</span>
            Payments
        </a>

        <a href="#" class="nav-item">
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

        <a href="#" class="nav-item">
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