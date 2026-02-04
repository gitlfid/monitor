<?php
// =========================================================================
// 1. SETUP & DATA FETCHING (BACKEND LOGIC - TIDAK DIKURANGI)
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);
require_once 'includes/config.php'; require_once 'includes/functions.php'; 
require_once 'includes/header.php'; require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; 

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// Variables Data
$list_providers_new = [];
$dashboard_data = [];
$activations_raw = [];
$terminations_raw = [];
$total_system_sims = 0; $total_system_active = 0; $total_system_term = 0;

if($db) {
    // Dropdown Upload Baru
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
    try { 
        $dashboard_data = $db->query($sql_main)->fetchAll(PDO::FETCH_ASSOC); 
        // Hitung Total System Stats
        foreach($dashboard_data as $d) {
            $total_system_sims += $d['total_uploaded'];
            $total_system_active += $d['cnt_active'];
            $total_system_term += $d['cnt_term'];
        }
    } catch(Exception $e){}

    // Data Chart
    try {
        $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date DESC")->fetchAll(PDO::FETCH_ASSOC);
        $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Chart Logic
$chart_data_act = []; $chart_data_term = []; $js_labels = []; $js_series_act = []; $js_series_term = [];
foreach ($activations_raw as $r) { $d = $r['activation_date']; if(!isset($chart_data_act[$d])) $chart_data_act[$d]=0; $chart_data_act[$d]+=$r['active_qty']; }
foreach ($terminations_raw as $r) { $d = $r['termination_date']; if(!isset($chart_data_term[$d])) $chart_data_term[$d]=0; $chart_data_term[$d]+=$r['terminated_qty']; }
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term))); sort($all_dates);
foreach ($all_dates as $dk) { $js_labels[]=date('d M', strtotime($dk)); $js_series_act[]=$chart_data_act[$dk]??0; $js_series_term[]=$chart_data_term[$dk]??0; }
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
    
    :root {
        --primary: #4f46e5; --primary-hover: #4338ca; --primary-soft: #eef2ff;
        --success: #10b981; --success-soft: #ecfdf5;
        --danger: #ef4444; --danger-soft: #fef2f2;
        --dark: #1e293b; --gray: #64748b; --light: #f1f5f9; --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }

    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: var(--dark); }
    
    /* CARDS */
    .card-pro { background: var(--white); border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: var(--shadow-sm); transition: transform 0.2s; margin-bottom: 24px; overflow: hidden; }
    .card-pro:hover { box-shadow: var(--shadow-md); }
    .stat-card { padding: 24px; display: flex; align-items: center; gap: 20px; }
    .stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
    
    /* TABLE */
    .table-responsive { border-radius: 0 0 16px 16px; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    .table-pro th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray); font-weight: 700; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; }
    .table-pro td { padding: 20px 24px; vertical-align: top; border-bottom: 1px solid #f1f5f9; color: #334155; }
    
    /* BADGES & PROGRESS */
    .badge-pro { padding: 6px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
    .progress-track { background: #e2e8f0; border-radius: 6px; height: 8px; overflow: hidden; display: flex; }
    .progress-fill { height: 100%; transition: width 0.6s ease; }
    
    /* DRAG & DROP UPLOAD */
    .upload-area { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; background: #f8fafc; transition: all 0.2s; position: relative; cursor: pointer; }
    .upload-area:hover, .upload-area.dragover { border-color: var(--primary); background: var(--primary-soft); }
    .upload-input { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
    
    /* TOAST NOTIFICATION */
    .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .toast-pro { background: white; border-radius: 10px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 16px; margin-bottom: 10px; border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 12px; min-width: 300px; transform: translateX(120%); transition: transform 0.3s ease; }
    .toast-pro.show { transform: translateX(0); }
    .toast-pro.success { border-color: var(--success); }
    .toast-pro.error { border-color: var(--danger); }
    
    /* BUTTONS */
    .btn-primary-pro { background: var(--primary); border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; color: white; transition: 0.2s; }
    .btn-primary-pro:hover { background: var(--primary-hover); color: white; transform: translateY(-1px); }
    .btn-action-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; font-weight: 600; border: 1px solid transparent; width: 100%; margin-bottom: 4px; display: block; text-align: center; text-decoration: none; }
    .btn-action-sm.act { background: var(--success-soft); color: #059669; border-color: #a7f3d0; }
    .btn-action-sm.term { background: var(--danger-soft); color: #dc2626; border-color: #fecaca; }
    .btn-action-sm:hover { filter: brightness(0.95); }

    /* MANAGER MODAL */
    .mgr-layout { display: flex; height: 500px; }
    .mgr-left { width: 35%; background: #f8fafc; border-right: 1px solid #e2e8f0; padding: 20px; display: flex; flex-direction: column; }
    .mgr-right { width: 65%; padding: 0; display: flex; flex-direction: column; }
    .mgr-list-box { flex-grow: 1; overflow-y: auto; }
    .mgr-item { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
    .mgr-item:hover { background: #f1f5f9; }
    .mgr-item.selected { background: var(--primary-soft); border-left: 3px solid var(--primary); }
</style>

<div class="toast-container" id="toastContainer"></div>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">SIM Lifecycle Dashboard</h2>
            <p class="text-muted mb-0">Manage inventory, activation, and termination status.</p>
        </div>
        <button class="btn-primary-pro" onclick="openUploadModal()">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload New Batch
        </button>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card-pro stat-card">
                <div class="stat-icon bg-light text-primary"><i class="bi bi-sim"></i></div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Total System Inventory</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_system_sims) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-pro stat-card">
                <div class="stat-icon" style="background:var(--success-soft); color:var(--success)"><i class="bi bi-lightning-charge-fill"></i></div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Currently Active</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_system_active) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-pro stat-card">
                <div class="stat-icon" style="background:var(--danger-soft); color:var(--danger)"><i class="bi bi-power"></i></div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Terminated</h6>
                    <h2 class="fw-bold mb-0"><?= number_format($total_system_term) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card-pro p-4 mb-4">
        <h6 class="fw-bold text-dark mb-3">Activity Trends</h6>
        <div id="lifecycleChart" style="height: 280px;"></div>
    </div>

    <div class="card-pro">
        <div class="p-3 border-bottom d-flex align-items-center">
            <h6 class="fw-bold m-0"><i class="bi bi-table me-2"></i>Inventory Pools</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-pro w-100 mb-0">
                <thead>
                    <tr>
                        <th width="30%">Entity / Project</th>
                        <th width="20%">Source PO</th>
                        <th width="35%">Inventory Status</th>
                        <th width="15%" class="text-center">Quick Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No data available.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            $tot = (int)$row['total_uploaded']; $act = (int)$row['cnt_active']; $term = (int)$row['cnt_term']; $avail = (int)$row['cnt_avail'];
                            $pA = ($tot>0)?($act/$tot)*100:0; $pT = ($tot>0)?($term/$tot)*100:0; $pV = 100-$pA-$pT;
                            $json = htmlspecialchars(json_encode(['id'=>$row['po_id'], 'po'=>$row['provider_po'], 'batch'=>$row['batch_name'], 'company'=>$row['company_name']]), ENT_QUOTES);
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
                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                    <span>Total: <?=number_format($tot)?></span>
                                    <span class="text-success">Avail: <?=number_format($avail)?></span>
                                </div>
                                <div class="progress-track">
                                    <div class="progress-fill" style="width:<?=$pA?>%; background:var(--success)"></div>
                                    <div class="progress-fill" style="width:<?=$pT?>%; background:var(--danger)"></div>
                                    <div class="progress-fill" style="width:<?=$pV?>%; background:#cbd5e1"></div>
                                </div>
                                <div class="d-flex gap-3 small mt-1">
                                    <span class="text-success fw-bold"><i class="bi bi-dot"></i> Act: <?=number_format($act)?></span>
                                    <span class="text-danger fw-bold"><i class="bi bi-dot"></i> Term: <?=number_format($term)?></span>
                                </div>
                            </td>
                            <td>
                                <button class="btn-action-sm act" onclick='openManager(<?=$json?>, "activate")'>Activate</button>
                                <button class="btn-action-sm term" onclick='openManager(<?=$json?>, "terminate")'>Terminate</button>
                                <a href="#" class="text-center small d-block text-muted text-decoration-none mt-1" onclick='openLogs(<?=$json?>)'>View Logs</a>
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
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Upload Master Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formUploadMaster">
                    <input type="hidden" name="action" value="upload_master_bulk">
                    <input type="hidden" name="is_ajax" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">1. Select Provider PO</label>
                        <select name="po_provider_id" class="form-select" required>
                            <option value="">-- Choose PO --</option>
                            <?php foreach($list_providers_new as $p): ?>
                                <option value="<?=$p['id']?>"><?=$p['po_number']?> (Allocated: <?=number_format($p['sim_qty'])?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">2. Upload File (CSV/Excel)</label>
                        <div class="upload-area" id="dropZone">
                            <input type="file" name="upload_file" class="upload-input" id="fileInput" required accept=".csv, .xlsx">
                            <i class="bi bi-cloud-arrow-up display-4 text-primary opacity-50"></i>
                            <p class="mb-0 fw-bold mt-2" id="fileName">Click or Drag file here</p>
                            <div class="small text-muted mt-1">Format Header: <code>MSISDN</code> (Required)</div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col">
                            <label class="small fw-bold text-muted">Batch Name</label>
                            <input type="text" name="activation_batch" class="form-control" placeholder="Batch 1" required>
                        </div>
                        <div class="col">
                            <label class="small fw-bold text-muted">Date</label>
                            <input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required>
                        </div>
                    </div>

                    <div class="mb-3" id="progCont" style="display:none;">
                        <div class="d-flex justify-content-between small fw-bold mb-1">
                            <span id="progText">Uploading...</span>
                            <span id="progPct">0%</span>
                        </div>
                        <div class="progress-track" style="height:10px">
                            <div class="progress-fill" id="progBar" style="width:0%"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary-pro w-100" id="btnStartUpload">Start Upload Process</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalManager" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="overflow:hidden;">
            <div class="modal-header bg-white border-bottom">
                <div><h6 class="modal-title fw-bold" id="mgrTitle">Manage SIMs</h6><div class="small text-muted" id="mgrSubtitle">-</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="mgr-layout">
                <div class="mgr-left">
                    <label class="small fw-bold text-muted mb-2">BULK SEARCH (MSISDN)</label>
                    <textarea id="searchBulk" class="form-control mb-3 flex-grow-1" placeholder="Paste numbers here...&#10;62811...&#10;62812..."></textarea>
                    <button class="btn btn-dark w-100 fw-bold" onclick="fetchSims()"><i class="bi bi-search me-2"></i>Find Matches</button>
                </div>
                <div class="mgr-right">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                        <span class="fw-bold small">Results: <span id="resCount">0</span></span>
                        <div class="form-check"><input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleAll(this)"><label class="form-check-label small" for="checkAll">Select All</label></div>
                    </div>
                    <div class="mgr-list-box" id="simList">
                        <div class="text-center text-muted py-5 mt-5">
                            <i class="bi bi-arrow-left-circle display-4 text-light"></i>
                            <p class="mt-3">Search from the left panel.</p>
                        </div>
                    </div>
                    <div class="p-3 border-top bg-light d-flex align-items-center justify-content-between">
                        <div class="d-flex gap-2 align-items-center">
                            <span class="small fw-bold">Date:</span>
                            <input type="date" id="actionDate" class="form-control form-control-sm w-auto" value="<?=date('Y-m-d')?>">
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-end lh-1"><div class="small text-muted">Selected</div><div class="fw-bold text-primary h5 m-0" id="selCount">0</div></div>
                            <button class="btn-primary-pro" id="btnProcess" onclick="processAction()" disabled>Confirm Action</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLogs" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0"><div class="modal-header"><h6 class="modal-title fw-bold">Activity Logs</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0" id="logContent"><div class="text-center py-5"><div class="spinner-border text-primary"></div></div></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // --- UI HELPERS ---
    function showToast(type, msg) {
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        const html = `
            <div class="toast-pro ${type} show">
                <i class="bi ${icon} fs-4 ${type === 'success' ? 'text-success' : 'text-danger'}"></i>
                <div>
                    <div class="fw-bold text-dark">${type === 'success' ? 'Success' : 'Error'}</div>
                    <div class="small text-muted">${msg}</div>
                </div>
            </div>
        `;
        const $toast = $(html).appendTo('#toastContainer');
        setTimeout(() => { $toast.removeClass('show'); setTimeout(() => $toast.remove(), 300); }, 4000);
    }

    // Drag & Drop Visuals
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
    ['dragenter', 'dragover'].forEach(eventName => { dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false); });
    ['dragleave', 'drop'].forEach(eventName => { dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false); });
    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        fileInput.files = files;
        $('#fileName').text(files[0].name).addClass('text-primary');
    });
    fileInput.addEventListener('change', () => {
        if(fileInput.files.length > 0) $('#fileName').text(fileInput.files[0].name).addClass('text-primary');
    });

    // --- UPLOAD HANDLER ---
    function openUploadModal() {
        $('#formUploadMaster')[0].reset();
        $('#progCont').hide(); $('#btnStartUpload').prop('disabled', false).text('Start Upload');
        $('#fileName').text('Click or Drag file here').removeClass('text-primary');
        new bootstrap.Modal(document.getElementById('modalUpload')).show();
    }

    $('#formUploadMaster').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        
        $('#btnStartUpload').prop('disabled', true).text('Processing...');
        $('#progCont').slideDown();
        $('#progBar').css('width', '0%').css('background', '#4f46e5');
        $('#progText').text('Uploading...');

        $.ajax({
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var pct = Math.round((evt.loaded / evt.total) * 100);
                        $('#progBar').css('width', pct + '%');
                        $('#progPct').text(pct + '%');
                        if (pct === 100) $('#progText').text('Server Validating...');
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
                    $('#progBar').css('background', '#10b981');
                    $('#progText').text('Completed!');
                    showToast('success', res.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    $('#progBar').css('background', '#ef4444');
                    $('#progText').text('Failed');
                    $('#btnStartUpload').prop('disabled', false).text('Try Again');
                    showToast('error', res.message);
                }
            },
            error: function(xhr) {
                let msg = "Server Error";
                if(xhr.responseText) msg += ": " + xhr.responseText.substring(0, 100);
                $('#progBar').css('background', '#ef4444');
                $('#btnStartUpload').prop('disabled', false).text('Try Again');
                showToast('error', msg);
            }
        });
    });

    // --- MANAGER HANDLER ---
    let curPO = 0, curMode = '', curBatch = '';
    
    function openManager(d, m) {
        curPO = d.id; curMode = m; curBatch = d.batch;
        $('#mgrTitle').text(m === 'activate' ? 'Activate SIMs' : 'Terminate SIMs');
        $('#mgrSubtitle').text(`${d.company} - ${d.po}`);
        $('#searchBulk').val('');
        $('#simList').html('<div class="text-center text-muted py-5 mt-5"><p>Enter MSISDNs to search.</p></div>');
        $('#resCount').text('0'); $('#selCount').text('0');
        $('#btnProcess').prop('disabled', true);
        
        if (m === 'activate') {
            $('#btnProcess').removeClass('btn-danger').addClass('btn-success').text('Switch to Active');
        } else {
            $('#btnProcess').removeClass('btn-success').addClass('btn-danger').text('Switch to Terminated');
        }
        new bootstrap.Modal(document.getElementById('modalManager')).show();
    }

    function fetchSims() {
        let v = $('#searchBulk').val().trim();
        $('#simList').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        
        $.post('process_sim_tracking.php', {action:'fetch_sims', po_id:curPO, mode:curMode, search_bulk:v}, function(res){
            if(res.status === 'success'){
                let h = '';
                res.data.forEach(s => {
                    h += `<div class="mgr-item" onclick="toggleRow(this)">
                            <div><div class="fw-bold" style="font-family:monospace;font-size:1rem">${s.msisdn}</div><small class="text-muted">${s.iccid||'-'}</small></div>
                            <input type="checkbox" class="form-check-input chk-item" value="${s.id}" onclick="event.stopPropagation();upd()">
                          </div>`;
                });
                $('#simList').html(h || '<div class="text-center py-5 text-muted">No results found.</div>');
                $('#resCount').text(res.data.length);
                upd();
            } else { showToast('error', res.message); }
        }, 'json');
    }

    function toggleRow(el) {
        let chk = $(el).find('.chk-item');
        chk.prop('checked', !chk.prop('checked'));
        upd();
    }
    function toggleAll(el) { $('.chk-item').prop('checked', el.checked); upd(); }
    
    function upd() {
        let n = $('.chk-item:checked').length;
        $('#selCount').text(n);
        $('#btnProcess').prop('disabled', n===0);
        $('.mgr-item').removeClass('selected');
        $('.chk-item:checked').closest('.mgr-item').addClass('selected');
    }

    function processAction() {
        let ids = []; $('.chk-item:checked').each(function(){ids.push($(this).val())});
        if(!confirm(`Confirm action for ${ids.length} SIMs?`)) return;
        
        $('#btnProcess').prop('disabled',true).text('Processing...');
        $.post('process_sim_tracking.php', {
            action:'process_bulk_sim_action', po_provider_id:curPO, mode:curMode, sim_ids:ids, date_field:$('#actionDate').val(), batch_name:curBatch
        }, function(res){
            if(res.status==='success'){ showToast('success', res.message); setTimeout(()=>location.reload(), 1500); }
            else { showToast('error', res.message); $('#btnProcess').prop('disabled',false).text('Retry'); }
        }, 'json');
    }

    // --- LOGS (AJAX) ---
    function openLogs(d) {
        $('#logContent').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        new bootstrap.Modal(document.getElementById('modalLogs')).show();
        $.post('process_sim_tracking.php', {action:'fetch_logs', po_id:d.id}, function(res){
            if(res.status==='success'){
                let h='<div class="list-group list-group-flush">';
                if(res.data.length===0) h+='<div class="p-4 text-center text-muted">No history logs available.</div>';
                else res.data.forEach(l=>{
                    let c = l.type==='Activation'?'text-success':'text-danger';
                    h+=`<div class="list-group-item d-flex justify-content-between"><div><span class="fw-bold ${c}">${l.type}</span><div class="small text-muted">${l.batch} - ${l.date}</div></div><span class="fw-bold fs-5">${parseInt(l.qty).toLocaleString()}</span></div>`;
                });
                $('#logContent').html(h+'</div>');
            } else $('#logContent').html('<div class="p-3 text-danger">Failed to load logs.</div>');
        },'json');
    }
    
    // --- CHART ---
    const labels = <?php echo json_encode($js_labels??[]); ?>;
    const sAct = <?php echo json_encode($js_series_act??[]); ?>;
    const sTerm = <?php echo json_encode($js_series_term??[]); ?>;
    if(labels.length > 0) {
        new ApexCharts(document.querySelector('#lifecycleChart'), {
            series: [{name:'Activations',data:sAct},{name:'Terminations',data:sTerm}],
            chart: {type:'area', height:250, toolbar:{show:false}},
            colors:['#10b981','#ef4444'], stroke:{curve:'smooth',width:2},
            xaxis:{categories:labels}, grid:{borderColor:'#f1f5f9'},
            fill:{type:'gradient', gradient:{shadeIntensity:1, opacityFrom:0.7, opacityTo:0.2, stops:[0, 90, 100]}}
        }).render();
    }
</script>

<?php require_once 'includes/footer.php'; ?>