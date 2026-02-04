<?php
// =======================================================================
// 1. SETUP & DB CONNECTION
// =======================================================================
ini_set('display_errors', 0); // Hide errors for clean JSON/Redirect
error_reporting(E_ALL);

require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';

$db = null; $db_type = '';
// Universal Connection Logic
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }

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

if (!$db) {
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json'); echo json_encode(['status'=>'error', 'message'=>'DB Connection Failed']); exit;
    } else { die("System Error: Database Connection Failed."); }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// 2. AUTO-REPAIR DATABASE (Ensure Inventory Table Exists)
// =======================================================================
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

try {
    if ($db_type === 'pdo') $db->exec($sql_inv); else mysqli_query($db, $sql_inv);
} catch (Exception $e) {}

// Fix Legacy Tables columns
function fixCol($db, $t, $c, $d, $type) {
    try {
        $exists = false;
        if ($type === 'pdo') { $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'"); if($res && $res->rowCount()>0) $exists=true; }
        else { $res = mysqli_query($db, "SHOW COLUMNS FROM `$t` LIKE '$c'"); if($res && mysqli_num_rows($res)>0) $exists=true; }
        if(!$exists) { 
            $sql="ALTER TABLE `$t` ADD COLUMN `$c` $d"; 
            if($type==='pdo')$db->exec($sql); else mysqli_query($db,$sql); 
        }
    } catch(Exception $e){}
}
foreach(['msisdn','iccid','imsi','sn'] as $col) {
    fixCol($db, 'sim_activations', $col, "VARCHAR(50) NULL", $db_type);
    fixCol($db, 'sim_terminations', $col, "VARCHAR(50) NULL", $db_type);
}
fixCol($db, 'sim_terminations', 'po_provider_id', "INT(11) NULL", $db_type);

// =======================================================================
// 3. HELPER: SMART FILE READER (CSV/EXCEL)
// =======================================================================
function readSpreadsheet($tmpPath, $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $data = [];

    // CSV Handler with BOM Fix
    if ($ext === 'csv') {
        if (($handle = fopen($tmpPath, "r")) !== FALSE) {
            $bom = "\xEF\xBB\xBF";
            $firstLine = fgets($handle);
            if (strncmp($firstLine, $bom, 3) === 0) $firstLine = substr($firstLine, 3);
            if(!empty(trim($firstLine))) $data[] = str_getcsv(trim($firstLine));
            
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if(array_filter($row)) $data[] = $row;
            }
            fclose($handle);
        }
        return $data;
    }

    // XLSX Handler
    if ($ext === 'xlsx') {
        $zip = new ZipArchive;
        if ($zip->open($tmpPath) === TRUE) {
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

// Helper: Cari Index Kolom
function findIdx($headers, $keys) {
    foreach ($headers as $i => $v) {
        $clean = strtolower(trim(str_replace([' ','_','-','.'], '', $v)));
        if (in_array($clean, $keys)) return $i;
    }
    return false;
}

// =======================================================================
// 4. ACTION HANDLERS (CORE LOGIC)
// =======================================================================

// --- A. UPLOAD MASTER (Masuk ke Inventory sebagai Available) ---
if ($action == 'upload_master_bulk') {
    try {
        $po_id = $_POST['po_provider_id'];
        
        if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
            $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
            if (!$rows || count($rows) < 2) die("<script>alert('File kosong/format salah');window.history.back();</script>");

            $header = $rows[0];
            $idx_msisdn = findIdx($header, ['msisdn','nohp','number','phone','mobile']);
            $idx_iccid = findIdx($header, ['iccid']); 
            $idx_imsi = findIdx($header, ['imsi']); 
            $idx_sn = findIdx($header, ['sn','serial']);

            if ($idx_msisdn === false) die("<script>alert('Header MSISDN tidak ditemukan');window.history.back();</script>");

            if($db_type === 'pdo') $db->beginTransaction();
            $c = 0;
            $stmt = $db->prepare("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES (?, ?, ?, ?, ?, 'Available')");
            
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
    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); die("Error: ".$e->getMessage()); }
}

// --- B. CREATE ACTIVATION / TERMINATION (Logic Sync Inventory) ---
// Handler ini dipakai untuk Manual Submit & File Upload (via Form)
if ($action == 'create_activation_simple' || $action == 'create_termination_simple') {
    try {
        $is_term = ($action == 'create_termination_simple');
        $target_status = $is_term ? 'Terminated' : 'Active';
        $log_table = $is_term ? 'sim_terminations' : 'sim_activations';
        $batch_f = $is_term ? 'termination_batch' : 'activation_batch';
        $date_f = $is_term ? 'termination_date' : 'activation_date';
        $qty_f = $is_term ? 'terminated_qty' : 'active_qty';
        
        $po_id = $_POST['po_provider_id'];
        $date = $_POST['date_field'];
        $batch = $_POST[$batch_f];
        
        // Auto Lookup
        $company_id = $_POST['company_id'] ?? 0;
        $project_id = $_POST['project_id'] ?? 0;
        if (empty($company_id)) {
            $q = $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id = $po_id")->fetch();
            $company_id = $q['company_id'] ?? 0;
            $project_id = $q['project_id'] ?? 0;
        }

        // --- MODE 1: FILE UPLOAD (SYNC INVENTORY) ---
        if (isset($_FILES['action_file']) && $_FILES['action_file']['error'] == 0) {
            $rows = readSpreadsheet($_FILES['action_file']['tmp_name'], $_FILES['action_file']['name']);
            if (!$rows || count($rows) < 2) die("<script>alert('File Error');window.history.back();</script>");

            $header = $rows[0];
            $idx_msisdn = findIdx($header, ['msisdn','nohp','number','phone']);
            if ($idx_msisdn === false) die("<script>alert('Header MSISDN Missing');window.history.back();</script>");

            if($db_type === 'pdo') $db->beginTransaction();
            
            $msisdn_list = [];
            for($i=1; $i<count($rows); $i++) {
                $r = $rows[$i];
                $m = isset($r[$idx_msisdn]) ? trim($r[$idx_msisdn]) : '';
                if(!empty($m)) $msisdn_list[] = $m;
            }

            // Sync Update Inventory
            if(!empty($msisdn_list)) {
                $chunks = array_chunk($msisdn_list, 500); // Batch update per 500
                foreach($chunks as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '?'));
                    $sql_up = "UPDATE sim_inventory SET status = ?, $date_f = ? WHERE msisdn IN ($ph) AND po_provider_id = ?";
                    $params = array_merge([$target_status, $date], $chunk, [$po_id]);
                    $stmt = $db->prepare($sql_up);
                    $stmt->execute($params);
                }
            }

            // Catat Log Summary (1 Baris Log mewakili semua upload)
            $count = count($msisdn_list);
            $stmtLog = $db->prepare("INSERT INTO $log_table (po_provider_id, company_id, project_id, $date_f, $batch_f, total_sim, $qty_f) VALUES (?,?,?,?,?,?,?)");
            $stmtLog->execute([$po_id, $company_id, $project_id, $date, $batch." (File)", $count, $count]);

            if($db_type === 'pdo') $db->commit();
            header("Location: sim_tracking_status.php?msg=processed_file&count=$count"); exit;

        } else {
            // --- MODE 2: MANUAL QTY (Legacy / Bulk Blind) ---
            // Hanya update log tanpa update inventory (karena tidak tahu nomor mana)
            // ATAU kita ambil X nomor available dan update mereka (Auto-Pick).
            // Disini saya implementasi Auto-Pick agar Inventory tetap sinkron.
            
            $qty = ($is_term ? $_POST['terminated_qty'] : $_POST['active_qty']) ?? 0;
            $qty = intval($qty);

            if($qty > 0) {
                if($db_type === 'pdo') $db->beginTransaction();
                
                // Cari ID yang Available (untuk activate) atau Active (untuk terminate)
                $source_status = $is_term ? 'Active' : 'Available';
                
                $sql_get = "SELECT id FROM sim_inventory WHERE po_provider_id = ? AND status = ? LIMIT $qty";
                $stmtGet = $db->prepare($sql_get);
                $stmtGet->execute([$po_id, $source_status]);
                $ids = $stmtGet->fetchAll(PDO::FETCH_COLUMN);

                if(count($ids) > 0) {
                    $id_str = implode(',', $ids);
                    $db->query("UPDATE sim_inventory SET status = '$target_status', $date_f = '$date' WHERE id IN ($id_str)");
                }

                // Insert Log
                $stmtLog = $db->prepare("INSERT INTO $log_table (po_provider_id, company_id, project_id, $date_f, $batch_f, total_sim, $qty_f) VALUES (?,?,?,?,?,?,?)");
                $stmtLog->execute([$po_id, $company_id, $project_id, $date, $batch, $qty, $qty]);

                if($db_type === 'pdo') $db->commit();
            }
            header("Location: sim_tracking_status.php?msg=processed_manual&count=$qty"); exit;
        }

    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); die("Error: ".$e->getMessage()); }
}

// --- API: BULK SEARCH (AJAX) ---
if ($action == 'fetch_sims') {
    header('Content-Type: application/json');
    $po_id = $_POST['po_id']; $mode = $_POST['mode']; $search = trim($_POST['search_bulk'] ?? '');
    $status = ($mode === 'activate') ? 'Available' : 'Active';
    
    $q = "SELECT id, msisdn, iccid, status FROM sim_inventory WHERE po_provider_id = ? AND status = ?";
    $p = [$po_id, $status];

    if (!empty($search)) {
        $nums = preg_split('/[\s,]+/', $search); // Split by space/comma/newline
        if (!empty($nums)) {
            $q .= " AND msisdn IN (" . implode(',', array_fill(0, count($nums), '?')) . ")";
            $p = array_merge($p, $nums);
        }
    }
    $q .= " LIMIT 500"; 

    try {
        $stmt = $db->prepare($q);
        $stmt->execute($p);
        echo json_encode(['status'=>'success', 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) { echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]); }
    exit;
}

// --- API: PROCESS AJAX BULK (SWITCH STATUS) ---
if ($action == 'process_bulk_sim_action') {
    header('Content-Type: application/json');
    try {
        $ids = $_POST['sim_ids'] ?? []; $mode = $_POST['mode']; $date = $_POST['date_field']; $batch = $_POST['batch_name']; $po = $_POST['po_provider_id'];
        if(empty($ids)) { echo json_encode(['status'=>'error']); exit; }

        $st = ($mode === 'activate') ? 'Active' : 'Terminated';
        $dc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
        if ($db_type === 'pdo') {
            $db->beginTransaction();
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("UPDATE sim_inventory SET status = ?, $dc = ? WHERE id IN ($ph)")->execute(array_merge([$st, $date], $ids));
            
            // Log Summary
            $tbl = ($mode === 'activate') ? 'sim_activations' : 'sim_terminations';
            $qty = ($mode === 'activate') ? 'active_qty' : 'terminated_qty';
            $dbc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
            $bc = ($mode === 'activate') ? 'activation_batch' : 'termination_batch';
            
            $cnt = count($ids);
            $inf = $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po")->fetch();
            $db->prepare("INSERT INTO $tbl (po_provider_id, company_id, project_id, $dbc, $bc, total_sim, $qty) VALUES (?,?,?,?,?,?,?)")
               ->execute([$po, $inf['company_id']??0, $inf['project_id']??0, $date, $batch." (Selected)", $cnt, $cnt]);
            
            $db->commit();
            echo json_encode(['status'=>'success']);
        }
    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); echo json_encode(['status'=>'error']); }
    exit;
}

// =======================================================================
// LEGACY FEATURES (KEEPING THEM ALIVE)
// =======================================================================
function uploadFile($file, $prefix) {
    if(isset($file) && $file['error']===0) {
        $dir = __DIR__ . "/uploads/po/"; if(!is_dir($dir)) mkdir($dir,0755,true);
        $name = $prefix."_".time()."_".rand(100,999).".".pathinfo($file['name'], PATHINFO_EXTENSION);
        if(move_uploaded_file($file['tmp_name'], $dir.$name)) return $name;
    } return null;
}

// PO Create/Update
if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
    $id = $_POST['id']??null; $type = $_POST['type']; 
    $cId = $_POST['company_id']?:null; $pId = $_POST['project_id']?:null;
    $mC = (!$cId && !empty($_POST['manual_company_name']))?$_POST['manual_company_name']:null;
    $mP = (!$pId && !empty($_POST['manual_project_name']))?$_POST['manual_project_name']:null;
    $file = uploadFile($_FILES['po_file'], $type) ?? $_POST['existing_file'] ?? null;
    
    $sql = ($_POST['action']=='update') 
        ? "UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?"
        : "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    
    $p = [$type, $cId, $pId, $mC, $mP, $_POST['batch_name']??null, $_POST['link_client_po_id']??null, $_POST['po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    if($_POST['action']=='update') $p[] = $id;
    
    $stmt = $db->prepare($sql); $stmt->execute($p);
    header("Location: sim_tracking_{$type}_po.php?msg=success"); exit;
}

// Logistics
if (isset($_POST['action']) && strpos($_POST['action'], 'logistic') !== false) {
    $id = $_POST['id']??null; $act = $_POST['action'];
    $sql = ($act=='update_logistic') 
        ? "UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?"
        : "INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    
    $p = [$_POST['type'], $_POST['po_id']?:0, $_POST['logistic_date'], $_POST['qty'], $_POST['pic_name'], $_POST['pic_phone'], $_POST['delivery_address'], $_POST['courier'], $_POST['awb'], $_POST['status'], $_POST['received_date'], $_POST['receiver_name'], $_POST['notes']];
    if($act=='update_logistic') $p[] = $id;
    
    $stmt = $db->prepare($sql); $stmt->execute($p);
    header("Location: sim_tracking_receive.php?msg=success"); exit;
}

// Delete Handlers
if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    $t = ''; $r = '';
    if($_GET['action']=='delete') { $t='sim_tracking_po'; $r="sim_tracking_{$_GET['type']}_po.php"; }
    if($_GET['action']=='delete_logistic') { $t='sim_tracking_logistics'; $r="sim_tracking_receive.php"; }
    
    if($t) { $db->prepare("DELETE FROM $t WHERE id=?")->execute([$_GET['id']]); header("Location: $r?msg=deleted"); exit; }
}

// Company
if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([trim($_POST['company_name']), $_POST['company_type']]);
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>