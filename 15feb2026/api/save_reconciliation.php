<?php
// api/save_reconciliation.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

$date = $_POST['date'];
$category = $_POST['category'] ?? 'Commercial'; // Default to Commercial
$sys_qty  = $_POST['system_qty'] ?? 0;
$inv_qty  = $_POST['invoice_qty'] ?? 0;
$inv_nos  = $_POST['invoice_nos'] ?? '';
$reason   = $_POST['reason'] ?? '';
$variance = $sys_qty - $inv_qty;

try {
    $sql = "INSERT INTO daily_reconciliation 
            (date, category, system_qty_cartons, invoice_qty_cartons, invoice_numbers, variance, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            system_qty_cartons = VALUES(system_qty_cartons),
            invoice_qty_cartons = VALUES(invoice_qty_cartons),
            invoice_numbers = VALUES(invoice_numbers),
            variance = VALUES(variance),
            reason = VALUES(reason)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date, $category, $sys_qty, $inv_qty, $inv_nos, $variance, $reason]);

    header("Location: ../reconcile.php?date=$date&success=1");

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>