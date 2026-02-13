<?php
// =======================================================================
// FILE: process_sim_tracking.php
// DESC: Backend Full - New History Logic, Safe Output & Legacy Support
// =======================================================================

// 1. CLEAN OUTPUT BUFFER (Wajib untuk JSON Stabil)
// Menangkap semua output liar (warning/notice) agar tidak merusak JSON
ob_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/sim_helper.php';

// --- FUNGSI RESPONSE AMAN ---
function sendSafeJson($status, $msg, $data = []) {
    if (ob_get_length()) ob_clean(); // Bersihkan sampah output sebelum kirim
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $msg], $data));
    exit;
}

// Cek Koneksi
if (!$db) {
    if(isset($_POST['is_ajax'])) sendSafeJson('error', 'Database Connection Failed');
    die("System Error: DB Connection Failed");
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// 1. AUTO REPAIR DATABASE (Pastikan Tabel History Siap)
// =======================================================================
try {
    // Tabel Log Aktivasi
    $sql_act = "CREATE TABLE IF NOT EXISTS sim_activations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        po_provider_id INT(11) NOT NULL DEFAULT 0,
        company_id INT(11) NULL,
        project_id INT(11) NULL,
        activation_date DATE NOT NULL,
        activation_batch VARCHAR(100) NULL,
        total_sim INT(11) DEFAULT 0,
        active_qty INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (po_provider_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Tabel Log Terminasi
    $sql_term = "CREATE TABLE IF NOT EXISTS sim_terminations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        po_provider_id INT(11) NOT NULL DEFAULT 0,
        company_id INT(11) NULL,
        project_id INT(11) NULL,
        termination_date DATE NOT NULL,
        termination_batch VARCHAR(100) NULL,
        total_sim INT(11) DEFAULT 0,
        terminated_qty INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (po_provider_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($db_type === 'pdo') {
        $db->exec($sql_act); 
        $db->exec($sql_term);
    } else {
        mysqli_query($db, $sql_act); 
        mysqli_query($db, $sql_term);
    }
} catch (Exception $e) { /* Silent fail */ }


// =======================================================================
// 2. AJAX HANDLERS BARU (HISTORY FEATURE)
// =======================================================================

// --- A. GET HISTORY SUMMARY (HANYA TANGGAL & TOTAL QTY) ---
if ($action == 'fetch_history_summary') {
    $po_id = $_POST['po_id'] ?? 0;
    $history = [];

    try {
        // Ambil Ringkasan Aktivasi
        $sqlAct = "SELECT 'Activation' as type, activation_date as date, active_qty as qty, activation_batch as batch 
                   FROM sim_activations WHERE po_provider_id = ?";
        
        // Ambil Ringkasan Terminasi
        $sqlTerm = "SELECT 'Termination' as type, termination_date as date, terminated_qty as qty, termination_batch as batch 
                    FROM sim_terminations WHERE po_provider_id = ?";

        if ($db_type === 'pdo') {
            // Execute Act
            $stmt = $db->prepare($sqlAct); $stmt->execute([$po_id]);
            $actData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Execute Term
            $stmt = $db->prepare($sqlTerm); $stmt->execute([$po_id]);
            $termData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $safe_id = mysqli_real_escape_string($db, $po_id);
            $actRes = mysqli_query($db, "SELECT 'Activation' as type, activation_date as date, active_qty as qty, activation_batch as batch FROM sim_activations WHERE po_provider_id = '$safe_id'");
            $actData = []; if($actRes) while($r = mysqli_fetch_assoc($actRes)) $actData[] = $r;

            $termRes = mysqli_query($db, "SELECT 'Termination' as type, termination_date as date, terminated_qty as qty, termination_batch as batch FROM sim_terminations WHERE po_provider_id = '$safe_id'");
            $termData = []; if($termRes) while($r = mysqli_fetch_assoc($termRes)) $termData[] = $r;
        }

        $history = array_merge($actData, $termData);

        // Sort by Date Descending (Terbaru diatas)
        usort($history, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

    } catch (Exception $e) { /* Silent fail */ }

    sendSafeJson('success', 'History loaded', ['data' => $history]);
}

// --- B. GET HISTORY DETAILS (DAFTAR MSISDN UNTUK POPUP DETAIL) ---
// Handler ini dipanggil saat user mengklik baris di tabel history
if ($action == 'fetch_history_details') {
    $po_id = $_POST['po_id'];
    $date  = $_POST['date'];
    $type  = $_POST['type']; // 'Activation' or 'Termination'

    $list = [];
    try {
        // Tentukan kolom filter berdasarkan tipe aksi
        // Kita cari data di tabel Inventory yang memiliki tanggal & status sesuai
        $dateCol = ($type === 'Activation') ? 'activation_date' : 'termination_date';
        $statusCol = ($type === 'Activation') ? 'Active' : 'Terminated';

        // Query ke Inventory langsung agar data akurat (Real-time)
        $sql = "SELECT msisdn, iccid, status, activation_date, termination_date 
                FROM sim_inventory 
                WHERE po_provider_id = ? AND $dateCol = ? AND status = ?";
        
        if ($db_type === 'pdo') {
            $stmt = $db->prepare($sql);
            $stmt->execute([$po_id, $date, $statusCol]);
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $s_po = mysqli_real_escape_string($db, $po_id);
            $s_date = mysqli_real_escape_string($db, $date);
            $s_stat = mysqli_real_escape_string($db, $statusCol);
            
            $res = mysqli_query($db, "SELECT msisdn, iccid, status, activation_date, termination_date FROM sim_inventory WHERE po_provider_id = '$s_po' AND $dateCol = '$s_date' AND status = '$s_stat'");
            if($res) while($r = mysqli_fetch_assoc($res)) $list[] = $r;
        }
    } catch (Exception $e) { sendSafeJson('error', $e->getMessage()); }

    sendSafeJson('success', 'Details loaded', ['data' => $list]);
}

// --- C. PROCESS BULK ACTION (EKSEKUSI & CATAT LOG) ---
if ($action == 'process_bulk_sim_action') {
    try {
        $ids = $_POST['sim_ids'] ?? []; 
        $mode = $_POST['mode']; // 'activate' atau 'terminate'
        $date = $_POST['date_field']; 
        $batch = $_POST['batch_name']; 
        $po = $_POST['po_provider_id'];
        
        if(empty($ids)) sendSafeJson('error', 'No selection');

        // Mapping Status
        $targetStatus = ($mode === 'activate') ? 'Active' : 'Terminated';
        $dateColInv   = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
        $logTable     = ($mode === 'activate') ? 'sim_activations' : 'sim_terminations';
        $logDateCol   = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        $logBatchCol  = ($mode === 'activate') ? 'activation_batch' : 'termination_batch';
        $logQtyCol    = ($mode === 'activate') ? 'active_qty' : 'terminated_qty';

        if ($db_type === 'pdo') {
            $db->beginTransaction();
            
            // 1. Update Inventory (Ubah Status & Tanggal)
            $chunkSize = 1000; 
            $chunks = array_chunk($ids, $chunkSize);
            foreach ($chunks as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $sql = "UPDATE sim_inventory SET status = ?, $dateColInv = ? WHERE id IN ($ph)";
                $params = array_merge([$targetStatus, $date], $chunk);
                $db->prepare($sql)->execute($params);
            }
            
            // 2. Catat ke Tabel Log (Untuk History)
            $cnt = count($ids);
            
            $inf = $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po")->fetch();
            $c_id = $inf['company_id'] ?? NULL;
            $p_id = $inf['project_id'] ?? NULL;

            // Pastikan Batch Name ada isinya
            $finalBatch = $batch ? $batch : "Manual Action (" . date('H:i') . ")";

            $logSql = "INSERT INTO $logTable 
                       (po_provider_id, company_id, project_id, $logDateCol, $logBatchCol, total_sim, $logQtyCol) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $db->prepare($logSql)->execute([$po, $c_id, $p_id, $date, $finalBatch, $cnt, $cnt]);
            
            $db->commit();
            sendSafeJson('success', "Success! Processed $cnt items.");
        }
    } catch (Exception $e) { 
        if($db_type === 'pdo' && $db->inTransaction()) $db->rollBack(); 
        sendSafeJson('error', $e->getMessage()); 
    }
}

// --- D. FETCH SIMS (TABEL UTAMA DASHBOARD) ---
if ($action == 'fetch_sims') {
    $po_id = $_POST['po_id']; 
    $search = trim($_POST['search_bulk'] ?? '');
    $target_action = $_POST['target_action'] ?? 'all'; 
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $limit = 500; 
    $offset = ($page - 1) * $limit;

    $where = " WHERE po_provider_id = ? ";
    $params = [$po_id];

    if ($target_action === 'activate') $where .= " AND status = 'Available' ";
    elseif ($target_action === 'terminate') $where .= " AND status = 'Active' ";
    elseif ($target_action === 'view_terminated') $where .= " AND status = 'Terminated' ";

    if (!empty($search)) {
        if (strpos($search, "\n") !== false || strpos($search, ",") !== false) {
            $nums = preg_split('/[\s,]+/', str_replace([',', ';'], "\n", $search));
            $nums = array_unique(array_filter(array_map('trim', $nums)));
            if (!empty($nums)) {
                $placeholders = implode(',', array_fill(0, count($nums), '?'));
                $where .= " AND msisdn IN ($placeholders)";
                $params = array_merge($params, $nums);
            }
        } else {
            $where .= " AND (msisdn LIKE ? OR iccid LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
    }

    try { 
        // Statistik (Total = Available)
        $stats = ['total'=>0, 'active'=>0, 'terminated'=>0];
        if ($db_type === 'pdo') {
            $stmtStats = $db->prepare("SELECT 
                IFNULL(SUM(CASE WHEN status='Available' THEN 1 ELSE 0 END), 0) as `total`, 
                IFNULL(SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END), 0) as `active`,
                IFNULL(SUM(CASE WHEN status='Terminated' THEN 1 ELSE 0 END), 0) as `terminated`
                FROM sim_inventory WHERE po_provider_id = ?");
            $stmtStats->execute([$po_id]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        }

        // Pagination Count
        $totalRows = 0;
        if ($db_type === 'pdo') {
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM sim_inventory $where");
            $stmtCount->execute($params);
            $totalRows = $stmtCount->fetchColumn();
        }

        // Data Rows
        $data = [];
        if ($db_type === 'pdo') {
            $stmt = $db->prepare("SELECT id, msisdn, iccid, status, activation_date, termination_date FROM sim_inventory $where ORDER BY msisdn ASC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        sendSafeJson('success', 'OK', [
            'data' => $data, 
            'stats' => $stats, 
            'total_rows' => $totalRows, 
            'page' => $page, 
            'total_pages' => ceil($totalRows / ($limit>0?$limit:1))
        ]); 
    } catch (Exception $e) { sendSafeJson('error', $e->getMessage()); }
}

// --- E. UPLOAD & GET PO (UNTUK MODAL UPLOAD) ---
if ($action == 'get_po_details') {
    $id = $_POST['id'];
    try {
        if ($db_type === 'pdo') {
            $stmt = $db->prepare("SELECT batch_name, sim_qty FROM sim_tracking_po WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $safe_id = mysqli_real_escape_string($db, $id);
            $res = mysqli_query($db, "SELECT batch_name, sim_qty FROM sim_tracking_po WHERE id = '$safe_id'");
            $data = $res ? mysqli_fetch_assoc($res) : null;
        }
        if($data) {
            if(empty($data['batch_name'])) $data['batch_name'] = "BATCH-PO-".$id;
            sendSafeJson('success', 'Found', $data);
        } else sendSafeJson('error', 'Not Found');
    } catch(Exception $e){ sendSafeJson('error', $e->getMessage()); }
}

if ($action == 'upload_master_bulk') {
    try {
        $po_id = $_POST['po_provider_id'];
        if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] != 0) sendSafeJson('error', 'File Error');
        $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
        if (!$rows || count($rows) < 2) sendSafeJson('error', 'File Kosong');
        
        $header = $rows[0];
        $idx_msisdn = findIdx($header, ['msisdn','nohp','number']);
        $idx_iccid = findIdx($header, ['iccid']);
        if ($idx_msisdn === false) sendSafeJson('error', 'Header MSISDN Missing');

        if($db_type === 'pdo') $db->beginTransaction();
        $c = 0; 
        $stmt = $db->prepare("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, status) VALUES (?, ?, ?, 'Available')");
        for ($i=1; $i<count($rows); $i++) {
            $r = $rows[$i];
            $msisdn = trim($r[$idx_msisdn] ?? '');
            $iccid = ($idx_iccid!==false) ? trim($r[$idx_iccid] ?? '') : NULL;
            if($msisdn) { $stmt->execute([$po_id, $msisdn, $iccid]); $c++; }
        }
        if($db_type === 'pdo') $db->commit();
        sendSafeJson('success', "Disimpan $c data.", ['count'=>$c]);
    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); sendSafeJson('error', $e->getMessage()); }
}

// =======================================================================
// 3. LEGACY HANDLERS (TIDAK DIHAPUS - UNTUK FITUR LAIN)
// =======================================================================

// Create / Update PO
if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
    if (ob_get_length()) ob_end_flush();
    $id = $_POST['id'] ?? null; $type = $_POST['type']; 
    $cId = !empty($_POST['company_id']) ? $_POST['company_id'] : null; 
    $pId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $file = uploadFileLegacy($_FILES['po_file'], $type) ?? $_POST['existing_file'] ?? null;
    
    $sql = ($_POST['action'] == 'update') 
        ? "UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?"
        : "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    
    $p = [$type, $cId, $pId, $_POST['manual_company_name']??null, $_POST['manual_project_name']??null, $_POST['batch_name']??null, $_POST['link_client_po_id']??null, $_POST['po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    
    if ($db_type === 'pdo') { 
        if($_POST['action']=='update') $p[]=$id; 
        $db->prepare($sql)->execute($p); 
    }
    header("Location: sim_tracking_{$type}_po.php?msg=success"); exit;
}

// Logistic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_POST['action'] ?? '', 'logistic') !== false) {
    if (ob_get_length()) ob_end_flush();
    $id = $_POST['id'] ?? null; $upd = ($_POST['action'] == 'update_logistic');
    $sql = $upd ? "UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?" : "INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $p = [$_POST['type'], $_POST['po_id']?:0, $_POST['logistic_date'], $_POST['qty'], $_POST['pic_name'], $_POST['pic_phone'], $_POST['delivery_address'], $_POST['courier'], $_POST['awb'], $_POST['status'], $_POST['received_date'], $_POST['receiver_name'], $_POST['notes']];
    if ($db_type === 'pdo') { if($upd) $p[]=$id; $db->prepare($sql)->execute($p); }
    header("Location: sim_tracking_receive.php?msg=success"); exit;
}

// Provider from Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_provider_from_client') {
    if (ob_get_length()) ob_end_flush();
    $cId = !empty($_POST['provider_company_id']) ? $_POST['provider_company_id'] : NULL;
    $file = uploadFileLegacy($_FILES['po_file'], 'provider');
    $p = ['provider', $cId, NULL, $_POST['manual_provider_name']??NULL, NULL, $_POST['batch_name'], $_POST['link_client_po_id'], $_POST['provider_po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    if ($db_type === 'pdo') { $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($p); }
    header("Location: sim_tracking_provider_po.php?msg=created_from_client"); exit;
}

// Delete
if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    if (ob_get_length()) ob_end_flush();
    $t = ($_GET['action'] == 'delete') ? 'sim_tracking_po' : 'sim_tracking_logistics';
    $r = ($_GET['action'] == 'delete') ? "sim_tracking_{$_GET['type']}_po.php" : "sim_tracking_receive.php";
    $id = $_GET['id'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM $t WHERE id=?")->execute([$id]);
    header("Location: $r?msg=deleted"); exit;
}

// Company
if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    if (ob_get_length()) ob_end_flush();
    $name = $_POST['company_name']; $type = $_POST['company_type'];
    if($db_type === 'pdo') $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([$name, $type]);
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>