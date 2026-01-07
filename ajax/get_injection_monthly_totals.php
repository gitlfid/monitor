<?php
// ajax/get_injection_monthly_totals.php
// (MODIFIKASI) Menghitung Total Bulanan DAN Total Keseluruhan (All Time)

// Menggunakan __DIR__ untuk path absolut yang aman
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$db = db_connect();
if (!$db) {
    echo json_encode(['status' => false, 'message' => 'DB Error']);
    exit;
}

// Ambil parameter
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$company_id = $_GET['company_id'] ?? 0;
$project_id = $_GET['project_id'] ?? 0;

// 1. SIAPKAN FILTER DINAMIS (Company & Project)
// Filter ini akan dipakai oleh kedua query (Bulanan & All Time)
$filter_sql = "";
$filter_params = [];

if (!empty($company_id)) {
    $filter_sql .= " AND company_id = ?";
    $filter_params[] = $company_id;
}
if (!empty($project_id)) {
    $filter_sql .= " AND project_id = ?";
    $filter_params[] = $project_id;
}

// Struktur Data Response Awal
$response_data = [
    'status' => true,
    'totals' => [ // Data Bulanan
        'total_success' => 0,
        'total_failed' => 0,
        'total_gb_success' => 0
    ],
    'grand_totals' => [ // Data All Time (BARU)
        'total_success' => 0,
        'total_failed' => 0,
        'total_gb_success' => 0
    ]
];

try {
    // ==================================================
    // A. QUERY 1: DATA BULANAN (Monthly Stats)
    // ==================================================
    // Kita gabungkan parameter Tanggal ($year, $month) dengan parameter Filter ($filter_params)
    $monthly_params = array_merge([$year, $month], $filter_params);

    $sql_monthly = "
        SELECT
            status,
            COUNT(*) as count,
            SUM(CASE WHEN quota_unit = 'MB' THEN quota_value / 1024 ELSE quota_value END) as total_gb
        FROM inject_history
        WHERE
            YEAR(created_at) = ? AND MONTH(created_at) = ?
            AND status IN ('SUCCESS', 'FAILED')
            $filter_sql
        GROUP BY status
    ";

    $stmt = $db->prepare($sql_monthly);
    $stmt->execute($monthly_params);
    $monthly_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($monthly_rows as $row) {
        if ($row['status'] == 'SUCCESS') {
            $response_data['totals']['total_success'] = (int)$row['count'];
            $response_data['totals']['total_gb_success'] = round((float)$row['total_gb'], 2);
        } elseif ($row['status'] == 'FAILED') {
            $response_data['totals']['total_failed'] = (int)$row['count'];
        }
    }

    // ==================================================
    // B. QUERY 2: DATA KESELURUHAN (All Time / Grand Total)
    // ==================================================
    // Query ini TIDAK menggunakan filter Tahun/Bulan, hanya filter Company/Project
    
    $sql_grand = "
        SELECT
            status,
            COUNT(*) as count,
            SUM(CASE WHEN quota_unit = 'MB' THEN quota_value / 1024 ELSE quota_value END) as total_gb
        FROM inject_history
        WHERE
            status IN ('SUCCESS', 'FAILED')
            $filter_sql
        GROUP BY status
    ";

    // Gunakan $filter_params saja (tanpa year/month)
    $stmt_grand = $db->prepare($sql_grand);
    $stmt_grand->execute($filter_params); 
    $grand_rows = $stmt_grand->fetchAll(PDO::FETCH_ASSOC);

    foreach ($grand_rows as $row) {
        if ($row['status'] == 'SUCCESS') {
            $response_data['grand_totals']['total_success'] = (int)$row['count'];
            $response_data['grand_totals']['total_gb_success'] = round((float)$row['total_gb'], 2);
        } elseif ($row['status'] == 'FAILED') {
            $response_data['grand_totals']['total_failed'] = (int)$row['count'];
        }
    }

    // Kirim Response JSON Lengkap
    echo json_encode($response_data);

} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>