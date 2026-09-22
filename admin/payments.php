<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$method = strtoupper(trim($_GET["payment_method"] ?? ""));
$methods = ["CASH", "GCASH", "BANK_TRANSFER", "CARD", "OTHER"];

$sql = "
    SELECT p.payment_no, p.payment_method, p.amount, p.reference_no, p.payment_date,
           p.notes, i.invoice_no, i.balance, o.order_no,
           c.customer_no, c.fullname,
           CONCAT(e.first_name, ' ', e.last_name) AS received_by_name
    FROM payments p
    INNER JOIN orders o ON o.id = p.order_id
    LEFT JOIN invoices i ON i.id = p.invoice_id
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN users u ON u.id = p.received_by
    LEFT JOIN employees e ON e.id = u.employee_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (p.payment_no LIKE ? OR p.reference_no LIKE ? OR i.invoice_no LIKE ? OR o.order_no LIKE ? OR c.fullname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($method, $methods, true)) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method;
}

$sql .= " ORDER BY p.payment_date DESC, p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

function paymentLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Payments</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/payments.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Payments</h1><p>Review received payments and their invoice references.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="payments-content">
            <div class="payments-header">
                <div><span>CASH COLLECTIONS</span><h2>Payment Register</h2></div>
                <strong><?= number_format(count($payments)) ?> payment<?= count($payments) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="payments-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search payment, invoice, order, or customer">
                <select name="payment_method"><option value="">All methods</option><?php foreach ($methods as $methodOption): ?><option value="<?= $methodOption ?>" <?= $method === $methodOption ? "selected" : "" ?>><?= paymentLabel($methodOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="payments.php">Clear</a>
            </form>
            <div class="payments-table-wrap">
                <table class="payments-table">
                    <thead><tr><th>Payment</th><th>Customer / Order</th><th>Invoice</th><th>Method</th><th>Amount</th><th>Reference</th><th>Received By</th></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?>
                        <tr><td colspan="7" class="empty-state">No payment records found.</td></tr>
                    <?php else: foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($payment["payment_no"]) ?></strong><small><?= htmlspecialchars(date("M d, Y h:i A", strtotime($payment["payment_date"]))) ?></small></td>
                            <td><strong><?= htmlspecialchars($payment["fullname"]) ?></strong><small><?= htmlspecialchars($payment["customer_no"]) ?> · <?= htmlspecialchars($payment["order_no"]) ?></small></td>
                            <td><?= htmlspecialchars($payment["invoice_no"] ?: "Unlinked") ?></td>
                            <td><span class="payment-method <?= strtolower($payment["payment_method"]) ?>"><?= htmlspecialchars(paymentLabel($payment["payment_method"])) ?></span></td>
                            <td><strong>PHP <?= number_format((float) $payment["amount"], 2) ?></strong></td>
                            <td><?= htmlspecialchars($payment["reference_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($payment["received_by_name"] ?: "System") ?></td>
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