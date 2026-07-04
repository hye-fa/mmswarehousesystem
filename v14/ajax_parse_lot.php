<?php
// ajax_parse_lot.php
// UPDATED: Logic for B10 (Batch) and P3 (Pallet ID)

header('Content-Type: application/json');

$lot_string = $_REQUEST['lot_no'] ?? ''; 
$lot_string = trim($lot_string);

if (empty($lot_string)) {
    echo json_encode(['status' => 'error', 'message' => 'No input provided.']);
    exit;
}

try {
    // 1. CLEANUP (Handle QR Slashes if present)
    $target_segment = $lot_string;
    $extracted_qty = 0;

    if (strpos($lot_string, '/') !== false) {
        $segments = explode('/', $lot_string);
        foreach($segments as $seg) {
            if (strpos($seg, 'BANO') === 0) $target_segment = str_replace('BANO', '', $seg);
            if (strpos($seg, 'QTY') === 0) $extracted_qty = (int)str_replace('QTY', '', $seg);
        }
    }

    // 2. PARSING (Format: YYMMDD-BATCH-PALLET)
    $parts = explode('-', $target_segment);
    
    if (count($parts) !== 3) {
        throw new Exception("Invalid Format. Expected YYMMDD-BATCH-PALLET.");
    }

    // --- PART A: EXPIRY DATE (260831 -> 31/08/2026) ---
    $raw_date = $parts[0];
    $year_short = substr($raw_date, 0, 2); 
    $month      = substr($raw_date, 2, 2); 
    $day        = substr($raw_date, 4, 2); 
    $expiry_date = $day . "/" . $month . "/20" . $year_short; 

    // --- PART B: BATCH NO (MFB010 -> B10) ---
    // Logic: 3rd Character + Integer of the rest
    $middle_part = $parts[1]; 
    if (strlen($middle_part) >= 3) {
        $batch_char = substr($middle_part, 2, 1); // "B"
        $batch_num  = (int)substr($middle_part, 3); // "010" -> 10
        $batch_final = $batch_char . $batch_num; // "B10"
    } else {
        $batch_final = $middle_part; // Fallback
    }

    // --- PART C: PALLET ID (PP003 -> P3) ---
    // Logic: 2nd Character + Integer of the rest
    $last_part = $parts[2];
    $pallet_extracted = $last_part; // Default
    
    if (strlen($last_part) >= 2) {
        $pallet_char = substr($last_part, 1, 1); // "P" from PP, "M" from PM
        $pallet_num  = (int)substr($last_part, 2); // "003" -> 3
        $pallet_extracted = $pallet_char . $pallet_num; // "P3" or "M3"
    }

    // Return Data
    echo json_encode([
        'status' => 'success',
        'data' => [
            'expiry_date'      => $expiry_date,
            'batch'            => $batch_final,
            'pallet_id_short'  => $pallet_extracted, // P3, M3
            'qty_pieces'       => $extracted_qty,
            'pallet_raw_code'  => $last_part // For dropdown logic if needed (PP003)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400); 
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>