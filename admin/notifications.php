<?php

require_once "includes/header.php";

requirePermission("notifications", "view");


/* =========================================================
   MARK NOTIFICATION AS READ
   ========================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["notification_action"])
) {

    $action = $_POST["notification_action"];

    /* =====================================================
       MARK ONE AS READ
       ====================================================== */

    if ($action === "mark_read") {

        $notificationId = (int) ($_POST["notification_id"] ?? 0);

        if ($notificationId > 0) {

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                  AND (
                      user_id = ?
                      OR user_id IS NULL
                  )
            ");

            $stmt->execute([
                $notificationId,
                $_SESSION["user_id"]
            ]);
        }

        header("Location: notifications.php");
        exit;
    }


    /* =====================================================
       MARK ALL AS READ
       ====================================================== */

    if ($action === "mark_all_read") {

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE
                user_id = ?
                OR user_id IS NULL
        ");

        $stmt->execute([
            $_SESSION["user_id"]
        ]);

        header("Location: notifications.php");
        exit;
    }
}


/* =========================================================
   GET NOTIFICATIONS
   ========================================================== */

$stmt = $pdo->prepare("
    SELECT
        n.id,
        n.type,
        n.title,
        n.message,
        n.employee_id,
        n.user_id,
        n.reference_type,
        n.reference_id,
        n.is_read,
        n.created_at,
        e.first_name,
        e.last_name

    FROM notifications n

    LEFT JOIN employees e
        ON n.employee_id = e.id

    WHERE
        n.user_id = ?
        OR n.user_id IS NULL

    ORDER BY
        n.created_at DESC
");

$stmt->execute([
    $_SESSION["user_id"]
]);

$notifications = $stmt->fetchAll();


/* =========================================================
   COUNT UNREAD
   ========================================================== */

$unreadCount = 0;

foreach ($notifications as $notification) {

    if (!$notification["is_read"]) {
        $unreadCount++;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

```
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>GNR IMS - Notifications</title>

<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/dashboard.css">
<link rel="stylesheet" href="css/notifications.css">
```

</head>

<body>

<div class="admin-layout">

```
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
                Notifications
            </h1>

            <p>
                System alerts and activity notifications.
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
         NOTIFICATIONS CONTENT
         ================================================== -->

    <section class="dashboard-content">


        <div class="panel notifications-panel">


            <!-- =================================================
                 PANEL HEADER
                 ================================================== -->

            <div class="panel-header">

                <div>

                    <h3>
                        Notification History
                    </h3>

                    <span>
                        <?= count($notifications) ?> RECORDS
                    </span>

                </div>


                <?php if ($unreadCount > 0): ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="notification_action"
                            value="mark_all_read"
                        >

                        <button
                            type="submit"
                            class="mark-all-read-btn"
                        >
                            MARK ALL AS READ
                        </button>

                    </form>

                <?php endif; ?>

            </div>


            <!-- =================================================
                 EMPTY STATE
                 ================================================== -->

            <?php if (empty($notifications)): ?>

                <div class="empty-state">

                    <div>
                        🔔
                    </div>

                    <p>
                        No notifications yet.
                    </p>

                </div>


            <?php else: ?>


                <!-- =================================================
                     NOTIFICATION LIST
                     ================================================== -->

                <div class="notification-list">


                    <?php foreach ($notifications as $notification): ?>


                        <div
                            class="notification-item <?= $notification["is_read"] ? "read" : "unread" ?>"
                        >


                            <!-- =================================================
                                 ICON
                                 ================================================== -->

                            <div class="notification-icon">

                                🔔

                            </div>


                            <!-- =================================================
                                 CONTENT
                                 ================================================== -->

                            <div class="notification-content">


                                <div class="notification-top">


                                    <strong>

                                        <?= htmlspecialchars(
                                            $notification["title"]
                                        ) ?>

                                    </strong>


                                    <?php if (!$notification["is_read"]): ?>

                                        <span class="notification-badge">
                                            NEW
                                        </span>

                                    <?php endif; ?>


                                </div>


                                <p>

                                    <?= htmlspecialchars(
                                        $notification["message"]
                                    ) ?>

                                </p>


                                <small>

                                    <?= htmlspecialchars(
                                        date(
                                            "M d, Y h:i A",
                                            strtotime(
                                                $notification["created_at"]
                                            )
                                        )
                                    ) ?>

                                </small>


                            </div>


                            <!-- =================================================
                                 ACTION
                                 ================================================== -->

                            <?php if (!$notification["is_read"]): ?>

                                <div class="notification-action">

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="notification_action"
                                            value="mark_read"
                                        >

                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?= (int) $notification["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="mark-read-btn"
                                        >
                                            MARK AS READ
                                        </button>

                                    </form>

                                </div>

                            <?php endif; ?>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>


    </section>


</main>
```

</div>

<script src="js/admin.js"></script>

</body>

</html>
