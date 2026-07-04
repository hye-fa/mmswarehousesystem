<?php
// reports.php
// MONITORING DASHBOARD: Inbound Logs, Stock Balance, Pallet Status
// FIXED: PHP 8.1+ Deprecation Warning (null to htmlspecialchars)

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// 1. Fetch Recent Inbound Logs (Last 50)
// Added COALESCE to SQL to ensure numeric values return 0 instead of NULL
$sql_inbound = "
    SELECT l.id, l.received_date, l.supplier_do, l.category, 
           COUNT(i.id) as item_count, 
           (COALESCE(l.pallet_qty_loscam_red, 0) + 
            COALESCE(l.pallet_qty_loscam_red, 0) + 
            COALESCE(l.pallet_qty_ffm_orange, 0) + 
            COALESCE(l.pallet_qty_plastic_black, 0)) as total_pallets
    FROM inbound_logs l
    LEFT JOIN inbound_items i ON l.id = i.inbound_id
    GROUP BY l.id
    ORDER BY l.received_date DESC
    LIMIT 50
";
$inbound_logs = $pdo->query($sql_inbound)->fetchAll();

// 2. Fetch Current Stock Balance
$sql_stock = "
    SELECT p.name, p.category, SUM(b.qty_on_hand) as total_qty, p.uom
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.qty_on_hand > 0
    GROUP BY p.id
    ORDER BY p.category, p.name ASC
";
$stock_balance = $pdo->query($sql_stock)->fetchAll();

// 3. Fetch Pallet Liability
$sql_pallets = "
    SELECT 
        SUM(pallet_qty_loscam_red) as red,
        SUM(pallet_qty_ffm_orange) as orange,
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
    <!-- DataTables CSS -->
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

    <div class="row">
        
        <!-- COL 1: STOCK BALANCE -->
        <div class="col-md-6">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">📦 Current Stock Balance</div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0" id="stockTable">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Product Name</th>
                                <th class="text-end">Qty Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($stock_balance as $row): ?>
                            <tr>
                                <td><span class="badge badge-<?= strtolower($row['category'] ?? 'uht') ?>"><?= htmlspecialchars($row['category'] ?? 'Unknown') ?></span></td>
                                <td><?= htmlspecialchars($row['name'] ?? 'Unknown Product') ?></td>
                                <td class="text-end fw-bold"><?= number_format($row['total_qty'] ?? 0) ?> <small class="text-muted"><?= htmlspecialchars($row['uom'] ?? '') ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- COL 2: INBOUND LOGS & PALLETS -->
        <div class="col-md-6">
            
            <!-- PALLET WIDGET -->
            <div class="card shadow-sm mb-4 border-warning">
                <div class="card-header bg-warning-subtle">🧱 Total Pallet Liability (Inbound Accumulation)</div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col">
                            <h4 class="text-danger mb-0"><?= number_format($pallet_totals['red'] ?? 0) ?></h4>
                            <small class="text-muted">Loscam Red</small>
                        </div>
                        <div class="col">
                            <h4 class="text-warning mb-0"><?= number_format($pallet_totals['orange'] ?? 0) ?></h4>
                            <small class="text-muted">FFM Orange</small>
                        </div>
                        <div class="col">
                            <h4 class="text-dark mb-0"><?= number_format($pallet_totals['black'] ?? 0) ?></h4>
                            <small class="text-muted">Plastic Black</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT LOGS -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">🚚 Recent Inbound Logs (GRN)</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>DO Number</th>
                                <th>Category</th>
                                <th>Items</th>
                                <th>Pallets</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($inbound_logs as $log): ?>
                            <tr>
                                <!-- FIXED: Added '??' to handle nulls -->
                                <td><?= date('d/m/Y', strtotime($log['received_date'] ?? 'now')) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($log['supplier_do'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($log['category'] ?? 'General') ?></td>
                                <td><?= $log['item_count'] ?? 0 ?> items</td>
                                <td><?= $log['total_pallets'] ?? 0 ?></td>
                                <td><a href="#" class="btn btn-sm btn-outline-info">View</a></td>
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
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#stockTable').DataTable({
            "pageLength": 10,
            "order": [[ 0, "asc" ]] // Sort by Category
        });
    });
</script>

</body>
</html>