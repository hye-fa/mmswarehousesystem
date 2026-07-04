<?php
header('Content-Type: application/json');
require_once '../config/db.php';

try {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $submit_date = !empty($_POST['submit_date']) ? $_POST['submit_date'] : null;
    $cn_num = !empty($_POST['cn_num']) ? $_POST['cn_num'] : null;
    $cn_date = !empty($_POST['cn_date']) ? $_POST['cn_date'] : null;

    $stmt = $pdo->prepare("UPDATE spoilage_logs SET 
        claim_status = ?, 
        supplier_submitted_at = ?, 
        cn_number = ?, 
        cn_date = ? 
        WHERE id = ?");

    if ($stmt->execute([$status, $submit_date, $cn_num, $cn_date, $id])) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("Database update failed.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}