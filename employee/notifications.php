 <?php

require_once "../admin/includes/auth.php";

requireLogin();

if (!hasRole("EMPLOYEE")) {
    header("Location: ../admin/dashboard.php");
    exit;
}

$employeeId = $_SESSION["employee_id"] ?? null;

if (!$employeeId) {
    die("Employee account is not properly linked.");
}

$message = "";
$messageType = "";


/* =========================================================
   MARK AS READ
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["notification_action"] ?? "";

    if ($action === "mark_read") {

        $notificationId = (int)($_POST["notification_id"] ?? 0);

        if ($notificationId > 0) {

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE id = ?
                  AND (
                        employee_id = ?
                        OR user_id = ?
                      )
            ");

            $stmt->execute([
                $notificationId,
                $employeeId,
                $_SESSION["user_id"]
            ]);

            $message = "Notification marked as read.";
            $messageType = "success";
        }

    } elseif ($action === "mark_all_read") {

        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE is_read = 0
              AND (
                    employee_id = ?
                    OR user_id = ?
                  )
        ");

        $stmt->execute([
            $employeeId,
            $_SESSION["user_id"]
        ]);

        $message = "All notifications marked as read.";
        $messageType = "success";
    }
}


/* =========================================================
   NOTIFICATIONS
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        n.*,
        e.first_name,
        e.last_name
    FROM notifications n
    LEFT JOIN employees e
        ON n.employee_id = e.id
    WHERE n.employee_id = ?
       OR n.user_id = ?
    ORDER BY n.created_at DESC, n.id DESC
");

$stmt->execute([
    $employeeId,
    $_SESSION["user_id"]
]);

$notifications = $stmt->fetchAll();


/* =========================================================
   UNREAD COUNT
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE is_read = 0
      AND (
            employee_id = ?
            OR user_id = ?
          )
");

$stmt->execute([
    $employeeId,
    $_SESSION["user_id"]
]);

$unreadCount = (int)$stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications | GNR Employee Portal</title>

    <link
        rel="stylesheet"
        href="css/employee.css"
    >

    <style>

        .notifications-page {
            min-height: 100vh;

            padding: 40px;

            background: #0b0b0b;

            color: #f4f4f4;
        }


        /* =====================================================
           HEADER
           ===================================================== */

        .notifications-header {
            display: flex;

            justify-content: space-between;
            align-items: flex-end;

            gap: 20px;

            margin-bottom: 30px;
        }


        .notifications-eyebrow {
            margin-bottom: 8px;

            color: #c9a227;

            font-size: 10px;
            font-weight: 800;

            letter-spacing: 2px;
        }


        .notifications-header h1 {
            margin: 0;

            font-size: 32px;
            font-weight: 800;
        }


        .notifications-header p {
            margin: 8px 0 0;

            color: #8f8f8f;

            font-size: 14px;
        }


        .mark-all-button {
            padding: 11px 16px;

            background: transparent;

            border: 1px solid rgba(201,162,39,0.45);

            border-radius: 9px;

            color: #c9a227;

            font-size: 10px;
            font-weight: 800;

            letter-spacing: 1px;

            cursor: pointer;

            transition:
                background 0.2s ease,
                color 0.2s ease;
        }


        .mark-all-button:hover {
            background: #c9a227;

            color: #111;
        }


        /* =====================================================
           MESSAGE
           ===================================================== */

        .notification-message {
            margin-bottom: 20px;

            padding: 14px 16px;

            border-radius: 10px;

            font-size: 13px;
            font-weight: 600;
        }


        .notification-message.success {
            background: rgba(127,201,127,0.1);

            border: 1px solid rgba(127,201,127,0.2);

            color: #7fc97f;
        }


        .notification-message.error {
            background: rgba(217,83,79,0.1);

            border: 1px solid rgba(217,83,79,0.2);

            color: #d9534f;
        }


        /* =====================================================
           SUMMARY
           ===================================================== */

        .notifications-summary {
            display: flex;

            align-items: center;
            gap: 10px;

            margin-bottom: 18px;

            color: #777;

            font-size: 11px;
            font-weight: 700;

            letter-spacing: 1px;
        }


        .notification-count {
            min-width: 26px;
            height: 26px;

            padding: 0 8px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            background: rgba(201,162,39,0.12);

            border: 1px solid rgba(201,162,39,0.25);

            border-radius: 20px;

            color: #c9a227;

            font-size: 10px;
        }


        /* =====================================================
           NOTIFICATION LIST
           ===================================================== */

        .notification-list {
            display: flex;

            flex-direction: column;

            gap: 10px;
        }


        .notification-item {
            position: relative;

            padding: 20px 22px;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 14px;

            transition:
                background 0.2s ease,
                border-color 0.2s ease;
        }


        .notification-item:hover {
            background: #191919;

            border-color: rgba(255,255,255,0.11);
        }


        .notification-item.unread {
            border-color: rgba(201,162,39,0.28);

            background: rgba(201,162,39,0.045);
        }


        .notification-item.unread::before {
            content: "";

            position: absolute;

            left: 0;
            top: 16px;
            bottom: 16px;

            width: 3px;

            background: #c9a227;

            border-radius: 0 4px 4px 0;
        }


        .notification-top {
            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;
        }


        .notification-title-wrapper {
            display: flex;

            align-items: center;

            gap: 10px;
        }


        .notification-icon {
            width: 38px;
            height: 38px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #101010;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 10px;

            color: #c9a227;

            font-size: 15px;
            font-weight: 800;
        }


        .notification-title {
            margin: 0;

            color: #f4f4f4;

            font-size: 14px;
            font-weight: 800;
        }


        .notification-new {
            padding: 4px 7px;

            background: #c9a227;

            border-radius: 5px;

            color: #111;

            font-size: 7px;
            font-weight: 900;

            letter-spacing: 1px;
        }


        .notification-message-text {
            margin: 15px 0 12px;

            color: #aaa;

            font-size: 13px;

            line-height: 1.6;
        }


        .notification-footer {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;
        }


        .notification-date {
            color: #666;

            font-size: 10px;

            letter-spacing: 0.3px;
        }


        .mark-read-button {
            padding: 7px 10px;

            background: transparent;

            border: 1px solid rgba(255,255,255,0.1);

            border-radius: 7px;

            color: #888;

            font-size: 9px;
            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s ease,
                color 0.2s ease;
        }


        .mark-read-button:hover {
            background: rgba(255,255,255,0.06);

            color: #fff;
        }


        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .notifications-empty {
            padding: 70px 25px;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 16px;

            text-align: center;
        }


        .notifications-empty-icon {
            width: 60px;
            height: 60px;

            margin: 0 auto 18px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #101010;

            border: 1px solid rgba(255,255,255,0.08);

            border-radius: 50%;

            color: #555;

            font-size: 22px;
        }


        .notifications-empty h2 {
            margin: 0 0 8px;

            font-size: 18px;
        }


        .notifications-empty p {
            margin: 0;

            color: #666;

            font-size: 12px;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 700px) {

            .notifications-page {
                padding: 25px 20px;
            }


            .notifications-header {
                align-items: flex-start;

                flex-direction: column;
            }


            .notifications-header h1 {
                font-size: 27px;
            }


            .notification-top {
                flex-direction: column;

                gap: 12px;
            }


            .notification-footer {
                align-items: flex-start;

                flex-direction: column;
            }

        }

    </style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<main class="employee-main">

    <div class="notifications-page">


        <!-- HEADER -->

        <div class="notifications-header">

            <div>

                <div class="notifications-eyebrow">
                    EMPLOYEE PORTAL / NOTIFICATIONS
                </div>

                <h1>
                    Notifications
                </h1>

                <p>
                    Stay updated with your attendance activity.
                </p>

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
                        class="mark-all-button"
                    >
                        MARK ALL AS READ
                    </button>

                </form>

            <?php endif; ?>

        </div>


        <?php if ($message): ?>

            <div class="notification-message <?= $messageType ?>">
                <?= e($message) ?>
            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <div class="notifications-summary">

            <span>
                UNREAD
            </span>

            <span class="notification-count">
                <?= $unreadCount ?>
            </span>

        </div>


        <!-- NOTIFICATION LIST -->

        <?php if (!empty($notifications)): ?>

            <div class="notification-list">

                <?php foreach ($notifications as $notification): ?>

                    <?php

                    $isUnread = (int)$notification["is_read"] === 0;

                    $type = strtoupper($notification["type"] ?? "");

                    if (strpos($type, "TIME_IN") !== false) {
                        $icon = "IN";
                    } elseif (strpos($type, "TIME_OUT") !== false) {
                        $icon = "OUT";
                    } else {
                        $icon = "!";
                    }

                    ?>

                    <article
                        class="notification-item <?= $isUnread ? "unread" : "" ?>"
                    >

                        <div class="notification-top">

                            <div class="notification-title-wrapper">

                                <div class="notification-icon">
                                    <?= e($icon) ?>
                                </div>

                                <h2 class="notification-title">
                                    <?= e($notification["title"]) ?>
                                </h2>

                                <?php if ($isUnread): ?>

                                    <span class="notification-new">
                                        NEW
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="notification-message-text">

                            <?= nl2br(e($notification["message"])) ?>

                        </div>


                        <div class="notification-footer">

                            <span class="notification-date">

                                <?= e(
                                    date(
                                        "M d, Y • h:i A",
                                        strtotime($notification["created_at"])
                                    )
                                ) ?>

                            </span>


                            <?php if ($isUnread): ?>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="notification_action"
                                        value="mark_read"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int)$notification["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="mark-read-button"
                                    >
                                        MARK AS READ
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="notification-date">
                                    READ
                                </span>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>


        <?php else: ?>


            <div class="notifications-empty">

                <div class="notifications-empty-icon">
                    !
                </div>

                <h2>
                    No Notifications
                </h2>

                <p>
                    You're all caught up. New attendance alerts will appear here.
                </p>

            </div>


        <?php endif; ?>


    </div>

</main>


</body>

</html>