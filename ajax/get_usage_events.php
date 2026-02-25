<?php
// (1) Load Konfigurasi
// PERUBAHAN: Path-nya '../includes/' (Naik 1 level, masuk ke includes)
require_once __DIR__ . '/../includes/config.php'; 
require_once __DIR__ . '/../includes/functions.php'; 

// (2) Cek Autentikasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

// (3) Ambil parameter
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');
$client_id = $_GET['client_id'] ?? 'all'; // Ambil filter

try {
    // (4) Query ke DB
    $db = db_connect();
    
    // SQL Awal (SAMA KAYAK summary.php)
    $sql_base = "
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
        ),
        IncrementalUsage AS (
            SELECT
                snapshot_date,
                company_id,
                (total_cumulative_usage_mb - previous_day_usage_mb) AS daily_incremental_usage_mb
            FROM DailyUsagePerClient
            WHERE (total_cumulative_usage_mb - previous_day_usage_mb) > 0
        )
        -- (5) Query Final: Jumlahkan total penggunaan harian
        SELECT
            snapshot_date,
            SUM(daily_incremental_usage_mb) AS total_daily_usage_mb
        FROM IncrementalUsage
        WHERE snapshot_date BETWEEN :start_date AND :end_date
    ";

    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];

    // (6) Terapkan Filter Klien jika ada
    if ($client_id !== 'all') {
        $sql_base .= " AND company_id = :client_id";
        $params[':client_id'] = $client_id;
    }

    $sql_base .= " GROUP BY snapshot_date";
    
    $stmt = $db->prepare($sql_base);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // (7) Format data untuk FullCalendar
    $calendar_events = [];
    foreach ($results as $row) {
        $usage_gb = round($row['total_daily_usage_mb'] / 1024, 2);
        
        $calendar_events[] = [
            'title' => 'Usage: ' . $usage_gb . ' GB',
            'start' => $row['snapshot_date'],
            'className' => 'event-usage'
        ];
    }
    
    // (8) Kirim sebagai JSON
    header('Content-Type: application/json');
    echo json_encode($calendar_events);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
