<?php

require_once "includes/header.php";

$date = $_GET["date"] ?? date("Y-m-d");
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date("Y-m-d");
}

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["PRESENT", "LATE", "ABSENT", "HALF_DAY", "OVERTIME"];

$sql = "
    SELECT a.date, a.time_in, a.time_out, a.late_minutes, a.overtime_hours, a.status,
           e.employee_no, e.first_name, e.last_name, e.position, e.department,
           COALESCE(b.break_minutes, 0) AS break_minutes
    FROM attendance a
    INNER JOIN employees e ON e.id = a.employee_id
    LEFT JOIN (
        SELECT attendance_id, SUM(COALESCE(break_minutes, 0)) AS break_minutes
        FROM attendance_breaks
        WHERE break_in IS NOT NULL
        GROUP BY attendance_id
    ) b ON b.attendance_id = a.id
    WHERE a.date = ?
";
$params = [$date];

if ($search !== "") {
    $sql .= " AND (e.employee_no LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.department LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY e.last_name ASC, e.first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

function attendanceLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Attendance</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/attendance-overview.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Attendance</h1><p>Review employee attendance, time records, breaks, and overtime.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="attendance-content">
            <div class="attendance-header">
                <div><span>PEOPLE OPERATIONS</span><h2>Attendance Register</h2></div>
                <strong><?= number_format(count($records)) ?> record<?= count($records) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="attendance-filters" method="get">
                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search employee or department">
                <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $statusOption): ?><option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= attendanceLabel($statusOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="attendance.php">Today</a>
            </form>
            <div class="attendance-table-wrap">
                <table class="attendance-table">
                    <thead><tr><th>Employee</th><th>Time In</th><th>Time Out</th><th>Break</th><th>Late</th><th>Overtime</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$records): ?>
                        <tr><td colspan="7" class="empty-state">No attendance records found for <?= htmlspecialchars(date("M d, Y", strtotime($date))) ?>.</td></tr>
                    <?php else: foreach ($records as $record): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($record["first_name"] . " " . $record["last_name"]) ?></strong><small><?= htmlspecialchars($record["employee_no"]) ?> · <?= htmlspecialchars($record["department"] ?: ($record["position"] ?: "Employee")) ?></small></td>
                            <td><?= $record["time_in"] ? htmlspecialchars(date("h:i A", strtotime($record["time_in"]))) : "-" ?></td>
                            <td><?= $record["time_out"] ? htmlspecialchars(date("h:i A", strtotime($record["time_out"]))) : "Active" ?></td>
                            <td><?= (int) $record["break_minutes"] ?> min</td>
                            <td><?= (int) $record["late_minutes"] ?> min</td>
                            <td><?= number_format((float) $record["overtime_hours"], 2) ?> hrs</td>
                            <td><span class="attendance-status <?= strtolower($record["status"]) ?>"><?= htmlspecialchars(attendanceLabel($record["status"])) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>