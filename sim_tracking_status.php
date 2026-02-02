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
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// =========================================================================
// 2. FETCH DATA FOR DROPDOWNS (NEW ACTIVATION)
// =========================================================================
$list_providers = [];
$list_clients   = [];
$list_projects  = [];

if ($db) {
    // Ambil Provider PO yang masih punya sisa kuota (Optional logic)
    $sql_prov = "SELECT id, po_number, batch_name, sim_qty FROM sim_tracking_po WHERE type='provider' ORDER BY id DESC";
    $list_providers = $db->query($sql_prov)->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Client
    $list_clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil Projects (Untuk JS filtering nanti)
    $list_projects = $db->query("SELECT id, company_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// =========================================================================
// 3. FETCH RAW DATA (CHART)
// =========================================================================
$activations_raw = [];
$terminations_raw = [];
$chart_data_act = []; 
$chart_data_term = [];

if ($db) {
    try {
        $sql_act_raw = "SELECT * FROM sim_activations ORDER BY activation_date DESC";
        $stmt = $db->query($sql_act_raw);
        if($stmt) $activations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_term_raw = "SELECT * FROM sim_terminations ORDER BY termination_date DESC";
        $stmt = $db->query($sql_term_raw);
        if($stmt) $terminations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}

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
$js_labels = []; $js_series_act = []; $js_series_term = [];
foreach ($all_dates as $dateKey) {
    $js_labels[] = date('d M Y', strtotime($dateKey));
    $js_series_act[] = $chart_data_act[$dateKey] ?? 0;
    $js_series_term[] = $chart_data_term[$dateKey] ?? 0;
}

// =========================================================================
// 4. MAIN DASHBOARD DATA
// =========================================================================
$dashboard_data = [];
if ($db) {
    $sql_main = "SELECT 
                    po.id as po_id,
                    po.po_number as provider_po,
                    po.batch_name as batch_name,
                    po.sim_qty as total_allocation,
                    client_po.po_number as client_po,
                    c.company_name,
                    p.project_name,
                    c.id as company_id,
                    p.id as project_id,
                    
                    (SELECT COALESCE(SUM(active_qty + inactive_qty), 0) 
                     FROM sim_activations WHERE po_provider_id = po.id) as total_activated_hist,
                    
                    (SELECT COALESCE(SUM(terminated_qty), 0) 
                     FROM sim_terminations WHERE po_provider_id = po.id) as total_terminated_hist

                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider'
                ORDER BY po.id DESC";
    
    try {
        $stmt = $db->query($sql_main);
        if($stmt) $dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}
?>

<style>
    /* PROFESSIONAL UI VARIABLES */
    :root {
        --primary-soft: #eff6ff; --primary-border: #bfdbfe; --primary-text: #1d4ed8;
        --gray-soft: #f9fafb; --gray-border: #e5e7eb; --gray-text: #6b7280;
        --success-soft: #ecfdf5; --success-text: #047857;
        --danger-soft: #fef2f2; --danger-text: #b91c1c;
    }
    body { background-color: #f3f4f6; font-family: 'Inter', system-ui, sans-serif; }
    
    /* CARD & LAYOUT */
    .card { border: 1px solid var(--gray-border); border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); background: #fff; margin-bottom: 24px; }
    .card-header { background: #fff; border-bottom: 1px solid var(--gray-border); padding: 20px 24px; border-radius: 12px 12px 0 0 !important; }
    
    /* TABLE STYLING */
    .table-pro { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-pro th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 20px; border-bottom: 1px solid var(--gray-border); }
    .table-pro td { padding: 20px; vertical-align: top; border-bottom: 1px solid var(--gray-border); font-size: 0.9rem; color: #1e293b; background: #fff; }
    .table-pro tr:hover td { background-color: #f8fafc; transition: background 0.2s; }

    /* INFO BOXES (REPLACEMENT FOR ICONS) */
    .info-group { margin-bottom: 10px; }
    .info-label { font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 2px; }
    .info-value { font-weight: 600; font-size: 0.9rem; color: #334155; display: flex; align-items: center; gap: 6px; }
    
    .badge-soft { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; }
    .badge-prov { background: var(--primary-soft); color: var(--primary-text); border: 1px solid var(--primary-border); }
    .badge-cli { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
    .badge-batch { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

    /* LIFECYCLE BAR */
    .lifecycle-wrapper { background: #fff; border: 1px solid var(--gray-border); border-radius: 8px; padding: 12px; }
    .lifecycle-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px; }
    .stat-main { font-size: 0.8rem; color: #64748b; }
    .stat-val { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
    
    .progress-stacked { display: flex; height: 8px; border-radius: 4px; overflow: hidden; background: #f1f5f9; margin-bottom: 12px; }
    .bar-seg { height: 100%; transition: width 0.6s ease; }
    .bg-act { background-color: #10b981; }
    .bg-term { background-color: #ef4444; }
    .bg-rem { background-color: #e2e8f0; }

    /* ACTION BUTTONS */
    .btn-action { padding: 6px 12px; font-size: 0.75rem; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; transition: all 0.2s; border: 1px solid transparent; text-decoration: none; }
    .btn-act { background: var(--success-soft); color: var(--success-text); border-color: #a7f3d0; }
    .btn-act:hover { background: #d1fae5; border-color: #34d399; }
    .btn-term { background: var(--danger-soft); color: var(--danger-text); border-color: #fecaca; }
    .btn-term:hover { background: #fee2e2; border-color: #f87171; }
    .btn-log { background: #fff; color: #475569; border-color: #cbd5e1; }
    .btn-log:hover { background: #f8fafc; border-color: #94a3b8; }
    .disabled { opacity: 0.5; pointer-events: none; filter: grayscale(1); }

    /* MASTER BUTTON */
    .btn-master { background: #4f46e5; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2); transition: 0.2s; }
    .btn-master:hover { background: #4338ca; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.3); color: #fff; }

    /* MODAL */
    .modal-content { border: none; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    .modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background: #fff; border-radius: 16px 16px 0 0; }
    .form-section-title { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 8px; margin-top: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">SIM Lifecycle Dashboard</h3>
            <p class="text-muted mb-0 small">Centralized Management for Activation & Termination.</p>
        </div>
        <div>
            <button class="btn-master" onclick="openMasterModal()">
                <i class="bi bi-rocket-takeoff-fill me-2"></i> Inject Master Data
            </button>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm border-0">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Trends</h6>
            <div id="lifecycleChart" style="height: 280px;"></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark m-0"><i class="bi bi-hdd-stack me-2"></i> Active Pools & Batches</h6>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table-pro">
                <thead>
                    <tr>
                        <th width="30%">Entity Information</th>
                        <th width="30%">Source Hierarchy</th>
                        <th width="30%">Lifecycle Status</th>
                        <th width="10%" class="text-center">History</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">No data yet. Click "Inject Master Data" to start.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            $total = (int)$row['total_allocation'];
                            $actHist = (int)$row['total_activated_hist'];
                            $termHist = (int)$row['total_terminated_hist'];
                            
                            $currActive = max(0, $actHist - $termHist);
                            $remAlloc = max(0, $total - $actHist);

                            if($total > 0) {
                                $pctTerm = ($termHist / $total) * 100;
                                $pctActive = ($currActive / $total) * 100;
                                $pctEmpty = 100 - $pctTerm - $pctActive;
                            } else { $pctTerm = 0; $pctActive = 0; $pctEmpty = 100; }

                            $rowJson = htmlspecialchars(json_encode([
                                'po_id' => $row['po_id'],
                                'po_number' => $row['provider_po'],
                                'batch_name' => $row['batch_name'],
                                'company_id' => $row['company_id'],
                                'project_id' => $row['project_id'],
                                'rem_alloc' => $remAlloc,
                                'curr_active' => $currActive,
                                'total' => $total
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <div class="info-group">
                                    <span class="info-label">Client Name</span>
                                    <div class="info-value text-dark"><?= e($row['company_name']) ?></div>
                                </div>
                                <div class="info-group">
                                    <span class="info-label">Project Reference</span>
                                    <div class="info-value text-secondary"><i class="bi bi-folder2-open me-1"></i> <?= e($row['project_name']) ?></div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="info-group">
                                    <span class="info-label">Provider Source PO</span>
                                    <div class="info-value"><span class="badge-prov"><?= e($row['provider_po']) ?></span></div>
                                </div>
                                <div class="d-flex gap-3">
                                    <div class="info-group">
                                        <span class="info-label">Client PO</span>
                                        <div class="info-value"><span class="badge-cli"><?= e($row['client_po']) ?: '-' ?></span></div>
                                    </div>
                                    <div class="info-group">
                                        <span class="info-label">Batch ID</span>
                                        <div class="info-value"><span class="badge-batch"><?= e($row['batch_name']) ?: 'BATCH 1' ?></span></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="lifecycle-wrapper">
                                    <div class="lifecycle-header">
                                        <div class="stat-main">Total Allocation: <span class="stat-val"><?= number_format($total) ?></span></div>
                                        <div class="d-flex gap-3 small fw-bold">
                                            <span class="text-success">Active: <?= number_format($currActive) ?></span>
                                            <span class="text-danger">Term: <?= number_format($termHist) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-stacked" title="Status Distribution">
                                        <div class="bar-seg bg-term" style="width: <?= $pctTerm ?>%"></div>
                                        <div class="bar-seg bg-act" style="width: <?= $pctActive ?>%"></div>
                                        <div class="bar-seg bg-rem" style="width: <?= $pctEmpty ?>%"></div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-2">
                                        <button class="btn-action btn-act <?= ($remAlloc <= 0) ? 'disabled' : '' ?>" onclick='openActionModal("activate", <?= $rowJson ?>)'>
                                            <i class="bi bi-plus-lg me-1"></i> Activate
                                        </button>
                                        <button class="btn-action btn-term <?= ($currActive <= 0) ? 'disabled' : '' ?>" onclick='openActionModal("terminate", <?= $rowJson ?>)'>
                                            <i class="bi bi-x-lg me-1"></i> Terminate
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <button class="btn-action btn-log" onclick='openDetailModal(<?= $rowJson ?>)'>
                                    <i class="bi bi-clock-history me-1"></i> Logs
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
        <form action="process_sim_tracking.php" method="POST" class="modal-content">
            <input type="hidden" name="action" value="create_activation_simple">
            <input type="hidden" name="is_master_inject" value="1"> 

            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-rocket-takeoff me-2"></i> Inject Master Data (Start New Lifecycle)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="alert alert-primary bg-primary bg-opacity-10 border-0 fs-6">
                    <i class="bi bi-info-circle-fill me-2"></i> Use this form to start tracking a new Batch from a Provider PO.
                </div>

                <div class="form-section-title">1. Source & Destination</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Provider PO (Source)</label>
                        <select name="po_provider_id" class="form-select fw-bold border-primary" required>
                            <option value="">-- Select Provider PO --</option>
                            <?php foreach($list_providers as $prov): ?>
                                <option value="<?= $prov['id'] ?>">
                                    <?= $prov['po_number'] ?> (Total: <?= number_format($prov['sim_qty']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client (Destination)</label>
                        <select name="company_id" id="master_company" class="form-select" required onchange="filterProjects(this.value)">
                            <option value="">-- Select Client --</option>
                            <?php foreach($list_clients as $cli): ?>
                                <option value="<?= $cli['id'] ?>"><?= $cli['company_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Project Context</label>
                        <select name="project_id" id="master_project" class="form-select">
                            <option value="">-- Select Project (Auto Filtered) --</option>
                        </select>
                    </div>
                </div>

                <div class="form-section-title">2. Batch Configuration</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Batch Name</label>
                        <input type="text" name="activation_batch" class="form-control" placeholder="e.g. BATCH 1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Initial Activation Date</label>
                        <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-success">Total Allocation</label>
                        <input type="number" name="active_qty" class="form-control fw-bold border-success" placeholder="Qty" required min="1">
                        <div class="form-text small">This creates initial Active Qty.</div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">Inject Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAction" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content">
            <input type="hidden" name="action" id="act_form_action"> 
            <input type="hidden" name="po_provider_id" id="act_po_id">
            <input type="hidden" name="company_id" id="act_comp_id">
            <input type="hidden" name="project_id" id="act_proj_id">
            <input type="hidden" name="activation_batch" id="act_batch_name_hidden"> 
            <input type="hidden" name="termination_batch" id="term_batch_name_hidden"> 

            <div class="modal-header text-white" id="act_header">
                <h6 class="modal-title fw-bold" id="act_title">Action</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-bold">CONTEXT:</span>
                        <span class="badge bg-secondary" id="act_po_display">-</span>
                    </div>
                    <div class="fs-6 fw-bold" id="act_limit_display">...</div>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small">Date</label>
                        <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Quantity</label>
                        <input type="number" name="qty_input" id="act_qty_input" class="form-control fw-bold" required min="1">
                        <div class="text-danger small fw-bold mt-1" id="act_error_msg" style="display:none;">Exceeds Limit!</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="submit" class="btn fw-bold px-4 w-100" id="act_btn_save">Confirm Action</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-white border-bottom">
                <h6 class="modal-title fw-bold">Transaction History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light" id="timeline_content"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // DATA PREP
    const allProjects = <?php echo json_encode($list_projects); ?>;
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;
    const actsRaw = <?php echo json_encode($activations_raw ?? []); ?>;
    const termsRaw = <?php echo json_encode($terminations_raw ?? []); ?>;

    // 1. CHART
    document.addEventListener('DOMContentLoaded', function () {
        if(chartLabels.length > 0){
            var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                colors: ['#10b981', '#ef4444'], 
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '10px' } } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f5f9' }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });

    // 2. MASTER MODAL PROJECT FILTER
    function openMasterModal() {
        new bootstrap.Modal(document.getElementById('modalMaster')).show();
    }

    function filterProjects(compId) {
        let $sel = $('#master_project');
        $sel.empty().append('<option value="">-- Select Project --</option>');
        if(compId) {
            let filtered = allProjects.filter(p => p.company_id == compId);
            filtered.forEach(p => {
                $sel.append(`<option value="${p.id}">${p.project_name}</option>`);
            });
        }
    }

    // 3. ACTION MODAL
    let maxLimit = 0;
    function openActionModal(type, data) {
        $('#act_qty_input').val('').removeClass('is-invalid');
        $('#act_error_msg').hide();
        $('#act_btn_save').prop('disabled', false);
        
        $('#act_po_id').val(data.po_id);
        $('#act_comp_id').val(data.company_id);
        $('#act_proj_id').val(data.project_id);
        $('#act_po_display').text(data.po_number);

        if (type === 'activate') {
            $('#act_title').text('Add Activation');
            $('#act_header').removeClass('bg-danger').addClass('bg-success');
            $('#act_form_action').val('create_activation_simple'); 
            $('#act_qty_input').attr('name', 'active_qty'); 
            $('#act_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.rem_alloc);
            $('#act_limit_display').html(`Available: <b class="text-success">${maxLimit.toLocaleString()}</b>`);
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success');
        } else {
            $('#act_title').text('Add Termination');
            $('#act_header').removeClass('bg-success').addClass('bg-danger');
            $('#act_form_action').val('create_termination_simple'); 
            $('#act_qty_input').attr('name', 'terminated_qty');
            $('#term_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.curr_active);
            $('#act_limit_display').html(`Active: <b class="text-danger">${maxLimit.toLocaleString()}</b>`);
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger');
        }

        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) {
                $(this).addClass('is-invalid');
                $('#act_error_msg').show();
                $('#act_btn_save').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid');
                $('#act_error_msg').hide();
                $('#act_btn_save').prop('disabled', false);
            }
        });

        new bootstrap.Modal(document.getElementById('modalAction')).show();
    }

    // 4. TIMELINE LOGS
    function openDetailModal(data) {
        let acts = actsRaw.filter(i => i.po_provider_id == data.po_id);
        let terms = termsRaw.filter(i => i.po_provider_id == data.po_id);
        let combined = [];
        acts.forEach(i => combined.push({type:'act', date:i.activation_date, qty:i.active_qty}));
        terms.forEach(i => combined.push({type:'term', date:i.termination_date, qty:i.terminated_qty}));
        combined.sort((a,b) => new Date(b.date) - new Date(a.date));

        let html = '<div style="border-left:2px solid #e2e8f0; margin-left:10px; padding-left:20px;">';
        if(combined.length===0) html += '<div class="text-muted small">No logs yet.</div>';
        
        combined.forEach(log => {
            let isAct = log.type==='act';
            let color = isAct ? 'text-success' : 'text-danger';
            let bg = isAct ? 'bg-success' : 'bg-danger';
            let label = isAct ? 'Activated' : 'Terminated';
            let icon = isAct ? 'bi-plus-circle' : 'bi-dash-circle';
            
            html += `
            <div class="mb-3 position-relative">
                <div class="position-absolute rounded-circle ${bg}" style="width:10px; height:10px; left:-26px; top:5px;"></div>
                <div class="small text-muted fw-bold">${log.date}</div>
                <div class="card p-2 border-0 shadow-sm mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="${color} fw-bold"><i class="bi ${icon} me-1"></i> ${label}</span>
                        <span class="fw-bold text-dark">${parseInt(log.qty).toLocaleString()}</span>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        
        $('#timeline_content').html(html);
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>