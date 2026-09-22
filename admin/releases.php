<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["PENDING", "READY", "RELEASED", "CANCELLED"];

$sql = "
    SELECT r.release_no, r.release_date, r.payment_verified, r.qc_verified,
           r.status, r.remarks, o.order_no, c.customer_no, c.fullname,
           CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no,
           CONCAT(e.first_name, ' ', e.last_name) AS released_by_name
    FROM releases r
    INNER JOIN orders o ON o.id = r.order_id
    INNER JOIN customers c ON c.id = r.customer_id
    LEFT JOIN vehicles v ON v.id = r.vehicle_id
    LEFT JOIN users u ON u.id = r.released_by
    LEFT JOIN employees e ON e.id = u.employee_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (r.release_no LIKE ? OR o.order_no LIKE ? OR c.customer_no LIKE ? OR c.fullname LIKE ? OR v.plate_no LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY FIELD(r.status, 'PENDING', 'READY', 'RELEASED', 'CANCELLED'), r.release_date DESC, r.release_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$releases = $stmt->fetchAll();

function releaseLabel($value)
{
    return ucfirst(strtolower($value));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Releases</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/releases.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Releases</h1><p>Confirm payment and quality gates before customer handover.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="releases-content">
            <div class="releases-header">
                <div><span>HANDOVER CONTROL</span><h2>Release Register</h2></div>
                <strong><?= number_format(count($releases)) ?> release<?= count($releases) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="releases-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search release, order, customer, or plate">
                <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $statusOption): ?><option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= releaseLabel($statusOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="releases.php">Clear</a>
            </form>
            <div class="releases-table-wrap">
                <table class="releases-table">
                    <thead><tr><th>Release</th><th>Customer / Order</th><th>Vehicle</th><th>Payment</th><th>Quality Control</th><th>Released By</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$releases): ?>
                        <tr><td colspan="7" class="empty-state">No release records found.</td></tr>
                    <?php else: foreach ($releases as $release): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($release["release_no"]) ?></strong><small><?= $release["release_date"] ? htmlspecialchars(date("M d, Y h:i A", strtotime($release["release_date"]))) : "Not released" ?></small></td>
                            <td><strong><?= htmlspecialchars($release["fullname"]) ?></strong><small><?= htmlspecialchars($release["customer_no"]) ?> · <?= htmlspecialchars($release["order_no"]) ?></small></td>
                            <td><?= htmlspecialchars(trim($release["vehicle_name"] ?? "") ?: "-") ?><?php if ($release["plate_no"]): ?><small><?= htmlspecialchars($release["plate_no"]) ?></small><?php endif; ?></td>
                            <td><span class="gate <?= $release["payment_verified"] ? "verified" : "pending" ?>"><?= $release["payment_verified"] ? "Verified" : "Pending" ?></span></td>
                            <td><span class="gate <?= $release["qc_verified"] ? "verified" : "pending" ?>"><?= $release["qc_verified"] ? "Passed" : "Pending" ?></span></td>
                            <td><?= htmlspecialchars($release["released_by_name"] ?: "Unassigned") ?></td>
                            <td><span class="release-status <?= strtolower($release["status"]) ?>"><?= htmlspecialchars(releaseLabel($release["status"])) ?></span></td>
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