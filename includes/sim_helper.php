<?php
// =======================================================================
// FILE: includes/sim_helper.php
// FUNGSI: Koneksi DB, Auto-Repair Table, dan Reader Excel
// =======================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);

// 1. KONEKSI DB UNIVERSAL
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }

if (!$db && defined('DB_HOST')) { 
    try { 
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); 
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $db_type = 'pdo'; 
    } catch (Exception $e) {} 
}

// 2. HELPER JSON RESPONSE
function jsonResponse($status, $message, $data = []) {
    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// 3. AUTO REPAIR TABLE (Inventory)
if($db) {
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
}

// 4. SMART SPREADSHEET READER
function readSpreadsheet($tmpPath, $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $data = [];
    if ($ext === 'csv') {
        if (($handle = fopen($tmpPath, "r")) !== FALSE) {
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

function findIdx($headers, $keys) {
    foreach ($headers as $i => $v) { 
        $clean = strtolower(trim(str_replace([' ','_','-','.'],'',$v)));
        if (in_array($clean, $keys)) return $i; 
    }
    return false;
}

function uploadFileLegacy($file, $prefix) {
    if(isset($file) && $file['error']===0) {
        $dir = __DIR__ . "/../uploads/po/"; 
        if(!is_dir($dir)) mkdir($dir,0755,true);
        $name = $prefix."_".time()."_".rand(100,999).".".pathinfo($file['name'], PATHINFO_EXTENSION);
        if(move_uploaded_file($file['tmp_name'], $dir.$name)) return $name;
    } return null;
}
?>