<?php
// import_products.php
require_once 'config/db.php';
?>
<?php
$page_title = 'Master Product Import | MMS';
require_once 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .template-table { font-size: 0.8rem; background: #f8f9fa; }
    .card-import { border-top: 5px solid var(--mms-cyan); }
</style>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm card-import">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    <span>📦 Master Product Import & Update</span>
                    <a href="index.php" class="btn-close" aria-label="Close"></a>
                </div>
                <div class="card-body">
                    <p class="text-muted">Use this tool to bulk-update your product master list. If a product <strong>name</strong> matches an existing record, the system will update its details. Otherwise, a new product will be created.</p>
                    
                    <div class="alert alert-warning">
                        <h6 class="fw-bold">CSV Column Order (Required):</h6>
                        <table class="table table-sm table-bordered template-table mb-0">
                            <thead>
                                <tr>
                                    <th>name</th>
                                    <th>category</th>
                                    <th>uom</th>
                                    <th>pack_size</th>
                                    <th>pallet_capacity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Yarra Full Cream 1L</td>
                                    <td>PST</td>
                                    <td>Carton</td>
                                    <td>12</td>
                                    <td>75</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <form id="importForm" action="api/process_product_import.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Choose CSV File</label>
                            <input type="file" name="product_file" class="form-control" accept=".csv" required>
                            <div class="form-text">Save your Excel as <strong>CSV (Comma Delimited)</strong> before uploading.</div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">🚀 Process Master List</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('importForm').onsubmit = function(e) {
    e.preventDefault();
    Swal.fire({ 
        title: 'Updating Database...', 
        text: 'Please wait while we process the records.',
        allowOutsideClick: false, 
        didOpen: () => { Swal.showLoading(); } 
    });
    
    fetch(this.action, { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Swal.fire('Import Complete', data.message, 'success').then(() => location.href='index.php');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    });
};
</script>
<?php require_once 'includes/footer.php'; ?>