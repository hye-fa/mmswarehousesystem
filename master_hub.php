<?php
// master_hub.php - REORGANIZED COMMAND CENTER
require_once 'config/db.php';
require_once 'includes/header.php';

try {
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {
    $total_products = "0";
}


$page_title = 'MMS Master Hub | Susumura';

?>
<style>
    .section-header { 
        border-left: 5px solid #dc3545; 
        padding: 8px 15px; 
        margin-bottom: 20px; 
        font-weight: 800; 
        color: #212529;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 1.2px;
        background: rgba(220, 53, 69, 0.05);
    }
    .header-blue { border-left-color: #0d6efd; background: rgba(13, 110, 253, 0.05); }
    .header-green { border-left-color: #198754; background: rgba(25, 135, 84, 0.05); }

    .nav-card { 
        transition: all 0.25s ease; 
        border: none; 
        border-radius: 16px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        background: #fff;
        height: 100%;
    }
    .nav-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
    
    .list-group-item { 
        border: none; 
        padding: 1.15rem; 
        display: flex;
        align-items: center;
        border-bottom: 1px solid #f1f5f9 !important;
    }
    .list-group-item:last-child { border-bottom: none !important; }
    .list-group-item:hover { background-color: #f8fafc; }

    .icon-box { 
        width: 44px; height: 44px; 
        display: flex; align-items: center; justify-content: center; 
        border-radius: 12px; margin-right: 15px; flex-shrink: 0;
        font-size: 1.3rem;
    }

    .menu-title { font-weight: 700; color: #1e293b; margin-bottom: 1px; display: block; }
    .menu-sub { font-size: 0.8rem; color: #64748b; display: block; }
</style>
<div class="mt-4">

<div class="container">
    <div class="row mb-5 align-items-center">
        <div class="col-8">
            <h1 class="fw-bold text-dark mb-0 h2">MMS Master Hub</h1>
            <p class="text-muted small mb-0">Susumura Inventory & Logistics Management</p>
        </div>
        <div class="col-4 text-end">
            <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-box-seam me-1 text-primary"></i> <?= $total_products ?> SKUs ACTIVE
            </span>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-4 col-md-6">
            <div class="section-header">🚚 Logistics & Receiving</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="receiving_multi.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-success-subtle text-success"><i class="bi bi-plus-square-fill"></i></div>
                        <div>
                            <span class="menu-title">Multi-Item Receiving</span>
                            <span class="menu-sub">GRN entry with Pallet Tally.</span>
                        </div>
                    </a>
                    <a href="commercial_outbound.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-info-subtle text-info"><i class="bi bi-dash-square-fill"></i></div>
                        <div>
                            <span class="menu-title">Commercial Outbound</span>
                            <span class="menu-sub">Retail & Wholesale Stock Out.</span>
                        </div>
                    </a>
                    <a href="inventory_report.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-dark-subtle text-dark"><i class="bi bi-graph-up-arrow"></i></div>
                        <div>
                            <span class="menu-title">Live Stock Report</span>
                            <span class="menu-sub">Category balance & expiry view.</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="section-header header-green">🏫 PSS Operation</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="pss_delivery.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-success-subtle text-success"><i class="bi bi-truck"></i></div>
                        <div>
                            <span class="menu-title">PSS School Delivery</span>
                            <span class="menu-sub">Generate DOs for School Milk.</span>
                        </div>
                    </a>
                    <a href="import_schools.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-geo-alt-fill"></i></div>
                        <div>
                            <span class="menu-title">School DB Update</span>
                            <span class="menu-sub">Bulk Address & Student Count Update.</span>
                        </div>
                    </a>
                    <a href="import_co_ui.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-warning-subtle text-warning-emphasis"><i class="bi bi-file-earmark-arrow-up"></i></div>
                        <div>
                            <span class="menu-title">Import Monthly CO</span>
                            <span class="menu-sub">CSV to SAP Generator for Schools.</span>
                        </div>
                    </a>
                    <a href="view_batch.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-secondary-subtle text-secondary"><i class="bi bi-folder2-open"></i></div>
                        <div>
                            <span class="menu-title">View Batch Reports</span>
                            <span class="menu-sub">Access Generated SAP Lists.</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="section-header">🛡️ Quality & Return Management</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="spoilage_record.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-camera-fill"></i></div>
                        <div>
                            <span class="menu-title">Report New Spoilage</span>
                            <span class="menu-sub">Log physical damage with photos.</span>
                        </div>
                    </a>
                    <a href="spoilage_report.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-clipboard-data-fill"></i></div>
                        <div>
                            <span class="menu-title">Spoilage List (Claims)</span>
                            <span class="menu-sub">Track CNs & Supplier documentation.</span>
                        </div>
                    </a>
                    <a href="reconcile.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-warning-subtle text-warning-emphasis"><i class="bi bi-arrow-repeat"></i></div>
                        <div>
                            <span class="menu-title">Daily Reconciliation</span>
                            <span class="menu-sub">Verify System vs Physical stock.</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="section-header header-blue">📊 Master Data & Admin</div>
            <div class="card nav-card">
                <div class="list-group list-group-flush">
                    <a href="product_management.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-primary-subtle text-primary"><i class="bi bi-table"></i></div>
                        <div>
                            <span class="menu-title">Product Catalog</span>
                            <span class="menu-sub">View/Edit Cooking & Beef SKUs.</span>
                        </div>
                    </a>
                    <a href="import_products.php" class="list-group-item list-group-item-action">
                        <div class="icon-box bg-danger-subtle text-danger"><i class="bi bi-file-earmark-arrow-up-fill"></i></div>
                        <div>
                            <span class="menu-title">Bulk Product Import</span>
                            <span class="menu-sub">Excel/CSV Master Data Update.</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

</div>
<?php require_once 'includes/footer.php'; ?>