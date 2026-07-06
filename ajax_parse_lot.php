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
        'product_id'   => 0,
        'product_code' => '',
        'expiry_date'  => '',
        'batch'        => '',
        'qty_pieces'   => 0,
        'pallet_id_short' => '',
        'pallet_raw_code' => ''
    ]
];

// 1. Check if the scanned code is an exact match for any product barcode in the DB
$barcode_matched = false;
try {
    require_once 'config/db.php';
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$lot]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prod) {
        $response['data']['product_id'] = (int)$prod['id'];
        $response['data']['product_code'] = $lot;
        $barcode_matched = true;
    }
} catch (Exception $e) {
    // Ignore
}

if ($barcode_matched) {
    echo json_encode($response);
    exit;
}

// Product Mapping from scanned code to DB product names
$product_mapping = [
    'CW61P4' => [
        'default' => 'Chocomalt 800g'
    ],
    'CW6CH' => [
        125 => 'UHT Yarra Chocolate 125ml',
        200 => 'UHT FF Yarra Chocolate 200ml',
        1000 => 'UHT Yarra Chocolate 1l'
    ],
    'CW6SB' => [
        125 => 'UHT Yarra Strawberry 125ml',
        200 => 'UHT FF Yarra Strawberry 200ml',
        1000 => 'UHT Yarra Strawberry 1l'
    ],
    'CW6FC' => [
        'school' => 'UHT Yarra Full Cream (School) 200ml',
        125 => 'UHT Yarra Full Cream 125ml',
        200 => 'UHT FF Yarra Full Cream 200ml',
        1000 => 'UHT Yarra Full Cream 1l'
    ],
    'YD1SB' => [
        'UHT' => [
            125 => 'UHT FF Yog Strawberry 125ml',
            200 => 'UHT FF Yog Strawberry 200ml'
        ],
        'PST' => [
            200 => 'PST Yogurt Strawberry 200ml'
        ]
    ],
    'YD1MB' => [
        'UHT' => [
            125 => 'UHT FF Yog Mix Berry 125ml',
            200 => 'UHT FF Yog Mix Berry 200ml'
        ],
        'PST' => [
            200 => 'PST Yogurt Mixberries 200ml'
        ]
    ],
    'YD1MG' => [
        'UHT' => [
            125 => 'UHT FF Yog Mango 125ml',
            200 => 'UHT FF Yog Mango 200ml'
        ],
        'PST' => [
            200 => 'PST Yogurt Mango 200ml'
        ]
    ],
    'CW1KU' => [
        'UHT' => [
            125 => 'UHT FF Kurma 125ml',
            200 => 'UHT FF Kurma 200ml',
            1000 => 'UHT FF Kurma 1l'
        ],
        'PST' => [
            200 => 'PST Kurma 200ml',
            1000 => 'PST Kurma Milk 1L',
            2000 => 'PST Kurma 2L'
        ]
    ],
    'CW1CO' => [
        'UHT' => [
            200 => 'UHT FF Caf'
        ],
        'PST' => [
            200 => 'PST Cafe Latte 200ml'
        ]
    ],
    'CW1CH' => [
        'UHT' => [
            125 => 'UHT FF Chocolate 125ml',
            200 => 'UHT FF Chocolate 200ml',
            1000 => 'UHT FF Chocolate 1l'
        ],
        'PST' => [
            200 => 'PST Chocolate 200ml',
            1000 => 'PST Chocolate 1L'
        ]
    ],
    'CW1FC' => [
        'UHT' => [
            125 => 'UHT FF Fresh 125ml',
            200 => 'UHT FF Fresh 200ml',
            1000 => 'UHT FF Fresh 1l'
        ],
        'PST' => [
            568 => 'PST Pure Fresh 568ml',
            1000 => 'PST Pure Fresh Milk 1L',
            2000 => 'PST Pure Fresh Milk 2L'
        ]
    ],
    'CW1BA' => [
        'UHT' => [
            125 => 'UHT FF Banana 125ml',
            200 => 'UHT FF Banana 200ml'
        ],
        'PST' => [
            700 => 'PST Banana 700ml'
        ]
    ],
    'CW1CT' => [
        'UHT' => [
            200 => 'UHT FF Soy Chocolate 200ml',
            1000 => 'UHT FF Soy Chocolate 1l'
        ]
    ]
];

// Check if new format (contains /)
if (strpos($lot, '/') !== false) {
    $parts = explode('/', $lot);
    
    // 1. Parse Component 1 (Product & Packaging info)
    $prod_code = '';
    $size = 0;
    $suffix = '';
    $known_codes = implode('|', array_keys($product_mapping));
    if (preg_match('/^(?:G{2,3}ITN[O0]\d*)(' . $known_codes . ')-?(\d+)([A-Z])(\d+)([A-Z])$/i', trim($parts[0]), $m1)) {
        $prod_code = strtoupper($m1[1]);
        $size      = (int)$m1[2];
        $packaging = strtoupper($m1[3]);
        $pack_size = (int)$m1[4];
        $suffix    = strtoupper($m1[5]);
        
        $response['data']['product_code'] = $prod_code . '-' . $m1[2] . $packaging . $m1[4] . $suffix;
    }
    
    // 2. Parse Component 2 (Date, Batch, Pallet)
    if (isset($parts[1])) {
        $comp2_parts = explode('-', trim($parts[1]));
        
        // Expiry Date (first segment, extract 6 digits as date)
        if (isset($comp2_parts[0]) && preg_match('/(\d{2})(\d{2})(\d{2})/', $comp2_parts[0], $date_m)) {
            $year  = "20" . $date_m[1];
            $month = $date_m[2];
            $day   = $date_m[3];
            $response['data']['expiry_date'] = "$day/$month/$year";
        }
        
        // Batch & Pallet segments
        if (count($comp2_parts) === 3) {
            // Batch is segment 2 (e.g., MPD012 or MFC010)
            if (preg_match('/M[PF]([A-Z])(\d+)/i', $comp2_parts[1], $batch_m)) {
                $batch_letter = strtoupper($batch_m[1]);
                $batch_num    = (int)$batch_m[2];
                if ($category === 'PSS' || $suffix === 'F') {
                    $response['data']['batch'] = 'S' . $batch_letter . $batch_num;
                } else {
                    $response['data']['batch'] = $batch_letter . $batch_num;
                }
            } else {
                $response['data']['batch'] = $comp2_parts[1];
            }
            
            // Pallet is segment 3 (e.g., PA010)
            $pallet_raw = $comp2_parts[2];
        } elseif (count($comp2_parts) === 2) {
            // Pallet is segment 2 (e.g., PC018)
            $pallet_raw = $comp2_parts[1];
        } else {
            $pallet_raw = '';
        }
        
        if ($pallet_raw) {
            if (preg_match('/^P([A-Z])(\d+)$/i', $pallet_raw, $pallet_m)) {
                $pallet_prefix = 'P' . strtoupper($pallet_m[1]);
                $pallet_num    = $pallet_m[2];
                $response['data']['pallet_raw_code'] = $pallet_prefix . $pallet_num;
                $response['data']['pallet_id_short']  = $pallet_prefix . (int)$pallet_num;
            } else {
                $response['data']['pallet_raw_code'] = $pallet_raw;
                $response['data']['pallet_id_short']  = $pallet_raw;
            }
        }
    }
    
    // 3. Parse Component 3 (Quantity, e.g. QTY432)
    if (isset($parts[2]) && preg_match('/QTY(\d+)/i', trim($parts[2]), $qty_m)) {
        $response['data']['qty_pieces'] = (int)$qty_m[1];
    } else {
        // Fallback search in entire code
        if (preg_match('/QTY(\d+)/i', $lot, $qty_m)) {
            $response['data']['qty_pieces'] = (int)$qty_m[1];
        }
    }
    
    // 4. Resolve Product ID using mapping
    $target_name = '';
    if (isset($product_mapping[$prod_code])) {
        $rules = $product_mapping[$prod_code];
        if (isset($rules['UHT']) || isset($rules['PST'])) {
            $cat_key = ($category === 'PST') ? 'PST' : 'UHT';
            $rules = isset($rules[$cat_key]) ? $rules[$cat_key] : [];
        }
        if (($category === 'PSS') && isset($rules['school'])) {
            $target_name = $rules['school'];
        } elseif (isset($rules[$size])) {
            $target_name = $rules[$size];
        } elseif (isset($rules['default'])) {
            $target_name = $rules['default'];
        }
    }
    
    if ($target_name) {
        try {
            require_once 'config/db.php';
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 1");
            $stmt->execute(["%" . $target_name . "%"]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($prod) {
                $response['data']['product_id'] = (int)$prod['id'];
            }
        } catch (Exception $e) {
            // Ignore DB errors
        }
    }
} else {
    // Old format fallback
    if (preg_match('/QTY(\d+)/i', $lot, $matches)) {
        $response['data']['qty_pieces'] = (int)$matches[1];
    }
    
    if (preg_match('/(?:BAN|BBD|EXP)(\d{6})/i', $lot, $matches)) {
        $exp_raw = $matches[1];
        $year  = "20" . substr($exp_raw, 0, 2);
        $month = substr($exp_raw, 2, 2);
        $day   = substr($exp_raw, 4, 2);
        $response['data']['expiry_date'] = "$day/$month/$year";
    } elseif (preg_match('/(\d{6})-?MF[A-Z]/i', $lot, $matches)) {
        $exp_raw = $matches[1];
        $year  = "20" . substr($exp_raw, 0, 2);
        $month = substr($exp_raw, 2, 2);
        $day   = substr($exp_raw, 4, 2);
        $response['data']['expiry_date'] = "$day/$month/$year";
    }
    
    $prod_code = '';
    $known_codes = implode('|', array_keys($product_mapping));
    // Check old format
    if (preg_match('/(\d{4}[A-Z0-9]*)-?\d{6}-?MF[A-Z]/i', $lot, $matches)) {
        $prod_code = $matches[1];
        $response['data']['product_code'] = $prod_code; 
    }
    // Check new format without slashes but with full product details
    elseif (preg_match('/(?:G{2,3}ITN[O0]\d*)(' . $known_codes . ')-?(\d+)([A-Z])(\d+)([A-Z])/i', $lot, $matches)) {
        $prod_code = strtoupper($matches[1]);
        $size = (int)$matches[2];
        $suffix = strtoupper($matches[5]);
        $response['data']['product_code'] = $prod_code . '-' . $matches[2] . strtoupper($matches[3]) . $matches[4] . $suffix;
    }
    // Check old format (just prefix)
    elseif (preg_match('/(?:G{2,3}ITN[O0]\d*)(' . $known_codes . ')/i', $lot, $matches)) {
        $prod_code = strtoupper($matches[1]);
        $response['data']['product_code'] = $prod_code; 
    }
    
    if (preg_match('/MF([A-Z])(\d+)/i', $lot, $matches)) {
        $batch_letter = strtoupper($matches[1]);
        $batch_num = (int)$matches[2];
        if ($category === 'PSS') {
            $batch_final = 'S' . $batch_letter . $batch_num;
        } else {
            $batch_final = $batch_letter . $batch_num;
        }
        $response['data']['batch'] = $batch_final;
    }
    
    if (preg_match('/(PW|PM|LR|PR|LG|PG|FO|FG|P[A-Z])(\d+)/i', $lot, $matches)) {
        $p_prefix = strtoupper($matches[1]);
        $p_num    = (int)$matches[2];
        $response['data']['pallet_raw_code'] = $p_prefix . $matches[2];
        $response['data']['pallet_id_short']  = $p_prefix . $p_num;
    }
    
    if ($prod_code) {
        $target_name = '';
        if (isset($product_mapping[$prod_code])) {
            $rules = $product_mapping[$prod_code];
            if (isset($rules['UHT']) || isset($rules['PST'])) {
                $cat_key = ($category === 'PST') ? 'PST' : 'UHT';
                $rules = isset($rules[$cat_key]) ? $rules[$cat_key] : [];
            }
            if (($category === 'PSS') && isset($rules['school'])) {
                $target_name = $rules['school'];
            } elseif (isset($size) && isset($rules[$size])) {
                $target_name = $rules[$size];
            } elseif (isset($rules['default'])) {
                $target_name = $rules['default'];
            }
        }
        
        try {
            require_once 'config/db.php';
            if ($target_name) {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 1");
                $stmt->execute(["%" . $target_name . "%"]);
            } else {
                $numericSize = preg_replace('/[^0-9]/', '', $prod_code);
                $stmt = $pdo->prepare("SELECT id FROM products WHERE (name LIKE ? OR name LIKE ?) AND is_active = 1 LIMIT 1");
                $stmt->execute(["%" . $prod_code . "%", "%" . $numericSize . "%"]);
            }
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($prod) {
                $response['data']['product_id'] = (int)$prod['id'];
            }
        } catch (Exception $e) {
            // Ignore
        }
    }
}

echo json_encode($response);
exit;
?>