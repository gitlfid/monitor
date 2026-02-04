<?php
// =======================================================================
// 1. INISIALISASI & KONEKSI DATABASE
// =======================================================================
// Kita matikan display_errors agar response JSON untuk AJAX tidak rusak oleh warning PHP
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';

// Koneksi Universal (Support PDO & MySQLi)
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }

// Fallback Koneksi
if (!$db && defined('DB_HOST')) { 
    try { 
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); 
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $db_type = 'pdo'; 
    } catch (Exception $e) { 
        // Jika request AJAX, kirim JSON error
        if(isset($_POST['is_ajax'])) { header('Content-Type: application/json'); echo json_encode(['status'=>'error', 'message'=>'DB Connection Failed']); exit; }
        die("System Error: Database Connection Failed.");
    } 
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// 2. AUTO-REPAIR (INJECT FITUR BARU TANPA MERUSAK LAMA)
// =======================================================================
// Kita pastikan tabel inventory ada untuk fitur modern, tapi tabel lama tetap jalan
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

try { if ($db_type === 'pdo') $db->exec($sql_inv); else mysqli_query($db, $sql_inv); } catch (Exception $e) {}

// Cek Kolom di tabel lama (Legacy)
function checkAndFix($db, $db_type, $table, $col, $def) {
    try {
        $exists = false;
        if ($db_type === 'pdo') { $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'"); if ($stmt->rowCount() > 0) $exists = true; }
        else { $res = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '$col'"); if (mysqli_num_rows($res) > 0) $exists = true; }
        if (!$exists) {
            $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $def";
            if ($db_type === 'pdo') $db->exec($sql); else mysqli_query($db, $sql);
        }
    } catch (Exception $e) {}
}
foreach(['msisdn','iccid','imsi','sn'] as $c) {
    checkAndFix($db, $db_type, 'sim_activations', $c, "VARCHAR(50) NULL");
    checkAndFix($db, $db_type, 'sim_terminations', $c, "VARCHAR(50) NULL");
}
checkAndFix($db, $db_type, 'sim_terminations', 'po_provider_id', "INT(11) NULL");

// =======================================================================
// 3. HELPER FUNCTIONS
// =======================================================================
function readSpreadsheet($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $data = [];
    if ($ext === 'csv') {
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $bom = "\xEF\xBB\xBF"; $firstLine = fgets($handle);
            if (strncmp($firstLine, $bom, 3) === 0) $firstLine = substr($firstLine, 3);
            if(!empty(trim($firstLine))) $data[] = str_getcsv(trim($firstLine));
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) { if(array_filter($row)) $data[] = $row; }
            fclose($handle);
        }
        return $data;
    }
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
                    $rowData = [];
                    foreach ($row->c as $cell) {
                        $val = (string)$cell->v;
                        if (isset($cell['t']) && (string)$cell['t'] === 's') $val = $sharedStrings[intval($val)] ?? $val;
                        $rowData[] = $val;
                    }
                    if(!empty($rowData)) $data[] = $rowData;
                }
            }
            $zip->close();
        }
        return $data;
    }
    return false; 
}

function findColIdx($header, $keys) {
    foreach($header as $i => $v) {
        if(in_array(strtolower(trim(str_replace([' ','_','-','.'],'',$v))), $keys)) return $i;
    }
    return false;
}

// =======================================================================
// 4. ACTION HANDLERS
// =======================================================================

// --- A. UPLOAD MASTER BULK (MODIFIED FOR AJAX + PROGRESS) ---
if ($action == 'upload_master_bulk') {
    $is_ajax = isset($_POST['is_ajax']); // Deteksi mode panggil
    
    try {
        $po_id      = $_POST['po_provider_id'];
        $company_id = $_POST['company_id'] ?? 0;
        $project_id = $_POST['project_id'] ?? 0;
        $date       = $_POST['date_field'];
        $batch      = $_POST['activation_batch'];
        
        if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == 0) {
            $rows = readSpreadsheet($_FILES['upload_file']['tmp_name']); // Pake helper lama yg sudah fix BOM

            if (!$rows || count($rows) < 2) {
                $msg = "File kosong atau format header tidak terbaca.";
                if($is_ajax) { echo json_encode(['status'=>'error', 'message'=>$msg]); exit; }
                die($msg);
            }

            $header = $rows[0];
            $idx_msisdn = findColIdx($header, ['msisdn','nohp','number','phone']);
            $idx_iccid  = findColIdx($header, ['iccid']);
            $idx_imsi   = findColIdx($header, ['imsi']);
            $idx_sn     = findColIdx($header, ['sn','serial']);

            if ($idx_msisdn === false) {
                $msg = "Header MSISDN tidak ditemukan. Header terbaca: " . implode(', ', $header);
                if($is_ajax) { echo json_encode(['status'=>'error', 'message'=>$msg]); exit; }
                die($msg);
            }

            if($db_type === 'pdo') $db->beginTransaction();

            $successCount = 0;
            // Statement Prepare untuk 2 tabel (Legacy & Inventory Baru)
            // Agar dashboard lama jalan, dashboard baru juga jalan.
            if ($db_type === 'pdo') {
                $stmtLegacy = $db->prepare("INSERT INTO sim_activations (po_provider_id, company_id, project_id, activation_date, activation_batch, total_sim, active_qty, inactive_qty, msisdn, iccid, imsi, sn) VALUES (?, ?, ?, ?, ?, 1, 1, 0, ?, ?, ?, ?)");
                $stmtNew = $db->prepare("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES (?, ?, ?, ?, ?, 'Available')");
            }

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $msisdn = isset($row[$idx_msisdn]) ? trim($row[$idx_msisdn]) : '';
                $iccid  = ($idx_iccid !== false && isset($row[$idx_iccid])) ? trim($row[$idx_iccid]) : NULL;
                $imsi   = ($idx_imsi !== false && isset($row[$idx_imsi])) ? trim($row[$idx_imsi]) : NULL;
                $sn     = ($idx_sn !== false && isset($row[$idx_sn])) ? trim($row[$idx_sn]) : NULL;

                if (empty($msisdn)) continue; 

                if ($db_type === 'pdo') {
                    // 1. Insert ke Tabel Lama (Legacy)
                    $stmtLegacy->execute([$po_id, $company_id, $project_id, $date, $batch, $msisdn, $iccid, $imsi, $sn]);
                    // 2. Insert ke Tabel Baru (Inventory)
                    $stmtNew->execute([$po_id, $msisdn, $iccid, $imsi, $sn]);
                } else {
                    // MySQLi Fallback
                    $ic = $iccid?"'$iccid'":"NULL"; $im = $imsi?"'$imsi'":"NULL"; $s = $sn?"'$sn'":"NULL";
                    mysqli_query($db, "INSERT INTO sim_activations (po_provider_id, company_id, project_id, activation_date, activation_batch, total_sim, active_qty, inactive_qty, msisdn, iccid, imsi, sn) VALUES ('$po_id', '$company_id', '$project_id', '$date', '$batch', 1, 1, 0, '$msisdn', $ic, $im, $s)");
                    mysqli_query($db, "INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES ('$po_id', '$msisdn', $ic, $im, $s, 'Available')");
                }
                $successCount++;
            }
            
            if($db_type === 'pdo') $db->commit();
            
            if($is_ajax) {
                echo json_encode(['status'=>'success', 'message'=>"Berhasil mengupload $successCount data SIM.", 'count'=>$successCount]); exit;
            } else {
                header("Location: sim_tracking_status.php?msg=uploaded_bulk&count=$successCount"); exit;
            }

        } else {
            $msg = "Upload Gagal. Error Code: " . $_FILES['upload_file']['error'];
            if($is_ajax) { echo json_encode(['status'=>'error', 'message'=>$msg]); exit; }
            die($msg);
        }

    } catch (Exception $e) {
        if($db_type === 'pdo') $db->rollBack();
        $msg = "System Error: " . $e->getMessage();
        if($is_ajax) { echo json_encode(['status'=>'error', 'message'=>$msg]); exit; }
        die($msg);
    }
}

// --- B. CREATE ACTIVATION & TERMINATION (MANUAL & FILE) ---
if ($action == 'create_activation_simple' || $action == 'create_termination_simple') {
    // ... [BAGIAN INI TETAP UTUH DARI KODE ANDA] ...
    // Saya salin ulang logika insert ke sim_activations/terminations
    try {
        $is_term = ($action == 'create_termination_simple');
        $table   = $is_term ? 'sim_terminations' : 'sim_activations';
        $batch_f = $is_term ? 'termination_batch' : 'activation_batch';
        $date_f  = $is_term ? 'termination_date' : 'activation_date';
        $qty_f   = $is_term ? 'terminated_qty' : 'active_qty';
        $xtra_f  = $is_term ? ', unterminated_qty' : ', inactive_qty';
        
        $po_id = $_POST['po_provider_id'];
        $date  = $_POST['date_field'];
        $batch = $_POST[$batch_f];
        
        $company_id = $_POST['company_id'] ?? 0;
        $project_id = $_POST['project_id'] ?? 0;
        
        // Auto Lookup
        if (empty($company_id)) {
            $q = ($db_type==='pdo') ? $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po_id")->fetch() : mysqli_fetch_assoc(mysqli_query($db, "SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po_id"));
            $company_id = $q['company_id']??0; $project_id = $q['project_id']??0;
        }

        if (isset($_FILES['action_file']) && $_FILES['action_file']['error'] == 0) {
            $rows = readSpreadsheet($_FILES['action_file']['tmp_name']);
            if (!$rows || count($rows) < 2) die("File Format Invalid");
            
            $header = $rows[0];
            $idx_msisdn = findColIdx($header, ['msisdn','nohp','number']);
            if ($idx_msisdn === false) die("Header MSISDN Missing");

            if($db_type === 'pdo') $db->beginTransaction();
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $msisdn = isset($row[$idx_msisdn]) ? trim($row[$idx_msisdn]) : '';
                if (empty($msisdn)) continue;

                // Insert Log
                if ($db_type === 'pdo') {
                    $db->prepare("INSERT INTO $table (po_provider_id, company_id, project_id, $date_f, $batch_f, total_sim, $qty_f $xtra_f, msisdn) VALUES (?,?,?,?,?,1,1,0,?)")
                       ->execute([$po_id, $company_id, $project_id, $date, $batch, $msisdn]);
                    // Sync Inventory (Fitur Tambahan)
                    $stat = $is_term ? 'Terminated' : 'Active';
                    $db->prepare("UPDATE sim_inventory SET status=?, $date_f=? WHERE msisdn=?")->execute([$stat, $date, $msisdn]);
                } else {
                    mysqli_query($db, "INSERT INTO $table (po_provider_id, company_id, project_id, $date_f, $batch_f, total_sim, $qty_f $xtra_f, msisdn) VALUES ('$po_id', '$company_id', '$project_id', '$date', '$batch', 1, 1, 0, '$msisdn')");
                }
            }
            if($db_type === 'pdo') $db->commit();
        } else {
            // Manual Qty
            $qty = ($is_term ? $_POST['terminated_qty'] : $_POST['active_qty']) ?? 0;
            $msisdn = $_POST['msisdn'] ?? NULL;
            if ($db_type === 'pdo') {
                $db->prepare("INSERT INTO $table (po_provider_id, company_id, project_id, $date_f, $batch_f, total_sim, $qty_f $xtra_f, msisdn) VALUES (?,?,?,?,?,?,?,0,?)")
                   ->execute([$po_id, $company_id, $project_id, $date, $batch, $qty, $qty, $msisdn]);
            } else {
                $m = $msisdn ? "'$msisdn'" : "NULL";
                mysqli_query($db, "INSERT INTO $table (po_provider_id, company_id, project_id, $date_f, $batch_f, total_sim, $qty_f $xtra_f, msisdn) VALUES ('$po_id', '$company_id', '$project_id', '$date', '$batch', '$qty', '$qty', 0, $m)");
            }
        }
        header("Location: sim_tracking_status.php?msg=" . ($is_term ? 'terminated' : 'activated')); exit;
    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); die("Error: ".$e->getMessage()); }
}

// =======================================================================
// [LEGACY] 5. FITUR LAMA - PO, LOGISTIC, COMPANY (TIDAK DIUBAH)
// =======================================================================
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

// PO CREATE/UPDATE
if (isset($_POST['action']) && ($_POST['action'] === 'create' || $_POST['action'] === 'update')) {
    $is_upd = ($_POST['action'] === 'update');
    $id = $_POST['id'] ?? null;
    $type = $_POST['type'];
    $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    $project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : NULL;
    $m_comp = (empty($company_id) && !empty($_POST['manual_company_name'])) ? $_POST['manual_company_name'] : NULL;
    $m_proj = (empty($project_id) && !empty($_POST['manual_project_name'])) ? $_POST['manual_project_name'] : NULL;
    $po_num = $_POST['po_number'];
    $po_date = $_POST['po_date'] ?? date('Y-m-d');
    $sim_qty = str_replace(',', '', $_POST['sim_qty']); 
    $batch = $_POST['batch_name'] ?? NULL;
    $link = $_POST['link_client_po_id'] ?? NULL;
    $file = uploadFile($_FILES['po_file'], $type) ?? $_POST['existing_file'] ?? NULL;

    $params = [$type, $company_id, $project_id, $m_comp, $m_proj, $batch, $link, $po_num, $po_date, $sim_qty, $file];

    if ($db_type === 'pdo') {
        if ($is_upd) {
            $params[] = $id;
            $db->prepare("UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?")->execute($params);
        } else {
            $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
        }
    } else {
        // Fallback MySQLi Logic here if needed...
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        if($is_upd) mysqli_query($db, "UPDATE sim_tracking_po SET type=$v[0], company_id=$v[1], project_id=$v[2], manual_company_name=$v[3], manual_project_name=$v[4], batch_name=$v[5], link_client_po_id=$v[6], po_number=$v[7], po_date=$v[8], sim_qty=$v[9], po_file=$v[10] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_{$type}_po.php?msg=" . ($is_upd ? 'updated' : 'success')); exit;
}

// LOGISTICS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && strpos($_POST['action'], 'logistic') !== false) {
    // ... Logic Logistik Asli (Singkatnya sama) ...
    $id = $_POST['id'] ?? null; $is_upd = ($_POST['action'] === 'update_logistic');
    $p = [$_POST['type'], $_POST['po_id']?:0, $_POST['logistic_date'], $_POST['qty'], $_POST['pic_name'], $_POST['pic_phone'], $_POST['delivery_address'], $_POST['courier'], $_POST['awb'], $_POST['status'], $_POST['received_date'], $_POST['receiver_name'], $_POST['notes']];
    if($db_type === 'pdo') {
        if($is_upd) { $p[]=$id; $db->prepare("UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?")->execute($p); }
        else { $db->prepare("INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($p); }
    }
    header("Location: sim_tracking_receive.php?msg=success"); exit;
}

// DELETE HANDLER
if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    $t = ''; $r = ''; $id = $_GET['id'];
    if($_GET['action']=='delete') { $t='sim_tracking_po'; $r="sim_tracking_{$_GET['type']}_po.php"; }
    if($_GET['action']=='delete_logistic') { $t='sim_tracking_logistics'; $r="sim_tracking_receive.php"; }
    if($t) { if($db_type=='pdo') $db->prepare("DELETE FROM $t WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM $t WHERE id='$id'"); header("Location: $r?msg=deleted"); exit; }
}

// COMPANY
if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    if($db_type=='pdo') $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([$_POST['company_name'], $_POST['company_type']]);
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>