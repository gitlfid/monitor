<?php
// =========================================================================
// FILE: sim_tracking_status.php
// UPDATE: Advanced Modal with Tabs, Pagination & Global Search
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; 

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 1. FETCH DATA UTAMA ---
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
    
    // Dashboard Data
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

    // Data Chart
    try {
        $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Chart Data Prep
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
    .btn-act { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-size: 0.75rem; font-weight: 600; border-radius: 6px; padding: 6px 12px; width: 100%; display: block; margin-bottom: 4px; transition: 0.2s; }
    .btn-act:hover { background: #166534; color: #fff; }
    .btn-term { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 0.75rem; font-weight: 600; border-radius: 6px; padding: 6px 12px; width: 100%; display: block; margin-bottom: 4px; transition: 0.2s; }
    .btn-term:hover { background: #991b1b; color: #fff; }
    .btn-log { background: #fff; color: #64748b; border: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 600; border-radius: 6px; padding: 6px 12px; width: 100%; display: block; transition: 0.2s; }
    .btn-log:hover { background: #f1f5f9; }

    /* MODAL MANAGER (ADVANCED) */
    .mgr-header-tabs { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 0 20px; }
    .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 600; padding: 15px 20px; border-bottom: 3px solid transparent; transition: 0.2s; }
    .nav-tabs .nav-link:hover { color: #4f46e5; }
    .nav-tabs .nav-link.active { color: #4f46e5; border-bottom-color: #4f46e5; background: transparent; }
    
    .mgr-search-bar { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .mgr-content-area { height: 55vh; overflow-y: auto; background: #fff; position: relative; }
    .mgr-footer-area { padding: 15px 20px; border-top: 1px solid #e2e8f0; background: #fff; display: flex; justify-content: space-between; align-items: center; }
    
    .sim-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid #f1f5f9; transition: 0.1s; }
    .sim-row:hover { background: #f8fafc; }
    .sim-row.selected { background: #eff6ff; border-left: 4px solid #4f46e5; }
    
    .status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }
    .status-active { background: #dcfce7; color: #166534; }
    .status-term { background: #fee2e2; color: #991b1b; }
    .status-avail { background: #f1f5f9; color: #64748b; }

    /* TOAST */
    #toastCont { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .toast-item { min-width: 300px; padding: 15px; border-radius: 8px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 10px; border-left: 4px solid; display: flex; gap: 12px; align-items: center; animation: slideIn 0.3s ease; }
    .toast-success { border-color: #10b981; } .toast-error { border-color: #ef4444; }
    @keyframes slideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
    
    .btn-primary-pro { background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: white; transition: 0.2s; text-decoration: none; display: inline-block; }
    .btn-primary-pro:hover { background: #4338ca; color: white; transform: translateY(-1px); }
    
    /* LOGS */
    .log-stats { background: #f8fafc; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; }
    .log-stat-item { text-align: center; flex: 1; border-right: 1px solid #e2e8f0; }
    .log-stat-item:last-child { border-right: none; }
    .log-stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .log-stat-value { font-size: 1.2rem; font-weight: 700; margin-top: 4px; color: #1e293b; }
    .val-act { color: #10b981; } .val-term { color: #ef4444; }
</style>

<div id="toastCont"></div>

<div class="d-flex justify-content-between align-items-center px-4 py-4">
    <div>
        <h3 class="fw-bold mb-0 text-dark">SIM Lifecycle</h3>
        <p class="text-muted small m-0">Inventory Management Dashboard</p>
    </div>
    <button class="btn btn-primary-pro" onclick="openUploadModal()">
        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload New Batch
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
                <thead>
                    <tr>
                        <th width="30%">Entity & Project</th>
                        <th width="20%">Provider Source</th>
                        <th width="35%">Status Distribution</th>
                        <th width="15%" class="text-center">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No data available. Please upload master data.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            $tot = (int)$row['total_uploaded']; $act = (int)$row['cnt_active']; $term = (int)$row['cnt_term']; $avail = (int)$row['cnt_avail'];
                            $pA = ($tot>0)?($act/$tot)*100:0; $pT = ($tot>0)?($term/$tot)*100:0; $pV = 100-$pA-$pT;
                            
                            $stats = ['total'=>$tot, 'active'=>$act, 'terminated'=>$term, 'available'=>$avail];
                            $json = htmlspecialchars(json_encode(['id'=>$row['po_id'], 'po'=>$row['provider_po'], 'batch'=>$row['batch_name'], 'comp'=>$row['company_name'], 'stats'=>$stats]), ENT_QUOTES);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?=e($row['company_name'])?></div>
                                <div class="small text-muted">Client PO: <?=e($row['client_po'])?:'-'?></div>
                                <span class="badge bg-light text-primary border mt-1"><?=e($row['project_name'])?></span>
                            </td>
                            <td>
                                <span class="badge bg-white border text-dark"><?=e($row['provider_po'])?></span>
                                <div class="small fw-bold mt-1 text-muted"><?=e($row['batch_name'])?></div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                    <span>Total: <?=number_format($tot)?></span>
                                    <span class="text-success">Avail: <?=number_format($avail)?></span>
                                </div>
                                <div class="progress-track">
                                    <div class="bar-seg bg-a" style="width:<?=$pA?>%"></div>
                                    <div class="bar-seg bg-t" style="width:<?=$pT?>%"></div>
                                    <div class="bar-seg bg-v" style="width:<?=$pV?>%"></div>
                                </div>
                                <div class="d-flex gap-3 small mt-1">
                                    <span class="text-success fw-bold"><i class="bi bi-circle-fill" style="font-size:6px"></i> Act: <?=number_format($act)?></span>
                                    <span class="text-danger fw-bold"><i class="bi bi-circle-fill" style="font-size:6px"></i> Term: <?=number_format($term)?></span>
                                </div>
                            </td>
                            <td>
                                <button class="btn-act" onclick='openMgr(<?=$json?>)'>Manage SIMs</button>
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
                        <div class="col">
                            <label class="small fw-bold text-muted">Batch Name (Auto)</label>
                            <input type="text" name="activation_batch" id="batchInput" class="form-control bg-light text-secondary" placeholder="Select PO first..." readonly required>
                        </div>
                        <div class="col">
                            <label class="small fw-bold text-muted">Date</label>
                            <input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required>
                        </div>
                    </div>

                    <div class="mb-3" id="progCont" style="display:none;">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span id="progText">Uploading...</span><span id="progPct">0%</span>
                        </div>
                        <div class="progress" style="height:10px">
                            <div class="progress-bar bg-primary" id="progBar" style="width:0%"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold" id="btnUp">Start Upload</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMgr" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="height:85vh; display:flex; flex-direction:column;">
            
            <div class="modal-header bg-white pb-3">
                <div>
                    <h6 class="modal-title fw-bold" id="mgrTitle">Manage SIMs</h6>
                    <div class="small text-muted" id="mgrSubtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="mgr-header-tabs">
                <ul class="nav nav-tabs" id="simTabs">
                    <li class="nav-item"><a class="nav-link active" href="#" onclick="switchTab('total')">All SIMs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" onclick="switchTab('active')">Active SIMs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#" onclick="switchTab('terminated')">Terminated SIMs</a></li>
                </ul>
            </div>

            <div class="mgr-search-bar">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="globalSearch" class="form-control border-start-0" placeholder="Search MSISDN, ICCID..." onkeyup="delaySearch()">
                    <button class="btn btn-dark" onclick="doSearch()">Search</button>
                </div>
            </div>

            <div class="mgr-content-area" id="simList">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>

            <div class="mgr-footer-area">
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleAll(this)">
                        <label class="form-check-label small fw-bold" for="checkAll">Select All</label>
                    </div>
                    <span class="small text-muted">Selected: <b id="selCount" class="text-primary">0</b></span>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick="changePage(-1)" id="btnPrev">Previous</button>
                    <span class="small fw-bold mx-2" id="pageInfo">Page 1</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="changePage(1)" id="btnNext">Next</button>
                </div>

                <div class="d-flex gap-2">
                    <input type="date" id="actDate" class="form-control form-control-sm w-auto" value="<?=date('Y-m-d')?>">
                    <button class="btn btn-success btn-sm fw-bold" onclick="doProc('activate')">Activate</button>
                    <button class="btn btn-danger btn-sm fw-bold" onclick="doProc('terminate')">Terminate</button>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalLog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-white border-bottom pb-3">
                <div>
                    <h6 class="modal-title fw-bold" id="logTitle">History Logs</h6>
                    <div class="small text-muted" id="logSubtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="logStatsContainer"></div>
                <h6 class="fw-bold text-secondary small mb-3 border-bottom pb-2">TRANSACTION HISTORY</h6>
                <div id="logList" class="list-group list-group-flush"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // TOAST
    function toast(t,m){ 
        let c=t==='success'?'toast-success':'toast-error'; let i=t==='success'?'bi-check-circle-fill text-success':'bi-exclamation-triangle-fill text-danger';
        let h=`<div class="toast-item ${c}"><i class="bi ${i} fs-4"></i><div><div class="fw-bold text-uppercase">${t}</div><div class="small text-muted">${m}</div></div></div>`;
        $('#toastCont').append(h); setTimeout(()=>$('#toastCont').children().first().remove(),4000); 
    }

    // UPLOAD LOGIC
    function openUpload(){ $('#formUploadMaster')[0].reset(); $('#pgCont').hide(); $('#btnUp').prop('disabled',false).text('Start Upload'); $('#batchInput').val(''); new bootstrap.Modal(document.getElementById('modalUp')).show(); }
    
    function fetchBatchInfo(id) {
        if(!id) { $('#batchInput').val(''); return; }
        $.post('process_sim_tracking.php', { action: 'get_po_details', id: id }, function(res){
            if(res.status === 'success') { $('#batchInput').val(res.batch_name || 'BATCH 1'); } 
            else { toast('error', res.message); $('#batchInput').val(''); }
        }, 'json');
    }

    $('#formUploadMaster').on('submit', function(e){
        e.preventDefault(); let fd = new FormData(this);
        if($('#batchInput').val() === '') { toast('error', 'Batch Name Missing'); return; }
        $('#btnUp').prop('disabled',true); $('#pgCont').slideDown();
        
        $.ajax({
            xhr: function(){var x=new window.XMLHttpRequest(); x.upload.addEventListener("progress",e=>{if(e.lengthComputable){var p=Math.round((e.loaded/e.total)*100); $('.progress-bar').css('width',p+'%');}},false); return x;},
            type:'POST', url:'process_sim_tracking.php', data:fd, contentType:false, processData:false, dataType:'json',
            success: function(r){ 
                if(r.status==='success'){ $('.progress-bar').addClass('bg-success'); toast('success',r.message); setTimeout(()=>location.reload(),1500); }
                else { $('.progress-bar').addClass('bg-danger'); toast('error',r.message); $('#btnUp').prop('disabled',false).text('Retry'); }
            },
            error: function(x){ toast('error', x.responseText); $('#btnUp').prop('disabled',false).text('Retry'); }
        });
    });

    // --- MANAGER LOGIC (ADVANCED) ---
    let curPO = 0, curTab = 'total', curPage = 1, curSearch = '', totalPages = 1;

    function openMgr(d) { 
        curPO = d.id; curTab = 'total'; curPage = 1; curSearch = '';
        $('#mgrTitle').text('Manage SIMs'); 
        $('#mgrSubtitle').text(`${d.comp} - ${d.po}`); 
        $('#globalSearch').val('');
        
        // Reset Tabs
        $('.nav-link').removeClass('active');
        $('.nav-link').first().addClass('active');
        
        new bootstrap.Modal(document.getElementById('modalMgr')).show(); 
        loadData();
    }

    function switchTab(tab) {
        curTab = tab; curPage = 1;
        $('.nav-link').removeClass('active');
        $(event.target).addClass('active');
        loadData();
    }

    let searchTimeout;
    function delaySearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(doSearch, 500);
    }

    function doSearch() {
        curSearch = $('#globalSearch').val().trim();
        curPage = 1;
        loadData();
    }

    function changePage(dir) {
        let nextPage = curPage + dir;
        if(nextPage > 0 && nextPage <= totalPages) {
            curPage = nextPage;
            loadData();
        }
    }

    function loadData() {
        $('#simList').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted">Loading data...</div></div>');
        $('#checkAll').prop('checked', false);
        $('#selCount').text(0);

        $.post('process_sim_tracking.php', {
            action: 'fetch_sims', 
            po_id: curPO, 
            tab: curTab, 
            search_bulk: curSearch, 
            page: curPage
        }, function(res) {
            if(res.status === 'success') {
                let h = '';
                if(res.data.length === 0) {
                    h = '<div class="text-center py-5 text-muted">No data found in this category.</div>';
                } else {
                    res.data.forEach(s => {
                        let stClass = s.status === 'Active' ? 'status-active' : (s.status === 'Terminated' ? 'status-term' : 'status-avail');
                        let badge = `<span class="status-badge ${stClass}">${s.status}</span>`;
                        let dateInfo = s.activation_date ? `<small class="text-muted ms-2"><i class="bi bi-calendar"></i> ${s.activation_date}</small>` : '';
                        
                        h += `<div class="sim-row" onclick="togRow(this)">
                                <div>
                                    <div class="fw-bold font-monospace">${s.msisdn} <span class="ms-2">${badge}</span></div>
                                    <div class="small text-muted">ICCID: ${s.iccid||'-'} ${dateInfo}</div>
                                </div>
                                <input type="checkbox" class="form-check-input chk-sim" value="${s.id}" onclick="event.stopPropagation(); updSel()">
                              </div>`;
                    });
                }
                
                $('#simList').html(h);
                
                // Update Pagination Info
                totalPages = res.total_pages;
                $('#pageInfo').text(`Page ${curPage} of ${totalPages} (${res.total_rows} items)`);
                $('#btnPrev').prop('disabled', curPage <= 1);
                $('#btnNext').prop('disabled', curPage >= totalPages);
                
            } else {
                $('#simList').html('<div class="text-center py-5 text-danger">Error loading data.</div>');
            }
        }, 'json');
    }

    function togRow(el) {
        let chk = $(el).find('.chk-sim');
        chk.prop('checked', !chk.prop('checked'));
        updSel();
    }

    function toggleAll(el) {
        $('.chk-sim').prop('checked', el.checked);
        updSel();
    }

    function updSel() {
        let n = $('.chk-sim:checked').length;
        $('#selCount').text(n);
        $('.sim-row').removeClass('selected');
        $('.chk-sim:checked').closest('.sim-row').addClass('selected');
    }

    function doProc(mode) {
        let ids = []; 
        $('.chk-sim:checked').each(function(){ ids.push($(this).val()) });
        
        if(ids.length === 0) { toast('error', 'Please select at least one item.'); return; }
        if(!confirm(`Confirm to ${mode.toUpperCase()} ${ids.length} selected items?`)) return;
        
        // Show loading state on buttons
        let btn = (mode === 'activate') ? $('.btn-success') : $('.btn-danger');
        let orgText = btn.text();
        btn.prop('disabled', true).text('Processing...');

        $.post('process_sim_tracking.php', {
            action: 'process_bulk_sim_action', 
            po_provider_id: curPO, 
            mode: mode, 
            sim_ids: ids, 
            date_field: $('#actDate').val(), 
            batch_name: $('#mgrSubtitle').text().split(' - ')[1] // Basic batch info
        }, function(res) {
            btn.prop('disabled', false).text(orgText);
            if(res.status === 'success') { 
                toast('success', res.message); 
                loadData(); // Reload current list
            } else { 
                toast('error', res.message); 
            }
        }, 'json');
    }

    // LOGS
    function fetchLogs(d) {
        $('#logTitle').text("Logs: " + d.po); $('#logSubtitle').text(d.comp + " | " + d.batch);
        
        if(d.stats) {
            let s = d.stats;
            $('#logStatsContainer').html(`
                <div class="log-stats">
                    <div class="log-stat-item"><div class="log-stat-label">Total</div><div class="log-stat-value">${parseInt(s.total).toLocaleString()}</div></div>
                    <div class="log-stat-item"><div class="log-stat-label text-success">Active</div><div class="log-stat-value val-act">${parseInt(s.active).toLocaleString()}</div></div>
                    <div class="log-stat-item"><div class="log-stat-label text-danger">Terminated</div><div class="log-stat-value val-term">${parseInt(s.terminated).toLocaleString()}</div></div>
                </div>`);
        }

        $('#logList').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>'); 
        new bootstrap.Modal(document.getElementById('modalLog')).show();
        
        $.post('process_sim_tracking.php', {action:'fetch_logs', po_id:d.id}, function(r){
            if(r.status==='success'){
                let h=''; if(r.data.length===0) h='<div class="text-center p-4 text-muted">No history found.</div>';
                else r.data.forEach(l=>{ 
                    let c = l.type==='Activation'?'text-success':'text-danger'; 
                    let icon = l.type==='Activation'?'bi-check-circle-fill':'bi-x-circle-fill';
                    h+=`<div class="list-group-item border-0 border-bottom py-3 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div><div class="fw-bold ${c} mb-1"><i class="bi ${icon} me-1"></i> ${l.type}</div><div class="small text-muted">${l.batch}</div><div class="small text-muted" style="font-size:0.75rem">${l.date}</div></div>
                                <div class="text-end"><span class="fw-bold fs-5 text-dark">${parseInt(l.qty).toLocaleString()}</span><div class="small text-muted">SIMs</div></div>
                            </div>
                        </div>`; 
                });
                $('#logList').html(h);
            } else $('#logList').html('<div class="p-3 text-danger text-center">Error loading logs.</div>');
        },'json');
    }

    const lbl=<?php echo json_encode($lbls??[]); ?>; 
    const sa=<?php echo json_encode($s_a??[]); ?>; 
    const st=<?php echo json_encode($s_t??[]); ?>;
    
    if(lbl.length > 0) {
        new ApexCharts(document.querySelector("#lifecycleChart"), {
            series: [{name:'Activations',data:sa},{name:'Terminations',data:st}],
            chart: {type:'area', height:280, toolbar:{show:false}},
            colors:['#10b981','#ef4444'], stroke:{curve:'smooth',width:2},
            xaxis:{categories:lbl}, grid:{borderColor:'#f1f5f9'},
            fill:{type:'gradient', gradient:{shadeIntensity:1, opacityFrom:0.7, opacityTo:0.2, stops:[0, 90, 100]}}
        }).render();
    }
    
    // DRAG & DROP
    const dz=document.getElementById('dropZone'), fi=document.getElementById('fIn');
    ['dragenter','dragover'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.style.backgroundColor='#eef2ff';dz.style.borderColor='#4f46e5'},false));
    ['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.style.backgroundColor='#f8fafc';dz.style.borderColor='#cbd5e1'},false));
    fi.addEventListener('change',()=>{ if(fi.files.length>0) {$('#fTxt').text(fi.files[0].name).addClass('text-primary');} });
</script>

<?php require_once 'includes/footer.php'; ?>