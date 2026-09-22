<?php

require_once "includes/header.php";

requirePermission("archive", "view");


/*
=========================================================
RESTORE ARCHIVED RECORD
=========================================================
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["restore_archive_id"])) {

    if (!hasPermission("archive", "restore")) {
        http_response_code(403);
        die("Access Denied.");
    }

    $archiveId = (int) ($_POST["restore_archive_id"] ?? 0);

    if ($archiveId > 0) {

        $archiveStmt = $pdo->prepare("
            SELECT
                id,
                module,
                record_id,
                record_no,
                record_name,
                status,
                data_snapshot
            FROM archive_records
            WHERE id = ?
            LIMIT 1
        ");

        $archiveStmt->execute([$archiveId]);

        $archive = $archiveStmt->fetch();

        if ($archive && $archive["module"] === "EMPLOYEES") {

            $employeeStmt = $pdo->prepare("
                SELECT
                    id,
                    status,
                    employee_no
                FROM employees
                WHERE id = ?
                LIMIT 1
            ");

            $employeeStmt->execute([$archive["record_id"]]);

            $employee = $employeeStmt->fetch();

            if (!$employee) {

                $_SESSION["archive_message"] =
                    "Cannot restore this employee because the original employee record is missing.";

                header("Location: archive.php");
                exit;
            }

            $updateEmployee = $pdo->prepare("
                UPDATE employees
                SET
                    status = 'ACTIVE',
                    updated_at = NOW()
                WHERE id = ?
            ");

            $updateEmployee->execute([
                $archive["record_id"]
            ]);

            $updateArchive = $pdo->prepare("
                UPDATE archive_records
                SET
                    status = 'RESTORED',
                    restored_by = ?,
                    restored_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");

            $updateArchive->execute([
                $_SESSION["user_id"] ?? null,
                $archiveId
            ]);

            logAudit(
                "EMPLOYEES",
                "RESTORE",
                "EMPLOYEE",
                $archive["record_id"],
                [
                    "status" => $employee["status"],
                    "archive_id" => $archiveId
                ],
                [
                    "status" => "ACTIVE",
                    "archive_id" => $archiveId,
                    "restored_by" => $_SESSION["user_id"] ?? null
                ]
            );

            $_SESSION["archive_message"] =
                "Employee restored successfully.";

            header("Location: archive.php");
            exit;
        }

        $_SESSION["archive_message"] =
            "This archive item cannot be restored from this screen.";

        header("Location: archive.php");
        exit;
    }
}


/*
=========================================================
PERMANENT DELETE
=========================================================
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_archive_id"])) {

    if (!hasPermission("archive", "delete_permanently")) {
        http_response_code(403);
        die("Access Denied.");
    }

    $archiveId = (int) ($_POST["delete_archive_id"] ?? 0);

    if ($archiveId <= 0) {

        $_SESSION["archive_message"] =
            "Invalid archive record.";

        header("Location: archive.php");
        exit;
    }

    try {

        $pdo->beginTransaction();


        /*
        =========================================================
        GET ARCHIVE RECORD
        =========================================================
        */

        $archiveStmt = $pdo->prepare("
            SELECT
                id,
                module,
                record_id,
                record_name,
                record_no,
                status
            FROM archive_records
            WHERE id = ?
            LIMIT 1
        ");

        $archiveStmt->execute([
            $archiveId
        ]);

        $archive = $archiveStmt->fetch();


        if (!$archive) {

            throw new Exception(
                "Archive record not found."
            );
        }


        /*
        =========================================================
        ONLY DELETE ARCHIVED RECORDS
        =========================================================
        */

        if (strtoupper($archive["status"] ?? "") !== "ARCHIVED") {

            throw new Exception(
                "Only archived records can be permanently deleted."
            );
        }


        $module = strtoupper(
            trim($archive["module"] ?? "")
        );

        $recordId = (int) (
            $archive["record_id"] ?? 0
        );


        /*
        =========================================================
        EMPLOYEE PERMANENT DELETE
        =========================================================
        */

        if ($module === "EMPLOYEES") {


            /*
            -----------------------------------------------------
            PROTECT SUPER ADMIN
            -----------------------------------------------------
            */

            $protectedUserStmt = $pdo->prepare("
                SELECT
                    id,
                    username,
                    role_id,
                    status
                FROM users
                WHERE employee_id = ?
                LIMIT 1
            ");

            $protectedUserStmt->execute([
                $recordId
            ]);

            $linkedUser = $protectedUserStmt->fetch();


            if (
                $linkedUser &&
                (int) $linkedUser["role_id"] === 1
            ) {

                throw new Exception(
                    "This employee is linked to a SUPER ADMIN account and cannot be permanently deleted."
                );
            }


            /*
            -----------------------------------------------------
            GET EMPLOYEE INFORMATION
            -----------------------------------------------------
            */

            $employeeStmt = $pdo->prepare("
                SELECT
                    id,
                    employee_no,
                    first_name,
                    middle_name,
                    last_name,
                    status
                FROM employees
                WHERE id = ?
                LIMIT 1
            ");

            $employeeStmt->execute([
                $recordId
            ]);

            $employee = $employeeStmt->fetch();


            if (!$employee) {

                throw new Exception(
                    "The original employee record no longer exists."
                );
            }


            /*
            -----------------------------------------------------
            GET LINKED LOGIN ACCOUNTS
            -----------------------------------------------------
            */

            $userStmt = $pdo->prepare("
                SELECT
                    id,
                    username,
                    email
                FROM users
                WHERE employee_id = ?
            ");

            $userStmt->execute([
                $recordId
            ]);

            $linkedUsers = $userStmt->fetchAll();


            /*
            -----------------------------------------------------
            DELETE LINKED LOGIN ACCOUNTS
            -----------------------------------------------------
            */

            $deleteUsers = $pdo->prepare("
                DELETE FROM users
                WHERE employee_id = ?
            ");

            $deleteUsers->execute([
                $recordId
            ]);


            /*
            -----------------------------------------------------
            DELETE EMPLOYEE
            -----------------------------------------------------
            */

            $deleteEmployee = $pdo->prepare("
                DELETE FROM employees
                WHERE id = ?
                LIMIT 1
            ");

            $deleteEmployee->execute([
                $recordId
            ]);


            /*
            -----------------------------------------------------
            DELETE ARCHIVE RECORD
            -----------------------------------------------------
            */

            $deleteArchive = $pdo->prepare("
                DELETE FROM archive_records
                WHERE id = ?
                LIMIT 1
            ");

            $deleteArchive->execute([
                $archiveId
            ]);


            /*
            -----------------------------------------------------
            AUDIT PERMANENT DELETE
            -----------------------------------------------------
            */

            logAudit(
                "EMPLOYEES",
                "DELETE_PERMANENTLY",
                "EMPLOYEE",
                $recordId,
                [
                    "employee_id" =>
                        $recordId,

                    "employee_no" =>
                        $employee["employee_no"],

                    "name" =>
                        trim(
                            $employee["first_name"] . " " .
                            $employee["middle_name"] . " " .
                            $employee["last_name"]
                        ),

                    "archive_id" =>
                        $archiveId,

                    "linked_users_deleted" =>
                        count($linkedUsers)
                ],
                [
                    "status" =>
                        "DELETED_PERMANENTLY",

                    "employee_id" =>
                        $recordId,

                    "archive_id" =>
                        $archiveId
                ]
            );


            /*
            -----------------------------------------------------
            COMMIT
            -----------------------------------------------------
            */

            $pdo->commit();


            $_SESSION["archive_message"] =
                "Employee and linked login account permanently deleted.";

            header("Location: archive.php");
            exit;
        }


        /*
        =========================================================
        OTHER MODULES
        =========================================================

        Other modules are intentionally blocked until their
        relationships and permanent-delete rules are configured.
        =========================================================
        */

        throw new Exception(
            "Permanent deletion for the {$module} module is not configured yet."
        );


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION["archive_message"] =
            "Permanent delete failed: " . $e->getMessage();

        header("Location: archive.php");
        exit;
    }
}


/*
=========================================================
CATEGORY FILTER
=========================================================
*/

$categoryFilter = $_GET["category"] ?? "ALL";


$allowedCategories = [
    "ALL",
    "CUSTOMERS",
    "VEHICLES",
    "EMPLOYEES",
    "USER ACCOUNTS",
    "INQUIRIES",
    "QUOTATIONS",
    "ORDERS",
    "JOBS",
    "INVENTORY",
    "PRODUCTS",
    "SERVICES",
    "LABOR",
    "PAYROLL",
    "ATTENDANCE",
    "QUALITY CONTROL",
    "INVOICES",
    "PAYMENTS",
    "RELEASES",
    "OTHER"
];


if (!in_array(
    $categoryFilter,
    $allowedCategories,
    true
)) {

    $categoryFilter = "ALL";
}


$where = "";

$params = [];


if ($categoryFilter !== "ALL") {

    $where = " WHERE ar.module = ? ";

    $params[] = $categoryFilter;
}


/*
=========================================================
GET ARCHIVE RECORDS
=========================================================
*/

$sql = "
    SELECT
        ar.id,
        ar.module,
        ar.record_id,
        ar.record_no,
        ar.record_name,
        ar.deleted_by,
        ar.deleted_at,
        ar.reason,
        ar.status,
        ar.restored_at,
        ar.restored_by,
        du.username AS deleted_by_username,
        ru.username AS restored_by_username
    FROM archive_records ar
    LEFT JOIN users du
        ON ar.deleted_by = du.id
    LEFT JOIN users ru
        ON ar.restored_by = ru.id
    $where
    ORDER BY ar.deleted_at DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$archiveRecords = $stmt->fetchAll();


/*
=========================================================
PERMISSIONS
=========================================================
*/

$canDeletePermanently =
    hasPermission(
        "archive",
        "delete_permanently"
    );

$canRestore =
    hasPermission(
        "archive",
        "restore"
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

```
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>GNR IMS - Archive</title>

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
    href="css/archive.css"
>
```

</head>

<body>

<div class="admin-layout">

```
<?php require_once "includes/sidebar.php"; ?>


<main class="main-content">


    <header class="topbar">


        <div>

            <h1>
                Archive
            </h1>

            <p>
                Safe historical record retention and restoration.
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


    <section class="dashboard-content">


        <?php if (!empty($_SESSION["archive_message"])): ?>

            <div class="employee-message success">

                <?= htmlspecialchars(
                    $_SESSION["archive_message"]
                ) ?>

            </div>

            <?php unset(
                $_SESSION["archive_message"]
            ); ?>

        <?php endif; ?>


        <div class="panel archive-panel">


            <div class="panel-header">


                <h3>
                    Archived Records
                </h3>


                <span>
                    <?= count($archiveRecords) ?> RECORDS
                </span>


            </div>


            <div class="archive-toolbar">


                <div class="archive-filters">


                    <?php foreach (
                        $allowedCategories
                        as $category
                    ): ?>


                        <a
                            href="archive.php?category=<?= urlencode($category) ?>"
                            class="filter-pill <?= $categoryFilter === $category ? 'active' : '' ?>"
                        >

                            <?= htmlspecialchars(
                                $category
                            ) ?>

                        </a>


                    <?php endforeach; ?>


                </div>


            </div>


            <?php if (empty($archiveRecords)): ?>


                <div class="empty-state">


                    <div>
                        ◌
                    </div>


                    <p>
                        No archived records found for this category.
                    </p>


                </div>


            <?php else: ?>


                <div class="archive-table-wrapper">


                    <table class="archive-table">


                        <thead>


                            <tr>

                                <th>
                                    Record
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Deleted By
                                </th>

                                <th>
                                    Date
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


                            <?php foreach (
                                $archiveRecords
                                as $record
                            ): ?>


                                <tr>


                                    <td>

                                        <div class="record-name">

                                            <?= htmlspecialchars(
                                                $record["record_name"]
                                                ??
                                                (
                                                    "#"
                                                    .
                                                    (
                                                        $record["record_id"]
                                                        ??
                                                        "-"
                                                    )
                                                )
                                            ) ?>

                                        </div>


                                        <div class="record-meta">

                                            <?= htmlspecialchars(
                                                $record["module"]
                                                ??
                                                "OTHER"
                                            ) ?>


                                            <?php if (
                                                !empty(
                                                    $record["record_no"]
                                                )
                                            ): ?>

                                                •
                                                <?= htmlspecialchars(
                                                    $record["record_no"]
                                                ) ?>

                                            <?php endif; ?>


                                        </div>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $record["module"]
                                            ??
                                            "OTHER"
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $record[
                                                "deleted_by_username"
                                            ]
                                            ??
                                            "System"
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            date(
                                                "M d, Y h:i A",
                                                strtotime(
                                                    $record["deleted_at"]
                                                )
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <span
                                            class="archive-status archive-status-<?= strtolower(
                                                str_replace(
                                                    " ",
                                                    "-",
                                                    $record["status"]
                                                )
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $record["status"]
                                                ??
                                                "ARCHIVED"
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>


                                        <div class="archive-actions">


                                            <?php if (
                                                $canRestore
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="archive.php"
                                                    style="display:inline;"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="restore_archive_id"
                                                        value="<?= (int) $record["id"] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="archive-action-btn restore-btn"
                                                    >
                                                        RESTORE
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (
                                                $canDeletePermanently
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="archive.php"
                                                    class="inline-delete-form"
                                                    onsubmit="return confirm('Permanent delete this archive record? This action cannot be undone.');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="delete_archive_id"
                                                        value="<?= (int) $record["id"] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="archive-action-btn delete-btn"
                                                    >
                                                        DELETE
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                        </div>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </div>


    </section>


</main>
```

</div>

</body>

</html>
