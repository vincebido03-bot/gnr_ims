<?php

require_once __DIR__ . "/../config/database.php";

function xlsxRows($filePath, $wantedSheet)
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException("Unable to open {$filePath}");
    }

    $workbook = simplexml_load_string($zip->getFromName("xl/workbook.xml"));
    $relations = simplexml_load_string($zip->getFromName("xl/_rels/workbook.xml.rels"));
    $targets = [];
    foreach ($relations->xpath("//*[local-name()='Relationship']") as $relation) {
        $targets[(string) $relation["Id"]] = (string) $relation["Target"];
    }

    $sharedStrings = [];
    if ($zip->locateName("xl/sharedStrings.xml") !== false) {
        $shared = simplexml_load_string($zip->getFromName("xl/sharedStrings.xml"));
        foreach ($shared->xpath("//*[local-name()='si']") as $item) {
            $text = "";
            foreach ($item->xpath(".//*[local-name()='t']") as $part) {
                $text .= (string) $part;
            }
            $sharedStrings[] = $text;
        }
    }

    $sheetTarget = null;
    foreach ($workbook->xpath("//*[local-name()='sheet']") as $sheet) {
        if ((string) $sheet["name"] === $wantedSheet) {
            $relationshipId = (string) $sheet->attributes("http://schemas.openxmlformats.org/officeDocument/2006/relationships")["id"];
            $sheetTarget = "xl/" . ltrim($targets[$relationshipId], "/");
            break;
        }
    }

    if (!$sheetTarget || $zip->locateName($sheetTarget) === false) {
        $zip->close();
        return [];
    }

    $sheetXml = simplexml_load_string($zip->getFromName($sheetTarget));
    $rows = [];

    foreach ($sheetXml->xpath("//*[local-name()='sheetData']/*[local-name()='row']") as $row) {
        $values = [];
        foreach ($row->xpath("./*[local-name()='c']") as $cell) {
            $reference = (string) $cell["r"];
            preg_match('/^[A-Z]+/', $reference, $match);
            $column = 0;
            foreach (str_split($match[0] ?? "A") as $letter) {
                $column = ($column * 26) + ord($letter) - 64;
            }
            $value = "";
            $type = (string) $cell["t"];
            if ($type === "s") {
                $value = $sharedStrings[(int) $cell->v] ?? "";
            } elseif ($type === "inlineStr") {
                foreach ($cell->xpath(".//*[local-name()='t']") as $text) {
                    $value .= (string) $text;
                }
            } else {
                $value = (string) $cell->v;
            }
            $values[$column] = trim($value);
        }
        $rows[] = $values;
    }

    $zip->close();
    return $rows;
}

function headerRow(array $rows, array $needles)
{
    foreach ($rows as $index => $row) {
        $joined = strtolower(implode(" | ", $row));
        $matches = true;
        foreach ($needles as $needle) {
            if (strpos($joined, strtolower($needle)) === false) {
                $matches = false;
                break;
            }
        }
        if ($matches) {
            $columns = [];
            foreach ($row as $column => $value) {
                $columns[strtolower(trim($value))] = $column;
            }
            return [$index, $columns];
        }
    }
    return [null, []];
}

function cell(array $row, array $columns, string $name)
{
    $column = $columns[strtolower($name)] ?? null;
    return $column === null ? "" : trim((string) ($row[$column] ?? ""));
}

function excelDate($value)
{
    if (!is_numeric($value) || (float) $value <= 0) {
        return null;
    }
    return date("Y-m-d", strtotime("1899-12-30") + (int) round((float) $value * 86400));
}

function excelDateTime($value)
{
    if (!is_numeric($value) || (float) $value <= 0) {
        return null;
    }
    return date("Y-m-d H:i:s", strtotime("1899-12-30") + (int) round((float) $value * 86400));
}

function numberValue($value)
{
    $value = str_replace([",", "PHP", "₱"], "", (string) $value);
    return is_numeric(trim($value)) ? (float) trim($value) : 0;
}

$base = dirname(__DIR__) . "/sheets_to_read/";
$result = ["customers" => 0, "vehicles" => 0, "orders" => 0, "inventory" => 0, "movements" => 0, "labor" => 0];

$customerRows = xlsxRows($base . "GNR JOB ORDER & SERVICE MANAGEMENT — 2026.xlsx", "CUSTOMERS AND BIKES");
[$headerIndex, $columns] = headerRow($customerRows, ["Customer ID", "Customer Name", "Motorcycle ID"]);
if ($headerIndex !== null) {
    $customerIds = [];
    foreach (array_slice($customerRows, $headerIndex + 1) as $row) {
        $sourceId = cell($row, $columns, "customer id");
        $name = cell($row, $columns, "customer name");
        if ($sourceId === "" || $name === "") continue;
        $contact = cell($row, $columns, "contact");
        $facebook = cell($row, $columns, "fb/email");
        $stmt = $pdo->prepare("INSERT INTO customers (customer_no, fullname, contact_no, facebook, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE fullname=VALUES(fullname), contact_no=VALUES(contact_no), facebook=VALUES(facebook)");
        $stmt->execute([$sourceId, $name, $contact ?: null, $facebook !== "N/A" ? $facebook : null, "Imported from job management workbook"]);
        $customerId = $pdo->lastInsertId();
        if (!$customerId) { $find = $pdo->prepare("SELECT id FROM customers WHERE customer_no = ?"); $find->execute([$sourceId]); $customerId = $find->fetchColumn(); }
        $customerIds[$sourceId] = $customerId;
        $result["customers"]++;

        $brand = cell($row, $columns, "brand");
        $model = cell($row, $columns, "model");
        $plate = cell($row, $columns, "plate no.");
        if ($brand !== "" || $model !== "" || $plate !== "") {
            $check = $pdo->prepare("SELECT id FROM vehicles WHERE customer_id = ? AND COALESCE(brand, '') = ? AND COALESCE(model, '') = ? LIMIT 1");
            $check->execute([$customerId, $brand, $model]);
            if (!$check->fetchColumn()) {
                $vehicle = $pdo->prepare("INSERT INTO vehicles (customer_id, brand, model, plate_no, notes) VALUES (?, ?, ?, ?, ?)");
                $vehicle->execute([$customerId, $brand ?: null, $model ?: null, $plate ?: null, "Imported motorcycle record"]);
                $result["vehicles"]++;
            }
        }
    }
}

$inventoryRows = xlsxRows($base . "GNR JOB ORDER & SERVICE MANAGEMENT — 2026.xlsx", "INVENTORY MASTER");
[$headerIndex, $columns] = headerRow($inventoryRows, ["SKU", "Item", "Unit Cost", "Current Stock"]);
if ($headerIndex !== null) {
    foreach (array_slice($inventoryRows, $headerIndex + 1) as $row) {
        $code = cell($row, $columns, "sku");
        $name = cell($row, $columns, "item");
        if ($code === "" || $name === "") continue;
        $category = cell($row, $columns, "category");
        $categoryId = null;
        if ($category !== "") {
            $cat = $pdo->prepare("INSERT INTO inventory_categories (category_name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $cat->execute([$category]);
            $categoryId = $pdo->lastInsertId();
        }
        $stmt = $pdo->prepare("INSERT INTO inventory_items (item_code, item_name, category_id, unit, current_stock, reorder_level, unit_cost, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE item_name=VALUES(item_name), current_stock=VALUES(current_stock), reorder_level=VALUES(reorder_level), unit_cost=VALUES(unit_cost), category_id=VALUES(category_id)");
        $status = strtoupper(cell($row, $columns, "status")) === "INACTIVE" ? "INACTIVE" : "ACTIVE";
        $stmt->execute([$code, $name, $categoryId ?: null, cell($row, $columns, "unit") ?: "pcs", numberValue(cell($row, $columns, "current stock")), numberValue(cell($row, $columns, "reorder level")), numberValue(cell($row, $columns, "unit cost")), $status, "Imported from inventory master"]);
        $result["inventory"]++;
    }
}

$orderRows = xlsxRows($base . "GNR JOB ORDER & SERVICE MANAGEMENT — 2026.xlsx", "JOB ORDERS");
[$headerIndex, $columns] = headerRow($orderRows, ["Job Order ID", "Customer ID", "Total", "Payment Status"]);
if ($headerIndex !== null) {
    foreach (array_slice($orderRows, $headerIndex + 1) as $row) {
        $orderNo = cell($row, $columns, "job order id");
        $sourceCustomerId = cell($row, $columns, "customer id");
        if ($orderNo === "" || $sourceCustomerId === "" || empty($customerIds[$sourceCustomerId])) continue;

        $customerId = $customerIds[$sourceCustomerId];
        $orderStatus = strtoupper(cell($row, $columns, "status"));
        $statusMap = ["RELEASED" => "COMPLETED", "COMPLETED" => "COMPLETED", "IN PROGRESS" => "IN_PROGRESS", "CANCELLED" => "CANCELLED"];
        $mappedStatus = $statusMap[$orderStatus] ?? "PENDING";
        $orderDate = excelDate(cell($row, $columns, "date")) ?: date("Y-m-d");
        $subtotal = numberValue(cell($row, $columns, "subtotal"));
        $discount = numberValue(cell($row, $columns, "discount"));
        $total = numberValue(cell($row, $columns, "total"));
        $brandModel = cell($row, $columns, "brand/model");
        $vehicleId = null;
        if ($brandModel !== "") {
            $vehicleStmt = $pdo->prepare("SELECT id FROM vehicles WHERE customer_id = ? AND LOWER(CONCAT(COALESCE(brand, ''), ' ', COALESCE(model, ''))) LIKE LOWER(?) ORDER BY id ASC LIMIT 1");
            $vehicleStmt->execute([$customerId, "%{$brandModel}%"]);
            $vehicleId = $vehicleStmt->fetchColumn() ?: null;
            if (!$vehicleId) {
                $vehicleStmt = $pdo->prepare("SELECT id FROM vehicles WHERE customer_id = ? ORDER BY id ASC LIMIT 1");
                $vehicleStmt->execute([$customerId]);
                $vehicleId = $vehicleStmt->fetchColumn() ?: null;
            }
            if (!$vehicleId) {
                $parts = preg_split('/\s+/', $brandModel, 2);
                $vehicleInsert = $pdo->prepare("INSERT INTO vehicles (customer_id, brand, model, notes) VALUES (?, ?, ?, ?)");
                $vehicleInsert->execute([$customerId, $parts[0] ?? $brandModel, $parts[1] ?? null, "Created from imported order motorcycle details"]);
                $vehicleId = $pdo->lastInsertId();
            }
        }

        $stmt = $pdo->prepare("INSERT INTO orders (order_no, customer_id, vehicle_id, order_date, subtotal, discount, total_amount, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id), vehicle_id=VALUES(vehicle_id), order_date=VALUES(order_date), subtotal=VALUES(subtotal), discount=VALUES(discount), total_amount=VALUES(total_amount), status=VALUES(status), remarks=VALUES(remarks)");
        $stmt->execute([$orderNo, $customerId, $vehicleId, $orderDate, $subtotal, $discount, $total, $mappedStatus, cell($row, $columns, "notes") ?: "Imported from job orders workbook"]);
        $find = $pdo->prepare("SELECT id FROM orders WHERE order_no = ?");
        $find->execute([$orderNo]);
        $orderId = $find->fetchColumn();
        $result["orders"]++;

        $jobType = cell($row, $columns, "job type") ?: "Imported service job";
        $jobNo = $orderNo . "-JOB";
        $jobStatus = $mappedStatus === "COMPLETED" ? "COMPLETED" : ($mappedStatus === "CANCELLED" ? "CANCELLED" : "PENDING");
        $technician = cell($row, $columns, "technician");
        $jobStmt = $pdo->prepare("INSERT INTO jobs (job_no, order_id, job_title, description, priority, status, scheduled_end, assigned_staff_name) VALUES (?, ?, ?, ?, 'NORMAL', ?, ?, ?) ON DUPLICATE KEY UPDATE job_title=VALUES(job_title), status=VALUES(status), scheduled_end=VALUES(scheduled_end), assigned_staff_name=VALUES(assigned_staff_name)");
        $jobStmt->execute([$jobNo, $orderId, $jobType, cell($row, $columns, "notes") ?: "Imported job order", $jobStatus, excelDate(cell($row, $columns, "target date")), $technician ?: null]);
        $jobFind = $pdo->prepare("SELECT id FROM jobs WHERE job_no = ?");
        $jobFind->execute([$jobNo]);
        $jobId = $jobFind->fetchColumn();

        $paymentStatus = strtoupper(cell($row, $columns, "payment status"));
        if ($total > 0) {
            $invoiceNo = "INV-" . $orderNo;
            $amountPaid = numberValue(cell($row, $columns, "amount paid"));
            $balance = numberValue(cell($row, $columns, "balance"));
            $invoiceStatus = $paymentStatus === "PAID" ? "PAID" : ($amountPaid > 0 ? "PARTIALLY_PAID" : "ISSUED");
            $invoiceStmt = $pdo->prepare("INSERT INTO invoices (invoice_no, order_id, customer_id, subtotal, discount, total_amount, amount_paid, balance, status, issued_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_amount=VALUES(total_amount), amount_paid=VALUES(amount_paid), balance=VALUES(balance), status=VALUES(status)");
            $invoiceStmt->execute([$invoiceNo, $orderId, $customerId, $subtotal, $discount, $total, $amountPaid, $balance, $invoiceStatus, $orderDate . " 00:00:00"]);
            $invoiceFind = $pdo->prepare("SELECT id FROM invoices WHERE invoice_no = ?");
            $invoiceFind->execute([$invoiceNo]);
            $invoiceId = $invoiceFind->fetchColumn();
            if ($amountPaid > 0) {
                $paymentNo = "PAY-" . $orderNo;
                $method = strtoupper(cell($row, $columns, "payment method"));
                $method = in_array($method, ["CASH", "GCASH", "BANK_TRANSFER", "CARD", "OTHER"], true) ? $method : "OTHER";
                $paymentStmt = $pdo->prepare("INSERT INTO payments (payment_no, order_id, invoice_id, payment_method, amount, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE amount=VALUES(amount), payment_method=VALUES(payment_method)");
                $paymentStmt->execute([$paymentNo, $orderId, $invoiceId, $method, $amountPaid, $orderDate . " 00:00:00", "Imported from job orders workbook"]);
            }
            if ($mappedStatus === "COMPLETED") {
                $releaseStaff = cell($row, $columns, "technician") ?: cell($row, $columns, "service advisor");
                $releaseStmt = $pdo->prepare("INSERT INTO releases (release_no, order_id, customer_id, vehicle_id, release_date, released_by_name, payment_verified, qc_verified, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'RELEASED', ?) ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id), vehicle_id=VALUES(vehicle_id), released_by_name=VALUES(released_by_name), payment_verified=VALUES(payment_verified), qc_verified=VALUES(qc_verified), status=VALUES(status)");
                $releaseStmt->execute(["REL-" . $orderNo, $orderId, $customerId, $vehicleId, $orderDate . " 00:00:00", $releaseStaff ?: null, $paymentStatus === "PAID" ? 1 : 0, strtoupper(cell($row, $columns, "qc status")) === "PASSED" ? 1 : 0, "Imported release record"]);
            }
        }
    }
}

$productRows = xlsxRows($base . "GNR BOLTS PER PRODUCT.xlsx", "GNR PRODUCTS DESCRIPTION");
[$headerIndex, $columns] = headerRow($productRows, ["PRODUCT CODE", "PRODUCT DESCRIPTION", "COMPATIBILITY"]);
if ($headerIndex !== null) {
    foreach (array_slice($productRows, $headerIndex + 1) as $row) {
        $code = cell($row, $columns, "product code");
        $name = cell($row, $columns, "product");
        if ($code === "" || $name === "") continue;
        $description = cell($row, $columns, "product description");
        $compatibility = cell($row, $columns, "compatibility");
        $stmt = $pdo->prepare("INSERT INTO products (product_code, product_name, description, compatibility, selling_price) VALUES (?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE product_name=VALUES(product_name), description=VALUES(description), compatibility=VALUES(compatibility)");
        $stmt->execute([$code, $name, $description ?: null, $compatibility ?: null]);
    }
}

$movementRows = xlsxRows($base . "GNR INVENTORY — STOCK MOVEMENT V3.xlsx", "Form Responses 1");
[$headerIndex, $columns] = headerRow($movementRows, ["Timestamp", "Movement Type", "Item", "Quantity"]);
if ($headerIndex !== null) {
    foreach (array_slice($movementRows, $headerIndex + 1) as $row) {
        $itemName = cell($row, $columns, "item");
        if ($itemName === "") continue;
        $itemStmt = $pdo->prepare("SELECT id, unit_cost FROM inventory_items WHERE LOWER(item_name) = LOWER(?) LIMIT 1");
        $itemStmt->execute([$itemName]);
        $item = $itemStmt->fetch();
        if (!$item) continue;
        $movementText = strtoupper(cell($row, $columns, "movement type"));
        $movementType = strpos($movementText, "STOCK IN") !== false ? "STOCK_IN" : "STOCK_OUT";
        preg_match('/[0-9]+(?:\.[0-9]+)?/', cell($row, $columns, "quantity"), $quantityMatch);
        $quantity = numberValue($quantityMatch[0] ?? "1");
        if ($quantity <= 0) $quantity = 1;
        $referenceNo = cell($row, $columns, "receipt / j.o no.") ?: null;
        $timestamp = cell($row, $columns, "timestamp");
        $createdAt = excelDateTime($timestamp);
        $notes = "Imported stock movement: " . cell($row, $columns, "notes/price");
        $check = $pdo->prepare("SELECT id FROM stock_movements WHERE inventory_item_id = ? AND movement_type = ? AND quantity = ? AND COALESCE(reference_no, '') = COALESCE(?, '') AND notes LIKE 'Imported stock movement:%' LIMIT 1");
        $check->execute([$item["id"], $movementType, $quantity, $referenceNo]);
        $existingMovementId = $check->fetchColumn();
        if ($existingMovementId) {
            $stmt = $pdo->prepare("UPDATE stock_movements SET created_at = ? WHERE id = ?");
            $stmt->execute([$createdAt ?: date("Y-m-d H:i:s"), $existingMovementId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO stock_movements (inventory_item_id, movement_type, quantity, reference_no, unit_cost, total_cost, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, COALESCE(?, NOW()))");
            $stmt->execute([$item["id"], $movementType, $quantity, $referenceNo, $item["unit_cost"], $item["unit_cost"] * $quantity, $notes, $createdAt]);
            $result["movements"]++;
        }
    }
}

$laborRows = xlsxRows($base . "GNR SHOP LABOR LIST.xlsx", "SHOP LABOR LIST");
[$headerIndex, $columns] = headerRow($laborRows, ["labor"]);
if ($headerIndex !== null) {
    foreach (array_slice($laborRows, $headerIndex + 1) as $row) {
        $name = cell($row, $columns, "fabrications:");
        if ($name === "") { $name = cell($row, $columns, "labor"); }
        $rate = numberValue($row[2] ?? ($row[1] ?? 0));
        if ($name === "" || $rate <= 0) continue;
        $check = $pdo->prepare("SELECT id FROM labor_rates WHERE labor_name = ? LIMIT 1");
        $check->execute([$name]);
        if (!$check->fetchColumn()) {
            $stmt = $pdo->prepare("INSERT INTO labor_rates (labor_name, rate_type, default_rate, notes) VALUES (?, 'PER_JOB', ?, ?)");
            $stmt->execute([$name, $rate, "Imported from shop labor list"]);
            $result["labor"]++;
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;