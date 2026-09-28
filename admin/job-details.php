<?php

require_once "includes/header.php";

if (empty($_SESSION["job_detail_token"])) {
    $_SESSION["job_detail_token"] = bin2hex(random_bytes(32));
}

$jobId = (int) ($_GET["job_id"] ?? $_POST["job_id"] ?? 0);
$embedded = (string) ($_GET["embedded"] ?? $_POST["embedded"] ?? "") === "1";
$activeDetailIndex = max(0, (int) ($_GET["detail_index"] ?? $_POST["detail_index"] ?? 0));
$jobDetailReturnUrl = "job-details.php?job_id=" . $jobId . ($embedded ? "&embedded=1" : "") . "&detail_index=" . $activeDetailIndex;
$jobStmt = $pdo->prepare("SELECT j.id, j.job_no, j.job_title, j.description, j.status, j.scheduled_start, j.scheduled_end, o.order_no, c.customer_no, c.fullname, v.vehicle_no, CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no FROM jobs j INNER JOIN orders o ON o.id = j.order_id INNER JOIN customers c ON c.id = o.customer_id LEFT JOIN vehicles v ON v.id = o.vehicle_id WHERE j.id = ? LIMIT 1");
$jobStmt->execute([$jobId]);
$job = $jobStmt->fetch();

if (!$job) {
    http_response_code(404);
    exit("Job record not found.");
}

$jobDetailMessage = $_SESSION["job_detail_message"] ?? "";
$jobDetailMessageType = $_SESSION["job_detail_message_type"] ?? "";
unset($_SESSION["job_detail_message"], $_SESSION["job_detail_message_type"]);

$detailStatuses = ["PENDING", "IN_PROGRESS", "FOR_QC", "QC_PASSED", "QC_FAILED", "COMPLETED", "CANCELLED"];
$itemTypes = ["SERVICE", "PRODUCT", "PART", "MATERIAL", "OTHER"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_job_detail"])) {
    $serviceType = strtoupper(trim((string) ($_POST["service_type"] ?? "SERVICE")));
    $description = trim((string) ($_POST["description"] ?? ""));
    $quantityInput = trim((string) ($_POST["quantity"] ?? ""));
    $sellingPriceInput = trim((string) ($_POST["selling_price"] ?? ""));

    try {
        $submittedToken = $_POST["job_detail_token"] ?? "";
        if (!is_string($submittedToken) || !hash_equals($_SESSION["job_detail_token"], $submittedToken)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }
        if (!in_array($serviceType, $itemTypes, true)) {
            throw new RuntimeException("Select a valid detail type.");
        }
        if ($description === "" || strlen($description) > 255) {
            throw new RuntimeException("Enter a detail description up to 255 characters.");
        }
        if (!is_numeric($quantityInput) || (float) $quantityInput <= 0) {
            throw new RuntimeException("Quantity must be greater than zero.");
        }
        if (!is_numeric($sellingPriceInput) || (float) $sellingPriceInput < 0) {
            throw new RuntimeException("Selling price cannot be negative.");
        }

        $quantity = round((float) $quantityInput, 2);
        $sellingPrice = round((float) $sellingPriceInput, 2);
        $insertStmt = $pdo->prepare("INSERT INTO job_details (job_id, service_type, description, quantity, selling_price, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$jobId, $serviceType, $description, $quantity, $sellingPrice, $_SESSION["user_id"] ?? null]);
        $detailId = (int) $pdo->lastInsertId();

        logAudit("JOBS", "CREATE", "JOB_DETAIL", $detailId, null, [
            "job_no" => $job["job_no"],
            "service_type" => $serviceType,
            "description" => $description,
            "quantity" => $quantity,
            "selling_price" => $sellingPrice,
        ]);

        $_SESSION["job_detail_message"] = "Job detail was added.";
        $_SESSION["job_detail_message_type"] = "success";
        header("Location: " . $jobDetailReturnUrl);
        exit;
    } catch (Throwable $exception) {
        $jobDetailMessage = $exception->getMessage();
        $jobDetailMessageType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_job_status"])) {
    $detailId = (int) ($_POST["detail_id"] ?? 0);
    $status = strtoupper(trim((string) ($_POST["status"] ?? "")));

    try {
        $submittedToken = $_POST["job_detail_token"] ?? "";
        if (!is_string($submittedToken) || !hash_equals($_SESSION["job_detail_token"], $submittedToken)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }
        if ($detailId <= 0 || !in_array($status, ["QC_PASSED", "QC_FAILED", "COMPLETED"], true)) {
            throw new RuntimeException("Select a valid approval status.");
        }

        $existingStmt = $pdo->prepare("SELECT status FROM job_details WHERE id = ? AND job_id = ? LIMIT 1");
        $existingStmt->execute([$detailId, $jobId]);
        $existingStatus = $existingStmt->fetchColumn();
        if ($existingStatus === false) {
            throw new RuntimeException("Job detail not found.");
        }

        $updateStmt = $pdo->prepare("UPDATE job_details SET status = ? WHERE id = ? AND job_id = ? LIMIT 1");
        $updateStmt->execute([$status, $detailId, $jobId]);
        logAudit("JOBS", "APPROVE_DETAIL", "JOB_DETAIL", $detailId, ["status" => $existingStatus], ["status" => $status]);
        $_SESSION["job_detail_message"] = "Job detail status updated.";
        $_SESSION["job_detail_message_type"] = "success";
        header("Location: " . $jobDetailReturnUrl);
        exit;
    } catch (Throwable $exception) {
        $jobDetailMessage = $exception->getMessage();
        $jobDetailMessageType = "error";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_job_detail"])) {
    $detailId = (int) ($_POST["detail_id"] ?? 0);
    $status = strtoupper(trim((string) ($_POST["status"] ?? "PENDING")));
    $assignedStaff = trim((string) ($_POST["assigned_staff_name"] ?? ""));
    $findings = trim((string) ($_POST["findings_notes"] ?? ""));
    $completionDate = trim((string) ($_POST["completion_date"] ?? ""));
    $approvedPriceInput = trim((string) ($_POST["customer_approved_price"] ?? ""));
    $costInputs = [
        "labor_cost" => trim((string) ($_POST["labor_cost"] ?? "")),
        "raw_material_cost" => trim((string) ($_POST["raw_material_cost"] ?? "")),
        "consumable_cost" => trim((string) ($_POST["consumable_cost"] ?? "")),
        "other_cost" => trim((string) ($_POST["other_cost"] ?? "")),
    ];

    try {
        $submittedToken = $_POST["job_detail_token"] ?? "";
        if (!is_string($submittedToken) || !hash_equals($_SESSION["job_detail_token"], $submittedToken)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }
        if ($detailId <= 0 || !in_array($status, $detailStatuses, true)) {
            throw new RuntimeException("The job detail or its status is invalid.");
        }
        $existingStmt = $pdo->prepare("SELECT * FROM job_details WHERE id = ? AND job_id = ? LIMIT 1");
        $existingStmt->execute([$detailId, $jobId]);
        $existingDetail = $existingStmt->fetch();
        if (!$existingDetail) {
            throw new RuntimeException("Job detail not found.");
        }
        if ($status !== $existingDetail["status"] && in_array($status, ["QC_PASSED", "QC_FAILED", "COMPLETED"], true)) {
            requirePermission("jobs", "approve");
        }

        $costs = [];
        foreach ($costInputs as $field => $value) {
            if (!is_numeric($value) || (float) $value < 0) {
                throw new RuntimeException("All cost amounts must be zero or greater.");
            }
            $costs[$field] = round((float) $value, 2);
        }
        if ($approvedPriceInput !== "" && (!is_numeric($approvedPriceInput) || (float) $approvedPriceInput < 0)) {
            throw new RuntimeException("Customer-approved price must be zero or greater.");
        }
        if ($completionDate !== "") {
            $parsedDate = DateTimeImmutable::createFromFormat("!Y-m-d", $completionDate);
            if (!$parsedDate || $parsedDate->format("Y-m-d") !== $completionDate) {
                throw new RuntimeException("Enter a valid completion date.");
            }
        }

        $updateStmt = $pdo->prepare("UPDATE job_details SET labor_cost = ?, raw_material_cost = ?, consumable_cost = ?, other_cost = ?, assigned_staff_name = ?, status = ?, findings_notes = ?, completion_date = ?, customer_approved_price = ? WHERE id = ? AND job_id = ? LIMIT 1");
        $updateStmt->execute([
            $costs["labor_cost"],
            $costs["raw_material_cost"],
            $costs["consumable_cost"],
            $costs["other_cost"],
            $assignedStaff !== "" ? $assignedStaff : null,
            $status,
            $findings !== "" ? $findings : null,
            $completionDate !== "" ? $completionDate : null,
            $approvedPriceInput !== "" ? round((float) $approvedPriceInput, 2) : null,
            $detailId,
            $jobId,
        ]);

        logAudit("JOBS", "UPDATE", "JOB_DETAIL", $detailId, [
            "status" => $existingDetail["status"],
            "labor_cost" => $existingDetail["labor_cost"],
            "raw_material_cost" => $existingDetail["raw_material_cost"],
            "consumable_cost" => $existingDetail["consumable_cost"],
            "other_cost" => $existingDetail["other_cost"],
        ], [
            "status" => $status,
            "labor_cost" => $costs["labor_cost"],
            "raw_material_cost" => $costs["raw_material_cost"],
            "consumable_cost" => $costs["consumable_cost"],
            "other_cost" => $costs["other_cost"],
        ]);

        $_SESSION["job_detail_message"] = "Job detail was updated.";
        $_SESSION["job_detail_message_type"] = "success";
        header("Location: " . $jobDetailReturnUrl);
        exit;
    } catch (Throwable $exception) {
        $jobDetailMessage = $exception->getMessage();
        $jobDetailMessageType = "error";
    }
}

$detailsStmt = $pdo->prepare("SELECT * FROM job_details WHERE job_id = ? ORDER BY source_sheet_row IS NULL, source_sheet_row ASC, id ASC");
$detailsStmt->execute([$jobId]);
$jobDetails = $detailsStmt->fetchAll();
$activeDetailIndex = min($activeDetailIndex, max(count($jobDetails) - 1, 0));
$totalsStmt = $pdo->prepare("SELECT COUNT(*) AS detail_count, COALESCE(SUM(sales_total), 0) AS sales_total, COALESCE(SUM(total_job_cost), 0) AS total_job_cost, COALESCE(SUM(gross_profit), 0) AS gross_profit FROM job_details WHERE job_id = ?");
$totalsStmt->execute([$jobId]);
$jobTotals = $totalsStmt->fetch();

function jobDetailLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Job Details</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/job-details.css?v=20260929-customer-modal">
</head>
<body class="<?= $embedded ? "job-details-embedded" : "" ?>">
<?php if (!$embedded): ?>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Job Details</h1><p>Track sold work, costs, approval, and completion by detail line.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
<?php else: ?>
<main class="job-details-embedded-main">
<?php endif; ?>
        <section class="job-details-content">
            <?php if (!$embedded): ?><a class="back-to-jobs" href="jobs.php">&larr; Jobs</a><?php endif; ?>
            <?php if ($jobDetailMessage): ?>
                <div class="job-detail-message <?= htmlspecialchars($jobDetailMessageType) ?>" role="alert"><?= htmlspecialchars($jobDetailMessage) ?></div>
            <?php endif; ?>
            <header class="job-detail-page-header">
                <div>
                    <span><?= htmlspecialchars($job["job_no"]) ?> &middot; <?= htmlspecialchars($job["order_no"]) ?></span>
                    <h2><?= htmlspecialchars($job["job_title"]) ?></h2>
                    <p><?= htmlspecialchars($job["fullname"]) ?> (<?= htmlspecialchars($job["customer_no"]) ?>)<?php if ($job["vehicle_no"]): ?> &middot; <?= htmlspecialchars($job["vehicle_no"]) ?><?php endif; ?><?php if ($job["vehicle_name"]): ?> &middot; <?= htmlspecialchars(trim($job["vehicle_name"])) ?><?php endif; ?></p>
                </div>
                <span class="detail-count"><?= number_format((int) $jobTotals["detail_count"]) ?> detail<?= (int) $jobTotals["detail_count"] === 1 ? "" : "s" ?></span>
            </header>

            <div class="job-detail-totals">
                <div><span>Sales Total</span><strong>PHP <?= number_format((float) $jobTotals["sales_total"], 2) ?></strong></div>
                <div><span>Total Job Cost</span><strong>PHP <?= number_format((float) $jobTotals["total_job_cost"], 2) ?></strong></div>
                <div><span>Gross Profit</span><strong class="<?= (float) $jobTotals["gross_profit"] < 0 ? "negative" : "" ?>">PHP <?= number_format((float) $jobTotals["gross_profit"], 2) ?></strong></div>
            </div>

            <?php if (hasPermission("jobs", "create")): ?>
            <section class="job-detail-add-panel">
                <div class="job-detail-section-heading"><div><span>NEW LINE</span><h3>Add Job Detail</h3></div></div>
                <form method="post" class="job-detail-add-form">
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                    <input type="hidden" name="job_detail_token" value="<?= htmlspecialchars($_SESSION["job_detail_token"], ENT_QUOTES) ?>">
                    <input type="hidden" name="detail_index" value="<?= $activeDetailIndex ?>">
                    <?php if ($embedded): ?><input type="hidden" name="embedded" value="1"><?php endif; ?>
                    <div class="job-detail-add-grid">
                        <label>Type<select name="service_type" required><?php foreach ($itemTypes as $type): ?><option value="<?= $type ?>"><?= jobDetailLabel($type) ?></option><?php endforeach; ?></select></label>
                        <label class="detail-description-field">Description<input type="text" name="description" maxlength="255" required></label>
                        <label>Quantity<input type="number" name="quantity" min="0.01" step="0.01" value="1" required></label>
                        <label>Selling Price<input type="number" name="selling_price" min="0" step="0.01" value="0" required></label>
                    </div>
                    <button type="submit" name="create_job_detail" class="job-detail-primary-button">Add Detail</button>
                </form>
            </section>
            <?php endif; ?>

            <section class="job-detail-list-section">
                <div class="job-detail-section-heading">
                    <div><span>LINE ITEMS &amp; COSTING</span><h3>Job Detail Register</h3></div>
                    <?php if ($jobDetails): ?>
                        <nav class="job-detail-navigation" aria-label="Job detail navigation">
                            <button type="button" data-detail-previous aria-label="Previous detail" title="Previous detail" <?= $activeDetailIndex === 0 ? "disabled" : "" ?>>&#10094;</button>
                            <span data-detail-position>Detail <?= $activeDetailIndex + 1 ?> / <?= count($jobDetails) ?></span>
                            <button type="button" data-detail-next aria-label="Next detail" title="Next detail" <?= $activeDetailIndex >= count($jobDetails) - 1 ? "disabled" : "" ?>>&#10095;</button>
                        </nav>
                    <?php endif; ?>
                </div>
                <?php if (!$jobDetails): ?>
                    <div class="job-detail-empty">No detail lines recorded for this job yet.</div>
                <?php else: ?>
                    <div class="job-detail-list" data-detail-index="<?= $activeDetailIndex ?>">
                        <?php foreach ($jobDetails as $detailIndex => $detail): ?>
                            <article class="job-detail-card" data-detail-slide <?= $detailIndex === $activeDetailIndex ? "" : "hidden" ?>>
                                <header class="job-detail-card-header">
                                    <div>
                                        <span><?= htmlspecialchars(jobDetailLabel($detail["service_type"] ?: "OTHER")) ?><?= $detail["order_item_id"] ? " · From quotation" : ($detail["source_sheet_row"] ? " · Imported sheet row " . (int) $detail["source_sheet_row"] : " · Manual detail") ?></span>
                                        <h4><?= htmlspecialchars($detail["description"]) ?></h4>
                                        <small><?= number_format((float) $detail["quantity"], 2) ?> &times; PHP <?= number_format((float) $detail["selling_price"], 2) ?><?php if ($detail["customer_approved_price"] !== null): ?> &middot; Approved PHP <?= number_format((float) $detail["customer_approved_price"], 2) ?><?php endif; ?></small>
                                    </div>
                                    <span class="job-detail-status <?= strtolower($detail["status"]) ?>"><?= htmlspecialchars(jobDetailLabel($detail["status"])) ?></span>
                                </header>
                                <div class="job-detail-financials">
                                    <div><span>Sales Total</span><strong>PHP <?= number_format((float) $detail["sales_total"], 2) ?></strong></div>
                                    <div><span>Total Job Cost</span><strong>PHP <?= number_format((float) $detail["total_job_cost"], 2) ?></strong></div>
                                    <div><span>Gross Profit</span><strong class="<?= (float) $detail["gross_profit"] < 0 ? "negative" : "" ?>">PHP <?= number_format((float) $detail["gross_profit"], 2) ?></strong></div>
                                </div>
                                <?php if (hasPermission("jobs", "approve")): ?>
                                    <form method="post" class="job-detail-approval-form">
                                        <input type="hidden" name="job_id" value="<?= $jobId ?>">
                                        <input type="hidden" name="detail_id" value="<?= (int) $detail["id"] ?>">
                                        <input type="hidden" name="job_detail_token" value="<?= htmlspecialchars($_SESSION["job_detail_token"], ENT_QUOTES) ?>">
                                        <input type="hidden" name="detail_index" value="<?= $activeDetailIndex ?>">
                                        <?php if ($embedded): ?><input type="hidden" name="embedded" value="1"><?php endif; ?>
                                        <label>Approval status<select name="status" required><option value="QC_PASSED">QC Passed</option><option value="QC_FAILED">QC Failed</option><option value="COMPLETED">Completed</option></select></label>
                                        <button type="submit" name="update_job_status" class="job-detail-secondary-button">Update Status</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (hasPermission("jobs", "edit")): ?>
                                <form method="post" class="job-detail-update-form">
                                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                                    <input type="hidden" name="detail_id" value="<?= (int) $detail["id"] ?>">
                                    <input type="hidden" name="job_detail_token" value="<?= htmlspecialchars($_SESSION["job_detail_token"], ENT_QUOTES) ?>">
                                    <input type="hidden" name="detail_index" value="<?= $activeDetailIndex ?>">
                                    <?php if ($embedded): ?><input type="hidden" name="embedded" value="1"><?php endif; ?>
                                    <div class="job-detail-update-grid">
                                        <label>Labor Cost<input type="number" name="labor_cost" min="0" step="0.01" value="<?= htmlspecialchars($detail["labor_cost"]) ?>" required></label>
                                        <label>Raw Material Cost<input type="number" name="raw_material_cost" min="0" step="0.01" value="<?= htmlspecialchars($detail["raw_material_cost"]) ?>" required></label>
                                        <label>Consumable Cost<input type="number" name="consumable_cost" min="0" step="0.01" value="<?= htmlspecialchars($detail["consumable_cost"]) ?>" required></label>
                                        <label>Other Cost<input type="number" name="other_cost" min="0" step="0.01" value="<?= htmlspecialchars($detail["other_cost"]) ?>" required></label>
                                        <label>Assigned Staff<input type="text" name="assigned_staff_name" maxlength="255" value="<?= htmlspecialchars($detail["assigned_staff_name"] ?? "") ?>"></label>
                                        <label>Status<select name="status"><?php foreach ($detailStatuses as $statusOption): ?><?php if (!in_array($statusOption, ["QC_PASSED", "QC_FAILED", "COMPLETED"], true) || hasPermission("jobs", "approve") || $detail["status"] === $statusOption): ?><option value="<?= $statusOption ?>" <?= $detail["status"] === $statusOption ? "selected" : "" ?>><?= jobDetailLabel($statusOption) ?></option><?php endif; ?><?php endforeach; ?></select></label>
                                        <label>Customer Approved Price<input type="number" name="customer_approved_price" min="0" step="0.01" value="<?= $detail["customer_approved_price"] !== null ? htmlspecialchars($detail["customer_approved_price"]) : "" ?>"></label>
                                        <label>Completion Date<input type="date" name="completion_date" value="<?= htmlspecialchars($detail["completion_date"] ?? "") ?>"></label>
                                        <label class="job-detail-findings-field">Findings / Notes<textarea name="findings_notes" rows="2"><?= htmlspecialchars($detail["findings_notes"] ?? "") ?></textarea></label>
                                    </div>
                                    <div class="job-detail-update-actions"><button type="submit" name="update_job_detail" class="job-detail-secondary-button">Save Detail</button></div>
                                </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
<?php if (!$embedded): ?>
</div>
<?php else: ?>
<script>
document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && window.parent !== window) {
        window.parent.postMessage({ type: "close-job-details" }, window.location.origin);
    }
});
</script>
<?php endif; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const detailList = document.querySelector(".job-detail-list[data-detail-index]");
    const previousButton = document.querySelector("[data-detail-previous]");
    const nextButton = document.querySelector("[data-detail-next]");
    const positionLabel = document.querySelector("[data-detail-position]");
    if (!detailList || !previousButton || !nextButton || !positionLabel) {
        return;
    }

    const slides = Array.from(detailList.querySelectorAll("[data-detail-slide]"));
    let currentIndex = Number(detailList.dataset.detailIndex) || 0;

    function showDetail(index) {
        currentIndex = Math.max(0, Math.min(index, slides.length - 1));
        slides.forEach(function (slide, slideIndex) {
            slide.hidden = slideIndex !== currentIndex;
        });
        detailList.querySelectorAll('input[name="detail_index"]').forEach(function (input) {
            input.value = currentIndex;
        });
        positionLabel.textContent = "Detail " + (currentIndex + 1) + " / " + slides.length;
        previousButton.disabled = currentIndex === 0;
        nextButton.disabled = currentIndex === slides.length - 1;
    }

    previousButton.addEventListener("click", function () {
        showDetail(currentIndex - 1);
    });
    nextButton.addEventListener("click", function () {
        showDetail(currentIndex + 1);
    });
    showDetail(currentIndex);
});
</script>
</body>
</html>