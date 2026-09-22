<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$sql = "
    SELECT id, customer_no, fullname, contact_no, facebook, social_platform, social_link, address, notes, created_at
    FROM customers
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (customer_no LIKE ? OR fullname LIKE ? OR contact_no LIKE ? OR facebook LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

$sql .= " ORDER BY fullname ASC, customer_no ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Customers</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/customers.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Customers</h1><p>View customer records and contact information.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="customers-content">
            <div class="customers-header">
                <div><span>CUSTOMER REGISTER</span><h2>Customer Directory</h2></div>
                <strong><?= number_format(count($customers)) ?> customer<?= count($customers) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="customers-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search customer no., name, or contact">
                <button type="submit">Search</button>
                <a href="customers.php">Clear</a>
            </form>
            <div class="customers-table-wrap">
                <table class="customers-table">
                    <thead><tr><th>Customer</th><th>Contact</th><th>Social</th><th>Address</th><th>Added</th></tr></thead>
                    <tbody>
                    <?php if (!$customers): ?>
                        <tr><td colspan="5" class="empty-state">No customer records found.</td></tr>
                    <?php else: foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($customer["fullname"]) ?></strong><small><?= htmlspecialchars($customer["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars($customer["contact_no"] ?: "-") ?></td>
                            <td><?php if ($customer["social_link"]): ?><a class="customer-social-link" href="<?= htmlspecialchars($customer["social_link"]) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($customer["social_platform"] ?: "Social profile") ?></a><?php else: ?>-<?php endif; ?></td>
                            <td><?= htmlspecialchars($customer["address"] ?: "-") ?></td>
                            <td><?= htmlspecialchars(date("M d, Y", strtotime($customer["created_at"]))) ?></td>
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