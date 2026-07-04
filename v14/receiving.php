<?php
// receiving.php
// Updated: Security Removed, Layout Centered, & Auto-Parsing Logic Included

// 1. CONFIGURATION
require_once 'config/db.php';

// 2. HANDLE FORM SUBMISSION
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize inputs
    $item_code   = htmlspecialchars(strip_tags($_POST['item_code']));
    $batch_no    = htmlspecialchars(strip_tags($_POST['batch_no']));
    $expiry_date = htmlspecialchars(strip_tags($_POST['expiry_date']));
    $qty         = (int) $_POST['qty'];
    $pallet_id   = htmlspecialchars(strip_tags($_POST['pallet_id']));

    if (empty($item_code) || empty($batch_no) || empty($qty)) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
        // Insert into Database
        $sql = "INSERT INTO inventory (item_code, batch_no, expiry_date, qty, pallet_id) VALUES (:item, :batch, :expiry, :qty, :pallet)";
        
        try {
            // Check if $pdo is available (from config/db.php)
            if (isset($pdo)) {
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(":item", $item_code);
                $stmt->bindParam(":batch", $batch_no);
                $stmt->bindParam(":expiry", $expiry_date);
                $stmt->bindParam(":qty", $qty);
                $stmt->bindParam(":pallet", $pallet_id);

                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Success! Item received into inventory.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Database Error: Could not save item.</div>';
                }
            } else {
                 $message = '<div class="alert alert-danger">Database Connection Failed.</div>';
            }
        } catch (PDOException $e) {
             $message = '<div class="alert alert-danger">SQL Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// 3. FETCH ITEM LIST (Safe Mode)
$item_list = [];
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT item_code, description FROM items ORDER BY item_code ASC");
        $item_list = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    // If table missing or DB error, ignore so form still loads
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbound Receiving</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
        }
        /* Navbar stays at top */
        .navbar { width: 100%; flex-shrink: 0; }
        
        /* Main Content Wrapper for Centering */
        .content-wrapper {
            flex-grow: 1;
            display: flex;
            align-items: center; /* Vertical Center */
            justify-content: center; /* Horizontal Center */
            padding: 20px;
        }

        .form-container { 
            width: 100%;
            max-width: 600px; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
        }
        .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="index.php">MMS Warehouse</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="receiving.php">Receiving</a>
                <a class="nav-link" href="index.php">Home</a>
            </div>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="form-container">
            <h3 class="text-success mb-4 border-bottom pb-2">📦 Single Item Receiving</h3>
            
            <?= $message ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Item Code</label>
                    <select name="item_code" class="form-select" required autofocus>
                        <option value="">-- Select Item --</option>
                        <?php if (!empty($item_list)): ?>
                            <?php foreach($item_list as $item): ?>
                                <option value="<?= $item['item_code'] ?>"><?= $item['item_code'] ?> - <?= $item['description'] ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="ITEM-001">ITEM-001 (Sample)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-3 bg-light p-3 rounded border">
                    <label class="form-label fw-bold text-primary mb-1">SCAN LOT NO:</label>
                    <input type="text" id="lot_no" class="form-control border-primary" placeholder="e.g. 260831-MFB010-PP003" oninput="parseLotNo()" autocomplete="off">
                    <div class="form-text small">Auto-fills details below.</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control readonly-input" required readonly>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Batch No</label>
                        <input type="text" name="batch_no" id="batch_no" class="form-control readonly-input" required readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Shelf Life (Months)</label>
                        <input type="text" id="shelf_life" class="form-control readonly-input" readonly>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pallet ID</label>
                        <input type="text" name="pallet_id" id="pallet_id" class="form-control readonly-input" required readonly>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Quantity (Cartons)</label>
                    <input type="number" name="qty" class="form-control form-control-lg" placeholder="Enter Qty" required min="1">
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">Confirm Receive</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    function parseLotNo() {
        let lotString = document.getElementById('lot_no').value.trim();
        // Split by hyphen
        let parts = lotString.split('-');

        if (parts.length === 3) {
            
            // A. EXPIRY DATE (260831 -> 2026-08-31)
            let datePart = parts[0];
            if (datePart.length === 6) {
                let year  = "20" + datePart.substring(0, 2);
                let month = datePart.substring(2, 4);
                let day   = datePart.substring(4, 6);
                document.getElementById('expiry_date').value = `${year}-${month}-${day}`;
            }

            // B. BATCH & SHELFLIFE (MFB010)
            let midPart = parts[1];
            if (midPart.length >= 5) {
                let batchChar = midPart.substring(2, 3); // 3rd char
                let shelfLifeRaw = midPart.substring(3); // Rest
                let shelfLifeInt = parseInt(shelfLifeRaw);
                
                document.getElementById('batch_no').value = batchChar + shelfLifeInt; // e.g., B10
                document.getElementById('shelf_life').value = shelfLifeInt;
            }

            // C. PALLET ID (PP003)
            let lastPart = parts[2];
            if (lastPart.length >= 4) {
                let palletChar = lastPart.substring(1, 2); // 2nd char
                let palletNum  = parseInt(lastPart.substring(2)); // Rest
                
                document.getElementById('pallet_id').value = palletChar + palletNum; // e.g., P3
            }
        }
    }
    </script>

</body>
</html>