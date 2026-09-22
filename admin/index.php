<?php

session_start();

require_once "../config/database.php";
require_once "includes/functions.php";


/* =========================================================
   ROLE-BASED REDIRECT
   ========================================================== */

function redirectByRole($roleName)
{
    $role = strtoupper(trim($roleName));

    /*
     * Employee accounts go to the Employee Portal.
     *
     * Management accounts remain inside the Admin Portal.
     */

    if ($role === "EMPLOYEE") {

        header("Location: ../employee/dashboard.php");
        exit;

    }

    header("Location: dashboard.php");
    exit;
}


/* =========================================================
   REDIRECT IF ALREADY LOGGED IN
   ========================================================== */

if (isset($_SESSION["user_id"])) {

    redirectByRole(
        $_SESSION["role_name"] ?? ""
    );

}


$error = "";


/* =========================================================
   LOGIN PROCESS
   ========================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username =
        trim($_POST["username"] ?? "");

    $password =
        $_POST["password"] ?? "";


    /* =====================================================
       VALIDATE INPUT
       ====================================================== */

    if (
        $username === "" ||
        $password === ""
    ) {

        $error =
            "Please enter your username and password.";

    } else {


        /* =================================================
           FIND USER
           ================================================== */

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.employee_id,
                u.role_id,
                u.username,
                u.email,
                u.password,
                u.status,
                r.role_name
            FROM users u

            INNER JOIN roles r
                ON u.role_id = r.id

            WHERE u.username = ?

            LIMIT 1
        ");

        $stmt->execute([
            $username
        ]);

        $user =
            $stmt->fetch();


        /* =================================================
           VERIFY LOGIN
           ================================================== */

        if (
            !$user ||
            !password_verify(
                $password,
                $user["password"]
            )
        ) {

            $error =
                "Invalid username or password.";

        } else {


            /* =============================================
               CHECK ACCOUNT STATUS
               ============================================== */

            if (
                strtoupper(
                    $user["status"]
                ) !== "ACTIVE"
            ) {

                $error =
                    "Your account is not active.";

            } else {


                /* =========================================
                   EMPLOYEE ACCOUNT VALIDATION
                   ========================================== */

                if (
                    strtoupper(
                        $user["role_name"]
                    ) === "EMPLOYEE" &&
                    empty($user["employee_id"])
                ) {

                    $error =
                        "This employee account is not linked to an employee profile.";

                } else {


                    /* =====================================
                       REGENERATE SESSION
                       ====================================== */

                    session_regenerate_id(true);


                    /* =====================================
                       SESSION DATA
                       ====================================== */

                    $_SESSION["user_id"] =
                        $user["id"];

                    $_SESSION["employee_id"] =
                        $user["employee_id"];

                    $_SESSION["role_id"] =
                        $user["role_id"];

                    $_SESSION["username"] =
                        $user["username"];

                    $_SESSION["email"] =
                        $user["email"];

                    $_SESSION["role_name"] =
                        $user["role_name"];


                    $_SESSION["user"] = [

                        "id" =>
                            $user["id"],

                        "employee_id" =>
                            $user["employee_id"],

                        "username" =>
                            $user["username"],

                        "email" =>
                            $user["email"],

                        "role_name" =>
                            $user["role_name"]

                    ];


                    /* =====================================
                       UPDATE LAST LOGIN
                       ====================================== */

                    $update = $pdo->prepare("
                        UPDATE users

                        SET last_login = NOW()

                        WHERE id = ?

                        LIMIT 1
                    ");

                    $update->execute([
                        $user["id"]
                    ]);


                    /* =====================================
                       AUDIT LOG
                       ====================================== */

                    logAudit(
                        "AUTHENTICATION",
                        "LOGIN",
                        "USER",
                        $user["id"],
                        null,
                        [
                            "username" =>
                                $user["username"],

                            "role" =>
                                $user["role_name"]
                        ]
                    );


                    /* =====================================
                       ROLE-BASED ROUTING
                       ====================================== */

                    redirectByRole(
                        $user["role_name"]
                    );

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

<title>
    GNR IMS - Login
</title>

<link
    rel="stylesheet"
    href="css/login.css"
>


</head>

<body>

<div class="login-container">


<div class="login-card">


    <!-- =================================================
         BRAND
         ================================================== -->

    <div class="login-brand">

        <h1>
            GREASE <span>N'</span> RESIN
        </h1>

        <p>
            INTEGRATED MANAGEMENT SYSTEM
        </p>

    </div>


    <!-- =================================================
         ERROR
         ================================================== -->

    <?php if ($error): ?>

        <div class="login-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         LOGIN FORM
         ================================================== -->

    <form method="POST">


        <div class="form-group">

            <label for="username">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                autocomplete="username"
                placeholder="Enter username"
                value="<?= htmlspecialchars(
                    $_POST["username"] ?? ""
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                placeholder="Enter password"
                required
            >

        </div>


        <button
            type="submit"
            class="login-btn"
        >
            LOGIN
        </button>

    </form>


    <!-- =================================================
         FOOTER
         ================================================== -->

    <div class="login-footer">

        GNR IMS &copy;
        <?= date("Y") ?>

    </div>

</div>


</div>

</body>

</html>
