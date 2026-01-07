<?php
// Validasi Sesi Login
require_once '../includes/auth_check.php'; 
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set header sebagai JSON
header('Content-Type: application/json');

$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if ($company_id === 0) {
    echo json_encode(['status' => false, 'message' => 'Company ID tidak valid.']);
    exit;
}

try {
    // 1. Ambil subscription_key dari database (Poin 3)
    $db = db_connect();
    $stmt = $db->prepare("SELECT subscription_key FROM projects WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch();

    if (!$company) {
        echo json_encode(['status' => false, 'message' => 'Perusahaan tidak ditemukan.']);
        exit;
    }

    $subscription_key = $company['subscription_key'];

    // 2. Buat URL API lengkap (Poin 2 & 3)
    // Kita gunakan URL baru dari config
    $api_url = TELKOMSEL_PKG_URL . '?' . http_build_query([
        'subscriptionKey' => $subscription_key,
        'page' => 1,
        'pageSize' => 20 // Sesuai contoh cURL Anda
    ]);

    // 3. Panggil API (Poin 2: Metode GET)
    // Kita gunakan ulang fungsi call_telkomsel_api() karena metode autentikasinya SAMA
    // (api-key, x-signature, x-timestamp)
    $response = call_telkomsel_api($api_url);

    // 4. Kembalikan response ke frontend
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Terjadi error: ' . $e->getMessage()]);
}
exit;
?>