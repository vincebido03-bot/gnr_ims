<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["PENDING", "CONFIRMED", "IN_PROGRESS", "READY_FOR_QC", "READY_FOR_RELEASE", "COMPLETED", "CANCELLED"];

$sql = "
    SELECT o.order_no, o.order_date, o.subtotal, o.discount, o.total_amount, o.status,
           o.remarks, c.customer_no, c.fullname,
           q.quotation_no, CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN quotations q ON q.id = o.quotation_id
    LEFT JOIN vehicles v ON v.id = o.vehicle_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (o.order_no LIKE ? OR q.quotation_no LIKE ? OR c.customer_no LIKE ? OR c.fullname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY o.order_date DESC, o.order_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

function orderLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Orders</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/orders.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Orders</h1><p>Track confirmed work orders through completion and release.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="orders-content">
            <div class="orders-header">
                <div><span>WORK INTAKE</span><h2>Order Register</h2></div>
                <strong><?= number_format(count($orders)) ?> order<?= count($orders) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="orders-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search order, quotation, or customer">
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= orderLabel($statusOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <a href="orders.php">Clear</a>
            </form>
            <div class="orders-table-wrap">
                <table class="orders-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Vehicle</th><th>Quotation</th><th>Total Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="empty-state">No order records found.</td></tr>
                    <?php else: foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($order["order_no"]) ?></strong><small><?= htmlspecialchars(date("M d, Y", strtotime($order["order_date"]))) ?></small></td>
                            <td><strong><?= htmlspecialchars($order["fullname"]) ?></strong><small><?= htmlspecialchars($order["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars(trim($order["vehicle_name"] ?? "") ?: "-") ?><?php if ($order["plate_no"]): ?><small><?= htmlspecialchars($order["plate_no"]) ?></small><?php endif; ?></td>
                            <td><?= htmlspecialchars($order["quotation_no"] ?: "-") ?></td>
                            <td>PHP <?= number_format((float) $order["total_amount"], 2) ?></td>
                            <td><span class="order-status <?= strtolower($order["status"]) ?>"><?= htmlspecialchars(orderLabel($order["status"])) ?></span></td>
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