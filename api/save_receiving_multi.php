<?php
// api/save_receiving_multi.php
// UPDATED: Saving FFM Green & LHP Green to dedicated columns

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

try {
    $pdo->beginTransaction();

    // HEADER INFO
    $supplier_do = $_POST['supplier_do'] ?? '';
    $po_number   = $_POST['po_number'] ?? '';
    $category    = $_POST['category'] ?? 'PST';
    
    $recv_date = convertDate($_POST['received_date'] ?? date('d/m/Y'));
    $ordered_date = convertDate($_POST['ordered_date'] ?? '');
    
    // LOGISTICS
    $transporter = $_POST['transporter_name'] ?? '';
    $driver      = $_POST['driver_name'] ?? '';
    $plate       = strtoupper($_POST['vehicle_plate'] ?? '');
    $arrival     = $_POST['arrival_time'] ?? '';

    // PALLET TALLY (Manual)
    $man_red    = (int)($_POST['manual_qty_red'] ?? 0);
    $man_lhp    = (int)($_POST['manual_qty_lhp_green'] ?? 0);
    $man_ffm    = (int)($_POST['manual_qty_ffm_green'] ?? 0);
    $man_orange = (int)($_POST['manual_qty_orange'] ?? 0);
    $man_black  = (int)($_POST['manual_qty_black'] ?? 0);
    $man_plain  = (int)($_POST['manual_qty_plain'] ?? 0);

    // SUM ROW PALLETS
    $items = $_POST['items'] ?? [];
    $row_red = 0; $row_lhp = 0; $row_ffm = 0; $row_orange = 0; $row_black = 0; $row_plain = 0;

    foreach ($items as $item) {
        $type = $item['pallet_type'] ?? '';
        $qty  = (int)($item['pallet_qty'] ?? 0);
        if ($type === 'Loscam Red') $row_red += $qty;
        if ($type === 'LHP Green') $row_lhp += $qty;
        if ($type === 'FFM Green') $row_ffm += $qty;
        if ($type === 'FFM Orange') $row_orange += $qty;
        if ($type === 'Plastic Black') $row_black += $qty;
        if ($type === 'Plain') $row_plain += $qty;
    }

    // TOTALS
    $total_red    = $man_red + $row_red;
    $total_orange = $man_orange + $row_orange;
    $total_black  = $man_black + $row_black;
    $total_ffm    = $man_ffm + $row_ffm;    // NEW
    $total_lhp    = $man_lhp + $row_lhp;    // NEW
    
    $pallet_remarks = "PO: $po_number";
    if (($man_plain + $row_plain) > 0) $pallet_remarks .= " | Plain: " . ($man_plain + $row_plain);

    // INSERT LOG (Added Green Columns)
    $stmt = $pdo->prepare("INSERT INTO inbound_logs 
        (category, received_date, supplier_do, remarks, 
         pallet_qty_loscam_red, pallet_qty_ffm_orange, pallet_qty_plastic_black,
         pallet_qty_ffm_green, pallet_qty_lhp_green,
         transporter_name, driver_name, vehicle_plate, arrival_time) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
    $stmt->execute([
        $category, $recv_date, $supplier_do, $pallet_remarks, 
        $total_red, $total_orange, $total_black, 
        $total_ffm, $total_lhp,
        $transporter, $driver, $plate, $arrival
    ]);
    $inbound_id = $pdo->lastInsertId();

    // INSERT ITEMS
    if (empty($items)) throw new Exception("No items found.");
    $count = 0;

    foreach ($items as $item) {
        $prod_id   = $item['product_id'];
        $batch     = $item['batch_no'];
        $qty       = $item['qty'];
        $prod_time = !empty($item['production_time']) ? $item['production_time'] : null;
        $expiry    = convertDate($item['expiry_date']);
        $p_type    = $item['pallet_type'] ?? 'No Pallet';

        if ($qty > 0 && !empty($prod_id)) {
            // A. Add to HISTORY
            $stmtItem = $pdo->prepare("INSERT INTO inbound_items 
                (inbound_id, product_id, batch_no, qty_received, ordered_date, production_time, expiry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtItem->execute([$inbound_id, $prod_id, $batch, $qty, $ordered_date, $prod_time, $expiry]);

            // B. Add to STOCK
            $stmtStock = $pdo->prepare("INSERT INTO inventory_batches 
                (product_id, batch_no, expiry_date, qty_on_hand, location_status, pallet_type) 
                VALUES (?, ?, ?, ?, 'Warehouse', ?)");
            $stmtStock->execute([$prod_id, $batch, $expiry, $qty, $p_type]);
            
            $count++;
        }
    }

    // Rekod log audit sistem
    log_system_activity("Received Inbound Stock", "inbound_logs", $inbound_id, "Stok diterima: GRN ID $inbound_id, Supplier DO $supplier_do ($count jenis item).");

    $pdo->commit();

    echo "<script>
        alert('✅ Successfully Received $count Items! (GRN ID: $inbound_id)');
        window.location.href='../receiving_multi.php';
    </script>";

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Error Saving Data: " . $e->getMessage());
}
?>