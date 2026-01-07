<?php
require_once '../includes/auth_check.php';
// File ini tidak boleh diakses langsung
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set header sebagai JSON
header('Content-Type: application/json');

// (Opsional) Implementasi caching di sini untuk Poin 12 (tidak memberatkan server)
// Cek di tabel `balance_cache` apakah ada data < 5 menit yang lalu.
// Jika ada, kembalikan data dari cache.
// ... (logika cache) ...

// Ambil company_id dari request GET
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if ($company_id === 0) {
    echo json_encode(['status' => false, 'message' => 'Company ID tidak valid.']);
    exit;
}

try {
    // 1. Ambil subscription_key dari database (Poin 7)
    $db = db_connect();
    $stmt = $db->prepare("SELECT subscription_key FROM projects WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch();

    if (!$company) {
        echo json_encode(['status' => false, 'message' => 'Perusahaan tidak ditemukan.']);
        exit;
    }

    $subscription_key = $company['subscription_key'];

    // 2. Buat URL API lengkap (Poin 1 & 5)
    $api_url = TELKOMSEL_API_URL . '?subscriptionKey=' . urlencode($subscription_key);

    // 3. Panggil API (Poin 1, 11, 12)
    $response = call_telkomsel_api($api_url);

    // 4. (Opsional) Simpan hasil ke cache `balance_cache`
    // ... (logika simpan cache) ...

    // 5. Kembalikan response ke frontend
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Terjadi error: ' . $e->getMessage()]);
}
exit;
?>