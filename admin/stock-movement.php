<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$movementType = strtoupper(trim($_GET["movement_type"] ?? ""));
$movementTypes = [
    "STOCK_IN" => "Stock In",
    "STOCK_OUT" => "Stock Out",
    "RESERVE" => "Reserve",
    "RELEASE_RESERVATION" => "Release Reservation",
    "ADJUSTMENT" => "Adjustment"
];

$sql = "
    SELECT sm.*, i.item_code, i.item_name, i.unit,
           CONCAT(e.first_name, ' ', e.last_name) AS performer_name
    FROM stock_movements sm
    INNER JOIN inventory_items i ON i.id = sm.inventory_item_id
    LEFT JOIN users u ON u.id = sm.performed_by
    LEFT JOIN employees e ON e.id = u.employee_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (i.item_code LIKE ? OR i.item_name LIKE ? OR sm.reference_no LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%"];
}

if (isset($movementTypes[$movementType])) {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $movementType;
}

$sql .= " ORDER BY sm.created_at DESC, sm.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

function movementNumber($value)
{
    return rtrim(rtrim(number_format((float) $value, 2), "0"), ".");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Stock Movement</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/stock-movement.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Stock Movement</h1>
                <p>Review inventory receipts, releases, reservations, and adjustments.</p>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="movement-content">
            <div class="movement-header">
                <div><span>INVENTORY LEDGER</span><h2>Movement History</h2></div>
                <strong><?= number_format(count($movements)) ?> record<?= count($movements) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="movement-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search item or reference no.">
                <select name="movement_type">
                    <option value="">All movement types</option>
                    <?php foreach ($movementTypes as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $movementType === $value ? "selected" : "" ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <a href="stock-movement.php">Clear</a>
            </form>
            <div class="movement-table-wrap">
                <table class="movement-table">
                    <thead><tr><th>Date</th><th>Item</th><th>Movement</th><th>Quantity</th><th>Reference</th><th>Total Cost</th><th>Performed By</th></tr></thead>
                    <tbody>
                    <?php if (!$movements): ?>
                        <tr><td colspan="7" class="empty-state">No stock movement records found.</td></tr>
                    <?php else: foreach ($movements as $movement): ?>
                        <tr>
                            <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($movement["created_at"]))) ?></td>
                            <td><strong><?= htmlspecialchars($movement["item_name"]) ?></strong><small><?= htmlspecialchars($movement["item_code"]) ?></small></td>
                            <td><span class="movement-type <?= strtolower($movement["movement_type"]) ?>"><?= htmlspecialchars($movementTypes[$movement["movement_type"]] ?? $movement["movement_type"]) ?></span></td>
                            <td><?= movementNumber($movement["quantity"]) ?> <?= htmlspecialchars($movement["unit"]) ?></td>
                            <td><?= htmlspecialchars($movement["reference_no"] ?: "-") ?></td>
                            <td>PHP <?= number_format((float) $movement["total_cost"], 2) ?></td>
                            <td><?= htmlspecialchars($movement["performer_name"] ?: "System") ?></td>
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