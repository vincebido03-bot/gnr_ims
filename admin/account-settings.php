<?php

require_once "includes/header.php";

$userId = (int) $_SESSION["user_id"];
if (empty($_SESSION["admin_account_token"])) {
    $_SESSION["admin_account_token"] = bin2hex(random_bytes(32));
}

$accountStmt = $pdo->prepare("SELECT u.id, u.display_name, u.username, u.email, u.password, u.profile_picture, u.status, r.role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1");
$accountStmt->execute([$userId]);
$account = $accountStmt->fetch();
if (!$account) {
    http_response_code(404);
    exit("Account not found.");
}

$error = "";
$success = "";
$displayName = trim((string) ($account["display_name"] ?: $account["username"]));
$email = (string) ($account["email"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $displayName = trim((string) ($_POST["display_name"] ?? ""));
    $email = trim((string) ($_POST["email"] ?? ""));
    $currentPassword = (string) ($_POST["current_password"] ?? "");
    $newPassword = (string) ($_POST["new_password"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");
    $token = $_POST["admin_account_token"] ?? "";
    $newPicture = null;

    try {
        if (!is_string($token) || !hash_equals($_SESSION["admin_account_token"], $token)) {
            throw new RuntimeException("This form has expired. Reload the page and try again.");
        }
        if ($displayName === "" || strlen($displayName) > 200) {
            throw new RuntimeException("Enter a display name up to 200 characters.");
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
            if ($currentPassword === "" || !password_verify($currentPassword, $account["password"])) {
                throw new RuntimeException("Enter your current password to change it.");
            }
            if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
                throw new RuntimeException("New passwords must match and contain at least 8 characters.");
            }
        }

        $newPicture = storeUserProfilePicture($_FILES["profile_picture"] ?? []);
        $pdo->beginTransaction();
        if ($newPassword !== "") {
            $update = $pdo->prepare("UPDATE users SET display_name = ?, email = ?, profile_picture = COALESCE(?, profile_picture), password = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
            $update->execute([$displayName, $email, $newPicture, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        } else {
            $update = $pdo->prepare("UPDATE users SET display_name = ?, email = ?, profile_picture = COALESCE(?, profile_picture), updated_at = NOW() WHERE id = ? LIMIT 1");
            $update->execute([$displayName, $email, $newPicture, $userId]);
        }

        logAudit("ACCOUNT SETTINGS", "UPDATE", "USER", $userId, null, [
            "display_name" => $displayName,
            "email" => $email,
            "profile_picture_updated" => $newPicture !== null,
            "password_updated" => $newPassword !== "",
        ]);
        $pdo->commit();

        if ($newPicture !== null && !empty($account["profile_picture"])) {
            removeUserProfilePicture($account["profile_picture"]);
        }
        $_SESSION["admin_account_message"] = "Account settings saved.";
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

if (isset($_SESSION["admin_account_message"])) {
    $success = $_SESSION["admin_account_message"];
    unset($_SESSION["admin_account_message"]);
}
$accountStmt->execute([$userId]);
$account = $accountStmt->fetch();
$displayName = trim((string) ($account["display_name"] ?: $account["username"]));
$email = (string) ($account["email"] ?? "");
$initial = strtoupper(substr($displayName, 0, 1));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GNR IMS - Account Settings</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/account-settings.css">
</head>
<body>
<div class="admin-layout">
    <?php require_once "includes/sidebar.php"; ?>
    <main class="main-content">
        <header class="topbar">
            <div><h1>Account Settings</h1><p>Manage your own profile and login details.</p></div>
            <div class="user-info">
                <div class="user-avatar"><?= e($initial) ?></div>
                <div><strong><?= e($displayName) ?></strong><small><?= e($account["role_name"]) ?></small></div>
            </div>
        </header>
        <section class="admin-account-settings-content">
            <?php if ($error !== ""): ?><div class="account-settings-message error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success !== ""): ?><div class="account-settings-message success" role="status"><?= e($success) ?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="admin-account-settings-form">
                <input type="hidden" name="admin_account_token" value="<?= e($_SESSION["admin_account_token"]) ?>">
                <section class="admin-account-settings-card admin-account-identity">
                    <div class="admin-account-photo">
                        <?php if (!empty($account["profile_picture"])): ?>
                            <img src="../<?= e($account["profile_picture"]) ?>" alt="Profile picture">
                        <?php else: ?>
                            <span><?= e($initial) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-account-identity-fields">
                        <label>Profile picture<input name="profile_picture" type="file" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG, or WebP. Maximum 5 MB.</small></label>
                        <label>Display name<input name="display_name" maxlength="200" value="<?= e($displayName) ?>" autocomplete="name" required></label>
                    </div>
                </section>
                <section class="admin-account-settings-card">
                    <div class="admin-account-section-heading"><span>LOGIN</span><h2>Account details</h2></div>
                    <div class="admin-account-fields">
                        <label>Username<input value="<?= e($account["username"]) ?>" readonly></label>
                        <label>Email<input name="email" type="email" maxlength="150" value="<?= e($email) ?>" autocomplete="email" required></label>
                    </div>
                    <div class="admin-account-password-heading"><strong>Change password</strong><small>Leave password fields blank to keep your current password.</small></div>
                    <div class="admin-account-fields">
                        <label>Current password<input name="current_password" type="password" autocomplete="current-password"></label>
                        <label>New password<input name="new_password" type="password" minlength="8" autocomplete="new-password"></label>
                        <label>Confirm new password<input name="confirm_password" type="password" minlength="8" autocomplete="new-password"></label>
                    </div>
                </section>
                <div class="admin-account-settings-actions"><button type="submit">Save account settings</button></div>
            </form>
        </section>
    </main>
</div>
</body>
</html>