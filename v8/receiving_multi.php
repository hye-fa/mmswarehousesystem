<?php
// receiving_multi.php
// MULTI-ITEM RECEIVING FORM
// UPDATED: Added Logistics Info (Transporter, Driver, Plate, Time)

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';

// Fetch Products
$products = $pdo->query("SELECT id, name, category, COALESCE(pack_size, 1) as pack_size, COALESCE(pallet_capacity, 60) as pallet_capacity FROM products WHERE is_active=1 ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Item Receiving</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        .table-responsive { overflow-x: auto; }
        .table-sm td, .table-sm th { padding: 0.25rem; vertical-align: middle; }
        .form-control-sm { font-size: 0.85rem; }
        .is-filled { background-color: #e8f0fe; border-color: #0d6efd; }
        .pallet-input { max-width: 70px; text-align: center; font-weight: bold; background-color: #fff; }
        .qr-input { cursor: text; background-color: #fff; border: 1px solid #ced4da; } 
        .qr-input:focus { border-color: #86b7fe; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
        .highlight-input { border: 2px solid #dc3545; background-color: #fff0f0; }
        .category-locked { background-color: #e9ecef; color: #6c757d; border-color: #ced4da; cursor: not-allowed; }

        .tally-table th { font-size: 0.85rem; text-align: center; background-color: #f8f9fa; }
        .tally-table td { text-align: center; padding: 0.3rem; }
        .tally-label { text-align: left; font-weight: bold; }
        .modal { z-index: 1055 !important; }
        .modal-backdrop { z-index: 1050 !important; }
        .grand-total-row { background-color: #212529; color: white; font-weight: bold; font-size: 1.1rem; }
        .grand-total-row input { background-color: #495057; color: white; border: none; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="index.php" class="btn btn-outline-dark me-2">🏠 Home</a>
            <button onclick="history.back()" class="btn btn-outline-secondary">⬅ Back</button>
        </div>
        <h3 class="text-primary">🚚 Multi-Item Receiving (GRN)</h3>
    </div>

    <!-- DOUBLE CHECK NOTICE (Hidden by default) -->
    <div id="global_scan_warning" class="alert alert-warning border-warning shadow-sm py-2 mb-3" role="alert" style="display: none;">
        <div class="d-flex align-items-center">
            <span class="fs-4 me-2">⚠️</span>
            <div>
                <strong>DOUBLE CHECK QUANTITY!</strong><br>
                <small>Please verify the Physical Quantity matches the Scanned Figure before saving.</small>
            </div>
        </div>
    </div>

    <form id="multiReceiveForm" action="api/save_receiving_multi.php" method="POST" onsubmit="return validateMultiForm()">
        
        <!-- HEADER SECTION -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">1. Delivery Info (Header)</div>
            <div class="card-body">
                
                <!-- Row 1: Main Doc Info -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Supplier DO No. <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_do" class="form-control" required placeholder="e.g. DO-12345">
                    </div>
                    <div class="col-md-2">
                        <label>PO / SO Number</label>
                        <input type="text" name="po_number" class="form-control" placeholder="e.g. SO-9876">
                    </div>
                    <div class="col-md-2">
                        <label>Ordered Date</label>
                        <input type="text" name="ordered_date" class="form-control datepicker" placeholder="DD/MM/YYYY">
                    </div>
                    <div class="col-md-2">
                        <label>Received Date</label>
                        <input type="text" name="received_date" class="form-control datepicker" value="<?= date('d/m/Y') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>Category (Filter)</label>
                        <select name="category" class="form-select border-primary fw-bold" id="main_category" onchange="applyCategoryFilter()">
                            <option value="PST" selected>PST (Fresh)</option>
                            <option value="UHT">UHT (Retail)</option>
                            <option value="PSS">PSS (School)</option>
                            <option value="Ice Cream">Ice Cream</option>
                        </select>
                        <small id="cat_lock_msg" class="text-danger fw-bold" style="display:none;">🔒 Locked: Clear items to switch.</small>
                    </div>
                </div>

                <!-- Row 2: Logistics Info (NEW) -->
                <div class="row g-3 p-3 bg-light border rounded">
                    <div class="col-md-3">
                        <label>Transporter Company</label>
                        <input type="text" name="transporter_name" class="form-control" placeholder="e.g. Tiong Nam">
                    </div>
                    <div class="col-md-3">
                        <label>Driver Name</label>
                        <input type="text" name="driver_name" class="form-control" placeholder="e.g. Ali bin Abu">
                    </div>
                    <div class="col-md-3">
                        <label>Vehicle Plate No.</label>
                        <input type="text" name="vehicle_plate" class="form-control text-uppercase" placeholder="e.g. VAB 1234">
                    </div>
                    <div class="col-md-3">
                        <label>Arrival Time</label>
                        <input type="time" name="arrival_time" class="form-control" value="<?= date('H:i') ?>">
                    </div>
                </div>
                
                <!-- PALLET TALLY TABLE -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <label class="fw-bold text-primary mb-2">🧱 Pallet Tally (Auto + Manual)</label>
                        <div class="table-responsive bg-white border rounded" id="pallet_section">
                            <table class="table table-bordered mb-0 tally-table">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Type</th>
                                        <th style="width: 20%;">Auto (From Rows)</th>
                                        <th style="width: 20%;">+ Shared / Manual</th>
                                        <th style="width: 20%;">= Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Plain/Wood -->
                                    <tr>
                                        <td class="tally-label text-secondary">🟤 Plain/Wood</td>
                                        <td><input type="text" id="auto_plain" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_plain" id="manual_plain" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_plain" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <!-- Loscam Red -->
                                    <tr>
                                        <td class="tally-label text-danger">🔴 Loscam Red</td>
                                        <td><input type="text" id="auto_red" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_red" id="manual_red" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_red" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <!-- LHP Green -->
                                    <tr>
                                        <td class="tally-label text-success">🟢 LHP Green</td>
                                        <td><input type="text" id="auto_lhp" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_lhp_green" id="manual_lhp" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_lhp" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <!-- FFM Orange -->
                                    <tr>
                                        <td class="tally-label text-warning">🟠 FFM Orange</td>
                                        <td><input type="text" id="auto_orange" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_orange" id="manual_orange" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_orange" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <!-- FFM Green -->
                                    <tr>
                                        <td class="tally-label text-success" style="border-left: 4px solid orange;">🟢 FFM Green</td>
                                        <td><input type="text" id="auto_ffm" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_ffm_green" id="manual_ffm" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_ffm" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <!-- Plastic Black -->
                                    <tr>
                                        <td class="tally-label text-dark">⚫ Plastic Black</td>
                                        <td><input type="text" id="auto_black" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_black" id="manual_black" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_black" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="grand-total-row">
                                        <td class="text-end">GRAND TOTAL:</td>
                                        <td><input type="text" id="grand_auto" class="form-control-plaintext text-center text-white fw-bold" value="0" readonly></td>
                                        <td><input type="text" id="grand_manual" class="form-control-plaintext text-center text-white fw-bold" value="0" readonly></td>
                                        <td class="text-center text-warning fs-4"><span id="grand_final">0</span></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- CAMERA MODAL -->
        <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-white">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Scan QR Code</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center p-0">
                        <div id="reader" style="width: 100%; min-height: 250px;"></div>
                        <div id="camera_error" class="alert alert-danger m-2" style="display:none;"></div>
                        <button type="button" class="btn btn-secondary m-2" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEMS SECTION -->
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                <span>2. Items List</span>
                <div>
                    <button type="button" class="btn btn-sm btn-light fw-bold" onclick="addRow()">+ Add Empty Row</button>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-striped table-sm mb-0" id="itemsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="20%">Product Name <span class="text-danger">*</span></th>
                            <th width="15%">Lot / QR Scan</th>
                            <th width="10%">Expiry</th>
                            <th width="10%">Batch</th>
                            <th width="8%">Prod. Time</th>
                            <th width="8%">Qty (Ctn)</th>
                            <th width="12%" class="bg-warning-subtle">Row Pallet</th>
                            <th width="8%" class="bg-warning-subtle">Qty</th>
                            <th width="5%">X</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Rows added by JS -->
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">+ Add Another Row</button>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success btn-lg py-3 fw-bold">💾 SAVE ALL ITEMS</button>
        </div>

    </form>
</div>

<script>
    const productList = <?php echo json_encode($products); ?>;
    let html5QrCode = null;
    let activeRowIndex = null;
    let globalRowCounter = 0;
    let cameraModalInstance = null;

    document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".datepicker", { dateFormat: "d/m/Y", allowInput: true });
        
        const modalEl = document.getElementById('cameraModal');
        cameraModalInstance = new bootstrap.Modal(modalEl);
        modalEl.addEventListener('shown.bs.modal', function () { startScanner(); });
        modalEl.addEventListener('hidden.bs.modal', function () { stopScanner(); });
        
        applyCategoryFilter();
    });

    // --- FILTER & LOCK LOGIC ---
    function applyCategoryFilter() {
        const selectedCat = document.getElementById('main_category').value;
        const rows = document.querySelectorAll('#itemsBody tr');
        rows.forEach(row => {
            const select = row.querySelector('.prod-select');
            if(select) filterDropdown(select, selectedCat);
        });
    }

    function filterDropdown(selectElement, category) {
        const options = selectElement.querySelectorAll('option');
        const currentVal = selectElement.value;
        let currentIsValid = false;

        options.forEach(opt => {
            if (opt.value === "") return;
            const pCat = opt.getAttribute('data-cat');
            let show = false;

            if (category === 'PSS' && pCat === 'PSS') show = true;
            else if (category === 'PST' && (pCat === 'PST' || pCat === 'Yogurt' || pCat === 'Dairy' || pCat === 'Juice')) show = true;
            else if (category === 'UHT' && pCat === 'UHT') show = true;
            else if (category === 'Ice Cream' && pCat === 'Ice Cream') show = true;
            else if (category === 'Water' && pCat === 'Water') show = true;

            if(show) {
                opt.style.display = "";
                if(opt.value === currentVal) currentIsValid = true;
            } else {
                opt.style.display = "none";
            }
        });
        if(!currentIsValid) selectElement.value = "";
    }

    function checkCategoryLock() {
        const selects = document.querySelectorAll('.prod-select');
        let hasSelection = false;
        selects.forEach(sel => {
            if(sel.value !== "") hasSelection = true;
        });
        const mainCat = document.getElementById('main_category');
        const msg = document.getElementById('cat_lock_msg');
        if(hasSelection) {
            mainCat.disabled = true;
            mainCat.classList.add('category-locked');
            if(msg) msg.style.display = 'inline';
        } else {
            mainCat.disabled = false;
            mainCat.classList.remove('category-locked');
            if(msg) msg.style.display = 'none';
        }
    }

    // --- ROW LOGIC ---
    function addRow(prefillData = null) {
        const tbody = document.getElementById('itemsBody');
        const rowId = 'row_' + globalRowCounter;
        
        let optionsHtml = '<option value="">- Select -</option>';
        productList.forEach(p => {
            optionsHtml += `<option value="${p.id}" data-cat="${p.category}" data-packsize="${p.pack_size}" data-palletcap="${p.pallet_capacity}">${p.name}</option>`;
        });

        const html = `
            <tr id="${rowId}">
                <td>
                    <select name="items[${globalRowCounter}][product_id]" class="form-select form-select-sm prod-select" required onchange="onProductSelect('${globalRowCounter}')">
                        ${optionsHtml}
                    </select>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="text" id="scan_input_${globalRowCounter}" class="form-control qr-input" placeholder="Scan or Type..." 
                               onchange="parseRowQR(this, '${globalRowCounter}')">
                        <button class="btn btn-outline-secondary" type="button" onclick="openCameraForRow('${globalRowCounter}')">📷</button>
                    </div>
                </td>
                <td>
                    <input type="text" name="items[${globalRowCounter}][expiry_date]" id="expiry_${globalRowCounter}" class="form-control form-control-sm row-datepicker is-filled" placeholder="DD/MM/YYYY" required>
                </td>
                <td>
                    <input type="text" name="items[${globalRowCounter}][batch_no]" id="batch_${globalRowCounter}" class="form-control form-control-sm is-filled" required>
                </td>
                <td>
                    <input type="time" name="items[${globalRowCounter}][production_time]" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="number" name="items[${globalRowCounter}][qty]" id="qty_${globalRowCounter}" 
                           class="form-control form-control-sm fw-bold border-primary qty-input" 
                           required min="1" oninput="checkRowVolume('${globalRowCounter}')">
                    <input type="hidden" id="raw_pcs_${globalRowCounter}" value="0">
                </td>
                <td>
                    <select name="items[${globalRowCounter}][pallet_type]" id="pallet_type_${globalRowCounter}" class="form-select form-select-sm pallet-type-select" onchange="updateHeaderTally()">
                        <option value="No Pallet">No Pallet</option>
                        <option value="Plain">🟤 Plain/Wood</option>
                        <option value="Loscam Red">🔴 Loscam</option>
                        <option value="LHP Green">🟢 LHP</option>
                        <option value="FFM Orange">🟠 FFM O</option>
                        <option value="FFM Green">🟢 FFM G</option>
                        <option value="Plastic Black">⚫ Plastic</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${globalRowCounter}][pallet_qty]" id="pallet_qty_${globalRowCounter}" 
                           class="form-control form-control-sm pallet-qty-input" value="0" min="0" oninput="updateHeaderTally()">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow('${rowId}')">×</button>
                </td>
            </tr>
        `;
        
        tbody.insertAdjacentHTML('beforeend', html);
        flatpickr("#expiry_" + globalRowCounter, { dateFormat: "d/m/Y", allowInput: true });

        const newSelect = document.querySelector(`#${rowId} .prod-select`);
        const currentCat = document.getElementById('main_category').value;
        filterDropdown(newSelect, currentCat);

        if (prefillData) fillRowData(globalRowCounter, prefillData);
        globalRowCounter++;
        checkCategoryLock(); 
    }

    function onProductSelect(index) {
        checkRowVolume(index);
        recalcRowQty(index);
        checkCategoryLock();
    }

    function removeRow(id) { 
        document.getElementById(id).remove(); 
        updateHeaderTally();
        checkCategoryLock();
    }

    // ... (Volume Check, Tally Update, Camera Functions same as previous) ...

    function checkRowVolume(index) {
        const select = document.querySelector(`select[name="items[${index}][product_id]"]`);
        const qtyInput = document.getElementById('qty_' + index);
        const palletQtyInput = document.getElementById('pallet_qty_' + index);
        const palletTypeSelect = document.getElementById('pallet_type_' + index);

        if (select && select.value && qtyInput.value) {
            const qty = parseInt(qtyInput.value) || 0;
            const palletCap = parseInt(select.options[select.selectedIndex].getAttribute('data-palletcap')) || 60;
            const threshold = palletCap * 0.65;

            if (qty > threshold) {
                palletQtyInput.classList.add('highlight-input');
                palletTypeSelect.classList.add('highlight-input');
                if(parseInt(palletQtyInput.value) === 0) {
                    palletQtyInput.value = 1;
                    if(palletTypeSelect.value === 'No Pallet') palletTypeSelect.value = 'Loscam Red';
                }
            } else {
                palletQtyInput.classList.remove('highlight-input');
                palletTypeSelect.classList.remove('highlight-input');
            }
        }
        updateHeaderTally();
    }

    function updateHeaderTally() {
        let red=0, greenL=0, orange=0, greenF=0, black=0, plain=0;
        
        const rows = document.querySelectorAll('#itemsBody tr');
        rows.forEach(row => {
            const typeSelect = row.querySelector('.pallet-type-select');
            const qtyInput = row.querySelector('.pallet-qty-input');
            
            if(typeSelect && qtyInput) {
                const qty = parseInt(qtyInput.value) || 0;
                const type = typeSelect.value;
                if (type === 'Loscam Red') red += qty;
                if (type === 'LHP Green') greenL += qty;
                if (type === 'FFM Orange') orange += qty;
                if (type === 'FFM Green') greenF += qty;
                if (type === 'Plastic Black') black += qty;
                if (type === 'Plain') plain += qty;
            }
        });

        document.getElementById('auto_red').value = red;
        document.getElementById('auto_lhp').value = greenL;
        document.getElementById('auto_orange').value = orange;
        document.getElementById('auto_ffm').value = greenF;
        document.getElementById('auto_black').value = black;
        document.getElementById('auto_plain').value = plain;

        const man_red = parseInt(document.getElementById('manual_red').value) || 0;
        const man_lhp = parseInt(document.getElementById('manual_lhp').value) || 0;
        const man_orange = parseInt(document.getElementById('manual_orange').value) || 0;
        const man_ffm = parseInt(document.getElementById('manual_ffm').value) || 0;
        const man_black = parseInt(document.getElementById('manual_black').value) || 0;
        const man_plain = parseInt(document.getElementById('manual_plain').value) || 0;

        const tot_red = red + man_red;
        const tot_lhp = greenL + man_lhp;
        const tot_orange = orange + man_orange;
        const tot_ffm = greenF + man_ffm;
        const tot_black = black + man_black;
        const tot_plain = plain + man_plain;

        document.getElementById('total_red').innerText = tot_red;
        document.getElementById('total_lhp').innerText = tot_lhp;
        document.getElementById('total_orange').innerText = tot_orange;
        document.getElementById('total_ffm').innerText = tot_ffm;
        document.getElementById('total_black').innerText = tot_black;
        document.getElementById('total_plain').innerText = tot_plain;

        const grandAuto = red + greenL + orange + greenF + black + plain;
        const grandManual = man_red + man_lhp + man_orange + man_ffm + man_black + man_plain;
        const grandFinal = tot_red + tot_lhp + tot_orange + tot_ffm + tot_black + tot_plain;

        document.getElementById('grand_auto').value = grandAuto;
        document.getElementById('grand_manual').value = grandManual;
        document.getElementById('grand_final').innerText = grandFinal;
    }

    async function startScanner() {
        document.getElementById('camera_error').style.display = 'none';
        if (html5QrCode) { await stopScanner(); }
        html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
            .catch(err => {
                document.getElementById('camera_error').innerText = "Camera Error: " + err;
                document.getElementById('camera_error').style.display = 'block';
            });
    }

    async function stopScanner() {
        if (html5QrCode) {
            try { await html5QrCode.stop(); await html5QrCode.clear(); } catch (err) {}
            html5QrCode = null;
        }
    }

    function openCameraForRow(index) {
        activeRowIndex = index;
        if(cameraModalInstance) cameraModalInstance.show();
    }

    function onScanSuccess(decodedText) {
        if(activeRowIndex !== null) {
            const input = document.getElementById('scan_input_' + activeRowIndex);
            input.value = decodedText;
            parseRowQR(input, activeRowIndex);
            cameraModalInstance.hide();
        }
    }

    function onScanFailure(error) {}

    function parseRowQR(input, index) {
        const scannedString = input.value;
        if(scannedString.length < 5) return;

        fetch('ajax_parse_lot.php?lot_no=' + encodeURIComponent(scannedString))
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    // Show warning
                    document.getElementById('global_scan_warning').style.display = 'block';
                    
                    fillRowData(index, data.data);
                    input.classList.add('is-valid');
                } else {
                    Swal.fire('Scan Error', data.message, 'error');
                }
            });
    }

    function fillRowData(index, data) {
        document.getElementById('batch_' + index).value = data.batch;
        const expiryInput = document.getElementById('expiry_' + index);
        if(expiryInput._flatpickr) {
            expiryInput._flatpickr.setDate(data.expiry_date, true, "d/m/Y");
        } else {
            expiryInput.value = data.expiry_date;
        }
        if(data.qty_pieces > 0) {
            document.getElementById('raw_pcs_' + index).value = data.qty_pieces;
            recalcRowQty(index);
        }
        checkCategoryLock();
    }

    function recalcRowQty(index) {
        const select = document.querySelector(`select[name="items[${index}][product_id]"]`);
        const qtyInput = document.getElementById('qty_' + index);
        const rawPcs = parseInt(document.getElementById('raw_pcs_' + index).value) || 0;
        if (select.value && rawPcs > 0) {
            const packSize = parseInt(select.options[select.selectedIndex].getAttribute('data-packsize')) || 1;
            if(packSize > 1) {
                qtyInput.value = Math.floor(rawPcs / packSize);
                checkRowVolume(index);
            } else {
                qtyInput.placeholder = "Manual Calc";
            }
        }
    }

    function validateMultiForm() {
        document.getElementById('main_category').disabled = false;
        const tbody = document.getElementById('itemsBody');
        if (tbody.rows.length === 0) {
            Swal.fire('Empty List', 'Please add at least one item.', 'warning');
            return false;
        }
        
        let hasError = false;
        const rows = tbody.rows;
        
        for (let i = 0; i < rows.length; i++) {
            const palletQtyInput = rows[i].querySelector('.pallet-qty-input');
            if (palletQtyInput.classList.contains('highlight-input')) {
                if (parseInt(palletQtyInput.value) <= 0) {
                    hasError = true;
                }
            }
        }

        if (hasError) {
            Swal.fire({
                title: 'Missing Pallet Info!',
                html: `Some high-volume items have <b>0 Pallets</b> assigned.<br>Please check the highlighted red boxes in the list.`,
                icon: 'error'
            });
            document.getElementById('pallet_section').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        return true;
    }

    window.onload = function() { 
        addRow(); 
        applyCategoryFilter(); 
        // Check if lock message element exists
        if(!document.getElementById('cat_lock_msg')) {
             const container = document.querySelector('#main_category').parentNode;
             const msg = document.createElement('small');
             msg.id = 'cat_lock_msg';
             msg.className = 'text-danger fw-bold d-block mt-1';
             msg.style.display = 'none';
             msg.innerText = '🔒 Locked: Clear items to switch.';
             container.appendChild(msg);
        }
    };
</script>

</body>
</html>