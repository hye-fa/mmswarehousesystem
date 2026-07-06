<?php
// api/save_stock_transfer.php
// Proses pindahan stok antara lokasi
// MMS Warehouse System | Moo Moo Supplies

header('Content-Type: application/json');

require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi tamat. Sila log masuk semula.']);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Kaedah tidak sah.']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input) || !isset($input['items']) || !isset($input['destination'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$items        = $input['items'];
$destination  = trim($input['destination']);
$from_loc     = trim($input['from_location'] ?? '');
$reason       = trim($input['reason'] ?? '');
$username     = $_SESSION['username'] ?? 'System';
$user_id      = $_SESSION['user_id'] ?? null;

// Validate destination
$valid_locs = ['Warehouse', 'Buffer', 'Shop', 'Damaged'];
if (!in_array($destination, $valid_locs)) {
    echo json_encode(['success' => false, 'message' => 'Lokasi destinasi tidak sah.']);
    exit;
}
if (!in_array($from_loc, $valid_locs)) {
    echo json_encode(['success' => false, 'message' => 'Lokasi sumber tidak sah.']);
    exit;
}
if ($destination === $from_loc) {
    echo json_encode(['success' => false, 'message' => 'Lokasi sumber dan destinasi tidak boleh sama.']);
    exit;
}

$transferred = 0;
$errors      = [];

try {
    $pdo->beginTransaction();

    foreach ($items as $item) {
        $batch_id = (int)($item['batch_id'] ?? 0);
        $qty      = (int)($item['qty'] ?? 0);

        if ($batch_id <= 0 || $qty <= 0) {
            $errors[] = "Batch #$batch_id: kuantiti tidak sah.";
            continue;
        }

        // Semak batch wujud & cukup stok
        $stmtCheck = $pdo->prepare("SELECT id, qty_on_hand, location_status, product_id FROM inventory_batches WHERE id = ? FOR UPDATE");
        $stmtCheck->execute([$batch_id]);
        $batch = $stmtCheck->fetch();

        if (!$batch) {
            $errors[] = "Batch #$batch_id tidak dijumpai.";
            continue;
        }
        if ($batch['location_status'] !== $from_loc) {
            $errors[] = "Batch #$batch_id bukan di lokasi '$from_loc' (sebenar: {$batch['location_status']}).";
            continue;
        }
        if ($qty > $batch['qty_on_hand']) {
            $errors[] = "Batch #$batch_id: kuantiti ({$qty}) melebihi stok ({$batch['qty_on_hand']}).";
            continue;
        }

        $remaining = $batch['qty_on_hand'] - $qty;

        if ($remaining === 0) {
            // Pindah semua — update lokasi batch sedia ada
            $stmtUpdate = $pdo->prepare("UPDATE inventory_batches SET location_status = ? WHERE id = ?");
            $stmtUpdate->execute([$destination, $batch_id]);
        } else {
            // Pindah sebahagian:
            // 1. Kurangkan stok batch asal
            $stmtDeduct = $pdo->prepare("UPDATE inventory_batches SET qty_on_hand = qty_on_hand - ? WHERE id = ?");
            $stmtDeduct->execute([$qty, $batch_id]);

            // 2. Buat batch baru di lokasi destinasi (copy dari batch asal)
            $stmtOld = $pdo->prepare("SELECT * FROM inventory_batches WHERE id = ?");
            $stmtOld->execute([$batch_id]);
            $oldBatch = $stmtOld->fetch();

            $stmtNew = $pdo->prepare("
                INSERT INTO inventory_batches
                (product_id, batch_no, lot_no_raw, expiry_date, production_date, qty_on_hand, pallet_type, pallet_id_tag, location_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtNew->execute([
                $oldBatch['product_id'],
                $oldBatch['batch_no'],
                $oldBatch['lot_no_raw'],
                $oldBatch['expiry_date'],
                $oldBatch['production_date'],
                $qty,
                $oldBatch['pallet_type'],
                $oldBatch['pallet_id_tag'],
                $destination
            ]);
        }

        // Log aktiviti
        $log_details = "Pindah $qty ctn dari $from_loc ke $destination" . ($reason ? " | Sebab: $reason" : '');
        if (function_exists('log_system_activity')) {
            log_system_activity(
                "Stock Transfer",
                "inventory_batches",
                $batch_id,
                $log_details
            );
        }

        $transferred++;
    }

    $pdo->commit();

    if ($transferred === 0) {
        echo json_encode(['success' => false, 'message' => 'Tiada rekod dipindah. ' . implode(' ', $errors)]);
    } else {
        $msg = "$transferred batch berjaya dipindah ke $destination.";
        if (!empty($errors)) {
            $msg .= ' (Ralat: ' . implode(', ', $errors) . ')';
        }
        echo json_encode(['success' => true, 'message' => $msg, 'transferred' => $transferred]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Stock Transfer Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ralat sistem: ' . $e->getMessage()]);
}
