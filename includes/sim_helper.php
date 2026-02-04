<?php
// =======================================================================
// FILE: includes/sim_helper.php
// FUNGSI: Library Pendukung (Database & Excel Reader)
// =======================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);

// 1. KONEKSI DATABASE UNIVERSAL
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { 
    if (isset($GLOBALS[$var])) { 
        if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } 
        if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } 
    } 
}

if (!$db && defined('DB_HOST')) { 
    try { 
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); 
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $db_type = 'pdo'; 
    } catch (Exception $e) {} 
}

// 2. HELPER JSON RESPONSE (Untuk AJAX Progress Bar)
function jsonResponse($status, $message, $data = []) {
    ob_clean(); // Bersihkan output buffer
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// 3. SMART EXCEL/CSV READER
function readSpreadsheet($tmpPath, $originalName) {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $data = [];
    
    // CSV Handler
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

// 4. FIND COLUMN INDEX
function findIdx($headers, $keys) {
    foreach ($headers as $i => $v) { 
        $clean = strtolower(trim(str_replace([' ','_','-','.'],'',$v)));
        if (in_array($clean, $keys)) return $i; 
    }
    return false;
}

// 5. LEGACY UPLOAD FILE (Untuk fitur PO lama)
function uploadFileLegacy($file, $prefix) {
    if(isset($file) && $file['error']===0) {
        $dir = __DIR__ . "/../uploads/po/"; 
        if(!is_dir($dir)) mkdir($dir,0755,true);
        $name = $prefix."_".time()."_".rand(100,999).".".pathinfo($file['name'], PATHINFO_EXTENSION);
        if(move_uploaded_file($file['tmp_name'], $dir.$name)) return $name;
    } return null;
}
?>