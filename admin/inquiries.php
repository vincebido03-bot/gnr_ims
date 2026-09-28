<?php

require_once "includes/header.php";

$inquiryActionMessage = "";
$inquiryActionType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_inquiry"])) {
    $customerId = (int) ($_POST["customer_id"] ?? 0);
    $vehicleId = isset($_POST["vehicle_id"]) && $_POST["vehicle_id"] !== "" ? (int) $_POST["vehicle_id"] : null;
    $inquiryType = trim((string) ($_POST["inquiry_type"] ?? ""));
    $description = trim((string) ($_POST["description"] ?? ""));
    $status = strtoupper(trim((string) ($_POST["status"] ?? "NEW")));

    try {
        if ($customerId <= 0) {
            throw new RuntimeException("Please select a customer.");
        }

        $customerStmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? LIMIT 1");
        $customerStmt->execute([$customerId]);
        if (!$customerStmt->fetchColumn()) {
            throw new RuntimeException("Customer record not found.");
        }

        if ($vehicleId !== null) {
            $vehicleStmt = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND customer_id = ? LIMIT 1");
            $vehicleStmt->execute([$vehicleId, $customerId]);
            if (!$vehicleStmt->fetchColumn()) {
                throw new RuntimeException("Selected vehicle does not belong to this customer.");
            }
        }

        if ($inquiryType === "") {
            throw new RuntimeException("Please provide the inquiry type.");
        }

        if ($description === "") {
            throw new RuntimeException("Please describe the service request.");
        }

        $validStatuses = ["NEW", "CONTACTED", "ASSESSMENT", "QUOTATION_PREPARED", "QUOTATION_SENT", "FOLLOW_UP", "CONVERTED", "LOST", "CLOSED"];
        if (!in_array($status, $validStatuses, true)) {
            $status = "NEW";
        }

        do {
            $inquiryNo = "INQ-" . date("YmdHis") . random_int(100, 999);
            $checkStmt = $pdo->prepare("SELECT id FROM inquiries WHERE inquiry_no = ? LIMIT 1");
            $checkStmt->execute([$inquiryNo]);
            if (!$checkStmt->fetchColumn()) {
                break;
            }
        } while (true);

        $stmt = $pdo->prepare(
            "INSERT INTO inquiries (inquiry_no, customer_id, vehicle_id, inquiry_type, description, status) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$inquiryNo, $customerId, $vehicleId, $inquiryType, $description, $status]);

        logAudit("INQUIRIES", "CREATE", "INQUIRY", (int) $pdo->lastInsertId(), null, [
            "inquiry_no" => $inquiryNo,
            "customer_id" => $customerId,
            "vehicle_id" => $vehicleId,
            "inquiry_type" => $inquiryType,
            "status" => $status
        ]);

        $inquiryActionMessage = "Inquiry " . $inquiryNo . " was created successfully.";
        $inquiryActionType = "success";
    } catch (Throwable $exception) {
        $inquiryActionMessage = $exception->getMessage();
        $inquiryActionType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["archive_inquiry_id"])) {
    $inquiryId = (int) $_POST["archive_inquiry_id"];

    if ($inquiryId <= 0) {
        $inquiryActionMessage = "Invalid inquiry selected.";
        $inquiryActionType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            $inquiryStmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ? LIMIT 1");
            $inquiryStmt->execute([$inquiryId]);
            $inquiry = $inquiryStmt->fetch();

            if (!$inquiry) {
                throw new RuntimeException("Inquiry record not found.");
            }

            $customerStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
            $customerStmt->execute([$inquiry["customer_id"]]);
            $customer = $customerStmt->fetch();

            $vehicle = null;
            if (!empty($inquiry["vehicle_id"])) {
                $vehicleStmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? LIMIT 1");
                $vehicleStmt->execute([$inquiry["vehicle_id"]]);
                $vehicle = $vehicleStmt->fetch();
            }

            $attachmentStmt = $pdo->prepare("SELECT * FROM inquiry_attachments WHERE inquiry_id = ? ORDER BY id ASC");
            $attachmentStmt->execute([$inquiryId]);
            $attachments = $attachmentStmt->fetchAll();

            archiveRecord(
                "INQUIRIES",
                $inquiryId,
                $inquiry["inquiry_no"],
                $customer["fullname"] ?? "Inquiry",
                $_SESSION["user_id"] ?? null,
                "Archived from Inquiry Register",
                [
                    "inquiry" => $inquiry,
                    "customer" => $customer,
                    "vehicle" => $vehicle,
                    "attachments" => $attachments
                ]
            );

            $deleteStmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ? LIMIT 1");
            $deleteStmt->execute([$inquiryId]);

            $pdo->commit();
            $inquiryActionMessage = "Inquiry " . $inquiry["inquiry_no"] . " was moved to Archive.";
            $inquiryActionType = "success";
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $inquiryActionMessage = "Unable to archive this inquiry. It may be linked to another record.";
            $inquiryActionType = "error";
        }
    }
}

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$statuses = ["NEW", "CONTACTED", "ASSESSMENT", "QUOTATION_PREPARED", "QUOTATION_SENT", "FOLLOW_UP", "CONVERTED", "LOST", "CLOSED"];
$customerOptions = $pdo->query("SELECT id, customer_no, fullname FROM customers ORDER BY fullname ASC, customer_no ASC")->fetchAll();
$vehicleOptions = $pdo->query("SELECT v.id, v.vehicle_no, v.brand, v.model, v.plate_no, c.fullname, c.customer_no FROM vehicles v INNER JOIN customers c ON c.id = v.customer_id ORDER BY c.fullname ASC, v.brand ASC, v.model ASC")->fetchAll();

$sql = "
        SELECT i.id, i.inquiry_no, i.inquiry_type, i.description, i.status, i.created_at,
           c.customer_no, c.fullname,
               v.vehicle_no, CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no,
               COUNT(ia.id) AS attachment_count,
               GROUP_CONCAT(ia.stored_name SEPARATOR '|') AS attachment_files
    FROM inquiries i
    INNER JOIN customers c ON c.id = i.customer_id
    LEFT JOIN vehicles v ON v.id = i.vehicle_id
        LEFT JOIN inquiry_attachments ia ON ia.inquiry_id = i.id
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

$sql .= " GROUP BY i.id ORDER BY i.created_at DESC, i.inquiry_no DESC";
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
            <?php if ($inquiryActionMessage): ?>
                <div class="inquiry-action-message <?= htmlspecialchars($inquiryActionType) ?>">
                    <?= htmlspecialchars($inquiryActionMessage) ?>
                </div>
            <?php endif; ?>
            <div class="inquiries-header">
                <div><span>SALES PIPELINE</span><h2>Inquiry Register</h2></div>
                <strong><?= number_format(count($inquiries)) ?> quer<?= count($inquiries) === 1 ? "y" : "ies" ?></strong>
            </div>
            <?php if (hasPermission("inquiries", "create")): ?>
            <div class="inquiry-action-panel">
                <div class="inquiry-panel-header">
                    <h3>Create New Inquiry</h3>
                </div>
                <form method="post" class="inquiry-form">
                    <div class="inquiry-form-grid">
                        <div class="inquiry-form-field">
                            <label for="customer_id">Customer</label>
                            <select id="customer_id" name="customer_id" required>
                                <option value="">Select a customer</option>
                                <?php foreach ($customerOptions as $customer): ?>
                                    <option value="<?= (int) $customer["id"] ?>"><?= htmlspecialchars($customer["fullname"] . " (" . $customer["customer_no"] . ")") ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="inquiry-form-field">
                            <label for="vehicle_id">Vehicle</label>
                            <select id="vehicle_id" name="vehicle_id">
                                <option value="">No vehicle linked</option>
                                <?php foreach ($vehicleOptions as $vehicle): ?>
                                    <option value="<?= (int) $vehicle["id"] ?>"><?= htmlspecialchars($vehicle["fullname"] . " - " . ($vehicle["vehicle_no"] ? $vehicle["vehicle_no"] . " - " : "") . ($vehicle["brand"] ?: "Unknown") . " " . ($vehicle["model"] ?: "") . ($vehicle["plate_no"] ? " (" . $vehicle["plate_no"] . ")" : "")) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="inquiry-form-field">
                            <label for="inquiry_type">Inquiry Type</label>
                            <input id="inquiry_type" name="inquiry_type" type="text" placeholder="Service / Repair / Install" required>
                        </div>
                        <div class="inquiry-form-field inquiry-form-field-wide">
                            <label for="description">Request Details</label>
                            <textarea id="description" name="description" rows="3" placeholder="Describe the service request" required></textarea>
                        </div>
                        <div class="inquiry-form-field">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach ($statuses as $statusOption): ?>
                                    <option value="<?= $statusOption ?>" <?= $statusOption === "NEW" ? "selected" : "" ?>><?= inquiryLabel($statusOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="inquiry-form-actions">
                        <button type="submit" name="create_inquiry" class="primary-button">Create Inquiry</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
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
                    <thead><tr><th>Inquiry</th><th>Customer</th><th>Vehicle</th><th>Request</th><th>Photos</th><th>Status</th><th>Received</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (!$inquiries): ?>
                        <tr><td colspan="8" class="empty-state">No inquiry records found.</td></tr>
                    <?php else: foreach ($inquiries as $inquiry): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inquiry["inquiry_no"]) ?></strong><small><?= htmlspecialchars(date("M d, Y", strtotime($inquiry["created_at"]))) ?></small></td>
                            <td><strong><?= htmlspecialchars($inquiry["fullname"]) ?></strong><small><?= htmlspecialchars($inquiry["customer_no"]) ?></small></td>
                            <td><?= htmlspecialchars(trim($inquiry["vehicle_name"] ?? "") ?: "-") ?><?php if ($inquiry["vehicle_no"]): ?><small><?= htmlspecialchars($inquiry["vehicle_no"]) ?></small><?php endif; ?><?php if ($inquiry["plate_no"]): ?><small><?= htmlspecialchars($inquiry["plate_no"]) ?></small><?php endif; ?></td>
                            <td><strong><?= htmlspecialchars($inquiry["inquiry_type"] ?: "General inquiry") ?></strong><small><?= htmlspecialchars($inquiry["description"] ?: "No description") ?></small></td>
                            <td>
                                <?php if ((int) $inquiry["attachment_count"] > 0): ?>
                                    <?php foreach (explode("|", $inquiry["attachment_files"]) as $attachmentFile): ?>
                                        <a class="inquiry-photo-link" href="../uploads/inquiries/<?= rawurlencode($attachmentFile) ?>" data-photo-src="../uploads/inquiries/<?= rawurlencode($attachmentFile) ?>">View photo</a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="no-photos">No photos</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="inquiry-status <?= strtolower($inquiry["status"]) ?>"><?= htmlspecialchars(inquiryLabel($inquiry["status"])) ?></span></td>
                            <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($inquiry["created_at"]))) ?></td>
                            <td>
                                <?php if (hasPermission("inquiries", "delete")): ?><form method="post" onsubmit="return confirm('Move this inquiry to Archive?');">
                                    <input type="hidden" name="archive_inquiry_id" value="<?= (int) $inquiry["id"] ?>">
                                    <button type="submit" class="archive-inquiry-button">Archive</button>
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
<div class="photo-lightbox" data-photo-lightbox aria-hidden="true">
    <div class="photo-lightbox-backdrop" data-photo-close></div>
    <div class="photo-lightbox-dialog" role="dialog" aria-modal="true" aria-label="Inquiry photo preview">
        <button class="photo-lightbox-close" type="button" data-photo-close aria-label="Close photo preview">&times;</button>
        <img class="photo-lightbox-image" data-photo-image alt="Inquiry reference photo">
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const lightbox = document.querySelector("[data-photo-lightbox]");
    const image = document.querySelector("[data-photo-image]");
    const photoLinks = document.querySelectorAll("[data-photo-src]");

    if (!lightbox || !image || !photoLinks.length) return;

    function closePhoto() {
        lightbox.classList.remove("is-visible");
        lightbox.setAttribute("aria-hidden", "true");
        image.removeAttribute("src");
        document.body.classList.remove("photo-lightbox-open");
    }

    photoLinks.forEach(function (link) {
        link.addEventListener("click", function (event) {
            event.preventDefault();
            image.src = link.dataset.photoSrc;
            lightbox.classList.add("is-visible");
            lightbox.setAttribute("aria-hidden", "false");
            document.body.classList.add("photo-lightbox-open");
        });
    });

    lightbox.querySelectorAll("[data-photo-close]").forEach(function (control) {
        control.addEventListener("click", closePhoto);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && lightbox.classList.contains("is-visible")) closePhoto();
    });
});
</script>
</body>
</html>