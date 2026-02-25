<?php
// ajax/get_datapool_cards.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Ambil filter dari request AJAX
$company_id = $_GET['company_id'] ?? 0;
$project_id = $_GET['project_id'] ?? 0;

try {
    $db = db_connect();

    // 1. Ambil daftar project yang akan dikueri, berdasarkan filter
    $sql = "SELECT id, project_name, subscription_key FROM projects";
    $params = [];

    if (!empty($project_id)) {
        $sql .= " WHERE id = ?";
        $params[] = $project_id;
    } elseif (!empty($company_id)) {
        $sql .= " WHERE company_id = ?";
        $params[] = $company_id;
    }

    $stmt_projects = $db->prepare($sql);
    $stmt_projects->execute($params);
    $projects_to_query = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    if (empty($projects_to_query)) {
        header('Content-Type: application/json');
        echo json_encode([]); 
        exit;
    }

    // Siapkan statement untuk menghitung total injeksi sukses
    $stmt_inject = $db->prepare("
        SELECT COUNT(*) 
        FROM inject_history 
        WHERE project_id = ? AND status = 'SUCCESS'
    ");

    // (BARU) Siapkan statement untuk mengambil TANGGAL injeksi terakhir
    $stmt_last_date = $db->prepare("
        SELECT MAX(created_at) 
        FROM inject_history 
        WHERE project_id = ? AND status = 'SUCCESS'
    ");

    // 2. Loop setiap project dan panggil API
    $all_project_data = [];

    foreach ($projects_to_query as $project) {
        
        // Buat URL API Saldo
        $balance_url = TELKOMSEL_API_URL . '?' . http_build_query([
            'subscriptionKey' => $project['subscription_key']
        ]);
        
        // Panggil fungsi dari functions.php
        $balanceData = call_telkomsel_api($balance_url); 
        
        // Siapkan data untuk dikirim ke frontend
        $project_detail = [
            'project_id' => $project['id'],
            'project_name' => $project['project_name'],
            'subscription_key' => $project['subscription_key']
        ];
        
        // Cek jika panggilan API sukses
        if (isset($balanceData['status']) && $balanceData['status'] == true && isset($balanceData['data'])) {
            $project_detail['error'] = false;
            $project_detail['api_data'] = $balanceData['data']; 
        } else {
            // Panggilan API gagal
            $project_detail['error'] = true;
            $project_detail['message'] = $balanceData['message'] ?? 'API call failed or returned invalid data.';
            $project_detail['api_response'] = $balanceData['response'] ?? json_encode($balanceData);
        }

        // Jalankan query DB tambahan (Count & Last Date)
        try {
            // 1. Hitung Total
            $stmt_inject->execute([$project['id']]);
            $total_success_inject = $stmt_inject->fetchColumn();
            $project_detail['total_success_inject'] = $total_success_inject ? (int)$total_success_inject : 0;

            // 2. (BARU) Ambil Tanggal Terakhir
            $stmt_last_date->execute([$project['id']]);
            $last_date_val = $stmt_last_date->fetchColumn();
            $project_detail['last_success_inject_date'] = $last_date_val ? $last_date_val : null;

        } catch (PDOException $e) {
            $project_detail['total_success_inject'] = 0; 
            $project_detail['last_success_inject_date'] = null;
        }
        
        $all_project_data[] = $project_detail;
        
        usleep(100000); // Jeda 0.1 detik
    }

    // 3. Kembalikan hasil sebagai JSON
    header('Content-Type: application/json');
    echo json_encode($all_project_data);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => 'Server Error: ' . $e->getMessage()]);
}

exit;
?>