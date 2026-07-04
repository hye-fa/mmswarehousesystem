<?php
// spoilage_report.php
// Error reporting to catch the cause of Error 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

// Fetch batches with their product conversion rates
try {
    $batches = $pdo->query("
        SELECT b.id, b.batch_no, b.qty_on_hand, p.name as product_name, p.pcs_per_carton
        FROM inventory_batches b
        JOIN products p ON b.product_id = p.id
        WHERE b.qty_on_hand > 0
        ORDER BY p.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . ". Ensure 'pcs_per_carton' column exists in products table.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Damage & Spoilage Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card-spoilage { border-top: 5px solid #dc3545; border-radius: 10px; }
        .preview-thumb { height: 65px; width: 65px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
        .calc-box { font-size: 0.85rem; background: #fff5f5; padding: 5px 10px; border-radius: 5px; border: 1px solid #feb2b2; }
    </style>
</head>
<body class="py-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Damage & Spoilage Report</h3>
        <a href="index.php" class="btn btn-outline-dark btn-sm">🏠 Back to Dashboard</a>
    </div>

    <form id="spoilageForm" action="api/save_spoilage.php" method="POST" enctype="multipart/form-data">
        <div class="card shadow-sm card-spoilage mb-4">
            <div class="card-header bg-white fw-bold">1. Header Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Discovery Date</label>
                        <input type="date" name="report_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">General Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Context (e.g., Transit damage, forklift error)">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-primary">📸 Upload Evidence</label>
                        <input type="file" name="spoilage_photos[]" id="photo_input" class="form-control" accept="image/*" capture="environment" multiple>
                        <div id="preview_container" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">2. Items & Quantity (pcs Calculation)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th width="40%">Product (Batch | Stock)</th>
                                <th width="30%">Quantity Entry</th>
                                <th width="20%">Reason</th>
                                <th width="10%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="spoilageBody">
                            <tr>
                                <td class="p-3">
                                    <select name="items[0][batch_id]" class="form-select batch-select" required onchange="calculateRow(this)">
                                        <option value="" data-pcs="1">-- Select Batch --</option>
                                        <?php foreach($batches as $b): ?>
                                            <option value="<?= $b['id'] ?>" data-pcs="<?= $b['pcs_per_carton'] ?>">
                                                <?= htmlspecialchars($b['product_name']) ?> (B: <?= $b['batch_no'] ?> | Bal: <?= $b['qty_on_hand'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="p-3">
                                    <div class="input-group">
                                        <input type="number" step="1" class="form-control qty-input" placeholder="Qty" required oninput="calculateRow(this)">
                                        <select class="form-select unit-type" style="max-width: 90px;" onchange="calculateRow(this)">
                                            <option value="pcs">pcs</option>
                                            <option value="ctn">ctn</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="items[0][qty]" class="final-qty-input">
                                    <div class="calc-box mt-2 d-none">
                                        <strong>Total: <span class="final-qty-text">0</span> pcs</strong>
                                    </div>
                                </td>
                                <td class="p-3">
                                    <select name="items[0][reason]" class="form-select" required>
                                        <option value="Leaking">Leaking</option>
                                        <option value="Expired">Expired</option>
                                        <option value="Crushed">Crushed</option>
                                        <option value="Pest Damage">Pest Damage</option>
                                    </select>
                                </td>
                                <td class="text-center p-3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()">+ Add Item</button>
            </div>
        </div>

        <button type="submit" class="btn btn-danger btn-lg w-100 py-3 shadow fw-bold">SUBMIT REPORT & DEDUCT STOCK</button>
    </form>
</div>

<script>
    let rowCount = 1;
    const batchOptions = `<?php 
        $opt = '<option value="" data-pcs="1">-- Select Batch --</option>';
        foreach($batches as $b) {
            $opt .= '<option value="'.$b['id'].'" data-pcs="'.$b['pcs_per_carton'].'">'.htmlspecialchars($b['product_name']).' (B: '.$b['batch_no'].' | Bal: '.$b['qty_on_hand'].')</option>';
        }
        echo $opt;
    ?>`;

    function calculateRow(el) {
        const row = el.closest('tr');
        const batch = row.querySelector('.batch-select');
        const pcsPerCtn = parseInt(batch.options[batch.selectedIndex].dataset.pcs) || 1;
        const val = parseFloat(row.querySelector('.qty-input').value) || 0;
        const type = row.querySelector('.unit-type').value;
        const display = row.querySelector('.calc-box');
        
        let total = type === 'ctn' ? (val * pcsPerCtn) : val;
        
        row.querySelector('.final-qty-input').value = total;
        row.querySelector('.final-qty-text').innerText = total;
        display.classList.toggle('d-none', val <= 0);
    }

    function addRow() {
        const tbody = document.getElementById('spoilageBody');
        const html = `
            <tr>
                <td class="p-3">
                    <select name="items[${rowCount}][batch_id]" class="form-select batch-select" required onchange="calculateRow(this)">
                        ${batchOptions}
                    </select>
                </td>
                <td class="p-3">
                    <div class="input-group">
                        <input type="number" class="form-control qty-input" required oninput="calculateRow(this)">
                        <select class="form-select unit-type" style="max-width: 90px;" onchange="calculateRow(this)">
                            <option value="pcs">pcs</option>
                            <option value="ctn">ctn</option>
                        </select>
                    </div>
                    <input type="hidden" name="items[${rowCount}][qty]" class="final-qty-input">
                    <div class="calc-box mt-2 d-none"><strong>Total: <span class="final-qty-text">0</span> pcs</strong></div>
                </td>
                <td class="p-3">
                    <select name="items[${rowCount}][reason]" class="form-select" required>
                        <option value="Leaking">Leaking</option>
                        <option value="Expired">Expired</option>
                        <option value="Crushed">Crushed</option>
                        <option value="Pest Damage">Pest Damage</option>
                    </select>
                </td>
                <td class="text-center p-3">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('tr').remove()">×</button>
                </td>
            </tr>`;
        tbody.insertAdjacentHTML('beforeend', html);
        rowCount++;
    }

    document.getElementById('photo_input').onchange = e => {
        const cont = document.getElementById('preview_container');
        cont.innerHTML = '';
        Array.from(e.target.files).forEach(f => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            img.className = 'preview-thumb';
            cont.appendChild(img);
        });
    };

    document.getElementById('spoilageForm').onsubmit = function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Submit Report?',
            text: "Stock will be deducted in pieces (pcs).",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Confirm'
        }).then(res => {
            if(res.isConfirmed) {
                fetch(this.action, { method: 'POST', body: new FormData(this) })
                .then(r => r.json())
                .then(data => {
                    if(data.status==='success') Swal.fire('Saved!', data.message, 'success').then(()=>location.href='index.php');
                    else Swal.fire('Error', data.message, 'error');
                });
            }
        });
    };
</script>
</body>
</html>