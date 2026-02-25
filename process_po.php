<?php
require_once 'includes/auth_check.php';
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type']; // 'client' atau 'provider'
    $company_id = $_POST['company_id'];
    $project_id = $_POST['project_id'];
    $po_number = $_POST['po_number'];
    $sim_qty = $_POST['sim_qty'];
    $link_client_po = isset($_POST['link_client_po_id']) ? $_POST['link_client_po_id'] : null;

    // Handle File Upload
    $file_name = null;
    if (isset($_FILES['po_file']) && $_FILES['po_file']['error'] == 0) {
        $target_dir = "uploads/po/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_extension = pathinfo($_FILES["po_file"]["name"], PATHINFO_EXTENSION);
        $file_name = $type . "_" . time() . "." . $file_extension;
        move_uploaded_file($_FILES["po_file"]["tmp_name"], $target_dir . $file_name);
    }

    try {
        $sql = "INSERT INTO sim_tracking_po (type, company_id, project_id, po_number, sim_qty, po_file, link_client_po_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $company_id, $project_id, $po_number, $sim_qty, $file_name, $link_client_po]);
        
        $redirect = ($type == 'client') ? 'sim_tracking_client_po.php' : 'sim_tracking_provider_po.php';
        header("Location: $redirect?msg=success");
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}