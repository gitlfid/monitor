<?php
// ajax/get_projects_by_company.php
// File ini mengambil daftar project berdasarkan company_id

// ========= PERBAIKAN =========
// Menggunakan __DIR__ untuk membuat path absolut yang lebih andal
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
// =============================

header('Content-Type: application/json');

$db = db_connect(); // Fungsi ini diambil dari config.php
$company_id = $_GET['company_id'] ?? 0;

if (!$db || !$company_id) {
    echo json_encode(['status' => false, 'projects' => []]);
    exit;
}

try {
    // Ini mengambil data dari database 'projects'
    $stmt = $db->prepare("
        SELECT id, project_name 
        FROM projects 
        WHERE company_id = ? 
        ORDER BY project_name ASC
    ");
    $stmt->execute([$company_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => true, 'projects' => $projects]);

} catch (PDOException $e) {
    // Kirim pesan error agar bisa di-debug jika kueri gagal
    echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>