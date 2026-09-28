<?php

require_once "includes/header.php";
requirePermission("employees", "view");

$employeeId = (int) ($_GET["id"] ?? $_POST["employee_id"] ?? 0);
if ($employeeId <= 0) {
    header("Location: employees.php");
    exit;
}

$jobDepartments = employeeJobDepartments();
$editing = (string) ($_GET["edit"] ?? "") === "1" || $_SERVER["REQUEST_METHOD"] === "POST";
if ($editing) {
    requirePermission("employees", "edit");
}
$error = "";
$success = "";

if (empty($_SESSION["employee_record_token"])) {
    $_SESSION["employee_record_token"] = bin2hex(random_bytes(32));
}

$employeeStmt = $pdo->prepare("SELECT e.*, u.username, u.email AS account_email, u.status AS account_status FROM employees e LEFT JOIN users u ON u.employee_id = e.id WHERE e.id = ? LIMIT 1");
$employeeStmt->execute([$employeeId]);
$employee = $employeeStmt->fetch();
if (!$employee) {
    http_response_code(404);
    exit("Employee record not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    requirePermission("employees", "edit");
    $token = $_POST["employee_record_token"] ?? "";
    $firstName = trim((string) ($_POST["first_name"] ?? ""));
    $middleName = trim((string) ($_POST["middle_name"] ?? ""));
    $lastName = trim((string) ($_POST["last_name"] ?? ""));
    $nickname = trim((string) ($_POST["nickname"] ?? ""));
    $contactNo = trim((string) ($_POST["contact_no"] ?? ""));
    $address = trim((string) ($_POST["address"] ?? ""));
    $position = trim((string) ($_POST["position"] ?? ""));
    $hireDate = trim((string) ($_POST["hire_date"] ?? ""));
    $dailyRate = trim((string) ($_POST["daily_rate"] ?? ""));
    $hourlyRate = trim((string) ($_POST["hourly_rate"] ?? ""));

    try {
        if (!is_string($token) || !hash_equals($_SESSION["employee_record_token"], $token)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }
        if ($firstName === "" || $lastName === "" || $nickname === "" || $contactNo === "") {
            throw new RuntimeException("First name, last name, nickname, and contact number are required.");
        }
        if (strlen($firstName) > 100 || strlen($middleName) > 100 || strlen($lastName) > 100 || strlen($nickname) > 100 || strlen($contactNo) > 30 || strlen($address) > 5000) {
            throw new RuntimeException("One or more fields exceed their allowed length.");
        }
        if (!array_key_exists($position, $jobDepartments)) {
            throw new RuntimeException("Select a valid job description.");
        }
        if ($hireDate !== "") {
            $parsedDate = DateTimeImmutable::createFromFormat("!Y-m-d", $hireDate);
            if (!$parsedDate || $parsedDate->format("Y-m-d") !== $hireDate) {
                throw new RuntimeException("Enter a valid hire date.");
            }
        }
        if ($dailyRate !== "" && (!is_numeric($dailyRate) || (float) $dailyRate < 0)) {
            throw new RuntimeException("Enter a valid daily rate.");
        }
        if ($hourlyRate !== "" && (!is_numeric($hourlyRate) || (float) $hourlyRate < 0)) {
            throw new RuntimeException("Enter a valid hourly rate.");
        }

        $oldValues = [
            "first_name" => $employee["first_name"],
            "middle_name" => $employee["middle_name"],
            "last_name" => $employee["last_name"],
            "nickname" => $employee["nickname"],
            "contact_no" => $employee["contact_no"],
            "address" => $employee["address"],
            "position" => $employee["position"],
            "department" => $employee["department"],
            "hire_date" => $employee["hire_date"],
            "daily_rate" => $employee["daily_rate"],
            "hourly_rate" => $employee["hourly_rate"],
        ];

        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ?, nickname = ?, contact_no = ?, address = ?, position = ?, department = ?, hire_date = ?, daily_rate = ?, hourly_rate = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $update->execute([
            $firstName,
            $middleName !== "" ? $middleName : null,
            $lastName,
            $nickname,
            $contactNo,
            $address !== "" ? $address : null,
            $position,
            $jobDepartments[$position],
            $hireDate !== "" ? $hireDate : null,
            $dailyRate !== "" ? round((float) $dailyRate, 2) : null,
            $hourlyRate !== "" ? round((float) $hourlyRate, 2) : null,
            $employeeId,
        ]);
        logAudit("EMPLOYEES", "UPDATE", "EMPLOYEE", $employeeId, $oldValues, [
            "first_name" => $firstName,
            "middle_name" => $middleName !== "" ? $middleName : null,
            "last_name" => $lastName,
            "nickname" => $nickname,
            "contact_no" => $contactNo,
            "address" => $address !== "" ? $address : null,
            "position" => $position,
            "department" => $jobDepartments[$position],
            "hire_date" => $hireDate !== "" ? $hireDate : null,
            "daily_rate" => $dailyRate !== "" ? round((float) $dailyRate, 2) : null,
            "hourly_rate" => $hourlyRate !== "" ? round((float) $hourlyRate, 2) : null,
        ]);
        $pdo->commit();
        $_SESSION["employee_record_message"] = "Employee record updated.";
        header("Location: employee-record.php?id=" . $employeeId . "&edit=1");
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof PDOException ? "Unable to save the employee record." : $exception->getMessage();
        $employee = array_merge($employee, [
            "first_name" => $firstName,
            "middle_name" => $middleName,
            "last_name" => $lastName,
            "nickname" => $nickname,
            "contact_no" => $contactNo,
            "address" => $address,
            "position" => $position,
            "department" => $jobDepartments[$position] ?? "",
            "hire_date" => $hireDate,
            "daily_rate" => $dailyRate,
            "hourly_rate" => $hourlyRate,
        ]);
    }
}

if (isset($_SESSION["employee_record_message"])) {
    $success = $_SESSION["employee_record_message"];
    unset($_SESSION["employee_record_message"]);
}
$fullName = trim(implode(" ", array_filter([$employee["first_name"], $employee["middle_name"], $employee["last_name"]])));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Employee Record</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/employees.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1><?= $editing ? "Edit Employee" : "Employee Record" ?></h1><p><?= e($employee["employee_no"]) ?> · <?= e($fullName) ?></p></div>
            <div class="user-info"><div class="user-avatar"><?= strtoupper(substr($user["username"], 0, 1)) ?></div><div><strong><?= e($user["username"]) ?></strong><small><?= e($user["role_name"]) ?></small></div></div>
        </header>
        <section class="dashboard-content">
            <div class="employee-form-card">
                <div class="employee-form-header"><div><span>EMPLOYEE RECORD</span><h2><?= $editing ? "Employment and profile details" : e($fullName) ?></h2></div><a class="employee-action-link" href="employees.php">Back to employees</a></div>
                <?php if ($error !== ""): ?><div class="employee-message error" role="alert"><?= e($error) ?></div><?php endif; ?>
                <?php if ($success !== ""): ?><div class="employee-message success" role="status"><?= e($success) ?></div><?php endif; ?>
                <?php if ($editing): ?>
                    <form method="post">
                        <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
                        <input type="hidden" name="employee_record_token" value="<?= e($_SESSION["employee_record_token"]) ?>">
                        <div class="employee-form-grid">
                            <div class="form-group"><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="100" value="<?= e($employee["first_name"]) ?>" required></div>
                            <div class="form-group"><label for="middle_name">Middle name</label><input id="middle_name" name="middle_name" maxlength="100" value="<?= e($employee["middle_name"] ?? "") ?>"></div>
                            <div class="form-group"><label for="last_name">Last name</label><input id="last_name" name="last_name" maxlength="100" value="<?= e($employee["last_name"]) ?>" required></div>
                            <div class="form-group"><label for="nickname">Nickname</label><input id="nickname" name="nickname" maxlength="100" value="<?= e($employee["nickname"] ?? "") ?>" required></div>
                            <div class="form-group"><label for="contact_no">Contact number</label><input id="contact_no" name="contact_no" maxlength="30" value="<?= e($employee["contact_no"] ?? "") ?>" required></div>
                            <div class="form-group"><label for="position">Job description</label><select id="position" name="position" required><?php foreach ($jobDepartments as $description => $department): ?><option value="<?= e($description) ?>" <?= $employee["position"] === $description ? "selected" : "" ?>><?= e($description) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label for="department">Department</label><input id="department" value="<?= e($jobDepartments[$employee["position"]] ?? $employee["department"] ?? "") ?>" readonly></div>
                            <div class="form-group"><label for="hire_date">Hire date</label><input id="hire_date" name="hire_date" type="date" value="<?= e($employee["hire_date"] ?? "") ?>"></div>
                            <div class="form-group"><label for="daily_rate">Daily rate</label><input id="daily_rate" name="daily_rate" type="number" min="0" step="0.01" value="<?= e($employee["daily_rate"] ?? "") ?>"></div>
                            <div class="form-group"><label for="hourly_rate">Hourly rate</label><input id="hourly_rate" name="hourly_rate" type="number" min="0" step="0.01" value="<?= e($employee["hourly_rate"] ?? "") ?>"></div>
                            <div class="form-group full-width"><label for="address">Address</label><textarea id="address" name="address" rows="3" maxlength="5000"><?= e($employee["address"] ?? "") ?></textarea></div>
                        </div>
                        <div class="employee-form-actions"><button type="submit" class="employee-save-btn">Save changes</button></div>
                    </form>
                <?php else: ?>
                    <dl class="employee-record-grid">
                        <div><dt>Employee number</dt><dd><?= e($employee["employee_no"]) ?></dd></div>
                        <div><dt>Full name</dt><dd><?= e($fullName) ?></dd></div>
                        <div><dt>Nickname</dt><dd><?= e($employee["nickname"] ?: "—") ?></dd></div>
                        <div><dt>Contact number</dt><dd><?= e($employee["contact_no"] ?: "—") ?></dd></div>
                        <div><dt>Job description</dt><dd><?= e($employee["position"] ?: "—") ?></dd></div>
                        <div><dt>Department</dt><dd><?= e($employee["department"] ?: "—") ?></dd></div>
                        <div><dt>Hire date</dt><dd><?= !empty($employee["hire_date"]) ? e(date("M d, Y", strtotime($employee["hire_date"]))) : "—" ?></dd></div>
                        <div><dt>Daily rate</dt><dd><?= $employee["daily_rate"] !== null ? "PHP " . number_format((float) $employee["daily_rate"], 2) : "—" ?></dd></div>
                        <div><dt>Hourly rate</dt><dd><?= $employee["hourly_rate"] !== null ? "PHP " . number_format((float) $employee["hourly_rate"], 2) : "—" ?></dd></div>
                        <div><dt>Address</dt><dd><?= e($employee["address"] ?: "—") ?></dd></div>
                        <div><dt>Username</dt><dd><?= e($employee["username"] ?: "No account") ?></dd></div>
                        <div><dt>Account status</dt><dd><?= e($employee["account_status"] ?: "—") ?></dd></div>
                    </dl>
                    <?php if (hasPermission("employees", "edit")): ?><div class="employee-form-actions"><a class="employee-save-btn" href="employee-record.php?id=<?= $employeeId ?>&amp;edit=1">Edit employee</a></div><?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const position = document.getElementById("position");
    const department = document.getElementById("department");
    if (position && department) {
        position.addEventListener("change", function () {
            department.value = position.selectedOptions[0]?.dataset.department || "";
        });
    }
});
</script>
</body>
</html>