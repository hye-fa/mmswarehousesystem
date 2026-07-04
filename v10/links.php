<?php
// links.php
// WMS SYSTEM MAP
// UPDATED: Corrected PSS Delivery Link

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
        .link-card { transition: transform 0.2s; }
        .link-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .icon-box { font-size: 1.5rem; margin-right: 10px; }
        .section-title { border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; color: #495057; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold text-primary">📦 MMS Warehouse System</h1>
        <p class="lead text-muted">Quick Navigation & Module Map</p>
        <span class="badge bg-success">🔒 Secure Connection Active</span>
    </div>

    <div class="row g-4">

        <!-- 1. DASHBOARD -->
        <div class="col-12 mb-3">
            <div class="card border-primary shadow-sm link-card">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box">🏠</div>
                    <div class="flex-grow-1">
                        <h4 class="card-title mb-0"><a href="index.php" class="text-decoration-none stretched-link">Main Dashboard</a></h4>
                        <p class="card-text text-muted small">Overview of Pallet Liability, Stock Alerts, and Quick Actions.</p>
                    </div>
                    <span class="badge bg-primary rounded-pill">Start Here</span>
                </div>
            </div>
        </div>

        <!-- 2. INBOUND (RECEIVING) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-success text-white fw-bold">📥 Inbound Operations</div>
                <div class="list-group list-group-flush">
                    <a href="receiving_multi.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Multi-Item Receiving (Primary)</div>
                            <small class="text-muted">Receive mixed trucks, single DO, multiple items. Features QR Scanning & Pallet Tally.</small>
                        </div>
                    </a>
                    <a href="receiving.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Single Item Receiving</div>
                            <small class="text-muted">Simple form for scanning one specific item batch quickly.</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- 3. OUTBOUND (DELIVERY) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-warning text-dark fw-bold">📤 Outbound Operations</div>
                <div class="list-group list-group-flush">
                    <!-- FIXED LINK -->
                    <a href="pss_delivery.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">PSS Delivery (Schools)</div>
                            <small class="text-muted">Create DOs for school contracts. Tracks stock deduction and driver liability.</small>
                        </div>
                    </a>
                    <a href="commercial_outbound.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Commercial Outbound</div>
                            <small class="text-muted">Record stock leaving for Retail/Shops (7-Eleven, etc).</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- 4. MONITORING & CONTROL -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-info text-white fw-bold">📊 Monitoring & Reports</div>
                <div class="list-group list-group-flush">
                    <a href="reports.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Warehouse Monitor / Trace</div>
                            <small class="text-muted">Search Items by Batch/Expiry. View current stock balance and inbound logs.</small>
                        </div>
                    </a>
                    <a href="reconcile.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Daily Reconciliation</div>
                            <small class="text-muted">Compare WMS Outbound totals vs Accounting Invoices (Financial Audit).</small>
                        </div>
                    </a>
                    <a href="stock_take.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Physical Stock Take</div>
                            <small class="text-muted">Perform stock counts and adjust quantities. Tracks variances.</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- 5. SETUP & TOOLS -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-secondary text-white fw-bold">⚙️ Setup & Tools</div>
                <div class="list-group list-group-flush">
                    <a href="import_schools.php" class="list-group-item list-group-item-action d-flex align-items-start">
                        <div class="ms-2 me-auto">
                            <div class="fw-bold">Import School Database</div>
                            <small class="text-muted">Upload CSV to update Student Counts & HDs for new Contract Orders.</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>