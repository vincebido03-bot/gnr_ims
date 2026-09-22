<?php

require_once "../admin/includes/auth.php";
requireLogin();

if (!hasRole("EMPLOYEE")) {
    header("Location: ../admin/dashboard.php");
    exit;
}

$employeeId = (int) ($_SESSION["employee_id"] ?? 0);
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $itemId = (int) ($_POST["inventory_item_id"] ?? 0);
    $quantity = (float) ($_POST["quantity_requested"] ?? 0);
    $reason = trim($_POST["reason"] ?? "");

    if ($itemId <= 0 || $quantity <= 0) {
        $message = "Select an item and enter a valid quantity.";
        $messageType = "error";
    } else {
        $stmt = $pdo->prepare("INSERT INTO inventory_requests (inventory_item_id, employee_id, quantity_requested, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$itemId, $employeeId, $quantity, $reason ?: null]);
        $requestId = $pdo->lastInsertId();
        createNotification("INVENTORY_REQUEST", "New Inventory Request", "An employee requested inventory replenishment.", $employeeId, "INVENTORY_REQUEST", $requestId);
        $message = "Inventory request submitted to Admin.";
        $messageType = "success";
    }
}

$items = $pdo->query("SELECT id, item_code, item_name, unit, current_stock FROM inventory_items WHERE status='ACTIVE' ORDER BY item_name")->fetchAll();
$requestsStmt = $pdo->prepare("SELECT r.quantity_requested, r.reason, r.status, r.created_at, i.item_name, i.unit FROM inventory_requests r JOIN inventory_items i ON i.id=r.inventory_item_id WHERE r.employee_id=? ORDER BY r.id DESC");
$requestsStmt->execute([$employeeId]);
$requests = $requestsStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Inventory Request</title>
    <link rel="stylesheet" href="css/employee.css">
    <link rel="stylesheet" href="css/inventory-request.css">
</head>
<body>
<div class="employee-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="employee-main-content">
        <header class="employee-topbar"><div><h1>Inventory Request</h1><p>Request supplies that are running low or needed for work.</p></div></header>
        <section class="inventory-request-content">
            <?php if ($message): ?><div class="request-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="request-card">
                <span>SUPPLY REQUEST</span><h2>Request Inventory</h2>
                <form method="post">
                    <label>Item<select name="inventory_item_id" required><option value="">Select item</option><?php foreach ($items as $item): ?><option value="<?= (int) $item["id"] ?>"><?= htmlspecialchars($item["item_code"] . " - " . $item["item_name"] . " (" . $item["current_stock"] . " " . $item["unit"] . ")") ?></option><?php endforeach; ?></select></label>
                    <label>Quantity needed<input type="number" name="quantity_requested" min="0.01" step="0.01" required></label>
                    <label>Reason <span>(Optional)</span><textarea name="reason" rows="3" placeholder="What is this needed for?"></textarea></label>
                    <button type="submit">Submit Request</button>
                </form>
            </div>
            <div class="request-card"><span>REQUEST HISTORY</span><h2>My Requests</h2><div class="request-list"><?php if (!$requests): ?><p class="muted">No requests yet.</p><?php else: foreach ($requests as $request): ?><div><strong><?= htmlspecialchars($request["item_name"]) ?></strong><small><?= htmlspecialchars($request["quantity_requested"] . " " . $request["unit"]) ?> · <?= htmlspecialchars($request["status"]) ?> · <?= htmlspecialchars(date("M d, Y", strtotime($request["created_at"]))) ?></small></div><?php endforeach; endif; ?></div></div>
        </section>
    </main>
</div>
</body>
</html>