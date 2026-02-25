<?php
// ajax/get_injection_details.php
// (MODIFIKASI) Mengambil log harian dan total harian DENGAN filter

// ========= PERBAIKAN =========
// Menggunakan __DIR__ untuk membuat path absolut yang lebih andal
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
// =============================

header('Content-Type: application/json');

$db = db_connect(); // Fungsi ini diambil dari config.php
if (!$db) {
    echo json_encode(['status' => false, 'message' => 'DB Error']);
    exit;
}

// Ambil parameter
$date = $_GET['date'] ?? null;
$company_id = $_GET['company_id'] ?? 0;
$project_id = $_GET['project_id'] ?? 0;

if (!$date) {
    echo json_encode(['status' => false, 'message' => 'Tanggal tidak valid.']);
    exit;
}

// Bangun Kueri Dinamis
$params = [$date];
$sql_where_parts = [];

if (!empty($company_id)) {
    $sql_where_parts[] = "company_id = ?";
    $params[] = $company_id;
}
if (!empty($project_id)) {
    $sql_where_parts[] = "project_id = ?";
    $params[] = $project_id;
}

$sql_where = "";
if (!empty($sql_where_parts)) {
    $sql_where = " AND " . implode(" AND ", $sql_where_parts);
}

$response = [
    'status' => false,
    'totals' => [
        'total_success' => 0,
        'total_failed' => 0,
        'total_gb_success' => 0
    ],
    'logs' => []
];

try {
    // 1. Ambil Total Harian (dengan filter)
    $sql_totals = "
        SELECT
            status,
            COUNT(*) as count,
            SUM(CASE WHEN quota_unit = 'MB' THEN quota_value / 1024 ELSE quota_value END) as total_gb
        FROM inject_history
        WHERE
            DATE(created_at) = ?
            AND status IN ('SUCCESS', 'FAILED')
            $sql_where
        GROUP BY status
    ";
    
    $stmt_totals = $db->prepare($sql_totals);
    $stmt_totals->execute($params);
    $daily_stats = $stmt_totals->fetchAll(PDO::FETCH_ASSOC);

    foreach ($daily_stats as $stat) {
        if ($stat['status'] == 'SUCCESS') {
            $response['totals']['total_success'] = (int)$stat['count'];
            $response['totals']['total_gb_success'] = round((float)$stat['total_gb'], 2);
        } elseif ($stat['status'] == 'FAILED') {
            $response['totals']['total_failed'] = (int)$stat['count'];
        }
    }

    // 2. Ambil Log Harian (dengan filter)
    $sql_logs = "
        SELECT msisdn_target, denom_name, quota_value, quota_unit, status
        FROM inject_history
        WHERE
            DATE(created_at) = ?
            $sql_where
        ORDER BY created_at ASC 
    ";
    
    $stmt_logs = $db->prepare($sql_logs);
    $stmt_logs->execute($params);
    $response['logs'] = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
    
    $response['status'] = true;
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>