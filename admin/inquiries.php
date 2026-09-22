<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["NEW", "CONTACTED", "ASSESSMENT", "QUOTATION_PREPARED", "QUOTATION_SENT", "FOLLOW_UP", "CONVERTED", "LOST", "CLOSED"];

$sql = "
    SELECT i.inquiry_no, i.inquiry_type, i.description, i.status, i.created_at,
           c.customer_no, c.fullname,
           CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no
    FROM inquiries i
    INNER JOIN customers c ON c.id = i.customer_id
    LEFT JOIN vehicles v ON v.id = i.vehicle_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (i.inquiry_no LIKE ? OR c.customer_no LIKE ? OR c.fullname LIKE ? OR i.inquiry_type LIKE ? OR i.description LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.created_at DESC, i.inquiry_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

function inquiryLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Inquiries</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/inquiries.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Inquiries</h1><p>Track customer requests from first contact to conversion.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="inquiries-content">
            <div class="inquiries-header">
                <div><span>SALES PIPELINE</span><h2>Inquiry Register</h2></div>
                <strong><?= number_format(count($inquiries)) ?> quer<?= count($inquiries) === 1 ? "y" : "ies" ?></strong>
            </div>
            <form class="inquiries-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search inquiry, customer, or request">
                <select name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= inquiryLabel($statusOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <a href="inquiries.php">Clear</a>
            </form>
            <div class="inquiries-table-wrap">
                <table class="inquiries-table">
                    <thead><tr><th>Inquiry</th><th>Customer</th><th>Vehicle</th><th>Request</th><th>Status</th><th>Received</th></tr></thead>
                    <tbody>
                    <?php if (!$inquiries): ?>
                        <tr><td colspan="6" class="empty-state">No inquiry records found.</td></tr>
                    <?php else: foreach ($inquiries as $inquiry): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inquiry["inquiry_no"]) ?></strong><small><?= htmlspecialchars(date("M d, Y", strtotime($inquiry["created_at"]))) ?></small></td>
                            <td><strong><?= htmlspecialchars($inquiry["fullname"]) ?></strong><small><?= htmlspecialchars($inquiry["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars(trim($inquiry["vehicle_name"] ?? "") ?: "-") ?><?php if ($inquiry["plate_no"]): ?><small><?= htmlspecialchars($inquiry["plate_no"]) ?></small><?php endif; ?></td>
                            <td><strong><?= htmlspecialchars($inquiry["inquiry_type"] ?: "General inquiry") ?></strong><small><?= htmlspecialchars($inquiry["description"] ?: "No description") ?></small></td>
                            <td><span class="inquiry-status <?= strtolower($inquiry["status"]) ?>"><?= htmlspecialchars(inquiryLabel($inquiry["status"])) ?></span></td>
                            <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($inquiry["created_at"]))) ?></td>
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