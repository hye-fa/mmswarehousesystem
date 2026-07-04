<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Item Receiving</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        .table-responsive { overflow-x: visible; }
        .table-sm td, .table-sm th { padding: 0.25rem; vertical-align: bottom; }
        .form-control-sm { font-size: 0.85rem; }
        .is-filled { background-color: #e8f0fe; border-color: #0d6efd; }
        .pallet-input { max-width: 70px; text-align: center; font-weight: bold; background-color: #fff; }
        .qr-input { cursor: text; background-color: #fff; border: 1px solid #ced4da; } 
        .qr-input:focus { border-color: #86b7fe; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
        .highlight-input { border: 2px solid #dc3545; background-color: #fff0f0; }
        .category-locked { background-color: #e9ecef; color: #6c757d; border-color: #ced4da; cursor: not-allowed; }
        
        /* Tally Table Styling */
        .tally-table th { font-size: 0.85rem; text-align: center; background-color: #f8f9fa; }
        .tally-table td { text-align: center; padding: 0.3rem; }
        .grand-total-row { background-color: #212529; color: white; font-weight: bold; font-size: 1.1rem; }
        .grand-total-row input { background-color: #495057; color: white; border: none; }

        /* Stacked Input Labels (Tiny helper text) */
        .input-label-sm { font-size: 0.7rem; color: #6c757d; display: block; margin-bottom: 1px; }
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
        
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">1. Delivery Info (Header)</div>
            <div class="card-body">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Supplier DO No. <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_do" class="form-control text-uppercase fw-bold" required placeholder="e.g. DO-12345" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="col-md-2">
                        <label>PO / SO Number</label>
                        <input type="text" name="po_number" class="form-control text-uppercase fw-bold" placeholder="e.g. SO-9876" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="col-md-2">
                        <label>Ordered Date</label>
                        <input type="text" name="ordered_date" class="form-control datepicker" placeholder="DD/MM/YYYY">
                    </div>
                    <div class="col-md-2">
                        <label>Received Date</label>
                        <input type="text" name="received_date" class="form-control datepicker" value="<?php echo date('d/m/Y'); ?>" required>
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

                <div class="row g-3 p-3 bg-light border rounded">
                    <div class="col-md-3">
                        <label>Transporter Company</label>
                        <input type="text" name="transporter_name" class="form-control" 
                               placeholder="e.g. Tiong Nam" 
                               oninput="toTitleCase(this)">
                    </div>
                    <div class="col-md-3">
                        <label>Driver Name</label>
                        <input type="text" name="driver_name" class="form-control" 
                               placeholder="e.g. Ali Bin Abu" 
                               oninput="toTitleCase(this)">
                    </div>
                    <div class="col-md-3">
                        <label>Vehicle Plate No.</label>
                        <input type="text" name="vehicle_plate" class="form-control text-uppercase fw-bold" 
                               placeholder="e.g. VAB1234" 
                               maxlength="10"
                               oninput="formatPlate(this)">
                        <small class="text-muted" style="font-size: 0.65rem;">No Space. e.g. W1234A</small>
                    </div>
                    <div class="col-md-3">
                        <label>Arrival Time</label>
                        <input type="time" name="arrival_time" class="form-control" value="<?php echo date('H:i'); ?>">
                    </div>
                </div>
                
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
                                    <tr>
                                        <td class="tally-label text-secondary">🟤 Plain/Wood</td>
                                        <td><input type="text" id="auto_plain" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_plain" id="manual_plain" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_plain" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <tr>
                                        <td class="tally-label text-danger">🔴 Loscam Red</td>
                                        <td><input type="text" id="auto_red" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_red" id="manual_red" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_red" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <tr>
                                        <td class="tally-label text-success">🟢 LHP Green</td>
                                        <td><input type="text" id="auto_lhp" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_lhp_green" id="manual_lhp" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_lhp" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <tr>
                                        <td class="tally-label text-warning">🟠 FFM Orange</td>
                                        <td><input type="text" id="auto_orange" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_orange" id="manual_orange" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_orange" class="fw-bold fs-5">0</span></td>
                                    </tr>
                                    <tr>
                                        <td class="tally-label text-success" style="border-left: 4px solid orange;">🟢 FFM Green</td>
                                        <td><input type="text" id="auto_ffm" class="form-control-plaintext text-center fw-bold" value="0" readonly></td>
                                        <td><input type="number" name="manual_qty_ffm_green" id="manual_ffm" class="form-control form-control-sm text-center fw-bold" value="0" min="0" oninput="updateHeaderTally()"></td>
                                        <td><span id="total_ffm" class="fw-bold fs-5">0</span></td>
                                    </tr>
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
                            <th width="35%">Product / QR Scan</th>
                            <th width="15%">Expiry / Pallet ID</th>
                            <th width="15%">Batch No / Pallet Type</th>
                            <th width="15%">Qty (Ctn) / Plt Qty</th>
                            <th width="5%">X</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
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
    // --- PRODUCT LIST (Safe Format) ---
    const productList = [
        {"id":43,"name":"Almond 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":63,"name":"Almond 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":65,"name":"Almond Barista 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":44,"name":"Almond Unsweetened 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":64,"name":"Almond Unsweetened 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":23,"name":"Butter Salted 9gm","category":"Dairy","pack_size":200,"pallet_capacity":60},
        {"id":24,"name":"Butter Unsalted 9gm","category":"Dairy","pack_size":200,"pallet_capacity":60},
        {"id":59,"name":"Café Latte Tongkat Ali 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":76,"name":"Choc Soy 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":22,"name":"Cooking Cream 1L","category":"Dairy","pack_size":12,"pallet_capacity":60},
        {"id":34,"name":"Drinking Water 1.5L","category":"Water","pack_size":1,"pallet_capacity":60},
        {"id":32,"name":"FF Choco Bar 60ml","category":"Ice Cream","pack_size":24,"pallet_capacity":60},
        {"id":30,"name":"FF Cream Hauz Chocolate 75ml","category":"Ice Cream","pack_size":24,"pallet_capacity":60},
        {"id":31,"name":"FF Cream Hauz Vanilla 75ml","category":"Ice Cream","pack_size":24,"pallet_capacity":60},
        {"id":50,"name":"Full Cream Family Pack 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":17,"name":"Fyog Apr 120gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":20,"name":"Fyog Mango 120gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":16,"name":"Fyog Mix 120gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":19,"name":"Fyog Natural 120gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":18,"name":"Fyog Peach 120gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":15,"name":"Fyog Strawberry 120gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":28,"name":"GC Apple","category":"Juice","pack_size":1,"pallet_capacity":60},
        {"id":27,"name":"GC Grape","category":"Juice","pack_size":1,"pallet_capacity":60},
        {"id":29,"name":"GC Melon","category":"Juice","pack_size":1,"pallet_capacity":60},
        {"id":25,"name":"GC Ori","category":"Juice","pack_size":30,"pallet_capacity":60},
        {"id":26,"name":"GC Tutti","category":"Juice","pack_size":1,"pallet_capacity":60},
        {"id":14,"name":"Greek Yog 470gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":75,"name":"Grow Up 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":67,"name":"Grow Up 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":38,"name":"Henry Jones A2 Organic 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":60,"name":"Henry Jones A2 Organic 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":61,"name":"Lactose Free 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":51,"name":"Low Fat Family Pack 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":33,"name":"Mineral Water 500ml","category":"Water","pack_size":1,"pallet_capacity":60},
        {"id":42,"name":"Oat 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":62,"name":"Oat 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":66,"name":"Oat Barista 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":1,"name":"PSS Yarra Full Cream 200ml","category":"PSS","pack_size":24,"pallet_capacity":144},
        {"id":7,"name":"PST Australian Milk 1L","category":"PST","pack_size":12,"pallet_capacity":75},
        {"id":8,"name":"PST Australian Milk 2L","category":"PST","pack_size":6,"pallet_capacity":60},
        {"id":6,"name":"PST Barista 1L","category":"PST","pack_size":12,"pallet_capacity":75},
        {"id":10,"name":"PST Chocolate Milk 1L C1","category":"PST","pack_size":12,"pallet_capacity":75},
        {"id":87,"name":"PST Chocolate Milk 568ml C568","category":"PST","pack_size":12,"pallet_capacity":60},
        {"id":2,"name":"PST Glass Bottle Milk 1L","category":"PST","pack_size":12,"pallet_capacity":75},
        {"id":12,"name":"PST Kurma Milk 700ml K7","category":"PST","pack_size":12,"pallet_capacity":60},
        {"id":9,"name":"PST Lactose Free 1L LF1","category":"PST","pack_size":12,"pallet_capacity":75},
        {"id":4,"name":"PST Pure Fresh Milk 1L F1","category":"PST","pack_size":12,"pallet_capacity":75},
        {"id":5,"name":"PST Pure Fresh Milk 2L F2","category":"PST","pack_size":6,"pallet_capacity":60},
        {"id":3,"name":"PST Pure Fresh Milk 568ml F568","category":"PST","pack_size":12,"pallet_capacity":60},
        {"id":46,"name":"Soy Chocolate 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":58,"name":"Soy Chocolate 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":45,"name":"Soy Ori 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":57,"name":"Soy Ori 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":74,"name":"UHT Banana 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":55,"name":"UHT Banana 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":54,"name":"UHT Café Latte 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":72,"name":"UHT Chocolate 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":36,"name":"UHT Chocolate 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":53,"name":"UHT Chocolate 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":71,"name":"UHT Fresh 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":37,"name":"UHT Fresh 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":56,"name":"UHT Fresh Milk 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":73,"name":"UHT Kurma 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":35,"name":"UHT Kurma 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":52,"name":"UHT Kurma 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":86,"name":"UHT Moola 100ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":21,"name":"Whipping cream","category":"Dairy","pack_size":12,"pallet_capacity":60},
        {"id":78,"name":"Yarra Chocolate 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":48,"name":"Yarra Chocolate 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":69,"name":"Yarra Chocolate 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":77,"name":"Yarra Full Cream 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":47,"name":"Yarra Full Cream 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":68,"name":"Yarra Full Cream 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":41,"name":"Yarra Full Cream Professional 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":40,"name":"Yarra Master Barista 1L (CAP)","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":39,"name":"Yarra Master Barista 1L (W/CAP)","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":79,"name":"Yarra Strawberry 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":49,"name":"Yarra Strawberry 1L","category":"UHT","pack_size":12,"pallet_capacity":75},
        {"id":70,"name":"Yarra Strawberry 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":13,"name":"Yarra Yog 470gm","category":"Yogurt","pack_size":12,"pallet_capacity":60},
        {"id":85,"name":"Yog Mango 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":80,"name":"Yog Mango 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":84,"name":"Yog Mix Berry 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":81,"name":"Yog Mix Berry 200ml","category":"UHT","pack_size":24,"pallet_capacity":144},
        {"id":83,"name":"Yog Strawberry 125ml","category":"UHT","pack_size":32,"pallet_capacity":192},
        {"id":82,"name":"Yog Strawberry 200ml","category":"UHT","pack_size":24,"pallet_capacity":144}
    ];

    let html5QrCode = null;
    let activeRowIndex = null;
    let globalRowCounter = 0;
    let cameraModalInstance = null;

    document.addEventListener('DOMContentLoaded', function() {
        if(typeof flatpickr !== 'undefined') {
            flatpickr(".datepicker", { dateFormat: "d/m/Y", allowInput: true });
        } else {
            console.error("Flatpickr not loaded! Check internet connection.");
        }
        
        const modalEl = document.getElementById('cameraModal');
        cameraModalInstance = new bootstrap.Modal(modalEl);
        modalEl.addEventListener('shown.bs.modal', function () { startScanner(); });
        modalEl.addEventListener('hidden.bs.modal', function () { stopScanner(); });
        
        applyCategoryFilter();
    });

    // --- HELPER: Title Case ---
    function toTitleCase(input) {
        let str = input.value.toLowerCase().split(' ');
        for (let i = 0; i < str.length; i++) {
            str[i] = str[i].charAt(0).toUpperCase() + str[i].slice(1);
        }
        input.value = str.join(' ');
    }

    // --- HELPER: Plate Number Format ---
    function formatPlate(input) {
        let val = input.value.toUpperCase();
        val = val.replace(/[^A-Z0-9]/g, '');
        val = val.replace(/^[^A-Z]+/, ''); 
        let parts = val.split(/(\d+)/);
        let final = parts[0] || ""; 
        if (parts[1]) {
            let nums = parts[1].substring(0, 4);
            final += nums;
            if (parts[2]) {
                let suffix = parts[2].replace(/[^A-Z]/g, '');
                final += suffix;
            }
        }
        input.value = final;
    }

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
        try {
            const tbody = document.getElementById('itemsBody');
            if(!tbody) { console.error("TBody not found!"); return; }
            const rowId = 'row_' + globalRowCounter;
            
            let optionsHtml = '<option value="">- Select -</option>';
            productList.forEach(p => {
                optionsHtml += `<option value="${p.id}" data-cat="${p.category}" data-packsize="${p.pack_size}" data-palletcap="${p.pallet_capacity}">${p.name}</option>`;
            });

            // UPDATED LAYOUT: Stacked inputs for mobile friendliness
            const html = `
                <tr id="${rowId}">
                    <td>
                        <span class="input-label-sm">Product Name</span>
                        <select name="items[${globalRowCounter}][product_id]" class="form-select form-select-sm prod-select mb-2" required onchange="onProductSelect('${globalRowCounter}')">
                            ${optionsHtml}
                        </select>
                        
                        <span class="input-label-sm">Scan QR</span>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0">🔍</span>
                            <input type="text" id="scan_input_${globalRowCounter}" class="form-control qr-input border-start-0" placeholder="Scan Lot..." 
                                   onchange="parseRowQR(this, '${globalRowCounter}')">
                            <button class="btn btn-outline-secondary" type="button" onclick="openCameraForRow('${globalRowCounter}')">📷</button>
                        </div>
                    </td>
                    
                    <td>
                        <span class="input-label-sm">Expiry Date</span>
                        <input type="text" name="items[${globalRowCounter}][expiry_date]" id="expiry_${globalRowCounter}" class="form-control form-control-sm row-datepicker is-filled mb-2" placeholder="DD/MM/YYYY" required>
                        
                        <span class="input-label-sm fw-bold text-primary">Pallet ID</span>
                        <input type="text" name="items[${globalRowCounter}][pallet_id]" id="pallet_id_${globalRowCounter}" class="form-control form-control-sm fw-bold text-center border-primary" placeholder="P#" readonly>
                    </td>

                    <td>
                        <span class="input-label-sm">Batch No</span>
                        <input type="text" name="items[${globalRowCounter}][batch_no]" id="batch_${globalRowCounter}" class="form-control form-control-sm is-filled mb-2" required>
                        
                        <span class="input-label-sm fw-bold text-primary">Pallet Type</span>
                        <select name="items[${globalRowCounter}][pallet_type]" id="pallet_type_${globalRowCounter}" class="form-select form-select-sm pallet-type-select border-primary" onchange="updateHeaderTally()">
                            <option value="No Pallet">No Pallet</option>
                            <option value="Select_Type" class="fw-bold text-danger">-- Select Type --</option>
                            <option value="Plain">🟤 Plain/Wood</option>
                            <option value="Loscam Red">🔴 Loscam</option>
                            <option value="LHP Green">🟢 LHP</option>
                            <option value="FFM Orange">🟠 FFM O</option>
                            <option value="FFM Green">🟢 FFM G</option>
                            <option value="Plastic Black">⚫ Plastic</option>
                        </select>
                    </td>

                    <td>
                        <span class="input-label-sm">Qty (Ctn)</span>
                        <input type="number" name="items[${globalRowCounter}][qty]" id="qty_${globalRowCounter}" 
                               class="form-control form-control-sm fw-bold mb-2 qty-input" 
                               required min="1" oninput="checkRowVolume('${globalRowCounter}')">
                        <input type="hidden" id="raw_pcs_${globalRowCounter}" value="0">
                        <input type="hidden" name="items[${globalRowCounter}][production_time]" value=""> <span class="input-label-sm fw-bold text-primary">Pallet Qty</span>
                        <input type="number" name="items[${globalRowCounter}][pallet_qty]" id="pallet_qty_${globalRowCounter}" 
                               class="form-control form-control-sm pallet-qty-input border-primary" value="0" min="0" oninput="updateHeaderTally()">
                    </td>

                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow('${rowId}')">×</button>
                    </td>
                </tr>
            `;
            
            tbody.insertAdjacentHTML('beforeend', html);
            
            if(typeof flatpickr !== 'undefined') {
                flatpickr("#expiry_" + globalRowCounter, { dateFormat: "d/m/Y", allowInput: true });
            }

            const newSelect = document.querySelector(`#${rowId} .prod-select`);
            const currentCat = document.getElementById('main_category').value;
            filterDropdown(newSelect, currentCat);

            if (prefillData) fillRowData(globalRowCounter, prefillData);
            globalRowCounter++;
            checkCategoryLock();

        } catch(e) {
            console.error(e);
            alert("Error adding row: " + e.message);
        }
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

    // UPDATED: Logic to force "Select Type" if volume is high or P# detected
    function checkRowVolume(index) {
        const select = document.querySelector(`select[name="items[${index}][product_id]"]`);
        const qtyInput = document.getElementById('qty_' + index);
        const palletQtyInput = document.getElementById('pallet_qty_' + index);
        const palletTypeSelect = document.getElementById('pallet_type_' + index);

        if (select && select.value && qtyInput.value) {
            const qty = parseInt(qtyInput.value) || 0;
            const palletCap = parseInt(select.options[select.selectedIndex].getAttribute('data-palletcap')) || 60;
            const threshold = palletCap * 0.65;

            // If Qty is high (>65% pallet)
            if (qty > threshold) {
                // Highlight inputs
                palletQtyInput.classList.add('highlight-input');
                palletTypeSelect.classList.add('highlight-input');

                // Auto-set Pallet Qty to 1 if it was 0
                if(parseInt(palletQtyInput.value) === 0) {
                    palletQtyInput.value = 1;
                    
                    // Force user to Select Type (don't assume Red)
                    if(palletTypeSelect.value === 'No Pallet') {
                        palletTypeSelect.value = 'Select_Type'; 
                    }
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
                else if (type === 'LHP Green') greenL += qty;
                else if (type === 'FFM Orange') orange += qty;
                else if (type === 'FFM Green') greenF += qty;
                else if (type === 'Plastic Black') black += qty;
                else if (type === 'Plain') plain += qty;
                // Note: "Select_Type" is ignored here (count = 0)
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
                    document.getElementById('global_scan_warning').style.display = 'block';
                    fillRowData(index, data.data);
                    input.classList.add('is-valid');
                } else {
                    Swal.fire('Scan Error', data.message, 'error');
                }
            });
    }

    function fillRowData(index, data) {
        // Fill basic data
        document.getElementById('batch_' + index).value = data.batch;
        
        const expiryInput = document.getElementById('expiry_' + index);
        if(expiryInput._flatpickr) {
            expiryInput._flatpickr.setDate(data.expiry_date, true, "d/m/Y");
        } else {
            expiryInput.value = data.expiry_date;
        }

        // Fill Pallet ID (New)
        if(data.pallet_id_short) {
            document.getElementById('pallet_id_' + index).value = data.pallet_id_short;
            
            // Logic: If Pallet ID found, force "Select Type" to prompt user
            const pType = document.getElementById('pallet_type_' + index);
            if(pType.value === 'No Pallet') {
                pType.value = 'Select_Type';
                pType.classList.add('highlight-input'); // Draw attention
            }
        }

        if(data.qty_pieces > 0) {
            document.getElementById('raw_pcs_' + index).value = data.qty_pieces;
            recalcRowQty(index);
        }
        checkCategoryLock();
        checkRowVolume(index); // Re-run volume check
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
        let selectTypeError = false;
        const rows = tbody.rows;
        
        for (let i = 0; i < rows.length; i++) {
            const palletQtyInput = rows[i].querySelector('.pallet-qty-input');
            const palletTypeSelect = rows[i].querySelector('.pallet-type-select');
            
            // Check 1: High volume but 0 pallet qty
            if (palletQtyInput.classList.contains('highlight-input')) {
                if (parseInt(palletQtyInput.value) <= 0) {
                    hasError = true;
                }
            }

            // Check 2: Pallet Qty > 0 but "Select Type" is still chosen
            if (parseInt(palletQtyInput.value) > 0 && palletTypeSelect.value === 'Select_Type') {
                selectTypeError = true;
                palletTypeSelect.classList.add('highlight-input');
            }
        }

        if (hasError) {
            Swal.fire('Missing Pallet Qty!', 'Some high-volume items have <b>0 Pallets</b> assigned.<br>Please check red boxes.', 'error');
            return false;
        }

        if (selectTypeError) {
            Swal.fire('Select Pallet Type!', 'You have items with Pallet Qty but <b>Undefined Type</b>.<br>Please select Red, Green, etc.', 'error');
            return false;
        }

        return true;
    }

    window.onload = function() { 
        addRow(); 
        applyCategoryFilter(); 
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