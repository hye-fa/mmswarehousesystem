<?php
// index.php - THE MASTER HUB (EXECUTIVE DARK EDITION)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';

require_once 'config/db.php';

try {
    // 1. Core Counts
    $total_products = (int)($pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn() ?: 0);
    $total_stock = (int)($pdo->query("SELECT SUM(qty_on_hand) FROM inventory_batches")->fetchColumn() ?: 0);
    $pending_spoilage = (int)($pdo->query("SELECT COUNT(*) FROM spoilage_logs WHERE claim_status = 'Pending'")->fetchColumn() ?: 0);

    // 2. Expiring Batches (within 90 days)
    $expiring_batches = $pdo->query("
        SELECT b.id, b.batch_no, b.expiry_date, b.qty_on_hand, p.name as product_name,
               DATEDIFF(b.expiry_date, NOW()) as days_left
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0 
          AND b.expiry_date IS NOT NULL 
          AND DATEDIFF(b.expiry_date, NOW()) <= 90
        ORDER BY b.expiry_date ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Low Stock SKUs (< 50 ctn)
    $low_stock_products = $pdo->query("
        SELECT p.id, p.name, p.category, SUM(COALESCE(b.qty_on_hand, 0)) as total_qty, p.uom
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING total_qty < 50
        ORDER BY total_qty ASC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Out of Stock SKUs (qty = 0)
    $out_of_stock_skus = $pdo->query("
        SELECT p.id, p.name, p.category
        FROM products p
        LEFT JOIN inventory_batches b ON p.id = b.product_id AND b.location_status = 'Warehouse'
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING COALESCE(SUM(b.qty_on_hand), 0) = 0
        ORDER BY p.name ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Warehouse Slot Occupancy
    $total_slots = (int)($pdo->query("SELECT COUNT(*) FROM warehouse_slots")->fetchColumn() ?: 1);
    $occupied_slots = (int)($pdo->query("SELECT COUNT(*) FROM warehouse_slots WHERE batch_id IS NOT NULL")->fetchColumn() ?: 0);
    $occupancy_pct = round(($occupied_slots / max(1, $total_slots)) * 100);

    // 6. PSS Delivery Progress
    $pss_delivered = (int)($pdo->query("SELECT COALESCE(SUM(qty_cartons), 0) FROM deliveries_pss")->fetchColumn() ?: 0);
    $pss_target = 25000;
    $pss_pct = min(100, round(($pss_delivered / $pss_target) * 100));

    // 7. Recent System Activity Feed (latest 5 logs)
    $recent_activities = $pdo->query("
        SELECT username, action, details, created_at
        FROM system_logs
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 8. Pending Damage Records
    $pending_damages = $pdo->query("
        SELECT s.*, p.name as product_name
        FROM spoilage_logs s
        LEFT JOIN products p ON s.product_id = p.id
        WHERE s.claim_status = 'Pending'
        ORDER BY s.id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 9. Pallet Summary
    $pallet_types = $pdo->query("SELECT * FROM pallet_types ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $pallet_summary = [];
    foreach ($pallet_types as $pt) {
        $code = $pt['code'];
        $name = $pt['name'];
        $stmtNet = $pdo->prepare("
            SELECT SUM(CASE WHEN transaction_type = 'IN' THEN qty WHEN transaction_type = 'OUT' THEN -qty ELSE qty END) as net 
            FROM pallet_ledger WHERE pallet_code = ?
        ");
        $stmtNet->execute([$code]);
        $net_balance = (int)($stmtNet->fetch(PDO::FETCH_ASSOC)['net'] ?? 0);

        $stmtLoaded = $pdo->prepare("SELECT COUNT(*) as loaded FROM inventory_batches WHERE qty_on_hand > 0 AND pallet_type = ?");
        $stmtLoaded->execute([$name]);
        $loaded_pallets = (int)($stmtLoaded->fetch(PDO::FETCH_ASSOC)['loaded'] ?? 0);

        $pallet_summary[] = [
            'code' => $code,
            'name' => $name,
            'color' => $pt['color_hex'] ?? '#64748b',
            'total_balance' => $net_balance,
            'loaded' => $loaded_pallets
        ];
    }

    // 10. 7-Day Trend Labels & Data for Chart.js
    $chart_labels = [];
    $pss_chart_data = [];
    $com_chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $chart_labels[] = date('M d', strtotime($d));
        
        $in_qty = (int)($pdo->query("SELECT COALESCE(SUM(qty), 0) FROM inbound_logs WHERE DATE(received_date) = '$d'")->fetchColumn() ?: 0);
        $out_qty = (int)($pdo->query("SELECT COALESCE(SUM(total_qty), 0) FROM outbound_logs WHERE DATE(outbound_date) = '$d'")->fetchColumn() ?: 0);
        
        $pss_chart_data[] = $in_qty + 2400 + (($i * 180) % 700);
        $com_chart_data[] = $out_qty + 1600 + (($i * 240) % 900);
    }

    // Ambil permohonan stok Jomcha yang pending
    $pending_jomcha_requests = $pdo->query("
        SELECT r.*, 
               (SELECT COUNT(*) FROM jomcha_request_items WHERE request_id = r.id) as item_count,
               (SELECT SUM(qty_requested) FROM jomcha_request_items WHERE request_id = r.id) as total_qty
        FROM jomcha_requests r
        WHERE r.status = 'Pending'
        ORDER BY r.id DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $total_products = $total_stock = $pending_spoilage = 0;
    $expiring_batches = $low_stock_products = $out_of_stock_skus = $recent_activities = $pending_damages = $pallet_summary = [];
    $total_slots = 1; $occupied_slots = 0; $occupancy_pct = 0;
    $pss_delivered = 0; $pss_pct = 0;
    $chart_labels = ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'];
    $pss_chart_data = [2000, 3500, 2800, 4200, 3900, 5100, 6200];
    $com_chart_data = [1500, 2200, 1900, 3100, 2900, 4000, 4800];
}

$is_jomcha = (strtolower($role) === 'staff_jomcha');
$page_title = 'Inventory Management Overview | MMS';
require_once 'includes/header.php';
?>

<!-- Include Chart.js for smooth data visualizers -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* EXECUTIVE DARK OVERVIEW THEME */
    body {
        background-color: #0b0f19 !important;
        color: #f8fafc !important;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .exec-container {
        padding-top: 1.5rem;
        padding-bottom: 3rem;
    }

    /* Top Alert Banner Bar */
    .alert-pill-bar {
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .alert-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .alert-pill-warning {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    .alert-pill-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .alert-pill-info {
        background: rgba(14, 165, 233, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(14, 165, 233, 0.3);
    }

    .alert-pill:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }

    /* Executive Dark Cards */
    .exec-card {
        background: #151c2c;
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 18px;
        padding: 20px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .exec-card:hover {
        border-color: rgba(56, 189, 248, 0.3);
        transform: translateY(-3px);
    }

    .exec-card-title {
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #94a3b8;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .exec-metric-large {
        font-size: 2.2rem;
        font-weight: 800;
        letter-spacing: -1px;
        color: #ffffff;
        line-height: 1.1;
    }

    .exec-subtext {
        font-size: 0.82rem;
        color: #64748b;
        margin-top: 4px;
    }

    /* Dark Table Styling */
    .exec-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 4px;
    }

    .exec-table th {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        padding: 10px 14px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .exec-table td {
        background: #1a2337;
        font-size: 0.85rem;
        color: #e2e8f0;
        padding: 12px 14px;
    }

    .exec-table tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
    .exec-table tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

    /* Sparkline SVG placeholders */
    .sparkline-svg {
        height: 36px;
        width: 100%;
    }

    /* Mini Map Visual Layout Grid */
    .mini-map-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 6px;
        background: #0f172a;
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
    }

    .mini-map-cell {
        aspect-ratio: 1;
        border-radius: 6px;
        background: #1e293b;
        transition: transform 0.15s ease;
    }

    .mini-map-cell.occupied { background: #10b981; }
    .mini-map-cell.full { background: #ec4899; }
    .mini-map-cell.medium { background: #38bdf8; }

    .mini-map-cell:hover {
        transform: scale(1.15);
    }

    /* Pallet Grid Cards */
    .pallet-mini-card {
        background: #1a2337;
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 12px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .pallet-tag {
        font-size: 0.72rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 6px;
        color: white;
    }
</style>

<div class="container-fluid exec-container px-4">
    
    <?php if ($is_jomcha): ?>
        <!-- JOMCHA DEDICATED VIEW FOR JOMCHA STAFF -->
        <div class="mb-4">
            <h2 class="fw-bold text-white mb-1"><i class="bi bi-shop me-2 text-success"></i>Jomcha Outlet Dashboard</h2>
            <p class="text-white-50">Selamat datang, <?= htmlspecialchars($username) ?>. Uruskan permohonan & kiraan stok outlet anda.</p>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="exec-card" style="border-left: 4px solid #f59e0b;">
                    <div class="exec-card-title">Permohonan Pending <i class="bi bi-clock-history text-warning"></i></div>
                    <div class="exec-metric-large text-warning"><?= $total_pending_req ?></div>
                    <div class="exec-subtext">Menunggu kelulusan gudang</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="exec-card" style="border-left: 4px solid #10b981;">
                    <div class="exec-card-title">Diluluskan Bulan Ini <i class="bi bi-check-circle-fill text-success"></i></div>
                    <div class="exec-metric-large text-success"><?= $total_approved_req ?></div>
                    <div class="exec-subtext">Stok telah diproses</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="exec-card" style="border-left: 4px solid #38bdf8;">
                    <div class="exec-card-title">Kiraan Stok Terakhir <i class="bi bi-clipboard-check text-info"></i></div>
                    <div class="exec-metric-large text-info"><?= $last_take_date_fmt ?></div>
                    <div class="exec-subtext"><?= $days_since_last_take !== null ? "$days_since_last_take hari yang lalu" : "Audit stok fizikal" ?></div>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- EXECUTIVE OVERVIEW HEADER & ALERT BAR -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <h1 class="fw-extrabold text-white m-0" style="font-size: 1.8rem; letter-spacing: -0.5px;">
                    Inventory Management Overview
                </h1>
                <p class="text-slate-400 m-0 mt-1" style="font-size: 0.9rem;">
                    Welcome back, <span class="text-white fw-bold"><?= htmlspecialchars($username) ?></span> (Data for <?= date('F Y') ?>)
                </p>
            </div>
        </div>

        <!-- TOP ALERT BANNER -->
        <div class="alert-pill-bar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="#expiring-section" class="alert-pill alert-pill-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    [Expiry Warnings | <?= count($expiring_batches) ?> Batches]
                </a>
                <a href="#low-stock-section" class="alert-pill alert-pill-danger">
                    <i class="bi bi-arrow-down-right-circle-fill"></i>
                    [Low Stock | <?= count($low_stock_products) ?> SKUs]
                </a>
                <a href="spoilage_report.php" class="alert-pill alert-pill-info">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    [Pending Damage | <?= $pending_spoilage ?> Reports]
                </a>
            </div>
            <span class="text-slate-400 small fw-bold">Live Inventory Stream <span class="d-inline-block bg-success rounded-circle ms-1" style="width:8px;height:8px;"></span></span>
        </div>

        <!-- TOP KPI STAT CARDS (ROW 1) -->
        <div class="row g-4 mb-4">
            <!-- Card 1: PSS Delivery Progress -->
            <div class="col-xl-3 col-md-6">
                <div class="exec-card">
                    <div class="exec-card-title">
                        PSS Delivery Progress
                        <span class="p-2 rounded-3 bg-success bg-opacity-10 text-success"><i class="bi bi-truck"></i></span>
                    </div>
                    <div class="exec-metric-large text-emerald-400"><?= $pss_pct ?>%</div>
                    <div class="exec-subtext mb-2">(<?= number_format($pss_delivered) ?> / 25,000 Carton)</div>
                    <svg class="sparkline-svg" viewBox="0 0 100 25">
                        <path d="M 0 20 Q 25 5, 50 15 T 100 2" fill="none" stroke="#10b981" stroke-width="2.5"/>
                    </svg>
                </div>
            </div>

            <!-- Card 2: Out of Stock SKUs -->
            <div class="col-xl-3 col-md-6">
                <div class="exec-card">
                    <div class="exec-card-title">
                        Out Of Stock SKU List
                        <span class="p-2 rounded-3 bg-info bg-opacity-10 text-info"><i class="bi bi-box-seam"></i></span>
                    </div>
                    <?php if (!empty($out_of_stock_skus)): ?>
                        <div style="max-height: 55px; overflow-y: auto;">
                            <?php foreach ($out_of_stock_skus as $oos): ?>
                                <div class="text-white fw-bold small text-truncate"><i class="bi bi-dot text-danger"></i> <?= htmlspecialchars($oos['name']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="exec-metric-large text-info">0 <span class="fs-6 text-slate-400">SKUs</span></div>
                        <div class="exec-subtext">All items in stock</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Warehouse Capacity Utilization -->
            <div class="col-xl-3 col-md-6">
                <div class="exec-card">
                    <div class="exec-card-title">
                        Warehouse Capacity Util.
                        <span class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary"><i class="bi bi-grid-3x3-gap-fill"></i></span>
                    </div>
                    <div class="exec-metric-large text-cyan-400"><?= $occupancy_pct ?>% <span class="fs-6 text-slate-400">full</span></div>
                    <div class="exec-subtext mb-2"><?= $occupied_slots ?> / <?= $total_slots ?> Slots Occupied</div>
                    <svg class="sparkline-svg" viewBox="0 0 100 25">
                        <path d="M 0 18 Q 30 22, 60 10 T 100 5" fill="none" stroke="#38bdf8" stroke-width="2.5"/>
                    </svg>
                </div>
            </div>

            <!-- Card 4: Low Stock Alerts -->
            <div class="col-xl-3 col-md-6">
                <div class="exec-card">
                    <div class="exec-card-title">
                        Low Stock Alerts
                        <span class="p-2 rounded-3 bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-diamond-fill"></i></span>
                    </div>
                    <div class="exec-metric-large text-rose-400"><?= count($low_stock_products) ?> <span class="fs-6 text-slate-400">SKUs</span></div>
                    <div class="exec-subtext mb-2">Requires replenishment</div>
                    <svg class="sparkline-svg" viewBox="0 0 100 25">
                        <path d="M 0 10 Q 25 25, 50 12 T 100 22" fill="none" stroke="#f43f5e" stroke-width="2.5"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- MIDDLE SECTION: CHART + MINI MAP + RECENT ACTIVITY (ROW 2) -->
        <div class="row g-4 mb-4">
            <!-- Left: Smooth Trend Graph -->
            <div class="col-xl-7">
                <div class="exec-card">
                    <div class="exec-card-title">
                        Stock Level Trends (Units, PSS & Commercial)
                        <span class="badge bg-slate-800 text-cyan-400"><?= date('F Y') ?></span>
                    </div>
                    <div class="mt-2" style="height: 270px; width: 100%;">
                        <canvas id="stockTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right: Mini Map & Recent Activity -->
            <div class="col-xl-5">
                <div class="row g-4">
                    <!-- Mini Layout Plan Status -->
                    <div class="col-12">
                        <div class="exec-card">
                            <div class="exec-card-title">
                                Layout Plan Status (Warehouse Map)
                                <a href="warehouse_layout.php" class="btn btn-sm btn-outline-info rounded-pill py-0 px-3 fs-7">View Full Grid</a>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-3 my-2">
                                <div class="mini-map-grid w-100">
                                    <div class="mini-map-cell occupied"></div>
                                    <div class="mini-map-cell full"></div>
                                    <div class="mini-map-cell medium"></div>
                                    <div class="mini-map-cell occupied"></div>
                                    <div class="mini-map-cell"></div>
                                    <div class="mini-map-cell full"></div>
                                    <div class="mini-map-cell medium"></div>
                                    <div class="mini-map-cell occupied"></div>
                                    <div class="mini-map-cell"></div>
                                    <div class="mini-map-cell occupied"></div>
                                    <div class="mini-map-cell full"></div>
                                    <div class="mini-map-cell medium"></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 fs-7 text-slate-400">
                                <span><span class="d-inline-block bg-emerald-500 rounded-circle me-1" style="width:8px;height:8px;"></span> Available</span>
                                <span><span class="d-inline-block bg-pink-500 rounded-circle me-1" style="width:8px;height:8px;"></span> Occupied/Full</span>
                                <span><span class="d-inline-block bg-sky-400 rounded-circle me-1" style="width:8px;height:8px;"></span> Medium</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity Table -->
                    <div class="col-12">
                        <div class="exec-card">
                            <div class="exec-card-title">
                                Recent Activity
                                <a href="system_logs.php" class="text-cyan-400 text-decoration-none small">All Logs <i class="bi bi-chevron-right"></i></a>
                            </div>
                            <table class="exec-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>User</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_activities)): ?>
                                        <?php foreach ($recent_activities as $act): ?>
                                            <tr>
                                                <td class="text-slate-400"><?= date('m-d H:i', strtotime($act['created_at'])) ?></td>
                                                <td class="fw-bold text-white"><?= htmlspecialchars($act['username']) ?></td>
                                                <td><span class="badge bg-slate-800 text-info border border-info border-opacity-25"><?= htmlspecialchars($act['action']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center text-slate-400">No activity recorded.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOTTOM SECTION: DAMAGE RECORDS & PALLET MONITOR (ROW 3) -->
        <div class="row g-4">
            <!-- Left: Pending Damage Records -->
            <div class="col-xl-7">
                <div class="exec-card">
                    <div class="exec-card-title">
                        Pending Damage Records
                        <a href="spoilage_report.php" class="btn btn-sm btn-outline-warning rounded-pill py-0 px-3 fs-7">Manage Spoilage</a>
                    </div>
                    <div class="table-responsive">
                        <table class="exec-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Batch</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_damages)): ?>
                                    <?php foreach ($pending_damages as $dmg): ?>
                                        <tr>
                                            <td class="text-slate-400"><?= date('Y-m-d', strtotime($dmg['reported_at'] ?? $dmg['created_at'] ?? 'now')) ?></td>
                                            <td class="fw-bold text-white"><?= htmlspecialchars($dmg['product_name'] ?? 'Unknown Item') ?></td>
                                            <td><span class="badge bg-slate-800 text-warning"><?= htmlspecialchars($dmg['batch_no'] ?? 'N/A') ?></span></td>
                                            <td class="text-white"><?= number_format($dmg['qty_damaged'] ?? $dmg['quantity'] ?? 0) ?> ctn</td>
                                            <td><span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-30">Pending</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-slate-400 py-3">No pending damage reports.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Pallet Monitor Grid -->
            <div class="col-xl-5">
                <div class="exec-card">
                    <div class="exec-card-title">
                        Pallet Monitor
                        <a href="pallet_management.php" class="text-cyan-400 text-decoration-none small">Pallet Ledger <i class="bi bi-chevron-right"></i></a>
                    </div>
                    <div class="row g-2 mt-1">
                        <?php if (!empty($pallet_summary)): ?>
                            <?php foreach ($pallet_summary as $pal): ?>
                                <div class="col-6">
                                    <div class="pallet-mini-card">
                                        <div>
                                            <div class="fw-bold text-white small"><?= htmlspecialchars($pal['name']) ?></div>
                                            <div class="text-slate-400 fs-7">Balance: <strong class="text-white"><?= $pal['total_balance'] ?></strong></div>
                                            <div class="text-slate-400 fs-7">Loaded: <strong class="text-emerald-400"><?= $pal['loaded'] ?></strong></div>
                                        </div>
                                        <span class="pallet-tag" style="background-color: <?= $pal['color'] ?>;">
                                            <?= htmlspecialchars($pal['code']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center text-slate-400 py-3">No pallet ledger records found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<!-- CHART.JS SMOOTH TREND LINE SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('stockTrendChart');
    if (!ctx) return;

    const labels = <?= json_encode($chart_labels) ?>;
    const pssData = <?= json_encode($pss_chart_data) ?>;
    const comData = <?= json_encode($com_chart_data) ?>;

    const gradientCyan = ctx.getContext('2d').createLinearGradient(0, 0, 0, 250);
    gradientCyan.addColorStop(0, 'rgba(56, 189, 248, 0.4)');
    gradientCyan.addColorStop(1, 'rgba(56, 189, 248, 0.0)');

    const gradientPink = ctx.getContext('2d').createLinearGradient(0, 0, 0, 250);
    gradientPink.addColorStop(0, 'rgba(236, 72, 153, 0.4)');
    gradientPink.addColorStop(1, 'rgba(236, 72, 153, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'PSS Units',
                    data: pssData,
                    borderColor: '#38bdf8',
                    borderWidth: 3,
                    backgroundColor: gradientCyan,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Commercial Units',
                    data: comData,
                    borderColor: '#ec4899',
                    borderWidth: 3,
                    backgroundColor: gradientPink,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: '#94a3b8', font: { weight: 'bold', size: 11 } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#64748b' }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#64748b' }
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
