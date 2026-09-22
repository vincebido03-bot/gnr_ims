<?php

require_once "includes/header.php";

requirePermission("employees", "create");


/* =========================================================
   VARIABLES
   ========================================================== */

$error = "";
$success = "";


/* =========================================================
   GET EXISTING EMPLOYEES
   ========================================================== */

$employeeStmt = $pdo->query("
    SELECT
        e.id,
        e.employee_no,
        e.first_name,
        e.last_name,
        e.nickname,
        e.status
    FROM employees e
    WHERE e.status = 'ACTIVE'
      AND NOT EXISTS (
          SELECT 1
          FROM users u
          WHERE u.employee_id = e.id
      )
    ORDER BY e.last_name ASC, e.first_name ASC
");

$employees = $employeeStmt->fetchAll();


/* =========================================================
   GET ROLES
   ========================================================== */

$roleStmt = $pdo->query("
    SELECT
        id,
        role_name
    FROM roles
    ORDER BY id ASC
");

$roles = $roleStmt->fetchAll();


/* =========================================================
   CREATE USER
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $employeeId = (int) ($_POST["employee_id"] ?? 0);
    $roleId = (int) ($_POST["role_id"] ?? 0);
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (
        !$employeeId ||
        !$roleId ||
        $username === "" ||
        $email === "" ||
        $password === ""
    ) {

        $error = "Please complete all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } else {

        /* =================================================
           VERIFY EMPLOYEE
           ================================================== */

        $employeeCheck = $pdo->prepare("
            SELECT
                id,
                employee_no,
                first_name,
                last_name,
                nickname
            FROM employees
            WHERE id = ?
              AND status = 'ACTIVE'
            LIMIT 1
        ");

        $employeeCheck->execute([
            $employeeId
        ]);

        $employee = $employeeCheck->fetch();


        if (!$employee) {

            $error = "Selected employee does not exist or is inactive.";

        } else {

            /* =============================================
               CHECK EXISTING ACCOUNT
               ============================================== */

            $accountCheck = $pdo->prepare("
                SELECT id
                FROM users
                WHERE employee_id = ?
                LIMIT 1
            ");

            $accountCheck->execute([
                $employeeId
            ]);

            if ($accountCheck->fetch()) {

                $error = "This employee already has a user account.";

            } else {

                /* =========================================
                   CHECK USERNAME
                   ========================================== */

                $usernameCheck = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ?
                    LIMIT 1
                ");

                $usernameCheck->execute([
                    $username
                ]);

                if ($usernameCheck->fetch()) {

                    $error = "Username is already taken.";

                } else {

                    /* =====================================
                       CHECK EMAIL
                       ====================================== */

                    $emailCheck = $pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE email = ?
                        LIMIT 1
                    ");

                    $emailCheck->execute([
                        $email
                    ]);

                    if ($emailCheck->fetch()) {

                        $error = "Email is already registered.";

                    } else {

                        /* =================================
                           CREATE ACCOUNT
                           ================================== */

                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $insert = $pdo->prepare("
                            INSERT INTO users (
                                employee_id,
                                role_id,
                                username,
                                email,
                                password,
                                status
                            )
                            VALUES (?, ?, ?, ?, ?, 'ACTIVE')
                        ");

                        $insert->execute([
                            $employeeId,
                            $roleId,
                            $username,
                            $email,
                            $passwordHash
                        ]);

                        $newUserId = $pdo->lastInsertId();


                        /* =================================
                           AUDIT
                           ================================== */

                        logAudit(
                            "USER MANAGEMENT",
                            "CREATE",
                            "USER",
                            $newUserId,
                            null,
                            [
                                "employee_id" => $employeeId,
                                "role_id" => $roleId,
                                "username" => $username,
                                "email" => $email,
                                "status" => "ACTIVE"
                            ]
                        );


                        $success = "User account created successfully.";


                        /* =================================
                           REFRESH EMPLOYEE LIST
                           ================================== */

                        $employeeStmt = $pdo->query("
                            SELECT
                                e.id,
                                e.employee_no,
                                e.first_name,
                                e.last_name,
                                e.nickname,
                                e.status
                            FROM employees e
                            WHERE e.status = 'ACTIVE'
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM users u
                                  WHERE u.employee_id = e.id
                              )
                            ORDER BY e.last_name ASC, e.first_name ASC
                        ");

                        $employees = $employeeStmt->fetchAll();

                    }

                }

            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>GNR IMS - Create User</title>

    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/create-user.css">

</head>

<body>

<div class="admin-layout">


    <?php require_once "includes/sidebar.php"; ?>


    <main class="main-content">


        <!-- =================================================
             TOPBAR
             ================================================== -->

        <header class="topbar">

            <div>

                <h1>
                    Create User Account
                </h1>

                <p>
                    Link an existing employee to a system login.
                </p>

            </div>


            <div class="user-info">

                <div class="user-avatar">

                    <?= strtoupper(
                        substr($user["username"], 0, 1)
                    ) ?>

                </div>

                <div>

                    <strong>
                        <?= htmlspecialchars($user["username"]) ?>
                    </strong>

                    <small>
                        <?= htmlspecialchars($user["role_name"]) ?>
                    </small>

                </div>

            </div>

        </header>


        <!-- =================================================
             CONTENT
             ================================================== -->

        <section class="dashboard-content">

            <div class="form-page">


                <?php if ($error): ?>

                    <div class="form-message error">

                        <?= htmlspecialchars($error) ?>

                    </div>

                <?php endif; ?>


                <?php if ($success): ?>

                    <div class="form-message success">

                        <?= htmlspecialchars($success) ?>

                    </div>

                <?php endif; ?>


                <div class="form-card">

                    <div class="form-card-header">

                        <div>

                            <span>
                                USER MANAGEMENT
                            </span>

                            <h2>
                                New System Account
                            </h2>

                        </div>

                    </div>


                    <?php if (count($employees) > 0): ?>


                        <form method="POST">


                            <!-- =================================
                                 EMPLOYEE
                                 ================================== -->

                            <div class="form-group">

                                <label for="employee_id">
                                    Employee
                                </label>

                                <select
                                    id="employee_id"
                                    name="employee_id"
                                    required
                                >

                                    <option value="">
                                        Select existing employee
                                    </option>

                                    <?php foreach ($employees as $employee): ?>

                                        <option
                                            value="<?= (int) $employee["id"] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $employee["employee_no"] .
                                                " - " .
                                                (
                                                    !empty($employee["nickname"])
                                                    ? $employee["nickname"]
                                                    : trim(
                                                        $employee["first_name"] .
                                                        " " .
                                                        $employee["last_name"]
                                                    )
                                                )
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- =================================
                                 ROLE
                                 ================================== -->

                            <div class="form-group">

                                <label for="role_id">
                                    System Role
                                </label>

                                <select
                                    id="role_id"
                                    name="role_id"
                                    required
                                >

                                    <option value="">
                                        Select role
                                    </option>

                                    <?php foreach ($roles as $role): ?>

                                        <option
                                            value="<?= (int) $role["id"] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $role["role_name"]
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- =================================
                                 USERNAME
                                 ================================== -->

                            <div class="form-group">

                                <label for="username">
                                    Username
                                </label>

                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    maxlength="100"
                                    required
                                >

                            </div>


                            <!-- =================================
                                 EMAIL
                                 ================================== -->

                            <div class="form-group">

                                <label for="email">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    maxlength="150"
                                    required
                                >

                            </div>


                            <!-- =================================
                                 PASSWORD
                                 ================================== -->

                            <div class="form-group">

                                <label for="password">
                                    Temporary Password
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    required
                                >

                                <small>
                                    Minimum 8 characters.
                                </small>

                            </div>


                            <button
                                type="submit"
                                class="save-btn"
                            >
                                CREATE ACCOUNT
                            </button>


                        </form>


                    <?php else: ?>


                        <div class="empty-form">

                            <div>
                                👥
                            </div>

                            <h3>
                                No Available Employees
                            </h3>

                            <p>
                                All active employees already have
                                user accounts, or there are currently
                                no active employee records.
                            </p>

                        </div>


                    <?php endif; ?>


                </div>

            </div>

        </section>

    </main>

</div>


<script src="js/admin.js"></script>

</body>

</html>