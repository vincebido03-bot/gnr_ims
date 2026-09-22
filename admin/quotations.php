<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["DRAFT", "PREPARED", "SENT", "APPROVED", "REJECTED", "EXPIRED", "CANCELLED"];

$sql = "
    SELECT q.quotation_no, q.version, q.quotation_date, q.valid_until,
           q.subtotal, q.discount, q.tax, q.total_amount, q.status,
           c.customer_no, c.fullname,
           CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no,
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
                            <td><?= htmlspecialchars(trim($quotation["vehicle_name"] ?? "") ?: "-") ?><?php if ($quotation["plate_no"]): ?><small><?= htmlspecialchars($quotation["plate_no"]) ?></small><?php endif; ?></td>
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
</body>
</html>