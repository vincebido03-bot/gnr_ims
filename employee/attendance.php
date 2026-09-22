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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["attendance_action"] ?? "";

    if ($action === "time_in") {
        $result = employeeTimeIn($employeeId);
        $message = $result["message"] ?? "Unable to time in.";
        $messageType = $result["success"] ? "success" : "error";
    } elseif ($action === "time_out") {
        $result = employeeTimeOut($employeeId);
        $message = $result["message"] ?? "Unable to time out.";
        $messageType = $result["success"] ? "success" : "error";
    } elseif ($action === "start_break") {
        $result = employeeStartBreak($employeeId);
        $message = $result["message"] ?? "Unable to start break.";
        $messageType = $result["success"] ? "success" : "error";
    } elseif ($action === "end_break") {
        $result = employeeEndBreak($employeeId);
        $message = $result["message"] ?? "Unable to end break.";
        $messageType = $result["success"] ? "success" : "error";
    }
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
        hourly_rate,
        daily_rate
    FROM employees
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found.");
}

$employeeName = trim($employee["first_name"] . " " . ($employee["middle_name"] ? $employee["middle_name"] . " " : "") . $employee["last_name"]);

$filter = $_GET["filter"] ?? "this_week";
$filter = in_array($filter, ["today", "yesterday", "this_week", "this_month", "last_month", "custom"], true) ? $filter : "this_week";
$startDate = $_GET["start_date"] ?? "";
$endDate = $_GET["end_date"] ?? "";

$stmt = $pdo->prepare("
    SELECT *
    FROM attendance
    WHERE employee_id = ?
      AND time_out IS NULL
        ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$employeeId]);
$attendance = $stmt->fetch();

$break = null;
$hasBreak = false;
$completedBreakMinutes = 0;

if ($attendance) {
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
    $stmt->execute([$attendance["id"]]);
    $break = $stmt->fetch();
    $hasBreak = (bool) $break;

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(break_minutes), 0)
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NOT NULL
    ");
    $stmt->execute([$attendance["id"]]);
    $completedBreakMinutes = (int) $stmt->fetchColumn();
}

$workedHours = 0;
if ($attendance && !empty($attendance["time_in"])) {
    $timeIn = new DateTime($attendance["time_in"]);
    $referenceTime = !empty($attendance["time_out"]) ? new DateTime($attendance["time_out"]) : new DateTime();
    $elapsedSeconds = max(0, $referenceTime->getTimestamp() - $timeIn->getTimestamp());
    $workedHours = round(($elapsedSeconds - ($completedBreakMinutes * 60)) / 3600, 2);
    if ($workedHours < 0) {
        $workedHours = 0;
    }
}

$isTimedIn = ($attendance && !empty($attendance["time_in"]) && empty($attendance["time_out"]));
$isCompleted = ($attendance && !empty($attendance["time_in"]) && !empty($attendance["time_out"]));
$isBreakActive = ($isTimedIn && $break && empty($break["break_in"]));

$breakDisplayMinutes = $completedBreakMinutes;
if ($isBreakActive) {
    $breakDisplayMinutes = max(0, (int) floor((time() - strtotime($break["break_out"])) / 60));
}

$breakStatus = "NO BREAK RECORDED";
if ($isBreakActive) {
    $breakStatus = "BREAK ACTIVE";
} elseif ($break && !empty($break["break_in"])) {
    $breakStatus = "BREAK COMPLETED";
}

$longBreakReminder = $isBreakActive && $breakDisplayMinutes >= 60;

$historySql = "
    SELECT a.*
    FROM attendance a
    WHERE a.employee_id = :employeeId
";
$historyParams = [":employeeId" => $employeeId];

if ($filter === "today") {
    $historySql .= " AND a.date = CURDATE() ";
} elseif ($filter === "yesterday") {
    $historySql .= " AND a.date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) ";
} elseif ($filter === "this_week") {
    $historySql .= " AND YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1) ";
} elseif ($filter === "this_month") {
    $historySql .= " AND MONTH(a.date) = MONTH(CURDATE()) AND YEAR(a.date) = YEAR(CURDATE()) ";
} elseif ($filter === "last_month") {
    $historySql .= " AND MONTH(a.date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(a.date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) ";
} elseif ($filter === "custom") {
    if ($startDate) {
        $historySql .= " AND a.date >= :startDate ";
        $historyParams[":startDate"] = $startDate;
    }
    if ($endDate) {
        $historySql .= " AND a.date <= :endDate ";
        $historyParams[":endDate"] = $endDate;
    }
}

$historySql .= " ORDER BY a.date DESC, a.time_in DESC ";
$stmt = $pdo->prepare($historySql);
$stmt->execute($historyParams);
$historyRecords = $stmt->fetchAll();

$historyRows = [];
$historyTotalHours = 0;

foreach ($historyRecords as $row) {
    $breakStmt = $pdo->prepare("
        SELECT COALESCE(SUM(break_minutes), 0)
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NOT NULL
    ");
    $breakStmt->execute([$row["id"]]);
    $breakMinutes = (int) $breakStmt->fetchColumn();

    $elapsedSeconds = 0;
    if (!empty($row["time_in"]) && !empty($row["time_out"])) {
        $elapsedSeconds = max(0, (new DateTime($row["time_out"]))->getTimestamp() - (new DateTime($row["time_in"]))->getTimestamp());
    }

    $workedHours = $elapsedSeconds > 0 ? max(0, round(($elapsedSeconds - ($breakMinutes * 60)) / 3600, 2)) : 0;
    $historyTotalHours += $workedHours;

    $historyRows[] = [
        "record" => $row,
        "break_minutes" => $breakMinutes,
        "worked_hours" => $workedHours,
    ];
}

function formatAttendanceDate($value) {
    return $value ? date("M d, Y", strtotime($value)) : "—";
}

function formatAttendanceDateTime($value) {
    return $value ? date("M d, Y h:i A", strtotime($value)) : "—";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | GNR Employee Portal</title>
    <link rel="stylesheet" href="css/employee.css">
    <style>
        .attendance-page { min-height: 100vh; padding: 40px; background: #0b0b0b; color: #f4f4f4; }
        .attendance-header { margin-bottom: 30px; }
        .attendance-eyebrow { margin-bottom: 8px; color: #c9a227; font-size: 10px; font-weight: 800; letter-spacing: 2px; }
        .attendance-header h1 { margin: 0; font-size: 32px; font-weight: 800; }
        .attendance-header p { margin: 8px 0 0; color: #8f8f8f; font-size: 14px; }
        .attendance-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 22px; }
        .attendance-card { padding: 28px; background: #151515; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; }
        .attendance-card-title { margin-bottom: 22px; color: #8f8f8f; font-size: 10px; font-weight: 800; letter-spacing: 1.5px; }
        .attendance-status { margin-bottom: 25px; font-size: 28px; font-weight: 800; }
        .attendance-status.active { color: #c9a227; }
        .attendance-status.completed { color: #7fc97f; }
        .attendance-status.pending { color: #8f8f8f; }
        .attendance-time-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .attendance-stat { padding: 18px; background: #101010; border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; }
        .attendance-stat span { display: block; margin-bottom: 8px; color: #777; font-size: 9px; font-weight: 700; letter-spacing: 1px; }
        .attendance-stat strong { color: #f4f4f4; font-size: 18px; }
        .attendance-action-card { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .attendance-action-card h2 { margin: 0 0 8px; font-size: 22px; }
        .attendance-action-card p { margin: 0 0 25px; color: #777; font-size: 13px; }
        .attendance-button { width: 100%; max-width: 260px; min-height: 52px; border: 0; border-radius: 12px; font-size: 12px; font-weight: 800; letter-spacing: 1px; cursor: pointer; transition: transform 0.2s ease, opacity 0.2s ease; }
        .attendance-button:hover { transform: translateY(-2px); opacity: 0.9; }
        .attendance-button.time-in { background: #c9a227; color: #111; }
        .attendance-button.time-out { background: #d9534f; color: #fff; }
        .attendance-button.break { margin-bottom: 12px; background: #4b8f8c; color: #fff; }
        .attendance-break-reminder { width: 100%; max-width: 320px; margin-bottom: 20px; padding: 12px 14px; background: rgba(201,162,39,0.12); border: 1px solid rgba(201,162,39,0.35); border-radius: 10px; color: #e2c35b; font-size: 12px; font-weight: 700; }
        .attendance-complete { color: #7fc97f; font-size: 13px; font-weight: 700; }
        .attendance-message { margin-bottom: 22px; padding: 14px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; }
        .attendance-message.success { background: rgba(127,201,127,0.1); border: 1px solid rgba(127,201,127,0.2); color: #7fc97f; }
        .attendance-message.error { background: rgba(217,83,79,0.1); border: 1px solid rgba(217,83,79,0.2); color: #d9534f; }
        .employee-info { margin-top: 22px; padding-top: 22px; border-top: 1px solid rgba(255,255,255,0.07); }
        .employee-info-row { display: flex; justify-content: space-between; gap: 20px; margin-bottom: 10px; font-size: 12px; }
        .employee-info-row span:first-child { color: #777; }
        .employee-info-row span:last-child { color: #ddd; font-weight: 600; text-align: right; }
        .history-toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
        .filter-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px; border-radius: 999px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: #f4f4f4; text-decoration: none; font-size: 12px; font-weight: 700; }
        .filter-btn.active { background: #c9a227; color: #111; border-color: #c9a227; }
        .history-custom-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .history-custom-form input { background: #111; border: 1px solid rgba(255,255,255,0.1); color: #f4f4f4; border-radius: 8px; padding: 8px 10px; }
        .history-custom-form button { background: #c9a227; border: none; border-radius: 8px; padding: 9px 14px; font-weight: 800; color: #111; }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .history-table th, .history-table td { padding: 12px 10px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; font-size: 13px; }
        .history-table th { color: #8f8f8f; font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase; }
        .history-total { display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.08); font-weight: 700; }
        @media (max-width: 900px) { .attendance-page { padding: 25px; } .attendance-grid { grid-template-columns: 1fr; } .attendance-time-grid { grid-template-columns: 1fr; } .history-table { display: block; overflow-x: auto; } }
    </style>
</head>

<body>
<?php include "includes/sidebar.php"; ?>

<main class="employee-main">
    <div class="attendance-page">
        <div class="attendance-header">
            <div class="attendance-eyebrow">EMPLOYEE PORTAL / ATTENDANCE</div>
            <h1>My Attendance</h1>
            <p>Track your working hours, breaks, and duty history.</p>
        </div>

        <?php if ($message): ?>
            <div class="attendance-message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="attendance-grid">
            <section class="attendance-card">
                <div class="attendance-card-title">CURRENT DUTY</div>

                <?php if ($isTimedIn): ?>
                    <div class="attendance-status active">WORKING</div>
                <?php elseif ($isCompleted): ?>
                    <div class="attendance-status completed">COMPLETED</div>
                <?php else: ?>
                    <div class="attendance-status pending">NOT TIMED IN</div>
                <?php endif; ?>

                <div class="attendance-time-grid">
                    <div class="attendance-stat">
                        <span>TIME IN</span>
                        <strong><?= $attendance && $attendance["time_in"] ? date("h:i A", strtotime($attendance["time_in"])) : "--" ?></strong>
                    </div>
                    <div class="attendance-stat">
                        <span>TIME OUT</span>
                        <strong><?= $attendance && $attendance["time_out"] ? date("h:i A", strtotime($attendance["time_out"])) : "--" ?></strong>
                    </div>
                    <div class="attendance-stat">
                        <span>BREAK</span>
                        <strong><?= $break && $break["break_out"] ? date("h:i A", strtotime($break["break_out"])) : "NO BREAK RECORDED" ?></strong>
                    </div>
                    <div class="attendance-stat">
                        <span>WORKED HOURS</span>
                        <strong><?= number_format($workedHours, 2) ?> H</strong>
                    </div>
                </div>

                <div class="employee-info">
                    <div class="employee-info-row"><span>Break Status</span><span><?= htmlspecialchars($breakStatus) ?></span></div>
                    <div class="employee-info-row"><span>Break Minutes</span><span><?= $breakDisplayMinutes ?> min</span></div>
                    <div class="employee-info-row"><span>Duty Status</span><span><?= $isTimedIn ? "Working" : ($isCompleted ? "Completed" : "Not Timed In") ?></span></div>
                    <div class="employee-info-row"><span>Employee</span><span><?= htmlspecialchars($employeeName) ?></span></div>
                </div>
            </section>

            <section class="attendance-card attendance-action-card">
                <?php if ($isTimedIn): ?>
                    <h2>You're currently working.</h2>
                    <p><?= $isBreakActive ? "Your break is currently active." : "Time out when you finish your shift." ?></p>

                    <?php if ($isBreakActive): ?>
                        <div id="break-reminder" class="attendance-break-reminder" data-break-out="<?= strtotime($break["break_out"]) ?>" style="<?= $longBreakReminder ? "" : "display:none;" ?>">Your break has reached 1 hour. Please end your break.</div>
                    <?php endif; ?>

                    <div class="employee-info" style="width:100%; max-width:320px; margin-top:0;">
                        <div class="employee-info-row"><span>Break Out</span><span><?= $break && $break["break_out"] ? date("h:i A", strtotime($break["break_out"])) : "--" ?></span></div>
                        <div class="employee-info-row"><span>Break duration</span><span><?= $breakDisplayMinutes ?> min</span></div>
                        <div class="employee-info-row"><span>Break status</span><span><?= htmlspecialchars($breakStatus) ?></span></div>
                        <?php if ($break && $break["break_in"]): ?>
                            <div class="employee-info-row"><span>Break In</span><span><?= date("h:i A", strtotime($break["break_in"])) ?></span></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isBreakActive): ?>
                        <form method="POST">
                            <input type="hidden" name="attendance_action" value="end_break">
                            <button type="submit" class="attendance-button break">END BREAK</button>
                        </form>
                    <?php elseif (!$hasBreak): ?>
                        <form method="POST">
                            <input type="hidden" name="attendance_action" value="start_break">
                            <button type="submit" class="attendance-button break">START BREAK</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$isBreakActive): ?>
                        <form method="POST">
                            <input type="hidden" name="attendance_action" value="time_out">
                            <button type="submit" class="attendance-button time-out">TIME OUT</button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($isCompleted): ?>
                    <h2>Shift Complete</h2>
                    <p>Your attendance for this shift has been recorded.</p>
                    <div class="attendance-complete"><?= number_format($workedHours, 2) ?> HOURS WORKED</div>
                    <div class="employee-info" style="width:100%; max-width:320px;">
                        <div class="employee-info-row"><span>Break</span><span><?= $break && $break["break_out"] ? date("h:i A", strtotime($break["break_out"])) . " - " . date("h:i A", strtotime($break["break_in"])) . " / " . $completedBreakMinutes . " min" : "NO BREAK RECORDED" ?></span></div>
                    </div>
                <?php else: ?>
                    <h2>Ready to Start?</h2>
                    <p>Press the button below to record your time in.</p>
                    <form method="POST">
                        <input type="hidden" name="attendance_action" value="time_in">
                        <button type="submit" class="attendance-button time-in">TIME IN</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <section class="attendance-card" style="margin-top: 24px;">
            <div class="attendance-card-title">ATTENDANCE HISTORY</div>

            <div class="history-toolbar">
                <a class="filter-btn <?= $filter === "today" ? "active" : "" ?>" href="?filter=today">Today</a>
                <a class="filter-btn <?= $filter === "yesterday" ? "active" : "" ?>" href="?filter=yesterday">Yesterday</a>
                <a class="filter-btn <?= $filter === "this_week" ? "active" : "" ?>" href="?filter=this_week">This Week</a>
                <a class="filter-btn <?= $filter === "this_month" ? "active" : "" ?>" href="?filter=this_month">This Month</a>
                <a class="filter-btn <?= $filter === "last_month" ? "active" : "" ?>" href="?filter=last_month">Last Month</a>
                <form class="history-custom-form" method="GET">
                    <input type="hidden" name="filter" value="custom">
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" placeholder="Start date">
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" placeholder="End date">
                    <button type="submit">Apply</button>
                </form>
            </div>

            <?php if ($historyRows): ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Work Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Break</th>
                            <th>Break Duration</th>
                            <th>Worked Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyRows as $row): ?>
                            <?php $record = $row["record"]; ?>
                            <tr>
                                <td><?= formatAttendanceDate($record["date"]) ?></td>
                                <td><?= formatAttendanceDateTime($record["time_in"]) ?></td>
                                <td><?= !empty($record["time_out"]) ? formatAttendanceDateTime($record["time_out"]) : "Current" ?></td>
                                <td><?= $row["break_minutes"] > 0 ? "Break Recorded" : "NO BREAK RECORDED" ?></td>
                                <td><?= $row["break_minutes"] > 0 ? $row["break_minutes"] . " min" : "0 min" ?></td>
                                <td><?= number_format($row["worked_hours"], 2) ?> H</td>
                                <td><?= !empty($record["time_out"]) ? "Completed" : "Working" ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="history-total">
                    <span>Total Worked Hours</span>
                    <span><?= number_format($historyTotalHours, 2) ?> H</span>
                </div>
            <?php else: ?>
                <div style="padding: 20px 0; color: #8f8f8f;">No attendance records found for the selected filter.</div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php if ($isBreakActive): ?>
<script>
    const breakReminder = document.getElementById("break-reminder");
    if (breakReminder) {
        const breakOut = Number(breakReminder.dataset.breakOut) * 1000;
        const updateBreakReminder = () => {
            if (Date.now() - breakOut >= 60 * 60 * 1000) {
                breakReminder.style.display = "block";
            }
        };
        updateBreakReminder();
        window.setInterval(updateBreakReminder, 30000);
    }
</script>
<?php endif; ?>
</body>
</html>


                    <div class="attendance-stat">

                        <span>
                            TIME OUT
                        </span>

                        <strong>
                            <?= $attendance && $attendance["time_out"]
                                ? date("h:i A", strtotime($attendance["time_out"]))
                                : "--"
                            ?>
                        </strong>

                    </div>


                    <div class="attendance-stat">

                        <span>
                            WORKED HOURS
                        </span>

                        <strong>
                            <?= number_format($workedHours, 2) ?> H
                        </strong>

                    </div>

                </div>


                <div class="employee-info">

                    <div class="employee-info-row">

                        <span>
                            Employee
                        </span>

                        <span>
                            <?= e($employeeName) ?>
                        </span>

                    </div>


                    <div class="employee-info-row">

                        <span>
                            Employee No.
                        </span>

                        <span>
                            <?= e($employee["employee_no"]) ?>
                        </span>

                    </div>


                    <div class="employee-info-row">

                        <span>
                            Position
                        </span>

                        <span>
                            <?= e($employee["position"] ?: "—") ?>
                        </span>

                    </div>


                    <div class="employee-info-row">

                        <span>
                            Department
                        </span>

                        <span>
                            <?= e($employee["department"] ?: "—") ?>
                        </span>

                    </div>

                </div>

            </section>


            <!-- ACTION -->

            <section class="attendance-card attendance-action-card">

                <?php if ($isTimedIn): ?>

                    <h2>
                        You're currently working.
                    </h2>

                    <p>
                        <?= $isBreakActive
                            ? "Your break is currently active."
                            : "Time out when you finish your shift."
                        ?>
                    </p>

                    <?php if ($isBreakActive): ?>

                        <div
                            id="break-reminder"
                            class="attendance-break-reminder"
                            data-break-out="<?= strtotime($break["break_out"]) ?>"
                            style="<?= $longBreakReminder ? "" : "display:none;" ?>"
                        >
                            Your break has reached 1 hour. Please end your break.
                        </div>

                    <?php endif; ?>

                    <div class="employee-info" style="width:100%; max-width:320px; margin-top:0;">

                        <div class="employee-info-row">
                            <span>Break Out</span>
                            <span><?= $break && $break["break_out"]
                                ? date("h:i A", strtotime($break["break_out"]))
                                : "--"
                            ?></span>
                        </div>

                        <div class="employee-info-row">
                            <span>Break duration</span>
                            <span><?= $breakDisplayMinutes ?> min</span>
                        </div>

                        <div class="employee-info-row">
                            <span>Break status</span>
                            <span><?= e($breakStatus) ?></span>
                        </div>

                        <?php if ($break && $break["break_in"]): ?>
                            <div class="employee-info-row">
                                <span>Break In</span>
                                <span><?= date("h:i A", strtotime($break["break_in"])) ?></span>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php if ($isBreakActive): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="attendance_action"
                                value="end_break"
                            >

                            <button
                                type="submit"
                                class="attendance-button break"
                            >
                                END BREAK
                            </button>

                        </form>

                    <?php else: ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="attendance_action"
                                value="start_break"
                            >

                            <button
                                type="submit"
                                class="attendance-button break"
                            >
                                START BREAK
                            </button>

                        </form>

                    <?php endif; ?>

                    <?php if (!$isBreakActive): ?>

                        <form method="POST">

                            <input
                                type="hidden"
                                name="attendance_action"
                                value="time_out"
                            >

                            <button
                                type="submit"
                                class="attendance-button time-out"
                            >
                                TIME OUT
                            </button>

                        </form>

                    <?php endif; ?>


                <?php elseif ($isCompleted): ?>

                    <h2>
                        Shift Complete
                    </h2>

                    <p>
                        Your attendance for this shift has been recorded.
                    </p>

                    <div class="attendance-complete">
                        <?= number_format($workedHours, 2) ?> HOURS WORKED
                    </div>

                    <div class="employee-info" style="width:100%; max-width:320px;">
                        <div class="employee-info-row">
                            <span>Break</span>
                            <span><?= $break && $break["break_out"]
                                ? date("h:i A", strtotime($break["break_out"])) . " - " . date("h:i A", strtotime($break["break_in"])) . " / " . $completedBreakMinutes . " min"
                                : "NO BREAK RECORDED"
                            ?></span>
                        </div>
                    </div>


                <?php else: ?>

                    <h2>
                        Ready to Start?
                    </h2>

                    <p>
                        Press the button below to record your time in.
                    </p>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="attendance_action"
                            value="time_in"
                        >

                        <button
                            type="submit"
                            class="attendance-button time-in"
                        >
                            TIME IN
                        </button>

                    </form>

                <?php endif; ?>

            </section>

        </div>

    </div>

</main>

<?php if ($isBreakActive): ?>
<script>
    const breakReminder = document.getElementById("break-reminder");
    const breakOut = Number(breakReminder.dataset.breakOut) * 1000;

    const updateBreakReminder = () => {
        if (Date.now() - breakOut >= 60 * 60 * 1000) {
            breakReminder.style.display = "block";
        }
    };

    updateBreakReminder();
    window.setInterval(updateBreakReminder, 30000);
</script>
<?php endif; ?>

</body>

</html>