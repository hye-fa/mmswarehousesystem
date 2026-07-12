<?php
// api_pss.php - PSS System Lama Backend API
header('Content-Type: application/json');

require_once 'config/db.php';

try {
    // Switch connection context to susumura_mms_logistik
    $pdo->exec("USE susumura_mms_logistik");
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Gagal menyambung ke database susumura_mms_logistik: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';

// --- 1. GET VEHICLES ---
if ($action == 'get_vehicles') {
    $dealer = $_GET['dealer'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE owner = ? OR owner = 'admin' ORDER BY id ASC");
    $stmt->execute([$dealer]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- 2. SAVE VEHICLES ---
if ($action == 'save_vehicles_global') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data)) {
        $owner = $data[0]['owner'];
        $pdo->prepare("DELETE FROM vehicles WHERE owner = ?")->execute([$owner]);
        $stmt = $pdo->prepare("INSERT INTO vehicles (v_name, v_capacity, owner, is_enabled, v_priority) VALUES (?, ?, ?, 1, 1)");
        foreach ($data as $v) {
            $stmt->execute([$v['v_name'], $v['v_capacity'], $v['owner']]);
        }
        echo json_encode(['status' => 'success']);
    }
    exit;
}

// --- 3. GET SCHOOLS ---
if ($action == 'get_schools') {
    $dealer = $_GET['dealer'] ?? '';
    $role = $_GET['role'] ?? '';
    if ($role == 'admin' || $role == 'staff') {
        $stmt = $pdo->query("SELECT * FROM mms_logistik ORDER BY co_no DESC, plan_date ASC, name ASC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM mms_logistik WHERE dealer = ? ORDER BY co_no DESC, plan_date ASC, name ASC");
        $stmt->execute([$dealer]);
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- 4. SAVE SCHOOLS (Composite Key Support) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action == 'save_schools' || $action == '')) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data)) {
        foreach ($data as $s) {
            $stmt = $pdo->prepare("INSERT INTO mms_logistik (id, name, district, dealer, co_no, plan_date, delivery_date, doc_signed_date, totalCartons, extraPacks, isDelivered, isDocSigned) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                plan_date=VALUES(plan_date), 
                delivery_date=VALUES(delivery_date), 
                doc_signed_date=VALUES(doc_signed_date), 
                totalCartons=VALUES(totalCartons),
                extraPacks=VALUES(extraPacks),
                isDelivered=VALUES(isDelivered), 
                isDocSigned=VALUES(isDocSigned)");
            $stmt->execute([
                $s['id'], $s['name'], $s['district'], $s['dealer'], $s['co_no'], 
                $s['plan_date'] ?: null, 
                !empty($s['delivery_date']) ? $s['delivery_date'] : null, 
                !empty($s['doc_signed_date']) ? $s['doc_signed_date'] : null, 
                $s['totalCartons'], $s['extraPacks'], 
                $s['isDelivered'] ? 1 : 0, 
                $s['isDocSigned'] ? 1 : 0
            ]);
        }
        echo json_encode(['status' => 'success']);
    }
    exit;
}

// --- 5. START NEW CO CYCLE ---
if ($action == 'start_new_co') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $newCo = trim($data['new_co'] ?? '');
    
    if (empty($newCo)) {
        echo json_encode(['status' => 'error', 'message' => 'Nama CO tidak boleh kosong.']);
        exit;
    }
    
    // Check if CO already exists
    $check = $pdo->prepare("SELECT COUNT(*) FROM mms_logistik WHERE co_no = ?");
    $check->execute([$newCo]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => "Kitaran '$newCo' sudah wujud dalam sistem."]);
        exit;
    }
    
    // Find latest CO
    $latestCo = $pdo->query("SELECT co_no FROM mms_logistik ORDER BY co_no DESC LIMIT 1")->fetchColumn();
    
    try {
        $pdo->beginTransaction();
        
        if ($latestCo) {
            // Copy from previous CO
            $stmt = $pdo->prepare("
                INSERT INTO mms_logistik 
                (id, name, district, dealer, co_no, totalCartons, extraPacks, isDelivered, isDocSigned) 
                SELECT id, name, district, dealer, ?, totalCartons, extraPacks, 0, 0 
                FROM mms_logistik 
                WHERE co_no = ?
            ");
            $stmt->execute([$newCo, $latestCo]);
        } else {
            // Initialize from master schools
            $stmt = $pdo->prepare("
                INSERT INTO mms_logistik 
                (id, name, district, dealer, co_no, totalCartons, extraPacks, isDelivered, isDocSigned) 
                SELECT s.school_code, s.school_name, s.zone_code, LOWER(h.short_code), ?, 0, 0, 0, 0 
                FROM susumurah_farmasimamadstock.schools s 
                LEFT JOIN susumurah_farmasimamadstock.hds h ON s.default_hd_id = h.id
            ");
            $stmt->execute([$newCo]);
        }
        
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "Kitaran '$newCo' berjaya dimula dengan " . $pdo->query("SELECT COUNT(*) FROM mms_logistik WHERE co_no = '$newCo'")->fetchColumn() . " sekolah."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Gagal membina kitaran: ' . $e->getMessage()]);
    }
    exit;
}

// No matching action
echo json_encode(['error' => 'Invalid action']);
?>
