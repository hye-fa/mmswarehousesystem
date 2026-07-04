<?php
// api/cleanup_photos.php
// SAFETY: Only deletes photos for Approved+CN reports older than 14 days

require_once '../config/db.php';

header('Content-Type: application/json');

try {
    // 1. Calculate the cutoff date (14 days ago from today)
    $cutoff_date = date('Y-m-d', strtotime('-14 days'));

    // 2. Find records meeting ALL criteria:
    // - Status is Approved
    // - CN Number is recorded
    // - Photos still exist in DB
    // - Discovery date is older than 14 days
    $stmt = $pdo->prepare("SELECT id, photo_path FROM spoilage_logs 
                         WHERE claim_status = 'Approved' 
                         AND cn_number IS NOT NULL 
                         AND photo_path IS NOT NULL
                         AND reported_at <= ?");
    $stmt->execute([$cutoff_date]);
    $records = $stmt->fetchAll();

    $deleted_count = 0;
    $record_updates = 0;
    $base_dir = __DIR__ . "/../uploads/spoilage/";

    foreach ($records as $row) {
        $photos = explode(',', $row['photo_path']);
        $all_deleted = true;

        foreach ($photos as $photo_rel_path) {
            $full_path = $base_dir . trim($photo_rel_path);
            
            if (file_exists($full_path)) {
                if (unlink($full_path)) {
                    $deleted_count++;
                } else {
                    $all_deleted = false; // Keep path in DB if file couldn't be deleted
                }
            }
        }

        // 3. Only clear the database path if the physical files were handled
        if ($all_deleted) {
            $update = $pdo->prepare("UPDATE spoilage_logs SET photo_path = NULL WHERE id = ?");
            $update->execute([$row['id']]);
            $record_updates++;
        }
    }

    echo json_encode([
        'status' => 'success', 
        'message' => "Cleanup complete. Removed $deleted_count photos from $record_updates closed claims (older than $cutoff_date)."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}