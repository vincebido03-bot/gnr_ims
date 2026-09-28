<?php

require_once "../admin/includes/auth.php";
requireLogin();
requireRole("EMPLOYEE");
requirePermission("employees", "edit");

$employeeId = (int) ($_SESSION["employee_id"] ?? 0);
$userId = (int) $_SESSION["user_id"];
if (!$employeeId) {
    http_response_code(403);
    exit("Employee account is not properly linked.");
}

if (empty($_SESSION["employee_account_token"])) {
    $_SESSION["employee_account_token"] = bin2hex(random_bytes(32));
}

$jobDepartments = employeeJobDepartments();
$error = "";
$success = "";
$submittedProfileFields = null;

$profileStmt = $pdo->prepare("SELECT e.*, u.username, u.email, u.password, u.status AS account_status, u.profile_picture FROM employees e INNER JOIN users u ON u.employee_id = e.id WHERE e.id = ? AND u.id = ? LIMIT 1");
$profileStmt->execute([$employeeId, $userId]);
$profile = $profileStmt->fetch();
if (!$profile) {
    http_response_code(404);
    exit("Employee profile not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["employee_account_token"] ?? "";
    $firstName = trim((string) ($_POST["first_name"] ?? ""));
    $middleName = trim((string) ($_POST["middle_name"] ?? ""));
    $lastName = trim((string) ($_POST["last_name"] ?? ""));
    $nickname = trim((string) ($_POST["nickname"] ?? ""));
    $contactNo = trim((string) ($_POST["contact_no"] ?? ""));
    $address = trim((string) ($_POST["address"] ?? ""));
    $position = trim((string) ($_POST["position"] ?? ""));
    $department = $position === "" ? null : ($jobDepartments[$position] ?? null);
    $email = trim((string) ($_POST["email"] ?? ""));
    $currentPassword = (string) ($_POST["current_password"] ?? "");
    $newPassword = (string) ($_POST["new_password"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");
    $newPicture = null;
    $submittedProfileFields = [
        "first_name" => $firstName,
        "middle_name" => $middleName,
        "last_name" => $lastName,
        "nickname" => $nickname,
        "contact_no" => $contactNo,
        "address" => $address,
        "position" => $position,
        "department" => $department,
        "email" => $email,
    ];

    try {
        if (!is_string($token) || !hash_equals($_SESSION["employee_account_token"], $token)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }
        if ($firstName === "" || $middleName === "" || $lastName === "" || $nickname === "" || $contactNo === "" || strlen($firstName) > 100 || strlen($middleName) > 100 || strlen($lastName) > 100) {
            throw new RuntimeException("First, middle, and last name, nickname, and contact number are required.");
        }
        if (strlen($middleName) > 100 || strlen($nickname) > 100 || strlen($contactNo) > 30 || strlen($address) > 5000) {
            throw new RuntimeException("One or more profile fields exceed their allowed length.");
        }
        if ($position === "" || !array_key_exists($position, $jobDepartments)) {
            throw new RuntimeException("Select a valid job description.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new RuntimeException("Enter a valid email address up to 150 characters.");
        }

        $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $emailStmt->execute([$email, $userId]);
        if ($emailStmt->fetchColumn()) {
            throw new RuntimeException("That email address is already in use.");
        }

        if ($currentPassword !== "" || $newPassword !== "" || $confirmPassword !== "") {
            if ($currentPassword === "" || !password_verify($currentPassword, $profile["password"])) {
                throw new RuntimeException("Enter your current password to change it.");
            }
            if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
                throw new RuntimeException("New passwords must match and contain at least 8 characters.");
            }
        }

        $newPicture = storeUserProfilePicture($_FILES["profile_picture"] ?? []);
        $pdo->beginTransaction();
        $employeeUpdate = $pdo->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ?, nickname = ?, contact_no = ?, address = ?, position = ?, department = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        $employeeUpdate->execute([
            $firstName,
            $middleName !== "" ? $middleName : null,
            $lastName,
            $nickname !== "" ? $nickname : null,
            $contactNo !== "" ? $contactNo : null,
            $address !== "" ? $address : null,
            $position !== "" ? $position : null,
            $department,
            $employeeId,
        ]);

        if ($newPassword !== "") {
            $userUpdate = $pdo->prepare("UPDATE users SET email = ?, profile_picture = COALESCE(?, profile_picture), password = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
            $userUpdate->execute([$email, $newPicture, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        } else {
            $userUpdate = $pdo->prepare("UPDATE users SET email = ?, profile_picture = COALESCE(?, profile_picture), updated_at = NOW() WHERE id = ? LIMIT 1");
            $userUpdate->execute([$email, $newPicture, $userId]);
        }

        logAudit("ACCOUNT SETTINGS", "UPDATE", "USER", $userId, null, [
            "employee_id" => $employeeId,
            "email" => $email,
            "profile_picture_updated" => $newPicture !== null,
            "password_updated" => $newPassword !== "",
            "position" => $position,
            "department" => $department,
        ]);
        $pdo->commit();

        if ($newPicture !== null && !empty($profile["profile_picture"])) {
            removeUserProfilePicture($profile["profile_picture"]);
        }
        $_SESSION["employee_account_message"] = "Account settings saved.";
        header("Location: account-settings.php");
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($newPicture !== null) {
            removeUserProfilePicture($newPicture);
        }
        $error = $exception instanceof PDOException ? "Unable to save your account settings." : $exception->getMessage();
    }
}

if (isset($_SESSION["employee_account_message"])) {
    $success = $_SESSION["employee_account_message"];
    unset($_SESSION["employee_account_message"]);
}
$profileStmt->execute([$employeeId, $userId]);
$profile = $profileStmt->fetch();
if ($error !== "" && $submittedProfileFields !== null) {
    $profile = array_merge($profile, $submittedProfileFields);
}
$fullName = trim($profile["first_name"] . " " . ($profile["middle_name"] ? $profile["middle_name"] . " " : "") . $profile["last_name"]);
$initials = strtoupper(substr($profile["first_name"], 0, 1) . substr($profile["last_name"], 0, 1));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | GNR Employee Portal</title>
    <link rel="stylesheet" href="css/employee.css">
    <link rel="stylesheet" href="css/account-settings.css">
</head>
<body>
<?php include "includes/sidebar.php"; ?>
<main class="employee-main">
    <section class="employee-account-page">
        <header class="employee-account-heading">
            <span>EMPLOYEE PORTAL / ACCOUNT</span>
            <h1>Account Settings</h1>
            <p>Keep your contact details and profile photo up to date.</p>
        </header>
        <?php if ($error !== ""): ?><div class="account-message error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success !== ""): ?><div class="account-message success" role="status"><?= e($success) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="employee-account-form">
            <input type="hidden" name="employee_account_token" value="<?= e($_SESSION["employee_account_token"]) ?>">
            <section class="employee-account-card employee-account-photo-card">
                <div class="employee-account-photo">
                    <?php if (!empty($profile["profile_picture"])): ?>
                        <img src="../<?= e($profile["profile_picture"]) ?>" alt="Profile picture">
                    <?php else: ?>
                        <span><?= e($initials) ?></span>
                    <?php endif; ?>
                </div>
                <div class="employee-account-photo-copy">
                    <h2><?= e($fullName) ?></h2>
                    <p><?= e($profile["employee_no"]) ?> · <?= e($profile["position"] ?: "Employee") ?></p>
                    <label for="profile_picture">Profile picture</label>
                    <input id="profile_picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/webp">
                    <small>JPEG, PNG, or WebP. Maximum 5 MB.</small>
                </div>
            </section>
            <section class="employee-account-card">
                <div class="employee-account-section-heading"><span>PROFILE</span><h2>Personal information</h2></div>
                <div class="employee-account-grid">
                    <label>First name<input name="first_name" maxlength="100" value="<?= e($profile["first_name"]) ?>" required></label>
                    <label>Middle name<input name="middle_name" maxlength="100" value="<?= e($profile["middle_name"] ?? "") ?>" required></label>
                    <label>Last name<input name="last_name" maxlength="100" value="<?= e($profile["last_name"]) ?>" required></label>
                    <label>Nickname<input name="nickname" maxlength="100" value="<?= e($profile["nickname"] ?? "") ?>" required></label>
                    <label>Contact number<input name="contact_no" maxlength="30" value="<?= e($profile["contact_no"] ?? "") ?>" autocomplete="tel" required></label>
                    <label>Job description
                        <select id="position" name="position" required>
                            <option value="" disabled <?= empty($profile["position"]) ? "selected" : "" ?>>Select job description</option>
                            <?php foreach ($jobDepartments as $jobDescription => $jobDepartment): ?>
                                <option value="<?= e($jobDescription) ?>" data-department="<?= e($jobDepartment) ?>" <?= $profile["position"] === $jobDescription ? "selected" : "" ?>><?= e($jobDescription) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Department<input id="department" value="<?= e($profile["department"] ?? "") ?>" readonly placeholder="Auto-assigned from job description"></label>
                    <label class="employee-account-full">Address<textarea name="address" rows="3" maxlength="5000" autocomplete="street-address"><?= e($profile["address"] ?? "") ?></textarea></label>
                </div>
            </section>
            <section class="employee-account-card">
                <div class="employee-account-section-heading"><span>WORK &amp; PAYROLL</span><h2>Employment information</h2></div>
                <div class="employee-account-grid">
                    <label>Employee number<input value="<?= e($profile["employee_no"]) ?>" readonly></label>
                    <label>Hire date<input value="<?= !empty($profile["hire_date"]) ? e(date("F d, Y", strtotime($profile["hire_date"]))) : "Not set" ?>" readonly></label>
                    <label>Daily rate<input value="<?= $profile["daily_rate"] !== null ? "PHP " . number_format((float) $profile["daily_rate"], 2) : "Not set" ?>" readonly></label>
                    <label>Hourly rate<input value="<?= $profile["hourly_rate"] !== null ? "PHP " . number_format((float) $profile["hourly_rate"], 2) : "Not set" ?>" readonly></label>
                </div>
                <small class="employee-account-managed-note">Employee number, hire date, and pay rates are managed by HR.</small>
            </section>
            <section class="employee-account-card">
                <div class="employee-account-section-heading"><span>LOGIN</span><h2>Account details</h2></div>
                <div class="employee-account-grid">
                    <label>Username<input value="<?= e($profile["username"]) ?>" readonly></label>
                    <label>Email<input name="email" type="email" maxlength="150" value="<?= e($profile["email"] ?? "") ?>" autocomplete="email" required></label>
                </div>
                <div class="employee-account-password-heading"><strong>Change password</strong><small>Leave these fields blank to keep your current password.</small></div>
                <div class="employee-account-grid">
                    <label>Current password<input name="current_password" type="password" autocomplete="current-password"></label>
                    <label>New password<input name="new_password" type="password" minlength="8" autocomplete="new-password"></label>
                    <label>Confirm new password<input name="confirm_password" type="password" minlength="8" autocomplete="new-password"></label>
                </div>
            </section>
            <div class="employee-account-actions"><button type="submit">Save account settings</button></div>
        </form>
    </section>
</main>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const position = document.getElementById("position");
    const department = document.getElementById("department");
    if (!position || !department) return;
    position.addEventListener("change", function () {
        department.value = position.selectedOptions[0]?.dataset.department || "";
    });
});
</script>
</body>
</html>