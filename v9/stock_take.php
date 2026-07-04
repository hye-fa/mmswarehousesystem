<?php
// stock_take.php
// PHYSICAL STOCK TAKE MODULE
// Allows staff to count actual stock and adjust system records.

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch Current Stock by Batch
// We group by Category -> Product -> Batch for clarity
$sql = "
    SELECT b.id as batch_id, p.name, p.category, p.uom, b.batch_no, b.expiry_date, b.qty_on_hand, b.pallet_type
    FROM inventory_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.qty_on_hand != 0 -- Optional: Show 0 qty items if you want to confirm they are gone?
    ORDER BY p.category, p.name, b.expiry_date ASC
";
$stock_items = $pdo->query($sql)->fetchAll();

$current_cat = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Physical Stock Take</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .variance-positive { color: green; font-weight: bold; }
        .variance-negative { color: red; font-weight: bold; }
        .table-group-header { background-color: #e9ecef; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-dark me-2">🏠 Home</a>
            <a href="reports.php" class="btn btn-outline-secondary">📊 Reports</a>
        </div>
        <h3 class="text-primary">📋 Physical Stock Take</h3>
        <button class="btn btn-success btn-lg" onclick="submitStockTake()">💾 Save Adjustments</button>
    </div>

    <div class="alert alert-info">
        <strong>Instructions:</strong> Enter the <b>Actual Physical Quantity</b> in the input boxes. 
        The system will automatically calculate the variance. Only modified rows will be saved.
    </div>

    <form id="stockTakeForm" action="api/save_stock_adjustment.php" method="POST">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th width="25%">Product Name</th>
                                <th width="10%">Batch No</th>
                                <th width="10%">Expiry</th>
                                <th width="10%">System Qty</th>
                                <th width="12%">Physical Qty</th>
                                <th width="10%">Variance</th>
                                <th width="23%">Reason / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($stock_items)): ?>
                                <tr><td colspan="7" class="text-center p-4">No active stock found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($stock_items as $item): ?>
                                    <?php 
                                        // Group Header logic
                                        if ($current_cat != $item['category']): 
                                            $current_cat = $item['category'];
                                    ?>
                                        <tr class="table-group-header">
                                            <td colspan="7" class="text-uppercase text-secondary ps-3">
                                                📁 <?= htmlspecialchars($current_cat) ?> SECTION
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr data-batch-id="<?= $item['batch_id'] ?>">
                                        <td>
                                            <?= htmlspecialchars($item['name']) ?>
                                            <small class="text-muted d-block"><?= $item['uom'] ?></small>
                                        </td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($item['batch_no']) ?></td>
                                        <td><?= $item['expiry_date'] ?></td>
                                        <td class="text-center bg-light">
                                            <span id="sys_<?= $item['batch_id'] ?>" class="fw-bold"><?= $item['qty_on_hand'] ?></span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="adjustments[<?= $item['batch_id'] ?>][actual_qty]" 
                                                   id="act_<?= $item['batch_id'] ?>" 
                                                   class="form-control form-control-sm fw-bold border-primary text-center" 
                                                   placeholder="<?= $item['qty_on_hand'] ?>"
                                                   oninput="calcVariance(<?= $item['batch_id'] ?>)">
                                        </td>
                                        <td class="text-center">
                                            <span id="var_<?= $item['batch_id'] ?>">-</span>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="adjustments[<?= $item['batch_id'] ?>][reason]" 
                                                   class="form-control form-control-sm" 
                                                   placeholder="Reason (if mismatch)...">
                                            <!-- Hidden Inputs for Original Data -->
                                            <input type="hidden" name="adjustments[<?= $item['batch_id'] ?>][system_qty]" value="<?= $item['qty_on_hand'] ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="d-grid mt-4 mb-5">
            <button type="submit" class="btn btn-success btn-lg py-3 fw-bold">✅ CONFIRM & UPDATE STOCK</button>
        </div>
    </form>
</div>

<script>
    function calcVariance(id) {
        const sysQty = parseInt(document.getElementById('sys_' + id).innerText) || 0;
        const actInput = document.getElementById('act_' + id);
        const varDisplay = document.getElementById('var_' + id);

        if (actInput.value === '') {
            varDisplay.innerText = '-';
            varDisplay.className = '';
            return;
        }

        const actQty = parseInt(actInput.value) || 0;
        const diff = actQty - sysQty;

        if (diff > 0) {
            varDisplay.innerText = '+' + diff;
            varDisplay.className = 'variance-positive';
        } else if (diff < 0) {
            varDisplay.innerText = diff;
            varDisplay.className = 'variance-negative';
        } else {
            varDisplay.innerText = 'OK';
            varDisplay.className = 'text-success fw-bold';
        }
    }

    function submitStockTake() {
        document.getElementById('stockTakeForm').submit();
    }
</script>

</body>
</html>