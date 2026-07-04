<?php
// api/save_co_update.php
header('Content-Type: application/json');

// 1. Database Connection (Adjust creds as needed)
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'school_db'; // Change to your actual DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB Connection Failed']);
    exit;
}

// 2. Capture POST Data
$contract_no = $_POST['contract_no'] ?? '';
$co_no       = $_POST['co_no'] ?? '';
$month_sess  = $_POST['month_session'] ?? '';

if (!$contract_no || !$co_no) {
    echo json_encode(['success' => false, 'message' => 'Missing Contract or CO Number']);
    exit;
}

// 3. Handle File Upload
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

$file = fopen($_FILES['csv_file']['tmp_name'], 'r');

$updated_count = 0;
$skipped_count = 0;
$errors = [];

// 4. Find the Header Row
// Based on your file, there are metadata rows at the top. 
// We look for the row starting with "BIL" to define columns.
$header_found = false;
$col_map = []; // To store indices: 'code' => 2, 'count' => 4

while (($row = fgetcsv($file)) !== FALSE) {
    // Check if this is the header row
    if (strtoupper($row[0]) == 'BIL' && !$header_found) {
        $header_found = true;
        
        // Map columns dynamically just in case positions shift slightly
        foreach ($row as $index => $colName) {
            $colName = strtoupper(trim($colName));
            if (strpos($colName, 'KOD SEKOLAH') !== false) {
                $col_map['code'] = $index;
            }
            if (strpos($colName, 'BIL MURID') !== false) {
                $col_map['count'] = $index;
            }
        }
        
        // Fallback if headers aren't exact matches, use known positions from your file
        if (!isset($col_map['code'])) $col_map['code'] = 2; // Column C
        if (!isset($col_map['count'])) $col_map['count'] = 4; // Column E
        
        continue; // Skip the header row itself
    }

    // Process Data Rows (only after header is found)
    if ($header_found) {
        
        // Ensure row has enough data
        if (count($row) < 3) continue;

        $school_code = trim($row[$col_map['code']]);
        
        // Clean the number (remove commas, spaces, quotes)
        // Your CSV has numbers like " 7,744 "
        $raw_count = $row[$col_map['count']];
        $clean_count = preg_replace('/[^0-9]/', '', $raw_count);
        $student_count = (int)$clean_count;

        // Skip invalid rows (like empty lines or totals at bottom)
        if (empty($school_code) || strlen($school_code) > 10) continue; 

        // 5. CHECK IF SCHOOL EXISTS IN MAIN DB
        // We only update if the school is already in our master list
        $check_stmt = $conn->prepare("SELECT id FROM schools WHERE school_code = ?");
        $check_stmt->bind_param("s", $school_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // School exists! Insert/Update the CO Entitlement record
            
            // SQL: Insert into the tracking table. If specific month/CO exists for this school, update the count.
            $sql = "INSERT INTO co_entitlements 
                    (school_code, contract_no, co_no, month, student_count) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE student_count = VALUES(student_count)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $school_code, $contract_no, $co_no, $month_sess, $student_count);
            
            if ($stmt->execute()) {
                $updated_count++;
            } else {
                $errors[] = "DB Error for $school_code: " . $stmt->error;
            }
            $stmt->close();

        } else {
            // School not found in master list
            $skipped_count++;
            $errors[] = "Skipped $school_code (Not in Master School List)";
        }
        $check_stmt->close();
    }
}

fclose($file);

echo json_encode([
    'success' => true,
    'updated' => $updated_count,
    'skipped' => $skipped_count,
    'errors'  => array_slice($errors, 0, 50) // Limit error log size
]);

$conn->close();
?>