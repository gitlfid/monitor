<?php
// =======================================================================
// PROCESSOR UTAMA (ROUTER)
// =======================================================================
require_once 'includes/auth_check.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';

// Include Helper File (Pastikan file ini ada)
require_once 'includes/sim_helper.php'; 

if (!$db) {
    if(isset($_POST['is_ajax'])) jsonResponse('error', 'Koneksi Database Gagal');
    die("Database Connection Error");
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// =======================================================================
// A. UPLOAD MASTER DATA (AJAX SUPPORT FOR PROGRESS BAR)
// =======================================================================
if ($action == 'upload_master_bulk') {
    try {
        $po_id = $_POST['po_provider_id'];
        
        // Validasi File
        if (!isset($_FILES['upload_file']) || $_FILES['upload_file']['error'] != 0) {
            jsonResponse('error', 'Upload Gagal: File tidak terdeteksi atau error server (Code: '.$_FILES['upload_file']['error'].')');
        }

        // Baca File
        $rows = readSpreadsheet($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['name']);
        if (!$rows || count($rows) < 2) {
            jsonResponse('error', 'File terbaca kosong atau format header salah. Gunakan CSV/XLSX valid.');
        }

        $header = $rows[0];
        $idx_msisdn = findIdx($header, ['msisdn','nohp','number','phone','mobile']);
        $idx_iccid = findIdx($header, ['iccid']); 
        $idx_imsi = findIdx($header, ['imsi']); 
        $idx_sn = findIdx($header, ['sn','serial']);

        if ($idx_msisdn === false) {
            jsonResponse('error', 'Header kolom "MSISDN" tidak ditemukan. Header terbaca: ' . implode(', ', $header));
        }

        if($db_type === 'pdo') $db->beginTransaction();
        
        $c = 0;
        $stmt = $db->prepare("INSERT INTO sim_inventory (po_provider_id, msisdn, iccid, imsi, sn, status) VALUES (?, ?, ?, ?, ?, 'Available')");
        
        // Loop Insert
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
        
        // Response JSON Sukses
        jsonResponse('success', "Berhasil mengupload $c data SIM Card ke inventory.", ['count' => $c]);

    } catch (Exception $e) { 
        if($db_type==='pdo') $db->rollBack(); 
        jsonResponse('error', "Database Error: ".$e->getMessage());
    }
}

// =======================================================================
// B. BULK SEARCH (AJAX)
// =======================================================================
if ($action == 'fetch_sims') {
    $po_id = $_POST['po_id']; $mode = $_POST['mode']; $search = trim($_POST['search_bulk'] ?? '');
    $status = ($mode === 'activate') ? 'Available' : 'Active';
    
    $q = "SELECT id, msisdn, iccid, status FROM sim_inventory WHERE po_provider_id = ? AND status = ?";
    $p = [$po_id, $status];

    if (!empty($search)) {
        $nums = preg_split('/[\s,]+/', str_replace([',', ';'], "\n", $search));
        if (!empty($nums)) {
            $q .= " AND msisdn IN (" . implode(',', array_fill(0, count($nums), '?')) . ")";
            $p = array_merge($p, $nums);
        }
    }
    $q .= " ORDER BY msisdn ASC LIMIT 500"; 

    try {
        $stmt = $db->prepare($q); $stmt->execute($p);
        jsonResponse('success', 'OK', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) { jsonResponse('error', $e->getMessage()); }
}

// =======================================================================
// C. PROCESS ACTION (ACTIVATE/TERMINATE) (AJAX)
// =======================================================================
if ($action == 'process_bulk_sim_action') {
    try {
        $ids = $_POST['sim_ids'] ?? []; $mode = $_POST['mode']; $date = $_POST['date_field']; $batch = $_POST['batch_name']; $po = $_POST['po_provider_id'];
        if(empty($ids)) jsonResponse('error', 'No SIMs selected');

        $st = ($mode === 'activate') ? 'Active' : 'Terminated';
        $dc = ($mode === 'activate') ? 'activation_date' : 'termination_date';
        
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
           ->execute([$po, $inf['company_id']??0, $inf['project_id']??0, $date, $batch." (Action)", $cnt, $cnt]);
        
        $db->commit();
        jsonResponse('success', "Successfully processed $cnt SIMs.");
    } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); jsonResponse('error', $e->getMessage()); }
}

// =======================================================================
// D. LEGACY FEATURES (PO, LOGISTICS, COMPANY) - TETAP ADA & UTUH
// =======================================================================

// PO Management
if (isset($_POST['action']) && ($_POST['action'] == 'create' || $_POST['action'] == 'update')) {
    $id = $_POST['id']??null; $type = $_POST['type']; 
    $cId = $_POST['company_id']?:null; $pId = $_POST['project_id']?:null;
    $mC = (!$cId && !empty($_POST['manual_company_name']))?$_POST['manual_company_name']:null;
    $mP = (!$pId && !empty($_POST['manual_project_name']))?$_POST['manual_project_name']:null;
    $file = uploadFileLegacy($_FILES['po_file'], $type) ?? $_POST['existing_file'] ?? null;
    
    $sql = ($_POST['action']=='update') 
        ? "UPDATE sim_tracking_po SET type=?, company_id=?, project_id=?, manual_company_name=?, manual_project_name=?, batch_name=?, link_client_po_id=?, po_number=?, po_date=?, sim_qty=?, po_file=? WHERE id=?"
        : "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    
    $p = [$type, $cId, $pId, $mC, $mP, $_POST['batch_name']??null, $_POST['link_client_po_id']??null, $_POST['po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    if($_POST['action']=='update') $p[] = $id;
    
    $stmt = $db->prepare($sql); $stmt->execute($p);
    header("Location: sim_tracking_{$type}_po.php?msg=success"); exit;
}

// Provider from Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_provider_from_client') {
    $cId = !empty($_POST['provider_company_id']) ? $_POST['provider_company_id'] : NULL;
    $mC = (empty($cId) && !empty($_POST['manual_provider_name'])) ? $_POST['manual_provider_name'] : NULL;
    $file = uploadFileLegacy($_FILES['po_file'], 'provider');
    $p = ['provider', $cId, NULL, $mC, NULL, $_POST['batch_name'], $_POST['link_client_po_id'], $_POST['provider_po_number'], $_POST['po_date'], str_replace(',','',$_POST['sim_qty']), $file];
    $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($p);
    header("Location: sim_tracking_provider_po.php?msg=created_from_client"); exit;
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

// Delete Logic
if (isset($_GET['action']) && strpos($_GET['action'], 'delete') !== false) {
    $t = ''; $r = '';
    if($_GET['action']=='delete') { $t='sim_tracking_po'; $r="sim_tracking_{$_GET['type']}_po.php"; }
    if($_GET['action']=='delete_logistic') { $t='sim_tracking_logistics'; $r="sim_tracking_receive.php"; }
    if($t) { $db->prepare("DELETE FROM $t WHERE id=?")->execute([$_GET['id']]); header("Location: $r?msg=deleted"); exit; }
}

// Company Logic
if (isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([trim($_POST['company_name']), $_POST['company_type']]);
    header("Location: sim_tracking_{$_POST['redirect']}_po.php?msg=success"); exit;
}
?>