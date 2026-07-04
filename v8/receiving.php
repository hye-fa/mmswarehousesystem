<?php
// receiving.php
// FINAL VERSION: Updated Pallet Options

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!file_exists('config/db.php')) {
    die("❌ Configuration File Not Found. Please create config/db.php");
}
require_once 'config/db.php';

$products = $pdo->query("SELECT id, name, category, COALESCE(pack_size, 1) as pack_size FROM products WHERE is_active=1 ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Universal Receiving</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        .cat-btn-group .btn-check:checked + .btn { font-weight: bold; border-width: 2px; }
    </style>
</head>
<body>

<div class="container mt-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="index.php" class="btn btn-outline-dark me-2">🏠 Home</a>
            <button onclick="history.back()" class="btn btn-outline-secondary">⬅ Back</button>
        </div>
        <h3 class="text-primary">🚚 Goods Received Note (GRN)</h3>
    </div>

    <!-- 5 CATEGORY TABS -->
    <div class="btn-group w-100 mb-4 cat-btn-group" role="group">
        <input type="radio" class="btn-check" name="cat_btn" id="cat_pss" autocomplete="off" onclick="setCategory('PSS')">
        <label class="btn btn-outline-primary" for="cat_pss">PSS (School)</label>

        <input type="radio" class="btn-check" name="cat_btn" id="cat_pst" autocomplete="off" checked onclick="setCategory('PST')">
        <label class="btn btn-outline-success" for="cat_pst">PST (Fresh)</label>

        <input type="radio" class="btn-check" name="cat_btn" id="cat_uht" autocomplete="off" onclick="setCategory('UHT')">
        <label class="btn btn-outline-warning" for="cat_uht">UHT (Retail)</label>

        <input type="radio" class="btn-check" name="cat_btn" id="cat_ice" autocomplete="off" onclick="setCategory('Ice Cream')">
        <label class="btn btn-outline-info" for="cat_ice">Ice Cream</label>

        <input type="radio" class="btn-check" name="cat_btn" id="cat_water" autocomplete="off" onclick="setCategory('Water')">
        <label class="btn btn-outline-secondary" for="cat_water">Water</label>
    </div>

    <form action="api/save_receiving.php" method="POST" onsubmit="return validateForm()">
        <input type="hidden" name="category" id="selected_category" value="PST">

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-dark text-white">📦 Product Details</div>
                    <div class="card-body">
                        
                        <div id="scan_warning_notice" class="alert alert-warning border-warning shadow-sm p-2 mb-3" role="alert" style="display: none;">
                            <strong class="d-block text-center">⚠️ DOUBLE CHECK QUANTITY!</strong>
                            <small class="d-block text-center text-muted">Pastikan kuantiti fizikal sama dengan angka imbasan.</small>
                        </div>

                        <div class="mb-3">
                            <label>Product Name <span class="text-danger">*</span></label>
                            <select name="product_id" id="product_select" class="form-select" required onchange="recalcQty()">
                                <option value="" data-packsize="0">-- Select Product --</option>
                                <?php foreach($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" 
                                            data-cat="<?= $p['category'] ?>" 
                                            data-packsize="<?= $p['pack_size'] ?>">
                                        <?= htmlspecialchars($p['name']) ?> (<?= $p['pack_size'] ?>/ctn)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="smart_scanner_group">
                            <label class="form-label fw-bold text-primary">Scan Lot No / QR Code</label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control form-control-lg" id="scanner_input" placeholder="Click here & Scan..." onchange="parseLot(this.value)">
                                <button class="btn btn-outline-dark" type="button" onclick="toggleCamera()">📷 Camera</button>
                            </div>
                            <div id="reader" style="width: 100%; display: none;" class="mb-2 border rounded"></div>
                            <small class="text-muted">Auto-fills Expiry, Batch & Pallet ID</small>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label>Batch</label>
                                <input type="text" name="batch_no" id="batch_no" class="form-control" required>
                            </div>
                            <div class="col">
                                <label>Expiry Date</label>
                                <input type="text" name="expiry_date" id="expiry_date" class="form-control datepicker" placeholder="DD/MM/YYYY" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label>Quantity (Cartons) <span class="text-danger">*</span></label>
                            <input type="number" name="qty" id="qty_input" class="form-control form-control-lg border-primary" required min="1">
                            <input type="hidden" id="raw_pcs_input" value="0">
                            
                            <div id="scanned_qty_display" class="alert alert-info mt-2 py-1 px-2" style="display:none; font-size: 0.9rem;">
                                ℹ️ <span id="scanned_qty_val"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">🧱 Pallet & Logistics</div>
                    <div class="card-body">
                        
                        <div class="mb-3" id="pallet_id_group">
                            <label>Pallet ID Tag (Scanned)</label>
                            <input type="text" name="pallet_id_tag" id="pallet_id_tag" class="form-control" readonly placeholder="Auto-filled from scan">
                        </div>

                        <div class="mb-3">
                            <label>Pallet Type (Liability)</label>
                            <select name="pallet_type" class="form-select">
                                <option value="Loscam Red">🔴 Loscam - Red</option>
                                <option value="LHP Green">🟢 LHP - Green</option>
                                <option value="FFM Orange">🟠 FFM - Orange</option>
                                <option value="FFM Green">🟢 FFM - Green</option>
                                <option value="Plastic Black">⚫ Plastic - Black</option>
                                <option value="Plain">Plain/Wood - None</option>
                                <option value="No Pallet">No Pallet</option>
                            </select>
                        </div>

                        <div class="row d-none" id="pst_temp_group">
                            <div class="col">
                                <label>Truck Temp (°C)</label>
                                <input type="number" step="0.1" name="temp_truck" class="form-control">
                            </div>
                            <div class="col">
                                <label>Stock Temp (°C)</label>
                                <input type="number" step="0.1" name="temp_stock" class="form-control">
                            </div>
                        </div>

                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold">💾 VERIFY & SAVE STOCK IN</button>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr(".datepicker", { dateFormat: "d/m/Y", allowInput: true });
    });

    function setCategory(cat) {
        document.getElementById('selected_category').value = cat;
        
        const tempGroup = document.getElementById('pst_temp_group');
        const scannerGroup = document.getElementById('smart_scanner_group');
        const palletIdGroup = document.getElementById('pallet_id_group');

        tempGroup.classList.add('d-none');
        scannerGroup.classList.remove('d-none');
        palletIdGroup.classList.remove('d-none');

        if(cat === 'PST') {
            tempGroup.classList.remove('d-none'); 
            scannerGroup.classList.add('d-none'); 
            palletIdGroup.classList.add('d-none'); 
        } 
        else if (cat === 'Ice Cream' || cat === 'Water') {
            scannerGroup.classList.add('d-none'); 
            palletIdGroup.classList.add('d-none'); 
        }

        filterProducts(cat);
    }

    function filterProducts(cat) {
        const select = document.getElementById('product_select');
        const options = select.querySelectorAll('option');
        const currentVal = select.value;
        let foundCurrent = false;

        options.forEach(opt => {
            if(opt.value === "") return;
            const pCat = opt.getAttribute('data-cat');
            let show = false;
            
            if (cat === 'PSS' && pCat === 'PSS') show = true;
            else if (cat === 'PST' && (pCat === 'PST' || pCat === 'Yogurt' || pCat === 'Dairy' || pCat === 'Juice')) show = true;
            else if (cat === 'UHT' && pCat === 'UHT') show = true;
            else if (cat === 'Ice Cream' && pCat === 'Ice Cream') show = true;
            else if (cat === 'Water' && pCat === 'Water') show = true;
            
            if(pCat === cat) show = true;

            opt.style.display = show ? 'block' : 'none';
            if(opt.value === currentVal && show) foundCurrent = true;
        });

        if(!foundCurrent) select.value = "";
    }

    let html5QrcodeScanner = null;

    function toggleCamera() {
        const readerDiv = document.getElementById('reader');
        if (readerDiv.style.display === 'none') {
            readerDiv.style.display = 'block';
            if(html5QrcodeScanner === null) {
                html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            }
        } else {
            if(html5QrcodeScanner) {
                html5QrcodeScanner.clear().then(() => {
                    html5QrcodeScanner = null;
                    readerDiv.style.display = 'none';
                });
            }
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        document.getElementById('scanner_input').value = decodedText;
        parseLot(decodedText);
        if(html5QrcodeScanner) {
            html5QrcodeScanner.clear().then(() => {
                html5QrcodeScanner = null;
                document.getElementById('reader').style.display = 'none';
            });
        }
    }

    function onScanFailure(error) {}

    function parseLot(scannedString) {
        const currentCat = document.getElementById('selected_category').value;
        const prod = document.getElementById('product_select').value;

        if (currentCat !== 'PSS' && prod === "") {
            Swal.fire({
                title: 'Select Item First',
                text: 'Please select a product before scanning the QR code to calculate cartons accurately.',
                icon: 'warning'
            });
            document.getElementById('scanner_input').value = "";
            return;
        }

        if(scannedString.length < 5) return;

        fetch('ajax_parse_lot.php?lot_no=' + encodeURIComponent(scannedString))
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    document.getElementById('batch_no').value = data.data.batch;
                    document.getElementById('expiry_date').value = data.data.expiry_date;
                    document.getElementById('pallet_id_tag').value = data.data.pallet_id;
                    document.getElementById('scan_warning_notice').style.display = 'block';

                    let message = "Data Auto-filled successfully.";
                    if(data.data.qty_pieces > 0) {
                        document.getElementById('raw_pcs_input').value = data.data.qty_pieces;
                        recalcQty();
                        const cartons = document.getElementById('qty_input').value;
                        if(cartons > 0) {
                            message = `Calculated <b>${cartons} Cartons</b> from ${data.data.qty_pieces} Pcs.`;
                        } else {
                            message = `Found <b>${data.data.qty_pieces} Pcs</b>. Select Product to calculate cartons.`;
                        }
                    }

                    Swal.fire({
                        title: 'MMS Warehouse Management System',
                        html: `
                            <h4 class="text-success">Successfully Scanned!</h4>
                            <p>${message}</p>
                            <hr>
                            <div class="alert alert-warning" style="text-align:left; font-size: 0.9em;">
                                <strong>⚠️ PLEASE DOUBLE CHECK!</strong><br>
                                Verify the <b>Physical Quantity</b> matches the figure above.
                                <br><i>Sila pastikan kuantiti fizikal adalah betul.</i>
                            </div>
                        `,
                        icon: 'success',
                        timer: 5000, 
                        showConfirmButton: true,
                        confirmButtonText: 'OK, I Verified'
                    });

                } else {
                    Swal.fire({ title: 'Scan Error', text: data.message, icon: 'error' });
                }
            })
            .catch(error => {
                Swal.fire({ title: 'System Error', text: error, icon: 'error' });
            });
    }

    function recalcQty() {
        const rawPcs = parseInt(document.getElementById('raw_pcs_input').value) || 0;
        const select = document.getElementById('product_select');
        let packSize = 1;
        
        if (select.selectedIndex >= 0) {
            packSize = parseInt(select.options[select.selectedIndex].getAttribute('data-packsize')) || 1;
        }

        const displayDiv = document.getElementById('scanned_qty_display');
        const displayVal = document.getElementById('scanned_qty_val');

        if (rawPcs > 0) {
            displayDiv.style.display = 'block';
            if (packSize > 1) {
                const cartons = Math.floor(rawPcs / packSize);
                document.getElementById('qty_input').value = cartons;
                displayVal.innerHTML = `Scanned QR: <b>${cartons} Cartons</b> (${rawPcs} pcs / ${packSize})`;
            } else {
                displayVal.innerHTML = `Scanned QR: <b>${rawPcs} Pcs</b> <span class="text-danger">(Update Pack Size in DB to Calc)</span>`;
            }
        } else {
            displayDiv.style.display = 'none';
        }
    }

    function validateForm() {
        const prod = document.getElementById('product_select').value;
        const qty = document.getElementById('qty_input').value;

        if (prod === "" || qty === "" || parseInt(qty) <= 0) {
            Swal.fire({ title: 'Missing Info', text: 'Please select a Product and enter a valid Quantity.', icon: 'warning' });
            return false;
        }
        return true;
    }

    setCategory('PST');
</script>

</body>
</html>