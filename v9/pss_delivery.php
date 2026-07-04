<?php
// pss_delivery.php
// PSS MANAGEMENT HUB
// UPDATED: Resized "Bil Pelajar" to make "SAP No" wider

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

$co_list = $pdo->query("SELECT DISTINCT co_number FROM schools_master WHERE co_number IS NOT NULL ORDER BY co_number DESC")->fetchAll();
$selected_co = $_GET['co'] ?? ($co_list[0]['co_number'] ?? '');

// DASHBOARD DATA
$stmt = $pdo->prepare("SELECT COUNT(*) as total_schools, SUM(student_count) as total_students FROM schools_master WHERE co_number = ?");
$stmt->execute([$selected_co]);
$stats_contract = $stmt->fetch();

$stmt = $pdo->query("SELECT SUM(b.qty_on_hand) as total_stock FROM inventory_batches b JOIN products p ON b.product_id = p.id WHERE p.category = 'PSS' AND b.location_status = 'Warehouse'");
$stats_stock = $stmt->fetch();

$stmt = $pdo->prepare("SELECT d.do_number, d.delivery_date, d.vehicle_plate, s.school_name FROM deliveries_pss d LEFT JOIN schools_master s ON d.school_id = s.id ORDER BY d.created_at DESC LIMIT 10");
$stmt->execute();
$recent_deliveries = $stmt->fetchAll();

// FORM DATA
$hds = $pdo->query("SELECT id, name FROM hds WHERE status='Active' ORDER BY name ASC")->fetchAll();

// Fetch ALL schools for this CO
$stmt = $pdo->prepare("SELECT id, school_name, school_code, student_count, default_hd_id, co_number, sap_no, tender_no, contract_no 
                       FROM schools_master WHERE co_number = ? ORDER BY school_name ASC");
$stmt->execute([$selected_co]);
$schools = $stmt->fetchAll();

$batches = $pdo->query("
    SELECT b.id, b.batch_no, b.expiry_date, b.qty_on_hand
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
    <title>PSS Management Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .select2-container .select2-selection--single { height: 38px; line-height: 38px; }
        .dash-card { border-left: 4px solid; transition: transform 0.2s; }
        .dash-card:hover { transform: translateY(-3px); }
        .dash-stock { border-color: #198754; }
        .dash-contract { border-color: #0d6efd; }
        
        .form-label { font-weight: bold; font-size: 0.85rem; color: #495057; margin-bottom: 0.2rem; }
        .read-only-field { background-color: #e9ecef; cursor: not-allowed; font-weight: bold; color: #495057; border: 1px solid #ced4da; }
        .qty-box { background-color: #e2e6ea; border: 2px solid #6c757d; color: #212529; font-size: 1.8rem; font-weight: bold; padding: 15px; text-align: center; border-radius: 8px; }
        .picking-box { background-color: #212529; color: #ffc107; font-family: 'Courier New', monospace; font-size: 1.3rem; padding: 15px; text-align: center; border-radius: 8px; font-weight: bold; letter-spacing: 1px; }
        
        /* Cumulative HD Info Box */
        .hd-summary-box { background-color: #cff4fc; border: 1px solid #b6effb; color: #055160; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .hd-sum-val { font-weight: 800; font-size: 1.1em; }
        .hd-sum-label { text-transform: uppercase; font-size: 0.8rem; color: #055160; opacity: 0.8; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid px-4 mt-4 mb-5">
    
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-primary fw-bold mb-0">🏫 PSS Management Hub</h3>
            <small class="text-muted">Centralized Monitoring & Operations</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <form method="GET" class="d-flex align-items-center">
                <label class="me-2 fw-bold">Cycle:</label>
                <select name="co" class="form-select form-select-sm fw-bold border-primary" onchange="this.form.submit()">
                    <?php foreach($co_list as $co): ?>
                        <option value="<?= $co['co_number'] ?>" <?= $selected_co == $co['co_number'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['co_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="index.php" class="btn btn-outline-dark btn-sm">🏠 Home</a>
        </div>
    </div>

    <!-- DASHBOARD ROW -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm dash-card dash-stock">
                <div class="card-body py-2">
                    <small class="text-muted">Current Stock</small>
                    <h3 class="mb-0 fw-bold text-success"><?= number_format($stats_stock['total_stock'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm dash-card dash-contract">
                <div class="card-body py-2">
                    <small class="text-muted">Total Schools (<?= $selected_co ?>)</small>
                    <h3 class="mb-0 fw-bold text-primary"><?= number_format($stats_contract['total_schools'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <small class="text-muted">Total Students</small>
                        <h4 class="mb-0 fw-bold"><?= number_format($stats_contract['total_students'] ?? 0) ?></h4>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Total Packs Required</small>
                        <h4 class="mb-0 fw-bold text-warning"><?= number_format(($stats_contract['total_students'] ?? 0) * 44) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- LEFT: CREATE DO FORM -->
        <div class="col-lg-8">
            <form method="POST" action="api/save_delivery.php">
                <input type="hidden" name="co_number" value="<?= htmlspecialchars($selected_co) ?>">
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between">
                        <span>📝 Create Delivery Order</span>
                        <span><?= date('d M Y') ?></span>
                    </div>
                    <div class="card-body">
                        
                        <!-- 1. HD SELECTOR (FILTER) -->
                        <div class="mb-3">
                            <label class="form-label">Select HD/Contractor (Filter)</label>
                            <select name="hd_id" id="hd_filter" class="form-select select2" onchange="filterSchoolsByHD()">
                                <option value="all">All HDs</option>
                                <?php foreach($hds as $hd): ?>
                                    <option value="<?= $hd['id'] ?>"><?= htmlspecialchars($hd['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecting an HD will filter the School list below.</small>
                        </div>

                        <!-- NEW: CUMULATIVE STATS FOR SELECTED HD -->
                        <div class="hd-summary-box" id="hd_stats_panel">
                            <div class="row text-center">
                                <div class="col-md-4 border-end border-info">
                                    <small class="hd-sum-label">Assigned Schools</small><br>
                                    <span class="hd-sum-val" id="hd_total_schools">0</span>
                                </div>
                                <div class="col-md-4 border-end border-info">
                                    <small class="hd-sum-label">Total Students</small><br>
                                    <span class="hd-sum-val" id="hd_total_students">0</span>
                                </div>
                                <div class="col-md-4">
                                    <small class="hd-sum-label">Total Packs Needed</small><br>
                                    <span class="hd-sum-val text-primary" id="hd_total_packs">0</span> <small class="text-muted">Pcs</small>
                                </div>
                            </div>
                            <div id="hd_warning" class="alert alert-danger mt-2 mb-0 p-1 text-center small fw-bold" style="display:none;">
                                ⚠️ No schools assigned to this HD in the system.
                            </div>
                        </div>

                        <hr class="mb-4">

                        <!-- 2. SCHOOL DATA GRID (RESIZED COLUMNS) -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">School Name / Kod</label>
                                <select name="school_id" class="form-select select2" id="school_select" onchange="updateQuota()">
                                    <option value="">-- Select School --</option>
                                    <?php foreach($schools as $s): ?>
                                        <option value="<?= $s['id'] ?>" 
                                                data-hd="<?= $s['default_hd_id'] ?>"
                                                data-students="<?= $s['student_count'] ?>"
                                                data-sap="<?= htmlspecialchars($s['sap_no'] ?? '') ?>">
                                            <?= htmlspecialchars($s['school_code']) ?> - <?= htmlspecialchars($s['school_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- RESIZED: Was col-md-3, Now col-md-2 -->
                            <div class="col-md-2">
                                <label class="form-label">Bil Pelajar</label>
                                <input type="text" id="disp_students" class="form-control read-only-field text-center" readonly>
                            </div>
                            <!-- RESIZED: Was col-md-3, Now col-md-4 (More space for SAP) -->
                            <div class="col-md-4">
                                <label class="form-label">SAP No (Display)</label>
                                <input type="text" id="disp_sap" class="form-control read-only-field text-center" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Bil TP</label>
                                <input type="number" id="bil_tp" class="form-control fw-bold text-center border-primary" value="44" oninput="filterSchoolsByHD(); updateQuota();"> 
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qty Needed:</label>
                                <input type="text" id="calc_packs_small" class="form-control read-only-field text-center" readonly>
                            </div>
                             <div class="col-md-6">
                                <label class="form-label">Batch Allocation</label>
                                <select name="inventory_batch_id" class="form-select border-success fw-bold" required>
                                    <?php foreach($batches as $b): ?>
                                        <option value="<?= $b['id'] ?>">
                                            <?= $b['batch_no'] ?> (Exp: <?= $b['expiry_date'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- QTY BOX -->
                        <div class="mb-4">
                            <label class="form-label">📦 Qty Needed (Exact):</label>
                            <div class="qty-box">
                                <span id="calc_packs_big">0</span> Packs Needed
                            </div>
                            <input type="hidden" name="qty" id="real_qty_cartons">
                        </div>

                        <!-- PICKING BOX -->
                        <div class="mb-4">
                            <label class="form-label">Picking:</label>
                            <div class="picking-box">
                                <span id="pick_plt">0</span> Plt + <span id="pick_ctn">0</span> Ctn + <span id="pick_pcs">0</span> Pcs
                            </div>
                        </div>

                        <!-- Logistics (Hidden) -->
                        <input type="hidden" name="vehicle_plate" value="TBA">
                        <input type="hidden" name="delivery_date" value="<?= date('Y-m-d') ?>">

                        <!-- SUBMIT -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold py-3 shadow">✅ CONFIRM & GENERATE DO</button>
                        </div>

                    </div>
                </div>
            </form>
        </div>

        <!-- RIGHT COLUMN: RECENT HISTORY -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-secondary text-white fw-bold">🕒 Recent History</div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0 table-sm" style="font-size: 0.8rem;">
                        <thead class="table-light">
                            <tr>
                                <th>DO No</th>
                                <th>Date</th>
                                <th>School</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_deliveries as $row): ?>
                            <tr>
                                <td class="fw-bold text-primary ps-2"><?= htmlspecialchars($row['do_number']) ?></td>
                                <td><?= date('d/m', strtotime($row['delivery_date'])) ?></td>
                                <td class="text-truncate" style="max-width: 120px;"><?= htmlspecialchars($row['school_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });
        filterSchoolsByHD();
    });

    function filterSchoolsByHD() {
        var selectedHD = $('#hd_filter').val();
        var $schoolSelect = $('#school_select');
        
        let hdTotalSchools = 0;
        let hdTotalStudents = 0;
        
        $schoolSelect.find('option').each(function() {
            var hdId = $(this).data('hd');
            var val = $(this).val();
            if (val === "") return;

            if (selectedHD === 'all' || hdId == selectedHD) {
                $(this).prop('disabled', false);
                hdTotalSchools++;
                hdTotalStudents += parseInt($(this).data('students')) || 0;
            } else {
                $(this).prop('disabled', true);
            }
        });

        if ($schoolSelect.find(':selected').prop('disabled')) {
            $schoolSelect.val('').trigger('change');
        } else {
            $schoolSelect.trigger('change.select2'); 
        }

        var bilTp = parseInt($('#bil_tp').val()) || 44;
        var totalPacks = hdTotalStudents * bilTp;
        
        $('#hd_total_schools').text(hdTotalSchools.toLocaleString());
        $('#hd_total_students').text(hdTotalStudents.toLocaleString());
        $('#hd_total_packs').text(totalPacks.toLocaleString());

        if (hdTotalSchools === 0 && selectedHD !== 'all') {
            $('#hd_warning').show();
        } else {
            $('#hd_warning').hide();
        }
        $('#hd_stats_panel').show();
    }

    function updateQuota() {
        var selected = $('#school_select').find(':selected');
        
        if (!selected.val()) {
             $('#disp_students').val('');
             $('#disp_sap').val('');
             $('#calc_packs_small').val('');
             $('#calc_packs_big').text('0');
             $('#pick_plt').text('0');
             $('#pick_ctn').text('0');
             $('#pick_pcs').text('0');
             return;
        }

        var studentCount = parseInt(selected.data('students')) || 0;
        
        $('#disp_students').val(studentCount);
        $('#disp_sap').val(selected.data('sap') || '-');

        var bilTp = parseInt($('#bil_tp').val()) || 44;
        var totalPacks = studentCount * bilTp;
        
        const PACKS_PER_CTN = 24;
        const CTN_PER_PLT = 144;
        const PACKS_PER_PLT = PACKS_PER_CTN * CTN_PER_PLT; 

        var fullPallets = Math.floor(totalPacks / PACKS_PER_PLT);
        var remainder = totalPacks % PACKS_PER_PLT;
        var fullCartons = Math.floor(remainder / PACKS_PER_CTN);
        var loosePieces = remainder % PACKS_PER_CTN;

        $('#calc_packs_small').val(totalPacks.toLocaleString() + " Packs");
        $('#calc_packs_big').text(totalPacks.toLocaleString() + " Packs Needed");
        
        $('#pick_plt').text(fullPallets);
        $('#pick_ctn').text(fullCartons);
        $('#pick_pcs').text(loosePieces);

        var totalCartonsFloat = totalPacks / 24;
        $('#real_qty_cartons').val(totalCartonsFloat);
    }
</script>

</body>
</html>