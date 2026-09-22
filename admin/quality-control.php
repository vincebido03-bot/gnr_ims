<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["PENDING", "PASSED", "FAILED", "FOR_RECHECK"];

$sql = "
    SELECT qc.id, qc.status, qc.checklist, qc.findings, qc.corrective_action, qc.checked_at,
           j.job_no, j.job_title, o.order_no, c.fullname,
           CONCAT(e.first_name, ' ', e.last_name) AS checked_by_name
    FROM quality_checks qc
    INNER JOIN jobs j ON j.id = qc.job_id
    INNER JOIN orders o ON o.id = qc.order_id
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN users u ON u.id = qc.checked_by
    LEFT JOIN employees e ON e.id = u.employee_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (j.job_no LIKE ? OR j.job_title LIKE ? OR o.order_no LIKE ? OR c.fullname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND qc.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY FIELD(qc.status, 'PENDING', 'FOR_RECHECK', 'FAILED', 'PASSED'), qc.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$checks = $stmt->fetchAll();

function qcLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Quality Control</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/quality-control.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Quality Control</h1><p>Review completed work before release and customer handover.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="qc-content">
            <div class="qc-header">
                <div><span>RELEASE GATE</span><h2>Quality Check Register</h2></div>
                <strong><?= number_format(count($checks)) ?> check<?= count($checks) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="qc-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search job, order, or customer">
                <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $statusOption): ?><option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= qcLabel($statusOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="quality-control.php">Clear</a>
            </form>
            <div class="qc-table-wrap">
                <table class="qc-table">
                    <thead><tr><th>Job / Order</th><th>Customer</th><th>Findings</th><th>Corrective Action</th><th>Checked By</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$checks): ?>
                        <tr><td colspan="6" class="empty-state">No quality control records found.</td></tr>
                    <?php else: foreach ($checks as $check): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($check["job_no"]) ?></strong><small><?= htmlspecialchars($check["job_title"]) ?> · <?= htmlspecialchars($check["order_no"]) ?></small></td>
                            <td><?= htmlspecialchars($check["fullname"]) ?></td>
                            <td><?= htmlspecialchars($check["findings"] ?: "No findings recorded") ?></td>
                            <td><?= htmlspecialchars($check["corrective_action"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($check["checked_by_name"] ?: "Unassigned") ?><?php if ($check["checked_at"]): ?><small><?= htmlspecialchars(date("M d, Y h:i A", strtotime($check["checked_at"]))) ?></small><?php endif; ?></td>
                            <td><span class="qc-status <?= strtolower($check["status"]) ?>"><?= htmlspecialchars(qcLabel($check["status"])) ?></span></td>
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