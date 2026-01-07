<?php
// ajax/get_injection_events.php
// (MODIFIKASI) Mengambil data agregat harian untuk FullCalendar DENGAN filter

// ========= PERBAIKAN =========
// Menggunakan __DIR__ untuk membuat path absolut yang lebih andal
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
// =============================

header('Content-Type: application/json');

$db = db_connect(); // Fungsi ini diambil dari config.php
if (!$db) { 
    echo json_encode([]);
    exit; 
}

// Ambil parameter (FullCalendar mengirim start/end)
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');
$company_id = $_GET['company_id'] ?? 0;
$project_id = $_GET['project_id'] ?? 0;

// Bangun Kueri Dinamis
$params = [$start, $end];
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

// Kueri agregat
$sql = "
    SELECT
        DATE(created_at) as event_date,
        status,
        COUNT(*) as count,
        SUM(CASE WHEN quota_unit = 'MB' THEN quota_value / 1024 ELSE quota_value END) as total_gb
    FROM inject_history
    WHERE
        created_at >= ? AND created_at < ?
        AND status IN ('SUCCESS', 'FAILED')
        $sql_where
    GROUP BY event_date, status
";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    $aggregated_data = [];

    // Agregasi data per hari
    foreach ($results as $row) {
        $date = $row['event_date'];
        if (!isset($aggregated_data[$date])) {
            $aggregated_data[$date] = [
                'SUCCESS' => ['count' => 0, 'gb' => 0],
                'FAILED' => ['count' => 0, 'gb' => 0]
            ];
        }
        if ($row['status'] == 'SUCCESS') {
            $aggregated_data[$date]['SUCCESS']['count'] = (int)$row['count'];
            $aggregated_data[$date]['SUCCESS']['gb'] = (float)$row['total_gb'];
        } elseif ($row['status'] == 'FAILED') {
            $aggregated_data[$date]['FAILED']['count'] = (int)$row['count'];
        }
    }

    // Ubah jadi format event FullCalendar
    foreach ($aggregated_data as $date => $data) {
        if ($data['SUCCESS']['count'] > 0) {
            $events[] = [
                'title' => '✓ ' . $data['SUCCESS']['count'] . ' | ' . round($data['SUCCESS']['gb'], 1) . ' GB',
                'start' => $date,
                'className' => 'status-success fc-event-main'
            ];
        }
        if ($data['FAILED']['count'] > 0) {
            $events[] = [
                'title' => '✗ ' . $data['FAILED']['count'] . ' Gagal',
                'start' => $date,
                'className' => 'status-failed fc-event-main'
            ];
        }
    }
    
    echo json_encode($events);

} catch (PDOException $e) {
    echo json_encode([]);
}
?>