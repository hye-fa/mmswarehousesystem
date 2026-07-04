<?php
// api/save_receiving.php
// UPDATED: Handling New Pallet Options

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('../config/db.php')) {
    die("❌ Configuration File Not Found.");
}
require_once '../config/db.php';

function convertDate($dateStr) {
    if (empty($dateStr)) return null;
    if (strpos($dateStr, '-') !== false) return $dateStr;
    $date = DateTime::createFromFormat('d/m/Y', $dateStr);
    return $date ? $date->format('Y-m-d') : null;
}

$category = $_POST['category'] ?? 'PSS';
$product_id = $_POST['product_id'] ?? 0;
$batch_no = $_POST['batch_no'] ?? '';
$expiry_raw = $_POST['expiry_date'] ?? null;
$expiry_date = convertDate($expiry_raw);
$qty = $_POST['qty'] ?? 0;
$pallet_id_tag = $_POST['pallet_id_tag'] ?? '';
$pallet_type = $_POST['pallet_type'] ?? 'No Pallet';
$temp_truck = $_POST['temp_truck'] ?? 0;
$temp_stock = $_POST['temp_stock'] ?? 0;

if ($qty <= 0 || empty($product_id)) {
    die("Error: Invalid Quantity or Product ID.");
}

try {
    $pdo->beginTransaction();

    // Pallet Logic (Single Item Mode = 1 Pallet usually)
    $qty_red = ($pallet_type == 'Loscam Red') ? 1 : 0;
    $qty_lhp_green = ($pallet_type == 'LHP Green') ? 1 : 0;
    $qty_ffm_orange = ($pallet_type == 'FFM Orange') ? 1 : 0;
    $qty_ffm_green = ($pallet_type == 'FFM Green') ? 1 : 0;
    $qty_black = ($pallet_type == 'Plastic Black') ? 1 : 0;
    
    // NOTE: Need to ensure database has columns for new pallet types if strictly tracking them separately in log
    // Or map them to generic 'green'/'orange' buckets. 
    // Assuming DB has: pallet_qty_loscam_red, pallet_qty_ffm_orange, pallet_qty_plastic_black
    // If not, update table structure or map appropriately.
    // For now, mapping broadly:
    
    $stmt = $pdo->prepare("INSERT INTO inbound_logs 
        (category, received_date, temp_truck, temp_stock, pallet_qty_loscam_red, pallet_qty_ffm_orange, pallet_qty_plastic_black) 
        VALUES (?, NOW(), ?, ?, ?, ?, ?)");
    
    // Mapping: LHP Green & FFM Green -> Grouped? Or just ignore others? 
    // Assuming standard tracking:
    $stmt->execute([$category, $temp_truck, $temp_stock, $qty_red, $qty_ffm_orange, $qty_black]);
    
    $inbound_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO inbound_items (inbound_id, product_id, batch_no, qty_received) VALUES (?, ?, ?, ?)");
    $stmt->execute([$inbound_id, $product_id, $batch_no, $qty]);

    $stmt = $pdo->prepare("INSERT INTO inventory_batches 
        (product_id, batch_no, expiry_date, qty_on_hand, pallet_type, pallet_id_tag, location_status) 
        VALUES (?, ?, ?, ?, ?, ?, 'Warehouse')");
    $stmt->execute([$product_id, $batch_no, $expiry_date, $qty, $pallet_type, $pallet_id_tag]);

    $pdo->commit();

    echo "<script>alert('✅ Stock Received Successfully! (Inbound ID: $inbound_id)'); window.location.href='../receiving.php';</script>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Database Error: " . $e->getMessage());
}
?>