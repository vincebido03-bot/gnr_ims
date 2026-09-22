<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$sql = "
    SELECT v.id, v.brand, v.model, v.year_model, v.plate_no, v.engine_no,
           v.chassis_no, v.color, c.customer_no, c.fullname
    FROM vehicles v
    INNER JOIN customers c ON c.id = v.customer_id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (c.customer_no LIKE ? OR c.fullname LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR v.plate_no LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

$sql .= " ORDER BY v.brand ASC, v.model ASC, v.plate_no ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Vehicles</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/vehicles.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Vehicles</h1><p>View registered vehicles and their customer owners.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="vehicles-content">
            <div class="vehicles-header">
                <div><span>VEHICLE REGISTER</span><h2>Vehicle Directory</h2></div>
                <strong><?= number_format(count($vehicles)) ?> vehicle<?= count($vehicles) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="vehicles-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search owner, brand, model, or plate no.">
                <button type="submit">Search</button>
                <a href="vehicles.php">Clear</a>
            </form>
            <div class="vehicles-table-wrap">
                <table class="vehicles-table">
                    <thead><tr><th>Vehicle</th><th>Owner</th><th>Plate No.</th><th>Engine No.</th><th>Chassis No.</th><th>Color</th></tr></thead>
                    <tbody>
                    <?php if (!$vehicles): ?>
                        <tr><td colspan="6" class="empty-state">No vehicle records found.</td></tr>
                    <?php else: foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars(trim(($vehicle["brand"] ?? "") . " " . ($vehicle["model"] ?? "")) ?: "Unspecified vehicle") ?></strong><small><?= htmlspecialchars($vehicle["year_model"] ?: "Year not specified") ?></small></td>
                            <td><strong><?= htmlspecialchars($vehicle["fullname"]) ?></strong><small><?= htmlspecialchars($vehicle["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars($vehicle["plate_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($vehicle["engine_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($vehicle["chassis_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($vehicle["color"] ?: "-") ?></td>
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