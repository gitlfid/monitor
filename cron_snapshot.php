<?php
// Script ini dirancang untuk dijalankan oleh CRON Job (bukan oleh browser)
// Pastikan path ke file config dan functions sudah benar
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Menandai awal proses di log
echo "--- Memulai Proses Snapshot Harian " . date('Y-m-d H:i:s') . " --- \n";
$db = db_connect();

// 1. Ambil semua klien yang aktif
$stmt = $db->query("SELECT id, company_name, subscription_key FROM companies");
$companies = $stmt->fetchAll();

if (empty($companies)) {
    echo "Tidak ada klien ditemukan. Selesai.\n";
    exit;
}

$today = date('Y-m-d');
$total_success = 0;
$total_failed = 0;

foreach ($companies as $company) {
    echo "Memproses klien: {$company['company_name']} (ID: {$company['id']})... \n";
    
    // 2. Buat URL API untuk klien ini
    $api_url = TELKOMSEL_API_URL . '?subscriptionKey=' . urlencode($company['subscription_key']);
    
    // 3. Panggil API (fungsi ini ada di functions.php)
    $response = call_telkomsel_api($api_url);
    
    // 4. Cek apakah panggilan API sukses
    if (isset($response['status']) && $response['status'] === true && isset($response['data'])) {
        $data = $response['data'];
        $usage_mb = $data['usage'];
        $balance_mb = $data['balance'];
        
        // 5. Simpan hasil snapshot ke database
        try {
            // "ON DUPLICATE KEY UPDATE" mencegah error jika cron berjalan 2x sehari
            $sql = "INSERT INTO daily_usage_snapshots (company_id, snapshot_date, usage_mb, balance_mb)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    usage_mb = VALUES(usage_mb), 
                    balance_mb = VALUES(balance_mb)";
                    
            $insert_stmt = $db->prepare($sql);
            $insert_stmt->execute([$company['id'], $today, $usage_mb, $balance_mb]);
            echo "-> SUKSES: Usage = {$usage_mb} MB, Balance = {$balance_mb} MB \n";
            $total_success++;

        } catch (PDOException $e) {
            echo "-> ERROR DB: " . $e->getMessage() . "\n";
            $total_failed++;
        }
    } else {
        // Panggilan API gagal
        echo "-> ERROR API: Gagal mengambil data. Pesan: " . ($response['message'] ?? 'Unknown API error') . "\n";
        $total_failed++;
    }
}

echo "--- Proses Selesai --- \n";
echo "Total Sukses: $total_success\n";
echo "Total Gagal: $total_failed\n";
?>