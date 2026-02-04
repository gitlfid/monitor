<?php
// =========================================================================
// 1. SETUP
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);
require_once 'includes/config.php'; require_once 'includes/functions.php'; 
require_once 'includes/header.php'; require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; // Include helper untuk akses DB

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch Data
$list_providers_new = [];
$dashboard_data = [];
$activations_raw = [];
$terminations_raw = [];

if($db) {
    // Dropdown Upload Baru
    try { $list_providers_new = $db->query("SELECT id, po_number, sim_qty FROM sim_tracking_po WHERE type='provider' AND id NOT IN (SELECT DISTINCT po_provider_id FROM sim_inventory)")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}
    
    // Main Dashboard Data
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

    // Data for Charts/Logs
    try {
        $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date DESC")->fetchAll(PDO::FETCH_ASSOC);
        $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Chart Logic Preparation
$chart_data_act = []; $chart_data_term = []; $js_labels = []; $js_series_act = []; $js_series_term = [];
foreach ($activations_raw as $r) { $d = $r['activation_date']; if(!isset($chart_data_act[$d])) $chart_data_act[$d]=0; $chart_data_act[$d]+=$r['active_qty']; }
foreach ($terminations_raw as $r) { $d = $r['termination_date']; if(!isset($chart_data_term[$d])) $chart_data_term[$d]=0; $chart_data_term[$d]+=$r['terminated_qty']; }
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term))); sort($all_dates);
foreach ($all_dates as $dk) { $js_labels[]=date('d M', strtotime($dk)); $js_series_act[]=$chart_data_act[$dk]??0; $js_series_term[]=$chart_data_term[$dk]??0; }
?>

<style>
    body { background-color: #f4f7fa; font-family: 'Inter', sans-serif; }
    .card-custom { border:none; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.03); margin-bottom:24px; background:#fff; }
    .progress-slim { height:10px; border-radius:5px; overflow:hidden; background:#e2e8f0; margin:8px 0; display:flex; }
    .bar-seg { height:100%; transition:width 0.6s ease; }
    .bg-active { background:#10b981; } .bg-term { background:#ef4444; } .bg-avail { background:#cbd5e1; }
    
    /* Progress Upload */
    .upload-progress-container { display:none; margin-top:20px; }
    .progress-bar-upload { height:12px; background-color:#4f46e5; transition: width 0.2s; border-radius:6px; }
    .alert-upload { display:none; margin-top:15px; font-size:0.9rem; border-radius:8px; }
    
    /* Modal Manager */
    .mgr-list { max-height:350px; overflow-y:auto; border:1px solid #eee; border-radius:8px; }
    .mgr-item { padding:10px; border-bottom:1px solid #f9f9f9; display:flex; justify-content:space-between; align-items:center; }
    .mgr-item:hover { background:#f8fafc; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-dark">SIM Lifecycle Dashboard</h3>
    <button class="btn btn-primary fw-bold px-4" onclick="openUploadModal()">
        <i class="bi bi-cloud-upload me-2"></i> Upload Master Data
    </button>
</div>

<div class="card-custom p-4">
    <h6 class="fw-bold text-primary mb-3">Lifecycle Trends</h6>
    <div id="lifecycleChart" style="height:250px;"></div>
</div>

<div class="card-custom">
    <div class="table-responsive">
        <table class="table w-100 mb-0 align-middle">
            <thead class="bg-light">
                <tr>
                    <th class="p-3 text-secondary small text-uppercase">Entity Info</th>
                    <th class="p-3 text-secondary small text-uppercase">Source</th>
                    <th class="p-3 text-secondary small text-uppercase">Inventory Status</th>
                    <th class="p-3 text-secondary small text-uppercase text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($dashboard_data)): ?><tr><td colspan="4" class="text-center py-5 text-muted">No data found.</td></tr><?php else: ?>
                <?php foreach($dashboard_data as $row): 
                    $tot = (int)$row['total_uploaded']; $act = (int)$row['cnt_active']; $trm = (int)$row['cnt_term']; $avl = (int)$row['cnt_avail'];
                    $pA = ($tot>0)?($act/$tot)*100:0; $pT = ($tot>0)?($trm/$tot)*100:0; $pV = 100 - $pA - $pT;
                    $json = htmlspecialchars(json_encode(['id'=>$row['po_id'], 'po'=>$row['provider_po'], 'batch'=>$row['batch_name']]), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td class="p-3">
                        <div class="fw-bold"><?=e($row['company_name'])?></div>
                        <div class="small text-muted">Client PO: <?=e($row['client_po'])?></div>
                    </td>
                    <td class="p-3">
                        <div class="badge bg-light text-primary border"><?=e($row['provider_po'])?></div>
                        <div class="small fw-bold mt-1"><?=e($row['batch_name'])?></div>
                    </td>
                    <td class="p-3">
                        <div class="d-flex justify-content-between small fw-bold"><span>Total: <?=number_format($tot)?></span><span class="text-success">Avail: <?=number_format($avl)?></span></div>
                        <div class="progress-slim">
                            <div class="bar-seg bg-active" style="width:<?=$pA?>%"></div>
                            <div class="bar-seg bg-term" style="width:<?=$pT?>%"></div>
                            <div class="bar-seg bg-avail" style="width:<?=$pV?>%"></div>
                        </div>
                        <div class="d-flex gap-3 small"><span class="text-success fw-bold">Act: <?=number_format($act)?></span><span class="text-danger fw-bold">Term: <?=number_format($trm)?></span></div>
                    </td>
                    <td class="p-3 text-center">
                        <button class="btn btn-sm btn-outline-success w-100 mb-1" onclick='openManager(<?=$json?>, "activate")'>Activate</button>
                        <button class="btn btn-sm btn-outline-danger w-100 mb-1" onclick='openManager(<?=$json?>, "terminate")'>Terminate</button>
                        <button class="btn btn-sm btn-light border w-100" onclick='openLogs(<?=$json?>)'>Logs</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalUpload" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title fw-bold">Upload Master Data</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <form id="formUploadMaster">
                    <input type="hidden" name="action" value="upload_master_bulk">
                    <input type="hidden" name="is_ajax" value="1">
                    
                    <div class="mb-3">
                        <label class="small fw-bold">Provider PO</label>
                        <select name="po_provider_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach($list_providers_new as $p): ?><option value="<?=$p['id']?>"><?=$p['po_number']?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">File (Excel/CSV)</label>
                        <input type="file" name="upload_file" class="form-control" required>
                        <div class="form-text small">Header Wajib: <code>MSISDN</code></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col"><input type="text" name="activation_batch" class="form-control" placeholder="Batch Name" required></div>
                        <div class="col"><input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold" id="btnUp">Start Upload</button>
                </form>

                <div class="upload-progress-container" id="progCont">
                    <div class="d-flex justify-content-between small fw-bold mb-1"><span id="progText">Uploading...</span><span id="progPct">0%</span></div>
                    <div class="progress" style="height:12px"><div class="progress-bar-upload" id="progBar" style="width:0%"></div></div>
                </div>
                <div class="alert alert-upload" id="alertBox"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalManager" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content border-0" style="height:80vh"><div class="modal-header"><h5 class="modal-title fw-bold" id="mgrTitle">Manage</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="d-flex h-100"><div class="bg-light p-3 border-end" style="width:300px"><textarea id="searchBulk" class="form-control mb-2" rows="10" placeholder="Search MSISDN..."></textarea><button class="btn btn-dark w-100" onclick="fetchSims()">Search</button></div><div class="flex-grow-1 p-3 d-flex flex-column"><div class="mgr-list flex-grow-1 mb-3" id="simList"></div><div class="d-flex justify-content-between align-items-center"><div>Selected: <span id="selCount" class="fw-bold">0</span></div><button class="btn btn-primary fw-bold px-4" id="btnProc" onclick="processAction()" disabled>Confirm</button></div></div></div></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // --- UPLOAD HANDLER WITH PROGRESS BAR ---
    function openUploadModal() {
        $('#formUploadMaster')[0].reset();
        $('#progCont').hide();
        $('#alertBox').hide();
        $('#btnUp').prop('disabled', false).text('Start Upload');
        $('#progBar').css('width', '0%');
        new bootstrap.Modal(document.getElementById('modalUpload')).show();
    }

    $('#formUploadMaster').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        
        $('#btnUp').prop('disabled', true).text('Processing...');
        $('#progCont').show();
        $('#alertBox').hide();
        $('#progBar').css('width', '0%').removeClass('bg-danger bg-success').addClass('bg-primary');
        $('#progText').text('Uploading...');

        $.ajax({
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var pct = Math.round((evt.loaded / evt.total) * 100);
                        $('#progBar').css('width', pct + '%');
                        $('#progPct').text(pct + '%');
                        if(pct === 100) $('#progText').text('Server Processing (Please Wait)...');
                    }
                }, false);
                return xhr;
            },
            type: 'POST',
            url: 'process_sim_tracking.php',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#progBar').removeClass('bg-primary').addClass('bg-success');
                    $('#progText').text('Completed!');
                    $('#alertBox').removeClass('alert-danger').addClass('alert-success').html(res.message).slideDown();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr) {
                let msg = "Server Error";
                if(xhr.responseText) msg += ": " + xhr.responseText.substring(0, 100);
                showError(msg);
            }
        });
    });

    function showError(msg) {
        $('#progBar').removeClass('bg-primary').addClass('bg-danger');
        $('#progText').text('Failed');
        $('#btnUp').prop('disabled', false).text('Try Again');
        $('#alertBox').removeClass('alert-success').addClass('alert-danger').html('<b>Error:</b> ' + msg).slideDown();
    }

    // --- MANAGER LOGIC (SEARCH & SWITCH) ---
    let curPO=0, curMode='', curBatch='';
    function openManager(d, m) {
        curPO = d.id; curMode = m; curBatch = d.batch;
        $('#mgrTitle').text(m === 'activate' ? 'Activate SIMs' : 'Terminate SIMs');
        $('#searchBulk').val(''); $('#simList').html('<div class="text-center text-muted py-5">Ready to search.</div>');
        $('#selCount').text('0'); $('#btnProc').prop('disabled', true);
        new bootstrap.Modal(document.getElementById('modalManager')).show();
    }

    function fetchSims() {
        let val = $('#searchBulk').val();
        $('#simList').html('<div class="text-center py-5">Searching...</div>');
        $.post('process_sim_tracking.php', { action:'fetch_sims', po_id:curPO, mode:curMode, search_bulk:val }, function(res){
            if(res.status === 'success') {
                let html = '';
                res.data.forEach(s => {
                    html += `<div class="mgr-item"><div><b>${s.msisdn}</b> <span class="text-muted ms-2">${s.iccid||''}</span></div><input type="checkbox" class="form-check-input chk-sim" value="${s.id}" onchange="chk()"></div>`;
                });
                $('#simList').html(html || '<div class="text-center py-5 text-muted">No results found.</div>');
                chk();
            } else { alert(res.message); }
        }, 'json');
    }

    function chk() {
        let n = $('.chk-sim:checked').length;
        $('#selCount').text(n);
        $('#btnProc').prop('disabled', n===0);
    }

    function processAction() {
        let ids = [];
        $('.chk-sim:checked').each(function(){ ids.push($(this).val()) });
        if(!confirm(`Confirm ${curMode} for ${ids.length} SIMs?`)) return;
        
        $.post('process_sim_tracking.php', { 
            action:'process_bulk_sim_action', po_provider_id:curPO, mode:curMode, sim_ids:ids, date_field:'<?=date('Y-m-d')?>', batch_name:curBatch 
        }, function(res){
            if(res.status==='success') { alert(res.message); location.reload(); }
            else { alert(res.message); }
        }, 'json');
    }

    // --- CHART ---
    const chartLabels = <?php echo json_encode($js_labels??[]); ?>;
    const seriesAct = <?php echo json_encode($js_series_act??[]); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term??[]); ?>;
    
    document.addEventListener('DOMContentLoaded', function () {
        if(chartLabels.length > 0){
             new ApexCharts(document.querySelector('#lifecycleChart'), {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 250, toolbar: { show: false } },
                colors: ['#10b981', '#ef4444'], stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels },
                grid: { borderColor: '#f1f5f9' }
            }).render();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>