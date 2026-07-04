<?php
// reports.php
// MONITORING DASHBOARD: Updated with 5 Pallet Types

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// --- 1. TRACEABILITY SEARCH ---
$trace_results = [];
$search_batch = $_GET['search_batch'] ?? '';
$search_expiry = $_GET['search_expiry'] ?? '';
$search_product = $_GET['search_product'] ?? '';

if (!empty($search_batch) || !empty($search_expiry) || !empty($search_product)) {
    $sql_trace = "
        SELECT 
            p.name as product_name,
            i.batch_no, 
            i.qty_received, 
            i.expiry_date, 
            l.supplier_do, 
            l.received_date
        FROM inbound_items i
        JOIN inbound_logs l ON i.inbound_id = l.id
        JOIN products p ON i.product_id = p.id
        WHERE 1=1
    ";
    
    $params = [];
    if (!empty($search_batch)) {
        $sql_trace .= " AND i.batch_no LIKE ?";
        $params[] = "%$search_batch%";
    }
    if (!empty($search_expiry)) {
        $sql_trace .= " AND i.expiry_date = ?"; 
        $params[] = $search_expiry;
    }
    if (!empty($search_product)) {
        $sql_trace .= " AND p.name LIKE ?";
        $params[] = "%$search_product%";
    }
    $sql_trace .= " ORDER BY l.received_date DESC";
    
    try {
        $stmt = $pdo->prepare($sql_trace);
        $stmt->execute($params);
        $trace_results = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_msg = "Search Error: " . $e->getMessage();
    }
}

// 2. Fetch Logs
$sql_inbound = "
    SELECT l.id, l.received_date, l.supplier_do, l.category, 
           COUNT(i.id) as item_count, 
           (COALESCE(l.pallet_qty_loscam_red, 0) + 
            COALESCE(l.pallet_qty_ffm_orange, 0) + 
            COALESCE(l.pallet_qty_ffm_green, 0) + 
            COALESCE(l.pallet_qty_lhp_green, 0) + 
            COALESCE(l.pallet_qty_plastic_black, 0)) as total_pallets
    FROM inbound_logs l
    LEFT JOIN inbound_items i ON l.id = i.inbound_id
    GROUP BY l.id
    ORDER BY l.received_date DESC
    LIMIT 50
";
$inbound_logs = $pdo->query($sql_inbound)->fetchAll();

// 3. Fetch Stock
$sql_stock = "
    SELECT p.name, p.category, SUM(b.qty_on_hand) as total_qty, p.uom
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.qty_on_hand > 0
    GROUP BY p.id
    ORDER BY p.category, p.name ASC
";
$stock_balance = $pdo->query($sql_stock)->fetchAll();

// 4. Pallet Liability (UPDATED)
$sql_pallets = "
    SELECT 
        SUM(pallet_qty_loscam_red) as red,
        SUM(pallet_qty_ffm_orange) as orange,
        SUM(pallet_qty_ffm_green) as ffm_green,
        SUM(pallet_qty_lhp_green) as lhp_green,
        SUM(pallet_qty_plastic_black) as black
    FROM inbound_logs
";
$pallet_totals = $pdo->query($sql_pallets)->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .card-header { font-weight: bold; }
        .badge-pss { background-color: #0d6efd; }
        .badge-pst { background-color: #198754; }
        .badge-uht { background-color: #ffc107; color: #000; }
        .badge-ice { background-color: #0dcaf0; color: #000; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    
    <!-- NAVIGATION -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-dark me-2">🏠 Home</a>
            <a href="receiving.php" class="btn btn-outline-primary me-2">📥 Single Receive</a>
            <a href="receiving_multi.php" class="btn btn-outline-primary">📥 Multi Receive</a>
        </div>
        <h3 class="text-secondary">📊 Warehouse Monitor</h3>
    </div>

    <!-- TRACEABILITY TOOL -->
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-header bg-primary text-white">🔍 Trace Item</div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Product Name</label>
                    <input type="text" name="search_product" class="form-control" value="<?= htmlspecialchars($search_product ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Batch Number</label>
                    <input type="text" name="search_batch" class="form-control" value="<?= htmlspecialchars($search_batch ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Expiry Date</label>
                    <input type="date" name="search_expiry" class="form-control" value="<?= htmlspecialchars($search_expiry ?? '') ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-50 fw-bold">Search</button>
                    <a href="reports.php" class="btn btn-outline-secondary w-50">Reset</a>
                </div>
            </form>

            <?php if (!empty($trace_results)): ?>
                <div class="mt-3 table-responsive">
                    <h5 class="text-success">Found <?= count($trace_results) ?> Records:</h5>
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Batch</th>
                                <th>Expiry</th>
                                <th>DO No.</th>
                                <th>Recv Date</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trace_results as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['product_name'] ?? '') ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['batch_no'] ?? '') ?></td>
                                <td><?= !empty($row['expiry_date']) ? date('d/m/Y', strtotime($row['expiry_date'])) : '-' ?></td>
                                <td class="fw-bold bg-warning-subtle"><?= htmlspecialchars($row['supplier_do'] ?? 'N/A') ?></td>
                                <td><?= !empty($row['received_date']) ? date('d/m/Y', strtotime($row['received_date'])) : '-' ?></td>
                                <td><?= $row['qty_received'] ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (!empty($search_batch) || !empty($search_product)): ?>
                <div class="alert alert-warning mt-3">No records found matching your criteria.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- STOCK -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">📦 Stock Balance</div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0" id="stockTable">
                        <thead class="table-light">
                            <tr>
                                <th>Cat</th>
                                <th>Product</th>
                                <th class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($stock_balance as $row): ?>
                            <tr>
                                <td><span class="badge badge-<?= strtolower($row['category'] ?? 'uht') ?>"><?= htmlspecialchars($row['category'] ?? '') ?></span></td>
                                <td><?= htmlspecialchars($row['name'] ?? '') ?></td>
                                <td class="text-end fw-bold"><?= number_format($row['total_qty'] ?? 0) ?> <small class="text-muted"><?= htmlspecialchars($row['uom'] ?? '') ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- LOGS -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4 border-warning">
                <div class="card-header bg-warning-subtle">🧱 Total Pallet Liability (Inbound)</div>
                <div class="card-body">
                    <div class="row text-center">
                        <!-- Red -->
                        <div class="col mb-2">
                            <h4 class="text-danger mb-0"><?= number_format($pallet_totals['red'] ?? 0) ?></h4>
                            <small class="text-muted">Loscam Red</small>
                        </div>
                        <!-- Orange -->
                        <div class="col mb-2">
                            <h4 class="text-warning mb-0"><?= number_format($pallet_totals['orange'] ?? 0) ?></h4>
                            <small class="text-muted">FFM Orange</small>
                        </div>
                        <!-- LHP Green -->
                        <div class="col mb-2">
                            <h4 class="text-success mb-0"><?= number_format($pallet_totals['lhp_green'] ?? 0) ?></h4>
                            <small class="text-muted">LHP Green</small>
                        </div>
                        <!-- FFM Green -->
                        <div class="col mb-2">
                            <h4 class="text-success mb-0" style="border-bottom: 2px solid orange;"><?= number_format($pallet_totals['ffm_green'] ?? 0) ?></h4>
                            <small class="text-muted">FFM Green</small>
                        </div>
                        <!-- Black -->
                        <div class="col mb-2">
                            <h4 class="text-dark mb-0"><?= number_format($pallet_totals['black'] ?? 0) ?></h4>
                            <small class="text-muted">Black</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">🚚 Recent Inbound Logs</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>DO Number</th>
                                <th>Cat</th>
                                <th>Items</th>
                                <th>Pallets</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inbound_logs as $log): ?>
                            <tr>
                                <td><?= !empty($log['received_date']) ? date('d/m/Y', strtotime($log['received_date'])) : '-' ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($log['supplier_do'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($log['category'] ?? '') ?></td>
                                <td><?= $log['item_count'] ?? 0 ?></td>
                                <td><?= $log['total_pallets'] ?? 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function () {
        $('#stockTable').DataTable({ "pageLength": 10, "order": [[ 0, "asc" ]] });
    });
</script>

</body>
</html>