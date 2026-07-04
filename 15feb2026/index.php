<?php
// index.php - Fixed Layout for Susumura WMS
require_once 'config/db.php';

// Force HTTPS
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
    <title>MMS WMS | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --mms-red: #dc3545; --mms-dark: #212529; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        /* FIX: Adjusted padding and display to prevent overlap */
        .section-header { 
            border-left: 5px solid var(--mms-red); 
            padding: 5px 0 5px 15px; 
            margin-bottom: 20px; 
            font-weight: 700; 
            color: var(--mms-dark);
            display: flex;
            align-items: center;
        }

        .nav-card { 
            transition: all 0.3s ease; 
            border: none; 
            border-radius: 12px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background: #ffffff;
            overflow: hidden; /* Ensures content stays inside rounded corners */
        }
        
        .nav-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        
        .list-group-item { 
            border: none; 
            padding: 1rem 1.25rem; 
            display: flex;
            align-items: center;
        }
        
        .list-group-item:hover { background-color: #f9fafb; }

        .icon-box { 
            width: 42px; 
            height: 42px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 8px; 
            margin-right: 15px;
            flex-shrink: 0; /* Prevents icon from squishing */
        }

        .menu-title { font-weight: 600; color: #333; margin-bottom: 2px; }
        .menu-sub { font-size: 0.82rem; color: #6c757d; display: block; }
    </style>
</head>
<body class="py-4">

<div class="container">
    <div class="row mb-4 align-items-center">
        <div class="col-8">
            <h2 class="fw-bold text-dark mb-1">MMS Warehouse</h2>
            <p class="text-muted small">Susumura Daily Distribution & Inventory Control</p>
        </div>
        <div class="col-4 text-end">
            <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-calendar3 me-1"></i> <?= date('d M Y') ?>
            </span>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-4 col-md-6">
            <div class="section-header">🚚 Logistics</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="receiving_multi.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-success-subtle text-success"><i class="bi bi-box-arrow-in-down"></i></div>
                        <div>
                            <span class="menu-title">Multi-Item Receiving</span>
                            <span class="menu-sub">GRN & QR Pallet Tally</span>
                        </div>
                    </a>
                    <a href="pss_delivery.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-truck"></i></div>
                        <div>
                            <span class="menu-title">PSS School Delivery</span>
                            <span class="menu-sub">Generate DOs for School Milk</span>
                        </div>
                    </a>
                    <a href="commercial_outbound.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-shop"></i></div>
                        <div>
                            <span class="menu-title">Commercial Outbound</span>
                            <span class="menu-sub">Retail & Customer Stock Out</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="section-header">⚠️ Quality & Loss</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="spoilage_report.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-exclamation-octagon"></i></div>
                        <div>
                            <span class="menu-title">Report Damage/Spoilage</span>
                            <span class="menu-sub">Log losses with photos (pcs)</span>
                        </div>
                    </a>
                    <a href="spoilage_list.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-file-earmark-medical"></i></div>
                        <div>
                            <span class="menu-title">Supplier Claims Dashboard</span>
                            <span class="menu-sub">Monitor CNs & Documentation</span>
                        </div>
                    </a>
                    <a href="reconcile.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-warning-subtle text-warning-emphasis"><i class="bi bi-arrow-repeat"></i></div>
                        <div>
                            <span class="menu-title">Daily Reconciliation</span>
                            <span class="menu-sub">Verify physical vs system stock</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="section-header">⚙️ Administration</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="import_co_ui.html" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-secondary-subtle text-secondary"><i class="bi bi-file-earmark-arrow-up"></i></div>
                        <div>
                            <span class="menu-title">Import Monthly CO</span>
                            <span class="menu-sub">CSV to SAP Generator</span>
                        </div>
                    </a>
                    <a href="view_batch.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-secondary-subtle text-secondary"><i class="bi bi-folder2-open"></i></div>
                        <div>
                            <span class="menu-title">View Batch Reports</span>
                            <span class="menu-sub">Access Generated SAP Lists</span>
                        </div>
                    </a>
                    <a href="import_schools.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-secondary-subtle text-secondary"><i class="bi bi-database-up"></i></div>
                        <div>
                            <span class="menu-title">Master School DB Update</span>
                            <span class="menu-sub">Bulk Address & Count Updates</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>