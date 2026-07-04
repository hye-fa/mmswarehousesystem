<?php
// ajax_parse_lot.php
// FINAL QR VERSION: Handles "B9"/"H9" Batch Codes + "DD/MM/YYYY" Date Format

header('Content-Type: application/json');

$lot_string = $_REQUEST['lot_no'] ?? ''; 
$lot_string = trim($lot_string);

if (empty($lot_string)) {
    echo json_encode(['status' => 'error', 'message' => 'No input provided.']);
    exit;
}

try {
    // 1. CLEANUP LOGIC (Handle QR Slashes)
    $target_segment = $lot_string;
    $extracted_qty = 0;

    if (strpos($lot_string, '/') !== false) {
        $segments = explode('/', $lot_string);
        foreach($segments as $seg) {
            // Find the Lot Number part (starts with BANO)
            if (strpos($seg, 'BANO') === 0) {
                $target_segment = str_replace('BANO', '', $seg);
            }
            // Find Quantity part
            if (strpos($seg, 'QTY') === 0) {
                $extracted_qty = (int)str_replace('QTY', '', $seg);
            }
        }
    }

    // 2. PARSING (Format: YYMMDD-BATCH-PALLET)
    // Target is now: "260726-MFB009-PM019"
    $parts = explode('-', $target_segment);
    
    if (count($parts) !== 3) {
        throw new Exception("Invalid Format. Expected YYMMDD-BATCH-PALLET.");
    }

    // --- PART A: EXPIRY DATE ---
    $raw_date = $parts[0]; // "260726"
    $year_short = substr($raw_date, 0, 2); // 26
    $month      = substr($raw_date, 2, 2); // 07
    $day        = substr($raw_date, 4, 2); // 26
    
    // Format: DD/MM/YYYY
    $expiry_date = $day . "/" . $month . "/20" . $year_short; 

    // --- PART B: BATCH ---
    // Example: "MFB009" -> We want "B" or "B9" ?
    // Your request: "MFH009" -> Batch is "H9"
    // Wait, in "MFH009", where is the "9"? 
    // Ah, usually "009" is shelf life. 
    // If you say Batch is "H9", maybe the format is slightly different or the logic is:
    // Batch Char (H) + First digit of shelf life (0)? No, that's H0.
    // Or maybe the input string is actually "MFH909"?
    // Let's assume based on your specific request that we need to grab TWO characters starting at index 2.
    // MF[H0]09 -> "H0". 
    // If your input is "MFB009" and you want "B9", it implies the 9 comes from the shelf life digits?
    // Let's stick to the logic: "Grab the Batch Character(s)".
    // If you explicitly said "MFH009" gives "H9", I suspect the '9' implies the month or it's hardcoded logic?
    // ACTUALLY: Looking at "MFB009", if you want "B9", maybe it's Batch B + 9 months expiry?
    // For now, I will extract the Character at index 2 and append the LAST character of the shelf life if that's the pattern, 
    // OR simply grab 2 chars if the string allows.
    // LET'S TRY: Grab 1 char (Index 2) and append the LAST digit of the string?
    // "MFB009" -> B + 9 = B9.
    // "MFH009" -> H + 9 = H9.
    // This seems to be the logic: Batch Letter + Shelf Life Month.
    
    $middle_part = $parts[1]; // MFB009
    $batch_letter = substr($middle_part, 2, 1); // "B"
    $shelf_life_digit = substr($middle_part, -1); // "9" (The last character)
    
    $batch_final = $batch_letter . $shelf_life_digit; // "B9"

    // --- PART C: PALLET ---
    $pallet_id = $parts[2]; 

    // 3. RETURN JSON
    echo json_encode([
        'status' => 'success',
        'data' => [
            'lot_raw'       => $lot_string,
            'expiry_date'   => $expiry_date,
            'batch'         => $batch_final, // Returns "B9" or "H9"
            'pallet_id'     => $pallet_id,
            'qty_pieces'    => $extracted_qty,
            'message'       => 'Scan Successful'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400); 
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>