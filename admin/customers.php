<?php

require_once "includes/header.php";

$customerActionMessage = "";
$customerActionType = "";
$editingCustomer = null;
$editCustomerId = isset($_GET["edit"]) ? (int) $_GET["edit"] : 0;

if ($editCustomerId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
    $stmt->execute([$editCustomerId]);
    $editingCustomer = $stmt->fetch();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["customer_submit"])) {
    $customerId = isset($_POST["customer_id"]) ? (int) $_POST["customer_id"] : 0;
    $fullname = trim((string) ($_POST["fullname"] ?? ""));
    $contactNo = trim((string) ($_POST["contact_no"] ?? ""));
    $facebook = trim((string) ($_POST["facebook"] ?? ""));
    $socialPlatform = trim((string) ($_POST["social_platform"] ?? ""));
    $socialLink = trim((string) ($_POST["social_link"] ?? ""));
    $address = trim((string) ($_POST["address"] ?? ""));
    $notes = trim((string) ($_POST["notes"] ?? ""));

    try {
        if ($fullname === "") {
            throw new RuntimeException("Customer name is required.");
        }

        if ($contactNo !== "" && !preg_match('/^[0-9+\-\s()]+$/', $contactNo)) {
            throw new RuntimeException("Please enter a valid contact number.");
        }

        if ($socialLink !== "" && !filter_var($socialLink, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Please enter a valid URL for the social profile.");
        }

        if ($customerId > 0) {
            $existingCustomerStmt = $pdo->prepare("SELECT customer_no FROM customers WHERE id = ? LIMIT 1");
            $existingCustomerStmt->execute([$customerId]);
            $customerNo = (string) ($existingCustomerStmt->fetchColumn() ?: "");

            $duplicateStmt = $pdo->prepare(
                "SELECT id FROM customers WHERE id != ? AND customer_no = ? LIMIT 1"
            );
            $duplicateStmt->execute([$customerId, $customerNo]);
            if ($duplicateStmt->fetchColumn()) {
                throw new RuntimeException("This customer number is already in use.");
            }

            $stmt = $pdo->prepare(
                "UPDATE customers SET customer_no = ?, fullname = ?, contact_no = ?, facebook = ?, social_platform = ?, social_link = ?, address = ?, notes = ?, updated_at = NOW() WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$customerNo, $fullname, $contactNo !== "" ? $contactNo : null, $facebook !== "" ? $facebook : null, $socialPlatform !== "" ? $socialPlatform : null, $socialLink !== "" ? $socialLink : null, $address !== "" ? $address : null, $notes !== "" ? $notes : null, $customerId]);
            logAudit("CUSTOMERS", "UPDATE", "CUSTOMER", $customerId, ["customer_no" => $customerNo, "fullname" => $fullname], ["customer_no" => $customerNo, "fullname" => $fullname]);
            $customerActionMessage = "Customer information was updated successfully.";
            $customerActionType = "success";
            $editingCustomer = null;
            $editCustomerId = 0;
        } else {
            $lockName = "gnr_customer_numbers";
            $lockAcquired = false;
            $pdo->beginTransaction();
            try {
                acquireNumberingLock($pdo, $lockName);
                $lockAcquired = true;
                $customerNo = nextCustomerNumber($pdo);
                $stmt = $pdo->prepare(
                    "INSERT INTO customers (customer_no, fullname, contact_no, facebook, social_platform, social_link, address, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$customerNo, $fullname, $contactNo !== "" ? $contactNo : null, $facebook !== "" ? $facebook : null, $socialPlatform !== "" ? $socialPlatform : null, $socialLink !== "" ? $socialLink : null, $address !== "" ? $address : null, $notes !== "" ? $notes : null]);
                $newCustomerId = (int) $pdo->lastInsertId();
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
            logAudit("CUSTOMERS", "CREATE", "CUSTOMER", $newCustomerId, null, ["customer_no" => $customerNo, "fullname" => $fullname]);
            $customerActionMessage = "New customer was created successfully.";
            $customerActionType = "success";
        }
    } catch (Throwable $exception) {
        $customerActionMessage = $exception->getMessage();
        $customerActionType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["archive_customer_id"])) {
    $customerId = (int) $_POST["archive_customer_id"];

    try {
        if ($customerId <= 0) {
            throw new RuntimeException("Invalid customer selected.");
        }

        $customerStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
        $customerStmt->execute([$customerId]);
        $customer = $customerStmt->fetch();

        if (!$customer) {
            throw new RuntimeException("Customer record not found.");
        }

        $vehicleCountStmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE customer_id = ?");
        $vehicleCountStmt->execute([$customerId]);
        if ((int) $vehicleCountStmt->fetchColumn() > 0) {
            throw new RuntimeException("This customer still has linked vehicles. Archive those first.");
        }

        archiveRecord(
            "CUSTOMERS",
            $customerId,
            $customer["customer_no"] ?: "CUS-" . $customerId,
            $customer["fullname"] ?: "Customer",
            $_SESSION["user_id"] ?? null,
            "Archived from Customer Register",
            ["customer" => $customer]
        );

        $deleteStmt = $pdo->prepare("DELETE FROM customers WHERE id = ? LIMIT 1");
        $deleteStmt->execute([$customerId]);

        logAudit("CUSTOMERS", "ARCHIVE", "CUSTOMER", $customerId, ["customer" => $customer], ["status" => "ARCHIVED"]);
        $customerActionMessage = "Customer was moved to Archive.";
        $customerActionType = "success";
    } catch (Throwable $exception) {
        $customerActionMessage = $exception->getMessage();
        $customerActionType = "error";
    }
}

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

$customerFormData = [
    "customer_id" => (string) ($editingCustomer["id"] ?? ""),
    "customer_no" => $editingCustomer["customer_no"] ?? "",
    "fullname" => $editingCustomer["fullname"] ?? "",
    "contact_no" => $editingCustomer["contact_no"] ?? "",
    "facebook" => $editingCustomer["facebook"] ?? "",
    "social_platform" => $editingCustomer["social_platform"] ?? "",
    "social_link" => $editingCustomer["social_link"] ?? "",
    "address" => $editingCustomer["address"] ?? "",
    "notes" => $editingCustomer["notes"] ?? "",
];

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
            <?php if ($customerActionMessage): ?>
                <div class="customer-action-message <?= htmlspecialchars($customerActionType) ?>">
                    <?= htmlspecialchars($customerActionMessage) ?>
                </div>
            <?php endif; ?>
            <div class="customers-header">
                <div><span>CUSTOMER REGISTER</span><h2>Customer Directory</h2></div>
                <strong><?= number_format(count($customers)) ?> customer<?= count($customers) === 1 ? "" : "s" ?></strong>
            </div>
            <?php if (($editingCustomer && hasPermission("customers", "edit")) || (!$editingCustomer && hasPermission("customers", "create"))): ?>
            <div class="customer-action-panel">
                <div class="customer-panel-header">
                    <h3><?= $editingCustomer ? "Edit Customer" : "Add New Customer" ?></h3>
                    <?php if ($editingCustomer): ?>
                        <a href="customers.php" class="secondary-button">Cancel Edit</a>
                    <?php endif; ?>
                </div>
                <form method="post" class="customer-form">
                    <?php if ($editingCustomer): ?>
                        <input type="hidden" name="customer_id" value="<?= (int) $editingCustomer["id"] ?>">
                    <?php endif; ?>
                    <div class="customer-form-grid">
                        <div class="customer-form-field">
                            <label for="customer_no">Customer Number</label>
                            <input id="customer_no" name="customer_no" type="text" value="<?= htmlspecialchars($customerFormData["customer_no"]) ?>" placeholder="System generated" readonly>
                        </div>
                        <div class="customer-form-field">
                            <label for="fullname">Full Name</label>
                            <input id="fullname" name="fullname" type="text" value="<?= htmlspecialchars($customerFormData["fullname"]) ?>" required>
                        </div>
                        <div class="customer-form-field">
                            <label for="contact_no">Contact Number</label>
                            <input id="contact_no" name="contact_no" type="text" value="<?= htmlspecialchars($customerFormData["contact_no"]) ?>" placeholder="09xx...">
                        </div>
                        <div class="customer-form-field">
                            <label for="facebook">Facebook / Username</label>
                            <input id="facebook" name="facebook" type="text" value="<?= htmlspecialchars($customerFormData["facebook"]) ?>" placeholder="facebook.com/username">
                        </div>
                        <div class="customer-form-field">
                            <label for="social_platform">Social Platform</label>
                            <select id="social_platform" name="social_platform">
                                <option value="" <?= ($customerFormData["social_platform"] === "" ? "selected" : "") ?>>None</option>
                                <option value="Facebook" <?= ($customerFormData["social_platform"] === "Facebook" ? "selected" : "") ?>>Facebook</option>
                                <option value="Instagram" <?= ($customerFormData["social_platform"] === "Instagram" ? "selected" : "") ?>>Instagram</option>
                                <option value="TikTok" <?= ($customerFormData["social_platform"] === "TikTok" ? "selected" : "") ?>>TikTok</option>
                                <option value="Messenger" <?= ($customerFormData["social_platform"] === "Messenger" ? "selected" : "") ?>>Messenger</option>
                            </select>
                        </div>
                        <div class="customer-form-field">
                            <label for="social_link">Social Link</label>
                            <input id="social_link" name="social_link" type="url" value="<?= htmlspecialchars($customerFormData["social_link"]) ?>" placeholder="https://facebook.com/...">
                        </div>
                        <div class="customer-form-field customer-form-field-wide">
                            <label for="address">Address</label>
                            <input id="address" name="address" type="text" value="<?= htmlspecialchars($customerFormData["address"]) ?>">
                        </div>
                        <div class="customer-form-field customer-form-field-wide">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="3"><?= htmlspecialchars($customerFormData["notes"]) ?></textarea>
                        </div>
                    </div>
                    <div class="customer-form-actions">
                        <button type="submit" name="customer_submit" class="primary-button"><?= $editingCustomer ? "Update Customer" : "Create Customer" ?></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <form class="customers-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search customer no., name, or contact">
                <button type="submit">Search</button>
                <a href="customers.php">Clear</a>
            </form>
            <div class="customers-table-wrap">
                <table class="customers-table">
                    <thead><tr><th>Customer</th><th>Contact</th><th>Social</th><th>Address</th><th>Added</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$customers): ?>
                        <tr><td colspan="6" class="empty-state">No customer records found.</td></tr>
                    <?php else: foreach ($customers as $customer): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($customer["fullname"]) ?></strong><small><?= htmlspecialchars($customer["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars($customer["contact_no"] ?: "-") ?></td>
                            <td><?php if ($customer["social_link"]): ?><a class="customer-social-link" href="<?= htmlspecialchars($customer["social_link"]) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($customer["social_platform"] ?: "Social profile") ?></a><?php else: ?>-<?php endif; ?></td>
                            <td><?= htmlspecialchars($customer["address"] ?: "-") ?></td>
                            <td><?= htmlspecialchars(date("M d, Y", strtotime($customer["created_at"]))) ?></td>
                            <td class="customer-actions">
                                <?php if (hasPermission("customers", "edit")): ?><a href="customers.php?edit=<?= (int) $customer["id"] ?>" class="small-action-button secondary">Edit</a><?php endif; ?>
                                <?php if (hasPermission("customers", "delete")): ?>
                                <form method="post" onsubmit="return confirm('Archive this customer?');">
                                    <input type="hidden" name="archive_customer_id" value="<?= (int) $customer["id"] ?>">
                                    <button type="submit" class="archive-button">Archive</button>
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