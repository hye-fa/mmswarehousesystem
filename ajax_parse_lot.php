<?php
// ajax_parse_lot.php
// VERSION 2026: Fixed Regex Patterns for Accurate QR Inbound Parsing

header('Content-Type: application/json');

if (!isset($_GET['lot_no'])) {
    echo json_encode(['status' => 'error', 'message' => 'No lot number provided']);
    exit;
}

$lot = trim($_GET['lot_no']);
$category = isset($_GET['category']) ? trim($_GET['category']) : 'UHT';

// Sediakan struktur data lalai (Default Response)
$response = [
    'status' => 'success',
    'data' => [
        'product_code' => '',
        'expiry_date'  => '',
        'batch'        => '',
        'qty_pieces'   => 0,
        'pallet_id_short' => '',
        'pallet_raw_code' => ''
    ]
];

/**
 * LOGIK EKSTRAK DATA DARI QR STRING (REGEX DITAMBAH BAIK):
 * Contoh Realiti: GGGITNO2CW6FC-0200S-260813-MFD010-PH034QTY3456
 */

// 1. Ekstrak Kuantiti (Mencari teks selepas 'QTY') -> Contoh: QTY3456 -> 3456
if (preg_match('/QTY(\d+)/i', $lot, $matches)) {
    $response['data']['qty_pieces'] = (int)$matches[1];
}

// 2. Ekstrak Expiry Date (Mengesan 6 digit angka bulat sebelum MFD/MFB/MFA dll)
if (preg_match('/(\d{6})-?MF[A-Z]/i', $lot, $matches)) {
    $exp_raw = $matches[1]; // "260813"
    $year  = "20" . substr($exp_raw, 0, 2);
    $month = substr($exp_raw, 2, 2);
    $day   = substr($exp_raw, 4, 2);
    $response['data']['expiry_date'] = "$day/$month/$year";
}

// 3. Ekstrak Kod Produk (Mencari 4 digit alphanum sebelum Expiry Date)
if (preg_match('/(\d{4}[A-Z0-9]*)-?\d{6}-?MF[A-Z]/i', $lot, $matches)) {
    $response['data']['product_code'] = $matches[1]; 
}

// 4. Ekstrak Batch (Mencari teks selepas MFD/MFB/MFE/MFF dsb) -> Contoh: MFF009 -> "F9"
if (preg_match('/MF([A-Z])(\d+)/i', $lot, $matches)) {
    $batch_letter = strtoupper($matches[1]); // Contoh: F
    $batch_num = (int)$matches[2]; // "009" -> 9
    
    // Logik prefix mengikut Kategori
    if ($category === 'PSS') {
        $batch_final = 'S' . $batch_letter . $batch_num; // Contoh: SF9
    } else {
        $batch_final = $batch_letter . $batch_num;  // Contoh: F9
    }
    $response['data']['batch'] = $batch_final;
}

// 5. Ekstrak Pallet Code secara dinamik -> Contoh: PH034
// Kita ambil kod penuh asal (PH034) DAN kod pendek (PH34) untuk front-end dropdown
if (preg_match('/(PH|PP|PW|PM|LR|PR|LG|PG|FO|FG|PB)(\d+)/i', $lot, $matches)) {
    $p_prefix = strtoupper($matches[1]); // PH
    $p_num    = (int)$matches[2];        // 034 -> 34
    
    $response['data']['pallet_raw_code'] = $p_prefix . $matches[2]; // "PH034"
    $response['data']['pallet_id_short']  = $p_prefix . $p_num;      // "PH34"
}

echo json_encode($response);
exit;
?>