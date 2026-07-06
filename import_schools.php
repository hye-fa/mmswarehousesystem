<?php
// import_schools.php
// Module to Bulk Update School Data from CSV

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config/db.php';


$page_title = 'Import School Data';
require_once 'includes/header.php';
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-dark">🏠 Home</a>
        <h3 class="text-primary">📂 Import School Database (New CO)</h3>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">Upload CSV File</div>
        <div class="card-body">
            
            <div class="alert alert-info">
                <strong>Instructions:</strong>
                <ul>
                    <li>Save your Excel file as <b>CSV (Comma delimited)</b>.</li>
                    <li>The system will match schools by <b>KOD SEKOLAH</b>.</li>
                    <li>It will update: <b>Student Count, HD Assignment, Zone, Address</b>.</li>
                    <li>Ensure HD Names in the file match the HD Names in the system exactly (e.g., "WALI", "NOR IDAYU").</li>
                </ul>
            </div>

            <form action="api/save_school_import.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="csv_file" class="form-label fw-bold">Select CSV File</label>
                    <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">🚀 Upload & Update Database</button>
                </div>
            </form>

        </div>
    </div>

    <!-- Sample Column Reference -->
    <div class="mt-4">
        <h5>Required Columns (Order doesn't matter, but Headers must match roughly):</h5>
        <p class="text-muted small">
            KOD SEKOLAH, NAMA SEKOLAH, BIL PELAJAR, ALAMAT, POSKOD, BANDAR, DAERAH, NEGERI, NO TEL, ZON HD, Nama HD
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>