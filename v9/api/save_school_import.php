<?php
// api/save_school_import.php
// Handles CSV Import for Schools
// UPDATED: Captures ZON HD & Matches HD Name

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config/db.php';

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
    die("❌ Error: No file uploaded.");
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === FALSE) {
    die("❌ Error: Cannot open file.");
}

try {
    $pdo->beginTransaction();

    // 1. Get Header Row
    $header = fgetcsv($handle, 1000, ",");
    
    // Normalize headers (Trim & Uppercase for easy matching)
    $header = array_map('trim', $header);
    $header = array_map('strtoupper', $header);
    
    // Find Indexes of columns in your CSV
    $idx_code = array_search('KOD SEKOLAH', $header);
    $idx_name = array_search('NAMA SEKOLAH', $header);
    $idx_stud = array_search('BIL PELAJAR', $header);
    $idx_addr = array_search('ALAMAT', $header);

    // TARGET COLUMNS
    $idx_zon  = array_search('ZON HD', $header); // Your specific column
    $idx_hd   = array_search('NAMA HD', $header); // Your specific column

    // Contract Columns
    $idx_co       = array_search('CO NUMBER', $header);
    $idx_sap      = array_search('NO SAP', $header);
    $idx_tender   = array_search('NO TENDER', $header);
    $idx_contract = array_search('NO KONTRAK', $header);

    if ($idx_code === false) {
        die("❌ Error: Could not find 'KOD SEKOLAH' column in CSV.");
    }

    // 2. Fetch all HDs for smart mapping (Name -> ID)
    // We get ID and Name to compare
    $hds = $pdo->query("SELECT id, name FROM hds")->fetchAll(PDO::FETCH_KEY_PAIR); 
    
    // Function to find HD ID by fuzzy name matching
    function findHdId($csv_name, $db_hds) {
        $csv_name = strtoupper(trim($csv_name));
        if (empty($csv_name)) return null;

        foreach ($db_hds as $hd_id => $db_name) {
            $db_name_upper = strtoupper($db_name);
            
            // 1. Exact match
            if ($db_name_upper === $csv_name) return $hd_id;
            
            // 2. Partial match (e.g. CSV="AHMAD TARMIZI", DB="TARMIZI")
            if (strpos($csv_name, $db_name_upper) !== false) return $hd_id;
            
            // 3. Reverse Partial (e.g. CSV="MMS", DB="MMS TRADING")
            if (strpos($db_name_upper, $csv_name) !== false) return $hd_id;
        }
        return null; // No match found
    }

    $updated_count = 0;

    // 3. Loop Rows
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $code = trim($data[$idx_code]);
        
        if (empty($code)) continue; // Skip empty rows

        $name = ($idx_name !== false) ? trim($data[$idx_name]) : '';
        $students = ($idx_stud !== false) ? (int)$data[$idx_stud] : 0;
        $address = ($idx_addr !== false) ? trim($data[$idx_addr]) : '';
        
        // Capture Zone
        $zone = ($idx_zon !== false) ? trim($data[$idx_zon]) : '';
        
        // Capture HD Name & Find ID
        $hd_name_csv = ($idx_hd !== false) ? trim($data[$idx_hd]) : '';
        $hd_id = findHdId($hd_name_csv, $hds);

        // Capture Contract Info
        $co_num   = ($idx_co !== false) ? trim($data[$idx_co]) : '';
        $sap_no   = ($idx_sap !== false) ? trim($data[$idx_sap]) : '';
        $tend_no  = ($idx_tender !== false) ? trim($data[$idx_tender]) : '';
        $cont_no  = ($idx_contract !== false) ? trim($data[$idx_contract]) : '';

        // UPSERT QUERY
        $sql = "INSERT INTO schools_master 
                (school_code, school_name, student_count, zone_code, default_hd_id, address, 
                 co_number, sap_no, tender_no, contract_no)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                school_name = VALUES(school_name),
                student_count = VALUES(student_count),
                zone_code = VALUES(zone_code),    -- Updates ZON HD
                default_hd_id = VALUES(default_hd_id), -- Updates Linked HD
                address = VALUES(address),
                co_number = VALUES(co_number),
                sap_no = VALUES(sap_no),
                tender_no = VALUES(tender_no),
                contract_no = VALUES(contract_no)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code, $name, $students, $zone, $hd_id, $address, $co_num, $sap_no, $tend_no, $cont_no]);
        
        $updated_count++;
    }

    fclose($handle);
    $pdo->commit();

    echo "<script>
        alert('✅ Success! Processed $updated_count schools. Zones & HDs updated.');
        window.location.href='../import_schools.php';
    </script>";

} catch (Exception $e) {
    $pdo->rollBack();
    fclose($handle);
    die("Database Error: " . $e->getMessage());
}
?>