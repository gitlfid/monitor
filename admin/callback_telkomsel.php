<?php
// === PERUBAHAN START: Path 'require_once' diperbaiki ===
// Path ini SALAH dan menyebabkan Fatal Error di error_log kamu
// require_once '../core/db_connect.php';
// require_once '../core/functions.php';

// Ganti dengan path yang BENAR, sesuai file lain di 'admin/'
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Sekarang kita bisa panggil koneksi DB (yang mengembalikan PDO $db)
$db = db_connect(); // Menggunakan fungsi dari functions.php
// === PERUBAHAN END ===


force_log("--- TELKOMSEL CALLBACK RECEIVED ---");
force_log("Callback Data (RAW): " . file_get_contents('php://input'));
force_log("Callback GET Data: " . json_encode($_GET));

$callback_data = json_decode(file_get_contents('php://input'), true) ?? $_GET;

$request_id = $callback_data['request_id'] ?? $callback_data['requestId'] ?? null;
$status_from_telkomsel = strtoupper($callback_data['status'] ?? '');
$error_code = $callback_data['error_code'] ?? null;
$error_message = $callback_data['error_message'] ?? null;

if (empty($request_id) || empty($status_from_telkomsel)) {
    // === PERUBAHAN: Tambah log untuk debug ===
    force_log("Callback Error: Missing request_id or status. Data: " . json_encode($callback_data));
    http_response_code(400); 
    exit('Missing parameters');
}

// 1. Cari item history berdasarkan request_id
// === PERUBAHAN START: Ganti dari mysqli ($conn) ke PDO ($db) ===
// $stmt_find = $conn->prepare("SELECT id, batch_id, status FROM inject_history WHERE request_id = ?");
// $stmt_find->bind_param("s", $request_id);
// $stmt_find->execute();
// $history_item = $stmt_find->get_result()->fetch_assoc();
// $stmt_find->close();

$stmt_find = $db->prepare("SELECT id, batch_id, status FROM inject_history WHERE request_id = ?");
$stmt_find->execute([$request_id]);
$history_item = $stmt_find->fetch(PDO::FETCH_ASSOC);
// === PERUBAHAN END ===

if (!$history_item) {
    // === PERUBAHAN: Tambah log untuk debug ===
    force_log("Callback Error: Request ID not found: " . $request_id);
    http_response_code(404); 
    exit('Request ID not found');
}
if ($history_item['status'] !== 'SUBMITTED') {
    // === PERUBAHAN: Tambah log untuk debug ===
    force_log("Callback Info: Request ID " . $request_id . " already processed. Status: " . $history_item['status']);
    http_response_code(200); 
    exit('Already processed');
}

$history_id = $history_item['id'];
$batch_id = $history_item['batch_id'];

// 2. Update status di inject_history
$final_status = ($status_from_telkomsel === 'SUCCESS') ? 'SUCCESS' : 'FAILED';
$api_response_update = json_encode($callback_data);

// === PERUBAHAN START: Ganti dari mysqli ($conn) ke PDO ($db) ===
// $stmt_update = $conn->prepare("UPDATE inject_history SET status = ?, api_response = ?, error_code = ?, error_message = ?, updated_at = NOW() WHERE id = ?");
// $stmt_update->bind_param("ssssi", $final_status, $api_response_update, $error_code, $error_message, $history_id);
// $stmt_update->execute();
// $stmt_update->close();

$stmt_update = $db->prepare("UPDATE inject_history SET status = ?, api_response = ?, error_code = ?, error_message = ?, updated_at = NOW() WHERE id = ?");
$stmt_update->execute([$final_status, $api_response_update, $error_code, $error_message, $history_id]);
// === PERUBAHAN END ===

force_log("Callback Success: Updated request_id " . $request_id . " to status " . $final_status);


// === PERUBAHAN START: Tambah Try-Catch ===
// Ini untuk mencegah error jika tabel 'inject_batches' (dari SQL-mu) belum ada
// atau jika $batch_id null.
try {
    if ($batch_id) {
        // 3. Update counter di tabel inject_batches
        $update_column = ($final_status === 'SUCCESS') ? 'success_count' : 'failed_count';
        // === PERUBAHAN: Ganti ke PDO dan amankan $batch_id ===
        $db->query("UPDATE inject_batches SET $update_column = $update_column + 1 WHERE id = " . intval($batch_id));

        // 4. Update status transaksi utama jika semua sudah selesai
        // === PERUBAHAN START: Ganti dari mysqli ($conn) ke PDO ($db) ===
        // $check_stmt = $conn->prepare("SELECT total_numbers, success_count, failed_count FROM inject_batches WHERE id = ?");
        // $check_stmt->bind_param("i", $batch_id);
        // $check_stmt->execute();
        // $batch_summary = $check_stmt->get_result()->fetch_assoc();
        // $check_stmt->close();

        $check_stmt = $db->prepare("SELECT total_numbers, success_count, failed_count FROM inject_batches WHERE id = ?");
        $check_stmt->execute([$batch_id]);
        $batch_summary = $check_stmt->fetch(PDO::FETCH_ASSOC);
        // === PERUBAHAN END ===

        if ($batch_summary && (($batch_summary['success_count'] + $batch_summary['failed_count']) >= $batch_summary['total_numbers'])) {
            // === PERUBAHAN: Ganti ke PDO dan amankan $batch_id ===
            $db->query("UPDATE inject_batches SET status = 'COMPLETED' WHERE id = " . intval($batch_id));
            force_log("Callback Info: Batch " . $batch_id . " marked as COMPLETED.");
        }
    }

} catch (PDOException $e) {
    // Jika gagal (misal tabel inject_batches belum ada), catat error tapi JANGAN GAGALKAN callback
    force_log("Callback DB Warning: Gagal update batch. Error: " . $e->getMessage());
}
// === PERUBAHAN END: Try-Catch Selesai ===
// === PERUBAHAN: Hapus $conn->close() karena PDO tidak perlu ini ===
// $conn->close(); 
http_response_code(200);
echo "Callback processed";
?>