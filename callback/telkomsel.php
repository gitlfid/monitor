<?php
// Ini adalah endpoint callback, tidak perlu session atau HTML
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set header output sebagai JSON
header('Content-Type: application/json');

// 1. Baca data JSON mentah dari body request
$raw_payload = file_get_contents('php://input');

// 2. (Opsional tapi PENTING) Verifikasi request
// Telkomsel seharusnya mengirimkan signature atau token di header.
// Anda harus memvalidasi itu di sini untuk keamanan.
// Contoh:
// $telkomsel_signature = $_SERVER['HTTP_X_TELKOMSEL_SIGNATURE'] ?? '';
// if (!validate_callback_signature($raw_payload, $telkomsel_signature)) {
//     http_response_code(403);
//     echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
//     exit;
// }


// 3. Simpan data ke database (Poin 3)
if (!empty($raw_payload)) {
    $data = json_decode($raw_payload, true);

    // Asumsi data yang masuk (berdasarkan image_3ce90d.png)
    // Kita perlu tahu key 'msisdn' dan 'status' dari Telkomsel
    // Mari kita asumsikan strukturnya:
    // { "msisdn": "628...", "status": "SUCCESS", "response": "{...}" }
    // Jika tidak, kita simpan saja payload mentahnya.
    
    // Mari kita buat asumsi berdasarkan log Anda:
    // Kolom 1 = MSISDN (misal: 'msisdn')
    // Kolom 2 = Status (misal: 'status')
    // Kolom 3 = JSON Response (kita simpan $raw_payload)
    
    $msisdn = $data['msisdn'] ?? null;
    $status = $data['status'] ?? null;
    
    // Jika status tidak ada di root, coba cari di dalam response
    if ($status === null && isset($data['response'])) {
        $inner_response = json_decode($data['response'], true);
        $status = $inner_response['status'] ?? 'UNKNOWN';
    } elseif ($status === null) {
        $status = 'UNKNOWN';
    }


    try {
        $db = db_connect();
        $stmt = $db->prepare("INSERT INTO injection_logs (msisdn, status, response_payload) VALUES (?, ?, ?)");
        $stmt->execute([$msisdn, $status, $raw_payload]);

        // Kirim response 200 OK ke Telkomsel
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Callback received']);

    } catch (PDOException $e) {
        // Jika gagal simpan ke DB, kirim error 500
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save to database: ' . $e->getMessage()]);
    }

} else {
    // Jika tidak ada payload
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No payload received']);
}

exit;
?>