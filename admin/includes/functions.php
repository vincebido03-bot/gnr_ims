<?php

require_once __DIR__ . "/../../config/database.php";


/* =========================================================
   AUDIT LOG
   ========================================================== */

function logAudit(
    $module,
    $action,
    $referenceType = null,
    $referenceId = null,
    $oldValues = null,
    $newValues = null
) {
    global $pdo;

    $userId = $_SESSION["user_id"] ?? null;
    $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;
    $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            user_id,
            module,
            action,
            reference_type,
            reference_id,
            old_values,
            new_values,
            ip_address,
            user_agent
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $userId,
        $module,
        $action,
        $referenceType,
        $referenceId,
        $oldValues !== null
            ? json_encode($oldValues, JSON_UNESCAPED_UNICODE)
            : null,
        $newValues !== null
            ? json_encode($newValues, JSON_UNESCAPED_UNICODE)
            : null,
        $ipAddress,
        $userAgent
    ]);
}


/* =========================================================
   CREATE NOTIFICATION
   ========================================================== */

function createNotification(
    $type,
    $title,
    $message,
    $employeeId = null,
    $referenceType = null,
    $referenceId = null
) {
    global $pdo;

    /*
     * Send notification only to ACTIVE users
     * who have notifications.view permission.
     */

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id
        FROM users u

        INNER JOIN roles r
            ON u.role_id = r.id

        INNER JOIN role_permissions rp
            ON r.id = rp.role_id

        INNER JOIN permissions p
            ON rp.permission_id = p.id

        WHERE u.status = 'ACTIVE'
          AND p.module = 'notifications'
          AND p.action = 'view'
    ");

    $stmt->execute();

    $users = $stmt->fetchAll();


    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications (
            type,
            title,
            message,
            employee_id,
            user_id,
            reference_type,
            reference_id,
            is_read
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, 0
        )
    ");


    foreach ($users as $user) {

        $notificationStmt->execute([
            $type,
            $title,
            $message,
            $employeeId,
            $user["id"],
            $referenceType,
            $referenceId
        ]);

    }


    return true;
}

function syncLowStockNotifications()
{
    global $pdo;

    $items = $pdo->query("
        SELECT id, item_code, item_name, current_stock, reorder_level, unit
        FROM inventory_items
        WHERE status = 'ACTIVE'
          AND current_stock <= reorder_level
    ")->fetchAll();

    $recentAlert = $pdo->prepare("
        SELECT id
        FROM notifications
        WHERE type = 'LOW_STOCK'
          AND reference_type = 'INVENTORY_ITEM'
          AND reference_id = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");

    foreach ($items as $item) {
        $recentAlert->execute([(int) $item["id"]]);

        if ($recentAlert->fetchColumn()) {
            continue;
        }

        createNotification(
            "LOW_STOCK",
            "Low Stock Alert",
            $item["item_name"] . " (" . $item["item_code"] . ") has " .
                rtrim(rtrim(number_format((float) $item["current_stock"], 2), "0"), ".") .
                " " . $item["unit"] . " remaining. Reorder threshold: " .
                rtrim(rtrim(number_format((float) $item["reorder_level"], 2), "0"), ".") .
                " " . $item["unit"] . ".",
            null,
            "INVENTORY_ITEM",
            (int) $item["id"]
        );
    }
}


/* =========================================================
   FORMATTING HELPERS
   ========================================================== */

function formatCurrency($amount)
{
    return "₱" . number_format(
        (float) $amount,
        2
    );
}


function formatDate($date)
{
    if (!$date) {
        return "-";
    }

    return date(
        "M d, Y",
        strtotime($date)
    );
}


function formatDateTime($datetime)
{
    if (!$datetime) {
        return "-";
    }

    return date(
        "M d, Y h:i A",
        strtotime($datetime)
    );
}


function statusClass($status)
{
    return strtolower(
        preg_replace(
            "/[^a-zA-Z0-9]+/",
            "-",
            $status
        )
    );
}


function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   ARCHIVE HELPERS
   ========================================================== */

function archiveRecord(
    $module,
    $recordId,
    $recordNo,
    $recordName,
    $deletedBy,
    $reason,
    $snapshot
) {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO archive_records (
            module,
            record_id,
            record_no,
            record_name,
            deleted_by,
            reason,
            data_snapshot,
            status
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, 'ARCHIVED'
        )
    ");

    $stmt->execute([
        $module,
        $recordId,
        $recordNo,
        $recordName,
        $deletedBy,
        $reason,
        json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE
        )
    ]);

    return true;
}


function restoreArchiveRecord(
    $archiveId,
    $restoredBy
) {
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE archive_records
        SET
            status = 'RESTORED',
            restored_by = ?,
            restored_at = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $restoredBy,
        $archiveId
    ]);

    return true;
}


function archiveCategories()
{
    return [
        "EMPLOYEES",
        "CUSTOMERS",
        "VEHICLES",
        "INQUIRIES"
    ];
}


/* =========================================================
   EMPLOYEE TIME IN
   ========================================================== */

function employeeTimeIn(
    $employeeId,
    $notes = null
) {
    global $pdo;


    /* =====================================================
       CHECK EMPLOYEE
       ====================================================== */

    $stmt = $pdo->prepare("
        SELECT
            id,
            first_name,
            middle_name,
            last_name,
            nickname,
            status
        FROM employees
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $employeeId
    ]);

    $employee = $stmt->fetch();


    if (!$employee) {

        return [
            "success" => false,
            "message" => "Employee record not found."
        ];

    }


    if (
        strtoupper(
            $employee["status"]
        ) !== "ACTIVE"
    ) {

        return [
            "success" => false,
            "message" => "Your employee account is not active."
        ];

    }


    /* =====================================================
       CHECK OPEN ATTENDANCE
       ====================================================== */

    $stmt = $pdo->prepare("
        SELECT
            id,
            date,
            time_in,
            time_out
        FROM attendance
        WHERE employee_id = ?
          AND time_out IS NULL
        ORDER BY date DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $employeeId
    ]);

    $openAttendance = $stmt->fetch();


    if ($openAttendance) {

        return [
            "success" => false,
            "message" =>
                "You already have an active Time In record. Please Time Out first."
        ];

    }


    /* =====================================================
    CREATE TIME IN
    ====================================================== */

    $currentDateTime = date("Y-m-d H:i:s");
    $today = date("Y-m-d", strtotime($currentDateTime));

    $stmt = $pdo->prepare("
        INSERT INTO attendance (
            employee_id,
            date,
            time_in,
            status,
            notes
        )
        VALUES (
            ?, ?, ?, 'PRESENT', ?
        )
    ");

    $stmt->execute([
        $employeeId,
        $today,
        $currentDateTime,
        $notes
    ]);


    $attendanceId =
        $pdo->lastInsertId();


    /* =====================================================
       DISPLAY NAME
       ====================================================== */

    if (!empty($employee["nickname"])) {

        $displayName =
            $employee["nickname"] .
            " " .
            $employee["last_name"];

    } else {

        $displayName =
            $employee["first_name"] .
            " " .
            $employee["last_name"];

    }


    /* =====================================================
       NOTIFICATION
       ====================================================== */

    createNotification(
        "ATTENDANCE",
        "Employee Time In",
        $displayName .
        " timed in at " .
        date(
            "h:i A",
            strtotime($currentDateTime)
        ) .
        " on " .
        date("M d, Y"),
        $employeeId,
        "ATTENDANCE",
        $attendanceId
    );


    /* =====================================================
       AUDIT
       ====================================================== */

    logAudit(
        "ATTENDANCE",
        "TIME_IN",
        "ATTENDANCE",
        $attendanceId,
        null,
        [
            "employee_id" => $employeeId,
            "date" => $today,
            "time_in" => $currentDateTime
        ]
    );


    return [
        "success" => true,
        "message" =>
            "Time In recorded successfully.",
        "attendance_id" =>
            $attendanceId
    ];
}


/* =========================================================
   EMPLOYEE START BREAK
   ========================================================== */

function employeeStartBreak(
    $employeeId
) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM employees
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        return [
            "success" => false,
            "message" => "Employee record not found."
        ];
    }

    if (strtoupper($employee["status"]) !== "ACTIVE") {
        return [
            "success" => false,
            "message" => "Your employee account is not active."
        ];
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM attendance
        WHERE employee_id = ?
          AND time_out IS NULL
        ORDER BY date DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([$employeeId]);
    $attendance = $stmt->fetch();

    if (!$attendance) {
        return [
            "success" => false,
            "message" => "You must Time In before starting a break."
        ];
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM attendance_breaks
        WHERE attendance_id = ?
        LIMIT 1
    ");

    $stmt->execute([$attendance["id"]]);

    if ($stmt->fetch()) {
        return [
            "success" => false,
            "message" => "Only one break is allowed per duty."
        ];
    }

    $breakOut = date("Y-m-d H:i:s");

    $stmt = $pdo->prepare("
        INSERT INTO attendance_breaks (
            attendance_id,
            break_out
        )
        VALUES (?, ?)
    ");

    $stmt->execute([
        $attendance["id"],
        $breakOut
    ]);

    $breakId = $pdo->lastInsertId();

    logAudit(
        "ATTENDANCE",
        "BREAK_START",
        "ATTENDANCE_BREAK",
        $breakId,
        null,
        [
            "employee_id" => $employeeId,
            "attendance_id" => $attendance["id"],
            "break_out" => $breakOut
        ]
    );

    return [
        "success" => true,
        "message" => "Break started successfully.",
        "break_id" => $breakId
    ];
}


/* =========================================================
   EMPLOYEE END BREAK
   ========================================================== */

function employeeEndBreak(
    $employeeId
) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT id
        FROM attendance
        WHERE employee_id = ?
          AND time_out IS NULL
        ORDER BY date DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([$employeeId]);
    $attendance = $stmt->fetch();

    if (!$attendance) {
        return [
            "success" => false,
            "message" => "No active Time In record was found."
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            break_out
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$attendance["id"]]);
    $break = $stmt->fetch();

    if (!$break) {
        return [
            "success" => false,
            "message" => "No active break was found."
        ];
    }

    $breakIn = date("Y-m-d H:i:s");
    $breakMinutes = (int) floor(
        (strtotime($breakIn) - strtotime($break["break_out"])) / 60
    );

    if ($breakMinutes < 0) {
        return [
            "success" => false,
            "message" => "Invalid break duration detected."
        ];
    }

    $stmt = $pdo->prepare("
        UPDATE attendance_breaks
        SET
            break_in = ?,
            break_minutes = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $breakIn,
        $breakMinutes,
        $break["id"]
    ]);

    logAudit(
        "ATTENDANCE",
        "BREAK_END",
        "ATTENDANCE_BREAK",
        $break["id"],
        [
            "break_out" => $break["break_out"],
            "break_in" => null
        ],
        [
            "break_out" => $break["break_out"],
            "break_in" => $breakIn,
            "break_minutes" => $breakMinutes
        ]
    );

    return [
        "success" => true,
        "message" => "Break ended successfully. " . $breakMinutes . " minutes.",
        "break_minutes" => $breakMinutes
    ];
}


/* =========================================================
   EMPLOYEE TIME OUT
   ========================================================== */

function employeeTimeOut(
    $employeeId,
    $notes = null
) {
    global $pdo;


    /* =====================================================
       FIND OPEN ATTENDANCE
       ====================================================== */

    $stmt = $pdo->prepare("
        SELECT
            id,
            employee_id,
            date,
            time_in,
            time_out,
            late_minutes,
            overtime_hours,
            status,
            notes
        FROM attendance
        WHERE employee_id = ?
          AND time_out IS NULL
        ORDER BY date DESC, id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $employeeId
    ]);

    $attendance = $stmt->fetch();


    if (!$attendance) {

        return [
            "success" => false,
            "message" =>
                "No active Time In record was found."
        ];

    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NULL
        LIMIT 1
    ");

    $stmt->execute([$attendance["id"]]);

    if ($stmt->fetch()) {
        return [
            "success" => false,
            "message" => "Please end your break before recording Time Out."
        ];
    }


    /* =====================================================
    TIME OUT
    ====================================================== */

    $timeOut = date("Y-m-d H:i:s");


    /* =====================================================
    CREATE TIMESTAMPS
    ====================================================== */

    $timeInTimestamp = strtotime(
        $attendance["time_in"]
    );

    $timeOutTimestamp = strtotime(
        $timeOut
    );


    /* =====================================================
       COMPUTE WORKED HOURS
       ====================================================== */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(break_minutes), 0) AS break_minutes
        FROM attendance_breaks
        WHERE attendance_id = ?
          AND break_in IS NOT NULL
    ");

    $stmt->execute([$attendance["id"]]);
    $breakMinutes = (int) $stmt->fetchColumn();

    $workedSeconds =
        $timeOutTimestamp -
        $timeInTimestamp -
        ($breakMinutes * 60);


    $workedHours =
        round(
            $workedSeconds / 3600,
            2
        );


    /*
     * Safety check.
     */

    if (
        $workedHours <= 0 ||
        $workedHours > 24
    ) {

        return [
            "success" => false,
            "message" =>
                "Invalid work duration detected."
        ];

    }


    /* =====================================================
       UPDATE ATTENDANCE
       ====================================================== */

    $stmt = $pdo->prepare("
        UPDATE attendance
        SET
            time_out = ?,
            overtime_hours = 0,
            notes = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $timeOut,
        $notes ?: $attendance["notes"],
        $attendance["id"]
    ]);


    /* =====================================================
       GET EMPLOYEE
       ====================================================== */

    $stmt = $pdo->prepare("
        SELECT
            first_name,
            last_name,
            nickname
        FROM employees
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $employeeId
    ]);

    $employee = $stmt->fetch();


    if (!$employee) {

        return [
            "success" => false,
            "message" =>
                "Employee record not found."
        ];

    }


    if (!empty($employee["nickname"])) {

        $displayName =
            $employee["nickname"] .
            " " .
            $employee["last_name"];

    } else {

        $displayName =
            $employee["first_name"] .
            " " .
            $employee["last_name"];

    }


    /* =====================================================
       TIME OUT NOTIFICATION
       ====================================================== */

    createNotification(
        "ATTENDANCE",
        "Employee Time Out",
        $displayName .
        " timed out at " .
        date(
            "h:i A",
            strtotime($timeOut)
        ) .
        " — " .
        number_format(
            $workedHours,
            2
        ) .
        " hours worked.",
        $employeeId,
        "ATTENDANCE",
        $attendance["id"]
    );


    /* =====================================================
       AUDIT
       ====================================================== */

    logAudit(
        "ATTENDANCE",
        "TIME_OUT",
        "ATTENDANCE",
        $attendance["id"],
        [
            "time_in" =>
                $attendance["time_in"],
            "time_out" => null
        ],
        [
            "time_in" =>
                $attendance["time_in"],
            "time_out" =>
                $timeOut,
            "worked_hours" =>
                $workedHours
        ]
    );


    /* =====================================================
       RESULT
       ====================================================== */

    return [
        "success" => true,
        "message" =>
            "Time Out recorded successfully. " .
            number_format(
                $workedHours,
                2
            ) .
            " hours worked.",
        "attendance_id" =>
            $attendance["id"],
        "worked_hours" =>
            $workedHours
    ];
}
