<?php
// product_management.php - MASTER PRODUCT CATALOG (UPGRADED INTERFACE)
require_once 'config/db.php';

// Handle status toggles
if (isset($_GET['toggle_id'])) {
    $stmt = $pdo->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['toggle_id']]);
    header("Location: product_management.php");
    exit;
}

$categories = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$search = $_GET['search'] ?? '';
$cat_filter = $_GET['category'] ?? '';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (name LIKE ? OR category LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat_filter) { $sql .= " AND category = ?"; $params[] = $cat_filter; }
$sql .= " ORDER BY category ASC, name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<?php
$page_title = 'Product Management | MMS';
require_once 'includes/header.php';
?>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-seam-fill me-2"></i>Product Master</h1>
                <p class="opacity-75 mb-0 fw-light">Moo Moo Supplies Catalog Management</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <a href="master_import.php" class="btn btn-info text-white fw-bold"><i class="bi bi-file-earmark-arrow-up me-1"></i> Bulk Import</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    
    <div class="card main-card border-0 mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by name or category..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">-- All Categories --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $cat_filter == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark w-100 fw-bold">APPLY FILTERS</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-navy"><i class="bi bi-list-ul me-2"></i>Product List</h5>
            <span class="badge bg-light text-dark border"><?= count($products) ?> Total Products</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Product Info</th>
                            <th>Category</th>
                            <th class="text-center">UOM</th>
                            <th class="text-center">Pack Size</th>
                            <th class="text-center">Pallet Cap</th>
                            <th class="text-center">Status</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">No products found in the catalog.</td></tr>
                        <?php else: ?>
                            <?php foreach($products as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded p-2 d-flex align-items-center justify-content-center me-3 fs-5">
                                            <i class="bi bi-box"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                            <small class="text-muted">ID: #<?= $p['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-cat"><?= htmlspecialchars($p['category']) ?></span></td>
                                <td class="text-center">
                                    <small class="fw-bold text-uppercase"><?= htmlspecialchars($p['uom']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-primary"><?= $p['pack_size'] ?></span>
                                    <div style="font-size: 10px;" class="text-muted text-uppercase">Units/Ctn</div>
                                </td>
                                <td class="text-center fw-bold text-secondary"><?= $p['pallet_capacity'] ?></td>
                                <td class="text-center">
                                    <?php if($p['is_active']): ?>
                                        <span class="badge rounded-pill bg-success px-3 py-2 border border-success">
                                            <i class="bi bi-check-circle-fill me-1"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-secondary px-3 py-2 border border-secondary">
                                            <i class="bi bi-x-circle-fill me-1"></i> Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="?toggle_id=<?= $p['id'] ?>" 
                                       class="btn btn-sm <?= $p['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                       title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="bi <?= $p['is_active'] ? 'bi-lock' : 'bi-unlock' ?>"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" title="Edit Product">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>