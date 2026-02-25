<?php
/*
 File: api_sim_upload.php
 Deskripsi: Backend API Final (Search Batch Dropdown Added)
*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 0); 

header('Content-Type: application/json');

require_once 'includes/config.php';
require_once 'includes/functions.php';

$hasExcelLib = file_exists(__DIR__ . '/vendor/autoload.php');
if ($hasExcelLib) { require_once __DIR__ . '/vendor/autoload.php'; }
use PhpOffice\PhpSpreadsheet\IOFactory;

function sendResponse($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $max = ini_get('post_max_size');
    sendResponse(false, "File too large! Server limit: $max.");
}

try {
    $db = db_connect();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid Request");
    if (!isset($_POST['action'])) throw new Exception("Action missing");

    // ==================================================================
    // 1. SEARCH ENGINE
    // ==================================================================
    if ($_POST['action'] == 'search_sim_data') {
        $general   = trim($_POST['general_search'] ?? '');
        $bulk      = trim($_POST['bulk_search'] ?? '');
        $batchName = trim($_POST['batch_search'] ?? ''); 
        $companyId = $_POST['company_id'] ?? '';
        $projectId = $_POST['project_id'] ?? '';
        
        $sql = "SELECT d.*, b.batch_name, c.company_name, p.project_name 
                FROM sim_details d
                JOIN sim_batches b ON d.sim_batch_id = b.id
                LEFT JOIN companies c ON b.company_id = c.id
                LEFT JOIN sim_projects p ON b.project_id = p.id
                WHERE 1=1";
        $params = [];

        if (!empty($companyId)) { $sql .= " AND b.company_id = ?"; $params[] = $companyId; }
        if (!empty($projectId)) { $sql .= " AND b.project_id = ?"; $params[] = $projectId; }
        
        // Filter Batch Name (Exact Match or Like)
        if (!empty($batchName)) {
            $sql .= " AND b.batch_name = ?";
            $params[] = $batchName;
        }
        
        if (!empty($general)) {
            $sql .= " AND (d.msisdn LIKE ? OR d.imsi LIKE ? OR d.iccid LIKE ? OR d.sn LIKE ?)";
            $term = "%$general%"; array_push($params, $term, $term, $term, $term);
        }

        if (!empty($bulk)) {
            $numbers = preg_split('/[\s,]+/', $bulk, -1, PREG_SPLIT_NO_EMPTY);
            if (count($numbers) > 0) {
                $numbers = array_slice($numbers, 0, 1000); 
                $placeholders = implode(',', array_fill(0, count($numbers), '?'));
                $sql .= " AND (d.msisdn IN ($placeholders) OR d.iccid IN ($placeholders))";
                $params = array_merge($params, $numbers, $numbers);
            }
        }

        $sql .= " ORDER BY d.id DESC LIMIT 1000"; 
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, "Found " . count($results), ['data' => $results]);
    }

    // ==================================================================
    // 2. GET DATA HELPERS (PROJECTS & BATCHES)
    // ==================================================================
    
    elseif ($_POST['action'] == 'get_projects') {
        $stmt = $db->prepare("SELECT id, project_name FROM sim_projects WHERE company_id = ? ORDER BY project_name ASC");
        $stmt->execute([$_POST['company_id']]);
        sendResponse(true, 'OK', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    
    // --- FITUR BARU: GET BATCH LIST FOR DROPDOWN ---
    elseif ($_POST['action'] == 'get_batches') {
        $sql = "SELECT DISTINCT batch_name FROM sim_batches WHERE 1=1";
        $params = [];
        
        if (!empty($_POST['company_id'])) {
            $sql .= " AND company_id = ?";
            $params[] = $_POST['company_id'];
        }
        if (!empty($_POST['project_id'])) {
            $sql .= " AND project_id = ?";
            $params[] = $_POST['project_id'];
        }
        
        $sql .= " ORDER BY id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        sendResponse(true, 'OK', ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // ==================================================================
    // 3. SERVER SIDE VIEW
    // ==================================================================
    elseif ($_POST['action'] == 'get_batch_details_server_side') {
        $batchId = $_POST['batch_id']; 
        $draw = $_POST['draw'] ?? 1; 
        $start = $_POST['start'] ?? 0; 
        $len = $_POST['length'] ?? 50; 
        $search = $_POST['search']['value'] ?? '';

        $cnt = $db->prepare("SELECT COUNT(*) FROM sim_details WHERE sim_batch_id=?");
        $cnt->execute([$batchId]); 
        $total = $cnt->fetchColumn();

        $sql = "SELECT msisdn, imsi, sn, iccid FROM sim_details WHERE sim_batch_id=?";
        $params = [$batchId];
        
        if ($search) { 
            $sql .= " AND (msisdn LIKE ? OR imsi LIKE ? OR sn LIKE ? OR iccid LIKE ?)";
            $l = "%$search%"; array_push($params, $l, $l, $l, $l); 
        }
        
        $flt = $db->prepare(str_replace("SELECT msisdn, imsi, sn, iccid", "SELECT COUNT(*)", $sql));
        $flt->execute($params);
        $filtered = $flt->fetchColumn();
        
        $sql .= " ORDER BY id ASC LIMIT " . intval($len) . " OFFSET " . intval($start);
        
        $q = $db->prepare($sql);
        $q->execute($params);
        $data = $q->fetchAll(PDO::FETCH_ASSOC);

        $output = [];
        $no = $start + 1;
        foreach ($data as $r) {
            $output[] = [$no++, $r['msisdn'], $r['imsi'], $r['sn'], $r['iccid']];
        }

        echo json_encode([
            "draw" => intval($draw),
            "recordsTotal" => intval($total),
            "recordsFiltered" => intval($filtered),
            "data" => $output
        ]);
        exit;
    }

    // ==================================================================
    // 4. UPLOAD & MANAGE (CRUD)
    // ==================================================================
    elseif ($_POST['action'] == 'get_batch_header') {
        $stmt=$db->prepare("SELECT * FROM sim_batches WHERE id=?"); $stmt->execute([$_POST['id']]);
        $d=$stmt->fetch(PDO::FETCH_ASSOC); if($d) sendResponse(true,'Found',['data'=>$d]); else sendResponse(false,'Not found');
    }
    elseif ($_POST['action'] == 'edit_batch') {
        if(empty($_POST['id'])||empty($_POST['batch_name'])) throw new Exception("Name required");
        $id=$_POST['id']; $dir='uploads/po/'; if(!is_dir($dir)) mkdir($dir,0777,true);
        $sql="UPDATE sim_batches SET company_id=?, project_id=?, batch_name=?, po_client_number=?, po_linksfield_number=? WHERE id=?";
        $db->prepare($sql)->execute([$_POST['company_id'],$_POST['project_id'],$_POST['batch_name'],$_POST['po_client_number'],$_POST['po_linksfield_number'],$id]);
        if(!empty($_FILES['po_client_file']['name'])){ $p=$dir.time().'_c.'.pathinfo($_FILES['po_client_file']['name'],4); if(move_uploaded_file($_FILES['po_client_file']['tmp_name'],$p)) $db->prepare("UPDATE sim_batches SET po_client_file=? WHERE id=?")->execute([$p,$id]); }
        if(!empty($_FILES['po_linksfield_file']['name'])){ $p=$dir.time().'_l.'.pathinfo($_FILES['po_linksfield_file']['name'],4); if(move_uploaded_file($_FILES['po_linksfield_file']['tmp_name'],$p)) $db->prepare("UPDATE sim_batches SET po_linksfield_file=? WHERE id=?")->execute([$p,$id]); }
        sendResponse(true,'Updated');
    }
    elseif ($_POST['action'] == 'save_batch_header') {
        $dir='uploads/po/'; if(!is_dir($dir)) mkdir($dir,0777,true);
        $pc=''; if(!empty($_FILES['po_client_file']['name'])){$pc=$dir.time().'_c.'.pathinfo($_FILES['po_client_file']['name'],4);move_uploaded_file($_FILES['po_client_file']['tmp_name'],$pc);}
        $pl=''; if(!empty($_FILES['po_linksfield_file']['name'])){$pl=$dir.time().'_l.'.pathinfo($_FILES['po_linksfield_file']['name'],4);move_uploaded_file($_FILES['po_linksfield_file']['tmp_name'],$pl);}
        $qty = $_POST['quantity'] ?? 0;
        $db->prepare("INSERT INTO sim_batches (company_id,project_id,batch_name,quantity,po_client_number,po_client_file,po_linksfield_number,po_linksfield_file) VALUES (?,?,?,?,?,?,?,?)")->execute([$_POST['company_id'],$_POST['project_id'],$_POST['batch_name'],$qty,$_POST['po_client_number'],$pc,$_POST['po_linksfield_number'],$pl]);
        sendResponse(true,'OK',['batch_id'=>$db->lastInsertId()]);
    }
    elseif ($_POST['action'] == 'process_chunk') {
        $bid=$_POST['batch_id']; $csv=$_POST['csv_path']; $start=(int)$_POST['start_line']; $size=(int)$_POST['chunk_size'];
        if(!file_exists($csv)) throw new Exception("CSV Expired");
        $f=new SplFileObject($csv,'r'); $f->setFlags(SplFileObject::READ_CSV|SplFileObject::SKIP_EMPTY); $f->seek($start+1);
        $c=0; $vals=[]; $par=[];
        while(!$f->eof() && $c<$size){
            $r=$f->current(); if(!empty($r)&&(isset($r[0])||isset($r[3]))){ $vals[]="(?,?,?,?,?)"; array_push($par,$bid,trim($r[0]??''),trim($r[1]??''),trim($r[2]??''),trim($r[3]??'')); $c++; } $f->next();
        }
        if($c>0){ $sql="INSERT INTO sim_details (sim_batch_id,msisdn,imsi,sn,iccid) VALUES ".implode(',',$vals); $db->prepare($sql)->execute($par); }
        sendResponse(true,'OK',['processed_count'=>$c]);
    }
    elseif ($_POST['action'] == 'preview_excel') {
        if(!$hasExcelLib) throw new Exception("Lib Missing"); if(empty($_FILES['excel_file'])) throw new Exception("No File");
        $tmp='uploads/temp/raw_'.uniqid(); move_uploaded_file($_FILES['excel_file']['tmp_name'], $tmp);
        $r=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp); $r->setReadDataOnly(true); $s=$r->load($tmp);
        $csv='uploads/temp/proc_'.uniqid().'.csv'; (new \PhpOffice\PhpSpreadsheet\Writer\Csv($s))->save($csv);
        unset($s); unlink($tmp);
        $f=new SplFileObject($csv,'r'); $f->setFlags(SplFileObject::READ_CSV|SplFileObject::SKIP_EMPTY);
        $h=$f->current(); $poc=-1; $pol=-1; if($h) foreach($h as $i=>$v) { $v=strtolower(trim($v)); if($v=='po number from client')$poc=$i; if($v=='po number from linksfield to telkomsel')$pol=$i; }
        $f->next(); $prev=[]; $vpc=''; $vpl=''; $row=0;
        while(!$f->eof()){ 
            $r=$f->current(); if(!empty($r)&&(isset($r[0])||isset($r[3]))){ if($row==0){ if($poc>=0)$vpc=$r[$poc]; if($pol>=0)$vpl=$r[$pol]; } if($row<50)$prev[]=['msisdn'=>$r[0]??'','imsi'=>$r[1]??'','sn'=>$r[2]??'','iccid'=>$r[3]??'']; $row++; } $f->next(); 
        }
        sendResponse(true,'OK',['quantity'=>$row,'temp_csv_path'=>$csv,'preview_rows'=>$prev,'po_client_val'=>$vpc,'po_lf_val'=>$vpl]);
    }
    elseif ($_POST['action'] == 'delete_temp_file') { if(file_exists($_POST['csv_path'])) @unlink($_POST['csv_path']); sendResponse(true,'OK'); }
    elseif ($_POST['action'] == 'get_next_batch_name') { $s=$db->prepare("SELECT COUNT(*) as total FROM sim_batches WHERE project_id=?"); $s->execute([$_POST['project_id']]); $r=$s->fetch(PDO::FETCH_ASSOC); sendResponse(true,'OK',['next_name'=>"Batch ".($r['total']+1)]); }
    elseif ($_POST['action'] == 'add_company') { $db->prepare("INSERT INTO companies (company_name) VALUES (?)")->execute([$_POST['name']]); sendResponse(true,'OK'); }
    elseif ($_POST['action'] == 'add_project') { $k='K'.bin2hex(random_bytes(4)); $db->prepare("INSERT INTO sim_projects (company_id,project_name,subscription_key) VALUES (?,?,?)")->execute([$_POST['company_id'],$_POST['name'],$k]); sendResponse(true,'OK'); }
    elseif ($_POST['action'] == 'delete_batch') { $db->prepare("DELETE FROM sim_batches WHERE id=?")->execute([$_POST['id']]); sendResponse(true,'Deleted'); }
    elseif ($_POST['action'] == 'delete_company') { $db->prepare("DELETE FROM companies WHERE id=?")->execute([$_POST['id']]); sendResponse(true,'Deleted'); }
    elseif ($_POST['action'] == 'delete_project') { $db->prepare("DELETE FROM sim_projects WHERE id=?")->execute([$_POST['id']]); sendResponse(true,'Deleted'); }

    else { sendResponse(false, "Unknown Action"); }

} catch (Exception $e) { sendResponse(false, $e->getMessage()); }
?>