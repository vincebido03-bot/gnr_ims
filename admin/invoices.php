<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["DRAFT", "ISSUED", "PARTIALLY_PAID", "PAID", "VOID"];

$sql = "
    SELECT i.invoice_no, i.subtotal, i.discount, i.tax, i.total_amount,
           i.amount_paid, i.balance, i.status, i.issued_at,
           o.order_no, c.customer_no, c.fullname
    FROM invoices i
    INNER JOIN orders o ON o.id = i.order_id
    INNER JOIN customers c ON c.id = i.customer_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (i.invoice_no LIKE ? OR o.order_no LIKE ? OR c.customer_no LIKE ? OR c.fullname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.issued_at IS NULL, i.issued_at DESC, i.invoice_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

function invoiceLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Invoices</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/invoices.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Invoices</h1><p>Monitor billing documents, payments received, and outstanding balances.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="invoices-content">
            <div class="invoices-header">
                <div><span>FINANCE CONTROL</span><h2>Invoice Register</h2></div>
                <strong><?= number_format(count($invoices)) ?> invoice<?= count($invoices) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="invoices-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search invoice, order, or customer">
                <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $statusOption): ?><option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= invoiceLabel($statusOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="invoices.php">Clear</a>
            </form>
            <div class="invoices-table-wrap">
                <table class="invoices-table">
                    <thead><tr><th>Invoice</th><th>Customer / Order</th><th>Total</th><th>Paid</th><th>Balance</th><th>Issued</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$invoices): ?>
                        <tr><td colspan="7" class="empty-state">No invoice records found.</td></tr>
                    <?php else: foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($invoice["invoice_no"]) ?></strong><small><?= htmlspecialchars($invoice["order_no"]) ?></small></td>
                            <td><strong><?= htmlspecialchars($invoice["fullname"]) ?></strong><small><?= htmlspecialchars($invoice["customer_no"]) ?></small></td>
                            <td>PHP <?= number_format((float) $invoice["total_amount"], 2) ?></td>
                            <td>PHP <?= number_format((float) $invoice["amount_paid"], 2) ?></td>
                            <td class="<?= (float) $invoice["balance"] > 0 ? "balance-due" : "" ?>">PHP <?= number_format((float) $invoice["balance"], 2) ?></td>
                            <td><?= $invoice["issued_at"] ? htmlspecialchars(date("M d, Y", strtotime($invoice["issued_at"]))) : "Not issued" ?></td>
                            <td><span class="invoice-status <?= strtolower($invoice["status"]) ?>"><?= htmlspecialchars(invoiceLabel($invoice["status"])) ?></span></td>
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