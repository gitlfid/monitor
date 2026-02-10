<?php
// =======================================================================
// FILE: process_sim_tracking.php
// DESC: Backend Processor (Logs Fixed & Total=Available Logic)
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
// 1. AUTO REPAIR DATABASE STRUCTURE (CRITICAL FOR LOGS)
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
    
    // Tabel Log Aktivasi
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

    // Tabel Log Terminasi
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

    // --- FIX MISSING COLUMNS (AUTO ADD IF NOT EXISTS) ---
    function ensureColumn($db, $table, $col, $def, $type) {
        try {
            $exists = false;
            if ($type === 'pdo') {
                $rs = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($rs && $rs->rowCount() > 0) $exists = true;
            } else {
                $rs = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($rs && mysqli_num_rows($rs) > 0) $exists = true;
            }
            if (!$exists) {
                $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $def";
                if ($type === 'pdo') $db->exec($sql); else mysqli_query($db, $sql);
            }
        } catch (Exception $e) {}
    }

    ensureColumn($db, 'sim_activations', 'po_provider_id', "INT(11) NOT NULL DEFAULT 0", $db_type);
    ensureColumn($db, 'sim_activations', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $db_type);
    ensureColumn($db, 'sim_terminations', 'po_provider_id', "INT(11) NOT NULL DEFAULT 0", $db_type);
    ensureColumn($db, 'sim_terminations', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $db_type);

} catch (Exception $e) {}

// =======================================================================
// 2. AJAX HANDLERS
// =======================================================================

// --- A. GET PO DETAILS ---
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
            jsonResponse('error', 'File error.');
        }

        $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
        if (!$rows || count($rows) < 2) jsonResponse('error', 'File kosong/format salah.');

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

            if ($c <= 5) $previewData[] = ['msisdn'=>$msisdn, 'iccid'=>$iccid, 'imsi'=>$imsi, 'sn'=>$sn];
        }
        
        if($db_type === 'pdo') $db->commit();
        jsonResponse('success', "Berhasil menyimpan $c data.", ['count'=>$c, 'preview'=>$previewData]);

    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// --- C. FETCH SIMS (LOGIC FIX: TOTAL = AVAILABLE) ---
if ($action == 'fetch_sims') {
    $po_id = $_POST['po_id']; 
    $search = trim($_POST['search_bulk'] ?? '');
    $target_action = $_POST['target_action'] ?? 'all'; 
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $limit = 500; 
    $offset = ($page - 1) * $limit;

    $where = " WHERE po_provider_id = ? ";
    $params = [$po_id];

    // Filter Strict
    if ($target_action === 'activate') {
        $where .= " AND status = 'Available' ";
    } elseif ($target_action === 'terminate') {
        $where .= " AND status = 'Active' ";
    } elseif ($target_action === 'view_terminated') {
        $where .= " AND status = 'Terminated' ";
    }

    if (!empty($search)) {
        if (strpos($search, "\n") !== false || strpos($search, ",") !== false) {
            $nums = preg_split('/[\s,]+/', str_replace([',', ';'], "\n", $search));
            $nums = array_filter(array_map('trim', $nums)); $nums = array_unique($nums);
            if (!empty($nums)) {
                $placeholders = implode(',', array_fill(0, count($nums), '?'));
                $where .= " AND msisdn IN ($placeholders)";
                $params = array_merge($params, $nums);
            }
        } else {
            $where .= " AND (msisdn LIKE ? OR iccid LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
    }

    try { 
        // 1. STATISTIK GLOBAL (UPDATED: TOTAL = AVAILABLE)
        // 'total' sekarang menghitung jumlah status 'Available' saja
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

        // 2. HITUNG ROW (Pagination)
        $countSql = "SELECT COUNT(*) as total FROM sim_inventory $where";
        if ($db_type === 'pdo') {
            $stmtCount = $db->prepare($countSql);
            $stmtCount->execute($params);
            $totalRows = $stmtCount->fetchColumn();
        } else {
            $totalRows = 0; 
        }

        // 3. AMBIL DATA
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

// --- D. FETCH LOGS (FIX BLANK ISSUE) ---
if ($action == 'fetch_logs') {
    $po_id = $_POST['po_id'];
    try {
        $logs = [];
        // UNION ALL & Robust Query
        // Memastikan kolom yang dipilih ada dan menggunakan alias yang konsisten
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
            $res1 = mysqli_query($db, "SELECT 'Activation' as type, activation_date as date, active_qty as qty, activation_batch as batch, created_at FROM sim_activations WHERE po_provider_id = '$po_id'");
            while($r = mysqli_fetch_assoc($res1)) $logs[] = $r;
            $res2 = mysqli_query($db, "SELECT 'Termination' as type, termination_date as date, terminated_qty as qty, termination_batch as batch, created_at FROM sim_terminations WHERE po_provider_id = '$po_id'");
            while($r = mysqli_fetch_assoc($res2)) $logs[] = $r;
            usort($logs, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
        }

        jsonResponse('success', 'Logs fetched', ['data' => $logs]);
    } catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// --- E. PROCESS BULK ACTION ---
if ($action == 'process_bulk_sim_action') {
    try {
        $ids = $_POST['sim_ids'] ?? []; 
        $mode = $_POST['mode']; 
        $date = $_POST['date_field']; 
        $batch = $_POST['batch_name']; 
        $po = $_POST['po_provider_id'];
        
        if(empty($ids)) jsonResponse('error', 'No selection');

        $st = ($mode === 'activate') ? 'Active' : 'Terminated';
        $dc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
        if ($db_type === 'pdo') {
            $db->beginTransaction();
            
            // 1. Update Status
            $chunkSize = 1000; 
            $chunks = array_chunk($ids, $chunkSize);
            foreach ($chunks as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $sql = "UPDATE sim_inventory SET status = ?, $dc = ? WHERE id IN ($ph)";
                $params = array_merge([$st, $date], $chunk);
                $db->prepare($sql)->execute($params);
            }
            
            // 2. Insert Log
            $tbl = ($mode === 'activate') ? 'sim_activations' : 'sim_terminations';
            $qty_col = ($mode === 'activate') ? 'active_qty' : 'terminated_qty';
            $date_col = ($mode === 'activate') ? 'activation_date' : 'termination_date';
            $batch_col = ($mode === 'activate') ? 'activation_batch' : 'termination_batch';
            
            $cnt = count($ids);
            
            $inf = $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po")->fetch();
            $c_id = $inf['company_id'] ?? 0;
            $p_id = $inf['project_id'] ?? 0;

            $logSql = "INSERT INTO $tbl (po_provider_id, company_id, project_id, $date_col, $batch_col, total_sim, $qty_col) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $db->prepare($logSql)->execute([$po, $c_id, $p_id, $date, $batch . " (Action)", $cnt, $cnt]);
            
            $db->commit();
            jsonResponse('success', "Processed $cnt items.");
        }
    } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// 3. LEGACY HANDLERS (UNCHANGED)
if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
    $id=$_POST['id']??null; $type=$_POST['type']; 
    $cId=!empty($_POST['company_id'])?$_POST['company_id']:null; $pId=!empty($_POST['project_id'])?$_POST['project_id']:null;
    $file=uploadFileLegacy($_FILES['po_file'], $type)??$_POST['existing_file']??null;
    
    $sql=($_POST['action']=='update')?"UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?":"INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $p=[$type, $cId, $pId, $_POST['manual_company_name']??null, $_POST['manual_project_name']??null, $_POST['batch_name']??null, $_POST['link_client_po_id']??null, $_POST['po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    
    if ($db_type === 'pdo') { if($_POST['action']=='update') $p[]=$id; $db->prepare($sql)->execute($p); }
    header("Location: sim_tracking_{$type}_po.php?msg=success"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_POST['action'], 'logistic') !== false) {
    $id=$_POST['id']??null; $upd=($_POST['action']=='update_logistic');
    $sql=$upd?"UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?":"INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $p=[$_POST['type'], $_POST['po_id']?:0, $_POST['logistic_date'], $_POST['qty'], $_POST['pic_name'], $_POST['pic_phone'], $_POST['delivery_address'], $_POST['courier'], $_POST['awb'], $_POST['status'], $_POST['received_date'], $_POST['receiver_name'], $_POST['notes']];
    if ($db_type === 'pdo') { if($upd)$p[]=$id; $db->prepare($sql)->execute($p); }
    header("Location: sim_tracking_receive.php?msg=success"); exit;
}

if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    $t=($_GET['action']=='delete')?'sim_tracking_po':'sim_tracking_logistics';
    $r=($_GET['action']=='delete')?"sim_tracking_{$_GET['type']}_po.php":"sim_tracking_receive.php";
    $id = $_GET['id'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM $t WHERE id=?")->execute([$id]);
    header("Location: $r?msg=deleted"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_provider_from_client') {
    $cId = !empty($_POST['provider_company_id']) ? $_POST['provider_company_id'] : NULL;
    $file = uploadFileLegacy($_FILES['po_file'], 'provider');
    $p = ['provider', $cId, NULL, $_POST['manual_provider_name']??NULL, NULL, $_POST['batch_name'], $_POST['link_client_po_id'], $_POST['provider_po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    if ($db_type === 'pdo') $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($p);
    header("Location: sim_tracking_provider_po.php?msg=created_from_client"); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $name = $_POST['company_name']; $type = $_POST['company_type'];
    if($db_type === 'pdo') $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([$name, $type]);
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>