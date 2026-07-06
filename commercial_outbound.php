<?php
// commercial_outbound.php - UPGRADED CORPORATE INTERFACE
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch Commercial Products (Excluding PSS for internal use)
$products = $pdo->query("SELECT id, name, category FROM products WHERE category != 'PSS' AND is_active=1 ORDER BY name ASC")->fetchAll();


$page_title = 'Commercial Outbound | MMS LOGISTIK';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* Header Styling */
    .mms-header { 
        background: var(--mms-navy); 
        color: white; 
        padding: 1.5rem 0; 
        border-bottom: 4px solid var(--mms-cyan);
        margin-bottom: 2rem;
        margin-top: -1.5rem; /* pull up under navbar */
    }

    /* Card Styling */
    .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .section-title { 
        font-size: 0.9rem; 
        font-weight: 700; 
        color: var(--mms-navy); 
        text-transform: uppercase; 
        letter-spacing: 1px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }
    .section-title i { margin-right: 10px; color: var(--mms-cyan); }

    /* Table Styling */
    .item-row td { padding: 12px 15px; vertical-align: middle; }
    
    /* Form Elements */
    .form-control, .form-select {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 15px;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--mms-cyan);
        box-shadow: 0 0 0 3px rgba(0, 174, 239, 0.1);
    }
    
    .product-select { font-weight: 600; color: var(--mms-navy); }
    
    /* Buttons */
    .btn-mms-confirm { 
        background: var(--mms-emerald, #10b981); 
        color: white; 
        font-weight: 700; 
        padding: 12px;
        border-radius: 10px;
        border: none;
        transition: all 0.3s;
    }
    .btn-mms-confirm:hover { 
        background: #059669; 
        transform: translateY(-2px); 
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        color: white;
    }
    
    .btn-add-row {
        color: var(--mms-cyan);
        font-weight: 600;
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        width: 100%;
        padding: 10px;
        transition: all 0.2s;
    }
    .btn-add-row:hover { background: #f1f5f9; border-color: var(--mms-cyan); }
</style>

<div class="page-header mb-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-800 mb-1"><i class="bi bi-box-arrow-up-right me-2"></i>Stock Outbound</h1>
                <p class="opacity-75 mb-0 fw-light">Commercial & Retail Distribution Command</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-outline-light"><i class="bi bi-house me-1"></i> Dashboard</a>
                <a href="reconcile.php" class="btn btn-info text-white fw-bold"><i class="bi bi-scale me-1"></i> Reconcile</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 pb-5">
    <form action="api/save_commercial_outbound.php" method="POST" id="outboundForm" class="card main-card border-0">
        
        <div class="section-title"><i class="bi bi-info-circle-fill"></i> Delivery Information</div>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">DELIVERY DATE</label>
                <input type="date" name="out_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">CUSTOMER / OUTLET</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-shop"></i></span>
                    <input type="text" name="customer_name" class="form-control" placeholder="e.g. Lotus's Kuala Terengganu" required>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">DO / INVOICE REF</label>
                <input type="text" name="doc_ref" class="form-control" placeholder="e.g. DO-2026-0450">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">VEHICLE / DRIVER</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-truck"></i></span>
                    <input type="text" name="vehicle" class="form-control" placeholder="e.g. WWW 9999 (Ali)">
                </div>
            </div>
        </div>

        <div class="section-title"><i class="bi bi-cart-check-fill"></i> Items to Outbound</div>
        <div class="table-responsive mb-3 border rounded-3">
            <table class="table align-middle mb-0" id="outTable">
                <thead>
                    <tr class="table-light text-secondary small fw-bold">
                        <th class="ps-3">Product Selection</th>
                        <th width="25%">Batch / Lot No.</th>
                        <th width="15%" class="text-center">Qty (Carton)</th>
                        <th width="10%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="outBody">
                    <tr class="item-row">
                        <td class="ps-3">
                            <select name="items[0][product_id]" class="form-select product-select" required>
                                <option value="">-- Choose Product --</option>
                                <?php foreach($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="items[0][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                                <option value="">-- Auto FEFO --</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="items[0][qty]" class="form-control form-control-sm fw-bold text-center border-primary-subtle qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                                <i class="bi bi-trash3 fs-5"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="mb-4">
            <button type="button" class="btn btn-add-row" onclick="addRow()">
                <i class="bi bi-plus-lg me-2"></i> Add Another Product
            </button>
        </div>

        <div class="d-grid shadow-sm">
            <button type="submit" class="btn btn-mms-confirm btn-lg py-3">
                <i class="bi bi-send-check-fill me-2"></i> CONFIRM & PROCESS OUTBOUND
            </button>
        </div>
        
    </form>
</div>


<script>
    let rowCount = 1;
    const products = <?php echo json_encode($products); ?>;
    
    function addRow() {
        let options = '<option value="">-- Choose Product --</option>';
        products.forEach(p => options += `<option value="${p.id}">${p.name}</option>`);
        
        const html = `
            <tr class="item-row">
                <td class="ps-4">
                    <select name="items[${rowCount}][product_id]" class="form-select product-select" required>${options}</select>
                </td>
                <td>
                    <select name="items[${rowCount}][batch]" class="form-select form-select-sm batch-select text-center fw-bold" onchange="validateBatchStock(this)">
                        <option value="">-- Auto FEFO --</option>
                    </select>
                </td>
                <td><input type="number" name="items[${rowCount}][qty]" class="form-control form-control-sm fw-bold text-center border-primary-subtle qty-input" required min="1" placeholder="0" oninput="validateBatchStock(this)"></td>
                <td class="text-center pe-4">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="removeRow(this)">
                        <i class="bi bi-trash3 fs-5"></i>
                    </button>
                </td>
            </tr>
        `;
        document.getElementById('outBody').insertAdjacentHTML('beforeend', html);
        rowCount++;
    }

    function removeRow(btn) {
        if(document.querySelectorAll('.item-row').length > 1) {
            btn.closest('tr').remove();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Constraint',
                text: 'At least one product is required for outbound.',
                confirmButtonColor: '#0b2147'
            });
        }
    }

    // Panggilan AJAX untuk memuatkan batch produk secara dinamik
    $(document).on('change', '.product-select', function() {
        let row = $(this).closest('tr');
        let pid = $(this).val();
        let batchSelect = row.find('.batch-select');
        let qtyInput = row.find('.qty-input');
        
        batchSelect.empty().append('<option value="">-- Auto FEFO --</option>');
        qtyInput.val('');
        
        if (pid) {
            fetch('api/get_batches.php?product_id=' + pid)
            .then(res => res.json())
            .then(batches => {
                if (batches.length === 0) {
                    batchSelect.empty().append('<option value="" disabled selected>⚠️ Tiada Stok Aktif</option>');
                } else {
                    batches.forEach(b => {
                        batchSelect.append(`<option value="${b.batch_no}" data-qty="${b.qty_on_hand}">Batch: ${b.batch_no} (Baki: ${b.qty_on_hand} ctn | Exp: ${b.expiry_date})</option>`);
                    });
                }
            })
            .catch(err => console.error("Gagal mendapatkan batch:", err));
        }
    });

    // Validasi stok batch di sebelah client
    function validateBatchStock(el) {
        let row = $(el).closest('tr');
        let qtyInput = row.find('.qty-input');
        let qty = parseInt(qtyInput.val()) || 0;
        
        let batchSelect = row.find('.batch-select');
        let selectedOption = batchSelect.find(':selected');
        let maxQty = parseInt(selectedOption.data('qty'));
        
        if (!isNaN(maxQty) && qty > maxQty) {
            Swal.fire({
                icon: 'warning',
                title: 'Had Baki Dilampaui',
                text: `Stok tersedia bagi batch '${selectedOption.val()}' hanyalah ${maxQty} karton. Sila pilih batch lain atau kurangkan kuantiti.`,
                confirmButtonColor: '#ffc107'
            });
            qtyInput.val(maxQty);
        }
    }

    document.getElementById('outboundForm').onsubmit = function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Confirm Shipment?',
            text: "This will deduct the stock from inventory balance.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Process Outbound',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    };
</script>
<?php require_once 'includes/footer.php'; ?>