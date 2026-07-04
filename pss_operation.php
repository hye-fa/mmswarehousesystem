<?php
// pss_operation.php
// OPERATION MODULE: Create New PSS Delivery Order (DO)
// ISOLATED FROM DASHBOARD FOR CLEANER WORKFLOW

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

// 1. GET SELECTED CO FROM URL (Passed from Dashboard)
$selected_co = $_GET['co'] ?? '';

if (empty($selected_co)) {
    // If no CO selected, redirect back or show error
    // Ideally, fetch latest if missing, but better to force selection from Hub
    $co_list = $pdo->query("SELECT DISTINCT co_number FROM schools_master WHERE co_number IS NOT NULL ORDER BY co_number DESC LIMIT 1")->fetch();
    $selected_co = $co_list['co_number'] ?? '';
}

// 2. FETCH DATA
$hds = $pdo->query("SELECT id, name FROM hds WHERE status='Active' ORDER BY name ASC")->fetchAll();

$stmt = $pdo->prepare("SELECT id, school_name, school_code, student_count, co_number, sap_no, tender_no, contract_no 
                       FROM schools_master WHERE co_number = ? ORDER BY school_name ASC");
$stmt->execute([$selected_co]);
$schools = $stmt->fetchAll();

$batches = $pdo->query("
    SELECT b.id, b.batch_no, b.expiry_date, b.qty_on_hand, p.name as product_name
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE p.category = 'PSS' AND b.qty_on_hand > 0 AND b.location_status = 'Warehouse'
    ORDER BY b.expiry_date ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create PSS DO - <?= htmlspecialchars($selected_co) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .select2-container .select2-selection--single { height: 38px; line-height: 38px; }
        .calc-box { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 8px; }
        .calc-val { font-weight: bold; color: #0d6efd; font-size: 1.1rem; }
        .info-label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-val { font-weight: 600; font-size: 0.9rem; }
        .picking-ref { background-color: #212529; color: #fff; padding: 10px; border-radius: 6px; margin-top: 10px; text-align: center; font-size: 1.1rem; font-weight: bold; }
        .picking-ref span { color: #ffc107; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4 mb-5">
    
    <!-- NAVIGATION -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="pss_delivery.php?co=<?= urlencode($selected_co) ?>" class="btn btn-outline-dark me-2">⬅ Back to Hub</a>
            <span class="badge bg-primary fs-6"><?= htmlspecialchars($selected_co) ?> Cycle</span>
        </div>
        <h4 class="text-secondary fw-bold m-0">📝 Create Delivery Order</h4>
    </div>
    
    <form method="POST" action="api/save_delivery.php">
        <input type="hidden" name="co_number" value="<?= htmlspecialchars($selected_co) ?>">
        
        <!-- SECTION 1: LOGISTICS -->
        <div class="card mb-4 shadow-sm border-primary">
            <div class="card-header bg-primary text-white fw-bold">1. Logistics Detail</div>
            <div class="card-body">
                <div class="row g-3">
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
                        <input type="text" name="vehicle_plate" class="form-control text-uppercase fw-bold" placeholder="e.g. VDU 7677" required>
                        <small class="text-muted">Manually enter plate for this trip.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Delivery Date</label>
                        <input type="text" name="delivery_date" class="form-control datepicker" value="<?= date('d/m/Y') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- SECTION 2: DESTINATION -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100 border-success">
                    <div class="card-header bg-success text-white fw-bold">2. Destination & Quota</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select School</label>
                            <select name="school_id" class="form-select select2" id="school_select" onchange="updateQuota()" required>
                                <option value="">-- Search School Name or Code --</option>
                                <?php foreach($schools as $s): ?>
                                    <option value="<?= $s['id'] ?>" 
                                            data-students="<?= $s['student_count'] ?>"
                                            data-co="<?= htmlspecialchars($s['co_number'] ?? '') ?>"
                                            data-sap="<?= htmlspecialchars($s['sap_no'] ?? '') ?>"
                                            data-tender="<?= htmlspecialchars($s['tender_no'] ?? '') ?>"
                                            data-contract="<?= htmlspecialchars($s['contract_no'] ?? '') ?>">
                                        <?= htmlspecialchars($s['school_code']) ?> - <?= htmlspecialchars($s['school_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-primary">Bil TP (Packs/Student)</label>
                                <input type="number" id="bil_tp" class="form-control fw-bold border-primary" value="44" oninput="updateQuota()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Student Count</label>
                                <input type="text" id="student_count_display" class="form-control-plaintext fw-bold fs-5" value="0" readonly>
                            </div>
                        </div>

                        <!-- Contract Info Grid -->
                        <div class="row g-2 mb-3 p-2 bg-light rounded border mx-0">
                            <div class="col-6"><span class="info-label">SAP No:</span> <span id="info_sap" class="info-val text-primary">-</span></div>
                            <div class="col-6"><span class="info-label">Kontrak:</span> <span id="info_contract" class="info-val">-</span></div>
                            <div class="col-6"><span class="info-label">Tender:</span> <span id="info_tender" class="info-val">-</span></div>
                        </div>

                        <!-- CALCULATOR -->
                        <div class="calc-box">
                            <h6 class="text-primary fw-bold mb-2">📦 Qty Needed (Exact):</h6>
                            <div class="row text-center mb-2">
                                <div class="col-4 border-end"><span id="calc_packs" class="calc-val">0</span> <small class="d-block text-muted">Packs</small></div>
                                <div class="col-4 border-end"><span id="calc_cartons" class="calc-val">0</span> <small class="d-block text-muted">Cartons</small></div>
                                <div class="col-4"><span id="calc_pallets" class="calc-val">0</span> <small class="d-block text-muted">Pallets</small></div>
                            </div>
                            
                            <!-- PICKING BREAKDOWN -->
                            <div class="picking-ref">
                                🔎 Picking: <br>
                                <span id="pick_pallets">0</span> Plt + 
                                <span id="pick_cartons">0</span> Ctn + 
                                <span id="pick_packs">0</span> Pcs
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: STOCK -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100 border-warning">
                    <div class="card-header bg-warning text-dark fw-bold">3. Stock Allocation</div>
                    <div class="card-body">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Batch to Deliver (FIFO)</label>
                            <select name="inventory_batch_id" class="form-select" required>
                                <?php if (empty($batches)): ?>
                                    <option value="" disabled selected>⚠️ No Stock Available</option>
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

                        <div class="mb-4">
                            <label class="form-label fw-bold">Confirm Qty (Cartons)</label>
                            <input type="number" name="qty" class="form-control form-control-lg border-success" placeholder="0" required min="1">
                            <small class="text-muted">Enter the exact cartons loaded.</small>
                        </div>
                        
                        <hr>
                        <h6 class="fw-bold text-secondary">🧱 Pallets Issued (Debt Tracker)</h6>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="small text-danger fw-bold">Red Out</label>
                                <input type="number" name="pallets_red" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-4">
                                <label class="small text-success fw-bold">Green Out</label>
                                <input type="number" name="pallets_green" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-4">
                                <label class="small text-warning fw-bold">Orange Out</label>
                                <input type="number" name="pallets_orange" class="form-control form-control-sm" value="0">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- SUBMIT -->
        <div class="d-grid mt-4 mb-5">
            <button type="submit" class="btn btn-success btn-lg fw-bold py-3 shadow">🚀 GENERATE DELIVERY ORDER</button>
        </div>

    </form>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });
        flatpickr(".datepicker", { dateFormat: "d/m/Y", allowInput: true });
    });

    function updateQuota() {
        var selected = $('#school_select').find(':selected');
        var studentCount = parseInt(selected.data('students')) || 0;
        
        $('#student_count_display').val(studentCount + " Students");
        $('#info_sap').text(selected.data('sap') || '-');
        $('#info_tender').text(selected.data('tender') || '-');
        $('#info_contract').text(selected.data('contract') || '-');

        var bilTp = parseInt(document.getElementById('bil_tp').value) || 0;
        var totalPacks = studentCount * bilTp;
        
        const PACKS_PER_CTN = 24;
        const CTN_PER_PLT = 144;
        const PACKS_PER_PLT = PACKS_PER_CTN * CTN_PER_PLT; 

        var fullPallets = Math.floor(totalPacks / PACKS_PER_PLT);
        var remainingPacks = totalPacks % PACKS_PER_PLT;
        var fullCartons = Math.floor(remainingPacks / PACKS_PER_CTN);
        var loosePieces = remainingPacks % PACKS_PER_CTN;

        $('#pick_pallets').text(fullPallets);
        $('#pick_cartons').text(fullCartons);
        $('#pick_packs').text(loosePieces);

        // Standard decimals
        $('#calc_packs').text(totalPacks.toLocaleString());
        $('#calc_cartons').text((totalPacks / 24).toFixed(2));
        $('#calc_pallets').text((totalPacks / (24 * 144)).toFixed(2));
    }
</script>

</body>
</html>