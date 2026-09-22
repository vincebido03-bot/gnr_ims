<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["DRAFT", "PROCESSING", "FINALIZED", "PAID", "CANCELLED"];

$sql = "
    SELECT p.payroll_no, p.period_start, p.period_end, p.total_gross,
           p.total_deductions, p.total_net, p.status, p.processed_at,
           COUNT(pi.id) AS employee_count
    FROM payroll p
    LEFT JOIN payroll_items pi ON pi.payroll_id = p.id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND p.payroll_no LIKE ?";
    $params[] = "%{$search}%";
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

$sql .= " GROUP BY p.id ORDER BY p.period_end DESC, p.payroll_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payrolls = $stmt->fetchAll();

function payrollLabel($value)
{
    return ucfirst(strtolower($value));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Payroll</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payroll-overview.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Payroll</h1><p>Review payroll periods, employee counts, and payment totals.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="payroll-content">
            <div class="payroll-header">
                <div><span>PEOPLE FINANCE</span><h2>Payroll Register</h2></div>
                <strong><?= number_format(count($payrolls)) ?> payroll period<?= count($payrolls) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="payroll-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search payroll number">
                <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $statusOption): ?><option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= payrollLabel($statusOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="payroll.php">Clear</a>
            </form>
            <div class="payroll-table-wrap">
                <table class="payroll-table">
                    <thead><tr><th>Payroll</th><th>Period</th><th>Employees</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$payrolls): ?>
                        <tr><td colspan="7" class="empty-state">No payroll periods found.</td></tr>
                    <?php else: foreach ($payrolls as $payroll): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($payroll["payroll_no"]) ?></strong><small><?= $payroll["processed_at"] ? "Processed " . htmlspecialchars(date("M d, Y", strtotime($payroll["processed_at"]))) : "Not processed" ?></small></td>
                            <td><?= htmlspecialchars(date("M d, Y", strtotime($payroll["period_start"]))) ?> - <?= htmlspecialchars(date("M d, Y", strtotime($payroll["period_end"]))) ?></td>
                            <td><?= number_format((int) $payroll["employee_count"]) ?></td>
                            <td>PHP <?= number_format((float) $payroll["total_gross"], 2) ?></td>
                            <td>PHP <?= number_format((float) $payroll["total_deductions"], 2) ?></td>
                            <td><strong>PHP <?= number_format((float) $payroll["total_net"], 2) ?></strong></td>
                            <td><span class="payroll-status <?= strtolower($payroll["status"]) ?>"><?= htmlspecialchars(payrollLabel($payroll["status"])) ?></span></td>
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