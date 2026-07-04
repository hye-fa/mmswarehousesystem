<?php
// reconcile.php
// COMMERCIAL RECONCILIATION ONLY

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

$date = $_GET['date'] ?? date('Y-m-d');

// 1. FETCH COMMERCIAL OUTBOUND TOTAL
// Summing all items sent to "Commercial" category today
$sql_comm = "
    SELECT SUM(i.qty) as total_qty
    FROM outbound_items i
    JOIN outbound_logs l ON i.outbound_id = l.id
    WHERE l.date = ? AND l.category = 'Commercial'
";
$stmt = $pdo->prepare($sql_comm);
$stmt->execute([$date]);
$comm_sys_qty = $stmt->fetch()['total_qty'] ?? 0;

// 2. FETCH SAVED RECON DATA
$sql_recon = "SELECT * FROM daily_reconciliation WHERE date = ? AND category = 'Commercial'";
$stmt = $pdo->prepare($sql_recon);
$stmt->execute([$date]);
$saved = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Commercial Reconciliation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-match { border-left: 5px solid #198754; }
        .card-mismatch { border-left: 5px solid #dc3545; background-color: #fff5f5; }
        .total-box { font-size: 2rem; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-dark">🏠 Home</a>
        <h3 class="text-secondary">⚖️ Commercial Stock Reconciliation</h3>
        <form class="d-flex">
            <input type="date" name="date" class="form-control me-2" value="<?= $date ?>">
            <button type="submit" class="btn btn-primary">Check Date</button>
        </form>
    </div>

    <form action="api/save_reconciliation.php" method="POST">
        <input type="hidden" name="date" value="<?= $date ?>">
        <input type="hidden" name="category" value="Commercial">
        <input type="hidden" name="system_qty" value="<?= $comm_sys_qty ?>">

        <?php 
            $inv_qty = $saved['invoice_qty_cartons'] ?? 0;
            $variance = $comm_sys_qty - $inv_qty;
            $status = ($variance == 0) ? 'MATCH' : 'MISMATCH';
            $color = ($variance == 0) ? 'text-success' : 'text-danger';
        ?>

        <div class="card shadow-lg <?= ($variance == 0) ? 'card-match' : 'card-mismatch' ?>">
            <div class="card-header fw-bold">Commercial Outbound - <?= date('d M Y', strtotime($date)) ?></div>
            <div class="card-body text-center">
                <div class="row">
                    
                    <!-- SYSTEM DATA -->
                    <div class="col-md-4 border-end">
                        <h6 class="text-muted text-uppercase">WMS System Out</h6>
                        <div class="total-box text-primary"><?= number_format($comm_sys_qty) ?></div>
                        <small>Cartons recorded in "Commercial Outbound"</small>
                    </div>

                    <!-- USER INPUT -->
                    <div class="col-md-4 border-end">
                        <h6 class="text-muted text-uppercase">Accounting Invoice Qty</h6>
                        <input type="number" name="invoice_qty" class="form-control form-control-lg text-center fw-bold" 
                               value="<?= $inv_qty ?>" style="font-size: 1.5rem;">
                        <input type="text" name="invoice_nos" class="form-control form-control-sm mt-2" 
                               placeholder="Invoice #s (Optional)" value="<?= $saved['invoice_numbers'] ?? '' ?>">
                    </div>

                    <!-- RESULT -->
                    <div class="col-md-4">
                        <h6 class="text-muted text-uppercase">Variance</h6>
                        <div class="total-box <?= $color ?>">
                            <?= $variance > 0 ? "+$variance" : $variance ?>
                        </div>
                        <span class="badge <?= ($variance == 0) ? 'bg-success' : 'bg-danger' ?>"><?= $status ?></span>
                    </div>

                </div>

                <?php if($variance != 0): ?>
                <div class="mt-3">
                    <textarea name="reason" class="form-control" placeholder="Explain the difference (e.g. Sample given, Return pending)..."><?= $saved['reason'] ?? '' ?></textarea>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success px-5">💾 Verify & Save</button>
            </div>
        </div>
    </form>
    
    <div class="text-center mt-4">
        <a href="commercial_outbound.php" class="btn btn-outline-secondary">Go to Outbound Form</a>
    </div>
</div>

</body>
</html>