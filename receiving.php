<?php
// receiving.php
// Updated: Security Checked, Category & Searchable Product Filter, Camera Scan Integrated

require_once 'config/db.php';

// Memastikan sesi dimulakan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sekatan peranan: Hanya Admin dan Staff sahaja boleh menerima stok
$role = $_SESSION['role'] ?? '';
$is_staff = ($role === 'admin' || $role === 'staff');
if (!$is_staff) {
    http_response_code(403);
    echo '<div style="font-family: sans-serif; text-align: center; padding: 100px 20px;">
            <h1 style="color: #e74c3c;">🚫 Akses Dihalang!</h1>
            <p>Anda tidak mempunyai kebenaran untuk mengakses halaman penerimaan stok ini.</p>
            <a href="index.php" style="color: #3498db; font-weight: bold; text-decoration: none;">Kembali ke Dashboard</a>
          </div>';
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category    = htmlspecialchars(strip_tags($_POST['category'] ?? ''));
    $product_id  = (int)($_POST['product_id'] ?? 0);
    $lot_no      = htmlspecialchars(strip_tags($_POST['lot_no'] ?? ''));
    $expiry_date = htmlspecialchars(strip_tags($_POST['expiry_date'] ?? ''));
    $batch_no    = htmlspecialchars(strip_tags($_POST['batch_no'] ?? ''));
    $pallet_id   = htmlspecialchars(strip_tags($_POST['pallet_id'] ?? ''));
    $qty         = (int)($_POST['qty'] ?? 0);

    if (empty($category) || empty($product_id) || empty($batch_no) || empty($qty) || empty($expiry_date)) {
        $message = '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-x-circle-fill me-2"></i>Sila lengkapkan semua ruangan wajib.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // Pemetaan pallet type berdasarkan kod pallet_id (cth: PP003 -> Plastic Black)
            $p_type = 'Plain';
            $raw_pallet = strtoupper($pallet_id);
            if (strpos($raw_pallet, 'PP') === 0 || strpos($raw_pallet, 'PB') === 0) {
                $p_type = 'Plastic Black';
            } elseif (strpos($raw_pallet, 'PW') === 0 || strpos($raw_pallet, 'PM') === 0) {
                $p_type = 'Plain';
            } elseif (strpos($raw_pallet, 'LR') === 0 || strpos($raw_pallet, 'PR') === 0) {
                $p_type = 'Loscam Red';
            } elseif (strpos($raw_pallet, 'LG') === 0 || strpos($raw_pallet, 'PG') === 0) {
                $p_type = 'LHP Green';
            } elseif (strpos($raw_pallet, 'FO') === 0) {
                $p_type = 'FFM Orange';
            } elseif (strpos($raw_pallet, 'FG') === 0) {
                $p_type = 'FFM Green';
            }

            // 1. Rekod header inbound
            $supplier_do = 'SR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $pallet_red = ($p_type === 'Loscam Red') ? 1 : 0;
            $pallet_orange = ($p_type === 'FFM Orange') ? 1 : 0;
            $pallet_black = ($p_type === 'Plastic Black') ? 1 : 0;
            $pallet_ffm = ($p_type === 'FFM Green') ? 1 : 0;
            $pallet_lhp = ($p_type === 'LHP Green') ? 1 : 0;
            $pallet_remarks = "Single GRN: Lot $lot_no";

            $stmtHeader = $pdo->prepare("INSERT INTO inbound_logs 
                (category, received_date, supplier_do, remarks, 
                 pallet_qty_loscam_red, pallet_qty_ffm_orange, pallet_qty_plastic_black,
                 pallet_qty_ffm_green, pallet_qty_lhp_green) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
            $stmtHeader->execute([
                $category, $supplier_do, $pallet_remarks,
                $pallet_red, $pallet_orange, $pallet_black, $pallet_ffm, $pallet_lhp
            ]);
            $inbound_id = $pdo->lastInsertId();

            // 2. Rekod item inbound
            $stmtItem = $pdo->prepare("INSERT INTO inbound_items 
                (inbound_id, product_id, batch_no, qty_received, expiry_date) 
                VALUES (?, ?, ?, ?, ?)");
            $stmtItem->execute([$inbound_id, $product_id, $batch_no, $qty, $expiry_date]);

            // 3. Masukkan ke stok aktif (inventory_batches)
            $stmtStock = $pdo->prepare("INSERT INTO inventory_batches 
                (product_id, batch_no, lot_no_raw, expiry_date, qty_on_hand, location_status, pallet_type, pallet_id_tag) 
                VALUES (?, ?, ?, ?, ?, 'Warehouse', ?, ?)");
            $stmtStock->execute([$product_id, $batch_no, $lot_no, $expiry_date, $qty, $p_type, $pallet_id]);

            // 4. Rekod log aktiviti sistem
            log_system_activity("Received Stock (Single)", "inbound_logs", $inbound_id, "Single GRN diproses: ID $inbound_id, Rujukan $supplier_do (Qty: $qty ctn).");

            $pdo->commit();
            $message = '<div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>Stok berjaya diterima! (GRN ID: ' . $inbound_id . ')</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>Ralat menyimpan data: ' . $e->getMessage() . '</div>';
        }
    }
}

// Dapatkan senarai semua produk aktif
$products = [];
try {
    $stmt = $pdo->query("SELECT id, name, category, pack_size, pallet_capacity FROM products WHERE is_active = 1 ORDER BY name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Abaikan ralat
}

$page_title = 'Single Inbound Receiving';
require_once 'includes/header.php';
?>

<style>
    .content-wrapper {
        flex-grow: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        margin-top: 1rem;
    }

    .form-container { 
        width: 100%;
        max-width: 650px; 
        background: white; 
        padding: 35px; 
        border-radius: 20px; 
        box-shadow: var(--card-shadow); 
        border: 1px solid rgba(241, 245, 249, 0.9);
    }
    .readonly-input { background-color: #f1f5f9; cursor: not-allowed; font-weight: bold; }
    .select2-container .select2-selection--single {
        height: 38px;
        border: 1.8px solid #cbd5e1;
        display: flex;
        align-items: center;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    
    @media (max-width: 576px) {
        .form-container {
            padding: 22px 16px;
            border-radius: 16px;
        }
        .content-wrapper {
            padding: 10px;
            margin-top: 0.5rem;
        }
    }
</style>

<div class="content-wrapper">
    <div class="form-container">
        <h3 class="text-success mb-4 border-bottom pb-2 fw-800"><i class="bi bi-box-seam-fill me-2"></i>Single Item Receiving</h3>
        
        <?= $message ?>

        <form action="receiving.php" method="post" id="singleReceiveForm">
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Category</label>
                    <select name="category" id="category" class="form-select" onchange="filterProducts()" required>
                        <option value="UHT" selected>UHT (Retail)</option>
                        <option value="PSS">PSS (School)</option>
                        <option value="PST">PST (Fresh/Dairy)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Select Product</label>
                    <select name="product_id" id="product_id" class="form-select" required>
                        <!-- Pilihan akan dimasukkan melalui JavaScript -->
                    </select>
                </div>
            </div>

            <div class="mb-4 bg-light p-3 rounded border border-primary border-opacity-25">
                <label class="form-label fw-bold text-primary mb-1 d-flex justify-content-between align-items-center">
                    <span>SCAN LOT NO:</span>
                    <button type="button" class="btn btn-primary btn-sm fw-bold px-3 py-1" onclick="openCamera()">
                        <i class="bi bi-camera-fill me-1"></i> Scan Camera
                    </button>
                </label>
                <input type="text" name="lot_no" id="lot_no" class="form-control border-primary text-uppercase fw-bold" placeholder="e.g. 260831-MFB010-PP003" oninput="parseLotNo()" autocomplete="off">
                <div class="form-text small text-muted">Mengisi butiran di bawah secara automatik daripada lot/QR barcode.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Expiry Date</label>
                    <input type="date" name="expiry_date" id="expiry_date" class="form-control readonly-input" required readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Batch No</label>
                    <input type="text" name="batch_no" id="batch_no" class="form-control readonly-input text-center" required readonly>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Shelf Life (Months)</label>
                    <input type="text" id="shelf_life" class="form-control readonly-input text-center" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Pallet ID (Raw Code)</label>
                    <input type="text" name="pallet_id" id="pallet_id" class="form-control readonly-input text-center" required readonly>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold text-navy" style="font-size: 1.1rem;">Quantity (Cartons) *</label>
                <input type="number" name="qty" class="form-control form-control-lg border-2 border-info text-center fw-bold" placeholder="Masukkan Kuantiti (ctn)" required min="1">
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm">Confirm Receive</button>
                <a href="index.php" class="btn btn-secondary py-2 fw-bold">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- Modal Kamera QR/Barcode -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-0">
            <div class="modal-body p-0 text-center position-relative">
                <div id="reader" style="width:100%;"></div>
                <button class="btn btn-danger m-3 px-4 fw-bold" data-bs-dismiss="modal">Tutup Kamera</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    const productList = <?= json_encode($products); ?>;
    let html5QrCode = null;
    let cameraModalInstance = null;

    $(document).ready(function() {
        cameraModalInstance = new bootstrap.Modal(document.getElementById('cameraModal'));
        
        // Initialize Select2
        $('#product_id').select2({
            width: '100%',
            placeholder: '-- Select Product --'
        });
        
        // Filter produk berdasarkan kategori lalai
        filterProducts();

        // Kawalan kamera
        document.getElementById('cameraModal').addEventListener('shown.bs.modal', startScanner);
        document.getElementById('cameraModal').addEventListener('hidden.bs.modal', stopScanner);
    });

    function filterProducts() {
        const cat = document.getElementById('category').value;
        const sel = $('#product_id');
        const currentVal = sel.val();
        
        sel.empty();
        sel.append('<option value="">-- Select Product --</option>');
        
        productList.forEach(p => {
            if (p.category === cat) {
                sel.append(`<option value="${p.id}" data-cat="${p.category}">${p.name}</option>`);
            }
        });
        
        if (currentVal) {
            sel.val(currentVal);
        }
        sel.trigger('change');
    }

    let parseTimeout;
    function parseLotNo() {
        let lotString = document.getElementById('lot_no').value.trim();
        if (lotString.length < 10) return;

        clearTimeout(parseTimeout);
        parseTimeout = setTimeout(() => {
            const catVal = document.getElementById('category').value;
            fetch('ajax_parse_lot.php?lot_no=' + encodeURIComponent(lotString) + '&category=' + encodeURIComponent(catVal))
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Isi Batch No & Expiry Date
                    document.getElementById('batch_no').value = data.data.batch || '';
                    document.getElementById('pallet_id').value = data.data.pallet_raw_code || '';
                    if (data.data.qty_pieces && data.data.qty_pieces > 0) {
                        let finalQty = data.data.qty_pieces;
                        const sel = document.getElementById('product_id');
                        if (sel && sel.value) {
                            const optText = sel.options[sel.selectedIndex].text;
                            const match = optText.match(/(\d+)\s*(PK|PCS|PC)\/CTN/i);
                            if (match) {
                                let packSize = parseInt(match[1]);
                                if (packSize > 0) {
                                    finalQty = Math.floor(data.data.qty_pieces / packSize);
                                }
                            }
                        }
                        document.querySelector('input[name="qty"]').value = finalQty;
                    }
                    
                    // Expiry Date format conversion (d/m/Y -> Y-m-d untuk input type="date")
                    if (data.data.expiry_date) {
                        let expParts = data.data.expiry_date.split('/');
                        if (expParts.length === 3) {
                            document.getElementById('expiry_date').value = `${expParts[2]}-${expParts[1]}-${expParts[0]}`;
                        }
                    }

                    // Kira Shelf Life Months berdasarkan Batch No (cth: B10 -> 10)
                    let batch = data.data.batch || '';
                    let numericShelf = batch.replace(/[^0-9]/g, '');
                    if (numericShelf) {
                        document.getElementById('shelf_life').value = parseInt(numericShelf, 10);
                    }

                    // Padanan Produk Pintar
                    if (data.data.product_code) {
                        let pCode = String(data.data.product_code).toUpperCase();
                        let numericSize = pCode.replace(/[^0-9]/g, '');
                        numericSize = parseInt(numericSize, 10).toString();
                        
                        let matched = false;
                        const sel = document.getElementById('product_id');

                        for (let opt of sel.options) {
                            let optText = opt.text.toUpperCase();
                            if (optText.includes(pCode)) {
                                $('#product_id').val(opt.value).trigger('change');
                                matched = true;
                                break;
                            }
                            if (numericSize && optText.includes(numericSize + "ML")) {
                                let isSchoolProduct = optText.includes("SCHOOL");
                                if (catVal === 'PSS' && isSchoolProduct) {
                                    if (pCode.includes('C') && optText.includes('CHOCOLATE')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (!pCode.includes('C') && !optText.includes('CHOCOLATE')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    }
                                } else if (catVal !== 'PSS' && !isSchoolProduct) {
                                    if (pCode.includes('S') && (optText.includes('STRAWBERRY') || optText.includes('SOY') || optText.includes('SWEET'))) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('C') && optText.includes('CHOCOLATE')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('F') && (optText.includes('FRESH') || optText.includes('FULL CREAM'))) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('K') && optText.includes('KURMA')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    } else if (pCode.includes('B') && optText.includes('BANANA')) {
                                        $('#product_id').val(opt.value).trigger('change');
                                        matched = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if (!matched && numericSize) {
                            for (let opt of sel.options) {
                                if (opt.text.toUpperCase().includes(numericSize + "ML")) {
                                    $('#product_id').val(opt.value).trigger('change');
                                    matched = true;
                                    break;
                                }
                            }
                        }
                    }
                }
            })
            .catch(err => console.error('Error parsing QR:', err));
        }, 300);
    }

    function openCamera() {
        cameraModalInstance.show();
    }

    function startScanner() {
        if (!html5QrCode) html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, (text) => {
            document.getElementById('lot_no').value = text;
            parseLotNo();
            cameraModalInstance.hide();
        });
    }

    function stopScanner() {
        if (html5QrCode) {
            html5QrCode.stop().catch(err => console.error("Error stopping scanner:", err));
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>