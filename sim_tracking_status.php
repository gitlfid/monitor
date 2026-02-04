<?php
// =========================================================================
// 1. SETUP & DATABASE
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch Data (Sama seperti sebelumnya)
$list_providers = []; $list_clients = []; $list_projects = []; $activations_raw = []; $terminations_raw = [];
if ($db) {
    // Provider PO (Auto Link Logic)
    $sql_prov = "SELECT p.id, p.po_number, p.batch_name, p.sim_qty, COALESCE(cpo.company_id, p.company_id) as client_comp_id, COALESCE(cpo.project_id, p.project_id) as client_proj_id FROM sim_tracking_po p LEFT JOIN sim_tracking_po cpo ON p.link_client_po_id = cpo.id WHERE p.type='provider' AND p.id NOT IN (SELECT DISTINCT po_provider_id FROM sim_activations) ORDER BY p.id DESC";
    try { $list_providers = $db->query($sql_prov)->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

    try { $list_clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
    try { $list_projects = $db->query("SELECT id, company_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
    
    // Logs for Chart
    try { $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
    try { $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
}

// Chart Logic
$chart_data_act = []; $chart_data_term = []; $js_labels = []; $js_series_act = []; $js_series_term = [];
foreach ($activations_raw as $r) { $d = $r['activation_date']; if(!isset($chart_data_act[$d])) $chart_data_act[$d]=0; $chart_data_act[$d]+=$r['active_qty']; }
foreach ($terminations_raw as $r) { $d = $r['termination_date']; if(!isset($chart_data_term[$d])) $chart_data_term[$d]=0; $chart_data_term[$d]+=$r['terminated_qty']; }
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term))); sort($all_dates);
foreach($all_dates as $dk){ $js_labels[]=date('d M', strtotime($dk)); $js_series_act[]=$chart_data_act[$dk]??0; $js_series_term[]=$chart_data_term[$dk]??0; }

// Main Dashboard Data
$dashboard_data = [];
if($db) {
    $sql_main = "SELECT po.id as po_id, po.po_number as provider_po, po.batch_name, po.sim_qty as total_pool, cp.po_number as client_po, c.company_name, p.project_name, c.id as company_id, p.id as project_id,
        (SELECT COALESCE(SUM(active_qty+inactive_qty),0) FROM sim_activations WHERE po_provider_id=po.id) as total_used_stock,
        (SELECT COALESCE(SUM(terminated_qty),0) FROM sim_terminations WHERE po_provider_id=po.id) as total_terminated
        FROM sim_tracking_po po LEFT JOIN sim_tracking_po cp ON po.link_client_po_id=cp.id LEFT JOIN companies c ON po.company_id=c.id LEFT JOIN projects p ON po.project_id=p.id
        WHERE po.type='provider' HAVING po.id IN (SELECT DISTINCT po_provider_id FROM sim_activations) ORDER BY po.id DESC";
    try { $dashboard_data = $db->query($sql_main)->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
}
?>

<style>
    /* ... CSS dari file sebelumnya tetap ada ... */
    body { background-color: #f4f6f8; font-family: 'Inter', sans-serif; }
    .card-custom { border:none; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.03); margin-bottom:24px; background:#fff; }
    .table-pro th { background:#f8fafc; font-size:0.75rem; text-transform:uppercase; color:#64748b; padding:15px; }
    .table-pro td { padding:15px; border-bottom:1px solid #f1f5f9; }
    .btn-action-row { display:grid; grid-template-columns:1fr 1fr; gap:5px; margin-bottom:5px; }
    .btn-act { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; width:100%; border-radius:6px; font-size:0.8rem; }
    .btn-term { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; width:100%; border-radius:6px; font-size:0.8rem; }
    .progress-bar-custom { height:100%; transition:width 0.6s ease; }
    
    /* NEW STYLES FOR UPLOAD PROGRESS */
    .progress-container { display:none; margin-top:15px; }
    .progress-track { background:#e2e8f0; height:12px; border-radius:6px; overflow:hidden; }
    .progress-fill { background:#4f46e5; height:100%; width:0%; transition:width 0.2s; }
    .upload-alert { display:none; margin-top:15px; border-radius:8px; font-size:0.9rem; }
</style>

<div class="page-heading mb-4 d-flex justify-content-between">
    <h3 class="fw-bold text-dark">SIM Lifecycle Dashboard</h3>
    <button class="btn btn-primary fw-bold" onclick="openMasterModal()"><i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Master Data</button>
</div>

<div class="card-custom p-4">
    <h6 class="fw-bold text-primary">Trends</h6>
    <div id="lifecycleChart" style="height: 250px;"></div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table w-100 mb-0 table-pro">
            <thead><tr><th>Entity Info</th><th>Source</th><th>Status</th><th class="text-center">Action</th></tr></thead>
            <tbody>
                <?php if(empty($dashboard_data)): ?><tr><td colspan="4" class="text-center py-5 text-muted">No data available.</td></tr><?php else: ?>
                <?php foreach($dashboard_data as $row): 
                    $tot = (int)$row['total_pool']; $used = (int)$row['total_used_stock']; $term = (int)$row['total_terminated'];
                    $act = max(0, $used - $term); $avail = max(0, $tot - $used);
                    $pA = ($tot>0)?($act/$tot)*100:0; $pT = ($tot>0)?($term/$tot)*100:0; $pV = 100-$pA-$pT;
                    $json = htmlspecialchars(json_encode(['po_id'=>$row['po_id'], 'po_number'=>$row['provider_po'], 'batch_name'=>$row['batch_name'], 'rem_alloc'=>$avail, 'curr_active'=>$act]), ENT_QUOTES);
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?=e($row['company_name'])?></div>
                        <div class="small text-muted">Client PO: <?=e($row['client_po'])?></div>
                    </td>
                    <td>
                        <div class="badge bg-light text-primary border"><?=e($row['provider_po'])?></div>
                        <div class="small fw-bold mt-1"><?=e($row['batch_name'])?></div>
                    </td>
                    <td>
                        <div class="d-flex justify-content-between small fw-bold mb-1"><span>Total: <?=number_format($tot)?></span><span class="text-success">Avail: <?=number_format($avail)?></span></div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-success" style="width:<?=$pA?>%"></div>
                            <div class="progress-bar bg-danger" style="width:<?=$pT?>%"></div>
                            <div class="progress-bar bg-secondary opacity-25" style="width:<?=$pV?>%"></div>
                        </div>
                        <div class="d-flex gap-3 small mt-1"><span class="text-success">Act: <?=number_format($act)?></span><span class="text-danger">Term: <?=number_format($term)?></span></div>
                    </td>
                    <td>
                        <div class="btn-action-row">
                            <button class="btn-act" onclick='openAction("activate", <?=$json?>)'>Activate</button>
                            <button class="btn-term" onclick='openAction("terminate", <?=$json?>)'>Terminate</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalMaster" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title fw-bold">Upload Master Data</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <form id="formUpload" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_master_bulk">
                    <input type="hidden" name="is_ajax" value="1"> <div class="mb-3">
                        <label class="small fw-bold">1. Source PO</label>
                        <select name="po_provider_id" id="inj_provider" class="form-select" required onchange="autoFill(this)">
                            <option value="">-- Select --</option>
                            <?php foreach($list_providers as $p): ?>
                                <option value="<?=$p['id']?>" data-comp="<?=$p['client_comp_id']?>" data-proj="<?=$p['client_proj_id']?>" data-batch="<?=$p['batch_name']?>"><?=$p['po_number']?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">2. Destination</label>
                        <select name="company_id" id="inj_client" class="form-select mb-2" required onchange="filterProj(this.value)">
                            <?php foreach($list_clients as $c): echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; endforeach; ?>
                        </select>
                        <select name="project_id" id="inj_project" class="form-select"><option value="">-- Project --</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">3. File (CSV/Excel)</label>
                        <input type="file" name="upload_file" class="form-control" required accept=".csv, .xlsx">
                        <div class="form-text small">Header: <code>MSISDN</code> (Required).</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col"><input type="text" name="activation_batch" id="inj_batch" class="form-control" placeholder="Batch Name" required></div>
                        <div class="col"><input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 fw-bold" id="btnUpload">Start Upload</button>
                </form>

                <div class="progress-container" id="progCont">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span id="progText">Uploading...</span>
                        <span id="progPct">0%</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill" id="progBar"></div></div>
                </div>
                
                <div class="alert upload-alert" id="alertBox"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAction" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0"><input type="hidden" name="action" id="act_action"><input type="hidden" name="po_provider_id" id="act_po"><div class="modal-header"><h6 class="modal-title fw-bold" id="act_title">Action</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><ul class="nav nav-tabs nav-fill mb-3"><li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#manual">Manual</a></li><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#file">File</a></li></ul><div class="tab-content"><div class="tab-pane fade show active" id="manual"><input type="number" name="qty_input" class="form-control text-center fw-bold" placeholder="Qty"></div><div class="tab-pane fade" id="file"><input type="file" name="action_file" class="form-control"></div></div><div class="mt-3"><input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>"></div><div class="mt-3"><button class="btn btn-primary w-100">Confirm</button></div></div></form></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    const projects = <?php echo json_encode($list_projects); ?>;
    
    function openMasterModal() {
        $('#formUpload')[0].reset();
        $('#progCont').hide(); $('#alertBox').hide();
        $('#btnUpload').prop('disabled', false).text('Start Upload');
        new bootstrap.Modal(document.getElementById('modalMaster')).show();
    }

    // AJAX UPLOAD HANDLER
    $('#formUpload').on('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        
        // UI Reset
        $('#btnUpload').prop('disabled', true).text('Processing...');
        $('#progCont').show();
        $('#alertBox').hide();
        $('#progBar').css('width', '0%').removeClass('bg-danger bg-success').addClass('bg-primary');
        $('#progText').text('Starting...');

        $.ajax({
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var pct = Math.round((evt.loaded / evt.total) * 100);
                        $('#progBar').css('width', pct + '%');
                        $('#progPct').text(pct + '%');
                        if (pct === 100) $('#progText').text('Saving to Database...');
                    }
                }, false);
                return xhr;
            },
            type: 'POST',
            url: 'process_sim_tracking.php',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json', // Expect JSON Response
            success: function(res) {
                if(res.status === 'success') {
                    $('#progBar').removeClass('bg-primary').addClass('bg-success');
                    $('#progText').text('Done!');
                    $('#alertBox').removeClass('alert-danger').addClass('alert-success').html(res.message).slideDown();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr, status, error) {
                // Handle HTML Error (e.g. PHP Fatal Error)
                let msg = "Server Error";
                if(xhr.responseText) {
                    // Strip tags to show plain text error
                    let tmp = document.createElement("DIV");
                    tmp.innerHTML = xhr.responseText;
                    msg = tmp.textContent || tmp.innerText || "";
                    if(msg.length > 200) msg = msg.substring(0, 200) + "...";
                }
                showError(msg);
            }
        });
    });

    function showError(msg) {
        $('#progBar').removeClass('bg-primary').addClass('bg-danger');
        $('#progText').text('Failed');
        $('#btnUpload').prop('disabled', false).text('Try Again');
        $('#alertBox').removeClass('alert-success').addClass('alert-danger').html('<b>Error:</b> ' + msg).slideDown();
    }

    function autoFill(el) {
        let opt = el.options[el.selectedIndex];
        $('#inj_client').val(opt.getAttribute('data-comp')).trigger('change');
        setTimeout(() => $('#inj_project').val(opt.getAttribute('data-proj')), 50);
        $('#inj_batch').val(opt.getAttribute('data-batch') || 'BATCH 1');
    }
    
    function filterProj(cid) {
        let sel = $('#inj_project').empty().append('<option value="">-- Project --</option>');
        projects.filter(p => p.company_id == cid).forEach(p => sel.append(`<option value="${p.id}">${p.project_name}</option>`));
    }

    function openAction(type, data) {
        $('#act_title').text(type === 'activate' ? 'Activate' : 'Terminate');
        $('#act_action').val(type === 'activate' ? 'create_activation_simple' : 'create_termination_simple');
        $('#act_po').val(data.po_id);
        if(type === 'activate') $('input[name="qty_input"]').attr('name', 'active_qty');
        else $('input[name="qty_input"]').attr('name', 'terminated_qty');
        new bootstrap.Modal(document.getElementById('modalAction')).show();
    }

    // Chart
    const labels = <?php echo json_encode($js_labels??[]); ?>;
    const sAct = <?php echo json_encode($js_series_act??[]); ?>;
    const sTerm = <?php echo json_encode($js_series_term??[]); ?>;
    if(labels.length > 0) {
        new ApexCharts(document.querySelector('#lifecycleChart'), {
            series: [{name:'Activations', data:sAct}, {name:'Terminations', data:sTerm}],
            chart: {type:'area', height:250, toolbar:{show:false}},
            colors: ['#10b981', '#ef4444'], stroke: {curve:'smooth', width:2},
            xaxis: {categories: labels}, grid:{borderColor:'#f1f5f9'}
        }).render();
    }
</script>

<?php require_once 'includes/footer.php'; ?>