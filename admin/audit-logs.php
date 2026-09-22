
<?php

require_once "includes/header.php";

requirePermission("audit_logs", "view");


/* =========================================================
   GET AUDIT LOGS
   ========================================================== */

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.user_id,
        a.module,
        a.action,
        a.reference_type,
        a.reference_id,
        a.old_values,
        a.new_values,
        a.ip_address,
        a.created_at,
        u.username,
        r.role_name
    FROM audit_logs a

    LEFT JOIN users u
        ON a.user_id = u.id

    LEFT JOIN roles r
        ON u.role_id = r.id

    ORDER BY a.created_at DESC
");

$stmt->execute();

$auditLogs = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>GNR IMS - Audit Logs</title>

    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/audit-logs.css">

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
                    Audit Logs
                </h1>

                <p>
                    System activity and user action history.
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
             AUDIT LOG CONTENT
             ================================================== -->

        <section class="dashboard-content">


            <div class="panel audit-panel">

                <div class="panel-header">

                    <h3>
                        Activity History
                    </h3>

                    <span>
                        <?= count($auditLogs) ?> RECORDS
                    </span>

                </div>


                <?php if (empty($auditLogs)): ?>

                    <div class="empty-state">

                        <div>
                            ◌
                        </div>

                        <p>
                            No audit logs yet.
                        </p>

                    </div>

                <?php else: ?>


                    <div class="audit-table-wrapper">

                        <table class="audit-table">

                            <thead>

                                <tr>

                                    <th>
                                        Date & Time
                                    </th>

                                    <th>
                                        User
                                    </th>

                                    <th>
                                        Role
                                    </th>

                                    <th>
                                        Module
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                    <th>
                                        Reference
                                    </th>

                                    <th>
                                        IP Address
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($auditLogs as $log): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars(
                                                date(
                                                    "M d, Y h:i A",
                                                    strtotime(
                                                        $log["created_at"]
                                                    )
                                                )
                                            ) ?>
                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $log["username"]
                                                ?? "System"
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $log["role_name"]
                                                ?? "-"
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $log["module"]
                                            ) ?>

                                        </td>


                                        <td>

                                            <span class="audit-action">

                                                <?= htmlspecialchars(
                                                    $log["action"]
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?php if (
                                                $log["reference_type"] !== null
                                            ): ?>

                                                <?= htmlspecialchars(
                                                    $log["reference_type"]
                                                ) ?>

                                                <?php if (
                                                    $log["reference_id"] !== null
                                                ): ?>

                                                    #

                                                    <?= htmlspecialchars(
                                                        $log["reference_id"]
                                                    ) ?>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                -

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $log["ip_address"]
                                                ?? "-"
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>


<script src="js/admin.js"></script>

</body>

</html>
