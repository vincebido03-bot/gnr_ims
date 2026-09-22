
<?php

require_once "includes/header.php";


/* =========================================================
   DASHBOARD STATISTICS
   ========================================================== */

$customerCount = $pdo
    ->query("SELECT COUNT(*) FROM customers")
    ->fetchColumn();


$vehicleCount = $pdo
    ->query("SELECT COUNT(*) FROM vehicles")
    ->fetchColumn();


$activeJobsCount = $pdo
    ->query("
        SELECT COUNT(*)
        FROM jobs
        WHERE status IN ('SCHEDULED', 'ASSIGNED', 'IN_PROGRESS', 'PAUSED', 'FOR_QC')
    ")
    ->fetchColumn();


$lowStockCount = $pdo
    ->query("
        SELECT COUNT(*)
        FROM inventory_items
        WHERE status = 'ACTIVE'
          AND current_stock <= reorder_level
    ")
    ->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>GNR IMS - Dashboard</title>

    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">

</head>

<body>

<div class="admin-layout">


    <!-- =====================================================
         SIDEBAR
         ====================================================== -->

    <?php require_once "includes/sidebar.php"; ?>


    <!-- =====================================================
         MAIN CONTENT
         ====================================================== -->

    <main class="main-content">


        <!-- =================================================
             TOPBAR
             ================================================== -->

        <header class="topbar">

            <div>

                <h1>
                    Dashboard
                </h1>

                <p>
                    Welcome back to GNR IMS.
                </p>

            </div>


            <div class="user-info">

                <div class="user-avatar">

                    <?= strtoupper(
                        substr($user["username"], 0, 1)
                    ) ?>

                </div>

                <div>

                    <strong>
                        <?= htmlspecialchars($user["username"]) ?>
                    </strong>

                    <small>
                        <?= htmlspecialchars($user["role_name"]) ?>
                    </small>

                </div>

            </div>

        </header>


        <!-- =================================================
             DASHBOARD CONTENT
             ================================================== -->

        <section class="dashboard-content">


            <!-- =================================================
                 STATISTICS
                 ================================================== -->

            <div class="dashboard-grid">


                <!-- CUSTOMERS -->

                <div class="stat-card">

                    <span class="stat-icon">
                        👤
                    </span>

                    <div>

                        <p>
                            Customers
                        </p>

                        <h2>
                            <?= number_format($customerCount) ?>
                        </h2>

                    </div>

                </div>


                <!-- VEHICLES -->

                <div class="stat-card">

                    <span class="stat-icon">
                        🏍
                    </span>

                    <div>

                        <p>
                            Vehicles
                        </p>

                        <h2>
                            <?= number_format($vehicleCount) ?>
                        </h2>

                    </div>

                </div>


                <!-- ACTIVE JOBS -->

                <div class="stat-card">

                    <span class="stat-icon">
                        🔧
                    </span>

                    <div>

                        <p>
                            Active Jobs
                        </p>

                        <h2>
                            <?= number_format($activeJobsCount) ?>
                        </h2>

                    </div>

                </div>


                <!-- LOW STOCK -->

                <div class="stat-card">

                    <span class="stat-icon">
                        📦
                    </span>

                    <div>

                        <p>
                            Low Stock
                        </p>

                        <h2>
                            <?= number_format($lowStockCount) ?>
                        </h2>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 DASHBOARD PANELS
                 ================================================== -->

            <div class="dashboard-panels">


                <!-- =================================================
                     RECENT ACTIVITY
                     ================================================== -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            Recent Activity
                        </h3>

                        <span>
                            LIVE
                        </span>

                    </div>

                    <div class="empty-state">

                        <div>
                            ◌
                        </div>

                        <p>
                            No recent activity yet.
                        </p>

                    </div>

                </div>


                <!-- =================================================
                     NOTIFICATIONS
                     ================================================== -->

                <div class="panel">

                    <div class="panel-header">

                        <h3>
                            Notifications
                        </h3>

                        <span>
                            0
                        </span>

                    </div>

                    <div class="empty-state">

                        <div>
                            🔔
                        </div>

                        <p>
                            No new notifications.
                        </p>

                    </div>

                </div>


            </div>

        </section>

    </main>

</div>


<script src="js/admin.js"></script>

</body>

</html>

