<?php
// view_batch.php
// Displays the generated SAP numbers combined with School Addresses

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Connect to Database
$configFile = __DIR__ . '/config/db.php';
if (!file_exists($configFile)) die("Config file not found.");
require_once $configFile;

// Ensure $pdo is set
if (!isset($pdo) || $pdo === null) die("Database connection failed.");

// 2. Handle Batch Selection
$batchId = $_GET['batch_id'] ?? null;
$batches = [];

// Fetch all batches for the dropdown
try {
    $stmt = $pdo->query("SELECT * FROM import_batches ORDER BY created_at DESC");
    $batches = $stmt->fetchAll();
    
    // Default to the latest batch if none selected
    if (!$batchId && count($batches) > 0) {
        $batchId = $batches[0]['id'];
    }
} catch (Exception $e) {
    die("Error fetching batches: " . $e->getMessage());
}

// 3. Fetch Report Data (The Big JOIN)
$reportData = [];
if ($batchId) {
    $sql = "
        SELECT 
            t.no_sap,
            t.kod_sekolah,
            t.bil_murid,
            m.nama_sekolah,
            m.alamat,
            m.no_tel,
            m.nama_hd,
            m.daerah_master
        FROM import_transactions t
        LEFT JOIN master_schools m ON t.kod_sekolah = m.kod_sekolah
        WHERE t.batch_id = ?
        ORDER BY t.no_sap ASC
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$batchId]);
        $reportData = $stmt->fetchAll();
    } catch (Exception $e) {
        die("Error fetching report: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Report View</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .shadow-lg { box-shadow: none; }
        }
    </style>
</head>
<body class="bg-gray-100 p-6">

<div class="max-w-7xl mx-auto">
    <div class="bg-white p-4 rounded shadow-lg mb-6 no-print flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Monthly Order Report</h1>
            <p class="text-sm text-gray-500">View generated SAP numbers and delivery details.</p>
        </div>
        
        <form method="GET" class="flex items-center space-x-4">
            <select name="batch_id" onchange="this.form.submit()" class="border p-2 rounded">
                <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $batchId ? 'selected' : '' ?>>
                        #<?= $b['id'] ?> - <?= htmlspecialchars($b['contract_name']) ?> (<?= $b['last_delivery_date'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Print Report
            </button>
        </form>
    </div>

    <div class="bg-white shadow-lg rounded overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-700 uppercase bg-gray-200">
                    <tr>
                        <th class="px-4 py-3">No. SAP (Generated)</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">School Name</th>
                        <th class="px-4 py-3">Address</th>
                        <th class="px-4 py-3">Tel</th>
                        <th class="px-4 py-3 text-center">Qty (Murid)</th>
                        <th class="px-4 py-3">Handler (HD)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reportData) > 0): ?>
                        <?php foreach ($reportData as $row): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2 font-bold text-blue-800 whitespace-nowrap">
                                    <?= htmlspecialchars($row['no_sap']) ?>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['kod_sekolah']) ?></td>
                                <td class="px-4 py-2 font-medium text-gray-900">
                                    <?= htmlspecialchars($row['nama_sekolah'] ?? 'Unknown School (Not in Master)') ?>
                                </td>
                                <td class="px-4 py-2 max-w-xs truncate" title="<?= htmlspecialchars($row['alamat'] ?? '') ?>">
                                    <?= htmlspecialchars($row['alamat'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($row['no_tel'] ?? '-') ?></td>
                                <td class="px-4 py-2 text-center font-bold text-green-700">
                                    <?= number_format($row['bil_murid']) ?>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($row['nama_hd'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No data found for this batch.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>