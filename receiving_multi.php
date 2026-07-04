<?php
// receiving_multi.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

$stmt = $pdo->prepare("SELECT id, name, category, pack_size, pallet_capacity FROM products");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Advanced GRN Portal | MMS';
require_once 'includes/header.php';
?>
<style>
    .top-filter-bar { background: #eff6ff; border: 2px solid #3b82f6; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
    .form-label-custom { font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block; }
    .btn-add { background: #ffffff; color: var(--accent-color); border: 2px dashed var(--accent-color); font-weight: 800; padding: 1rem; border-radius: 10px; }
    .qr-input:focus { border-color: var(--accent-color); box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25); }
    .select2-container .select2-selection--single { height: 31px; border: 1px solid #dee2e6; display: flex; align-items: center; font-weight: bold; font-size: 0.875rem;}
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: normal; padding-left: 8px; }
    
    @media (max-width: 991px) {
        .top-filter-bar {
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .summary-card {
            margin-top: 1.5rem;
        }
    }
</style>

<header class="page-header">
    <div class="container-fluid px-5 text-center text-md-start">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div>
                <h2 class="fw-800 mb-0"><i class="bi bi-box-seam me-2"></i>Stock Receiving Portal</h2>
                <p class="opacity-75 mb-0 text-uppercase small">Moo Moo Supplies Inbound Management</p>
            </div>
            <div class="d-flex gap-2 mt-3 mt-md-0">
                <a href="index.php" class="btn btn-light btn-sm fw-bold px-3">🏠 Home</a>
                <button onclick="history.back()" class="btn btn-outline-light btn-sm fw-bold px-3">⬅ Back</button>
            </div>
        </div>
    </div>
</header>

<div class="container-fluid px-5">
    <form id="multiReceiveForm" action="api/save_receiving_multi.php" method="POST">
        <div class="row g-4">
            <div class="col-lg-9">
                <div class="main-card card">
                    <div class="top-filter-bar shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h5 class="mb-0 fw-800 text-primary"><i class="bi bi-funnel-fill me-2"></i>STEP 1: CATEGORY</h5>
                            </div>
                            <div class="col-md-8">
                                <select class="form-select form-select-lg fw-800 border-primary shadow-sm" id="main_category" onchange="applyCategoryFilter()">
                                    <option value="UHT" selected>UHT (Retail)</option>
                                    <option value="PSS">PSS (School)</option>
                                    <option value="PST">PST (Fresh/Dairy)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-header">2. Delivery & Logistics Information</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label-custom text-primary">Supplier DO No. *</label>
                            <input type="text" name="supplier_do" class="form-control text-uppercase" required>
                            <label class="form-label-custom mt-3">PO Number</label>
                            <input type="text" name="po_number" class="form-control text-uppercase">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Received Date</label>
                            <input type="text" name="received_date" class="form-control datepicker fw-bold text-primary" value="<?= date('d/m/Y') ?>">
                            <label class="form-label-custom mt-3">Ordered Date</label>
                            <input type="text" name="ordered_date" class="form-control datepicker">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Transporter</label>
                            <input type="text" name="transporter_company" class="form-control" placeholder="Thong Nam">
                            <label class="form-label-custom mt-3">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Vehicle No</label>
                            <input type="text" name="vehicle_no" class="form-control text-uppercase" placeholder="ABC 1234">
                            <label class="form-label-custom mt-3">Arrival Time</label>
                            <input type="time" name="arrival_time" class="form-control">
                        </div>
                    </div>

                    <div class="section-header mt-4">3. Inbound Item List</div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr class="small fw-800 text-uppercase">
                                    <th class="ps-3">Product Description</th>
                                    <th>Scan / Lot</th>
                                    <th>Expiry</th>
                                    <th width="70">Batch</th>
                                    <th width="110">Qty (Pcs)</th>
                                    <th width="100">Qty (Ctn)</th>
                                    <th>Pallet</th>
                                    <th width="80">P.Qty</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody"></tbody>
                        </table>
                    </div>
                    <div class="p-3 text-center">
                        <button type="button" class="btn btn-add w-50" onclick="addRow()">+ Add New Product Row</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="summary-card sticky-top" style="top: 20px;">
                    <div class="p-3 bg-dark text-white text-center fw-800 small rounded-top">PALLET TALLY SUMMARY</div>
                    <div class="bg-white border-start border-end">
                        <?php 
                        $pts = [
                            'plain' => ['🟤', 'Plain Wood'], 'red' => ['🔴', 'Loscam Red'], 
                            'lhp' => ['🟢', 'LHP Green'], 'orange' => ['🟠', 'FFM Orange'], 
                            'ffm' => ['🟢', 'FFM Green'], 'black' => ['⚫', 'Plastic Black']
                        ];
                        foreach($pts as $k => $v): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 border-bottom px-3">
                            <span class="small fw-700"><?= $v[0] ?> <?= $v[1] ?></span>
                            <div style="width: 70px;">
                                <input type="number" id="manual_<?= $k ?>" class="form-control form-control-sm text-center mb-1" value="0" oninput="updateHeaderTally()">
                                <span class="badge bg-light text-dark border w-100" id="sub_<?= $k ?>">0</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="p-3 bg-primary text-white text-center">
                        <p class="small fw-bold mb-0 opacity-75 text-uppercase">Grand Total</p>
                        <h1 id="total_final" class="fw-900 mb-0">0</h1>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-3 fw-bold shadow-lg" style="border-radius: 0 0 0.5rem 0.5rem; font-size: 1.1rem; z-index: 1050;">
                        <i class="bi bi-cloud-check-fill me-2"></i> COMPLETE SUBMISSION
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="cameraModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark">
            <div class="modal-body p-0 text-center">
                <div id="reader" style="width:100%;"></div>
                <button class="btn btn-danger m-3" data-bs-dismiss="modal">Close Camera</button>
            </div>
        </div>
    </div>
</div>

<script>
    const productList = <?= json_encode($products); ?>;
    let rowCount = 0;
    let html5QrCode = null;
    let activeRowIndex = null;
    let cameraModalInstance = null;

    document.addEventListener('DOMContentLoaded', () => {
        cameraModalInstance = new bootstrap.Modal(document.getElementById('cameraModal'));
        flatpickr(".datepicker", { dateFormat: "d/m/Y", allowInput: true });
        
        document.getElementById('cameraModal').addEventListener('shown.bs.modal', startScanner);
        document.getElementById('cameraModal').addEventListener('hidden.bs.modal', stopScanner);
        
        addRow();
    });

    function addRow() {
        const id = rowCount++;

        const html = `
            <tr id="row_${id}">
                <td>
                    <select name="items[${id}][product_id]" id="product_${id}" class="form-select form-select-sm prod-select fw-bold" required style="width: 100%;">
                    </select>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" name="items[${id}][lot]" id="scan_input_${id}" class="form-control qr-input" placeholder="Scan/Lot" oninput="parseRowQR(this, ${id})">
                        <button type="button" class="btn btn-outline-secondary" onclick="openCameraForRow(${id})">📷</button>
                    </div>
                </td>
                <td><input type="text" name="items[${id}][expiry]" id="expiry_${id}" class="form-control form-control-sm datepicker bg-white"></td>
                <td><input type="text" name="items[${id}][batch]" id="batch_${id}" class="form-control form-control-sm text-center px-1" placeholder="B1"></td>
                <td><input type="number" name="items[${id}][qty_pcs]" id="qty_pcs_${id}" class="form-control form-control-sm text-center bg-light px-1" readonly></td>
                <td><input type="number" name="items[${id}][qty]" id="qty_${id}" class="form-control form-control-sm text-center fw-bold border-primary px-1" required></td>
                <td>
                    <select name="items[${id}][p_type]" class="form-select form-select-sm p-type-sel" onchange="updateHeaderTally()">
                        <option value="none">None</option>
                        <option value="plain">Wood</option>
                        <option value="red">Loscam Red</option>
                        <option value="lhp">LHP Green</option>
                        <option value="orange">FFM Orange</option>
                        <option value="ffm">FFM Green</option>
                        <option value="black">Plastic Black</option>
                    </select>
                </td>
                <td><input type="number" name="items[${id}][p_qty]" class="form-control form-control-sm p-qty-val text-center fw-bold" value="0" oninput="updateHeaderTally()"></td>
                <td>
                    <button type="button" class="btn btn-link text-danger p-0" onclick="document.getElementById('row_${id}').remove(); updateHeaderTally();">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </td>
            </tr>`;
        
        document.getElementById('itemsBody').insertAdjacentHTML('beforeend', html);
        flatpickr(`#expiry_${id}`, { dateFormat: "d/m/Y", allowInput: true });
        applyCategoryFilter();
    }

    function applyCategoryFilter() {
        const cat = document.getElementById('main_category').value;
        $('.prod-select').each(function() {
            const sel = $(this);
            const currentVal = sel.val();
            
            if (sel.data('select2')) {
                sel.select2('destroy');
            }
            
            sel.empty();
            sel.append('<option value="">Choose Product</option>');
            productList.forEach(p => {
                if (p.category === cat) {
                    sel.append(`<option value="${p.id}" data-cat="${p.category}" data-packsize="${p.pack_size}">${p.name}</option>`);
                }
            });
            
            if (currentVal && sel.find(`option[value="${currentVal}"]`).length > 0) {
                sel.val(currentVal);
            }
            
            sel.select2({
                width: '100%',
                placeholder: 'Choose Product',
                minimumResultsForSearch: 0
            }).on('change', function() {
                let name = $(this).attr('name');
                if (name) {
                    let m = name.match(/items\[(\d+)\]/);
                    if(m) calculateCtn(m[1]);
                }
            });
        });
    }

    function calculateCtn(id) {
        const qtyPcsInput = document.getElementById('qty_pcs_' + id);
        const qtyCtnInput = document.getElementById('qty_' + id);
        const sel = document.getElementById('product_' + id);
        
        if (qtyPcsInput && qtyCtnInput && sel && sel.value) {
            let pcs = parseInt(qtyPcsInput.value);
            if (!isNaN(pcs) && pcs > 0) {
                const optText = sel.options[sel.selectedIndex].text;
                const match = optText.match(/(\d+)\s*(PK|PCS|PC)\/CTN/i);
                if (match) {
                    let packSize = parseInt(match[1]);
                    if (packSize > 0) {
                        qtyCtnInput.value = Math.floor(pcs / packSize);
                        updateHeaderTally();
                    }
                } else {
                    // Jika tiada maklumat pack size dalam nama produk, letakkan CTN sama dengan PCS atau kosongkan
                    // qtyCtnInput.value = pcs; 
                }
            }
        }
    }

    let parseTimeout = {};
    function parseRowQR(input, id) {
        const lotString = input.value.trim();
        if (lotString.length < 10) return;

        clearTimeout(parseTimeout[id]);
        parseTimeout[id] = setTimeout(() => {
            const catVal = document.getElementById('main_category').value;
            fetch('ajax_parse_lot.php?lot_no=' + encodeURIComponent(lotString) + '&category=' + encodeURIComponent(catVal))
            .then(res => res.json())
            .then(data => {
            if (data.status === 'success') {
                let batchVal = data.data.batch || '';
                document.getElementById('batch_' + id).value = batchVal;
                
                const expInput = document.getElementById('expiry_' + id);
                if (expInput) {
                    if (expInput._flatpickr && data.data.expiry_date) {
                        expInput._flatpickr.setDate(data.data.expiry_date, true, "d/m/Y");
                    }
                    expInput.value = data.data.expiry_date || '';
                }
                
                // Match Product (Dinaiktaraf untuk padanan kod dan nama pintar)
                if (data.data.product_code) {
                    const sel = document.querySelector(`#row_${id} .prod-select`);
                    if (sel) {
                        let pCode = String(data.data.product_code).toUpperCase();
                        // Extract numeric size properly (e.g., "0200" -> "200")
                        let sizeMatch = pCode.match(/^0*(\d+)/);
                        let numericSize = sizeMatch ? sizeMatch[1] : ""; 
                        
                        // Extract flavor letters after the numeric part (e.g., "0200S24F" -> "S24F" -> "SF")
                        let suffix = pCode.replace(/^\d+/, '');
                        
                        let matched = false;

                        for (let opt of sel.options) {
                            let optText = opt.text.toUpperCase();
                            
                            if (optText.includes(pCode)) {
                                sel.value = opt.value;
                                matched = true;
                                break;
                            }
                            
                            if (numericSize && optText.includes(numericSize + "ML")) {
                                if (suffix.includes('C') && optText.includes('CHOCOLATE')) {
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                } else if (suffix.includes('S') && (optText.includes('STRAW') || optText.includes('SOY') || optText.includes('SWEET'))) {
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                } else if (suffix.includes('F') && (optText.includes('FRESH') || optText.includes('FULL CREAM') || optText.includes('PLAIN'))) {
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                } else if (suffix.includes('K') && optText.includes('KURMA')) {
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                } else if (suffix.includes('B') && optText.includes('BANANA')) {
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                } else if (!suffix.includes('C') && !suffix.includes('S') && !suffix.includes('F') && !suffix.includes('K') && !suffix.includes('B')) {
                                    // Fallback if no specific flavor letter is found
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                }
                            }
                        }
                        
                        if (!matched && numericSize) {
                            for (let opt of sel.options) {
                                if (opt.text.toUpperCase().includes(numericSize + "ML")) {
                                    sel.value = opt.value;
                                    matched = true;
                                    break;
                                }
                            }
                        }
                        $(sel).trigger('change');
                    }
                }
                
                if (data.data.qty_pieces > 0) {
                    const qtyPcsInput = document.getElementById('qty_pcs_' + id);
                    if (qtyPcsInput) qtyPcsInput.value = data.data.qty_pieces;
                    
                    const qtyInput = document.getElementById('qty_' + id);
                    if (qtyInput) {
                        qtyInput.value = ""; // Dikosongkan sehingga produk dipilih
                    }
                    // Cuba kira ctn jika produk sudah dipilih
                    calculateCtn(id);
                }
                
                if (data.data.pallet_raw_code || data.data.pallet_id_short) {
                    const pQtyInput = document.querySelector(`#row_${id} .p-qty-val`);
                    if (pQtyInput && (pQtyInput.value == "0" || pQtyInput.value == "")) {
                        pQtyInput.value = 1;
                    }
                    
                    const raw = String(data.data.pallet_raw_code || '').toUpperCase();
                    const pSel = document.querySelector(`#row_${id} .p-type-sel`);
                    if (pSel) {
                        if (raw.startsWith('PP') || raw.startsWith('PB') || raw.startsWith('PH')) {
                            pSel.value = 'black';
                        } else if (raw.startsWith('PW') || raw.startsWith('PM')) {
                            pSel.value = 'plain';
                        } else if (raw.startsWith('LR') || raw.startsWith('PR')) {
                            pSel.value = 'red';
                        } else if (raw.startsWith('LG') || raw.startsWith('PG')) {
                            pSel.value = 'lhp';
                        } else if (raw.startsWith('FO')) {
                            pSel.value = 'orange';
                        } else if (raw.startsWith('FG')) {
                            pSel.value = 'ffm';
                        }
                    }
                }
                
                input.style.borderColor = "#10b981";
                updateHeaderTally();
                
                const tbody = document.getElementById('itemsBody');
                if (tbody && tbody.lastElementChild && tbody.lastElementChild.id === 'row_' + id) {
                    addRow();
                    setTimeout(() => {
                        const nextInput = document.getElementById('scan_input_' + (rowCount - 1));
                        if (nextInput) nextInput.focus();
                    }, 100);
                }
            }
        })
        .catch(err => {
            console.error('QR Parsing Error:', err);
            input.style.borderColor = "#ef4444";
        });
        }, 500);
    }

    function updateHeaderTally() {
        let totals = { plain:0, red:0, lhp:0, orange:0, ffm:0, black:0 };
        let grand = 0;
        
        document.querySelectorAll('#itemsBody tr').forEach(row => {
            const typeSel = row.querySelector('.p-type-sel');
            const qtyInput = row.querySelector('.p-qty-val');
            if(!typeSel || !qtyInput) return;

            const type = typeSel.value.toLowerCase();
            const qty = parseInt(qtyInput.value) || 0;
            
            grand += qty;
            
            if (totals[type] !== undefined) {
                totals[type] += qty;
            }
        });

        for (let key in totals) {
            const manualInput = document.getElementById(`manual_${key}`);
            const manual = manualInput ? (parseInt(manualInput.value) || 0) : 0;
            const sub = totals[key] + manual;
            
            const subEl = document.getElementById(`sub_${key}`);
            if(subEl) subEl.innerText = sub;
            grand += manual;
        }
        const totalFinalEl = document.getElementById('total_final');
        if(totalFinalEl) totalFinalEl.innerText = grand;
    }

    function openCameraForRow(idx) { activeRowIndex = idx; cameraModalInstance.show(); }
    function startScanner() {
        if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, (text) => {
            const input = document.getElementById('scan_input_' + activeRowIndex);
            input.value = text;
            parseRowQR(input, activeRowIndex);
            cameraModalInstance.hide();
        });
    }
    function stopScanner() { if(html5QrCode) html5QrCode.stop(); }
</script>
<?php require_once 'includes/footer.php'; ?>