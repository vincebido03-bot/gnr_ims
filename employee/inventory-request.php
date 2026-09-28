<?php

require_once "../admin/includes/auth.php";
requireLogin();

if (!hasRole("EMPLOYEE")) {
    header("Location: ../admin/dashboard.php");
    exit;
}

requirePermission("inventory_requests", "view");

$employeeId = (int) ($_SESSION["employee_id"] ?? 0);
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requirePermission("inventory_requests", "create");
    $requestType = (string) ($_POST["request_type"] ?? "existing");
    $itemId = (int) ($_POST["inventory_item_id"] ?? 0);
    $requestedItemName = trim((string) ($_POST["requested_item_name"] ?? ""));
    $requestedCategoryId = (int) ($_POST["requested_category_id"] ?? 0);
    $requestedUnit = trim((string) ($_POST["requested_unit"] ?? ""));
    $quantity = (float) ($_POST["quantity_requested"] ?? 0);
    $reason = trim((string) ($_POST["reason"] ?? ""));
    $requestItemName = "";
    $requestCategory = null;

    if (!in_array($requestType, ["existing", "unlisted"], true) || $quantity <= 0) {
        $message = "Choose a valid request type and enter a valid quantity.";
        $messageType = "error";
    } else {
        if ($requestType === "existing") {
            $itemStmt = $pdo->prepare("SELECT item_name, unit FROM inventory_items WHERE id = ? AND status = 'ACTIVE' LIMIT 1");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();
            if (!$item) {
                $message = "Select an active inventory item.";
                $messageType = "error";
            } else {
                $requestItemName = $item["item_name"];
            }
        } else {
            $categoryStmt = $pdo->prepare("SELECT category_name FROM inventory_categories WHERE id = ? LIMIT 1");
            $categoryStmt->execute([$requestedCategoryId]);
            $requestCategory = $categoryStmt->fetchColumn();
            if ($requestedItemName === "" || strlen($requestedItemName) > 255 || !$requestCategory || $requestedUnit === "" || strlen($requestedUnit) > 30) {
                $message = "Enter an item name, category, and unit for the unlisted item.";
                $messageType = "error";
            } else {
                $requestItemName = $requestedItemName;
            }
        }

        if ($messageType !== "error") {
            $stmt = $pdo->prepare("INSERT INTO inventory_requests (inventory_item_id, requested_item_name, requested_category, requested_unit, employee_id, quantity_requested, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $requestType === "existing" ? $itemId : null,
                $requestType === "unlisted" ? $requestItemName : null,
                $requestType === "unlisted" ? $requestCategory : null,
                $requestType === "unlisted" ? $requestedUnit : null,
                $employeeId,
                $quantity,
                $reason !== "" ? $reason : null,
            ]);
            $requestId = $pdo->lastInsertId();
            $notificationMessage = $requestType === "unlisted"
                ? "An employee suggested an unlisted item: " . $requestItemName . "."
                : "An employee requested inventory replenishment: " . $requestItemName . ".";
            createNotification("INVENTORY_REQUEST", "New Inventory Request", $notificationMessage, $employeeId, "INVENTORY_REQUEST", $requestId);
            $message = $requestType === "unlisted" ? "Unlisted item suggestion submitted for Admin review." : "Inventory request submitted to Admin.";
            $messageType = "success";
        }
    }
}

$items = $pdo->query("SELECT i.id, i.item_code, i.item_name, i.unit, i.current_stock, c.category_name FROM inventory_items i LEFT JOIN inventory_categories c ON c.id=i.category_id WHERE i.status='ACTIVE' ORDER BY c.category_name, i.item_name")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM inventory_categories ORDER BY category_name")->fetchAll();
$requestsStmt = $pdo->prepare("SELECT r.quantity_requested, r.reason, r.status, r.created_at, COALESCE(r.requested_item_name, i.item_name) AS item_name, COALESCE(r.requested_unit, i.unit) AS unit, r.requested_category FROM inventory_requests r LEFT JOIN inventory_items i ON i.id=r.inventory_item_id WHERE r.employee_id=? ORDER BY r.id DESC");
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
    <link rel="stylesheet" href="css/inventory-request.css?v=20260929-search-unlisted">
</head>
<body>
<div class="employee-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="employee-main">
        <header class="employee-topbar"><div><h1>Inventory Request</h1><p>Request supplies that are running low or needed for work.</p></div></header>
        <section class="inventory-request-content">
            <?php if ($message): ?><div class="request-message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="request-card">
                <span>SUPPLY REQUEST</span><h2>Request Inventory</h2>
                <form method="post">
                    <fieldset class="request-type-options">
                        <legend>Request type</legend>
                        <label><input type="radio" name="request_type" value="existing" checked> Existing inventory item</label>
                        <label><input type="radio" name="request_type" value="unlisted"> Item is not listed</label>
                    </fieldset>
                    <div id="existing-item-fields" class="request-item-fields">
                        <label for="item-search">Search items<input id="item-search" type="search" placeholder="Search item name or code"></label>
                        <label for="item-category-filter">Category filter<select id="item-category-filter"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= htmlspecialchars(mb_strtolower($category["category_name"])) ?>"><?= htmlspecialchars($category["category_name"]) ?></option><?php endforeach; ?></select></label>
                        <label for="inventory-item-select">Inventory item<select id="inventory-item-select" name="inventory_item_id" required><option value="">Select item</option><?php foreach ($items as $item): ?><?php $searchText = mb_strtolower($item["item_code"] . " " . $item["item_name"] . " " . ($item["category_name"] ?? "")); ?><option value="<?= (int) $item["id"] ?>" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES) ?>" data-category="<?= htmlspecialchars(mb_strtolower($item["category_name"] ?? ""), ENT_QUOTES) ?>"><?= htmlspecialchars("[" . ($item["category_name"] ?: "Uncategorized") . "] " . $item["item_code"] . " - " . $item["item_name"] . " (" . $item["current_stock"] . " " . $item["unit"] . ")") ?></option><?php endforeach; ?></select><small id="item-search-empty" hidden>No matching items. Choose “Item is not listed” to suggest one.</small></label>
                    </div>
                    <div id="unlisted-item-fields" class="request-item-fields" hidden>
                        <label for="requested-item-name">Item name<input id="requested-item-name" name="requested_item_name" type="text" maxlength="255" placeholder="e.g. Custom sanding disc" disabled required></label>
                        <label for="requested-category">Category<select id="requested-category" name="requested_category_id" disabled required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category["id"] ?>"><?= htmlspecialchars($category["category_name"]) ?></option><?php endforeach; ?></select></label>
                        <label for="requested-unit">Unit<input id="requested-unit" name="requested_unit" type="text" maxlength="30" placeholder="pc, box, liter" disabled required></label>
                    </div>
                    <label>Quantity needed<input type="number" name="quantity_requested" min="0.01" step="0.01" required></label>
                    <label>Reason <span>(Optional)</span><textarea name="reason" rows="3" placeholder="What is this needed for?"></textarea></label>
                    <button type="submit">Submit Request</button>
                </form>
            </div>
            <div class="request-card"><span>REQUEST HISTORY</span><h2>My Requests</h2><div class="request-list"><?php if (!$requests): ?><p class="muted">No requests yet.</p><?php else: foreach ($requests as $request): ?><div><strong><?= htmlspecialchars($request["item_name"]) ?><?php if ($request["requested_category"]): ?><em><?= htmlspecialchars($request["requested_category"]) ?> · Unlisted item</em><?php endif; ?></strong><small><?= htmlspecialchars($request["quantity_requested"] . " " . $request["unit"]) ?> · <?= htmlspecialchars($request["status"]) ?> · <?= htmlspecialchars(date("M d, Y", strtotime($request["created_at"]))) ?></small></div><?php endforeach; endif; ?></div></div>
        </section>
    </main>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const typeOptions = Array.from(document.querySelectorAll('input[name="request_type"]'));
    const existingFields = document.getElementById("existing-item-fields");
    const unlistedFields = document.getElementById("unlisted-item-fields");
    const itemSelect = document.getElementById("inventory-item-select");
    const searchInput = document.getElementById("item-search");
    const categoryFilter = document.getElementById("item-category-filter");
    const emptyMessage = document.getElementById("item-search-empty");

    function updateRequestType() {
        const unlisted = document.querySelector('input[name="request_type"]:checked')?.value === "unlisted";
        existingFields.hidden = unlisted;
        unlistedFields.hidden = !unlisted;
        itemSelect.disabled = unlisted;
        itemSelect.required = !unlisted;
        unlistedFields.querySelectorAll("input, select").forEach(function (field) {
            field.disabled = !unlisted;
        });
    }

    function filterItems() {
        const query = searchInput.value.trim().toLowerCase();
        const category = categoryFilter.value;
        let visibleCount = 0;
        Array.from(itemSelect.options).slice(1).forEach(function (option) {
            const matches = option.dataset.search.includes(query) && (!category || option.dataset.category === category);
            option.hidden = !matches;
            if (matches) visibleCount++;
        });
        emptyMessage.hidden = visibleCount > 0;
    }

    typeOptions.forEach(function (option) {
        option.addEventListener("change", updateRequestType);
    });
    searchInput.addEventListener("input", filterItems);
    categoryFilter.addEventListener("change", filterItems);
    updateRequestType();
});
</script>
</body>
</html>