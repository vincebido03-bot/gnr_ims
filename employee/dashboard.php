<?php

require_once "../admin/includes/auth.php";

requireLogin();

if (!hasRole("EMPLOYEE")) {
    header("Location: ../admin/dashboard.php");
    exit;
}

$employeeId = $_SESSION["employee_id"] ?? null;
$user = currentUser();

if (!$employeeId) {
    die("Employee account is not properly linked.");
}

$stmt = $pdo->prepare("
    SELECT
        id,
        employee_no,
        first_name,
        middle_name,
        last_name,
        nickname,
        position,
        department,
        hire_date,
        daily_rate,
        hourly_rate,
        photo,
        status
    FROM employees
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found.");
}

$displayName = !empty($employee["nickname"]) ? $employee["nickname"] : $employee["first_name"];

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.date,
        a.time_in,
        a.time_out,
        a.status
    FROM attendance a
    WHERE a.employee_id = ?
    ORDER BY a.id DESC
    LIMIT 1
");
$stmt->execute([$employeeId]);
$currentAttendance = $stmt->fetch();

$currentBreak = null;
$currentBreakMinutes = 0;
$currentWorkedHours = 0;
$currentDutyStatus = "OFF DUTY";
$currentBreakStatus = "NO BREAK RECORDED";

if ($currentAttendance) {
    $stmt = $pdo->prepare("
        SELECT
            id,
            break_out,
            break_in,
            break_minutes
        FROM attendance_breaks
        WHERE attendance_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$currentAttendance["id"]]);
    $currentBreak = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(break_minutes), 0)
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NOT NULL
    ");
    $stmt->execute([$currentAttendance["id"]]);
    $currentBreakMinutes = (int) $stmt->fetchColumn();

    $timeIn = new DateTime($currentAttendance["time_in"]);
    $referenceTime = !empty($currentAttendance["time_out"])
        ? new DateTime($currentAttendance["time_out"])
        : new DateTime();
    $seconds = max(0, $referenceTime->getTimestamp() - $timeIn->getTimestamp());
    $currentWorkedHours = round(($seconds - ($currentBreakMinutes * 60)) / 3600, 2);

    $currentDutyStatus = !empty($currentAttendance["time_out"]) ? "COMPLETED" : "WORKING";
    if (empty($currentAttendance["time_out"]) && $currentBreak && empty($currentBreak["break_in"])) {
        $currentBreakStatus = "ON BREAK";
    } elseif ($currentBreak && !empty($currentBreak["break_in"])) {
        $currentBreakStatus = "BREAK COMPLETED";
    }
}

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.date,
        a.time_in,
        a.time_out,
        a.status
    FROM attendance a
    WHERE a.employee_id = ?
      AND a.time_out IS NOT NULL
    ORDER BY a.date DESC, a.time_in DESC
    LIMIT 5
");
$stmt->execute([$employeeId]);
$recentLogs = $stmt->fetchAll();

$recentWorkRows = [];
foreach ($recentLogs as $row) {
    $breakStmt = $pdo->prepare("
        SELECT COALESCE(SUM(break_minutes), 0)
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NOT NULL
    ");
    $breakStmt->execute([$row["id"]]);
    $breakMinutes = (int) $breakStmt->fetchColumn();

    $timeIn = new DateTime($row["time_in"]);
    $timeOut = new DateTime($row["time_out"]);
    $elapsedSeconds = max(0, $timeOut->getTimestamp() - $timeIn->getTimestamp());
    $workedHours = max(0, round(($elapsedSeconds - ($breakMinutes * 60)) / 3600, 2));

    $recentWorkRows[] = [
        "date" => $row["date"],
        "time_in" => $row["time_in"],
        "time_out" => $row["time_out"],
        "worked_hours" => $workedHours,
        "break_minutes" => $breakMinutes,
        "status" => $row["status"] ?? "PRESENT"
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Employee Dashboard</title>
    <link rel="stylesheet" href="css/employee.css">
    <style>
        html, body { overflow-x: hidden; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 28px 30px 20px; min-width: 0; }
        .topbar > div:first-child { min-width: 0; }
        .topbar h1 { margin: 0; font-size: 28px; overflow-wrap: anywhere; }
        .topbar p { margin: 8px 0 0; color: #8f8f8f; font-size: 13px; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border: 1px solid #c9a227; border-radius: 50%; color: #c9a227; font-size: 14px; font-weight: 800; }
        .user-info strong, .user-info small { display: block; }
        .user-info small { margin-top: 3px; color: #8f8f8f; font-size: 9px; letter-spacing: 1px; }
        .employee-dashboard { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; min-width: 0; }
        .employee-welcome { grid-column: 1 / -1; background: #111; border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 30px; }
        .employee-welcome span { display: block; font-size: 12px; letter-spacing: 2px; opacity: .6; margin-bottom: 8px; }
        .employee-welcome h2 { margin: 0; font-size: 32px; }
        .employee-welcome p { margin: 8px 0 0; opacity: .65; }
        .employee-card { min-width: 0; background: #111; border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 25px; }
        .employee-card.quick-access-card { grid-column: 1 / -1; }
        .employee-card h3 { margin: 0 0 20px; font-size: 15px; letter-spacing: 1px; }
        .attendance-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .attendance-box { background: rgba(255,255,255,.04); border-radius: 12px; padding: 18px; }
        .attendance-box small { display: block; opacity: .5; font-size: 11px; margin-bottom: 8px; }
        .attendance-box strong { font-size: 18px; }
        .employee-links { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .employee-link { display: block; padding: 18px; border-radius: 12px; background: rgba(255,255,255,.04); text-decoration: none; color: inherit; transition: .2s ease; }
        .employee-link:hover { transform: translateY(-2px); background: rgba(255,255,255,.08); }
        .employee-link strong { display: block; margin-bottom: 5px; }
        .employee-link span { font-size: 12px; opacity: .5; }
        .recent-log-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
        .recent-log-item { display: flex; justify-content: space-between; gap: 16px; align-items: center; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px 14px; }
        .recent-log-item .meta { font-size: 12px; color: #c7c7c7; }
        .recent-log-item .hours { font-weight: 700; color: #d4b34d; }
        @media (max-width: 1100px) {
            .topbar { align-items: flex-start; flex-direction: column; padding: 22px 16px 16px; }
            .user-info { margin-left: 0; }
            .employee-dashboard { grid-template-columns: minmax(0, 1fr); gap: 14px; }
            .employee-welcome { grid-column: auto; padding: 22px; }
            .employee-card { padding: 20px; }
            .attendance-grid, .employee-links { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
</head>

<body>
<div>
    <?php require_once "includes/sidebar.php"; ?>

    <main class="employee-main">
        <header class="topbar">
            <div>
                <h1>Employee Dashboard</h1>
                <p>Welcome to your GNR IMS employee portal.</p>
            </div>

            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($displayName, 0, 1)) ?></div>
                <div>
                    <strong><?= htmlspecialchars($displayName) ?></strong>
                    <small>EMPLOYEE</small>
                </div>
            </div>
        </header>

        <section class="dashboard-content">
            <div class="employee-dashboard">
                <div class="employee-welcome">
                    <span>WELCOME BACK</span>
                    <h2>Hello, <?= htmlspecialchars($displayName) ?>!</h2>
                    <p>
                        <?= htmlspecialchars($employee["position"] ?? "Employee") ?>
                        <?php if (!empty($employee["department"])): ?> · <?= htmlspecialchars($employee["department"]) ?> <?php endif; ?>
                    </p>
                </div>

                <div class="employee-card">
                    <h3>CURRENT DUTY</h3>
                    <div class="attendance-grid">
                        <div class="attendance-box">
                            <small>TIME IN</small>
                            <strong><?= $currentAttendance && $currentAttendance["time_in"] ? date("h:i A", strtotime($currentAttendance["time_in"])) : "-" ?></strong>
                        </div>
                        <div class="attendance-box">
                            <small>TIME OUT</small>
                            <strong><?= $currentAttendance && $currentAttendance["time_out"] ? date("h:i A", strtotime($currentAttendance["time_out"])) : "-" ?></strong>
                        </div>
                        <div class="attendance-box">
                            <small>WORKED HOURS</small>
                            <strong><?= $currentAttendance ? number_format($currentWorkedHours, 2) . " H" : "-" ?></strong>
                        </div>
                    </div>
                    <div style="margin-top: 18px; font-size: 12px; opacity: .75;">
                        <strong style="display:block; margin-bottom: 6px;">Break Status:</strong>
                        <?= htmlspecialchars($currentBreakStatus) ?>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; opacity: .75;">
                        <strong style="display:block; margin-bottom: 6px;">Duty Status:</strong>
                        <?= htmlspecialchars($currentDutyStatus) ?>
                    </div>
                </div>

                <div class="employee-card">
                    <h3>RECENT WORK LOGS</h3>
                    <?php if ($recentWorkRows): ?>
                        <ul class="recent-log-list">
                            <?php foreach ($recentWorkRows as $log): ?>
                                <li class="recent-log-item">
                                    <div>
                                        <div class="meta"><?= date("M d", strtotime($log["date"])) ?></div>
                                        <div><?= date("h:i A", strtotime($log["time_in"])) ?> → <?= date("h:i A", strtotime($log["time_out"])) ?></div>
                                    </div>
                                    <div class="hours"><?= number_format($log["worked_hours"], 2) ?> H</div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div style="opacity:.65; font-size:14px;">No recent attendance records yet.</div>
                    <?php endif; ?>
                </div>

                <div class="employee-card quick-access-card">
                    <h3>QUICK ACCESS</h3>
                    <div class="employee-links">
                        <a href="attendance.php" class="employee-link">
                            <strong>🕒 Attendance</strong>
                            <span>Time In and Time Out</span>
                        </a>
                        <a href="payroll.php" class="employee-link">
                            <strong>💰 My Payslips</strong>
                            <span>View processed payslip records</span>
                        </a>
                        <a href="profile.php" class="employee-link">
                            <strong>👤 My Profile</strong>
                            <span>View your employee information</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="../admin/js/admin.js"></script>
</body>
</html>

<?php exit; ?>


</head>

<body>

<div>

```
<!-- =====================================================
     SIDEBAR
     ====================================================== -->

<?php require_once "includes/sidebar.php"; ?>


<!-- =====================================================
     MAIN CONTENT
     ====================================================== -->

<main class="employee-main">


    <!-- =================================================
         TOPBAR
         ================================================== -->

    <header class="topbar">

        <div>

            <h1>
                Employee Dashboard
            </h1>

            <p>
                Welcome to your GNR IMS employee portal.
            </p>

        </div>


        <div class="user-info">

            <div class="user-avatar">

                <?= strtoupper(
                    substr(
                        $displayName,
                        0,
                        1
                    )
                ) ?>

            </div>

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $displayName
                    ) ?>
                </strong>

                <small>
                    EMPLOYEE
                </small>

            </div>

        </div>

    </header>


    <!-- =================================================
         CONTENT
         ================================================== -->

    <section class="dashboard-content">


        <div class="employee-dashboard">


            <!-- =========================================
                 WELCOME
                 ========================================== -->

            <div class="employee-welcome">

                <span>
                    WELCOME BACK
                </span>

                <h2>
                    Hello,
                    <?= htmlspecialchars(
                        $displayName
                    ) ?>!
                </h2>

                <p>

                    <?= $employee
                        ? htmlspecialchars(
                            $employee["position"] ??
                            "Employee"
                        )
                        : "Employee"
                    ?>

                    <?php if (
                        $employee &&
                        !empty(
                            $employee["department"]
                        )
                    ): ?>

                        ·

                        <?= htmlspecialchars(
                            $employee["department"]
                        ) ?>

                    <?php endif; ?>

                </p>

            </div>


            <!-- =========================================
                 TODAY'S ATTENDANCE
                 ========================================== -->

            <div class="employee-card">

                <h3>
                    TODAY'S ATTENDANCE
                </h3>


                <div class="attendance-grid">


                    <div class="attendance-box">

                        <small>
                            TIME IN
                        </small>

                        <strong>

                            <?= $attendance &&
                                $attendance["time_in"]
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


                    <div class="attendance-box">

                        <small>
                            TIME OUT
                        </small>

                        <strong>

                            <?= $attendance &&
                                $attendance["time_out"]
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


                    <div class="attendance-box">

                        <small>
                            WORKED HOURS
                        </small>

                        <strong>

                            <?= $workedHours !== null
                                ? number_format(
                                    $workedHours,
                                    2
                                ) . " HRS"
                                : "-"
                            ?>

                        </strong>

                    </div>

                </div>

            </div>


            <!-- =========================================
                 QUICK ACCESS
                 ========================================== -->

            <div class="employee-card">

                <h3>
                    QUICK ACCESS
                </h3>


                <div class="employee-links">


                    <a
                        href="attendance.php"
                        class="employee-link"
                    >

                        <strong>
                            🕒 Attendance
                        </strong>

                        <span>
                            Time In and Time Out
                        </span>

                    </a>


                    <a
                        href="payroll.php"
                        class="employee-link"
                    >

                        <strong>
                            💰 My Payslips
                        </strong>

                        <span>
                            View processed payslip records
                        </span>

                    </a>


                    <a
                        href="profile.php"
                        class="employee-link"
                    >

                        <strong>
                            👤 My Profile
                        </strong>

                        <span>
                            View your employee information
                        </span>

                    </a>

                </div>

            </div>


        </div>

    </section>

</main>
```

</div>

<script src="../admin/js/admin.js"></script>

</body>

</html>
