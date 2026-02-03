<?php
// =========================================================================
// 1. SETUP & DATABASE CONNECTION
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

// Helper Function
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// =========================================================================
// 2. FETCH DATA DROPDOWNS & RAW DATA FOR JS
// =========================================================================
$list_providers = [];
$list_clients   = [];
$list_projects  = [];

// Raw Data Arrays for JS (History Logs & Charts)
$activations_raw = [];
$terminations_raw = [];

if ($db) {
    // A. PROVIDER PO (FILTERED)
    $sql_prov = "SELECT 
                    p.id, p.po_number, p.batch_name, p.sim_qty,
                    cpo.company_id as client_comp_id, 
                    cpo.project_id as client_proj_id  
                 FROM sim_tracking_po p 
                 LEFT JOIN sim_tracking_po cpo ON p.link_client_po_id = cpo.id
                 WHERE p.type='provider' 
                 AND p.id NOT IN (SELECT DISTINCT po_provider_id FROM sim_activations) 
                 ORDER BY p.id DESC";
    $list_providers = $db->query($sql_prov)->fetchAll(PDO::FETCH_ASSOC);

    // B. MASTER DATA
    $list_clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $list_projects = $db->query("SELECT id, company_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // C. FETCH RAW HISTORY (ALL DATA)
    // Penting: Ambil semua data tanpa limit agar history log lengkap
    try {
        $sql_act_raw = "SELECT * FROM sim_activations ORDER BY activation_date DESC, id DESC";
        $stmt = $db->query($sql_act_raw);
        if($stmt) $activations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_term_raw = "SELECT * FROM sim_terminations ORDER BY termination_date DESC, id DESC";
        $stmt = $db->query($sql_term_raw);
        if($stmt) $terminations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}

// Chart Data Logic
$chart_data_act = []; 
$chart_data_term = [];
$js_labels = []; $js_series_act = []; $js_series_term = [];

foreach ($activations_raw as $row) {
    $d = date('Y-m-d', strtotime($row['activation_date']));
    if(!isset($chart_data_act[$d])) $chart_data_act[$d] = 0;
    $chart_data_act[$d] += (int)$row['active_qty'];
}
foreach ($terminations_raw as $row) {
    $d = date('Y-m-d', strtotime($row['termination_date']));
    if(!isset($chart_data_term[$d])) $chart_data_term[$d] = 0;
    $chart_data_term[$d] += (int)$row['terminated_qty'];
}
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term)));
sort($all_dates); 
foreach ($all_dates as $dateKey) {
    $js_labels[] = date('d M', strtotime($dateKey));
    $js_series_act[] = $chart_data_act[$dateKey] ?? 0;
    $js_series_term[] = $chart_data_term[$dateKey] ?? 0;
}

// =========================================================================
// 3. MAIN DASHBOARD DATA
// =========================================================================
$dashboard_data = [];
if ($db) {
    $sql_main = "SELECT 
                    po.id as po_id,
                    po.po_number as provider_po,
                    po.batch_name as batch_name,
                    po.sim_qty as total_pool,
                    client_po.po_number as client_po,
                    c.company_name,
                    p.project_name,
                    c.id as company_id,
                    p.id as project_id,
                    
                    (SELECT COALESCE(SUM(active_qty + inactive_qty), 0) 
                     FROM sim_activations WHERE po_provider_id = po.id) as total_used_stock,
                    
                    (SELECT COALESCE(SUM(terminated_qty), 0) 
                     FROM sim_terminations WHERE po_provider_id = po.id) as total_terminated

                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider'
                HAVING po.id IN (SELECT DISTINCT po_provider_id FROM sim_activations)
                ORDER BY po.id DESC";
    
    try {
        $stmt = $db->query($sql_main);
        if($stmt) $dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}
?>

<style>
    /* 1. CORE LAYOUT */
    body { background-color: #f4f7fa; font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #1e293b; }
    
    /* 2. CARD & CONTAINERS */
    .card { border: none; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); background: #fff; margin-bottom: 24px; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 24px; border-radius: 16px 16px 0 0 !important; }
    
    /* 3. TABLE PROFESSIONAL */
    .table-pro { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-pro th { 
        background-color: #f8fafc; color: #64748b; font-size: 0.7rem; 
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; 
        padding: 16px 24px; border-bottom: 1px solid #e2e8f0; 
    }
    .table-pro td { padding: 24px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .table-pro tr:last-child td { border-bottom: none; }
    .table-pro tr:hover td { background-color: #fcfdfe; }

    /* 4. TYPOGRAPHY & LABELS */
    .text-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 4px; letter-spacing: 0.03em; }
    .text-value { font-size: 0.95rem; font-weight: 600; color: #334155; display: block; }
    .text-sub { font-size: 0.8rem; color: #64748b; }

    /* 5. BADGES */
    .badge-soft { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; font-family: monospace; }
    .badge-prov { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .badge-batch { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

    /* 6. STATUS BAR (PROFESSIONAL) */
    .status-wrapper { background: #fff; }
    .status-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px; }
    .progress-bar-modern { display: flex; height: 8px; border-radius: 4px; overflow: hidden; background: #f1f5f9; width: 100%; }
    .bar-seg { height: 100%; transition: width 0.5s ease; }
    
    /* COLORS */
    .bg-active { background-color: #10b981; } /* Green */
    .bg-terminated { background-color: #ef4444; } /* Red */
    .bg-available { background-color: #cbd5e1; } /* Gray */
    
    .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 4px; }
    .dot-act { background: #10b981; }
    .dot-term { background: #ef4444; }

    /* 7. ACTION BUTTONS */
    .btn-action { 
        padding: 8px 12px; font-size: 0.8rem; font-weight: 600; border-radius: 8px; 
        display: inline-flex; align-items: center; justify-content: center; gap: 6px; 
        transition: all 0.2s; border: 1px solid transparent; width: 100%; text-align: center; margin-bottom: 6px;
    }
    .btn-act { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .btn-act:hover { background: #059669; color: white; }
    .btn-term { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .btn-term:hover { background: #dc2626; color: white; }
    .btn-log { background: #fff; color: #64748b; border-color: #e2e8f0; }
    .btn-log:hover { background: #f8fafc; border-color: #cbd5e1; color: #334155; }
    .disabled { opacity: 0.5; pointer-events: none; filter: grayscale(1); }

    /* 8. MASTER BUTTON */
    .btn-master { 
        background: #4f46e5; color: white; border: none; padding: 10px 24px; border-radius: 8px; 
        font-weight: 600; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); 
        display: flex; align-items: center; gap: 8px; transition: 0.2s; 
    }
    .btn-master:hover { background: #4338ca; transform: translateY(-1px); color: white; }

    /* 9. TIMELINE (HISTORY LOG) */
    .timeline { position: relative; padding-left: 24px; border-left: 2px solid #e2e8f0; margin-left: 12px; }
    .timeline-item { position: relative; margin-bottom: 24px; }
    .timeline-dot { 
        position: absolute; left: -31px; top: 0; width: 16px; height: 16px; border-radius: 50%; 
        border: 3px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; 
    }
    .timeline-dot.act { background: #10b981; }
    .timeline-dot.term { background: #ef4444; }
    
    .timeline-content { background: #fff; border: 1px solid #f1f5f9; padding: 12px 16px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .time-date { font-size: 0.75rem; color: #94a3b8; font-weight: 600; margin-bottom: 4px; display: block; }
    
    /* 10. UPLOAD ZONE */
    .upload-zone { border: 2px dashed #cbd5e1; background: #f8fafc; padding: 30px; text-align: center; border-radius: 12px; position: relative; cursor: pointer; transition: 0.2s; }
    .upload-zone:hover { border-color: #4f46e5; background: #eef2ff; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">SIM Lifecycle Dashboard</h3>
            <p class="text-muted mb-0 small">Centralized Management for Master Data & Status.</p>
        </div>
        <div>
            <button class="btn-master" onclick="openMasterModal()">
                <i class="bi bi-cloud-arrow-up-fill"></i> Upload Master Data
            </button>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Trends</h6>
            <div id="lifecycleChart" style="height: 250px;"></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold text-dark m-0"><i class="bi bi-hdd-stack me-2"></i> Active SIM Pools</h6>
        </div>
        <div class="table-responsive">
            <table class="table-pro">
                <thead>
                    <tr>
                        <th width="30%">Entity Information</th>
                        <th width="25%">Source Hierarchy</th>
                        <th width="30%">Lifecycle Status</th>
                        <th width="15%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">No data found. Click "Upload Master Data" to start.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            // 1. LOGIKA UTAMA
                            $totalPool = (int)$row['total_pool']; // Total Awal
                            $usedStock = (int)$row['total_used_stock']; // Pernah Aktif
                            $terminated = (int)$row['total_terminated']; // Sudah Mati
                            
                            // 2. HITUNGAN STATUS
                            // Active = (Total Pernah Aktif) - (Sudah Mati)
                            $active = max(0, $usedStock - $terminated);
                            
                            // Available = (Total Pool) - (Total Pernah Aktif) -> Mengurangi Total
                            $available = max(0, $totalPool - $usedStock);

                            // 3. PERSENTASE VISUAL
                            $pctActive = ($totalPool > 0) ? ($active / $totalPool) * 100 : 0;
                            $pctTerm = ($totalPool > 0) ? ($terminated / $totalPool) * 100 : 0;
                            $pctAvail = 100 - $pctActive - $pctTerm;

                            // 4. JSON DATA
                            $rowJson = htmlspecialchars(json_encode([
                                'po_id' => $row['po_id'],
                                'po_number' => $row['provider_po'],
                                'batch_name' => $row['batch_name'],
                                'company_id' => $row['company_id'],
                                'project_id' => $row['project_id'],
                                'rem_alloc' => $available,
                                'curr_active' => $active,
                                'total_alloc' => $totalPool
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <div class="mb-2">
                                    <span class="text-label">Client Name</span>
                                    <span class="text-value"><?= e($row['company_name']) ?></span>
                                </div>
                                <div>
                                    <span class="text-label">Project</span>
                                    <div class="text-sub"><i class="bi bi-folder2-open text-primary me-1"></i> <?= e($row['project_name']) ?></div>
                                </div>
                            </td>

                            <td>
                                <div class="mb-2">
                                    <span class="text-label">Provider Source</span>
                                    <span class="badge-soft badge-prov"><?= e($row['provider_po']) ?></span>
                                </div>
                                <div>
                                    <span class="text-label">Batch ID</span>
                                    <span class="badge-soft badge-batch"><?= e($row['batch_name']) ?: 'BATCH 1' ?></span>
                                </div>
                            </td>

                            <td>
                                <div class="status-wrapper">
                                    <div class="status-header">
                                        <span class="text-sub fw-bold">Total Pool: <?= number_format($totalPool) ?></span>
                                        <span class="text-sub text-success fw-bold">Available: <?= number_format($available) ?></span>
                                    </div>
                                    
                                    <div class="progress-bar-modern mb-2">
                                        <div class="bar-seg bg-active" style="width: <?= $pctActive ?>%" title="Active"></div>
                                        <div class="bar-seg bg-terminated" style="width: <?= $pctTerm ?>%" title="Terminated"></div>
                                        <div class="bar-seg bg-available" style="width: <?= $pctAvail ?>%" title="Available"></div>
                                    </div>

                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <div class="dot dot-act"></div>
                                            <div class="ms-1 lh-1">
                                                <div class="text-label" style="font-size:0.6rem; margin:0;">Active</div>
                                                <div class="fw-bold text-dark"><?= number_format($active) ?></div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="dot dot-term"></div>
                                            <div class="ms-1 lh-1">
                                                <div class="text-label" style="font-size:0.6rem; margin:0;">Terminated</div>
                                                <div class="fw-bold text-dark"><?= number_format($terminated) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <button class="btn-action btn-act <?= ($available <= 0) ? 'disabled' : '' ?>" onclick='openActionModal("activate", <?= $rowJson ?>)'>
                                    <i class="bi bi-plus-lg"></i> Activate
                                </button>
                                
                                <button class="btn-action btn-term <?= ($active <= 0) ? 'disabled' : '' ?>" onclick='openActionModal("terminate", <?= $rowJson ?>)'>
                                    <i class="bi bi-x-lg"></i> Terminate
                                </button>
                                
                                <button class="btn-action btn-log" onclick='openDetailModal(<?= $rowJson ?>)'>
                                    <i class="bi bi-clock-history"></i> Logs
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalMaster" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0">
            <input type="hidden" name="action" value="upload_master_bulk">

            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Master Data</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted">1. SELECT SOURCE (PROVIDER PO)</label>
                    <select name="po_provider_id" id="inj_provider" class="form-select form-select-lg fw-bold border-primary" required onchange="autoFillClient(this)">
                        <option value="">-- Choose New Provider PO --</option>
                        <?php foreach($list_providers as $p): ?>
                            <option value="<?= $p['id'] ?>" 
                                data-comp="<?= $p['client_comp_id'] ?>" 
                                data-proj="<?= $p['client_proj_id'] ?>"
                                data-batch="<?= $p['batch_name'] ?>">
                                <?= $p['po_number'] ?> (Total: <?= number_format($p['sim_qty']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Client</label>
                        <select name="company_id" id="inj_client" class="form-select bg-light" required onchange="filterProjects(this.value)">
                            <option value="">-- Auto Select --</option>
                            <?php foreach($list_clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Project</label>
                        <select name="project_id" id="inj_project" class="form-select bg-light">
                            <option value="">-- Select Project --</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted">2. UPLOAD DATA</label>
                    <div class="upload-zone">
                        <input type="file" name="upload_file" accept=".csv, .xlsx, .xls" required onchange="handleFile(this)" style="position:absolute; width:100%; height:100%; top:0; left:0; opacity:0;">
                        <i class="bi bi-file-earmark-excel text-primary display-4"></i>
                        <h6 class="fw-bold mt-2 text-dark" id="fileNameDisplay">Drag & Drop or Click to Browse</h6>
                        <p class="text-muted small mb-0">Format: <code>SN, ICCID, IMSI, MSISDN</code></p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Batch Name</label>
                        <input type="text" name="activation_batch" id="inj_batch" class="form-control" placeholder="e.g. BATCH 1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Upload Date</label>
                        <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">Start Upload</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAction" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="act_form_action"> 
            <input type="hidden" name="po_provider_id" id="act_po_id">
            <input type="hidden" name="company_id" id="act_comp_id">
            <input type="hidden" name="project_id" id="act_proj_id">
            <input type="hidden" name="activation_batch" id="act_batch_name_hidden"> 
            <input type="hidden" name="termination_batch" id="term_batch_name_hidden"> 

            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold" id="act_title">Action</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body pt-2 text-center">
                <div class="text-muted small mb-3" id="act_limit_display">...</div>
                <div class="form-floating mb-2">
                    <input type="number" name="qty_input" id="act_qty_input" class="form-control text-center fw-bold fs-5" placeholder="Qty" required min="1">
                    <label>Quantity</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="date" name="date_field" class="form-control text-center" value="<?= date('Y-m-d') ?>" required>
                    <label>Date</label>
                </div>
                <div class="text-danger small fw-bold mb-3" id="act_error_msg" style="display:none;">Exceeds Limit!</div>
                
                <div class="text-end mb-3">
                    <a href="#" class="small text-decoration-none" onclick="$('#sim_detail_box').slideToggle(); return false;">+ SIM Details (Optional)</a>
                </div>
                <div id="sim_detail_box" class="text-start bg-light p-3 rounded mb-3 border" style="display:none;">
                    <input type="text" name="msisdn" id="inp_msisdn" class="form-control form-control-sm mb-2" placeholder="MSISDN (Required)">
                    <input type="text" name="iccid" class="form-control form-control-sm mb-2" placeholder="ICCID">
                    <input type="text" name="imsi" class="form-control form-control-sm mb-2" placeholder="IMSI">
                    <input type="text" name="sn" class="form-control form-control-sm" placeholder="SN">
                </div>

                <button type="submit" class="btn fw-bold w-100" id="act_btn_save">Confirm</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-white border-bottom">
                <h6 class="modal-title fw-bold">Activity History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" id="timeline_content"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // DATA DARI PHP
    const projects = <?php echo json_encode($list_projects); ?>;
    const activationsRaw = <?php echo json_encode($activations_raw ?? []); ?>;
    const terminationsRaw = <?php echo json_encode($terminations_raw ?? []); ?>;
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;

    // 1. OPEN MODAL UPLOAD
    function openMasterModal() { new bootstrap.Modal(document.getElementById('modalMaster')).show(); }

    // 2. FILENAME DISPLAY
    function handleFile(input) {
        if(input.files && input.files[0]) {
            document.getElementById('fileNameDisplay').innerText = input.files[0].name;
            document.getElementById('fileNameDisplay').className = "fw-bold mt-2 text-primary";
        }
    }

    // 3. AUTO LINK
    function autoFillClient(selectObj) {
        let opt = selectObj.options[selectObj.selectedIndex];
        let compId = opt.getAttribute('data-comp');
        let projId = opt.getAttribute('data-proj');
        let batch = opt.getAttribute('data-batch');

        if(compId) { document.getElementById('inj_client').value = compId; filterProjects(compId); }
        if(projId) { setTimeout(() => { document.getElementById('inj_project').value = projId; }, 50); }
        if(batch) document.getElementById('inj_batch').value = batch; else document.getElementById('inj_batch').value = 'BATCH 1';
    }

    function filterProjects(compId) {
        let $sel = $('#inj_project');
        $sel.empty().append('<option value="">-- Select Project --</option>');
        if (compId) {
            let filtered = projects.filter(p => p.company_id == compId);
            filtered.forEach(p => { $sel.append(`<option value="${p.id}">${p.project_name}</option>`); });
        }
    }

    // 4. ACTION MODAL
    let maxLimit = 0;
    function openActionModal(type, data) {
        // Reset
        $('#act_qty_input').val(''); $('#act_error_msg').hide(); $('#act_btn_save').prop('disabled', false);
        $('#sim_detail_box input').val(''); $('#sim_detail_box').hide(); $('#inp_msisdn').removeClass('is-invalid');

        $('#act_po_id').val(data.po_id);
        $('#act_comp_id').val(data.company_id);
        $('#act_proj_id').val(data.project_id);

        if (type === 'activate') {
            $('#act_title').text('Activate');
            $('#act_form_action').val('create_activation_simple'); 
            $('#act_qty_input').attr('name', 'active_qty'); 
            $('#act_batch_name_hidden').val(data.batch_name); 
            maxLimit = parseInt(data.rem_alloc);
            $('#act_limit_display').html(`Available: <b class="text-success">${maxLimit.toLocaleString()}</b>`);
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success');
        } else {
            $('#act_title').text('Terminate');
            $('#act_form_action').val('create_termination_simple'); 
            $('#act_qty_input').attr('name', 'terminated_qty');
            $('#term_batch_name_hidden').val(data.batch_name); 
            maxLimit = parseInt(data.curr_active);
            $('#act_limit_display').html(`Active: <b class="text-danger">${maxLimit.toLocaleString()}</b>`);
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger');
        }

        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) { $(this).addClass('is-invalid'); $('#act_error_msg').show(); $('#act_btn_save').prop('disabled', true); }
            else { $(this).removeClass('is-invalid'); $('#act_error_msg').hide(); $('#act_btn_save').prop('disabled', false); }
        });

        $('#act_btn_save').off('click').on('click', function(e) {
            let hasDetail = false;
            $('#sim_detail_box input').each(function(){ if($(this).val().trim()!=='') hasDetail=true; });
            if(hasDetail && $('#inp_msisdn').val().trim()==='') {
                e.preventDefault(); $('#inp_msisdn').addClass('is-invalid');
                if(!$('#sim_detail_box').is(':visible')) $('#sim_detail_box').slideDown();
                alert("MSISDN is required if detail is filled!");
            }
        });

        new bootstrap.Modal(document.getElementById('modalAction')).show();
    }

    // 5. HISTORY LOG (FIXED LOGIC)
    function openDetailModal(data) {
        // Filter strictly by PO Provider ID
        let acts = activationsRaw.filter(i => i.po_provider_id == data.po_id);
        let terms = terminationsRaw.filter(i => i.po_provider_id == data.po_id);
        
        let combined = [];
        acts.forEach(i => combined.push({type:'act', date:i.activation_date, qty:i.active_qty, batch:i.activation_batch}));
        terms.forEach(i => combined.push({type:'term', date:i.termination_date, qty:i.terminated_qty, batch:i.termination_batch}));
        
        // Sort descending
        combined.sort((a,b) => new Date(b.date) - new Date(a.date));

        let html = '<div class="timeline">';
        if(combined.length===0) html += '<div class="text-muted small text-center py-3">No activity logs found for this PO.</div>';
        
        combined.forEach(log => {
            let isAct = log.type==='act';
            let dotClass = isAct ? 'act' : 'term';
            let label = isAct ? 'Activation' : 'Termination';
            let color = isAct ? 'text-success' : 'text-danger';
            let sign = isAct ? '+' : '-';
            
            html += `
            <div class="timeline-item">
                <div class="timeline-dot ${dotClass}"></div>
                <div class="time-date">${log.date}</div>
                <div class="timeline-content">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-bold ${color}">${label}</span>
                            <div class="small text-muted">${log.batch}</div>
                        </div>
                        <div class="fw-bold fs-6">${sign} ${parseInt(log.qty).toLocaleString()}</div>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        
        $('#timeline_content').html(html);
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // 6. CHART
    document.addEventListener('DOMContentLoaded', function () {
        if(typeof chartLabels !== 'undefined' && chartLabels.length > 0){
             var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'Inter' },
                colors: ['#10b981', '#ef4444'], stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels, labels:{style:{fontSize:'10px'}} },
                dataLabels: { enabled: false }, grid: { borderColor: '#f1f5f9' }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>