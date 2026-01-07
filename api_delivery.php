<?php
/*
 File: api_delivery.php
 Deskripsi: Backend Final Stabil (Clean Buffer Output)
*/
ob_start(); // Tangkap semua output liar

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;

// Matikan error display agar tidak merusak JSON
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

// --- KONFIGURASI API ---
define('KLIK_RESI_API_KEY', '485762cb-0ade-41d3-afad-6da124ff90cb'); 
define('KLIK_RESI_BASE_URL', 'https://klikresi.com/api/trackings');

$action = $_POST['action'] ?? '';
$db = db_connect();

// Fungsi Kirim JSON yang Bersih
function sendJson($data) {
    ob_end_clean(); // Buang semua teks/error PHP sebelumnya
    echo json_encode($data);
    exit;
}

// Helper
function getOrCreateId($db, $table, $name) {
    $name = trim($name); if (empty($name)) return null;
    $stmt = $db->prepare("SELECT id FROM $table WHERE name = ?");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row['id'];
    $stmt = $db->prepare("INSERT INTO $table (name) VALUES (?)");
    $stmt->execute([$name]);
    return $db->lastInsertId();
}

try {
    // --- 1. TRACKING API (FIXED) ---
    if ($action == 'track_shipment') {
        $resi = trim($_POST['resi'] ?? '');
        $cour = strtolower(trim($_POST['courier'] ?? ''));

        if (empty($resi)) sendJson(['status'=>'error', 'message'=>'Nomor Resi Kosong']);
        
        // Mapping Kurir
        $map = ['jne'=>'jne', 'j&t'=>'jnt', 'jnt'=>'jnt', 'sicepat'=>'sicepat', 'anteraja'=>'anteraja', 'pos'=>'pos', 'tiki'=>'tiki', 'spx'=>'spx', 'lion'=>'lion', 'ninja'=>'ninja', 'idexpress'=>'ide'];
        $apiC = 'jne'; // Default
        foreach($map as $k=>$v) { if(strpos($cour, $k) !== false) { $apiC=$v; break; } }

        $ch = curl_init(KLIK_RESI_BASE_URL."/$resi/couriers/$apiC");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Timeout 20 detik
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Hindari masalah SSL di localhost
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-api-key: '.KLIK_RESI_API_KEY]);
        
        $res = curl_exec($ch);
        if(curl_errno($ch)) sendJson(['status'=>'error', 'message'=>'Koneksi API Gagal: '.curl_error($ch)]);
        curl_close($ch);
        
        $d = json_decode($res, true);
        if (isset($d['data'])) sendJson(['status'=>'success', 'data'=>$d['data']]);
        else sendJson(['status'=>'error', 'message'=>$d['message'] ?? 'Resi tidak ditemukan']);
    }

    // --- 2. MASTER DATA ---
    elseif (in_array($action, ['get_master', 'add_master', 'edit_master', 'delete_master'])) {
        $type = $_POST['type'] ?? '';
        $tableMap = ['item'=>'master_items','data'=>'master_data_types','product'=>'master_product_details','shipping'=>'master_shippings','company'=>'master_delivery_companies'];
        if (!isset($tableMap[$type])) throw new Exception("Invalid type");
        $table = $tableMap[$type];

        if ($action == 'get_master') {
            $stmt = $db->query("SELECT * FROM $table ORDER BY name ASC");
            sendJson(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } elseif ($action == 'add_master') {
            $db->prepare("INSERT INTO $table (name) VALUES (?)")->execute([trim($_POST['name'])]);
            sendJson(['status' => 'success']);
        } elseif ($action == 'edit_master') {
            $db->prepare("UPDATE $table SET name = ? WHERE id = ?")->execute([trim($_POST['name']), $_POST['id']]);
            sendJson(['status' => 'success']);
        } elseif ($action == 'delete_master') {
            $db->prepare("DELETE FROM $table WHERE id = ?")->execute([$_POST['id']]);
            sendJson(['status' => 'success']);
        }
    }

    // --- 3. CRUD TRANSACTION ---
    elseif ($action == 'save_transaction') {
        $date = $_POST['date'] ?? null; $qty = $_POST['quantity'] ?? 0; $comp = $_POST['company'] ?? null;
        if (!$date || !$comp || $qty <= 0) throw new Exception("Data Wajib: Date, Company, Qty");
        
        $stmt = $db->prepare("INSERT INTO delivery_transactions (delivery_date, item_id, data_id, product_detail_id, quantity, company_id, shipping_id, tracking_number) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$date, $_POST['item']??null, $_POST['data']??null, $_POST['product']??null, $qty, $comp, $_POST['shipping']??null, $_POST['tracking_number']??null]);
        sendJson(['status' => 'success', 'message' => 'Saved!']);
    }

    elseif ($action == 'get_transaction') {
        $stmt = $db->prepare("SELECT * FROM delivery_transactions WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        if($d) sendJson(['status'=>'success', 'data'=>$d]); else throw new Exception("Not found");
    }

    elseif ($action == 'update_transaction') {
        $id = $_POST['id'];
        $sql = "UPDATE delivery_transactions SET delivery_date=?, item_id=?, data_id=?, product_detail_id=?, quantity=?, company_id=?, shipping_id=?, tracking_number=? WHERE id=?";
        $db->prepare($sql)->execute([
            $_POST['date'], $_POST['item']??null, $_POST['data']??null, $_POST['product']??null, 
            $_POST['quantity'], $_POST['company'], $_POST['shipping']??null, $_POST['tracking_number']??null, $id
        ]);
        sendJson(['status' => 'success', 'message' => 'Updated!']);
    }

    elseif ($action == 'delete_transaction') {
        $db->prepare("DELETE FROM delivery_transactions WHERE id = ?")->execute([$_POST['id']]);
        sendJson(['status' => 'success']);
    }

    // --- 4. SEARCH ---
    elseif ($action == 'search_transactions') {
        $key = $_POST['keyword'] ?? '';
        $sql = "SELECT t.*, m1.name as item_name, m2.name as data_name, m3.name as product_name, m4.name as shipping_name, m5.name as company_name 
                FROM delivery_transactions t
                LEFT JOIN master_items m1 ON t.item_id = m1.id
                LEFT JOIN master_data_types m2 ON t.data_id = m2.id
                LEFT JOIN master_product_details m3 ON t.product_detail_id = m3.id
                LEFT JOIN master_shippings m4 ON t.shipping_id = m4.id
                LEFT JOIN master_delivery_companies m5 ON t.company_id = m5.id
                WHERE 1=1 ";
        $p = [];
        if (!empty($key)) {
            $sql .= " AND (m5.name LIKE ? OR t.tracking_number LIKE ? OR m4.name LIKE ?)";
            $p = ["%$key%", "%$key%", "%$key%"];
        }
        $sql .= " ORDER BY t.delivery_date DESC, t.id DESC LIMIT 200";
        $stmt = $db->prepare($sql); $stmt->execute($p);
        sendJson(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // --- 5. IMPORT EXCEL ---
    elseif ($action == 'import_excel') {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) throw new Exception("File error");
        $tmp = 'uploads/temp/del_'.uniqid().'.xlsx'; 
        if (!is_dir('uploads/temp')) mkdir('uploads/temp', 0777, true);
        move_uploaded_file($_FILES['excel_file']['tmp_name'], $tmp);
        
        $reader = IOFactory::createReaderForFile($tmp); $reader->setReadDataOnly(true); 
        $rows = $reader->load($tmp)->getActiveSheet()->toArray();
        $c = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $dt = !empty($r[0]) ? date('Y-m-d', strtotime($r[0])) : date('Y-m-d');
            $qty = (int)($r[4] ?? 0);
            if (empty($r[5]) && $qty == 0) continue;

            $ids = [
                getOrCreateId($db, 'master_items', $r[1]??''),
                getOrCreateId($db, 'master_data_types', $r[2]??''),
                getOrCreateId($db, 'master_product_details', $r[3]??''),
                getOrCreateId($db, 'master_delivery_companies', $r[5]??''),
                getOrCreateId($db, 'master_shippings', $r[6]??'')
            ];
            
            $db->prepare("INSERT INTO delivery_transactions (delivery_date, item_id, data_id, product_detail_id, quantity, company_id, shipping_id, tracking_number) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$dt, $ids[0], $ids[1], $ids[2], $qty, $ids[3], $ids[4], $r[7]??null]);
            $c++;
        }
        @unlink($tmp);
        sendJson(['status' => 'success', 'message' => "$c Data Imported!"]);
    }

} catch (Exception $e) { sendJson(['status' => 'error', 'message' => $e->getMessage()]); }
?>