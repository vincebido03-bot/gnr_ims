<?php

function acquireNumberingLock(PDO $pdo, string $lockName): void
{
    $statement = $pdo->prepare("SELECT GET_LOCK(?, 10)");
    $statement->execute([$lockName]);
    if ((int) $statement->fetchColumn() !== 1) {
        throw new RuntimeException("Unable to reserve the next record number. Please try again.");
    }
}

function releaseNumberingLock(PDO $pdo, string $lockName): void
{
    $statement = $pdo->prepare("SELECT RELEASE_LOCK(?)");
    $statement->execute([$lockName]);
}

function nextCustomerNumber(PDO $pdo): string
{
    $lastNumber = (int) $pdo->query("SELECT MAX(CAST(SUBSTRING(customer_no, 2) AS UNSIGNED)) FROM customers WHERE customer_no REGEXP '^C[0-9]+$'")->fetchColumn();
    return "C" . str_pad((string) ($lastNumber + 1), 3, "0", STR_PAD_LEFT);
}

function nextVehicleNumber(PDO $pdo): string
{
    $lastNumber = (int) $pdo->query("SELECT MAX(CAST(SUBSTRING(vehicle_no, 2) AS UNSIGNED)) FROM vehicles WHERE vehicle_no REGEXP '^B[0-9]+$'")->fetchColumn();
    return "B" . str_pad((string) ($lastNumber + 1), 3, "0", STR_PAD_LEFT);
}

function nextOrderNumber(PDO $pdo, int $year): string
{
    $statement = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(order_no, '-', -1) AS UNSIGNED)) FROM orders WHERE order_no REGEXP ?");
    $statement->execute(["^JO-" . $year . "-[0-9]+$"]);
    $lastNumber = (int) $statement->fetchColumn();
    return "JO-" . $year . "-" . str_pad((string) ($lastNumber + 1), 4, "0", STR_PAD_LEFT);
}