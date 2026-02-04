<?php
// =======================================================================
// 1. INISIALISASI & KONEKSI DATABASE
// =======================================================================
// Matikan display error agar output JSON bersih, log error tetap jalan
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';

// Koneksi Universal (Support PDO & MySQLi)
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }

// Fallback Koneksi jika variabel global tidak ditemukan
if (!$db && defined('DB_HOST')) { 
    try { 
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); 
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $db_type = 'pdo'; 
    } catch (Exception $e) { 
        $db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME); 
        if ($db) $db_type = 'mysqli'; 
    } 
}

// Jika koneksi gagal total
if (!$db) {
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status'=>'error', 'message'=>'Database Connection Failed']);
        exit;
    } else {
        die("System Error: Database Connection Failed.");
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// 2. AUTO-REPAIR DATABASE (EKSEKUSI DI AWAL)
// =======================================================================

// A. Buat Tabel Inventory (Core Fitur Baru)
$sql_create_inv = "CREATE TABLE IF NOT EXISTS sim_inventory (
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
    INDEX (po_provider_id),
    INDEX (msisdn),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    if ($db_type === 'pdo') $db->exec($sql_create_inv); else mysqli_query($db, $sql_create_inv);
} catch (Exception $e) { /* Ignore if exists */ }

// B. Perbaiki Tabel Lama (Legacy Support)
function checkAndFix($db, $db_type, $table, $col, $def) {
    try {
        $exists = false;
        if ($db_type === 'pdo') { 
            $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'"); 
            if ($stmt && $stmt->rowCount() > 0) $exists = true; 
        } else { 
            $res = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '$col'"); 
            if ($res && mysqli_num_rows($res) > 0) $exists = true; 
        }
        if (!$exists) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $def";
            if ($db_type === 'pdo') $db->exec($sql); else mysqli_query($db, $sql);
        }
    } catch (Exception $e) {}
}

$cols = ['msisdn', 'iccid', 'imsi', 'sn'];
foreach ($cols as $c) {
    checkAndFix($db, $db_type, 'sim_activations', $c, "VARCHAR(50) NULL");
    checkAndFix($db, $db_type, 'sim_terminations', $c, "VARCHAR(50) NULL");
}
checkAndFix($db, $db_type, 'sim_terminations', 'po_provider_id', "INT(11) NULL");

// =======================================================================
// 3. HELPER FUNCTIONS (READER & UPLOAD)
// =======================================================================

// Helper: Smart Excel/CSV Reader
function readSpreadsheet($filePath, $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $data = [];

    // CSV Handler
    if ($ext === 'csv') {
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // Remove BOM
            $bom = "\xEF\xBB\xBF"; $firstLine = fgets($handle);
            if (strncmp($firstLine, $bom, 3) === 0) $firstLine = substr($firstLine, 3);
            if(!empty(trim($firstLine))) $data[] = str_getcsv(trim($firstLine));
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Filter baris kosong
                if(array_filter($row)) $data[] = $row; 
            }
            fclose($handle);
        }
        return $data;
    }

    // XLSX Handler (Native ZipArchive)
    if ($ext === 'xlsx') {
        $zip = new ZipArchive;
        if ($zip->open($filePath) === TRUE) {
            $sharedStrings = [];
            if ($zip->locateName('xl/sharedStrings.xml')) {
                $xml = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml'));
                foreach ($xml->si as $si) $sharedStrings[] = (string)$si->t;
            }
            if ($zip->locateName('xl/worksheets/sheet1.xml')) {
                $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
                foreach ($xml->sheetData->row as $row) {
                    $r = [];
                    foreach ($row->c as $c) {
                        $v = (string)$c->v;
                        if (isset($c['t']) && (string)$c['t'] === 's') $v = $sharedStrings[intval($v)] ?? $v;
                        $r[] = $v;
                    }
                    if(!empty($r)) $data[] = $r;
                }
            }
            $zip->close();
        }
        return $data;
    }
    return false;
}

// Helper: Cari Index Kolom (Case Insensitive & Flexible)
function findIdx($headers, $keys) {
    foreach ($headers as $i => $v) { 
        $clean = strtolower(trim(str_replace([' ','_','-','.'],'',$v)));
        if (in_array($clean, $keys)) return $i; 
    }
    return false;
}

// Helper: Upload File (Legacy support for PO)
function uploadFile($fileArray, $prefix) {
    if (isset($fileArray) && $fileArray['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . "/uploads/po/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);
        $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
        $newName = $prefix . "_" . time() . "_" . rand(100,999) . "." . $ext;
        if(move_uploaded_file($fileArray['tmp_name'], $target_dir . $newName)) return $newName;
    }
    return null;
}

// =======================================================================
// 4. API HANDLERS (AJAX REQUESTS - NEW FEATURES)
// =======================================================================

// --- API: FETCH SIMS (BULK SEARCH) ---
if ($action == 'fetch_sims') {
    header('Content-Type: application/json');
    $po_id = $_POST['po_id']; 
    $mode = $_POST['mode']; 
    $search_bulk = trim($_POST['search_bulk'] ?? ''); 

    $target_status = ($mode === 'activate') ? 'Available' : 'Active';
    
    // Base Query
    $query = "SELECT id, msisdn, iccid, status FROM sim_inventory WHERE po_provider_id = ? AND status = ?";
    $params = [$po_id, $target_status];

    // Jika ada input search
    if (!empty($search_bulk)) {
        // Normalisasi input: ganti koma, titik koma, spasi dengan newline
        $search_bulk = str_replace([',', ';', ' '], "\n", $search_bulk);
        $numbers = array_filter(array_map('trim', explode("\n", $search_bulk)));
        $numbers = array_unique($numbers); // Hapus duplikat

        if (!empty($numbers)) {
            $placeholders = implode(',', array_fill(0, count($numbers), '?'));
            $query .= " AND msisdn IN ($placeholders)";
            $params = array_merge($params, $numbers);
        }
    }
    
    $query .= " ORDER BY msisdn ASC LIMIT 500"; 

    try {
        if ($db_type === 'pdo') {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Mysqli Fallback (Simple)
            $res = mysqli_query($db, "SELECT id, msisdn, iccid, status FROM sim_inventory WHERE po_provider_id='$po_id' AND status='$target_status' LIMIT 500");
            $data = []; while($r=mysqli_fetch_assoc($res)) $data[]=$r;
        }
        echo json_encode(['status'=>'success', 'data'=>$data]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
    exit;
}

// --- API: PROCESS BULK ACTION (SWITCH STATUS) ---
if ($action == 'process_bulk_sim_action') {
    header('Content-Type: application/json');
    try {
        $sim_ids = $_POST['sim_ids'] ?? []; 
        $mode = $_POST['mode']; 
        $date = $_POST['date_field'];
        $batch_name = $_POST['batch_name']; 
        $po_id = $_POST['po_provider_id']; 

        if (empty($sim_ids)) { echo json_encode(['status'=>'error', 'message'=>'No SIMs selected']); exit; }

        $new_status = ($mode === 'activate') ? 'Active' : 'Terminated';
        $date_col = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
        if ($db_type === 'pdo') {
            $db->beginTransaction();
            
            // 1. UPDATE INVENTORY
            $placeholders = implode(',', array_fill(0, count($sim_ids), '?'));
            $sql_update = "UPDATE sim_inventory SET status = ?, $date_col = ? WHERE id IN ($placeholders)";
            $params_update = array_merge([$new_status, $date], $sim_ids);
            
            $stmt = $db->prepare($sql_update);
            $stmt->execute($params_update);
            $count = $stmt->rowCount();

            // 2. INSERT SUMMARY LOG (Agar Dashboard Chart tetap jalan)
            $table_log = ($mode === 'activate') ? 'sim_activations' : 'sim_terminations';
            $col_qty = ($mode === 'activate') ? 'active_qty' : 'terminated_qty';
            $col_date = ($mode === 'activate') ? 'activation_date' : 'termination_date';
            $col_batch = ($mode === 'activate') ? 'activation_batch' : 'termination_batch';

            // Ambil Info PO
            $stmtInfo = $db->prepare("SELECT company_id, project_id FROM sim_tracking_po WHERE id = ?");
            $stmtInfo->execute([$po_id]);
            $poInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if ($count > 0) {
                $sql_log = "INSERT INTO $table_log (po_provider_id, company_id, project_id, $col_date, $col_batch, total_sim, $col_qty) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmtLog = $db->prepare($sql_log);
                $stmtLog->execute([
                    $po_id, 
                    $poInfo['company_id'] ?? 0, 
                    $poInfo['project_id'] ?? 0, 
                    $date, 
                    $batch_name . " (Bulk Action)", 
                    $count, 
                    $count
                ]);
            }

            $db->commit();
            echo json_encode(['status'=>'success', 'count'=>$count, 'message'=>"$count SIMs successfully " . ($mode=='activate'?'Activated':'Terminated')]);
        } else {
             echo json_encode(['status'=>'error', 'message'=>'PDO Driver Required for Bulk Action']);
        }
    } catch (Exception $e) {
        if($db_type==='pdo') $db->rollBack();
        echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
    }
    exit;
}

// =======================================================================
// 5. STANDARD HANDLERS (FORM SUBMIT)
// =======================================================================

// --- UPLOAD MASTER DATA (INJECT KE INVENTORY) ---
if ($action == 'upload_master_bulk') {
    try {
        $po_id = $_POST['po_provider_id'];
        
        if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
            $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
            if (!$rows || count($rows) < 2) die("<script>alert('File Kosong/Format Salah. Pastikan ada header MSISDN.');window.history.back();</script>");

            $header = $rows[0];
            $idx_msisdn = findIdx($header, ['msisdn','nohp','number','phone','mobile']);
            $idx_iccid = findIdx($header, ['iccid']); 
            $idx_imsi = findIdx($header, ['imsi']); 
            $idx_sn = findIdx($header, ['sn','serial','serialnumber']);

            if ($idx_msisdn === false) die("<script>alert('Error: Kolom MSISDN tidak ditemukan.');window.history.back();</script>");

            if($db_type === 'pdo') $db->beginTransaction();
            
            $c = 0;
            $stmt = $db->prepare("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES (?, ?, ?, ?, ?, 'Available')");
            
            // Start from index 1 (skip header)
            for ($i=1; $i<count($rows); $i++) {
                $r = $rows[$i];
                $msisdn = isset($r[$idx_msisdn]) ? trim($r[$idx_msisdn]) : '';
                if(empty($msisdn)) continue;
                
                $iccid = ($idx_iccid!==false && isset($r[$idx_iccid])) ? trim($r[$idx_iccid]) : NULL;
                $imsi = ($idx_imsi!==false && isset($r[$idx_imsi])) ? trim($r[$idx_imsi]) : NULL;
                $sn = ($idx_sn!==false && isset($r[$idx_sn])) ? trim($r[$idx_sn]) : NULL;

                $stmt->execute([$po_id, $msisdn, $iccid, $imsi, $sn]);
                $c++;
            }
            if($db_type === 'pdo') $db->commit();
            
            header("Location: sim_tracking_status.php?msg=uploaded_master&count=$c"); exit;
        }
    } catch (Exception $e) { if($db_type==='pdo') $db->rollBack(); die("Error Upload: ".$e->getMessage()); }
}

// =======================================================================
// [LEGACY] 6. FITUR LAMA (PO, LOGISTIC, COMPANY) - TETAP ADA
// =======================================================================

// A. PO Management
if (isset($_POST['action']) && ($_POST['action'] === 'create' || $_POST['action'] === 'update')) {
    $is_upd = ($_POST['action'] === 'update');
    $id = $_POST['id'] ?? null;
    $type = $_POST['type'];
    // Validasi Integer agar tidak error Strict Mode
    $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : NULL;
    $m_comp = (empty($company_id) && !empty($_POST['manual_company_name'])) ? $_POST['manual_company_name'] : NULL;
    $m_proj = (empty($project_id) && !empty($_POST['manual_project_name'])) ? $_POST['manual_project_name'] : NULL;
    $po_num = $_POST['po_number'];
    $po_date = !empty($_POST['po_date']) ? $_POST['po_date'] : date('Y-m-d');
    $sim_qty = str_replace(',', '', $_POST['sim_qty']); 
    $batch = !empty($_POST['batch_name']) ? $_POST['batch_name'] : NULL;
    $link = !empty($_POST['link_client_po_id']) ? $_POST['link_client_po_id'] : NULL;
    $file = $_POST['existing_file'] ?? NULL;
    $new_file = uploadFile($_FILES['po_file'], $type);
    if ($new_file) $file = $new_file;

    $params = [$type, $company_id, $project_id, $m_comp, $m_proj, $batch, $link, $po_num, $po_date, $sim_qty, $file];

    if ($db_type === 'pdo') {
        if ($is_upd) {
            $params[] = $id;
            $db->prepare("UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?")->execute($params);
        } else {
            $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
        }
    } else {
        // MySQLi Fallback
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        if($is_upd) mysqli_query($db, "UPDATE sim_tracking_po SET type=$v[0], company_id=$v[1], project_id=$v[2], manual_company_name=$v[3], manual_project_name=$v[4], batch_name=$v[5], link_client_po_id=$v[6], po_number=$v[7], po_date=$v[8], sim_qty=$v[9], po_file=$v[10] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_{$type}_po.php?msg=" . ($is_upd ? 'updated' : 'success')); exit;
}

// B. Provider from Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_provider_from_client') {
    $type = 'provider';
    $link_client_po_id = $_POST['link_client_po_id']; 
    $company_id = !empty($_POST['provider_company_id']) ? $_POST['provider_company_id'] : NULL;
    $m_comp = (empty($company_id) && !empty($_POST['manual_provider_name'])) ? $_POST['manual_provider_name'] : NULL;
    $po_num = $_POST['provider_po_number'];
    $sim_qty = str_replace(',', '', $_POST['sim_qty']);
    $po_date = !empty($_POST['po_date']) ? $_POST['po_date'] : date('Y-m-d');
    $batch = !empty($_POST['batch_name']) ? $_POST['batch_name'] : NULL;
    $file = NULL;
    $new_file = uploadFile($_FILES['po_file'], 'provider');
    if ($new_file) $file = $new_file;

    $params = [$type, $company_id, NULL, $m_comp, NULL, $batch, $link_client_po_id, $po_num, $po_date, $sim_qty, $file];

    if ($db_type === 'pdo') {
        $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_provider_po.php?msg=created_from_client"); exit;
}

// C. Delete PO
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id']; $type = $_GET['type'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM sim_tracking_po WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM sim_tracking_po WHERE id='$id'");
    header("Location: sim_tracking_{$type}_po.php?msg=deleted"); exit;
}

// D. Logistics
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_logistic' || $_POST['action'] === 'update_logistic')) {
    $is_upd = ($_POST['action'] === 'update_logistic');
    $id = $_POST['id'] ?? null;
    $params = [$_POST['type'], !empty($_POST['po_id'])?$_POST['po_id']:0, !empty($_POST['logistic_date'])?$_POST['logistic_date']:date('Y-m-d'), !empty($_POST['qty'])?$_POST['qty']:0, !empty($_POST['pic_name'])?$_POST['pic_name']:NULL, !empty($_POST['pic_phone'])?$_POST['pic_phone']:NULL, !empty($_POST['delivery_address'])?$_POST['delivery_address']:NULL, !empty($_POST['courier'])?$_POST['courier']:NULL, !empty($_POST['awb'])?$_POST['awb']:NULL, !empty($_POST['status'])?$_POST['status']:'On Process', !empty($_POST['received_date'])?$_POST['received_date']:NULL, !empty($_POST['receiver_name'])?$_POST['receiver_name']:NULL, !empty($_POST['notes'])?$_POST['notes']:NULL];

    if ($db_type === 'pdo') {
        if($is_upd) { $params[]=$id; $db->prepare("UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?")->execute($params); }
        else { $db->prepare("INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($params); }
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        if($is_upd) mysqli_query($db, "UPDATE sim_tracking_logistics SET type=$v[0], po_id=$v[1], logistic_date=$v[2], qty=$v[3], pic_name=$v[4], pic_phone=$v[5], delivery_address=$v[6], courier=$v[7], awb=$v[8], status=$v[9], received_date=$v[10], receiver_name=$v[11], notes=$v[12] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_receive.php?msg=" . ($is_upd ? 'updated' : 'success')); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_logistic') {
    $id = $_GET['id'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM sim_tracking_logistics WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM sim_tracking_logistics WHERE id='$id'");
    header("Location: sim_tracking_receive.php?msg=deleted"); exit;
}

// E. Legacy Activation/Termination (Untuk backward compatibility)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_activation' || $_POST['action'] === 'update_activation')) {
    $is_upd = ($_POST['action'] === 'update_activation');
    $id = $_POST['id'] ?? null;
    $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : NULL;
    $params = [$company_id, $project_id, $_POST['po_batch_sim']??NULL, !empty($_POST['po_provider_id'])?$_POST['po_provider_id']:NULL, !empty($_POST['po_client_id'])?$_POST['po_client_id']:NULL, $_POST['total_sim']??0, $_POST['active_qty']??0, $_POST['inactive_qty']??0, $_POST['activation_date']??NULL, $_POST['activation_qty']??0, $_POST['activation_batch']??NULL];

    if ($db_type === 'pdo') {
        if ($is_upd) { $params[] = $id; $db->prepare("UPDATE sim_activations SET company_id=?, project_id=?, po_batch_sim=?, po_provider_id=?, po_client_id=?, total_sim=?, active_qty=?, inactive_qty=?, activation_date=?, activation_qty=?, activation_batch=? WHERE id=?")->execute($params); }
        else { $db->prepare("INSERT INTO sim_activations (company_id, project_id, po_batch_sim, po_provider_id, po_client_id, total_sim, active_qty, inactive_qty, activation_date, activation_qty, activation_batch) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params); }
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        if($is_upd) mysqli_query($db, "UPDATE sim_activations SET company_id=$v[0], project_id=$v[1], po_batch_sim=$v[2], po_provider_id=$v[3], po_client_id=$v[4], total_sim=$v[5], active_qty=$v[6], inactive_qty=$v[7], activation_date=$v[8], activation_qty=$v[9], activation_batch=$v[10] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_activations (company_id, project_id, po_batch_sim, po_provider_id, po_client_id, total_sim, active_qty, inactive_qty, activation_date, activation_qty, activation_batch) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_status.php?msg=" . ($is_upd ? 'updated' : 'success')); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_activation') {
    $id = $_GET['id'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM sim_activations WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM sim_activations WHERE id='$id'");
    header("Location: sim_tracking_status.php?msg=deleted"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_termination' || $_POST['action'] === 'update_termination')) {
    $is_upd = ($_POST['action'] === 'update_termination');
    $id = $_POST['id'] ?? null;
    $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : NULL;
    $params = [$company_id, $project_id, $_POST['po_batch_sim']??NULL, !empty($_POST['po_provider_id'])?$_POST['po_provider_id']:NULL, !empty($_POST['po_client_id'])?$_POST['po_client_id']:NULL, $_POST['total_sim']??0, $_POST['terminated_qty']??0, $_POST['unterminated_qty']??0, $_POST['termination_date']??NULL, $_POST['termination_qty']??0, $_POST['termination_batch']??NULL];

    if ($db_type === 'pdo') {
        if ($is_upd) { $params[] = $id; $db->prepare("UPDATE sim_terminations SET company_id=?, project_id=?, po_batch_sim=?, po_provider_id=?, po_client_id=?, total_sim=?, terminated_qty=?, unterminated_qty=?, termination_date=?, termination_qty=?, termination_batch=? WHERE id=?")->execute($params); }
        else { $db->prepare("INSERT INTO sim_terminations (company_id, project_id, po_batch_sim, po_provider_id, po_client_id, total_sim, terminated_qty, unterminated_qty, termination_date, termination_qty, termination_batch) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params); }
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        if($is_upd) mysqli_query($db, "UPDATE sim_terminations SET company_id=$v[0], project_id=$v[1], po_batch_sim=$v[2], po_provider_id=$v[3], po_client_id=$v[4], total_sim=$v[5], terminated_qty=$v[6], unterminated_qty=$v[7], termination_date=$v[8], termination_qty=$v[9], termination_batch=$v[10] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_terminations (company_id, project_id, po_batch_sim, po_provider_id, po_client_id, total_sim, terminated_qty, unterminated_qty, termination_date, termination_qty, termination_batch) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_status.php?msg=" . ($is_upd ? 'updated' : 'success')); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_termination') {
    $id = $_GET['id'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM sim_terminations WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM sim_terminations WHERE id='$id'");
    header("Location: sim_tracking_status.php?msg=deleted"); exit;
}

// G. Master Company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $name = trim($_POST['company_name']); $type = $_POST['company_type']; $red = $_POST['redirect'];
    if($db_type==='pdo') $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([$name, $type]);
    else mysqli_query($db, "INSERT INTO companies (company_name, company_type) VALUES ('$name', '$type')");
    header("Location: sim_tracking_{$red}_po.php?msg=success"); exit;
}
?>