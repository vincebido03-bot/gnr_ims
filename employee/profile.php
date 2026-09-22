<?php

require_once "../admin/includes/auth.php";

requireLogin();

if (!hasRole("EMPLOYEE")) {
    header("Location: ../admin/dashboard.php");
    exit;
}

$employeeId = $_SESSION["employee_id"] ?? null;

if (!$employeeId) {
    die("Employee account is not properly linked.");
}


/* =========================================================
   EMPLOYEE DATA
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        e.*,
        u.username,
        u.email,
        u.status AS account_status
    FROM employees e
    LEFT JOIN users u
        ON u.employee_id = e.id
    WHERE e.id = ?
    LIMIT 1
");

$stmt->execute([$employeeId]);

$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found.");
}


/* =========================================================
   DISPLAY VALUES
   ========================================================= */

$fullName = trim(
    $employee["first_name"] . " " .
    ($employee["middle_name"] ? $employee["middle_name"] . " " : "") .
    $employee["last_name"]
);

$initials = strtoupper(
    substr($employee["first_name"], 0, 1) .
    substr($employee["last_name"], 0, 1)
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile | GNR Employee Portal</title>

    <link
        rel="stylesheet"
        href="css/employee.css"
    >

    <style>

        .profile-page {
            min-height: 100vh;

            padding: 40px;

            background: #0b0b0b;

            color: #f4f4f4;
        }


        /* =====================================================
           HEADER
           ===================================================== */

        .profile-header {
            margin-bottom: 30px;
        }


        .profile-eyebrow {
            margin-bottom: 8px;

            color: #c9a227;

            font-size: 10px;
            font-weight: 800;

            letter-spacing: 2px;
        }


        .profile-header h1 {
            margin: 0;

            font-size: 32px;
            font-weight: 800;
        }


        .profile-header p {
            margin: 8px 0 0;

            color: #8f8f8f;

            font-size: 14px;
        }


        /* =====================================================
           PROFILE GRID
           ===================================================== */

        .profile-grid {
            display: grid;

            grid-template-columns: 300px 1fr;

            gap: 22px;

        }


        .profile-card {
            padding: 28px;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.08);

            border-radius: 16px;
        }


        /* =====================================================
           PROFILE IDENTITY
           ===================================================== */

        .profile-identity {
            text-align: center;
        }


        .profile-avatar {
            width: 105px;
            height: 105px;

            margin: 5px auto 20px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: rgba(201,162,39,0.10);

            border: 1px solid rgba(201,162,39,0.45);

            border-radius: 50%;

            color: #c9a227;

            font-size: 30px;
            font-weight: 800;

            letter-spacing: 1px;
        }


        .profile-identity h2 {
            margin: 0;

            font-size: 20px;
            font-weight: 800;
        }


        .profile-position {
            margin-top: 7px;

            color: #c9a227;

            font-size: 10px;
            font-weight: 700;

            letter-spacing: 1px;
        }


        .profile-employee-number {
            margin-top: 14px;

            color: #777;

            font-size: 10px;
        }


        .profile-status {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 18px;

            padding: 7px 10px;

            background: rgba(127,201,127,0.08);

            border: 1px solid rgba(127,201,127,0.18);

            border-radius: 20px;

            color: #7fc97f;

            font-size: 9px;
            font-weight: 800;

            letter-spacing: 1px;
        }


        .profile-status-dot {
            width: 6px;
            height: 6px;

            background: #7fc97f;

            border-radius: 50%;
        }


        /* =====================================================
           INFORMATION
           ===================================================== */

        .profile-section-title {
            margin-bottom: 22px;

            color: #c9a227;

            font-size: 10px;
            font-weight: 800;

            letter-spacing: 1.8px;
        }


        .profile-info-grid {
            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 18px;
        }


        .profile-info-item {
            padding-bottom: 15px;

            border-bottom: 1px solid rgba(255,255,255,0.06);
        }


        .profile-info-label {
            display: block;

            margin-bottom: 7px;

            color: #666;

            font-size: 9px;
            font-weight: 700;

            letter-spacing: 1px;
        }


        .profile-info-value {
            color: #ddd;

            font-size: 13px;
            font-weight: 600;

            word-break: break-word;
        }


        .profile-info-item.full {
            grid-column: 1 / -1;
        }


        /* =====================================================
           ACCOUNT SECTION
           ===================================================== */

        .profile-account {
            margin-top: 25px;

            padding-top: 25px;

            border-top: 1px solid rgba(255,255,255,0.07);
        }


        .profile-account-grid {
            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 18px;
        }


        /* =====================================================
           NOTE
           ===================================================== */

        .profile-note {
            margin-top: 25px;

            padding: 15px 17px;

            background: rgba(201,162,39,0.05);

            border: 1px solid rgba(201,162,39,0.12);

            border-radius: 10px;

            color: #888;

            font-size: 11px;

            line-height: 1.6;
        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 900px) {

            .profile-page {
                padding: 25px;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-identity {
                text-align: left;

                display: grid;

                grid-template-columns: auto 1fr;

                column-gap: 20px;

                align-items: center;
            }

            .profile-avatar {
                grid-row: span 4;

                margin: 0;
            }

        }


        @media (max-width: 600px) {

            .profile-page {
                padding: 20px;
            }

            .profile-header h1 {
                font-size: 27px;
            }

            .profile-info-grid,
            .profile-account-grid {
                grid-template-columns: 1fr;
            }

            .profile-info-item.full {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>

<?php include "includes/sidebar.php"; ?>


<main class="employee-main">

    <div class="profile-page">


        <!-- HEADER -->

        <div class="profile-header">

            <div class="profile-eyebrow">
                EMPLOYEE PORTAL / ACCOUNT
            </div>

            <h1>
                My Profile
            </h1>

            <p>
                View your employee and account information.
            </p>

        </div>


        <div class="profile-grid">


            <!-- IDENTITY CARD -->

            <section class="profile-card profile-identity">

                <div class="profile-avatar">
                    <?= e($initials) ?>
                </div>


                <div>

                    <h2>
                        <?= e($fullName) ?>
                    </h2>

                    <div class="profile-position">
                        <?= e($employee["position"] ?: "EMPLOYEE") ?>
                    </div>

                    <div class="profile-employee-number">
                        <?= e($employee["employee_no"]) ?>
                    </div>


                    <div class="profile-status">

                        <span class="profile-status-dot"></span>

                        <?= e($employee["status"]) ?>

                    </div>

                </div>

            </section>


            <!-- INFORMATION CARD -->

            <section class="profile-card">

                <div class="profile-section-title">
                    PERSONAL INFORMATION
                </div>


                <div class="profile-info-grid">


                    <div class="profile-info-item">

                        <span class="profile-info-label">
                            FIRST NAME
                        </span>

                        <div class="profile-info-value">
                            <?= e($employee["first_name"]) ?>
                        </div>

                    </div>


                    <div class="profile-info-item">

                        <span class="profile-info-label">
                            MIDDLE NAME
                        </span>

                        <div class="profile-info-value">
                            <?= e($employee["middle_name"] ?: "—") ?>
                        </div>

                    </div>


                    <div class="profile-info-item">

                        <span class="profile-info-label">
                            LAST NAME
                        </span>

                        <div class="profile-info-value">
                            <?= e($employee["last_name"]) ?>
                        </div>

                    </div>


                    <div class="profile-info-item">

                        <span class="profile-info-label">
                            NICKNAME
                        </span>

                        <div class="profile-info-value">
                            <?= e($employee["nickname"] ?: "—") ?>
                        </div>

                    </div>


                    <div class="profile-info-item">

                        <span class="profile-info-label">
                            CONTACT NUMBER
                        </span>

                        <div class="profile-info-value">
                            <?= e($employee["contact_no"] ?: "—") ?>
                        </div>

                    </div>


                    <div class="profile-info-item">

                        <span class="profile-info-label">
                            HIRE DATE
                        </span>

                        <div class="profile-info-value">
                            <?= $employee["hire_date"]
                                ? e(date("F d, Y", strtotime($employee["hire_date"])))
                                : "—"
                            ?>
                        </div>

                    </div>


                    <div class="profile-info-item full">

                        <span class="profile-info-label">
                            ADDRESS
                        </span>

                        <div class="profile-info-value">
                            <?= e($employee["address"] ?: "—") ?>
                        </div>

                    </div>

                </div>


                <!-- WORK INFORMATION -->

                <div class="profile-account">

                    <div class="profile-section-title">
                        WORK INFORMATION
                    </div>


                    <div class="profile-info-grid">


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                DEPARTMENT
                            </span>

                            <div class="profile-info-value">
                                <?= e($employee["department"] ?: "—") ?>
                            </div>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                POSITION
                            </span>

                            <div class="profile-info-value">
                                <?= e($employee["position"] ?: "—") ?>
                            </div>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                DAILY RATE
                            </span>

                            <div class="profile-info-value">

                                <?= $employee["daily_rate"] !== null
                                    ? "₱" . number_format($employee["daily_rate"], 2)
                                    : "—"
                                ?>

                            </div>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                HOURLY RATE
                            </span>

                            <div class="profile-info-value">

                                <?= $employee["hourly_rate"] !== null
                                    ? "₱" . number_format($employee["hourly_rate"], 2)
                                    : "—"
                                ?>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ACCOUNT -->

                <div class="profile-account">

                    <div class="profile-section-title">
                        LOGIN ACCOUNT
                    </div>


                    <div class="profile-account-grid">


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                USERNAME
                            </span>

                            <div class="profile-info-value">
                                <?= e($employee["username"] ?: "—") ?>
                            </div>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                EMAIL
                            </span>

                            <div class="profile-info-value">
                                <?= e($employee["email"] ?: "—") ?>
                            </div>

                        </div>


                        <div class="profile-info-item">

                            <span class="profile-info-label">
                                ACCOUNT STATUS
                            </span>

                            <div class="profile-info-value">
                                <?= e($employee["account_status"] ?: "—") ?>
                            </div>

                        </div>

                    </div>


                    <div class="profile-note">
                        Your employee information is managed by the GNR management system.
                        Contact HR or an authorized administrator if any information needs to be updated.
                    </div>

                </div>

            </section>

        </div>

    </div>

</main>


</body>

</html>