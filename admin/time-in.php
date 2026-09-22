<?php

require_once "includes/header.php";


/* =========================================================
   CHECK EMPLOYEE ACCOUNT
   ========================================================== */

$employeeId =
    $_SESSION["employee_id"] ?? null;

$message = "";
$messageType = "";


/* =========================================================
   TIME IN
   ========================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["attendance_action"]) &&
    $_POST["attendance_action"] === "time_in" &&
    $employeeId
) {

    $result =
        employeeTimeIn($employeeId);

    $message =
        $result["message"];

    $messageType =
        $result["success"]
            ? "success"
            : "error";
}


/* =========================================================
   TIME OUT
   ========================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["attendance_action"]) &&
    $_POST["attendance_action"] === "time_out" &&
    $employeeId
) {

    $result =
        employeeTimeOut($employeeId);

    $message =
        $result["message"];

    $messageType =
        $result["success"]
            ? "success"
            : "error";
}


/* =========================================================
   EMPLOYEE ACCOUNT CHECK
   ========================================================== */

if (!$employeeId) {

    $message =
        "This account is not linked to an employee profile.";

    $messageType =
        "error";
}


/* =========================================================
   GET ACTIVE ATTENDANCE
   ========================================================== */

$attendance = null;


if ($employeeId) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            date,
            time_in,
            time_out,
            late_minutes,
            overtime_hours,
            status,
            notes
        FROM attendance
        WHERE employee_id = ?
          AND (
              date = ?
              OR (
                  time_out IS NULL
                  AND date >= DATE_SUB(?, INTERVAL 1 DAY)
              )
          )
        ORDER BY date DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $employeeId,
        date("Y-m-d"),
        date("Y-m-d")
    ]);

    $attendance =
        $stmt->fetch();
}

$break = null;
$completedBreakMinutes = 0;

if ($attendance) {

    $stmt = $pdo->prepare("
        SELECT
            break_out,
            break_in,
            break_minutes
        FROM attendance_breaks
        WHERE attendance_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$attendance["id"]]);
    $break = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(break_minutes), 0)
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NOT NULL
    ");

    $stmt->execute([$attendance["id"]]);
    $completedBreakMinutes = (int) $stmt->fetchColumn();
}


/* =========================================================
   COMPUTE DISPLAYED WORK HOURS
   ========================================================== */

$workedHours = null;


if (
    $attendance &&
    $attendance["time_in"] &&
    $attendance["time_out"]
) {

    $timeIn = strtotime($attendance["time_in"]);

    $timeOut = strtotime($attendance["time_out"]);


    $workedHours =
        round(
            ($timeOut - $timeIn - ($completedBreakMinutes * 60)) / 3600,
            2
        );
}

$breakDisplayMinutes = $completedBreakMinutes;

if ($attendance && $break && empty($break["break_in"])) {
    $breakDisplayMinutes = max(
        0,
        (int) floor(
            (time() - strtotime($break["break_out"])) / 60
        )
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>GNR IMS - Attendance</title>

<link
    rel="stylesheet"
    href="css/sidebar.css"
>

<link
    rel="stylesheet"
    href="css/dashboard.css"
>

<link
    rel="stylesheet"
    href="css/time-in.css"
>


</head>

<body>

<div class="admin-layout">


<?php require_once "includes/sidebar.php"; ?>


<main class="main-content">


    <!-- =================================================
         TOPBAR
         ================================================== -->

    <header class="topbar">

        <div>

            <h1>
                Attendance
            </h1>

            <p>
                Record your Time In and Time Out.
            </p>

        </div>


        <div class="user-info">

            <div class="user-avatar">

                <?= strtoupper(
                    substr(
                        $user["username"],
                        0,
                        1
                    )
                ) ?>

            </div>

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $user["username"]
                    ) ?>
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $user["role_name"]
                    ) ?>
                </small>

            </div>

        </div>

    </header>


    <!-- =================================================
         CONTENT
         ================================================== -->

    <section class="dashboard-content">


        <div class="time-in-container">


            <?php if ($message): ?>

                <div
                    class="time-message
                    <?= htmlspecialchars(
                        $messageType
                    ) ?>"
                >

                    <?= htmlspecialchars(
                        $message
                    ) ?>

                </div>

            <?php endif; ?>


            <div class="time-card">


                <div class="time-icon">
                    🕒
                </div>


                <div class="time-card-content">


                    <span class="time-label">
                        TODAY
                    </span>


                    <h2>
                        <?= date("M d, Y") ?>
                    </h2>


                    <?php if ($attendance): ?>


                        <div class="attendance-status">

                            <span>
                                STATUS
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $attendance["status"]
                                ) ?>
                            </strong>

                        </div>


                        <div class="attendance-time">


                            <div>

                                <small>
                                    TIME IN
                                </small>

                                <strong>

                                    <?= $attendance["time_in"]
                                        ? date(
                                            "h:i A",
                                            strtotime(
                                                $attendance["time_in"]
                                            )
                                        )
                                        : "-"
                                    ?>

                                </strong>

                            </div>


                            <div>

                                <small>
                                    BREAK OUT
                                </small>

                                <strong>
                                    <?= $break && $break["break_out"]
                                        ? date("h:i A", strtotime($break["break_out"]))
                                        : "-"
                                    ?>
                                </strong>

                            </div>


                            <div>

                                <small>
                                    BREAK IN
                                </small>

                                <strong>
                                    <?= $break && $break["break_in"]
                                        ? date("h:i A", strtotime($break["break_in"]))
                                        : "-"
                                    ?>
                                </strong>

                            </div>


                            <div>

                                <small>
                                    BREAK DURATION
                                </small>

                                <strong>
                                    <?= $break ? $breakDisplayMinutes . " MIN" : "-" ?>
                                </strong>

                            </div>


                            <div>

                                <small>
                                    TIME OUT
                                </small>

                                <strong>

                                    <?= $attendance["time_out"]
                                        ? date(
                                            "h:i A",
                                            strtotime(
                                                $attendance["time_out"]
                                            )
                                        )
                                        : "-"
                                    ?>

                                </strong>

                            </div>

                        </div>


                        <?php if ($workedHours !== null): ?>

                            <div class="attendance-status">

                                <span>
                                    WORKED HOURS
                                </span>

                                <strong>
                                    <?= number_format(
                                        $workedHours,
                                        2
                                    ) ?>
                                    HRS
                                </strong>

                            </div>

                        <?php endif; ?>


                        <?php if (!$attendance["time_out"]): ?>


                            <div class="already-timed">

                                You are currently clocked in.

                            </div>


                            <form
                                method="POST"
                                style="margin-top:20px;"
                            >

                                <input
                                    type="hidden"
                                    name="attendance_action"
                                    value="time_out"
                                >


                                <button
                                    type="submit"
                                    class="time-in-btn"
                                >
                                    TIME OUT
                                </button>

                            </form>


                        <?php else: ?>


                            <div class="already-timed">

                                ✓ Attendance completed for this shift.

                            </div>


                        <?php endif; ?>


                    <?php else: ?>


                        <p class="time-description">

                            You have not recorded your attendance
                            for today.

                        </p>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="attendance_action"
                                value="time_in"
                            >


                            <button
                                type="submit"
                                class="time-in-btn"
                            >
                                TIME IN
                            </button>

                        </form>


                    <?php endif; ?>


                </div>

            </div>

        </div>

    </section>

</main>


</div>

<script src="js/admin.js"></script>

</body>

</html>
