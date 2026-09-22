<?php

require_once "includes/header.php";

requirePermission("employees", "view");

$error = "";
$message = "";

if (isset($_SESSION["employee_success"])) {
    $message = $_SESSION["employee_success"];
    unset($_SESSION["employee_success"]);
}

$success = $message;


/* =========================================================
   HANDLE ADD EMPLOYEE + USER ACCOUNT
   ========================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["form_action"]) &&
    $_POST["form_action"] === "add_employee"
) {

    if (!hasPermission("employees", "create")) {
        http_response_code(403);
        die("Access Denied.");
    }


    /* =====================================================
       EMPLOYEE INFORMATION
       ====================================================== */

    $firstName = trim($_POST["first_name"] ?? "");
    $middleName = trim($_POST["middle_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $nickname = trim($_POST["nickname"] ?? "");
    $contactNo = trim($_POST["contact_no"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $position = trim($_POST["position"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $hireDate = trim($_POST["hire_date"] ?? "");
    $dailyRate = trim($_POST["daily_rate"] ?? "");
    $hourlyRate = trim($_POST["hourly_rate"] ?? "");


    /* =====================================================
       LOGIN ACCOUNT INFORMATION
       ====================================================== */

    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";


    /* =====================================================
       VALIDATION
       ====================================================== */

    if (
        $firstName === "" ||
        $lastName === "" ||
        $position === "" ||
        $department === "" ||
        $hireDate === "" ||
        $dailyRate === "" ||
        $username === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {

        $error = "Please complete all required fields.";

    } elseif (!is_numeric($dailyRate) || (float) $dailyRate < 0) {

        $error = "Please enter a valid daily rate.";

    } elseif (
        $hourlyRate !== "" &&
        (!is_numeric($hourlyRate) || (float) $hourlyRate < 0)
    ) {

        $error = "Please enter a valid hourly rate.";

    } elseif (
        !preg_match(
            "/^\d{4}-\d{2}-\d{2}$/",
            $hireDate
        )
    ) {

        $error = "Please select a valid hire date.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $error = "Password and confirm password do not match.";

    } else {

        try {

            /* =================================================
               START TRANSACTION
               ================================================== */

            $pdo->beginTransaction();


            /* =================================================
               GET EMPLOYEE ROLE
               ================================================== */

            $roleStmt = $pdo->prepare("
                SELECT id
                FROM roles
                WHERE UPPER(role_name) = 'EMPLOYEE'
                LIMIT 1
            ");

            $roleStmt->execute();

            $employeeRoleId = $roleStmt->fetchColumn();


            if (!$employeeRoleId) {

                throw new Exception(
                    "EMPLOYEE role was not found in the system."
                );

            }


            /* =================================================
               CHECK USERNAME
               ================================================== */

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

                throw new Exception(
                    "Username is already taken."
                );

            }


            /* =================================================
               CHECK EMAIL
               ================================================== */

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

                throw new Exception(
                    "Email is already registered."
                );

            }


            /* =================================================
               GENERATE EMPLOYEE NUMBER
               ================================================== */

            $lastEmployee = $pdo->query("
                SELECT employee_no
                FROM employees
                WHERE employee_no LIKE 'EMP-%'
                ORDER BY id DESC
                LIMIT 1
            ")->fetchColumn();


            if ($lastEmployee) {

                $lastNumber = (int) str_replace(
                    "EMP-",
                    "",
                    $lastEmployee
                );

                $nextNumber = $lastNumber + 1;

            } else {

                $nextNumber = 1;

            }


            $employeeNo = "EMP-" . str_pad(
                $nextNumber,
                4,
                "0",
                STR_PAD_LEFT
            );


            /* =================================================
               INSERT EMPLOYEE
               ================================================== */

            $employeeInsert = $pdo->prepare("
                INSERT INTO employees (
                    employee_no,
                    first_name,
                    middle_name,
                    last_name,
                    nickname,
                    contact_no,
                    address,
                    position,
                    department,
                    hire_date,
                    daily_rate,
                    hourly_rate,
                    status
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE'
                )
            ");


            $employeeInsert->execute([
                $employeeNo,
                $firstName,
                $middleName !== "" ? $middleName : null,
                $lastName,
                $nickname !== "" ? $nickname : null,
                $contactNo !== "" ? $contactNo : null,
                $address !== "" ? $address : null,
                $position,
                $department,
                $hireDate,
                (float) $dailyRate,
                $hourlyRate !== ""
                    ? (float) $hourlyRate
                    : null
            ]);


            $employeeId = $pdo->lastInsertId();


            /* =================================================
               HASH PASSWORD
               ================================================== */

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            if ($passwordHash === false) {

                throw new Exception(
                    "Unable to secure the password."
                );

            }


            /* =================================================
               CREATE USER ACCOUNT
               ================================================== */

            $userInsert = $pdo->prepare("
                INSERT INTO users (
                    employee_id,
                    role_id,
                    username,
                    email,
                    password,
                    status
                )
                VALUES (
                    ?, ?, ?, ?, ?, 'ACTIVE'
                )
            ");


            $userInsert->execute([
                $employeeId,
                $employeeRoleId,
                $username,
                $email,
                $passwordHash
            ]);


            $newUserId = $pdo->lastInsertId();


            /* =================================================
               EMPLOYEE AUDIT LOG
               ================================================== */

            logAudit(
                "EMPLOYEES",
                "CREATE",
                "EMPLOYEE",
                $employeeId,
                null,
                [
                    "employee_no" => $employeeNo,
                    "first_name" => $firstName,
                    "middle_name" => $middleName,
                    "last_name" => $lastName,
                    "nickname" => $nickname,
                    "contact_no" => $contactNo,
                    "address" => $address,
                    "position" => $position,
                    "department" => $department,
                    "hire_date" => $hireDate,
                    "daily_rate" => (float) $dailyRate,
                    "hourly_rate" =>
                        $hourlyRate !== ""
                            ? (float) $hourlyRate
                            : null,
                    "status" => "ACTIVE"
                ]
            );


            /* =================================================
               USER AUDIT LOG
               ================================================== */

            logAudit(
                "USER MANAGEMENT",
                "CREATE",
                "USER",
                $newUserId,
                null,
                [
                    "employee_id" => $employeeId,
                    "role_id" => $employeeRoleId,
                    "role" => "EMPLOYEE",
                    "username" => $username,
                    "email" => $email,
                    "status" => "ACTIVE"
                ]
            );


            /* =================================================
               COMMIT
               ================================================== */

            $pdo->commit();


            /* =================================================
               SUCCESS
               ================================================== */

            $_SESSION["employee_success"] =
                "Employee " .
                $employeeNo .
                " and login account created successfully.";

            header("Location: employees.php");
            exit;


        } catch (Throwable $e) {

            /* =================================================
               ROLLBACK
               ================================================== */

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();

        }

    }

}


/* =========================================================
   HANDLE EMPLOYEE ACTIONS
   ========================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["employee_action"])
) {

    if (!hasPermission("employees", "edit")) {
        http_response_code(403);
        die("Access Denied.");
    }


    $employeeId = (int) ($_POST["employee_id"] ?? 0);
    $action = $_POST["employee_action"] ?? "";


    if ($employeeId > 0) {

        $employeeStmt = $pdo->prepare("
            SELECT
                id,
                employee_no,
                first_name,
                middle_name,
                last_name,
                nickname,
                contact_no,
                address,
                position,
                department,
                hire_date,
                daily_rate,
                hourly_rate,
                status
            FROM employees
            WHERE id = ?
            LIMIT 1
        ");


        $employeeStmt->execute([
            $employeeId
        ]);


        $employee = $employeeStmt->fetch();


        if ($employee) {

            $fullName = trim(
                $employee["first_name"] .
                " " .
                $employee["middle_name"] .
                " " .
                $employee["last_name"]
            );


            /* =================================================
               SUSPEND
               ================================================== */

            if ($action === "suspend") {

                $update = $pdo->prepare("
                    UPDATE employees
                    SET status = 'SUSPENDED',
                        updated_at = NOW()
                    WHERE id = ?
                ");


                $update->execute([
                    $employeeId
                ]);


                logAudit(
                    "EMPLOYEES",
                    "SUSPEND",
                    "EMPLOYEE",
                    $employeeId,
                    [
                        "status" =>
                            $employee["status"],
                        "employee_no" =>
                            $employee["employee_no"]
                    ],
                    [
                        "status" =>
                            "SUSPENDED",
                        "employee_no" =>
                            $employee["employee_no"]
                    ]
                );


                $_SESSION["employee_success"] =
                    "Employee suspended successfully.";

                header("Location: employees.php");
                exit;

            }


            /* =================================================
               DELETE → ARCHIVE
               ================================================== */

            if ($action === "archive") {

                if ($employee["status"] === "ARCHIVED") {

                    $_SESSION["employee_success"] =
                        "Employee is already in the Archive.";

                    header("Location: employees.php");
                    exit;

                }


                $archiveId = archiveRecord(
                    "EMPLOYEES",
                    $employeeId,
                    $employee["employee_no"],
                    $fullName,
                    "Deleted by admin",
                    $_SESSION["user_id"] ?? null,
                    [
                        "employee_no" =>
                            $employee["employee_no"],
                        "first_name" =>
                            $employee["first_name"],
                        "middle_name" =>
                            $employee["middle_name"],
                        "last_name" =>
                            $employee["last_name"],
                        "nickname" =>
                            $employee["nickname"],
                        "contact_no" =>
                            $employee["contact_no"],
                        "address" =>
                            $employee["address"],
                        "position" =>
                            $employee["position"],
                        "department" =>
                            $employee["department"],
                        "hire_date" =>
                            $employee["hire_date"],
                        "daily_rate" =>
                            $employee["daily_rate"],
                        "hourly_rate" =>
                            $employee["hourly_rate"],
                        "status" =>
                            $employee["status"]
                    ],
                    "ARCHIVED"
                );


                $update = $pdo->prepare("
                    UPDATE employees
                    SET status = 'ARCHIVED',
                        updated_at = NOW()
                    WHERE id = ?
                ");


                $update->execute([
                    $employeeId
                ]);


                logAudit(
                    "EMPLOYEES",
                    "DELETE",
                    "EMPLOYEE",
                    $employeeId,
                    [
                        "status" =>
                            $employee["status"],
                        "employee_no" =>
                            $employee["employee_no"]
                    ],
                    [
                        "status" =>
                            "ARCHIVED",
                        "archive_id" =>
                            $archiveId,
                        "employee_no" =>
                            $employee["employee_no"]
                    ]
                );


                $_SESSION["employee_success"] =
                    "Employee deleted and moved to Archive.";

                header("Location: employees.php");
                exit;

            }


            /* =================================================
               DISABLE LOGIN ACCESS
               ================================================== */

            if ($action === "disable_login_access") {

                $userStmt = $pdo->prepare("
                    SELECT
                        id,
                        employee_id,
                        username,
                        status
                    FROM users
                    WHERE employee_id = ?
                    LIMIT 1
                ");


                $userStmt->execute([
                    $employeeId
                ]);


                $userRecord = $userStmt->fetch();


                if (!$userRecord) {

                    $_SESSION["employee_success"] =
                        "No login account found for this employee.";

                    header("Location: employees.php");
                    exit;

                }


                $oldStatus =
                    $userRecord["status"];


                $update = $pdo->prepare("
                    UPDATE users
                    SET status = 'INACTIVE',
                        updated_at = NOW()
                    WHERE id = ?
                ");


                $update->execute([
                    $userRecord["id"]
                ]);


                logAudit(
                    "EMPLOYEES",
                    "DISABLE_ACCOUNT",
                    "USER",
                    $userRecord["id"],
                    [
                        "status" =>
                            $oldStatus,
                        "employee_id" =>
                            $employeeId,
                        "username" =>
                            $userRecord["username"]
                    ],
                    [
                        "status" =>
                            "INACTIVE",
                        "employee_id" =>
                            $employeeId,
                        "username" =>
                            $userRecord["username"]
                    ]
                );


                $_SESSION["employee_success"] =
                    "Employee login access disabled.";

                header("Location: employees.php");
                exit;

            }

        }

    }


    $_SESSION["employee_success"] =
        "Invalid employee action.";

    header("Location: employees.php");
    exit;

}


/* =========================================================
   GET EMPLOYEES
   ========================================================== */

$stmt = $pdo->query("
    SELECT
        id,
        employee_no,
        first_name,
        middle_name,
        last_name,
        nickname,
        contact_no,
        position,
        department,
        hire_date,
        daily_rate,
        hourly_rate,
        status
    FROM employees
    WHERE status != 'ARCHIVED'
    ORDER BY id DESC
");


$employees = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>GNR IMS - Employees</title>

<link
    rel="stylesheet"
    href="css/sidebar.css"
>

<link
    rel="stylesheet"
    href="css/dashboard.css"
>

<link
    rel="stylesheet"
    href="css/employees.css"
>
`

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
                Employees
            </h1>

            <p>
                Manage GNR employee records.
            </p>

        </div>


        <div class="user-info">

            <div class="user-avatar">

                <?= strtoupper(
                    substr(
                        $user["username"],
                        0,
                        1
                    )
                ) ?>

            </div>


            <div>

                <strong>
                    <?= htmlspecialchars(
                        $user["username"]
                    ) ?>
                </strong>

                <small>
                    <?= htmlspecialchars(
                        $user["role_name"]
                    ) ?>
                </small>

            </div>

        </div>

    </header>


    <!-- =================================================
         CONTENT
         ================================================== -->

    <section class="dashboard-content">


        <?php if ($error): ?>

            <div class="employee-message error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <?php if ($success): ?>

            <div class="employee-message success">

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ADD EMPLOYEE
             ================================================== -->

        <?php if (hasPermission("employees", "create")): ?>


            <div class="employee-form-card">


                <div class="employee-form-header">

                    <div>

                        <span>
                            EMPLOYEE MANAGEMENT
                        </span>

                        <h2>
                            Add Employee
                        </h2>

                    </div>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="form_action"
                        value="add_employee"
                    >


                    <div class="employee-form-grid">


                        <!-- FIRST NAME -->

                        <div class="form-group">

                            <label for="first_name">
                                First Name
                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                autocomplete="given-name"
                                required
                            >

                        </div>


                        <!-- MIDDLE NAME -->

                        <div class="form-group">

                            <label for="middle_name">
                                Middle Name
                            </label>

                            <input
                                type="text"
                                id="middle_name"
                                name="middle_name"
                                autocomplete="additional-name"
                            >

                        </div>


                        <!-- LAST NAME -->

                        <div class="form-group">

                            <label for="last_name">
                                Last Name
                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                autocomplete="family-name"
                                required
                            >

                        </div>


                        <!-- NICKNAME -->

                        <div class="form-group">

                            <label for="nickname">
                                Nickname
                            </label>

                            <input
                                type="text"
                                id="nickname"
                                name="nickname"
                            >

                        </div>


                        <!-- CONTACT -->

                        <div class="form-group">

                            <label for="contact_no">
                                Contact No.
                            </label>

                            <input
                                type="text"
                                id="contact_no"
                                name="contact_no"
                                autocomplete="tel"
                            >

                        </div>


                        <!-- ADDRESS -->

                        <div class="form-group full-width">

                            <label for="address">
                                Address
                            </label>

                            <textarea
                                id="address"
                                name="address"
                                rows="2"
                                autocomplete="street-address"
                            ></textarea>

                        </div>


                        <!-- POSITION -->

                        <div class="form-group">

                            <label for="position">
                                Position
                            </label>

                            <input
                                type="text"
                                id="position"
                                name="position"
                                required
                            >

                        </div>


                        <!-- DEPARTMENT -->

                        <div class="form-group">

                            <label for="department">
                                Department
                            </label>

                            <input
                                type="text"
                                id="department"
                                name="department"
                                required
                            >

                        </div>


                        <!-- =================================================
                             HIRE DATE
                             ================================================== -->

                        <div class="form-group">

                            <label for="hire_date_display">
                                Hire Date
                            </label>


                            <div class="date-picker-wrapper">


                                <input
                                    type="text"
                                    id="hire_date_display"
                                    class="date-picker-input"
                                    placeholder="Select hire date"
                                    readonly
                                    required
                                >


                                <input
                                    type="hidden"
                                    id="hire_date"
                                    name="hire_date"
                                    required
                                >


                                <button
                                    type="button"
                                    class="date-picker-button"
                                    id="open-calendar"
                                    aria-label="Open calendar"
                                >
                                    📅
                                </button>


                                <div
                                    class="calendar-popup"
                                    id="calendar-popup"
                                >


                                    <div
                                        class="calendar-view active"
                                        id="calendar-view"
                                    >


                                        <div class="calendar-header">


                                            <button
                                                type="button"
                                                class="calendar-nav-button"
                                                id="calendar-prev"
                                            >
                                                ‹
                                            </button>


                                            <button
                                                type="button"
                                                class="calendar-month-year-button"
                                                id="calendar-month-year"
                                            >
                                                September 2026
                                            </button>


                                            <button
                                                type="button"
                                                class="calendar-nav-button"
                                                id="calendar-next"
                                            >
                                                ›
                                            </button>


                                        </div>


                                        <div class="calendar-weekdays">

                                            <span>Sun</span>
                                            <span>Mon</span>
                                            <span>Tue</span>
                                            <span>Wed</span>
                                            <span>Thu</span>
                                            <span>Fri</span>
                                            <span>Sat</span>

                                        </div>


                                        <div
                                            class="calendar-days"
                                            id="calendar-days"
                                        ></div>


                                        <button
                                            type="button"
                                            class="calendar-today"
                                            id="calendar-today"
                                        >
                                            Today
                                        </button>


                                    </div>


                                    <div
                                        class="calendar-selector"
                                        id="calendar-selector"
                                    >


                                        <div class="calendar-selector-title">

                                            Select Month & Year

                                        </div>


                                        <div class="calendar-selector-header">


                                            <button
                                                type="button"
                                                id="selector-prev-year"
                                            >
                                                ‹
                                            </button>


                                            <strong
                                                id="selector-year"
                                            >
                                                2026
                                            </strong>


                                            <button
                                                type="button"
                                                id="selector-next-year"
                                            >
                                                ›
                                            </button>


                                        </div>


                                        <div
                                            class="calendar-month-grid"
                                            id="calendar-month-grid"
                                        ></div>


                                        <button
                                            type="button"
                                            class="calendar-selector-back"
                                            id="calendar-selector-back"
                                        >
                                            Back to Calendar
                                        </button>


                                    </div>


                                </div>

                            </div>

                        </div>


                        <!-- DAILY RATE -->

                        <div class="form-group">

                            <label for="daily_rate">
                                Daily Rate
                            </label>

                            <input
                                type="number"
                                id="daily_rate"
                                name="daily_rate"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                required
                            >

                        </div>


                        <!-- HOURLY RATE -->

                        <div class="form-group">

                            <label for="hourly_rate">
                                Hourly Rate
                            </label>

                            <input
                                type="number"
                                id="hourly_rate"
                                name="hourly_rate"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                            >

                        </div>


                    </div>


                    <!-- =================================================
                         LOGIN ACCOUNT
                         ================================================== -->

                    <div class="employee-account-section">


                        <div class="employee-account-header">

                            <span>
                                SYSTEM ACCESS
                            </span>

                            <h3>
                                Employee Login Account
                            </h3>

                            <p>
                                This employee will automatically receive
                                an EMPLOYEE system account.
                            </p>

                        </div>


                        <div class="employee-form-grid">


                            <!-- USERNAME -->

                            <div class="form-group">

                                <label for="username">
                                    Username
                                </label>

                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    maxlength="100"
                                    autocomplete="username"
                                    required
                                >

                            </div>


                            <!-- EMAIL -->

                            <div class="form-group">

                                <label for="email">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    maxlength="150"
                                    autocomplete="email"
                                    required
                                >

                            </div>


                            <!-- PASSWORD -->

                            <div class="form-group">

                                <label for="password">
                                    Password
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <small>
                                    Minimum 8 characters.
                                </small>

                            </div>


                            <!-- CONFIRM PASSWORD -->

                            <div class="form-group">

                                <label for="confirm_password">
                                    Confirm Password
                                </label>

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                            </div>


                        </div>


                    </div>


                    <button
                        type="submit"
                        class="employee-save-btn"
                    >
                        ADD EMPLOYEE
                    </button>


                </form>


            </div>


        <?php endif; ?>


        <!-- =================================================
             EMPLOYEE LIST
             ================================================== -->

        <div class="employee-list-card">


            <div class="employee-list-header">

                <div>

                    <span>
                        RECORDS
                    </span>

                    <h2>
                        Employee List
                    </h2>

                </div>


                <strong>
                    <?= number_format(
                        count($employees)
                    ) ?>
                </strong>

            </div>


            <?php if (count($employees) > 0): ?>


                <div class="employee-table-wrapper">


                    <table class="employee-table">


                        <thead>

                            <tr>

                                <th>
                                    Employee No.
                                </th>

                                <th>
                                    Name
                                </th>

                                <th>
                                    Position
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Daily Rate
                                </th>

                                <th>
                                    Hourly Rate
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($employees as $employee): ?>


                                <tr>


                                    <td>
                                        <?= htmlspecialchars(
                                            $employee["employee_no"]
                                        ) ?>
                                    </td>


                                    <td>


                                        <?php

                                        $name = trim(
                                            $employee["first_name"] .
                                            " " .
                                            $employee["middle_name"] .
                                            " " .
                                            $employee["last_name"]
                                        );

                                        ?>


                                        <strong>
                                            <?= htmlspecialchars($name) ?>
                                        </strong>


                                        <?php if (
                                            !empty(
                                                $employee["nickname"]
                                            )
                                        ): ?>

                                            <small>
                                                <?= htmlspecialchars(
                                                    $employee["nickname"]
                                                ) ?>
                                            </small>

                                        <?php endif; ?>


                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $employee["position"]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars(
                                            $employee["department"]
                                        ) ?>
                                    </td>


                                    <td>
                                        ₱<?= number_format(
                                            (float)
                                            $employee["daily_rate"],
                                            2
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= formatCurrency(
                                            $employee["hourly_rate"]
                                        ) ?>
                                    </td>


                                    <td>

                                        <span class="employee-status employee-status-<?= strtolower($employee["status"] ?? "active") ?>">

                                            <?= htmlspecialchars(
                                                $employee["status"]
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="employee-actions">


                                            <a
                                                href="#"
                                                class="employee-action-link"
                                                aria-label="View employee"
                                                title="View"
                                            >
                                                VIEW
                                            </a>


                                            <a
                                                href="#"
                                                class="employee-action-link muted"
                                                aria-label="Edit employee"
                                                title="Edit"
                                            >
                                                EDIT
                                            </a>


                                            <form
                                                method="POST"
                                                class="inline-action-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="employee_id"
                                                    value="<?= (int) $employee["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="employee_action"
                                                    value="suspend"
                                                >

                                                <button
                                                    type="submit"
                                                    class="employee-action-btn suspend-btn"
                                                >
                                                    SUSPEND
                                                </button>

                                            </form>


                                            <!-- =================================================
                                                 DELETE → ARCHIVE
                                                 ================================================== -->

                                            <form
                                                method="POST"
                                                class="inline-action-form"
                                                onsubmit="return confirm('Delete this employee? The employee will be moved to Archive and can still be restored later.');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="employee_id"
                                                    value="<?= (int) $employee["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="employee_action"
                                                    value="archive"
                                                >

                                                <button
                                                    type="submit"
                                                    class="employee-action-btn archive-btn"
                                                >
                                                    DELETE
                                                </button>

                                            </form>


                                            <!-- =================================================
                                                 DISABLE LOGIN ACCESS
                                                 ================================================== -->

                                            <form
                                                method="POST"
                                                class="inline-action-form"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="employee_id"
                                                    value="<?= (int) $employee["id"] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="employee_action"
                                                    value="disable_login_access"
                                                >

                                                <button
                                                    type="submit"
                                                    class="employee-action-btn account-btn"
                                                >
                                                    USER ACCOUNT
                                                </button>

                                            </form>


                                        </div>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="employee-empty">

                    <div>
                        👥
                    </div>

                    <h3>
                        No Employees Yet
                    </h3>

                    <p>
                        There are currently no employee records
                        in the system.
                    </p>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


</div>

<script src="js/admin.js"></script>

</body>

</html>
