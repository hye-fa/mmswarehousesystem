<?php
// links.php
// WMS SYSTEM MAP
// UPDATED: Added Spoilage Reporting & Management Dashboard

// --- FORCE HTTPS REDIRECT ---
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WMS System Map</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .link-card { transition: transform 0.2s; border: none; border-radius: 12px; }
        .link-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .section-title { border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; color: #495057; font-weight: bold; }
        .list-group-item { border-left: none; border-right: none; padding: 15px; }
        .badge-new { font-size: 0.7rem; vertical-align: middle; margin-left: 5px; }
    </style>
</head>
<body class="p-4">

<div class="container">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-dark">MMS Warehouse Management</h1>
        <p class="text-muted">Central Navigation Hub</p>
    </div>

    <div class="row g-4">
        
        <div class="col-md-4">
            <h5 class="section-title text-success">📥 Inbound Logistics</h5>
            <div class="card shadow-sm link-card">
                <div class="list-group list-group-flush">
                    <a href="receiving_multi.php" class="list-group-item list-group-item-action">
                        <div class="fw-bold text-success">Multi-Item Receiving</div>
                        <small class="text-muted">GRN with QR scanning & Pallet Tally.</small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <h5 class="section-title text-primary">📤 Outbound Logistics</h5>
            <div class="card shadow-sm link-card">
                <div class="list-group list-group-flush">
                    <a href="pss_delivery.php" class="list-group-item list-group-item-action">
                        <div class="fw-bold text-primary">PSS School Delivery</div>
                        <small class="text-muted">Generate DOs for School Milk (PSS).</small>
                    </a>
                    <a href="commercial_outbound.php" class="list-group-item list-group-item-action">
                        <div class="fw-bold text-primary">Commercial Outbound</div>
                        <small class="text-muted">Stock out for Retail & Customers.</small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <h5 class="section-title text-danger">⚠️ Inventory Control</h5>
            <div class="card shadow-sm link-card">
                <div class="list-group list-group-flush">
                    <a href="spoilage_report.php" class="list-group-item list-group-item-action">
                        <div class="fw-bold text-danger">Report Spoilage/Damage <span class="badge bg-danger badge-new">NEW</span></div>
                        <small class="text-muted">Log losses with photo evidence.</small>
                    </a>
                    <a href="spoilage_list.php" class="list-group-item list-group-item-action">
                        <div class="fw-bold text-danger">Claim Status Dashboard <span class="badge bg-danger badge-new">NEW</span></div>
                        <small class="text-muted">Monitor supplier refunds & photos.</small>
                    </a>
                    <a href="reconcile.php" class="list-group-item list-group-item-action">
                        <div class="fw-bold text-dark">Daily Reconciliation</div>
                        <small class="text-muted">Compare system qty vs physical invoice.</small>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-12 mt-4">
            <h5 class="section-title text-secondary">⚙️ Administration & Data Imports</h5>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card shadow-sm link-card">
                        <div class="list-group list-group-flush">
                            <a href="import_co_ui.html" class="list-group-item list-group-item-action">
                                <div class="fw-bold text-secondary">Import Monthly CO</div>
                                <small class="text-muted">Upload CSV & Generate SAP Numbers.</small>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm link-card">
                        <div class="list-group list-group-flush">
                            <a href="view_batch.php" class="list-group-item list-group-item-action">
                                <div class="fw-bold text-secondary">View Batch Reports</div>
                                <small class="text-muted">Access generated SAP school lists.</small>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm link-card">
                        <div class="list-group list-group-flush">
                            <a href="import_schools.php" class="list-group-item list-group-item-action">
                                <div class="fw-bold text-secondary">Master School DB Update</div>
                                <small class="text-muted">Bulk update school counts & addresses.</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>