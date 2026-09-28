<?php

require_once "includes/header.php";

$vehicleActionMessage = "";
$vehicleActionType = "";
$editingVehicle = null;
$editVehicleId = isset($_GET["edit"]) ? (int) $_GET["edit"] : 0;

if ($editVehicleId > 0) {
    $vehicleStmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? LIMIT 1");
    $vehicleStmt->execute([$editVehicleId]);
    $editingVehicle = $vehicleStmt->fetch();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["vehicle_submit"])) {
    $vehicleId = isset($_POST["vehicle_id"]) ? (int) $_POST["vehicle_id"] : 0;
    $customerId = (int) ($_POST["customer_id"] ?? 0);
    $brand = trim((string) ($_POST["brand"] ?? ""));
    $model = trim((string) ($_POST["model"] ?? ""));
    $yearModel = trim((string) ($_POST["year_model"] ?? ""));
    $plateNo = trim((string) ($_POST["plate_no"] ?? ""));
    $engineNo = trim((string) ($_POST["engine_no"] ?? ""));
    $chassisNo = trim((string) ($_POST["chassis_no"] ?? ""));
    $color = trim((string) ($_POST["color"] ?? ""));
    $notes = trim((string) ($_POST["notes"] ?? ""));

    try {
        if ($customerId <= 0) {
            throw new RuntimeException("Please select the customer owner.");
        }

        $customerStmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? LIMIT 1");
        $customerStmt->execute([$customerId]);
        if (!$customerStmt->fetchColumn()) {
            throw new RuntimeException("Customer record not found.");
        }

        if ($brand === "" && $model === "" && $plateNo === "") {
            throw new RuntimeException("Please provide at least a brand, model, or plate number.");
        }

        if ($vehicleId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE vehicles SET customer_id = ?, brand = ?, model = ?, year_model = ?, plate_no = ?, engine_no = ?, chassis_no = ?, color = ?, notes = ?, updated_at = NOW() WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$customerId, $brand !== "" ? $brand : null, $model !== "" ? $model : null, $yearModel !== "" ? $yearModel : null, $plateNo !== "" ? $plateNo : null, $engineNo !== "" ? $engineNo : null, $chassisNo !== "" ? $chassisNo : null, $color !== "" ? $color : null, $notes !== "" ? $notes : null, $vehicleId]);
            logAudit("VEHICLES", "UPDATE", "VEHICLE", $vehicleId, ["vehicle" => $brand . " " . $model], ["vehicle" => $brand . " " . $model]);
            $vehicleActionMessage = "Vehicle record was updated successfully.";
            $vehicleActionType = "success";
            $editingVehicle = null;
            $editVehicleId = 0;
        } else {
            $lockName = "gnr_vehicle_numbers";
            $lockAcquired = false;
            $pdo->beginTransaction();
            try {
                acquireNumberingLock($pdo, $lockName);
                $lockAcquired = true;
                $vehicleNo = nextVehicleNumber($pdo);
                $stmt = $pdo->prepare(
                    "INSERT INTO vehicles (vehicle_no, customer_id, brand, model, year_model, plate_no, engine_no, chassis_no, color, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$vehicleNo, $customerId, $brand !== "" ? $brand : null, $model !== "" ? $model : null, $yearModel !== "" ? $yearModel : null, $plateNo !== "" ? $plateNo : null, $engineNo !== "" ? $engineNo : null, $chassisNo !== "" ? $chassisNo : null, $color !== "" ? $color : null, $notes !== "" ? $notes : null]);
                $newVehicleId = (int) $pdo->lastInsertId();
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            } finally {
                if ($lockAcquired) {
                    releaseNumberingLock($pdo, $lockName);
                }
            }
            logAudit("VEHICLES", "CREATE", "VEHICLE", $newVehicleId, null, ["vehicle_no" => $vehicleNo, "customer_id" => $customerId, "brand" => $brand, "model" => $model]);
            $vehicleActionMessage = "Vehicle was added successfully.";
            $vehicleActionType = "success";
        }
    } catch (Throwable $exception) {
        $vehicleActionMessage = $exception->getMessage();
        $vehicleActionType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["archive_vehicle_id"])) {
    $vehicleId = (int) $_POST["archive_vehicle_id"];

    try {
        if ($vehicleId <= 0) {
            throw new RuntimeException("Invalid vehicle selected.");
        }

        $pdo->beginTransaction();

        $vehicleStmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? LIMIT 1");
        $vehicleStmt->execute([$vehicleId]);
        $vehicle = $vehicleStmt->fetch();

        if (!$vehicle) {
            throw new RuntimeException("Vehicle record not found.");
        }

        $customerStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
        $customerStmt->execute([$vehicle["customer_id"]]);
        $customer = $customerStmt->fetch();

        archiveRecord(
            "VEHICLES",
            $vehicleId,
            $vehicle["vehicle_no"] ?: ($vehicle["plate_no"] ?: "VEH-" . $vehicleId),
            trim(($vehicle["brand"] ?? "") . " " . ($vehicle["model"] ?? "")) ?: "Vehicle",
            $_SESSION["user_id"] ?? null,
            "Archived from Vehicle Register",
            ["vehicle" => $vehicle, "customer" => $customer]
        );

        $deleteStmt = $pdo->prepare("DELETE FROM vehicles WHERE id = ? LIMIT 1");
        $deleteStmt->execute([$vehicleId]);

        $pdo->commit();
        $vehicleActionMessage = "Vehicle was moved to Archive.";
        $vehicleActionType = "success";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $vehicleActionMessage = $exception->getMessage() ?: "Unable to archive this vehicle. It may be linked to another record.";
        $vehicleActionType = "error";
    }
}

$search = trim($_GET["search"] ?? "");
$sql = "
    SELECT v.id, v.vehicle_no, v.brand, v.model, v.year_model, v.plate_no, v.engine_no,
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

$customerOptions = $pdo->query("SELECT id, customer_no, fullname FROM customers ORDER BY fullname ASC, customer_no ASC")->fetchAll();
$vehicleFormData = [
    "vehicle_id" => (string) ($editingVehicle["id"] ?? ""),
    "vehicle_no" => $editingVehicle["vehicle_no"] ?? "",
    "customer_id" => (string) ($editingVehicle["customer_id"] ?? ""),
    "brand" => $editingVehicle["brand"] ?? "",
    "model" => $editingVehicle["model"] ?? "",
    "year_model" => $editingVehicle["year_model"] ?? "",
    "plate_no" => $editingVehicle["plate_no"] ?? "",
    "engine_no" => $editingVehicle["engine_no"] ?? "",
    "chassis_no" => $editingVehicle["chassis_no"] ?? "",
    "color" => $editingVehicle["color"] ?? "",
    "notes" => $editingVehicle["notes"] ?? "",
];

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
            <?php if ($vehicleActionMessage): ?>
                <div class="vehicle-action-message <?= htmlspecialchars($vehicleActionType) ?>">
                    <?= htmlspecialchars($vehicleActionMessage) ?>
                </div>
            <?php endif; ?>
            <div class="vehicles-header">
                <div><span>VEHICLE REGISTER</span><h2>Vehicle Directory</h2></div>
                <strong><?= number_format(count($vehicles)) ?> vehicle<?= count($vehicles) === 1 ? "" : "s" ?></strong>
            </div>
            <?php if (($editingVehicle && hasPermission("vehicles", "edit")) || (!$editingVehicle && hasPermission("vehicles", "create"))): ?>
            <div class="vehicle-action-panel">
                <div class="vehicle-panel-header">
                    <h3><?= $editingVehicle ? "Edit Vehicle" : "Add New Vehicle" ?></h3>
                    <?php if ($editingVehicle): ?>
                        <a href="vehicles.php" class="secondary-button">Cancel Edit</a>
                    <?php endif; ?>
                </div>
                <form method="post" class="vehicle-form">
                    <?php if ($editingVehicle): ?>
                        <input type="hidden" name="vehicle_id" value="<?= (int) $editingVehicle["id"] ?>">
                    <?php endif; ?>
                    <div class="vehicle-form-grid">
                        <div class="vehicle-form-field">
                            <label for="vehicle_no">Vehicle Number</label>
                            <input id="vehicle_no" type="text" value="<?= htmlspecialchars($vehicleFormData["vehicle_no"] ?: "Assigned automatically") ?>" readonly>
                        </div>
                        <div class="vehicle-form-field">
                            <label for="customer_id">Customer Owner</label>
                            <select id="customer_id" name="customer_id" required>
                                <option value="">Select customer</option>
                                <?php foreach ($customerOptions as $customer): ?>
                                    <option value="<?= (int) $customer["id"] ?>" <?= ((string) $customer["id"] === $vehicleFormData["customer_id"]) ? "selected" : "" ?>><?= htmlspecialchars($customer["fullname"] . " (" . $customer["customer_no"] . ")") ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="vehicle-form-field">
                            <label for="brand">Brand</label>
                            <input id="brand" name="brand" type="text" value="<?= htmlspecialchars($vehicleFormData["brand"]) ?>">
                        </div>
                        <div class="vehicle-form-field">
                            <label for="model">Model</label>
                            <input id="model" name="model" type="text" value="<?= htmlspecialchars($vehicleFormData["model"]) ?>">
                        </div>
                        <div class="vehicle-form-field">
                            <label for="year_model">Year Model</label>
                            <input id="year_model" name="year_model" type="text" value="<?= htmlspecialchars($vehicleFormData["year_model"]) ?>" placeholder="2024">
                        </div>
                        <div class="vehicle-form-field">
                            <label for="plate_no">Plate Number</label>
                            <input id="plate_no" name="plate_no" type="text" value="<?= htmlspecialchars($vehicleFormData["plate_no"]) ?>">
                        </div>
                        <div class="vehicle-form-field">
                            <label for="engine_no">Engine Number</label>
                            <input id="engine_no" name="engine_no" type="text" value="<?= htmlspecialchars($vehicleFormData["engine_no"]) ?>">
                        </div>
                        <div class="vehicle-form-field">
                            <label for="chassis_no">Chassis Number</label>
                            <input id="chassis_no" name="chassis_no" type="text" value="<?= htmlspecialchars($vehicleFormData["chassis_no"]) ?>">
                        </div>
                        <div class="vehicle-form-field">
                            <label for="color">Color</label>
                            <input id="color" name="color" type="text" value="<?= htmlspecialchars($vehicleFormData["color"]) ?>">
                        </div>
                        <div class="vehicle-form-field vehicle-form-field-wide">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3"><?= htmlspecialchars($vehicleFormData["notes"]) ?></textarea>
                        </div>
                    </div>
                    <div class="vehicle-form-actions">
                        <button type="submit" name="vehicle_submit" class="primary-button"><?= $editingVehicle ? "Update Vehicle" : "Create Vehicle" ?></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <form class="vehicles-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search owner, brand, model, or plate no.">
                <button type="submit">Search</button>
                <a href="vehicles.php">Clear</a>
            </form>
            <div class="vehicles-table-wrap">
                <table class="vehicles-table">
                    <thead><tr><th>Vehicle</th><th>Owner</th><th>Plate No.</th><th>Engine No.</th><th>Chassis No.</th><th>Color</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (!$vehicles): ?>
                        <tr><td colspan="7" class="empty-state">No vehicle records found.</td></tr>
                    <?php else: foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars(trim(($vehicle["brand"] ?? "") . " " . ($vehicle["model"] ?? "")) ?: "Unspecified vehicle") ?></strong><small><?= htmlspecialchars($vehicle["vehicle_no"] ?: "No vehicle number") ?> · <?= htmlspecialchars($vehicle["year_model"] ?: "Year not specified") ?></small></td>
                            <td><strong><?= htmlspecialchars($vehicle["fullname"]) ?></strong><small><?= htmlspecialchars($vehicle["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars($vehicle["plate_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($vehicle["engine_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($vehicle["chassis_no"] ?: "-") ?></td>
                            <td><?= htmlspecialchars($vehicle["color"] ?: "-") ?></td>
                            <td class="vehicle-actions">
                                <?php if (hasPermission("vehicles", "edit")): ?><a href="vehicles.php?edit=<?= (int) $vehicle["id"] ?>" class="small-action-button secondary">Edit</a><?php endif; ?>
                                <?php if (hasPermission("vehicles", "delete")): ?>
                                <form method="post" onsubmit="return confirm('Move this vehicle to Archive?');">
                                    <input type="hidden" name="archive_vehicle_id" value="<?= (int) $vehicle["id"] ?>">
                                    <button type="submit" class="archive-vehicle-button">Archive</button>
                                </form>
                                <?php endif; ?>
                            </td>
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