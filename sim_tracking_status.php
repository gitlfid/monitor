<?php
// =========================================================================
// FRONTEND SETUP
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);
require_once 'includes/config.php'; require_once 'includes/functions.php'; 
require_once 'includes/header.php'; require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; 

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch Data
$list_providers_new = []; $dashboard_data = []; $activations_raw = []; $terminations_raw = [];
$total_sys_sims=0; $total_sys_act=0; $total_sys_term=0;

if($db) {
    // Upload Dropdown
    try { $list_providers_new = $db->query("SELECT id, po_number, sim_qty FROM sim_tracking_po WHERE type='provider' AND id NOT IN (SELECT DISTINCT po_provider_id FROM sim_inventory)")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
    
    // Dashboard Data
    $sql_main = "SELECT po.id as po_id, po.po_number as provider_po, po.batch_name as batch_name, po.sim_qty as total_pool,
                    client_po.po_number as client_po, c.company_name, p.project_name,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Available') as cnt_avail,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Active') as cnt_active,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Terminated') as cnt_term,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id) as total_uploaded
                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider' HAVING total_uploaded > 0 ORDER BY po.id DESC";
    try { $dashboard_data = $db->query($sql_main)->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

    // Stats Calc
    foreach($dashboard_data as $d) {
        $total_sys_sims += $d['total_uploaded']; $total_sys_act += $d['cnt_active']; $total_sys_term += $d['cnt_term'];
    }

    // Logs
    try { $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
    try { $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
}

// Chart Prep
$cd_a=[]; $cd_t=[]; $lbls=[]; $s_a=[]; $s_t=[];
foreach($activations_raw as $r){ $d=$r['activation_date']; if(!isset($cd_a[$d]))$cd_a[$d]=0; $cd_a[$d]+=$r['active_qty']; }
foreach($terminations_raw as $r){ $d=$r['termination_date']; if(!isset($cd_t[$d]))$cd_t[$d]=0; $cd_t[$d]+=$r['terminated_qty']; }
$dates = array_unique(array_merge(array_keys($cd_a), array_keys($cd_t))); sort($dates);
foreach($dates as $d){ $lbls[]=date('d M', strtotime($d)); $s_a[]=$cd_a[$d]??0; $s_t[]=$cd_t[$d]??0; }
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { background:#f4f6f8; font-family:'Inter', sans-serif; color:#334155; }
    
    /* Cards */
    .card-pro { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px; overflow:hidden; }
    .stat-card { padding:24px; display:flex; align-items:center; gap:20px; }
    .stat-icon { width:50px; height:50px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
    
    /* Table */
    .table-pro th { background:#f8fafc; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; padding:15px 20px; border-bottom:1px solid #e2e8f0; }
    .table-pro td { padding:18px 20px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    
    /* Progress */
    .progress-bar-custom { height:8px; border-radius:4px; overflow:hidden; display:flex; background:#e2e8f0; margin-top:8px; }
    .bar-seg { height:100%; }
    .bg-a { background:#10b981; } .bg-t { background:#ef4444; } .bg-v { background:#cbd5e1; }
    
    /* Buttons */
    .btn-act { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; font-size:0.75rem; font-weight:600; border-radius:6px; padding:5px 10px; width:100%; margin-bottom:4px; transition:0.2s; }
    .btn-act:hover { background:#166534; color:#fff; }
    .btn-term { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; font-size:0.75rem; font-weight:600; border-radius:6px; padding:5px 10px; width:100%; margin-bottom:4px; transition:0.2s; }
    .btn-term:hover { background:#991b1b; color:#fff; }
    .btn-log { background:#fff; color:#475569; border:1px solid #e2e8f0; font-size:0.75rem; font-weight:600; border-radius:6px; padding:5px 10px; width:100%; transition:0.2s; }
    .btn-log:hover { background:#f1f5f9; }

    /* Upload Area */
    .upload-box { border:2px dashed #cbd5e1; background:#f8fafc; border-radius:10px; padding:30px; text-align:center; cursor:pointer; transition:0.2s; }
    .upload-box:hover { border-color:#4f46e5; background:#eef2ff; }
    
    /* Toast */
    #toastCont { position:fixed; top:20px; right:20px; z-index:9999; }
    .toast-item { min-width:300px; padding:15px; border-radius:8px; background:#fff; box-shadow:0 5px 15px rgba(0,0,0,0.1); margin-bottom:10px; border-left:4px solid #333; display:flex; gap:12px; align-items:center; animation: slideIn 0.3s ease; }
    .toast-success { border-color:#10b981; } .toast-error { border-color:#ef4444; }
    @keyframes slideIn { from { transform:translateX(100%); opacity:0; } to { transform:translateX(0); opacity:1; } }
</style>

<div id="toastCont"></div>

<div class="d-flex justify-content-between align-items-center mb-4 p-4 pb-0">
    <div><h3 class="fw-bold text-dark m-0">SIM Lifecycle Dashboard</h3><p class="text-muted small">Manage Inventory & Status</p></div>
    <button class="btn btn-primary fw-bold px-4" onclick="openUpload()"><i class="bi bi-cloud-upload me-2"></i> Upload Batch</button>
</div>

<div class="row g-4 px-4 mb-4">
    <div class="col-md-4"><div class="card-pro stat-card"><div class="stat-icon bg-light text-primary"><i class="bi bi-sim"></i></div><div><div class="small text-muted fw-bold">TOTAL SIMS</div><h3 class="fw-bold m-0"><?=number_format($total_sys_sims)?></h3></div></div></div>
    <div class="col-md-4"><div class="card-pro stat-card"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-lightning-charge"></i></div><div><div class="small text-muted fw-bold">ACTIVE</div><h3 class="fw-bold m-0"><?=number_format($total_sys_act)?></h3></div></div></div>
    <div class="col-md-4"><div class="card-pro stat-card"><div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-power"></i></div><div><div class="small text-muted fw-bold">TERMINATED</div><h3 class="fw-bold m-0"><?=number_format($total_sys_term)?></h3></div></div></div>
</div>

<div class="px-4 mb-4">
    <div class="card-pro p-4">
        <h6 class="fw-bold text-dark mb-3">Transaction Trends</h6>
        <div id="chart" style="height:280px;"></div>
    </div>
</div>

<div class="px-4 pb-5">
    <div class="card-pro">
        <div class="table-responsive">
            <table class="table w-100 mb-0 table-pro">
                <thead><tr><th>Entity Info</th><th>Source</th><th>Inventory Status</th><th class="text-center">Manage</th></tr></thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?><tr><td colspan="4" class="text-center py-5 text-muted">No data available.</td></tr><?php else: ?>
                    <?php foreach($dashboard_data as $row): 
                        $t=$row['total_uploaded']; $a=$row['cnt_active']; $tm=$row['cnt_term']; $av=$row['cnt_avail'];
                        $pa=($t>0)?($a/$t)*100:0; $pt=($t>0)?($tm/$t)*100:0; $pv=100-$pa-$pt;
                        $json=htmlspecialchars(json_encode(['id'=>$row['po_id'], 'po'=>$row['provider_po'], 'batch'=>$row['batch_name'], 'comp'=>$row['company_name']]), ENT_QUOTES);
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?=e($row['company_name'])?></div>
                            <div class="small text-muted mb-1">PO Client: <?=e($row['client_po'])?:'-'?></div>
                            <span class="badge bg-light text-primary border"><?=e($row['project_name'])?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?=e($row['provider_po'])?></div>
                            <div class="small text-muted"><?=e($row['batch_name'])?></div>
                        </td>
                        <td>
                            <div class="d-flex justify-content-between small fw-bold"><span>Total: <?=number_format($t)?></span><span class="text-success">Avail: <?=number_format($av)?></span></div>
                            <div class="progress-bar-custom">
                                <div class="bar-seg bg-a" style="width:<?=$pa?>%"></div>
                                <div class="bar-seg bg-t" style="width:<?=$pt?>%"></div>
                                <div class="bar-seg bg-v" style="width:<?=$pv?>%"></div>
                            </div>
                            <div class="d-flex gap-3 small mt-1"><span class="text-success fw-bold">Act: <?=number_format($a)?></span><span class="text-danger fw-bold">Term: <?=number_format($tm)?></span></div>
                        </td>
                        <td>
                            <button class="btn-act" onclick='openMgr(<?=$json?>,"activate")'>Activate</button>
                            <button class="btn-term" onclick='openMgr(<?=$json?>,"terminate")'>Terminate</button>
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

<div class="modal fade" id="modalUp" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header bg-primary text-white"><h6 class="modal-title fw-bold">Upload Master</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4">
    <form id="formUp">
        <input type="hidden" name="action" value="upload_master_bulk">
        <div class="mb-3"><label class="small fw-bold">Provider PO</label><select name="po_provider_id" class="form-select"><?php foreach($list_providers_new as $p): ?><option value="<?=$p['id']?>"><?=$p['po_number']?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><div class="upload-box" onclick="$('#fIn').click()"><input type="file" name="upload_file" id="fIn" style="display:none" onchange="$('#fTxt').text(this.files[0].name)"><i class="bi bi-cloud-arrow-up display-6 text-muted"></i><div class="mt-2 fw-bold" id="fTxt">Click to Upload CSV/Excel</div></div></div>
        <div class="row mb-3"><div class="col"><input type="text" name="activation_batch" class="form-control" placeholder="Batch Name" required></div><div class="col"><input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required></div></div>
        <div class="mb-3" id="progWrap" style="display:none"><div class="d-flex justify-content-between small fw-bold mb-1"><span>Uploading...</span><span id="progPct">0%</span></div><div class="progress" style="height:10px"><div class="progress-bar bg-primary" id="progBar" style="width:0%"></div></div></div>
        <button type="submit" class="btn btn-primary w-100 fw-bold" id="btnUp">Start Upload</button>
    </form>
</div></div></div></div>

<div class="modal fade" id="modalMgr" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0" style="height:85vh"><div class="modal-header"><h6 class="modal-title fw-bold" id="mgrTitle">Manage</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0 d-flex">
    <div class="bg-light p-3 border-end" style="width:300px"><label class="small fw-bold text-muted">Search MSISDN</label><textarea id="sBulk" class="form-control mb-3" rows="10"></textarea><button class="btn btn-dark w-100 fw-bold" onclick="doSearch()">Search</button></div>
    <div class="flex-grow-1 p-3 d-flex flex-column"><div class="flex-grow-1 overflow-auto border rounded p-2 mb-3" id="sList"></div><div class="d-flex justify-content-between align-items-center"><div>Selected: <span id="sCount" class="fw-bold text-primary">0</span></div><button class="btn btn-primary fw-bold" id="btnProc" disabled onclick="doAction()">Execute</button></div></div>
</div></div></div></div>

<div class="modal fade" id="modalLogs" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content border-0"><div class="modal-header"><h6 class="modal-title fw-bold">Logs</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div id="logList" class="list-group list-group-flush"></div></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // TOAST
    function showToast(type, msg) {
        let col = type==='success'?'toast-success':'toast-error';
        let ico = type==='success'?'bi-check-circle-fill text-success':'bi-exclamation-triangle-fill text-danger';
        let h = `<div class="toast-item ${col}"><i class="bi ${ico} fs-4"></i><div><div class="fw-bold">${type.toUpperCase()}</div><div class="small text-muted">${msg}</div></div></div>`;
        $('#toastCont').append(h); setTimeout(()=>$('#toastCont').children().first().remove(), 4000);
    }

    // UPLOAD
    function openUpload(){ $('#formUp')[0].reset(); $('#progWrap').hide(); $('#btnUp').prop('disabled',false).text('Start Upload'); new bootstrap.Modal(document.getElementById('modalUp')).show(); }
    $('#formUp').on('submit', function(e){
        e.preventDefault();
        let fd = new FormData(this);
        $('#btnUp').prop('disabled',true); $('#progWrap').show();
        $.ajax({
            xhr: function(){var x=new window.XMLHttpRequest(); x.upload.addEventListener("progress", function(e){if(e.lengthComputable){var p=Math.round((e.loaded/e.total)*100); $('#progBar').css('width',p+'%'); $('#progPct').text(p+'%');}}, false); return x;},
            type:'POST', url:'process_sim_tracking.php', data:fd, contentType:false, processData:false, dataType:'json',
            success: function(r){
                if(r.status==='success'){ $('#progBar').addClass('bg-success'); showToast('success', r.message); setTimeout(()=>location.reload(), 1500); }
                else { $('#progBar').addClass('bg-danger'); showToast('error', r.message); $('#btnUp').prop('disabled',false).text('Retry'); }
            },
            error: function(x){ showToast('error', x.responseText); $('#btnUp').prop('disabled',false).text('Retry'); }
        });
    });

    // MANAGER
    let cId=0, cMode='', cBatch='';
    function openMgr(d, m) { cId=d.id; cMode=m; cBatch=d.batch; $('#mgrTitle').text(m==='activate'?'Activate':'Terminate'); $('#sList').html(''); $('#sBulk').val(''); new bootstrap.Modal(document.getElementById('modalMgr')).show(); }
    function doSearch() {
        $('#sList').html('<div class="text-center py-5">Searching...</div>');
        $.post('process_sim_tracking.php', {action:'fetch_sims', po_id:cId, mode:cMode, search_bulk:$('#sBulk').val()}, function(r){
            if(r.status==='success'){
                let h=''; r.data.forEach(s=>{ h+=`<div class="d-flex justify-content-between p-2 border-bottom align-items-center"><div><div class="fw-bold">${s.msisdn}</div><small class="text-muted">${s.iccid||''}</small></div><input type="checkbox" class="chk" value="${s.id}" onchange="updC()"></div>` });
                $('#sList').html(h||'<div class="text-center py-5 text-muted">No results.</div>');
            } else showToast('error', r.message);
        }, 'json');
    }
    function updC() { let n=$('.chk:checked').length; $('#sCount').text(n); $('#btnProc').prop('disabled', n===0); }
    function doAction() {
        let ids=[]; $('.chk:checked').each(function(){ ids.push($(this).val()) });
        if(!confirm(`Confirm ${cMode} ${ids.length} items?`)) return;
        $.post('process_sim_tracking.php', {action:'process_bulk_sim_action', po_provider_id:cId, mode:cMode, sim_ids:ids, date_field:'<?=date('Y-m-d')?>', batch_name:cBatch}, function(r){
            if(r.status==='success'){ showToast('success', r.message); setTimeout(()=>location.reload(), 1500); } else showToast('error', r.message);
        }, 'json');
    }

    // LOGS AJAX FIX
    function fetchLogs(d) {
        $('#logList').html('<div class="text-center py-5">Loading...</div>');
        new bootstrap.Modal(document.getElementById('modalLogs')).show();
        $.post('process_sim_tracking.php', {action:'fetch_logs', po_id:d.id}, function(r){
            if(r.status==='success'){
                let h=''; r.data.forEach(l=>{
                    let col = l.type==='Activation'?'text-success':'text-danger';
                    h+=`<div class="list-group-item d-flex justify-content-between"><div><div class="fw-bold ${col}">${l.type}</div><small class="text-muted">${l.batch} - ${l.date}</small></div><div class="fw-bold">${parseInt(l.qty).toLocaleString()}</div></div>`;
                });
                $('#logList').html(h||'<div class="text-center py-5 text-muted">No logs found.</div>');
            } else $('#logList').html('<div class="text-center text-danger">Error loading logs.</div>');
        }, 'json');
    }

    // CHART
    const lbl=<?php echo json_encode($lbls); ?>; const sa=<?php echo json_encode($s_a); ?>; const st=<?php echo json_encode($s_t); ?>;
    if(lbl.length>0) new ApexCharts(document.querySelector("#chart"), {series:[{name:'Act',data:sa},{name:'Term',data:st}], chart:{type:'area',height:250,toolbar:{show:false}}, colors:['#10b981','#ef4444'], stroke:{curve:'smooth',width:2}, xaxis:{categories:lbl}}).render();
</script>

<?php require_once 'includes/footer.php'; ?>