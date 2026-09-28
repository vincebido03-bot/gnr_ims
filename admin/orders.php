<?php

require_once "includes/header.php";

$orderActionMessage = "";
$orderActionType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["convert_quotation"])) {
    $quotationId = (int) ($_POST["quotation_id"] ?? 0);
    $orderNumberLockAcquired = false;

    try {
        if ($quotationId <= 0) {
            throw new RuntimeException("Please select an approved quotation.");
        }

        $pdo->beginTransaction();
        $quotationStmt = $pdo->prepare("SELECT q.*, i.inquiry_no, i.inquiry_type, i.description AS inquiry_description FROM quotations q INNER JOIN inquiries i ON i.id = q.inquiry_id WHERE q.id = ? AND q.status = 'APPROVED' LIMIT 1 FOR UPDATE");
        $quotationStmt->execute([$quotationId]);
        $quotation = $quotationStmt->fetch();

        if (!$quotation) {
            throw new RuntimeException("Only approved quotations can be converted to orders.");
        }
        if ($quotation["valid_until"] && $quotation["valid_until"] < date("Y-m-d")) {
            throw new RuntimeException("This quotation has expired and cannot be converted.");
        }

        $existingOrderStmt = $pdo->prepare("SELECT order_no FROM orders WHERE quotation_id = ? LIMIT 1");
        $existingOrderStmt->execute([$quotationId]);
        if ($existingOrderStmt->fetchColumn()) {
            throw new RuntimeException("This quotation has already been converted to an order.");
        }

        $orderYear = (int) date("Y");
        $orderNumberLockName = "gnr_order_numbers_" . $orderYear;
        acquireNumberingLock($pdo, $orderNumberLockName);
        $orderNumberLockAcquired = true;
        $orderNo = nextOrderNumber($pdo, $orderYear);

        $remarks = trim((string) ($quotation["notes"] ?? ""));
        $orderStmt = $pdo->prepare("INSERT INTO orders (order_no, quotation_id, customer_id, vehicle_id, order_date, subtotal, discount, total_amount, status, remarks, created_by) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, 'PENDING', ?, ?)");
        $orderStmt->execute([
            $orderNo,
            $quotationId,
            (int) $quotation["customer_id"],
            $quotation["vehicle_id"] ?: null,
            $quotation["subtotal"],
            $quotation["discount"],
            $quotation["total_amount"],
            $remarks !== "" ? $remarks : "Created from approved quotation " . $quotation["quotation_no"],
            $_SESSION["user_id"] ?? null,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, item_type, description, quantity, unit_price, amount, notes) SELECT ?, item_type, description, quantity, unit_price, amount, notes FROM quotation_items WHERE quotation_id = ?");
        $itemStmt->execute([$orderId, $quotationId]);

        $jobNo = null;
        if (strtoupper((string) $quotation["inquiry_type"]) !== "PRODUCT_INQUIRY") {
            $jobNo = "JOB-" . substr($orderNo, 4);
            $jobTitle = trim((string) ($quotation["inquiry_type"] ?? "")) ?: "Customer service order";
            $jobDescription = trim((string) ($quotation["inquiry_description"] ?? ""));
            $jobStmt = $pdo->prepare("INSERT INTO jobs (job_no, order_id, job_title, description, priority, status, created_by) VALUES (?, ?, ?, ?, 'NORMAL', 'PENDING', ?)");
            $jobStmt->execute([$jobNo, $orderId, $jobTitle, $jobDescription !== "" ? $jobDescription : null, $_SESSION["user_id"] ?? null]);
            $jobId = (int) $pdo->lastInsertId();

            $detailStmt = $pdo->prepare("INSERT INTO job_details (job_id, order_item_id, service_type, description, quantity, selling_price, customer_approved_price, created_by) SELECT ?, oi.id, oi.item_type, oi.description, oi.quantity, oi.unit_price, oi.unit_price, ? FROM order_items oi WHERE oi.order_id = ?");
            $detailStmt->execute([$jobId, $_SESSION["user_id"] ?? null, $orderId]);
        }

        $pdo->prepare("UPDATE inquiries SET status = 'CONVERTED' WHERE id = ? LIMIT 1")->execute([(int) $quotation["inquiry_id"]]);
        $pdo->commit();
        if ($orderNumberLockAcquired) {
            releaseNumberingLock($pdo, $orderNumberLockName);
            $orderNumberLockAcquired = false;
        }

        logAudit("ORDERS", "CREATE", "ORDER", $orderId, null, [
            "order_no" => $orderNo,
            "quotation_no" => $quotation["quotation_no"],
            "inquiry_no" => $quotation["inquiry_no"],
            "job_no" => $jobNo,
        ]);

        $orderActionMessage = $jobNo
            ? "Order " . $orderNo . " and job " . $jobNo . " were created from quotation " . $quotation["quotation_no"] . "."
            : "Product order " . $orderNo . " was created from quotation " . $quotation["quotation_no"] . ".";
        $orderActionType = "success";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($orderNumberLockAcquired) {
            releaseNumberingLock($pdo, $orderNumberLockName);
        }
        $orderActionMessage = $exception->getMessage();
        $orderActionType = "error";
    }
}

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["PENDING", "CONFIRMED", "IN_PROGRESS", "READY_FOR_QC", "READY_FOR_RELEASE", "COMPLETED", "CANCELLED"];
$approvedQuotations = $pdo->query("SELECT q.id, q.quotation_no, q.total_amount, c.fullname, i.inquiry_type FROM quotations q INNER JOIN customers c ON c.id = q.customer_id INNER JOIN inquiries i ON i.id = q.inquiry_id LEFT JOIN orders o ON o.quotation_id = q.id WHERE q.status = 'APPROVED' AND (q.valid_until IS NULL OR q.valid_until >= CURDATE()) AND o.id IS NULL ORDER BY q.quotation_date DESC, q.id DESC")->fetchAll();

$sql = "
    SELECT o.order_no, o.order_date, o.subtotal, o.discount, o.total_amount, o.status,
           o.remarks, c.customer_no, c.fullname,
           q.quotation_no, v.vehicle_no, CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no
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
            <?php if ($orderActionMessage): ?>
                <div class="order-action-message <?= htmlspecialchars($orderActionType) ?>">
                    <?= htmlspecialchars($orderActionMessage) ?>
                </div>
            <?php endif; ?>
            <div class="orders-header">
                <div><span>WORK INTAKE</span><h2>Order Register</h2></div>
                <strong><?= number_format(count($orders)) ?> order<?= count($orders) === 1 ? "" : "s" ?></strong>
            </div>
            <?php if (hasPermission("orders", "create")): ?>
            <div class="order-conversion-panel">
                <div class="order-conversion-heading">
                    <div><span>APPROVED WORK</span><h3>Convert quotation to order</h3></div>
                    <small><?= number_format(count($approvedQuotations)) ?> ready</small>
                </div>
                <form method="post" class="order-conversion-form">
                    <label class="sr-only" for="quotation_id">Approved quotation</label>
                    <select id="quotation_id" name="quotation_id" required <?= $approvedQuotations ? "" : "disabled" ?>>
                        <option value=""><?= $approvedQuotations ? "Select an approved quotation" : "No approved quotations ready" ?></option>
                        <?php foreach ($approvedQuotations as $quotation): ?>
                            <option value="<?= (int) $quotation["id"] ?>"><?= htmlspecialchars($quotation["quotation_no"] . " - " . $quotation["fullname"] . " - " . ($quotation["inquiry_type"] ?: "Service") . " - PHP " . number_format((float) $quotation["total_amount"], 2)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="convert_quotation" class="primary-button" <?= $approvedQuotations ? "" : "disabled" ?>>Create Order &amp; Job</button>
                </form>
            </div>
            <?php endif; ?>
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
                            <td><?= htmlspecialchars(trim($order["vehicle_name"] ?? "") ?: "-") ?><?php if ($order["vehicle_no"]): ?><small><?= htmlspecialchars($order["vehicle_no"]) ?></small><?php endif; ?><?php if ($order["plate_no"]): ?><small><?= htmlspecialchars($order["plate_no"]) ?></small><?php endif; ?></td>
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