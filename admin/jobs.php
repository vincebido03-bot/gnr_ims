<?php

require_once "includes/header.php";

$search = trim($_GET["search"] ?? "");
$status = strtoupper(trim($_GET["status"] ?? ""));
$priority = strtoupper(trim($_GET["priority"] ?? ""));
$statuses = ["PENDING", "SCHEDULED", "ASSIGNED", "IN_PROGRESS", "PAUSED", "FOR_QC", "QC_FAILED", "COMPLETED", "CANCELLED"];
$priorities = ["LOW", "NORMAL", "HIGH", "URGENT"];

$sql = "
        SELECT j.id, j.job_no, j.job_title, j.priority, j.status, j.scheduled_start, j.scheduled_end,
            o.order_no, o.vehicle_id, c.id AS customer_id, c.customer_no, c.fullname,
            v.vehicle_no, CONCAT(v.brand, ' ', v.model) AS vehicle_name, v.plate_no,
            COALESCE(detail_counts.detail_count, 0) AS detail_count,
            COALESCE(assigned.employee_names, NULLIF(j.assigned_staff_name, ''), 'Unassigned') AS employee_names
    FROM jobs j
    INNER JOIN orders o ON o.id = j.order_id
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN vehicles v ON v.id = o.vehicle_id
    LEFT JOIN (
        SELECT job_id, COUNT(*) AS detail_count
        FROM job_details
        GROUP BY job_id
    ) detail_counts ON detail_counts.job_id = j.id
    LEFT JOIN (
        SELECT ja.job_id,
               GROUP_CONCAT(CONCAT(e.first_name, ' ', e.last_name) ORDER BY e.last_name SEPARATOR ', ') AS employee_names
        FROM job_assignments ja
        INNER JOIN employees e ON e.id = ja.employee_id
        WHERE ja.status <> 'REMOVED'
        GROUP BY ja.job_id
    ) assigned ON assigned.job_id = j.id
    WHERE 1 = 1
";
$params = [];

if ($search !== "") {
    $sql .= " AND (j.job_no LIKE ? OR j.job_title LIKE ? OR o.order_no LIKE ? OR c.customer_no LIKE ? OR c.fullname LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}

if (in_array($status, $statuses, true)) {
    $sql .= " AND j.status = ?";
    $params[] = $status;
}

if (in_array($priority, $priorities, true)) {
    $sql .= " AND j.priority = ?";
    $params[] = $priority;
}

$sql .= " ORDER BY FIELD(j.priority, 'URGENT', 'HIGH', 'NORMAL', 'LOW'), j.scheduled_start IS NULL, j.scheduled_start ASC, j.job_no DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
$customerJobsStmt = $pdo->query("SELECT j.id, j.job_no, j.priority, j.scheduled_start, o.customer_id, o.order_no FROM jobs j INNER JOIN orders o ON o.id = j.order_id ORDER BY FIELD(j.priority, 'URGENT', 'HIGH', 'NORMAL', 'LOW'), j.scheduled_start IS NULL, j.scheduled_start ASC, j.job_no DESC");
$customerJobsByCustomer = [];
foreach ($customerJobsStmt->fetchAll() as $customerJob) {
    $customerJobsByCustomer[$customerJob["customer_id"]][] = [
        "id" => (int) $customerJob["id"],
        "order_number" => $customerJob["order_no"],
    ];
}

function jobLabel($value)
{
    return ucwords(strtolower(str_replace("_", " ", $value)));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Jobs</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/jobs.css?v=20260929-customer-modal-controls">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Jobs</h1><p>Monitor service work, schedules, assignments, and quality control readiness.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div>
                <div><strong><?= htmlspecialchars($user["username"]) ?></strong><small><?= htmlspecialchars($user["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="jobs-content">
            <div class="jobs-header">
                <div><span>SERVICE WORKFLOW</span><h2>Job Register</h2></div>
                <strong><?= number_format(count($jobs)) ?> job<?= count($jobs) === 1 ? "" : "s" ?></strong>
            </div>
            <form class="jobs-filters" method="get">
                <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search job, order, customer, or title">
                <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $statusOption): ?><option value="<?= $statusOption ?>" <?= $status === $statusOption ? "selected" : "" ?>><?= jobLabel($statusOption) ?></option><?php endforeach; ?></select>
                <select name="priority"><option value="">All priorities</option><?php foreach ($priorities as $priorityOption): ?><option value="<?= $priorityOption ?>" <?= $priority === $priorityOption ? "selected" : "" ?>><?= jobLabel($priorityOption) ?></option><?php endforeach; ?></select>
                <button type="submit">Filter</button>
                <a href="jobs.php">Clear</a>
            </form>
            <div class="jobs-table-wrap">
                <table class="jobs-table">
                    <thead><tr><th>Job</th><th>Customer / Order</th><th>Vehicle</th><th>Target Date</th><th>Assigned Staff</th><th>Priority</th><th>Status</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php if (!$jobs): ?>
                        <tr><td colspan="8" class="empty-state">No job records found.</td></tr>
                    <?php else: foreach ($jobs as $job): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($job["job_no"]) ?></strong><small><?= htmlspecialchars($job["job_title"]) ?></small></td>
                            <td><strong><?= htmlspecialchars($job["fullname"]) ?></strong><small><?= htmlspecialchars($job["customer_no"]) ?> · <?= htmlspecialchars($job["order_no"]) ?></small></td>
                            <td><?= htmlspecialchars(trim($job["vehicle_name"] ?? "") ?: "Unit not recorded") ?><?php if ($job["vehicle_no"]): ?><small><?= htmlspecialchars($job["vehicle_no"]) ?></small><?php endif; ?><?php if ($job["plate_no"]): ?><small><?= htmlspecialchars($job["plate_no"]) ?></small><?php endif; ?></td>
                            <td><?php $targetDate = $job["scheduled_end"] ?: $job["scheduled_start"]; ?><?= $targetDate ? htmlspecialchars(date("M d, Y", strtotime($targetDate))) : "Not scheduled" ?></td>
                            <td><?= htmlspecialchars($job["employee_names"]) ?></td>
                            <td><span class="job-priority <?= strtolower($job["priority"]) ?>"><?= htmlspecialchars(jobLabel($job["priority"])) ?></span></td>
                            <td><span class="job-status <?= strtolower($job["status"]) ?>"><?= htmlspecialchars(jobLabel($job["status"])) ?></span></td>
                            <td><a class="job-detail-link" href="job-details.php?job_id=<?= (int) $job["id"] ?>" data-job-detail-open data-job-id="<?= (int) $job["id"] ?>" data-detail-count="<?= (int) $job["detail_count"] ?>" data-customer-jobs="<?= htmlspecialchars(json_encode($customerJobsByCustomer[$job["customer_id"]] ?? []), ENT_QUOTES, "UTF-8") ?>">Details <span><?= number_format((int) $job["detail_count"]) ?></span></a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<div class="job-details-modal" id="jobDetailsModal" aria-hidden="true">
    <button type="button" class="job-details-modal-backdrop" data-job-details-close aria-label="Close job details"></button>
    <section class="job-details-modal-dialog" role="dialog" aria-modal="true" aria-label="Job Details">
        <header class="job-details-modal-header">
            <strong>Job Details</strong>
            <nav class="job-details-modal-nav" aria-label="Selected customer's jobs">
                <button type="button" id="jobDetailsPrevious" aria-label="Previous job" title="Previous job" disabled>&#10094;</button>
                <span id="jobDetailsPosition"></span>
                <button type="button" id="jobDetailsNext" aria-label="Next job" title="Next job" disabled>&#10095;</button>
            </nav>
            <button type="button" class="job-details-modal-close" data-job-details-close aria-label="Close job details" title="Close">&times;</button>
        </header>
        <iframe id="jobDetailsFrame" title="Selected job details" loading="eager"></iframe>
    </section>
</div>
<script src="js/jobs.js?v=20260929-customer-modal-controls"></script>
</body>
</html>