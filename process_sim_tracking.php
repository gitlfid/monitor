<?php
// =======================================================================
// FILE: process_sim_tracking.php
// DESC: Backend Processor (Full Stack Logic)
// =======================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);
ob_start();

require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/sim_helper.php'; 

if (!$db) {
    if(isset($_POST['is_ajax'])) jsonResponse('error', 'Database Connection Failed');
    die("System Error: DB Connection Failed");
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// 1. AUTO REPAIR (Jaga-jaga tabel belum ada)
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
try { if ($db_type === 'pdo') $db->exec($sql_inv); else mysqli_query($db, $sql_inv); } catch (Exception $e) {}

// =======================================================================
// 2. AJAX HANDLERS (MODERN FEATURES)
// =======================================================================

// --- A. UPLOAD MASTER (Returns JSON) ---
if ($action == 'upload_master_bulk') {
    try {
        $po_id = $_POST['po_provider_id'];
        if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] != 0) jsonResponse('error', 'File error.');

        $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
        if (!$rows || count($rows) < 2) jsonResponse('error', 'File kosong/format salah.');

        $header = $rows[0];
        $idx_msisdn = findIdx($header, ['msisdn','nohp','number','phone','mobile']);
        $idx_iccid = findIdx($header, ['iccid']); $idx_imsi = findIdx($header, ['imsi']); $idx_sn = findIdx($header, ['sn','serial']);

        if ($idx_msisdn === false) jsonResponse('error', 'Header MSISDN tidak ditemukan.');

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
        jsonResponse('success', "Uploaded $c SIMs.", ['count'=>$c]);

    } catch (Exception $e) { if($db_type==='pdo')$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// --- B. FETCH SIMS (ADVANCED SEARCH) ---
if ($action == 'fetch_sims') {
    $po_id = $_POST['po_id']; 
    $mode = $_POST['mode']; 
    $keyword = trim($_POST['keyword'] ?? ''); // Input pencarian
    
    $status = ($mode === 'activate') ? 'Available' : 'Active';
    
    // Base Query
    $q = "SELECT id, msisdn, iccid, imsi, sn, status FROM sim_inventory WHERE po_provider_id = ? AND status = ?";
    $params = [$po_id, $status];

    // Logic Pencarian Advanced (LIKE %...%)
    if (!empty($keyword)) {
        $q .= " AND (msisdn LIKE ? OR iccid LIKE ? OR sn LIKE ?)";
        $wildcard = "%$keyword%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }
    
    $q .= " ORDER BY msisdn ASC LIMIT 500"; // Limit 500 agar UI tidak berat

    try {
        $stmt = $db->prepare($q); 
        $stmt->execute($params); 
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Hitung total di database (untuk info jika > 500)
        // $countParams = array_slice($params, 0, count($params)); // Copy params
        // $qCount = str_replace("SELECT id, msisdn, iccid, imsi, sn, status", "SELECT COUNT(*)", $q);
        // $qCount = str_replace("ORDER BY msisdn ASC LIMIT 500", "", $qCount);
        // $stmtC = $db->prepare($qCount); $stmtC->execute($params); $total = $stmtC->fetchColumn();

        jsonResponse('success', 'OK', ['data' => $data]);
    } 
    catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// --- C. FETCH LOGS ---
if ($action == 'fetch_logs') {
    $po_id = $_POST['po_id'];
    try {
        $logs = [];
        $stmtAct = $db->prepare("SELECT activation_date as date, active_qty as qty, activation_batch as batch, 'Activation' as type FROM sim_activations WHERE po_provider_id = ?");
        $stmtAct->execute([$po_id]); $logs = array_merge($logs, $stmtAct->fetchAll(PDO::FETCH_ASSOC));

        $stmtTerm = $db->prepare("SELECT termination_date as date, terminated_qty as qty, termination_batch as batch, 'Termination' as type FROM sim_terminations WHERE po_provider_id = ?");
        $stmtTerm->execute([$po_id]); $logs = array_merge($logs, $stmtTerm->fetchAll(PDO::FETCH_ASSOC));

        usort($logs, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
        jsonResponse('success', 'Logs fetched', ['data' => $logs]);
    } catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// --- D. PROCESS ACTION ---
if ($action == 'process_bulk_sim_action') {
    try {
        $ids = $_POST['sim_ids'] ?? []; $mode = $_POST['mode']; $date = $_POST['date_field']; $batch = $_POST['batch_name']; $po = $_POST['po_provider_id'];
        if(empty($ids)) jsonResponse('error', 'No selection');

        $st = ($mode === 'activate') ? 'Active' : 'Terminated';
        $dc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
        $db->beginTransaction();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE sim_inventory SET status = ?, $dc = ? WHERE id IN ($ph)")->execute(array_merge([$st, $date], $ids));
        
        $tbl = ($mode === 'activate') ? 'sim_activations' : 'sim_terminations';
        $qty = ($mode === 'activate') ? 'active_qty' : 'terminated_qty';
        $dbc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        $bc = ($mode === 'activate') ? 'activation_batch' : 'termination_batch';
        
        $cnt = count($ids);
        $inf = $db->query("SELECT company_id, project_id FROM sim_tracking_po WHERE id=$po")->fetch();
        $db->prepare("INSERT INTO $tbl (po_provider_id, company_id, project_id, $dbc, $bc, total_sim, $qty) VALUES (?,?,?,?,?,?,?)")
           ->execute([$po, $inf['company_id']??0, $inf['project_id']??0, $date, $batch." (Action)", $cnt, $cnt]);
        
        $db->commit();
        jsonResponse('success', "Processed $cnt SIMs.");
    } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// =======================================================================
// 3. LEGACY HANDLERS (PO, LOGISTIC - FORM POST)
// =======================================================================
if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
    $id=$_POST['id']??null; $type=$_POST['type']; 
    $cId=!empty($_POST['company_id'])?$_POST['company_id']:null; $pId=!empty($_POST['project_id'])?$_POST['project_id']:null;
    $file=uploadFileLegacy($_FILES['po_file'], $type)??$_POST['existing_file']??null;
    $sql=($_POST['action']=='update')?"UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?":"INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $p=[$type, $cId, $pId, $_POST['manual_company_name']??null, $_POST['manual_project_name']??null, $_POST['batch_name']??null, $_POST['link_client_po_id']??null, $_POST['po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    if($_POST['action']=='update') $p[]=$id;
    $db->prepare($sql)->execute($p); header("Location: sim_tracking_{$type}_po.php?msg=success"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_POST['action'], 'logistic') !== false) {
    $id=$_POST['id']??null; $upd=($_POST['action']=='update_logistic');
    $sql=$upd?"UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?":"INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $p=[$_POST['type'], $_POST['po_id']?:0, $_POST['logistic_date'], $_POST['qty'], $_POST['pic_name'], $_POST['pic_phone'], $_POST['delivery_address'], $_POST['courier'], $_POST['awb'], $_POST['status'], $_POST['received_date'], $_POST['receiver_name'], $_POST['notes']];
    if($upd)$p[]=$id;
    $db->prepare($sql)->execute($p); header("Location: sim_tracking_receive.php?msg=success"); exit;
}

if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    $t=($_GET['action']=='delete')?'sim_tracking_po':'sim_tracking_logistics';
    $r=($_GET['action']=='delete')?"sim_tracking_{$_GET['type']}_po.php":"sim_tracking_receive.php";
    $db->prepare("DELETE FROM $t WHERE id=?")->execute([$_GET['id']]); header("Location: $r?msg=deleted"); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([trim($_POST['company_name']), $_POST['company_type']]);
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>