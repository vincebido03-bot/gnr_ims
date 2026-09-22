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
   EMPLOYEE INFORMATION
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        employee_no,
        first_name,
        middle_name,
        last_name,
        position,
        department,
        daily_rate,
        hourly_rate
    FROM employees
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$employeeId]);

$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found.");
}


$fullName = trim(
    $employee["first_name"] . " " .
    ($employee["middle_name"] ? $employee["middle_name"] . " " : "") .
    $employee["last_name"]
);


/* =========================================================
   PAYROLL RECORDS
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.payroll_no,
        p.period_start,
        p.period_end,

        /* Regular hours from attendance */
        COALESCE(
            (
                SELECT SUM(
                    GREATEST(
                        TIMESTAMPDIFF(
                            SECOND,
                            a.time_in,
                            a.time_out
                        ) / 3600 - a.overtime_hours,
                        0
                    )
                )
                FROM attendance a
                WHERE a.employee_id = pi.employee_id
                  AND a.date BETWEEN p.period_start AND p.period_end
                  AND a.time_in IS NOT NULL
                  AND a.time_out IS NOT NULL
            ),
            0
        ) AS regular_hours,

        /* Basic / regular pay */
        COALESCE(pi.basic_pay, 0) AS regular_pay,

        /* Approved overtime hours */
        COALESCE(
            (
                SELECT SUM(o.ot_hours)
                FROM overtime o
                WHERE o.employee_id = pi.employee_id
                  AND o.ot_date BETWEEN p.period_start AND p.period_end
                  AND o.status = 'APPROVED'
            ),
            0
        ) AS overtime_hours,

        /* Payroll's recorded overtime pay */
        COALESCE(pi.overtime_pay, 0) AS overtime_pay,

        COALESCE(pi.gross_pay, 0) AS gross_pay,

        COALESCE(pi.deductions, 0) AS deductions,

        COALESCE(pi.net_pay, 0) AS net_pay,

        p.status,
        p.created_at

    FROM payroll p

    INNER JOIN payroll_items pi
        ON pi.payroll_id = p.id

    WHERE pi.employee_id = ?

    ORDER BY p.period_end DESC, p.id DESC
");

$stmt->execute([$employeeId]);

$payrollRecords = $stmt->fetchAll();


/* =========================================================
   OVERTIME RECORDS
   ========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        ot_date,
        start_time,
        end_time,
        ot_hours,
        ot_rate,
        ot_pay,
        reason,
        status,
        created_at
    FROM overtime
    WHERE employee_id = ?
    ORDER BY ot_date DESC, id DESC
");

$stmt->execute([$employeeId]);

$overtimeRecords = $stmt->fetchAll();


/* =========================================================
   TOTALS
   ========================================================= */

$totalPayroll = count($payrollRecords);

$totalOTHours = 0;
$totalOTPay = 0;

foreach ($overtimeRecords as $ot) {

    if ($ot["status"] === "APPROVED") {

        $totalOTHours += (float) $ot["ot_hours"];
        $totalOTPay += (float) $ot["ot_pay"];

    }

}


/* =========================================================
   HELPERS
   ========================================================= */

function payrollMoney($amount)
{
    return "₱" . number_format((float) $amount, 2);
}


function payrollDate($date)
{
    if (!$date) {
        return "—";
    }

    return date("M d, Y", strtotime($date));
}


function payrollTime($time)
{
    if (!$time) {
        return "—";
    }

    return date("h:i A", strtotime($time));
}


function payrollStatusClass($status)
{
    return strtolower(
        str_replace(" ", "-", $status)
    );
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

    <title>My Payslips | GNR Employee Portal</title>


    <link
        rel="stylesheet"
        href="css/employee.css"
    >


    <style>

        /* =====================================================
           PAYROLL PAGE
           ===================================================== */

        .payroll-page {

            min-height: 100vh;

            padding: 40px;

            background: #0b0b0b;

            color: #f4f4f4;

        }


        /* =====================================================
           HEADER
           ===================================================== */

        .payroll-header {

            margin-bottom: 30px;

        }


        .payroll-eyebrow {

            margin-bottom: 8px;

            color: #c9a227;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 2px;

        }


        .payroll-header h1 {

            margin: 0;

            font-size: 32px;

            font-weight: 800;

        }


        .payroll-header p {

            margin: 8px 0 0;

            color: #888;

            font-size: 14px;

        }


        /* =====================================================
           SUMMARY CARDS
           ===================================================== */

        .payroll-summary {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 28px;

        }


        .payroll-summary-card {

            padding: 22px;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 15px;

        }


        .payroll-summary-label {

            margin-bottom: 10px;

            color: #666;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 1.5px;

        }


        .payroll-summary-value {

            color: #f1f1f1;

            font-size: 25px;

            font-weight: 800;

        }


        .payroll-summary-value.gold {

            color: #c9a227;

        }


        /* =====================================================
           EMPLOYEE BAR
           ===================================================== */

        .payroll-employee {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;

            padding: 18px 22px;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 15px;

        }


        .payroll-employee-name {

            font-size: 15px;

            font-weight: 800;

        }


        .payroll-employee-meta {

            margin-top: 5px;

            color: #777;

            font-size: 10px;

        }


        .payroll-rate {

            text-align: right;

        }


        .payroll-rate-label {

            display: block;

            margin-bottom: 5px;

            color: #666;

            font-size: 8px;

            font-weight: 800;

            letter-spacing: 1px;

        }


        .payroll-rate-value {

            color: #c9a227;

            font-size: 13px;

            font-weight: 800;

        }


        /* =====================================================
           SECTION
           ===================================================== */

        .payroll-section {

            margin-bottom: 30px;

        }


        .payroll-section-title {

            margin-bottom: 15px;

            color: #c9a227;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 1.8px;

        }


        /* =====================================================
           PAYROLL TABLE
           ===================================================== */

        .payroll-table-wrap {

            overflow-x: auto;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 15px;

        }


        .payroll-table {

            width: 100%;

            min-width: 850px;

            border-collapse: collapse;

        }


        .payroll-table th {

            padding: 16px;

            background: #111;

            color: #666;

            font-size: 8px;

            font-weight: 800;

            letter-spacing: 1.2px;

            text-align: left;

            white-space: nowrap;

        }


        .payroll-table td {

            padding: 16px;

            border-top: 1px solid rgba(255,255,255,0.05);

            color: #ccc;

            font-size: 11px;

            white-space: nowrap;

        }


        .payroll-table tr:hover td {

            background: rgba(255,255,255,0.015);

        }


        .payroll-net {

            color: #c9a227 !important;

            font-weight: 800;

        }


        /* =====================================================
           PAYROLL STATUS
           ===================================================== */

        .payroll-status {

            display: inline-flex;

            padding: 6px 9px;

            border-radius: 20px;

            font-size: 8px;

            font-weight: 800;

            letter-spacing: .7px;

        }


        .payroll-status.paid {

            background: rgba(90,180,110,.10);

            color: #76c77c;

            border: 1px solid rgba(90,180,110,.18);

        }


        .payroll-status.finalized {

            background: rgba(201,162,39,.10);

            color: #c9a227;

            border: 1px solid rgba(201,162,39,.18);

        }


        .payroll-status.processing {

            background: rgba(80,140,220,.10);

            color: #77a9e8;

            border: 1px solid rgba(80,140,220,.18);

        }


        .payroll-status.draft {

            background: rgba(255,255,255,.05);

            color: #999;

            border: 1px solid rgba(255,255,255,.08);

        }


        /* =====================================================
           EMPTY STATE
           ===================================================== */

        .payroll-empty {

            padding: 45px 20px;

            text-align: center;

            color: #666;

        }


        .payroll-empty strong {

            display: block;

            margin-bottom: 7px;

            color: #999;

            font-size: 13px;

        }


        .payroll-empty span {

            font-size: 10px;

        }


        /* =====================================================
           OT HEADER
           ===================================================== */

        .ot-section-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 15px;

        }


        .ot-section-header .payroll-section-title {

            margin-bottom: 0;

        }


        .add-ot-btn {

            padding: 10px 15px;

            background: #c9a227;

            border: 1px solid #c9a227;

            border-radius: 8px;

            color: #0b0b0b;

            font-size: 9px;

            font-weight: 900;

            letter-spacing: 1px;

            cursor: pointer;

            transition: .2s ease;

        }


        .add-ot-btn:hover {

            transform: translateY(-2px);

            background: #dfbd42;

        }


        /* =====================================================
           OT CARDS
           ===================================================== */

        .ot-list {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 15px;

        }


        .ot-card {

            padding: 20px;

            background: #151515;

            border: 1px solid rgba(255,255,255,0.07);

            border-radius: 15px;

        }


        .ot-card-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 16px;

        }


        .ot-date {

            color: #eee;

            font-size: 13px;

            font-weight: 800;

        }


        .ot-status {

            font-size: 8px;

            font-weight: 800;

            letter-spacing: .8px;

        }


        .ot-status.approved {

            color: #76c77c;

        }


        .ot-status.pending {

            color: #c9a227;

        }


        .ot-status.rejected {

            color: #d66;

        }


        .ot-details {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 12px;

        }


        .ot-detail-label {

            display: block;

            margin-bottom: 5px;

            color: #666;

            font-size: 8px;

            font-weight: 700;

            letter-spacing: .8px;

        }


        .ot-detail-value {

            color: #ccc;

            font-size: 11px;

            font-weight: 700;

        }


        .ot-pay {

            color: #c9a227;

        }


        .ot-reason {

            margin-top: 15px;

            padding-top: 13px;

            border-top: 1px solid rgba(255,255,255,0.06);

            color: #777;

            font-size: 10px;

        }


        /* =====================================================
           ADD OT MODAL
           ===================================================== */

        .ot-modal {

            position: fixed;

            inset: 0;

            z-index: 9999;

            display: none;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        .ot-modal.active {

            display: flex;

        }


        .ot-modal-overlay {

            position: absolute;

            inset: 0;

            background: rgba(0,0,0,.78);

            backdrop-filter: blur(8px);

        }


        .ot-modal-card {

            position: relative;

            z-index: 2;

            width: 100%;

            max-width: 520px;

            padding: 28px;

            background: #151515;

            border: 1px solid rgba(201,162,39,.25);

            border-radius: 18px;

            box-shadow: 0 30px 80px rgba(0,0,0,.6);

            animation: otModalIn .25s ease;

        }


        @keyframes otModalIn {

            from {

                opacity: 0;

                transform:
                    translateY(20px)
                    scale(.96);

            }

            to {

                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);

            }

        }


        /* =====================================================
           MODAL HEADER
           ===================================================== */

        .ot-modal-header {

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            margin-bottom: 25px;

        }


        .ot-modal-eyebrow {

            margin-bottom: 7px;

            color: #c9a227;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 1.8px;

        }


        .ot-modal-header h2 {

            margin: 0;

            color: #f2f2f2;

            font-size: 24px;

        }


        .ot-modal-header p {

            margin: 7px 0 0;

            color: #777;

            font-size: 11px;

        }


        .ot-close-btn {

            width: 34px;

            height: 34px;

            background: rgba(255,255,255,.04);

            border: 1px solid rgba(255,255,255,.08);

            border-radius: 50%;

            color: #aaa;

            font-size: 20px;

            cursor: pointer;

        }


        .ot-close-btn:hover {

            color: #fff;

            background: rgba(255,255,255,.08);

        }


        /* =====================================================
           OT FORM
           ===================================================== */

        .ot-form-group {

            margin-bottom: 18px;

        }


        .ot-form-group label {

            display: block;

            margin-bottom: 8px;

            color: #777;

            font-size: 8px;

            font-weight: 800;

            letter-spacing: 1.2px;

        }


        .ot-form-group input,
        .ot-form-group textarea {

            width: 100%;

            box-sizing: border-box;

            padding: 13px 14px;

            background: #0f0f0f;

            border: 1px solid rgba(255,255,255,.09);

            border-radius: 9px;

            outline: none;

            color: #eee;

            font-family: inherit;

            font-size: 12px;

            transition: .2s ease;

        }


        .ot-form-group input:focus,
        .ot-form-group textarea:focus {

            border-color: rgba(201,162,39,.6);

            box-shadow:
                0 0 0 3px
                rgba(201,162,39,.07);

        }


        .ot-form-group textarea {

            resize: vertical;

            min-height: 80px;

        }


        .ot-time-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 14px;

        }


        /* =====================================================
           OT CALCULATION
           ===================================================== */

        .ot-calculation {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 10px;

            margin: 5px 0 20px;

        }


        .ot-calculation > div {

            padding: 15px;

            background: rgba(201,162,39,.06);

            border: 1px solid rgba(201,162,39,.14);

            border-radius: 10px;

        }


        .ot-calculation span {

            display: block;

            margin-bottom: 6px;

            color: #777;

            font-size: 8px;

            font-weight: 800;

            letter-spacing: 1px;

        }


        .ot-calculation strong {

            color: #c9a227;

            font-size: 17px;

        }


        /* =====================================================
           MODAL ACTIONS
           ===================================================== */

        .ot-modal-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 25px;

        }


        .ot-cancel-btn,
        .ot-submit-btn {

            padding: 12px 17px;

            border-radius: 8px;

            font-size: 9px;

            font-weight: 800;

            letter-spacing: 1px;

            cursor: pointer;

        }


        .ot-cancel-btn {

            background: transparent;

            border: 1px solid rgba(255,255,255,.1);

            color: #888;

        }


        .ot-submit-btn {

            background: #c9a227;

            border: 1px solid #c9a227;

            color: #0b0b0b;

        }


        .ot-submit-btn:hover {

            background: #dfbd42;

        }


        /* =====================================================
           RESPONSIVE
           ===================================================== */

        @media (max-width: 900px) {

            .payroll-page {

                padding: 25px;

            }

            .payroll-summary {

                grid-template-columns: 1fr;

            }

            .ot-list {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 600px) {

            .payroll-page {

                padding: 20px;

            }

            .payroll-header h1 {

                font-size: 27px;

            }

            .payroll-employee {

                align-items: flex-start;

                flex-direction: column;

            }

            .payroll-rate {

                text-align: left;

            }

            .ot-section-header {

                align-items: flex-start;

                gap: 15px;

            }

            .ot-time-grid,
            .ot-calculation {

                grid-template-columns: 1fr;

            }

            .ot-modal-card {

                padding: 22px;

            }

        }

    </style>

</head>


<body>


<?php include "includes/sidebar.php"; ?>


<main class="employee-main">


    <div class="payroll-page">


        <!-- =================================================
             HEADER
             ================================================= -->

        <div class="payroll-header">

            <div class="payroll-eyebrow">
                EMPLOYEE PORTAL / PAYSLIPS
            </div>

            <h1>
                My Payslips
            </h1>

            <p>
                View processed payslip records.
            </p>

        </div>


        <!-- =================================================
             SUMMARY
             ================================================= -->

        <div class="payroll-summary">


            <div class="payroll-summary-card">

                <div class="payroll-summary-label">
                    PAYROLL RECORDS
                </div>

                <div class="payroll-summary-value">
                    <?= number_format($totalPayroll) ?>
                </div>

            </div>


            <div class="payroll-summary-card">

                <div class="payroll-summary-label">
                    APPROVED OT HOURS
                </div>

                <div class="payroll-summary-value">
                    <?= number_format($totalOTHours, 2) ?>
                </div>

            </div>


            <div class="payroll-summary-card">

                <div class="payroll-summary-label">
                    APPROVED OT PAY
                </div>

                <div class="payroll-summary-value gold">
                    <?= payrollMoney($totalOTPay) ?>
                </div>

            </div>


        </div>


        <!-- =================================================
             EMPLOYEE INFORMATION
             ================================================= -->

        <div class="payroll-employee">


            <div>

                <div class="payroll-employee-name">
                    <?= e($fullName) ?>
                </div>


                <div class="payroll-employee-meta">

                    <?= e($employee["employee_no"]) ?>

                    <?php if ($employee["department"]): ?>

                        &nbsp; • &nbsp;

                        <?= e($employee["department"]) ?>

                    <?php endif; ?>


                    <?php if ($employee["position"]): ?>

                        &nbsp; • &nbsp;

                        <?= e($employee["position"]) ?>

                    <?php endif; ?>

                </div>

            </div>


            <div class="payroll-rate">

                <span class="payroll-rate-label">
                    HOURLY RATE
                </span>


                <span class="payroll-rate-value">

                    <?= $employee["hourly_rate"] !== null
                        ? payrollMoney($employee["hourly_rate"])
                        : "NOT SET"
                    ?>

                </span>

            </div>


        </div>


        <!-- =================================================
             PAYROLL HISTORY
             ================================================= -->

        <section class="payroll-section">


            <div class="payroll-section-title">
                PAYROLL HISTORY
            </div>


            <div class="payroll-table-wrap">


                <?php if ($payrollRecords): ?>


                    <table class="payroll-table">


                        <thead>

                            <tr>

                                <th>
                                    PAY PERIOD
                                </th>

                                <th>
                                    REGULAR HOURS
                                </th>

                                <th>
                                    BASIC PAY
                                </th>

                                <th>
                                    OT HOURS
                                </th>

                                <th>
                                    OT PAY
                                </th>

                                <th>
                                    GROSS PAY
                                </th>

                                <th>
                                    DEDUCTIONS
                                </th>

                                <th>
                                    NET PAY
                                </th>

                                <th>
                                    STATUS
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($payrollRecords as $payroll): ?>


                                <tr>


                                    <td>

                                        <?= payrollDate(
                                            $payroll["period_start"]
                                        ) ?>

                                        —

                                        <?= payrollDate(
                                            $payroll["period_end"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float) $payroll["regular_hours"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= payrollMoney(
                                            $payroll["regular_pay"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            (float) $payroll["overtime_hours"],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= payrollMoney(
                                            $payroll["overtime_pay"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= payrollMoney(
                                            $payroll["gross_pay"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= payrollMoney(
                                            $payroll["deductions"]
                                        ) ?>

                                    </td>


                                    <td class="payroll-net">

                                        <?= payrollMoney(
                                            $payroll["net_pay"]
                                        ) ?>

                                    </td>


                                    <td>

                                        <span
                                            class="payroll-status <?= e(
                                                payrollStatusClass(
                                                    $payroll["status"]
                                                )
                                            ) ?>"
                                        >

                                            <?= e(
                                                $payroll["status"]
                                            ) ?>

                                        </span>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                <?php else: ?>


                    <div class="payroll-empty">

                        <strong>
                            No payroll records yet.
                        </strong>

                        <span>
                            Your payroll records will appear here once payroll has been processed.
                        </span>

                    </div>


                <?php endif; ?>


            </div>


        </section>


        <?php if (false): ?>
        <!-- =================================================
             OVERTIME
             ================================================= -->

        <section class="payroll-section">


            <div class="ot-section-header">


                <div class="payroll-section-title">
                    OVERTIME RECORDS
                </div>


                <button
                    type="button"
                    class="add-ot-btn"
                    onclick="openOTModal()"
                >
                    + ADD OT
                </button>


            </div>


            <?php if ($overtimeRecords): ?>


                <div class="ot-list">


                    <?php foreach ($overtimeRecords as $ot): ?>


                        <div class="ot-card">


                            <div class="ot-card-top">


                                <div class="ot-date">

                                    <?= payrollDate(
                                        $ot["ot_date"]
                                    ) ?>

                                </div>


                                <div
                                    class="ot-status <?= e(
                                        strtolower(
                                            $ot["status"]
                                        )
                                    ) ?>"
                                >

                                    <?= e(
                                        $ot["status"]
                                    ) ?>

                                </div>


                            </div>


                            <div class="ot-details">


                                <div>

                                    <span class="ot-detail-label">
                                        TIME
                                    </span>

                                    <div class="ot-detail-value">

                                        <?= payrollTime(
                                            $ot["start_time"]
                                        ) ?>

                                        —

                                        <?= payrollTime(
                                            $ot["end_time"]
                                        ) ?>

                                    </div>

                                </div>


                                <div>

                                    <span class="ot-detail-label">
                                        OT HOURS
                                    </span>

                                    <div class="ot-detail-value">

                                        <?= number_format(
                                            (float) $ot["ot_hours"],
                                            2
                                        ) ?>

                                    </div>

                                </div>


                                <div>

                                    <span class="ot-detail-label">
                                        OT PAY
                                    </span>

                                    <div class="ot-detail-value ot-pay">

                                        <?= payrollMoney(
                                            $ot["ot_pay"]
                                        ) ?>

                                    </div>

                                </div>


                            </div>


                            <?php if ($ot["reason"]): ?>


                                <div class="ot-reason">

                                    <strong>
                                        Reason:
                                    </strong>

                                    <?= e(
                                        $ot["reason"]
                                    ) ?>

                                </div>


                            <?php endif; ?>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="payroll-table-wrap">

                    <div class="payroll-empty">

                        <strong>
                            No overtime records yet.
                        </strong>

                        <span>
                            Submit an overtime request using the ADD OT button.
                        </span>

                    </div>

                </div>


            <?php endif; ?>


        </section>


    </div>


</main>


<!-- =========================================================
     ADD OT MODAL
     ========================================================= -->

<div
    class="ot-modal"
    id="otModal"
>


    <div
        class="ot-modal-overlay"
        onclick="closeOTModal()"
    ></div>


    <div class="ot-modal-card">


        <div class="ot-modal-header">


            <div>

                <div class="ot-modal-eyebrow">
                    OVERTIME REQUEST
                </div>


                <h2>
                    Add Overtime
                </h2>


                <p>
                    Submit an overtime request for approval.
                </p>

            </div>


            <button
                type="button"
                class="ot-close-btn"
                onclick="closeOTModal()"
            >
                ×
            </button>


        </div>


        <form
            method="POST"
            id="otForm"
        >


            <input
                type="hidden"
                name="action"
                value="add_ot"
            >


            <!-- DATE -->

            <div class="ot-form-group">

                <label for="otDate">
                    OT DATE
                </label>


                <input
                    type="date"
                    id="otDate"
                    name="ot_date"
                    required
                >

            </div>


            <!-- TIME -->

            <div class="ot-time-grid">


                <div class="ot-form-group">

                    <label for="otStart">
                        START TIME
                    </label>


                    <input
                        type="time"
                        id="otStart"
                        name="start_time"
                        required
                    >

                </div>


                <div class="ot-form-group">

                    <label for="otEnd">
                        END TIME
                    </label>


                    <input
                        type="time"
                        id="otEnd"
                        name="end_time"
                        required
                    >

                </div>


            </div>


            <!-- CALCULATION -->

            <div class="ot-calculation">


                <div>

                    <span>
                        OT HOURS
                    </span>


                    <strong id="otHoursDisplay">
                        0.00 hrs
                    </strong>

                </div>


                <div>

                    <span>
                        ESTIMATED OT PAY
                    </span>


                    <strong id="otPayDisplay">
                        ₱0.00
                    </strong>

                </div>


            </div>


            <!-- REASON -->

            <div class="ot-form-group">

                <label for="otReason">
                    REASON
                </label>


                <textarea
                    id="otReason"
                    name="reason"
                    rows="3"
                    placeholder="Enter reason for overtime..."
                ></textarea>

            </div>


            <!-- ACTIONS -->

            <div class="ot-modal-actions">


                <button
                    type="button"
                    class="ot-cancel-btn"
                    onclick="closeOTModal()"
                >
                    CANCEL
                </button>


                <button
                    type="submit"
                    class="ot-submit-btn"
                >
                    SUBMIT OT
                </button>


            </div>


        </form>


    </div>


</div>


<script>

/* =========================================================
   OPEN OT MODAL
   ========================================================= */

function openOTModal()
{

    const modal =
        document.getElementById("otModal");


    if (!modal) {
        return;
    }


    modal.classList.add("active");


    document.body.style.overflow =
        "hidden";


    calculateOT();

}


/* =========================================================
   CLOSE OT MODAL
   ========================================================= */

function closeOTModal()
{

    const modal =
        document.getElementById("otModal");


    if (!modal) {
        return;
    }


    modal.classList.remove("active");


    document.body.style.overflow =
        "";

}


/* =========================================================
   CALCULATE OT
   ========================================================= */

function calculateOT()
{

    const startInput =
        document.getElementById("otStart");


    const endInput =
        document.getElementById("otEnd");


    const hoursDisplay =
        document.getElementById(
            "otHoursDisplay"
        );


    const payDisplay =
        document.getElementById(
            "otPayDisplay"
        );


    if (
        !startInput ||
        !endInput ||
        !hoursDisplay ||
        !payDisplay
    ) {

        return;

    }


    const start =
        startInput.value;


    const end =
        endInput.value;


    if (!start || !end)
    {

        hoursDisplay.textContent =
            "0.00 hrs";


        payDisplay.textContent =
            "₱0.00";


        return;

    }


    const startParts =
        start.split(":");


    const endParts =
        end.split(":");


    let startMinutes =
        parseInt(startParts[0]) * 60 +
        parseInt(startParts[1]);


    let endMinutes =
        parseInt(endParts[0]) * 60 +
        parseInt(endParts[1]);


    /*
     * Overnight OT
     *
     * Example:
     *
     * 10:00 PM → 2:00 AM
     *
     * = 4 hours
     */

    if (endMinutes <= startMinutes)
    {

        endMinutes +=
            24 * 60;

    }


    const difference =
        endMinutes -
        startMinutes;


    const hours =
        difference / 60;


    /*
     * Initial OT computation.
     *
     * Hourly Rate × 1.25
     */

    const hourlyRate =
        <?= $employee["hourly_rate"] !== null
            ? (float) $employee["hourly_rate"]
            : 0
        ?>;


    const otRate =
        hourlyRate * 1.25;


    const estimatedPay =
        hours * otRate;


    hoursDisplay.textContent =
        hours.toFixed(2) +
        " hrs";


    payDisplay.textContent =
        "₱" +
        estimatedPay.toLocaleString(
            "en-PH",
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

}


/* =========================================================
   TIME CHANGE EVENTS
   ========================================================= */

document
    .getElementById("otStart")
    ?.addEventListener(
        "change",
        calculateOT
    );


document
    .getElementById("otEnd")
    ?.addEventListener(
        "change",
        calculateOT
    );


/* =========================================================
   ESC KEY
   ========================================================= */

document.addEventListener(
    "keydown",
    function(event)
    {

        if (event.key === "Escape")
        {

            closeOTModal();

        }

    }
);

</script>

<?php endif; ?>


</body>

</html>