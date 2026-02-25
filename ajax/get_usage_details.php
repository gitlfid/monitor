<?php
// (1) Load Konfigurasi
// Path-nya '../includes/' (Naik 1 level, masuk ke includes)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// (2) Cek Autentikasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => false, 'error' => 'Akses ditolak']);
    exit;
}

// (3) Ambil parameter
$date = $_GET['date'] ?? null;
$client_id = $_GET['client_id'] ?? 'all'; // Ambil filter

if (empty($date)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'error' => 'Parameter tanggal hilang']);
    exit;
}

// === PERUBAHAN: Inisialisasi struktur response baru ===
$response = [
    'status' => false, 
    'logs' => [], 
    'chart' => [
        'labels' => [],
        'data' => []
    ]
];

try {
    // (4) Query ke DB (Sama seperti sebelumnya)
    $db = db_connect();
    
    $sql = "
        WITH DailyTotalPerClient AS (
            SELECT
                snapshot_date,
                company_id,
                SUM(usage_mb) AS total_cumulative_usage_mb
            FROM daily_usage_snapshots
            GROUP BY snapshot_date, company_id
        ),
        DailyUsagePerClient AS (
            SELECT
                snapshot_date,
                company_id,
                total_cumulative_usage_mb,
                LAG(total_cumulative_usage_mb, 1, 0) OVER (PARTITION BY company_id ORDER BY snapshot_date ASC) AS previous_day_usage_mb
            FROM DailyTotalPerClient
        )
        SELECT
            T1.snapshot_date,
            C.company_name,
            (T1.total_cumulative_usage_mb - T1.previous_day_usage_mb) AS daily_incremental_usage_mb,
            T1.total_cumulative_usage_mb
        FROM DailyUsagePerClient AS T1
        JOIN companies AS C ON T1.company_id = C.id
        WHERE T1.snapshot_date = :snapshot_date
    ";

    $params = [':snapshot_date' => $date];

    // (5) Terapkan Filter Klien jika ada
    if ($client_id !== 'all') {
        $sql .= " AND T1.company_id = :client_id";
        $params[':client_id'] = $client_id;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    $response['status'] = true;
    
    // === PERUBAHAN START: Membagi data untuk Tabel dan Chart ===
    $chart_labels = [];
    $chart_data = [];

    // (6) Format data (Konversi ke GB)
    foreach ($results as $row) {
        $daily_usage_gb = round($row['daily_incremental_usage_mb'] / 1024, 2);
        
        // Hanya proses jika ada penggunaan
        if ($daily_usage_gb > 0) {
             // (A) Data untuk Tabel
             $response['logs'][] = [
                'company_name' => $row['company_name'],
                'daily_usage_gb' => $daily_usage_gb,
                'cumulative_gb' => round($row['total_cumulative_usage_mb'] / 1024, 2)
            ];
            
            // (B) Data untuk Pie Chart
            $chart_labels[] = $row['company_name'];
            $chart_data[] = $daily_usage_gb;
        }
    }
    
    // (C) Masukkan data chart ke response
    $response['chart'] = [
        'labels' => $chart_labels,
        'data'   => $chart_data
    ];
    // === PERUBAHAN END ===

} catch (PDOException $e) {
    http_response_code(500);
    $response['error'] = 'Database error: ' . $e->getMessage();
}

// (7) Kirim sebagai JSON
header('Content-Type: application/json');
echo json_encode($response);
?>