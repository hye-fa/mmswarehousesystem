<?php
// index.php
// The Main Dashboard: Shows Pallet Debt & Stock Alerts
require_once 'config/db.php';

// 1. Fetch Pallet Debt Summary (Who owes us what?)
$sql_pallets = "SELECT h.name, 
                       SUM(pl.qty_red) as red, 
                       SUM(pl.qty_green) as green, 
                       SUM(pl.qty_orange) as orange 
                FROM hds h
                LEFT JOIN pallet_ledger pl ON h.id = pl.hd_id
                WHERE h.status = 'Active'
                GROUP BY h.id";
$stmt = $pdo->query($sql_pallets);
$pallet_debts = $stmt->fetchAll();

// 2. Fetch Low Stock Alerts (PSS Only for now)
$sql_stock = "SELECT product_id, SUM(qty_on_hand) as total 
              FROM inventory_batches 
              GROUP BY product_id 
              HAVING total < 500"; // Alert if below 500 cartons
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-debt { border-left: 5px solid #dc3545; }
        .card-stock { border-left: 5px solid #198754; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Warehouse System</a>
        <div>
            <a href="receiving.php" class="btn btn-success me-2">Receiving (Inbound)</a>
            <a href="pss_delivery.php" class="btn btn-primary">PSS Delivery (Outbound)</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <!-- Pallet Liability Widget -->
        <div class="col-md-6">
            <div class="card card-debt shadow-sm">
                <div class="card-header bg-white fw-bold">🚩 Pallet Liability (HD Debt)</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>HD Name</th>
                                <th class="text-danger">Red (Loscam)</th>
                                <th class="text-success">Green (LHP)</th>
                                <th class="text-warning">Orange (FFM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pallet_debts as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td class="fw-bold"><?= $row['red'] ?: 0 ?></td>
                                <td><?= $row['green'] ?: 0 ?></td>
                                <td><?= $row['orange'] ?: 0 ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- System Alerts -->
        <div class="col-md-6">
            <div class="card card-stock shadow-sm">
                <div class="card-header bg-white fw-bold">📦 Quick Actions</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-danger">Report Spoilage / Damage</button>
                        <button class="btn btn-outline-secondary">Upload Initial Balance</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>