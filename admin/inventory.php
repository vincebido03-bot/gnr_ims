<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$categoryId = (int) ($_GET["category_id"] ?? 0);
$status = strtoupper(trim($_GET["status"] ?? ""));

$categories = $pdo
    ->query("SELECT id, category_name FROM inventory_categories ORDER BY category_name ASC")
    ->fetchAll();

$sql = "
    SELECT
        i.id,
        i.item_code,
        i.item_name,
        i.unit,
        i.current_stock,
        i.reserved_stock,
        i.reorder_level,
        i.unit_cost,
        i.status,
        c.category_name
    FROM inventory_items i
    LEFT JOIN inventory_categories c ON c.id = i.category_id
    WHERE 1 = 1
";

$params = [];

if ($search !== "") {
    $sql .= " AND (i.item_code LIKE ? OR i.item_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($categoryId > 0) {
    $sql .= " AND i.category_id = ?";
    $params[] = $categoryId;
}

if (in_array($status, ["ACTIVE", "INACTIVE"], true)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.item_name ASC, i.item_code ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

function inventoryNumber($value)
{
    return rtrim(rtrim(number_format((float) $value, 2), "0"), ".");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Inventory</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/inventory.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h1>Inventory</h1>
                <p>Monitor materials, stock levels, and item costs.</p>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div>
                    <strong><?= htmlspecialchars($user["username"]) ?></strong>
                    <small><?= htmlspecialchars($user["role_name"]) ?></small>
                </div>
            </div>
        </header>

        <section class="inventory-content">
            <div class="inventory-header">
                <div>
                    <span>STOCK REGISTER</span>
                    <h2>Inventory Items</h2>
                </div>
                <strong><?= number_format(count($items)) ?> item<?= count($items) === 1 ? "" : "s" ?></strong>
            </div>

            <form class="inventory-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search code or item name">
                <select name="category_id">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category["id"] ?>" <?= $categoryId === (int) $category["id"] ? "selected" : "" ?>>
                            <?= htmlspecialchars($category["category_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="ACTIVE" <?= $status === "ACTIVE" ? "selected" : "" ?>>Active</option>
                    <option value="INACTIVE" <?= $status === "INACTIVE" ? "selected" : "" ?>>Inactive</option>
                </select>
                <button type="submit">Filter</button>
                <a href="inventory.php">Clear</a>
            </form>

            <div class="inventory-table-wrap">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Available</th>
                            <th>Reserved</th>
                            <th>Reorder Level</th>
                            <th>Unit Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$items): ?>
                            <tr>
                                <td colspan="7" class="empty-state">No inventory items found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <?php $isLowStock = (float) $item["current_stock"] <= (float) $item["reorder_level"]; ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item["item_name"]) ?></strong>
                                        <small><?= htmlspecialchars($item["item_code"]) ?> · <?= htmlspecialchars($item["unit"]) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($item["category_name"] ?? "Uncategorized") ?></td>
                                    <td class="<?= $isLowStock ? "low-stock" : "" ?>">
                                        <?= inventoryNumber($item["current_stock"]) ?>
                                        <?= $isLowStock ? "<small>Low stock</small>" : "" ?>
                                    </td>
                                    <td><?= inventoryNumber($item["reserved_stock"]) ?></td>
                                    <td><?= inventoryNumber($item["reorder_level"]) ?></td>
                                    <td>₱<?= number_format((float) $item["unit_cost"], 2) ?></td>
                                    <td><span class="status <?= strtolower($item["status"]) ?>"><?= htmlspecialchars($item["status"]) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>