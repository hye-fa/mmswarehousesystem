<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'MMS WMS System';

// Sekatan akses pengguna (kecuali pada halaman login.php)
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page !== 'login.php') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Favicon / Gambar Browser Logo -->
    <link rel="shortcut icon" href="uploads/logo.png" type="image/png">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom Design & Responsive Stylesheet -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php if (!isset($hide_navbar) || !$hide_navbar): ?>
<?php
$role = $_SESSION['role'] ?? '';
$is_admin = ($role === 'admin');
$is_staff = ($role === 'admin' || $role === 'staff');
?>
<nav class="navbar navbar-expand-lg mms-navbar sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="uploads/logo.png" alt="MMS Logo" style="height: 32px; width: auto; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15);">
            <span class="fw-800 tracking-wide text-white">MMS HUB</span>
        </a>
        <button class="navbar-toggler text-white border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mmsNav">
            <i class="bi bi-list fs-2"></i>
        </button>
        <div class="collapse navbar-collapse" id="mmsNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house-door me-1"></i> Dashboard</a></li>
                
                <?php if ($is_staff): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Receiving
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="receiving.php">Single Item Receive</a></li>
                        <li><a class="dropdown-item" href="receiving_multi.php">Multi-Item Receive</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-truck me-1"></i> Operations
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($is_staff): ?>
                        <li><a class="dropdown-item" href="commercial_outbound.php">Commercial Outbound</a></li>
                        <?php endif; ?>
                        
                        <li><a class="dropdown-item" href="pss_delivery.php">PSS Delivery</a></li>
                        <li><a class="dropdown-item" href="outbound_history.php">Outbound History</a></li>
                        
                        <?php if ($is_staff): ?>
                        <li><a class="dropdown-item" href="reconcile.php">Daily Reconcile</a></li>
                        <li><a class="dropdown-item" href="stock_take.php">Stock Take</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="bi bi-graph-up-arrow me-1"></i> Reports</a></li>
                
                <?php if ($is_staff): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i> System
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="product_management.php">Master Data</a></li>
                        <li><a class="dropdown-item" href="spoilage_report.php">Spoilage Report</a></li>
                        <li><a class="dropdown-item" href="spoilage_list.php">Spoilage List</a></li>
                        <?php if ($is_admin): ?>
                        <div class="dropdown-divider"></div>
                        <li><a class="dropdown-item" href="user_management.php"><i class="bi bi-people me-1"></i> User Management</a></li>
                        <li><a class="dropdown-item" href="system_logs.php"><i class="bi bi-shield-check me-1"></i> System Audit Logs</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Profile Info & Log Keluar -->
            <div class="d-flex align-items-center gap-3">
                <span class="text-white small fw-bold">
                    <i class="bi bi-person-circle me-1 text-info fs-5"></i> 
                    <?= htmlspecialchars($_SESSION['full_name'] ?? 'Pengguna') ?> 
                    <span class="badge bg-info text-dark ms-1 fw-bold text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($role) ?></span>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm fw-bold border-white border-opacity-25 px-3"><i class="bi bi-box-arrow-right me-1"></i> Log Keluar</a>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>
