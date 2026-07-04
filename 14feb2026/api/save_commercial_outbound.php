<?php
// api/save_commercial_outbound.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

try {
    $pdo->beginTransaction();

    $date = $_POST['out_date'];
    $customer = $_POST['customer_name'];
    $ref = $_POST['doc_ref'];
    $vehicle = $_POST['vehicle'];
    $items = $_POST['items'] ?? [];

    // 1. Create Header
    $stmt = $pdo->prepare("INSERT INTO outbound_logs (date, customer, doc_ref, vehicle, category) VALUES (?, ?, ?, ?, 'Commercial')");
    $stmt->execute([$date, $customer, $ref, $vehicle]);
    $out_id = $pdo->lastInsertId();

    // 2. Process Items
    foreach ($items as $item) {
        $pid = $item['product_id'];
        $qty = $item['qty'];
        $batch = $item['batch'];

        if ($qty > 0) {
            // Record Line Item
            $stmtItem = $pdo->prepare("INSERT INTO outbound_items (outbound_id, product_id, qty, batch) VALUES (?, ?, ?, ?)");
            $stmtItem->execute([$out_id, $pid, $qty, $batch]);

            // Deduct Stock (Simple logic: deduct from any available batch if batch not specified)
            // For strict batch tracking, you'd need more complex logic here.
            // Assuming simple deduction for now:
            $stmtStock = $pdo->prepare("UPDATE inventory_batches SET qty_on_hand = qty_on_hand - ? WHERE product_id = ? AND qty_on_hand > 0 LIMIT 1");
            $stmtStock->execute([$qty, $pid]);
        }
    }

    $pdo->commit();
    echo "<script>alert('Outbound Recorded!'); window.location.href='../commercial_outbound.php';</script>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>