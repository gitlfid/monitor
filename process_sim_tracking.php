<?php
// =======================================================================
// FILE: process_sim_tracking.php
// DESC: Backend Processor (Full Logic: Strict Filter, Real Logs, Legacy)
// =======================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);
ob_start(); 

require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/sim_helper.php'; 

// Cek Koneksi DB
if (!$db) {
    if(isset($_POST['is_ajax'])) jsonResponse('error', 'Database Connection Failed');
    die("System Error: DB Connection Failed");
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// 1. AUTO REPAIR DATABASE STRUCTURE (Agar Tabel Logs Selalu Ada)
// =======================================================================
try {
    // Tabel Inventory Utama
    $sql_inv = "CREATE TABLE IF NOT EXISTS sim_inventory (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        po_provider_id INT(11) NOT NULL,
        msisdn VARCHAR(50) NOT NULL,
        iccid VARCHAR(50) NULL,
        imsi VARCHAR(50) NULL,
        sn VARCHAR(50) NULL,
        status ENUM('Available', 'Active', 'Terminated') DEFAULT 'Available',
        activation_date DATE NULL,
        termination_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (po_provider_id), INDEX (msisdn), INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    // Tabel Log Aktivasi (History)
    $sql_act = "CREATE TABLE IF NOT EXISTS sim_activations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        po_provider_id INT(11) NOT NULL,
        company_id INT(11) NULL,
        project_id INT(11) NULL,
        activation_date DATE NOT NULL,
        activation_batch VARCHAR(100) NULL,
        total_sim INT(11) DEFAULT 0,
        active_qty INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Tabel Log Terminasi (History)
    $sql_term = "CREATE TABLE IF NOT EXISTS sim_terminations (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        po_provider_id INT(11) NOT NULL,
        company_id INT(11) NULL,
        project_id INT(11) NULL,
        termination_date DATE NOT NULL,
        termination_batch VARCHAR(100) NULL,
        total_sim INT(11) DEFAULT 0,
        terminated_qty INT(11) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($db_type === 'pdo') {
        $db->exec($sql_inv); $db->exec($sql_act); $db->exec($sql_term);
    } else {
        mysqli_query($db, $sql_inv); mysqli_query($db, $sql_act); mysqli_query($db, $sql_term);
    }
} catch (Exception $e) {}

// =======================================================================
// 2. AJAX HANDLERS (MODERN FEATURES)
// =======================================================================

// --- A. GET PO DETAILS (Untuk Auto-fill Batch di Upload) ---
if ($action == 'get_po_details') {
    $id = $_POST['id'];
    try {
        $data = null;
        if ($db_type === 'pdo') {
            $stmt = $db->prepare("SELECT batch_name, sim_qty FROM sim_tracking_po WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $safe_id = $db->real_escape_string($id);
            $res = $db->query("SELECT batch_name, sim_qty FROM sim_tracking_po WHERE id = '$safe_id'");
            if($res) $data = $res->fetch_assoc();
        }
        
        if($data) {
            // Jika batch_name di DB kosong, buat default
            if(empty($data['batch_name'])) $data['batch_name'] = "BATCH-PO-".$id;
            jsonResponse('success', 'Found', $data);
        } else {
            jsonResponse('error', 'PO Data not found');
        }
    } catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// --- B. UPLOAD MASTER BULK ---
if ($action == 'upload_master_bulk') {
    try {
        $po_id = $_POST['po_provider_id'];
        
        if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] != 0) {
            jsonResponse('error', 'File tidak ditemukan atau error upload.');
        }

        $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
        if (!$rows || count($rows) < 2) jsonResponse('error', 'File kosong atau format salah.');

        $header = $rows[0];
        $idx_msisdn = findIdx($header, ['msisdn','nohp','number','phone','mobile']);
        $idx_iccid = findIdx($header, ['iccid']); 
        $idx_imsi = findIdx($header, ['imsi']); 
        $idx_sn = findIdx($header, ['sn','serial']);

        if ($idx_msisdn === false) jsonResponse('error', 'Header MSISDN tidak ditemukan.');

        if($db_type === 'pdo') $db->beginTransaction();
        
        $c = 0;
        $stmt = $db->prepare("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES (?, ?, ?, ?, ?, 'Available')");
        
        $previewData = []; 

        for ($i=1; $i<count($rows); $i++) {
            $r = $rows[$i];
            $msisdn = isset($r[$idx_msisdn]) ? trim($r[$idx_msisdn]) : '';
            if(empty($msisdn)) continue;
            
            $iccid = ($idx_iccid!==false && isset($r[$idx_iccid])) ? trim($r[$idx_iccid]) : NULL;
            $imsi = ($idx_imsi!==false && isset($r[$idx_imsi])) ? trim($r[$idx_imsi]) : NULL;
            $sn = ($idx_sn!==false && isset($r[$idx_sn])) ? trim($r[$idx_sn]) : NULL;

            if ($db_type === 'pdo') {
                $stmt->execute([$po_id, $msisdn, $iccid, $imsi, $sn]);
            } else {
                $ic = $iccid?"'$iccid'":"NULL"; $im = $imsi?"'$imsi'":"NULL"; $s = $sn?"'$sn'":"NULL";
                $db->query("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES ('$po_id', '$msisdn', $ic, $im, $s, 'Available')");
            }
            $c++;

            if ($c <= 5) {
                $previewData[] = ['msisdn'=>$msisdn, 'iccid'=>$iccid, 'imsi'=>$imsi, 'sn'=>$sn];
            }
        }
        
        if($db_type === 'pdo') $db->commit();
        jsonResponse('success', "Berhasil menyimpan $c data ke Inventory.", ['count'=>$c, 'preview'=>$previewData]);

    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// --- C. FETCH SIMS (LOGIC FIX: FILTER & NULL STATS) ---
if ($action == 'fetch_sims') {
    $po_id = $_POST['po_id']; 
    $search = trim($_POST['search_bulk'] ?? '');
    
    // Parameter baru untuk Logic Filter Context
    $target_action = $_POST['target_action'] ?? 'all'; // 'activate', 'terminate', atau 'all'

    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $limit = 500; 
    $offset = ($page - 1) * $limit;

    // Base Filter Query
    $where = " WHERE po_provider_id = ? ";
    $params = [$po_id];

    // --- LOGIC FILTER STRICT (SESUAI REQUEST) ---
    // 1. Jika mode 'activate', hanya tampilkan 'Available'
    if ($target_action === 'activate') {
        $where .= " AND status = 'Available' ";
    }
    // 2. Jika mode 'terminate', hanya tampilkan 'Active'
    elseif ($target_action === 'terminate') {
        $where .= " AND status = 'Active' ";
    }
    // Jika 'all', tampilkan semua (mode view biasa)

    // Filter Search (Global Search)
    if (!empty($search)) {
        if (strpos($search, "\n") !== false || strpos($search, ",") !== false) {
            // Bulk Search (Paste Multiple MSISDN)
            $nums = preg_split('/[\s,]+/', str_replace([',', ';'], "\n", $search));
            $nums = array_filter(array_map('trim', $nums)); $nums = array_unique($nums);
            if (!empty($nums)) {
                $placeholders = implode(',', array_fill(0, count($nums), '?'));
                $where .= " AND msisdn IN ($placeholders)";
                $params = array_merge($params, $nums);
            }
        } else {
            // Single Keyword Search
            $where .= " AND (msisdn LIKE ? OR iccid LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }

    try { 
        // 1. STATISTIK GLOBAL (Hitung Total, Active, Terminated untuk PO ini secara keseluruhan)
        // [FIXED] Menggunakan IFNULL agar tidak return NULL dan menyebabkan error NaN di frontend
        $stats = ['total'=>0, 'active'=>0, 'terminated'=>0];
        if ($db_type === 'pdo') {
            $stmtStats = $db->prepare("SELECT 
                COUNT(*) as total,
                IFNULL(SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END), 0) as `active`,
                IFNULL(SUM(CASE WHEN status='Terminated' THEN 1 ELSE 0 END), 0) as `terminated`
                FROM sim_inventory WHERE po_provider_id = ?");
            $stmtStats->execute([$po_id]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        }

        // 2. HITUNG TOTAL ROW (Sesuai Filter Status & Search untuk Pagination)
        $countSql = "SELECT COUNT(*) as total FROM sim_inventory $where";
        if ($db_type === 'pdo') {
            $stmtCount = $db->prepare($countSql);
            $stmtCount->execute($params);
            $totalRows = $stmtCount->fetchColumn();
        } else {
            $totalRows = 0; 
        }

        // 3. AMBIL DATA LIST (Limit & Offset)
        $sql = "SELECT id, msisdn, iccid, status, activation_date, termination_date 
                FROM sim_inventory $where 
                ORDER BY msisdn ASC 
                LIMIT $limit OFFSET $offset";
        
        if ($db_type === 'pdo') {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $data = [];
        }

        jsonResponse('success', 'OK', [
            'data' => $data, 
            'stats' => $stats, 
            'total_rows' => $totalRows,
            'page' => $page,
            'total_pages' => ceil($totalRows / $limit)
        ]); 
    } 
    catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// --- D. FETCH LOGS (REAL HISTORY) ---
if ($action == 'fetch_logs') {
    $po_id = $_POST['po_id'];
    try {
        $logs = [];
        // UNION ALL untuk menggabungkan history dari dua tabel
        // Sort by ID DESC agar transaksi terakhir muncul paling atas
        $query = "
            SELECT 'Activation' as type, activation_date as date, active_qty as qty, activation_batch as batch, created_at
            FROM sim_activations WHERE po_provider_id = ?
            UNION ALL
            SELECT 'Termination' as type, termination_date as date, terminated_qty as qty, termination_batch as batch, created_at
            FROM sim_terminations WHERE po_provider_id = ?
            ORDER BY created_at DESC
        ";

        if ($db_type === 'pdo') {
            $stmt = $db->prepare($query);
            $stmt->execute([$po_id, $po_id]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback mysqli manual merge
            $res1 = mysqli_query($db, "SELECT 'Activation' as type, activation_date as date, active_qty as qty, activation_batch as batch, created_at FROM sim_activations WHERE po_provider_id = '$po_id'");
            while($r = mysqli_fetch_assoc($res1)) $logs[] = $r;
            $res2 = mysqli_query($db, "SELECT 'Termination' as type, termination_date as date, terminated_qty as qty, termination_batch as batch, created_at FROM sim_terminations WHERE po_provider_id = '$po_id'");
            while($r = mysqli_fetch_assoc($res2)) $logs[] = $r;
            
            // Sort Manual
            usort($logs, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
        }

        jsonResponse('success', 'Logs fetched', ['data' => $logs]);
    } catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// --- E. PROCESS BULK ACTION (WITH LOGGING) ---
if ($action == 'process_bulk_sim_action') {
    try {
        $ids = $_POST['sim_ids'] ?? []; 
        $mode = $_POST['mode']; // activate / terminate
        $date = $_POST['date_field']; 
        $batch = $_POST['batch_name']; 
        $po = $_POST['po_provider_id'];
        
        if(empty($ids)) jsonResponse('error', 'No selection');

        $st = ($mode === 'activate') ? 'Active' : 'Terminated';
        $dc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
        if ($db_type === 'pdo') {
            $db->beginTransaction();
            
            // 1. Update Inventory Status (Hanya update record terpilih)
            $chunkSize = 1000; 
            $chunks = array_chunk($ids, $chunkSize);
            foreach ($chunks as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $sql = "UPDATE sim_inventory SET status = ?, $dc = ? WHERE id IN ($ph)";
                $params = array_merge([$st, $date], $chunk);
                $db->prepare($sql)->execute($params);
            }
            
            // 2. Insert into Log Table (Satu record log untuk bulk action ini)
            $tbl = ($mode === 'activate') ? 'sim_activations' : 'sim_terminations';
            $qty_col = ($mode === 'activate') ? 'active_qty' : 'terminated_qty';
            $date_col = ($mode === 'activate') ? 'activation_date' : 'termination_date';
            $batch_col = ($mode === 'activate') ? 'activation_batch' : 'termination_batch';
            
            $cnt = count($ids);
            
            // Get Company/Project Info for Log completeness
            $inf = $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po")->fetch();
            $c_id = $inf['company_id'] ?? 0;
            $p_id = $inf['project_id'] ?? 0;

            // Insert Log
            $logSql = "INSERT INTO $tbl (po_provider_id, company_id, project_id, $date_col, $batch_col, total_sim, $qty_col) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $db->prepare($logSql)->execute([$po, $c_id, $p_id, $date, $batch . " (Action)", $cnt, $cnt]);
            
            $db->commit();
            jsonResponse('success', "Successfully processed $cnt items.");
        }
    } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// =======================================================================
// 3. LEGACY HANDLERS (PO, LOGISTIC, COMPANY) - UNCHANGED
// =======================================================================

// A. PO CREATE/UPDATE
if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
    $id=$_POST['id']??null; $type=$_POST['type']; 
    $cId=!empty($_POST['company_id'])?$_POST['company_id']:null; $pId=!empty($_POST['project_id'])?$_POST['project_id']:null;
    $file=uploadFileLegacy($_FILES['po_file'], $type)??$_POST['existing_file']??null;
    
    // Support product_name & detail fields if available
    $product = $_POST['product_name'] ?? NULL;
    $detail = $_POST['detail'] ?? NULL;

    $sql=($_POST['action']=='update')
        ?"UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?"
        :"INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    
    $p=[$type, $cId, $pId, $_POST['manual_company_name']??null, $_POST['manual_project_name']??null, $_POST['batch_name']??null, $_POST['link_client_po_id']??null, $_POST['po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    
    if ($db_type === 'pdo') {
        if($_POST['action']=='update') $p[]=$id;
        $db->prepare($sql)->execute($p); 
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $p);
        if($_POST['action']=='update') mysqli_query($db, "UPDATE sim_tracking_po SET type=$v[0], company_id=$v[1], project_id=$v[2], manual_company_name=$v[3], manual_project_name=$v[4], batch_name=$v[5], link_client_po_id=$v[6], po_number=$v[7], po_date=$v[8], sim_qty=$v[9], po_file=$v[10] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_{$type}_po.php?msg=success"); exit;
}

// B. LOGISTICS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_POST['action'], 'logistic') !== false) {
    $id=$_POST['id']??null; $upd=($_POST['action']=='update_logistic');
    $sql=$upd?"UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?":"INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $p=[$_POST['type'], $_POST['po_id']?:0, $_POST['logistic_date'], $_POST['qty'], $_POST['pic_name'], $_POST['pic_phone'], $_POST['delivery_address'], $_POST['courier'], $_POST['awb'], $_POST['status'], $_POST['received_date'], $_POST['receiver_name'], $_POST['notes']];
    
    if ($db_type === 'pdo') {
        if($upd)$p[]=$id;
        $db->prepare($sql)->execute($p); 
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $p);
        if($upd) mysqli_query($db, "UPDATE sim_tracking_logistics SET type=$v[0], po_id=$v[1], logistic_date=$v[2], qty=$v[3], pic_name=$v[4], pic_phone=$v[5], delivery_address=$v[6], courier=$v[7], awb=$v[8], status=$v[9], received_date=$v[10], receiver_name=$v[11], notes=$v[12] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_receive.php?msg=success"); exit;
}

// C. DELETE HANDLER
if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    $t=($_GET['action']=='delete')?'sim_tracking_po':'sim_tracking_logistics';
    $r=($_GET['action']=='delete')?"sim_tracking_{$_GET['type']}_po.php":"sim_tracking_receive.php";
    $id = $_GET['id'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM $t WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM $t WHERE id='$id'");
    header("Location: $r?msg=deleted"); exit;
}

// D. PROVIDER FROM CLIENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_provider_from_client') {
    $cId = !empty($_POST['provider_company_id']) ? $_POST['provider_company_id'] : NULL;
    $file = uploadFileLegacy($_FILES['po_file'], 'provider');
    $p = ['provider', $cId, NULL, $_POST['manual_provider_name']??NULL, NULL, $_POST['batch_name'], $_POST['link_client_po_id'], $_POST['provider_po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    
    if ($db_type === 'pdo') $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($p);
    else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $p);
        mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_provider_po.php?msg=created_from_client"); exit;
}

// E. COMPANY
if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $name = $_POST['company_name']; $type = $_POST['company_type'];
    if($db_type === 'pdo') $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([$name, $type]);
    else mysqli_query($db, "INSERT INTO companies (company_name, company_type) VALUES ('$name', '$type')");
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>