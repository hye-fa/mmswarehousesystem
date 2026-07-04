<?php
// pss_delivery.php
// The "Digital DO" creator - NOW WITH FLATPICKR (DD/MM/YYYY)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

$hds = $pdo->query("SELECT id, name FROM hds WHERE status='Active' ORDER BY name ASC")->fetchAll();
$schools = $pdo->query("SELECT id, school_name, school_code, student_count FROM schools_master ORDER BY school_name ASC")->fetchAll();

$sql_batches = "
    SELECT b.id, b.batch_no, b.expiry_date, b.qty_on_hand, p.name as product_name
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE p.category = 'PSS' 
      AND b.qty_on_hand > 0 
      AND b.location_status = 'Warehouse'
    ORDER BY b.expiry_date ASC
";
$batches = $pdo->query($sql_batches)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create PSS Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .select2-container .select2-selection--single { height: 38px; line-height: 38px; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="index.php" class="btn btn-outline-dark me-2">🏠 Home</a>
            <button onclick="history.back()" class="btn btn-outline-secondary">⬅ Back</button>
        </div>
        <div class="text-muted small">MMS Warehouse System</div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🏫 Create PSS Delivery Order (DO)</h2>
    </div>
    
    <form method="POST" action="api/save_delivery.php">
        
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">🚚 Logistics Detail</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Select HD (Contractor)</label>
                        <select name="hd_id" class="form-select select2" required>
                            <option value="">-- Choose HD --</option>
                            <?php foreach($hds as $hd): ?>
                                <option value="<?= $hd['id'] ?>"><?= htmlspecialchars($hd['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold text-danger">Vehicle Plate No</label>
                        <input type="text" name="vehicle_plate" class="form-control" placeholder="e.g. VDU 7677" required>
                        <small class="text-muted">Must be keyed in manually every time.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Delivery Date (DD/MM/YYYY)</label>
                        <!-- FLATPICKR INPUT -->
                        <input type="text" name="delivery_date" class="form-control datepicker" value="<?= date('d/m/Y') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-success text-white">📍 Destination</div>
                    <div class="card-body">
                        <label class="form-label fw-bold">Select School</label>
                        <select name="school_id" class="form-select select2" id="school_select" onchange="updateQuota()">
                            <option value="">-- Search School Name or Code --</option>
                            <?php foreach($schools as $s): ?>
                                <option value="<?= $s['id'] ?>" data-students="<?= $s['student_count'] ?>">
                                    <?= htmlspecialchars($s['school_code']) ?> - <?= htmlspecialchars($s['school_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Student Count:</strong> <span id="student_display">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-warning text-dark">📦 Stock Allocation</div>
                    <div class="card-body">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Batch to Deliver (FIFO)</label>
                            <select name="inventory_batch_id" class="form-select" required>
                                <option value="">-- Select Batch (Oldest First) --</option>
                                <?php if (empty($batches)): ?>
                                    <option value="" disabled>⚠️ No Stock Available in System</option>
                                <?php else: ?>
                                    <?php foreach($batches as $b): ?>
                                        <option value="<?= $b['id'] ?>">
                                            Batch <?= htmlspecialchars($b['batch_no']) ?> | 
                                            Exp: <?= $b['expiry_date'] ?> | 
                                            Avail: <?= $b['qty_on_hand'] ?> Ctn
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Quantity to Send (Cartons)</label>
                            <input type="number" name="qty" class="form-control" placeholder="0" required min="1">
                        </div>
                        
                        <hr>
                        <h6>🧱 Pallets Issued to HD (Debt)</h6>
                        <div class="row">
                            <div class="col">
                                <label class="small text-danger">Red Out</label>
                                <input type="number" name="pallets_red" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col">
                                <label class="small text-success">Green Out</label>
                                <input type="number" name="pallets_green" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col">
                                <label class="small text-warning">Orange Out</label>
                                <input type="number" name="pallets_orange" class="form-control form-control-sm" value="0">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-dark btn-lg mt-4 w-100 py-3">🖨️ Generate DO & Update Stock</button>

    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });
        
        // Initialize Flatpickr
        flatpickr(".datepicker", {
            dateFormat: "d/m/Y",
            allowInput: true
        });
    });

    function updateQuota() {
        var studentCount = $('#school_select').find(':selected').data('students');
        $('#student_display').text(studentCount || 0);
    }
</script>

</body>
</html>