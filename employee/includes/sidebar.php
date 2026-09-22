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
        <div class="employee-nav-section">

            <div class="employee-nav-label">
                MY WORK
            </div>

                <a href="dashboard.php"
                    class="employee-nav-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">▣</span>
                <span>Dashboard</span>

            </a>


                <a href="attendance.php"
                    class="employee-nav-item <?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">🕒</span>
                <span>Attendance</span>

            </a>


                <a href="payroll.php"
                    class="employee-nav-item <?php echo $currentPage === 'payroll.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">💵</span>
                <span>My Payslips</span>

            </a>


                <a href="profile.php"
                    class="employee-nav-item <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">

                <span class="employee-nav-icon">👤</span>
                <span>My Profile</span>

            </a>

        </div>


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