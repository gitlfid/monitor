<?php
// =======================================================================
// 1. INISIALISASI & KONEKSI DATABASE
// =======================================================================
ini_set('display_errors', 1);
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
        $db = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME); 
        if ($db) $db_type = 'mysqli'; 
    } 
}
if (!$db) die("System Error: Database Connection Failed.");

// =======================================================================
// 2. AUTO-REPAIR & MIGRATION (UTUH - TIDAK DIUBAH)
// =======================================================================
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

// A. Tabel PO
if ($db_type === 'pdo') $db->exec("CREATE TABLE IF NOT EXISTS `sim_tracking_po` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");
else mysqli_query($db, "CREATE TABLE IF NOT EXISTS `sim_tracking_po` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");

checkAndFix($db, $db_type, 'sim_tracking_po', 'type', "ENUM('client','provider') NOT NULL DEFAULT 'client'");
checkAndFix($db, $db_type, 'sim_tracking_po', 'po_number', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'po_date', "DATE NULL"); // Pastikan kolom po_date ada
checkAndFix($db, $db_type, 'sim_tracking_po', 'company_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'project_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'manual_company_name', "VARCHAR(255) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'manual_project_name', "VARCHAR(255) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'batch_name', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'link_client_po_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'sim_qty', "INT(11) NOT NULL DEFAULT 0");
checkAndFix($db, $db_type, 'sim_tracking_po', 'po_file', "VARCHAR(255) NULL");
checkAndFix($db, $db_type, 'sim_tracking_po', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// B. Tabel Logistics
if ($db_type === 'pdo') $db->exec("CREATE TABLE IF NOT EXISTS `sim_tracking_logistics` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");
else mysqli_query($db, "CREATE TABLE IF NOT EXISTS `sim_tracking_logistics` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");

checkAndFix($db, $db_type, 'sim_tracking_logistics', 'type', "ENUM('receive','delivery') NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'po_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'logistic_date', "DATE NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'pic_name', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'pic_phone', "VARCHAR(50) NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'delivery_address', "TEXT NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'courier', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'awb', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'status', "VARCHAR(50) DEFAULT 'On Process'");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'received_date', "DATE NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'receiver_name', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'notes', "TEXT NULL");
checkAndFix($db, $db_type, 'sim_tracking_logistics', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// C. Tabel Activations
if ($db_type === 'pdo') $db->exec("CREATE TABLE IF NOT EXISTS `sim_activations` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");
else mysqli_query($db, "CREATE TABLE IF NOT EXISTS `sim_activations` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");

checkAndFix($db, $db_type, 'sim_activations', 'company_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_activations', 'project_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_activations', 'po_batch_sim', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_activations', 'po_provider_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_activations', 'po_client_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_activations', 'total_sim', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_activations', 'active_qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_activations', 'inactive_qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_activations', 'activation_date', "DATE NULL");
checkAndFix($db, $db_type, 'sim_activations', 'activation_qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_activations', 'activation_batch', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_activations', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// D. Tabel Terminations
if ($db_type === 'pdo') $db->exec("CREATE TABLE IF NOT EXISTS `sim_terminations` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");
else mysqli_query($db, "CREATE TABLE IF NOT EXISTS `sim_terminations` ( `id` int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`) ) ENGINE=InnoDB;");

checkAndFix($db, $db_type, 'sim_terminations', 'company_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'project_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'po_batch_sim', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'po_provider_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'po_client_id', "INT(11) NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'total_sim', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_terminations', 'terminated_qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_terminations', 'unterminated_qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_terminations', 'termination_date', "DATE NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'termination_qty', "INT(11) DEFAULT 0");
checkAndFix($db, $db_type, 'sim_terminations', 'termination_batch', "VARCHAR(100) NULL");
checkAndFix($db, $db_type, 'sim_terminations', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");


// Helper Upload
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
// 3. LOGISTIK (RECEIVE & DELIVERY)
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_logistic' || $_POST['action'] === 'update_logistic')) {
    $is_upd = ($_POST['action'] === 'update_logistic');
    $id = $_POST['id'] ?? null;

    $params = [
        $_POST['type'], 
        !empty($_POST['po_id']) ? $_POST['po_id'] : 0,
        !empty($_POST['logistic_date']) ? $_POST['logistic_date'] : date('Y-m-d'),
        !empty($_POST['qty']) ? $_POST['qty'] : 0,
        !empty($_POST['pic_name']) ? $_POST['pic_name'] : NULL,
        !empty($_POST['pic_phone']) ? $_POST['pic_phone'] : NULL,
        !empty($_POST['delivery_address']) ? $_POST['delivery_address'] : NULL,
        !empty($_POST['courier']) ? $_POST['courier'] : NULL,
        !empty($_POST['awb']) ? $_POST['awb'] : NULL,
        !empty($_POST['status']) ? $_POST['status'] : 'On Process',
        !empty($_POST['received_date']) ? $_POST['received_date'] : NULL,
        !empty($_POST['receiver_name']) ? $_POST['receiver_name'] : NULL,
        !empty($_POST['notes']) ? $_POST['notes'] : NULL
    ];

    if ($db_type === 'pdo') {
        if($is_upd) {
            $params[] = $id;
            $db->prepare("UPDATE sim_tracking_logistics SET type=?, po_id=?, logistic_date=?, qty=?, pic_name=?, pic_phone=?, delivery_address=?, courier=?, awb=?, status=?, received_date=?, receiver_name=?, notes=? WHERE id=?")->execute($params);
        } else {
            $db->prepare("INSERT INTO sim_tracking_logistics (type, po_id, logistic_date, qty, pic_name, pic_phone, delivery_address, courier, awb, status, received_date, receiver_name, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
        }
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

// =======================================================================
// 4. PO MANAGEMENT (CLIENT & PROVIDER)
// =======================================================================

// A. Create & Update Standard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create' || $_POST['action'] === 'update')) {
    $is_upd = ($_POST['action'] === 'update');
    $id = $_POST['id'] ?? null;
    $type = $_POST['type'];

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
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        if($is_upd) mysqli_query($db, "UPDATE sim_tracking_po SET type=$v[0], company_id=$v[1], project_id=$v[2], manual_company_name=$v[3], manual_project_name=$v[4], batch_name=$v[5], link_client_po_id=$v[6], po_number=$v[7], po_date=$v[8], sim_qty=$v[9], po_file=$v[10] WHERE id='$id'");
        else mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    header("Location: sim_tracking_{$type}_po.php?msg=" . ($is_upd ? 'updated' : 'success')); exit;
}

// B. Create Provider from Client (NEW LOGIC)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_provider_from_client') {
    $type = 'provider';
    $link_client_po_id = $_POST['link_client_po_id']; // ID Asal
    
    // Info Provider
    $company_id = !empty($_POST['provider_company_id']) ? $_POST['provider_company_id'] : NULL;
    $m_comp = (empty($company_id) && !empty($_POST['manual_provider_name'])) ? $_POST['manual_provider_name'] : NULL;
    
    // Info PO
    $po_num = $_POST['provider_po_number'];
    $sim_qty = str_replace(',', '', $_POST['sim_qty']);
    $po_date = !empty($_POST['po_date']) ? $_POST['po_date'] : date('Y-m-d');
    $batch = !empty($_POST['batch_name']) ? $_POST['batch_name'] : NULL; // Bawaan dari Client
    
    // File
    $file = NULL;
    $new_file = uploadFile($_FILES['po_file'], 'provider');
    if ($new_file) $file = $new_file;

    // Simpan
    $params = [$type, $company_id, NULL, $m_comp, NULL, $batch, $link_client_po_id, $po_num, $po_date, $sim_qty, $file];

    if ($db_type === 'pdo') {
        $db->prepare("INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
    } else {
        $v = array_map(function($x) use ($db) { return $x===NULL?"NULL":"'".mysqli_real_escape_string($db,$x)."'"; }, $params);
        mysqli_query($db, "INSERT INTO sim_tracking_po (type, company_id, project_id, manual_company_name, manual_project_name, batch_name, link_client_po_id, po_number, po_date, sim_qty, po_file) VALUES (" . implode(',', $v) . ")");
    }
    
    // Redirect ke halaman Provider PO
    header("Location: sim_tracking_provider_po.php?msg=created_from_client"); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id']; $type = $_GET['type'];
    if ($db_type === 'pdo') $db->prepare("DELETE FROM sim_tracking_po WHERE id=?")->execute([$id]); else mysqli_query($db, "DELETE FROM sim_tracking_po WHERE id='$id'");
    header("Location: sim_tracking_{$type}_po.php?msg=deleted"); exit;
}

// =======================================================================
// 5. ACTIVATION MANAGEMENT
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_activation' || $_POST['action'] === 'update_activation')) {
    $is_upd = ($_POST['action'] === 'update_activation');
    $id = $_POST['id'] ?? null;

    $params = [
        !empty($_POST['company_id']) ? $_POST['company_id'] : NULL,
        !empty($_POST['project_id']) ? $_POST['project_id'] : NULL,
        $_POST['po_batch_sim'] ?? NULL,
        !empty($_POST['po_provider_id']) ? $_POST['po_provider_id'] : NULL,
        !empty($_POST['po_client_id']) ? $_POST['po_client_id'] : NULL,
        $_POST['total_sim'] ?? 0,
        $_POST['active_qty'] ?? 0,
        $_POST['inactive_qty'] ?? 0,
        $_POST['activation_date'] ?? NULL,
        $_POST['activation_qty'] ?? 0,
        $_POST['activation_batch'] ?? NULL
    ];

    if ($db_type === 'pdo') {
        if ($is_upd) {
            $params[] = $id;
            $db->prepare("UPDATE sim_activations SET company_id=?, project_id=?, po_batch_sim=?, po_provider_id=?, po_client_id=?, total_sim=?, active_qty=?, inactive_qty=?, activation_date=?, activation_qty=?, activation_batch=? WHERE id=?")->execute($params);
        } else {
            $db->prepare("INSERT INTO sim_activations (company_id, project_id, po_batch_sim, po_provider_id, po_client_id, total_sim, active_qty, inactive_qty, activation_date, activation_qty, activation_batch) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
        }
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

// =======================================================================
// 6. TERMINATION MANAGEMENT
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_termination' || $_POST['action'] === 'update_termination')) {
    $is_upd = ($_POST['action'] === 'update_termination');
    $id = $_POST['id'] ?? null;

    $params = [
        !empty($_POST['company_id']) ? $_POST['company_id'] : NULL,
        !empty($_POST['project_id']) ? $_POST['project_id'] : NULL,
        $_POST['po_batch_sim'] ?? NULL,
        !empty($_POST['po_provider_id']) ? $_POST['po_provider_id'] : NULL,
        !empty($_POST['po_client_id']) ? $_POST['po_client_id'] : NULL,
        $_POST['total_sim'] ?? 0,
        $_POST['terminated_qty'] ?? 0,
        $_POST['unterminated_qty'] ?? 0,
        $_POST['termination_date'] ?? NULL,
        $_POST['termination_qty'] ?? 0,
        $_POST['termination_batch'] ?? NULL
    ];

    if ($db_type === 'pdo') {
        if ($is_upd) {
            $params[] = $id;
            $db->prepare("UPDATE sim_terminations SET company_id=?, project_id=?, po_batch_sim=?, po_provider_id=?, po_client_id=?, total_sim=?, terminated_qty=?, unterminated_qty=?, termination_date=?, termination_qty=?, termination_batch=? WHERE id=?")->execute($params);
        } else {
            $db->prepare("INSERT INTO sim_terminations (company_id, project_id, po_batch_sim, po_provider_id, po_client_id, total_sim, terminated_qty, unterminated_qty, termination_date, termination_qty, termination_batch) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute($params);
        }
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

// =======================================================================
// 7. MASTER COMPANY
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $name = trim($_POST['company_name']); $type = $_POST['company_type']; $red = $_POST['redirect'];
    if($db_type==='pdo') $db->prepare("INSERT INTO companies (company_name, company_type) VALUES (?, ?)")->execute([$name, $type]);
    else mysqli_query($db, "INSERT INTO companies (company_name, company_type) VALUES ('$name', '$type')");
    header("Location: sim_tracking_{$red}_po.php?msg=success"); exit;
}
?>