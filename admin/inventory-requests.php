<?php

require_once "includes/header.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestId = (int) ($_POST["request_id"] ?? 0);
    $newStatus = strtoupper($_POST["request_status"] ?? "");
    if ($requestId > 0 && in_array($newStatus, ["APPROVED", "REJECTED"], true)) {
        $stmt = $pdo->prepare("UPDATE inventory_requests SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND status='PENDING'");
        $stmt->execute([$newStatus, $_SESSION["user_id"], $requestId]);
    }
    header("Location: inventory-requests.php");
    exit;
}

$requests = $pdo->query("SELECT r.id,r.quantity_requested,r.reason,r.status,r.created_at,i.item_code,i.item_name,i.unit,e.first_name,e.last_name FROM inventory_requests r JOIN inventory_items i ON i.id=r.inventory_item_id JOIN employees e ON e.id=r.employee_id ORDER BY FIELD(r.status,'PENDING','APPROVED','REJECTED','FULFILLED'),r.created_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>GNR IMS - Inventory Requests</title><link rel="stylesheet" href="css/sidebar.css"><link rel="stylesheet" href="css/dashboard.css"><link rel="stylesheet" href="css/inventory-requests.css"></head>
<body><div class="admin-layout"><?php require_once "includes/sidebar.php"; ?><main class="main-content"><header class="topbar"><div><h1>Inventory Requests</h1><p>Review employee requests for supplies and materials.</p></div><div class="user-info"><div class="user-avatar"><?= strtoupper(substr($user["username"],0,1)) ?></div><div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div></div></header><section class="inventory-requests-content"><div class="requests-heading"><span>STOCK CONTROL</span><h2>Employee Requests</h2></div><div class="requests-table-wrap"><table class="requests-table"><thead><tr><th>Employee</th><th>Item</th><th>Quantity</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if (!$requests): ?><tr><td colspan="6" class="empty-state">No inventory requests found.</td></tr><?php else: foreach ($requests as $request): ?><tr><td><?= htmlspecialchars($request["first_name"] . " " . $request["last_name"]) ?></td><td><strong><?= htmlspecialchars($request["item_name"]) ?></strong><small><?= htmlspecialchars($request["item_code"]) ?></small></td><td><?= htmlspecialchars($request["quantity_requested"] . " " . $request["unit"]) ?></td><td><?= htmlspecialchars($request["reason"] ?: "-") ?></td><td><span class="request-status <?= strtolower($request["status"]) ?>"><?= htmlspecialchars($request["status"]) ?></span></td><td><?php if ($request["status"] === "PENDING"): ?><form method="post"><input type="hidden" name="request_id" value="<?= (int) $request["id"] ?>"><button name="request_status" value="APPROVED">Approve</button><button class="reject" name="request_status" value="REJECTED">Reject</button></form><?php else: ?>-<?php endif; ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section></main></div></body></html>