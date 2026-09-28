<?php

require_once "includes/header.php";

$quotationActionMessage = "";
$quotationActionType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_quotation"])) {
    $inquiryId = (int) ($_POST["inquiry_id"] ?? 0);
    $version = max(1, (int) ($_POST["version"] ?? 1));
    $quotationDate = trim((string) ($_POST["quotation_date"] ?? date("Y-m-d")));
    $validUntil = trim((string) ($_POST["valid_until"] ?? ""));
    $discount = round((float) ($_POST["discount"] ?? 0), 2);
    $tax = 0.00;
    $status = strtoupper(trim((string) ($_POST["status"] ?? "DRAFT")));
    $notes = trim((string) ($_POST["notes"] ?? ""));

    try {
        $itemTypes = $_POST["item_type"] ?? [];
        $itemDescriptions = $_POST["item_description"] ?? [];
        $itemQuantities = $_POST["item_quantity"] ?? [];
        $itemUnitPrices = $_POST["item_unit_price"] ?? [];
        if (!is_array($itemTypes) || !is_array($itemDescriptions) || !is_array($itemQuantities) || !is_array($itemUnitPrices)) {
            throw new RuntimeException("Quotation line items are invalid.");
        }

        $quotationItems = [];
        $subtotal = 0.0;
        $itemCount = max(count($itemTypes), count($itemDescriptions), count($itemQuantities), count($itemUnitPrices));
        $validItemTypes = ["SERVICE", "PRODUCT", "PART", "MATERIAL", "OTHER"];
        for ($index = 0; $index < $itemCount; $index++) {
            $itemType = strtoupper(trim((string) ($itemTypes[$index] ?? "SERVICE")));
            $description = trim((string) ($itemDescriptions[$index] ?? ""));
            $quantityValue = trim((string) ($itemQuantities[$index] ?? ""));
            $unitPriceValue = trim((string) ($itemUnitPrices[$index] ?? ""));

            if ($description === "" && $unitPriceValue === "") {
                continue;
            }
            if ($description === "" || !is_numeric($quantityValue) || !is_numeric($unitPriceValue)) {
                throw new RuntimeException("Complete each line item description, quantity, and unit price.");
            }
            if (!in_array($itemType, $validItemTypes, true)) {
                throw new RuntimeException("Select a valid line item type.");
            }

            $quantity = round((float) $quantityValue, 2);
            $unitPrice = round((float) $unitPriceValue, 2);
            if ($quantity <= 0 || $unitPrice < 0) {
                throw new RuntimeException("Line item quantities must be positive and prices cannot be negative.");
            }

            $amount = round($quantity * $unitPrice, 2);
            $subtotal = round($subtotal + $amount, 2);
            $quotationItems[] = [
                "item_type" => $itemType,
                "description" => $description,
                "quantity" => $quantity,
                "unit_price" => $unitPrice,
                "amount" => $amount,
            ];
        }

        if (!$quotationItems) {
            throw new RuntimeException("Add at least one quotation line item.");
        }
        if ($discount < 0 || $discount > $subtotal) {
            throw new RuntimeException("Discount must be non-negative and cannot exceed the subtotal.");
        }
        $totalAmount = round($subtotal - $discount, 2);

        if ($inquiryId <= 0) {
            throw new RuntimeException("Please select an inquiry to quote.");
        }

        $inquiryStmt = $pdo->prepare("SELECT i.id, i.customer_id, i.vehicle_id, i.inquiry_type, i.description, c.fullname, c.customer_no, v.vehicle_no, v.brand, v.model, v.plate_no FROM inquiries i INNER JOIN customers c ON c.id = i.customer_id LEFT JOIN vehicles v ON v.id = i.vehicle_id WHERE i.id = ? LIMIT 1");
        $inquiryStmt->execute([$inquiryId]);
        $inquiry = $inquiryStmt->fetch();

        if (!$inquiry) {
            throw new RuntimeException("Inquiry record not found.");
        }

        $validStatuses = ["DRAFT", "PREPARED", "SENT", "APPROVED", "REJECTED", "EXPIRED", "CANCELLED"];
        if (!in_array($status, $validStatuses, true)) {
            $status = "DRAFT";
        }

        do {
            $quotationNo = "QTN-" . date("YmdHis") . random_int(100, 999);
            $duplicateStmt = $pdo->prepare("SELECT id FROM quotations WHERE quotation_no = ? LIMIT 1");
            $duplicateStmt->execute([$quotationNo]);
            if (!$duplicateStmt->fetchColumn()) {
                break;
            }
        } while (true);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO quotations (quotation_no, inquiry_id, customer_id, vehicle_id, version, quotation_date, valid_until, subtotal, discount, tax, total_amount, status, notes, prepared_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $quotationNo,
            $inquiryId,
            (int) $inquiry["customer_id"],
            $inquiry["vehicle_id"] ?: null,
            $version,
            $quotationDate,
            $validUntil !== "" ? $validUntil : null,
            $subtotal,
            $discount,
            $tax,
            $totalAmount,
            $status,
            $notes !== "" ? $notes : null,
            $_SESSION["user_id"] ?? null,
        ]);

        $quotationId = (int) $pdo->lastInsertId();
        $itemStmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, item_type, description, quantity, unit_price, amount) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($quotationItems as $item) {
            $itemStmt->execute([
                $quotationId,
                $item["item_type"],
                $item["description"],
                $item["quantity"],
                $item["unit_price"],
                $item["amount"],
            ]);
        }
        $pdo->prepare("UPDATE inquiries SET status = 'QUOTATION_PREPARED' WHERE id = ? LIMIT 1")->execute([$inquiryId]);
        $pdo->commit();

        logAudit("QUOTATIONS", "CREATE", "QUOTATION", $quotationId, null, [
            "quotation_no" => $quotationNo,
            "inquiry_id" => $inquiryId,
            "customer_id" => $inquiry["customer_id"],
            "vehicle_id" => $inquiry["vehicle_id"] ?? null,
            "status" => $status,
            "total_amount" => $totalAmount
        ]);

        $quotationActionMessage = "Quotation " . $quotationNo . " was created successfully.";
        $quotationActionType = "success";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $quotationActionMessage = $exception->getMessage();
        $quotationActionType = "error";
    }
}

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["DRAFT", "PREPARED", "SENT", "APPROVED", "REJECTED", "EXPIRED", "CANCELLED"];
$inquiryOptions = $pdo->query("SELECT i.id, i.inquiry_no, i.inquiry_type, i.description, c.fullname, c.customer_no, v.vehicle_no, v.brand, v.model, v.plate_no FROM inquiries i INNER JOIN customers c ON c.id = i.customer_id LEFT JOIN vehicles v ON v.id = i.vehicle_id ORDER BY i.created_at DESC")->fetchAll();

$sql = "
    SELECT q.quotation_no, q.version, q.quotation_date, q.valid_until,
           q.subtotal, q.discount, q.tax, q.total_amount, q.status,
           c.customer_no, c.fullname,
           v.vehicle_no, CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no,
           i.inquiry_no
    FROM quotations q
    INNER JOIN customers c ON c.id = q.customer_id
    INNER JOIN inquiries i ON i.id = q.inquiry_id
    LEFT JOIN vehicles v ON v.id = q.vehicle_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (q.quotation_no LIKE ? OR i.inquiry_no LIKE ? OR c.customer_no LIKE ? OR c.fullname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND q.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY q.quotation_date DESC, q.quotation_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotations = $stmt->fetchAll();

function quotationLabel($value)
{
    return ucfirst(strtolower($value));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Quotations</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/quotations.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Quotations</h1><p>Review pricing proposals and their approval status.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="quotations-content">
            <div class="quotations-header">
                <div><span>SALES PIPELINE</span><h2>Quotation Register</h2></div>
                <strong><?= number_format(count($quotations)) ?> quotation<?= count($quotations) === 1 ? "" : "s" ?></strong>
            </div>
            <?php if (hasPermission("quotations", "create")): ?>
            <div class="quotation-action-panel">
                <div class="quotation-panel-header">
                    <h3>Create New Quotation</h3>
                </div>
                <form method="post" class="quotation-form">
                    <div class="quotation-form-grid">
                        <div class="quotation-form-field">
                            <label for="inquiry_id">Inquiry</label>
                            <select id="inquiry_id" name="inquiry_id" required>
                                <option value="">Select an inquiry</option>
                                <?php foreach ($inquiryOptions as $inquiry): ?>
                                    <option value="<?= (int) $inquiry["id"] ?>"><?= htmlspecialchars($inquiry["inquiry_no"] . " - " . $inquiry["fullname"] . " - " . ($inquiry["vehicle_no"] ? $inquiry["vehicle_no"] . " - " : "") . ($inquiry["inquiry_type"] ?: "Service request")) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="quotation-form-field">
                            <label for="version">Version</label>
                            <input id="version" name="version" type="number" min="1" value="1" required>
                        </div>
                        <div class="quotation-form-field">
                            <label for="quotation_date">Quotation Date</label>
                            <input id="quotation_date" name="quotation_date" type="date" value="<?= htmlspecialchars(date("Y-m-d")) ?>" required>
                        </div>
                        <div class="quotation-form-field">
                            <label for="valid_until">Valid Until</label>
                            <input id="valid_until" name="valid_until" type="date">
                        </div>
                        <div class="quotation-items-field">
                            <div class="quotation-items-heading">
                                <label>Quotation Items</label>
                                <button type="button" id="add-quotation-item" class="secondary-button">Add item</button>
                            </div>
                            <div class="quotation-items-table-wrap">
                                <table class="quotation-items-table">
                                    <thead><tr><th>Type</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Amount</th><th></th></tr></thead>
                                    <tbody id="quotation-item-rows">
                                        <tr class="quotation-item-row">
                                            <td><select name="item_type[]"><option value="SERVICE">Service</option><option value="PRODUCT">Product</option><option value="PART">Part</option><option value="MATERIAL">Material</option><option value="OTHER">Other</option></select></td>
                                            <td><input name="item_description[]" type="text" maxlength="255" placeholder="Describe service or item"></td>
                                            <td><input name="item_quantity[]" type="number" min="0.01" step="0.01" value="1"></td>
                                            <td><input name="item_unit_price[]" type="number" min="0" step="0.01" placeholder="0.00"></td>
                                            <td class="quotation-item-amount">PHP 0.00</td>
                                            <td><button type="button" class="remove-quotation-item" aria-label="Remove item">Remove</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <template id="quotation-item-template">
                                <tr class="quotation-item-row">
                                    <td><select name="item_type[]"><option value="SERVICE">Service</option><option value="PRODUCT">Product</option><option value="PART">Part</option><option value="MATERIAL">Material</option><option value="OTHER">Other</option></select></td>
                                    <td><input name="item_description[]" type="text" maxlength="255" placeholder="Describe service or item"></td>
                                    <td><input name="item_quantity[]" type="number" min="0.01" step="0.01" value="1"></td>
                                    <td><input name="item_unit_price[]" type="number" min="0" step="0.01" placeholder="0.00"></td>
                                    <td class="quotation-item-amount">PHP 0.00</td>
                                    <td><button type="button" class="remove-quotation-item" aria-label="Remove item">Remove</button></td>
                                </tr>
                            </template>
                        </div>
                        <div class="quotation-form-field">
                            <label for="subtotal">Subtotal</label>
                            <input id="subtotal" name="subtotal" type="number" value="0.00" readonly>
                        </div>
                        <div class="quotation-form-field">
                            <label for="discount">Discount</label>
                            <input id="discount" name="discount" type="number" min="0" step="0.01" value="0">
                        </div>
                        <div class="quotation-form-field">
                            <label for="total_amount">Total Amount</label>
                            <input id="total_amount" name="total_amount" type="number" value="0.00" readonly>
                        </div>
                        <div class="quotation-form-field quotation-form-field-wide">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Optional quotation notes"></textarea>
                        </div>
                        <div class="quotation-form-field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach ($statuses as $statusOption): ?>
                                    <?php if ($statusOption !== "APPROVED" || hasPermission("quotations", "approve")): ?>
                                        <option value="<?= $statusOption ?>" <?= $statusOption === "DRAFT" ? "selected" : "" ?>><?= quotationLabel($statusOption) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="quotation-form-actions">
                        <button type="submit" name="create_quotation" class="primary-button">Create Quotation</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <?php if ($quotationActionMessage): ?>
                <div class="quotation-action-message <?= htmlspecialchars($quotationActionType) ?>">
                    <?= htmlspecialchars($quotationActionMessage) ?>
                </div>
            <?php endif; ?>
            <form class="quotations-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search quotation, inquiry, or customer">
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= quotationLabel($statusOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <a href="quotations.php">Clear</a>
            </form>
            <div class="quotations-table-wrap">
                <table class="quotations-table">
                    <thead><tr><th>Quotation</th><th>Customer</th><th>Vehicle</th><th>Validity</th><th>Total Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$quotations): ?>
                        <tr><td colspan="6" class="empty-state">No quotation records found.</td></tr>
                    <?php else: foreach ($quotations as $quotation): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($quotation["quotation_no"]) ?></strong><small>Version <?= (int) $quotation["version"] ?> · <?= htmlspecialchars($quotation["inquiry_no"]) ?></small></td>
                            <td><strong><?= htmlspecialchars($quotation["fullname"]) ?></strong><small><?= htmlspecialchars($quotation["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars(trim($quotation["vehicle_name"] ?? "") ?: "-") ?><?php if ($quotation["vehicle_no"]): ?><small><?= htmlspecialchars($quotation["vehicle_no"]) ?></small><?php endif; ?><?php if ($quotation["plate_no"]): ?><small><?= htmlspecialchars($quotation["plate_no"]) ?></small><?php endif; ?></td>
                            <td><strong><?= htmlspecialchars(date("M d, Y", strtotime($quotation["quotation_date"]))) ?></strong><small>Until <?= $quotation["valid_until"] ? htmlspecialchars(date("M d, Y", strtotime($quotation["valid_until"]))) : "No expiry" ?></small></td>
                            <td>PHP <?= number_format((float) $quotation["total_amount"], 2) ?></td>
                            <td><span class="quotation-status <?= strtolower($quotation["status"]) ?>"><?= htmlspecialchars(quotationLabel($quotation["status"])) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="js/quotations.js"></script>
</body>
</html>