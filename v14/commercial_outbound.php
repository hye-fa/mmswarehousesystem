<?php
// commercial_outbound.php
// Record Stock Leaving for Commercial/Retail Customers

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch Commercial Products
$products = $pdo->query("SELECT id, name, category FROM products WHERE category != 'PSS' AND is_active=1 ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Commercial Outbound</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="index.php" class="btn btn-outline-dark me-2">🏠 Home</a>
            <a href="reconcile.php" class="btn btn-outline-primary">⚖️ Reconciliation</a>
        </div>
        <h3 class="text-success">📤 Commercial Outbound</h3>
    </div>

    <form action="api/save_commercial_outbound.php" method="POST">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label>Date</label>
                        <input type="date" name="out_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>Customer / Shop Name</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="e.g. 7-Eleven KT" required>
                    </div>
                    <div class="col-md-3">
                        <label>DO / Invoice Ref</label>
                        <input type="text" name="doc_ref" class="form-control" placeholder="e.g. INV-2025-001">
                    </div>
                    <div class="col-md-3">
                        <label>Vehicle / Driver</label>
                        <input type="text" name="vehicle" class="form-control" placeholder="e.g. VAB 1234">
                    </div>
                </div>
                
                <hr>
                
                <table class="table table-bordered" id="outTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th width="15%">Batch (Optional)</th>
                            <th width="15%">Qty (Ctn)</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody id="outBody">
                        <tr>
                            <td>
                                <select name="items[0][product_id]" class="form-select" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach($products as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="items[0][batch]" class="form-control" placeholder="Batch"></td>
                            <td><input type="number" name="items[0][qty]" class="form-control" required min="1"></td>
                            <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">+ Add Item</button>
                
                <div class="mt-4 d-grid">
                    <button type="submit" class="btn btn-success btn-lg">Confirm Stock Out</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let rowCount = 1;
    const products = <?php echo json_encode($products); ?>;
    
    function addRow() {
        let options = '<option value="">-- Select --</option>';
        products.forEach(p => options += `<option value="${p.id}">${p.name}</option>`);
        
        const html = `
            <tr>
                <td><select name="items[${rowCount}][product_id]" class="form-select" required>${options}</select></td>
                <td><input type="text" name="items[${rowCount}][batch]" class="form-control"></td>
                <td><input type="number" name="items[${rowCount}][qty]" class="form-control" required min="1"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button></td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        rowCount++;
    }

    function removeRow(btn) { btn.closest('tr').remove(); }
</script>
</body>
</html>