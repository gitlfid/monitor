<?php
// =========================================================================
// FILE: sim_tracking_status.php
// DESC: Frontend Dashboard (Fixed Responsive Layout & Footer)
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; 

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 1. FETCH DATA UTAMA DASHBOARD ---
$list_providers_new = [];
$dashboard_data = [];
$activations_raw = [];
$terminations_raw = [];
$total_sys_sims = 0; $total_sys_act = 0; $total_sys_term = 0;

if($db) {
    // Dropdown Upload
    try { 
        $list_providers_new = $db->query("SELECT id, po_number, sim_qty FROM sim_tracking_po WHERE type='provider' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); 
    } catch(Exception $e){}
    
    // Data Dashboard Utama
    $sql_main = "SELECT 
                    po.id as po_id, po.po_number as provider_po, po.batch_name as batch_name, po.sim_qty as total_pool,
                    client_po.po_number as client_po, c.company_name, p.project_name,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Available') as cnt_avail,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Active') as cnt_active,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Terminated') as cnt_term,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id) as total_uploaded
                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider' 
                HAVING total_uploaded > 0 
                ORDER BY po.id DESC";
    try { 
        $dashboard_data = $db->query($sql_main)->fetchAll(PDO::FETCH_ASSOC); 
        foreach($dashboard_data as $d) {
            $total_sys_sims += $d['total_uploaded'];
            $total_sys_act += $d['cnt_active'];
            $total_sys_term += $d['cnt_term'];
        }
    } catch(Exception $e){}

    // Data Grafik
    try {
        $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Proses Data Grafik
$cd_a=[]; $cd_t=[]; $lbls=[]; $s_a=[]; $s_t=[];
foreach($activations_raw as $r){ $d=date('Y-m-d', strtotime($r['activation_date'])); if(!isset($cd_a[$d]))$cd_a[$d]=0; $cd_a[$d]+=$r['active_qty']; }
foreach($terminations_raw as $r){ $d=date('Y-m-d', strtotime($r['termination_date'])); if(!isset($cd_t[$d]))$cd_t[$d]=0; $cd_t[$d]+=$r['terminated_qty']; }
$dates = array_unique(array_merge(array_keys($cd_a), array_keys($cd_t))); sort($dates);
foreach($dates as $d){ $lbls[]=date('d M', strtotime($d)); $s_a[]=$cd_a[$d]??0; $s_t[]=$cd_t[$d]??0; }
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
    
    /* CARDS */
    .card-pro { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; overflow: hidden; }
    .stat-card { padding: 24px; display: flex; align-items: center; gap: 20px; }
    .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
    
    /* TABLE */
    .table-pro th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; }
    .table-pro td { padding: 18px 20px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .progress-track { background: #e2e8f0; border-radius: 4px; height: 8px; overflow: hidden; display: flex; width: 100%; margin-top: 8px; }
    .bar-seg { height: 100%; transition: width 0.6s ease; }
    .bg-a { background: #10b981; } .bg-t { background: #ef4444; } .bg-v { background: #cbd5e1; }
    .text-act { color: #10b981; } .text-term { color: #ef4444; }
    
    /* BUTTONS */
    .btn-primary-pro { background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: white; transition: 0.2s; text-decoration: none; display: inline-block; }
    .btn-primary-pro:hover { background: #4338ca; color: white; transform: translateY(-1px); }
    .btn-act { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-size: 0.75rem; font-weight: 600; border-radius: 6px; padding: 6px 12px; width: 100%; display: block; margin-bottom: 4px; transition: 0.2s; }
    .btn-act:hover { background: #166534; color: #fff; }
    .btn-term { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 0.75rem; font-weight: 600; border-radius: 6px; padding: 6px 12px; width: 100%; display: block; margin-bottom: 4px; transition: 0.2s; }
    .btn-term:hover { background: #991b1b; color: #fff; }
    .btn-log { background: #fff; color: #64748b; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 600; border-radius: 6px; padding: 6px 12px; width: 100%; display: block; transition: 0.2s; }
    .btn-log:hover { background: #f1f5f9; }

    /* LAYOUT MODAL FIXED (FULL HEIGHT RESPONSIVE) */
    .modal-content-full { height: 85vh; display: flex; flex-direction: column; overflow: hidden; }
    .mgr-layout { display: flex; flex: 1; overflow: hidden; min-height: 0; } /* FIX: Flex Grow to fill space */
    
    .mgr-left { width: 30%; background: #f8fafc; border-right: 1px solid #e2e8f0; padding: 20px; display: flex; flex-direction: column; overflow-y: auto; }
    .mgr-right { width: 70%; display: flex; flex-direction: column; background: #fff; min-width: 0; }
    
    /* SUMMARY VISUAL CARDS */
    .mgr-stats-row { display: flex; border-bottom: 1px solid #e2e8f0; background: #fff; flex-shrink: 0; }
    .mgr-stat-item { flex: 1; padding: 15px; text-align: center; border-right: 1px solid #e2e8f0; }
    .mgr-stat-item:last-child { border-right: none; }
    .mgr-stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 5px; }
    .mgr-stat-val { font-size: 1.25rem; font-weight: 800; color: #334155; }
    .val-act { color: #10b981; } .val-term { color: #ef4444; }

    /* CONTENT LIST (SCROLLABLE AREA) */
    .mgr-list-box { flex-grow: 1; overflow-y: auto; position: relative; min-height: 0; } 
    
    /* FOOTER FIXED (RESPONSIVE WRAP) */
    .mgr-footer { 
        padding: 15px 20px; 
        border-top: 1px solid #e2e8f0; 
        background: #fff; 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        flex-shrink: 0; /* Prevent shrinking */
        flex-wrap: wrap; /* Allow wrapping on small screens */
        gap: 10px;
    }

    .sim-item { padding: 10px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: 0.1s; }
    .sim-item:hover { background: #f8fafc; }
    .sim-item.selected { background: #eff6ff; border-left: 4px solid #4f46e5; }
    
    .status-badge { font-size: 0.65rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase; margin-left: 8px; letter-spacing: 0.3px; }
    .sb-avail { background: #f1f5f9; color: #64748b; }
    .sb-active { background: #dcfce7; color: #166534; }
    .sb-term { background: #fee2e2; color: #991b1b; }

    /* UPLOAD & TOAST */
    .upload-zone { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 8px; text-align: center; padding: 30px; cursor: pointer; position: relative; transition: 0.2s; }
    .upload-zone:hover { border-color: #4f46e5; background: #eef2ff; }
    .prog-cont { display: none; margin-top: 20px; }
    .prog-bar { height: 10px; background: #4f46e5; width: 0%; transition: width 0.2s; border-radius: 5px; }
    #toastCont { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .toast-item { min-width: 300px; padding: 15px; border-radius: 8px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 10px; border-left: 4px solid; display: flex; gap: 12px; align-items: center; animation: slideIn 0.3s ease; }
    .toast-success { border-color: #10b981; } .toast-error { border-color: #ef4444; }
    @keyframes slideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
    
    /* LOGS */
    .log-summary { display: flex; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
    .log-stat-box { flex: 1; text-align: center; border-right: 1px solid #e2e8f0; }
    .log-stat-box:last-child { border-right: none; }
</style>

<div id="toastCont"></div>

<div class="d-flex justify-content-between align-items-center px-4 py-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">SIM Lifecycle</h3>
        <p class="text-muted small m-0">Inventory Management Dashboard</p>
    </div>
    <button class="btn btn-primary-pro" onclick="openUploadModal()">
        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Batch
    </button>
</div>

<div class="row g-4 px-4 mb-4">
    <div class="col-md-4"><div class="card-pro stat-card"><div class="stat-icon bg-light text-primary"><i class="bi bi-sim"></i></div><div><h6 class="text-muted small fw-bold mb-0">TOTAL INVENTORY</h6><h3 class="fw-bold mb-0"><?=number_format($total_sys_sims)?></h3></div></div></div>
    <div class="col-md-4"><div class="card-pro stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div><div><h6 class="text-muted small fw-bold mb-0">ACTIVE SIMS</h6><h3 class="fw-bold mb-0"><?=number_format($total_sys_act)?></h3></div></div></div>
    <div class="col-md-4"><div class="card-pro stat-card"><div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-x-circle"></i></div><div><h6 class="text-muted small fw-bold mb-0">TERMINATED</h6><h3 class="fw-bold mb-0"><?=number_format($total_sys_term)?></h3></div></div></div>
</div>

<div class="px-4 mb-4">
    <div class="card-pro p-4">
        <h6 class="fw-bold text-dark mb-3">Activity Trends</h6>
        <div id="lifecycleChart" style="height: 280px;"></div>
    </div>
</div>

<div class="px-4 pb-5">
    <div class="card-pro">
        <div class="table-responsive">
            <table class="table w-100 mb-0 table-pro align-middle">
                <thead><tr><th>Entity Info</th><th>Source</th><th>Inventory Status</th><th class="text-center">Manage</th></tr></thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?><tr><td colspan="4" class="text-center py-5 text-muted">No data available.</td></tr><?php else: ?>
                    <?php foreach($dashboard_data as $row): 
                        $tot = (int)$row['total_uploaded']; $act = (int)$row['cnt_active']; $term = (int)$row['cnt_term']; $avail = (int)$row['cnt_avail'];
                        $pA = ($tot>0)?($act/$tot)*100:0; $pT = ($tot>0)?($term/$tot)*100:0; $pV = 100-$pA-$pT;
                        
                        $stats = ['total'=>$tot, 'active'=>$act, 'terminated'=>$term, 'available'=>$avail];
                        $json = htmlspecialchars(json_encode(['id'=>$row['po_id'], 'po'=>$row['provider_po'], 'batch'=>$row['batch_name'], 'comp'=>$row['company_name'], 'stats'=>$stats]), ENT_QUOTES);
                    ?>
                    <tr>
                        <td><div class="fw-bold text-dark"><?=e($row['company_name'])?></div><span class="badge bg-light text-primary border mt-1"><?=e($row['project_name'])?></span></td>
                        <td><div class="fw-bold text-dark"><?=e($row['provider_po'])?></div><div class="small text-muted"><?=e($row['batch_name'])?></div></td>
                        <td>
                            <div class="d-flex justify-content-between small fw-bold mb-1"><span>Total: <?=number_format($tot)?></span><span class="text-success">Avail: <?=number_format($avail)?></span></div>
                            <div class="progress-track"><div class="bar-seg bg-a" style="width:<?=$pA?>%"></div><div class="bar-seg bg-t" style="width:<?=$pT?>%"></div><div class="bar-seg bg-v" style="width:<?=$pV?>%"></div></div>
                        </td>
                        <td>
                            <button class="btn-act" onclick='openMgr(<?=$json?>,"activate")'>Manage Activation</button>
                            <button class="btn-term" onclick='openMgr(<?=$json?>,"terminate")'>Manage Termination</button>
                            <button class="btn-log" onclick='fetchLogs(<?=$json?>)'>Logs</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMgr" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow modal-content-full">
            <div class="modal-header bg-white border-bottom">
                <div><h6 class="modal-title fw-bold" id="mgrTitle">Manage SIMs</h6><div class="small text-muted" id="mgrSubtitle">-</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="mgr-layout">
                <div class="mgr-left">
                    <label class="small fw-bold text-muted mb-2">BULK SEARCH</label>
                    <textarea id="sKey" class="form-control mb-3 flex-grow-1" style="resize:none" placeholder="Paste multiple MSISDNs here...&#10;0812xxx&#10;0813xxx"></textarea>
                    
                    <button class="btn btn-dark w-100 fw-bold mb-2" onclick="doSearch()">
                        <i class="bi bi-search me-2"></i> Search & Filter
                    </button>
                    
                    <div class="alert alert-info small mb-0 p-2 border-0 bg-info-subtle">
                        <i class="bi bi-info-circle me-1"></i> <span id="hintText">Use this to find specific SIMs.</span>
                    </div>
                </div>
                
                <div class="mgr-right">
                    <div class="mgr-stats-row">
                        <div class="mgr-stat-item">
                            <div class="mgr-stat-label">Total Inventory</div>
                            <div class="mgr-stat-val" id="stTotal">-</div>
                        </div>
                        <div class="mgr-stat-item">
                            <div class="mgr-stat-label text-success">Active</div>
                            <div class="mgr-stat-val val-act" id="stActive">-</div>
                        </div>
                        <div class="mgr-stat-item">
                            <div class="mgr-stat-label text-danger">Terminated</div>
                            <div class="mgr-stat-val val-term" id="stTerm">-</div>
                        </div>
                    </div>

                    <div class="mgr-list-box" id="sList">
                        <div class="text-center py-5 mt-5">
                            <i class="bi bi-search display-4 text-light"></i>
                            <p class="mt-3">Loading data...</p>
                        </div>
                    </div>
                    
                    <div class="mgr-footer">
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="changePage(-1)" id="btnPrev">Previous</button>
                            <span class="small fw-bold mx-2 text-nowrap" id="pageInfo">Page 1</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="changePage(1)" id="btnNext">Next</button>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                            <div class="text-end lh-1 me-2 d-none d-md-block">
                                <div class="small text-muted">Selected</div>
                                <div class="fw-bold text-primary h5 m-0" id="selCount">0</div>
                            </div>
                            <input type="date" id="actDate" class="form-control form-control-sm w-auto" value="<?=date('Y-m-d')?>">
                            <button class="btn px-4 fw-bold text-nowrap" id="btnProc" disabled onclick="doProc()">Execute</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUpload" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold">Upload Master Data</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formUploadMaster">
                    <input type="hidden" name="action" value="upload_master_bulk">
                    <input type="hidden" name="is_ajax" value="1">
                    
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Select Provider PO</label>
                        <select name="po_provider_id" id="poSelect" class="form-select" required onchange="fetchBatchInfo(this.value)">
                            <option value="">-- Choose PO --</option>
                            <?php foreach($list_providers_new as $p): ?>
                                <option value="<?=$p['id']?>"><?=$p['po_number']?> (Alloc: <?=number_format($p['sim_qty'])?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="upload-zone" id="dropZone">
                            <input type="file" name="upload_file" id="fIn" style="position:absolute;width:100%;height:100%;top:0;left:0;opacity:0;cursor:pointer" onchange="$('#fTxt').text(this.files[0].name).addClass('text-primary')">
                            <i class="bi bi-cloud-arrow-up display-4 text-secondary"></i>
                            <div id="fTxt" class="mt-2 fw-bold">Click/Drag CSV or Excel Here</div>
                            <div class="small text-muted">Header Required: <code>MSISDN</code></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col"><label class="small fw-bold text-muted">Batch Name</label><input type="text" name="activation_batch" id="batchInput" class="form-control bg-light text-secondary" placeholder="Select PO first..." readonly required></div>
                        <div class="col"><label class="small fw-bold text-muted">Date</label><input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                    </div>

                    <div class="mb-3" id="progCont" style="display:none;">
                        <div class="d-flex justify-content-between small fw-bold mb-1"><span id="progText">Uploading...</span><span id="progPct">0%</span></div>
                        <div class="progress" style="height:10px"><div class="progress-bar bg-primary" id="progBar" style="width:0%"></div></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold" id="btnUp">Start Upload</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-white border-bottom pb-3"><div><h6 class="modal-title fw-bold" id="logTitle">History Logs</h6><div class="small text-muted" id="logSubtitle">-</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4"><div id="logStatsContainer"></div><h6 class="fw-bold text-secondary small mb-3 border-bottom pb-2">TRANSACTION HISTORY</h6><div id="logList" class="list-group list-group-flush"></div></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    function toast(t,m){ 
        let c=t==='success'?'toast-success':'toast-error'; let i=t==='success'?'bi-check-circle-fill text-success':'bi-exclamation-triangle-fill text-danger';
        let h=`<div class="toast-item ${c}"><i class="bi ${i} fs-4"></i><div><div class="fw-bold text-uppercase">${t}</div><div class="small text-muted">${m}</div></div></div>`;
        $('#toastCont').append(h); setTimeout(()=>$('#toastCont').children().first().remove(), 4000); 
    }

    // UPLOAD
    function openUploadModal() { $('#formUploadMaster')[0].reset(); $('#pgCont').hide(); $('#btnUp').prop('disabled',false).text('Start Upload'); $('#batchInput').val(''); new bootstrap.Modal(document.getElementById('modalUpload')).show(); }
    function fetchBatchInfo(id) { if(!id){$('#batchInput').val('');return;} $.post('process_sim_tracking.php', {action:'get_po_details', id:id}, function(res){ if(res.status==='success'){$('#batchInput').val(res.batch_name||'BATCH 1');} else{toast('error',res.message);$('#batchInput').val('');} },'json'); }
    $('#formUploadMaster').on('submit', function(e){ e.preventDefault(); let fd=new FormData(this); if($('#batchInput').val()===''){toast('error','Batch Name Missing');return;} $('#btnUp').prop('disabled',true); $('#pgCont').slideDown(); $.ajax({xhr:function(){var x=new window.XMLHttpRequest();x.upload.addEventListener("progress",e=>{if(e.lengthComputable){var p=Math.round((e.loaded/e.total)*100);$('#pgBar').css('width',p+'%');$('#pgTxt').text(p+'%');}},false);return x;},type:'POST',url:'process_sim_tracking.php',data:fd,contentType:false,processData:false,dataType:'json',success:function(r){if(r.status==='success'){$('#pgBar').addClass('bg-success');toast('success',r.message);setTimeout(()=>location.reload(),1500);}else{$('#pgBar').addClass('bg-danger');toast('error',r.message);$('#btnUp').prop('disabled',false).text('Retry');}},error:function(x){toast('error',x.responseText);$('#btnUp').prop('disabled',false).text('Retry');}}); });

    // MANAGER
    let cId=0, cMode='', cBatch='', cPage=1, totalPages=1, cSearch='';
    
    function openMgr(d,m) { 
        cId=d.id; cMode=m; cBatch=d.batch; cPage=1; cSearch='';
        $('#mgrTitle').text(m==='activate'?'Activate SIMs':'Terminate SIMs'); 
        $('#mgrSubtitle').text(`${d.comp} - ${d.po}`); 
        $('#sKey').val(''); 
        
        let btnClass = (m === 'activate') ? 'btn-success' : 'btn-danger';
        let btnText = (m === 'activate') ? 'Switch to Active' : 'Switch to Terminate';
        let hintMsg = (m === 'activate') ? 'Only displaying <b>Available</b> SIMs. Select to Activate.' : 'Only displaying <b>Active</b> SIMs. Select to Terminate.';
            
        $('#btnProc').removeClass('btn-primary-pro btn-success btn-danger').addClass(btnClass).text(btnText).prop('disabled',true);
        $('#hintText').html(hintMsg);
        $('#stTotal').text('-'); $('#stActive').text('-'); $('#stTerm').text('-');
        
        new bootstrap.Modal(document.getElementById('modalMgr')).show(); 
        loadData();
    }

    function doSearch() { cSearch = $('#sKey').val().trim(); cPage = 1; loadData(); }
    function changePage(d) { let n = cPage + d; if(n > 0 && n <= totalPages) { cPage = n; loadData(); } }

    function loadData() {
        $('#sList').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted">Loading data...</div></div>');
        $('#selCount').text(0);
        
        $.post('process_sim_tracking.php', {
            action:'fetch_sims', po_id:cId, search_bulk:cSearch, page:cPage, target_action: cMode
        }, function(res){
            if(res.status==='success'){
                if(res.stats) {
                    $('#stTotal').text(parseInt(res.stats.total||0).toLocaleString());
                    $('#stActive').text(parseInt(res.stats.active||0).toLocaleString());
                    $('#stTerm').text(parseInt(res.stats.terminated||0).toLocaleString());
                }
                let h=''; 
                if(res.data.length===0) {
                    let emptyMsg = (cMode === 'activate') ? 'No available SIMs found.' : 'No active SIMs found.';
                    h = `<div class="text-center py-5 text-muted">${emptyMsg}</div>`;
                } else {
                    res.data.forEach(s => { 
                        let badgeClass = s.status==='Active'?'sb-active':(s.status==='Terminated'?'sb-term':'sb-avail');
                        let dateInfo = s.activation_date ? `<small class="text-muted ms-2"><i class="bi bi-calendar-event"></i> ${s.activation_date}</small>` : '';
                        h += `<div class="sim-item" onclick="togRow(this)"><div><div class="fw-bold font-monospace">${s.msisdn} <span class="status-badge ${badgeClass}">${s.status}</span></div><div class="small text-muted">ICCID: ${s.iccid||'-'} ${dateInfo}</div></div><input type="checkbox" class="chk form-check-input" value="${s.id}" onclick="event.stopPropagation();upd()"></div>`; 
                    });
                }
                $('#sList').html(h);
                totalPages = res.total_pages;
                $('#pageInfo').text(`Page ${cPage} of ${totalPages} (${res.total_rows} items)`);
                $('#btnPrev').prop('disabled', cPage <= 1);
                $('#btnNext').prop('disabled', cPage >= totalPages);
            } else toast('error', res.message);
        },'json');
    }

    function togRow(el) { let c=$(el).find('.chk'); c.prop('checked', !c.prop('checked')); upd(); }
    function upd() { let n=$('.chk:checked').length; $('#selCount').text(n); $('#btnProc').prop('disabled', n===0); }

    function doProc() {
        let ids=[]; $('.chk:checked').each(function(){ids.push($(this).val())});
        if(!confirm(`Proceed to ${cMode.toUpperCase()} ${ids.length} selected items?`)) return;
        $('#btnProc').prop('disabled',true).text('Processing...');
        $.post('process_sim_tracking.php', {action:'process_bulk_sim_action', po_provider_id:cId, mode:cMode, sim_ids:ids, date_field:$('#actDate').val(), batch_name:cBatch}, function(r){
            if(r.status==='success'){ toast('success', r.message); setTimeout(()=>location.reload(),1500); } 
            else { toast('error', r.message); $('#btnProc').prop('disabled',false).text('Execute'); }
        },'json');
    }

    // LOGS
    function fetchLogs(d) {
        $('#logTitle').text("Logs: " + d.po); $('#logSubtitle').text(d.comp + " | " + d.batch);
        let st = d.stats || {total:0, active:0, term:0};
        $('#logStatsContainer').html(`<div class="log-summary"><div class="log-stat-box"><div class="log-stat-label">Total</div><div class="log-stat-value">${parseInt(st.total||0).toLocaleString()}</div></div><div class="log-stat-box"><div class="log-stat-label text-success">Active</div><div class="log-stat-value val-act">${parseInt(st.active||0).toLocaleString()}</div></div><div class="log-stat-box"><div class="log-stat-label text-danger">Terminated</div><div class="log-stat-value val-term">${parseInt(st.term||0).toLocaleString()}</div></div></div>`);
        $('#logList').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>'); 
        new bootstrap.Modal(document.getElementById('modalLog')).show();
        $.post('process_sim_tracking.php', {action:'fetch_logs', po_id:d.id}, function(r){
            if(r.status==='success'){
                let h=''; if(r.data.length===0) h='<div class="text-center p-4 text-muted">No logs found.</div>';
                else r.data.forEach(l=>{ 
                    let c=l.type==='Activation'?'text-success':'text-danger'; h+=`<div class="list-group-item border-0 border-bottom py-3 px-0"><div class="d-flex justify-content-between align-items-center"><div><div class="fw-bold ${c}">${l.type}</div><div class="small text-muted">${l.batch} | ${l.date}</div></div><span class="fw-bold fs-5 text-dark">${parseInt(l.qty).toLocaleString()}</span></div></div>`; 
                });
                $('#logList').html(h);
            } else $('#logList').html('Error loading logs.');
        },'json');
    }

    const lbl=<?php echo json_encode($lbls??[]); ?>; const sa=<?php echo json_encode($s_a??[]); ?>; const st=<?php echo json_encode($s_t??[]); ?>;
    if(lbl.length > 0) new ApexCharts(document.querySelector("#lifecycleChart"), {series:[{name:'Activations',data:sa},{name:'Terminations',data:st}], chart:{type:'area',height:280,toolbar:{show:false}}, colors:['#10b981','#ef4444'], stroke:{curve:'smooth',width:2}, xaxis:{categories:lbl}, grid:{borderColor:'#f1f5f9'}, fill:{type:'gradient', gradient:{shadeIntensity:1, opacityFrom:0.7, opacityTo:0.2, stops:[0, 90, 100]}}}).render();
    
    const dz=document.getElementById('dropZone'), fi=document.getElementById('fIn');
    ['dragenter','dragover'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.style.backgroundColor='#eef2ff';dz.style.borderColor='#4f46e5'},false));
    ['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.style.backgroundColor='#f8fafc';dz.style.borderColor='#cbd5e1'},false));
    fi.addEventListener('change',()=>{ if(fi.files.length>0) {$('#fTxt').text(fi.files[0].name).addClass('text-primary');} });
</script>

<?php require_once 'includes/footer.php'; ?>