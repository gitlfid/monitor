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

// Helper Function untuk mencegah error htmlspecialchars null
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// =========================================================================
// 2. FETCH DATA FOR DROPDOWNS (FILTERED & AUTO-LINK)
// =========================================================================
$list_providers = [];
$list_clients   = [];
$list_projects  = [];

if ($db) {
    // A. FILTER PROVIDER PO:
    // Hanya tampilkan PO Provider yang BELUM pernah di-inject (belum ada di tabel sim_activations)
    // Sekaligus join ke Client PO untuk mendapatkan data Client/Project otomatis (Auto-Link)
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

    // B. LIST MASTER DATA (Untuk Dropdown Client/Project)
    $list_clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $list_projects = $db->query("SELECT id, company_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// =========================================================================
// 3. FETCH RAW DATA (CHART & LOGS)
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
    } catch (Exception $e) { /* Silent */ }
}

// Generate Chart Data
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
// 4. MAIN DASHBOARD DATA (GROUPED BY PO)
// =========================================================================
$dashboard_data = [];
if ($db) {
    // Query Utama: Menampilkan PO yang sudah aktif (sudah di-inject)
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
                -- FILTER: Hanya tampilkan yang sudah ada di tabel activation
                HAVING po.id IN (SELECT DISTINCT po_provider_id FROM sim_activations)
                ORDER BY po.id DESC";
    
    try {
        $stmt = $db->query($sql_main);
        if($stmt) $dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}
?>

<style>
    /* PROFESSIONAL UI SYSTEM */
    body { background-color: #f8fafc; font-family: 'Inter', system-ui, sans-serif; }
    
    /* CARDS */
    .card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 24px; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 24px; border-radius: 12px 12px 0 0 !important; }
    
    /* TABLE */
    .table-pro { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-pro th { background-color: #f1f5f9; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
    .table-pro td { padding: 20px; vertical-align: top; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: #1e293b; background: #fff; }
    .table-pro tr:hover td { background-color: #f8fafc; }

    /* INFO LABELS (REPLACES ICONS) */
    .info-label { font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px; }
    .info-value { font-weight: 600; color: #334155; }

    /* BADGES */
    .badge-prov { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 0.8rem; }
    .badge-cli { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 3px 8px; border-radius: 6px; font-family: monospace; font-size: 0.8rem; }
    .badge-batch { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }

    /* LIFECYCLE STATUS BAR */
    .lifecycle-container { background: #fff; }
    .lifecycle-stats { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .progress-stacked { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: #e2e8f0; margin-bottom: 12px; }
    .bar-seg { height: 100%; transition: width 0.6s ease; }
    .bg-act { background: #10b981; } 
    .bg-term { background: #ef4444; } 
    .bg-rem { background: #cbd5e1; }

    /* ACTION BUTTONS */
    .btn-quick { padding: 6px 12px; font-size: 0.75rem; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; transition: all 0.2s; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
    .btn-quick-act { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .btn-quick-act:hover { background: #059669; color: #fff; border-color: #059669; }
    .btn-quick-term { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .btn-quick-term:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
    .btn-quick.disabled { opacity: 0.5; pointer-events: none; filter: grayscale(100%); }
    .btn-log { background: #fff; color: #475569; border-color: #cbd5e1; padding: 6px 12px; font-size: 0.75rem; font-weight: 600; border-radius: 6px; }
    .btn-log:hover { background: #f1f5f9; border-color: #94a3b8; }
    
    /* MASTER BUTTON */
    .btn-master { background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    .btn-master:hover { background: #4338ca; transform: translateY(-1px); color: white; }

    /* UPLOAD BOX */
    .upload-zone { border: 2px dashed #94a3b8; background: #f1f5f9; border-radius: 8px; text-align: center; padding: 30px; position: relative; cursor: pointer; transition: 0.2s; }
    .upload-zone:hover { border-color: #4f46e5; background: #eef2ff; }
    .upload-zone input { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }

    /* TIMELINE LOGS */
    .timeline-box { position: relative; padding-left: 20px; border-left: 2px solid #e5e7eb; margin-left: 10px; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: -26px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2px solid #9ca3af; }
    .timeline-item.act::before { border-color: #10b981; background: #10b981; }
    .timeline-item.term::before { border-color: #ef4444; background: #ef4444; }
    .timeline-date { font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 4px; display: block; }
    .timeline-card { background: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #f3f4f6; }
    
    /* SIM DETAIL INPUT */
    .sim-detail-box { border: 1px dashed #cbd5e1; background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 15px; display: none; }
    .sim-toggle-btn { font-size: 0.8rem; font-weight: 600; color: #6366f1; cursor: pointer; display: flex; align-items: center; gap: 5px; margin-top: 10px; }
    .sim-toggle-btn:hover { text-decoration: underline; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">SIM Lifecycle Dashboard</h3>
            <p class="text-muted mb-0 small">Unified Management for Activation & Termination.</p>
        </div>
        <div>
            <button class="btn-master" onclick="openMasterModal()">
                <i class="bi bi-cloud-arrow-up-fill"></i> Inject Master Data (New Activation)
            </button>
        </div>
    </div>
</div>

<section>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Trends</h6>
            <div id="lifecycleChart" style="height: 280px;"></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold text-dark m-0"><i class="bi bi-hdd-stack me-2"></i> Active SIM Pools</h6>
        </div>
        <div class="table-responsive">
            <table class="table w-100 mb-0 align-middle">
                <thead class="bg-light text-uppercase text-muted" style="font-size: 0.75rem;">
                    <tr>
                        <th class="py-3 ps-4" width="30%">Entity Information</th>
                        <th class="py-3" width="25%">Source Hierarchy</th>
                        <th class="py-3" width="35%">Lifecycle Status</th>
                        <th class="py-3 text-center" width="10%">History</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">No active batches found. Click "Inject Master Data" to start.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            $total = (int)$row['total_allocation'];
                            $termHist = (int)$row['total_terminated_hist'];
                            $currActive = max(0, (int)$row['total_activated_hist'] - $termHist);
                            
                            $pctTerm = ($total > 0) ? ($termHist / $total) * 100 : 0;
                            $pctActive = ($total > 0) ? ($currActive / $total) * 100 : 0;
                            $pctEmpty = 100 - $pctTerm - $pctActive;
                            
                            // Data JSON for Modals
                            $rowJson = htmlspecialchars(json_encode([
                                'po_id' => $row['po_id'],
                                'po_number' => $row['provider_po'],
                                'batch_name' => $row['batch_name'],
                                'company_id' => $row['company_id'],
                                'project_id' => $row['project_id'],
                                'curr_active' => $currActive,
                                'total_alloc' => $total,
                                'rem_alloc' => max(0, $total - (int)$row['total_activated_hist'])
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="mb-2">
                                    <span class="info-label">Client Name</span>
                                    <div class="info-value"><?= e($row['company_name']) ?></div>
                                </div>
                                <div>
                                    <span class="info-label">Project</span>
                                    <div class="info-value text-secondary small"><i class="bi bi-folder2-open me-1"></i> <?= e($row['project_name']) ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="mb-2">
                                    <span class="info-label">Provider Source</span>
                                    <span class="badge-prov"><?= e($row['provider_po']) ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <div>
                                        <span class="info-label">Client PO</span>
                                        <span class="badge-cli"><?= e($row['client_po']) ?: 'N/A' ?></span>
                                    </div>
                                    <div>
                                        <span class="info-label">Batch ID</span>
                                        <span class="badge-batch"><?= e($row['batch_name']) ?: 'BATCH 1' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="lifecycle-container">
                                    <div class="lifecycle-stats">
                                        <span class="text-muted small">Total: <span class="text-dark fw-bold"><?= number_format($total) ?></span></span>
                                        <div>
                                            <span class="text-success me-2">Act: <?= number_format($currActive) ?></span>
                                            <span class="text-danger">Term: <?= number_format($termHist) ?></span>
                                        </div>
                                    </div>
                                    <div class="progress-stacked mb-3">
                                        <div class="bar-seg bg-term" style="width: <?= $pctTerm ?>%"></div>
                                        <div class="bar-seg bg-act" style="width: <?= $pctActive ?>%"></div>
                                        <div class="bar-seg bg-rem" style="width: <?= $pctEmpty ?>%"></div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn-quick btn-quick-act" onclick='openActionModal("activate", <?= $rowJson ?>)'>
                                            <i class="bi bi-plus-lg me-1"></i> Activate
                                        </button>
                                        <button class="btn-quick btn-quick-term" onclick='openActionModal("terminate", <?= $rowJson ?>)'>
                                            <i class="bi bi-x-lg me-1"></i> Terminate
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <button class="btn-log" onclick='openDetailModal(<?= $rowJson ?>)'>
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
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0">
            <input type="hidden" name="action" value="inject_master_bulk">

            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up-fill me-2"></i> Inject Master Data</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-bold text-uppercase text-secondary small">1. Select Source (Provider PO)</label>
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
                    <div class="form-text text-muted small"><i class="bi bi-info-circle"></i> Only POs not yet injected are shown.</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase text-secondary small">Client (Destination)</label>
                        <select name="company_id" id="inj_client" class="form-select bg-light" required onchange="filterProjects(this.value)">
                            <option value="">-- Auto Select --</option>
                            <?php foreach($list_clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-uppercase text-secondary small">Project</label>
                        <select name="project_id" id="inj_project" class="form-select bg-light">
                            <option value="">-- Select Project --</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-uppercase text-secondary small">2. Upload Data File</label>
                    <div class="upload-zone">
                        <input type="file" name="upload_file" accept=".csv, .xlsx, .xls" required onchange="handleFile(this)">
                        <i class="bi bi-file-earmark-spreadsheet text-primary display-4"></i>
                        <h6 class="fw-bold mt-2 text-dark" id="fileNameDisplay">Drag & Drop CSV/Excel here or Click</h6>
                        <p class="text-muted small mb-0">Format: <code>SN, ICCID, IMSI, MSISDN</code> (MSISDN Mandatory)</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Batch Name</label>
                        <input type="text" name="activation_batch" id="inj_batch" class="form-control" placeholder="e.g. BATCH 1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Inject Date</label>
                        <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">Start Injection</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAction" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0">
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
                    <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-layers-fill me-2 text-secondary"></i>
                        <strong class="text-dark" id="act_po_display">-</strong>
                    </div>
                    <div class="small text-muted" id="act_limit_display">Checking limits...</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Transaction Date</label>
                    <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small" id="act_qty_label">Quantity</label>
                    <div class="input-group">
                        <input type="number" name="qty_input" id="act_qty_input" class="form-control fw-bold" required min="1" placeholder="0">
                        <span class="input-group-text text-muted">SIMs</span>
                    </div>
                    <div class="form-text text-danger fw-bold small mt-1" id="act_error_msg" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Cannot exceed limit!
                    </div>
                </div>

                <div class="sim-toggle-btn" onclick="$('#sim_detail_box').slideToggle()">
                    <i class="bi bi-sim"></i> Input Specific SIM Details (Optional) <i class="bi bi-chevron-down ms-1" style="font-size:0.7em"></i>
                </div>
                
                <div id="sim_detail_box" class="sim-detail-box">
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark small">MSISDN <span class="text-danger">*</span></label>
                        <input type="text" name="msisdn" id="inp_msisdn" class="form-control form-control-sm" placeholder="e.g. 62812xxxx (Mandatory if filled)">
                        <div class="form-text text-muted" style="font-size:0.7rem">Required if providing details.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark small">ICCID</label>
                            <input type="text" name="iccid" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark small">IMSI</label>
                            <input type="text" name="imsi" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark small">Serial Number (SN)</label>
                            <input type="text" name="sn" class="form-control form-control-sm" placeholder="Optional">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-light text-muted fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn fw-bold px-4" id="act_btn_save">Confirm</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header border-bottom bg-white">
                <h6 class="modal-title fw-bold text-dark">History Logs</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="mb-4">
                    <h5 class="fw-bold mb-1" id="det_po">-</h5>
                    <p class="text-muted small m-0" id="det_client">-</p>
                </div>
                <h6 class="text-uppercase text-muted fw-bold small mb-3">Activity Timeline</h6>
                <div class="timeline-box" id="timeline_content"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // DATA DARI PHP UNTUK JS
    const projects = <?php echo json_encode($list_projects); ?>;
    const activationsRaw = <?php echo json_encode($activations_raw ?? []); ?>;
    const terminationsRaw = <?php echo json_encode($terminations_raw ?? []); ?>;
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;

    // 1. OPEN MODAL MASTER
    function openMasterModal() {
        var myModal = new bootstrap.Modal(document.getElementById('modalMaster'));
        myModal.show();
    }

    // 2. FILE NAME DISPLAY
    function handleFile(input) {
        if(input.files && input.files[0]) {
            document.getElementById('fileNameDisplay').innerText = input.files[0].name;
            document.getElementById('fileNameDisplay').classList.add('text-success');
        }
    }

    // 3. FILTER PROJECTS
    function filterProjects(compId) {
        let $sel = $('#inj_project');
        $sel.empty().append('<option value="">-- Select Project --</option>');
        
        if (compId) {
            let filtered = projects.filter(p => p.company_id == compId);
            filtered.forEach(p => {
                $sel.append(`<option value="${p.id}">${p.project_name}</option>`);
            });
        }
    }

    // 4. AUTO LINK (AUTOSELECT)
    function autoFillClient(selectObj) {
        let opt = selectObj.options[selectObj.selectedIndex];
        let compId = opt.getAttribute('data-comp');
        let projId = opt.getAttribute('data-proj');
        let batch = opt.getAttribute('data-batch');

        // A. Set Client Value & Trigger Filter
        if(compId) {
            document.getElementById('inj_client').value = compId;
            filterProjects(compId); // TRIGGER FILTER
        }
        
        // B. Set Project Value (After delay)
        if(projId) {
            setTimeout(() => {
                document.getElementById('inj_project').value = projId;
            }, 100);
        }

        // C. Set Batch
        if(batch) document.getElementById('inj_batch').value = batch;
        else document.getElementById('inj_batch').value = 'BATCH 1';
    }

    // 5. ACTION MODAL
    let maxLimit = 0;
    function openActionModal(type, data) {
        // Reset Inputs
        $('#act_qty_input').val('').removeClass('is-invalid');
        $('#act_error_msg').hide();
        $('#act_btn_save').prop('disabled', false);
        
        // Reset SIM Inputs
        $('#sim_detail_box input').val(''); 
        $('#sim_detail_box').hide();
        $('#inp_msisdn').removeClass('is-invalid');

        // Fill Hidden Fields
        $('#act_po_id').val(data.po_id);
        $('#act_comp_id').val(data.company_id);
        $('#act_proj_id').val(data.project_id);
        
        // UI Display
        $('#act_po_display').text(data.po_number + " (" + data.batch_name + ")");
        
        if (type === 'activate') {
            $('#act_title').text('New Activation');
            $('#act_header').removeClass('bg-danger').addClass('bg-success');
            $('#act_form_action').val('create_activation_simple'); 
            $('#act_qty_input').attr('name', 'active_qty'); 
            $('#act_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.rem_alloc);
            $('#act_limit_display').html(`Available: <b class="text-success">${maxLimit.toLocaleString()}</b> (of ${parseInt(data.total_alloc).toLocaleString()})`);
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success').text('Process Activation');
        } 
        else {
            $('#act_title').text('New Termination');
            $('#act_header').removeClass('bg-success').addClass('bg-danger');
            $('#act_form_action').val('create_termination_simple'); 
            $('#act_qty_input').attr('name', 'terminated_qty');
            $('#term_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.curr_active);
            $('#act_limit_display').html(`Active SIMs: <b class="text-danger">${maxLimit.toLocaleString()}</b> (Ready to Terminate)`);
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger').text('Process Termination');
        }

        // Qty Validation
        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) {
                $(this).addClass('is-invalid');
                $('#act_error_msg').text(`Limit exceeded! Max: ${maxLimit.toLocaleString()}`).show();
                $('#act_btn_save').prop('disabled', true);
            } else if (val <= 0) {
                $('#act_btn_save').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid');
                $('#act_error_msg').hide();
                $('#act_btn_save').prop('disabled', false);
            }
        });

        // SIM Detail Validation
        $('#act_btn_save').off('click').on('click', function(e) {
            let hasSimDetail = false;
            $('#sim_detail_box input').each(function() {
                if($(this).val().trim() !== '') hasSimDetail = true;
            });

            if(hasSimDetail && $('#inp_msisdn').val().trim() === '') {
                e.preventDefault();
                $('#inp_msisdn').addClass('is-invalid');
                if(!$('#sim_detail_box').is(':visible')) $('#sim_detail_box').slideDown();
                alert("MSISDN is mandatory if you provide SIM details!");
            }
        });

        var myModal = new bootstrap.Modal(document.getElementById('modalAction'));
        myModal.show();
    }

    // 6. TIMELINE LOGS
    function openDetailModal(data) {
        $('#det_po').text(data.po_number);
        $('#det_client').text(data.company_name + " / " + data.batch_name);
        
        let acts = activationsRaw.filter(item => item.po_provider_id == data.po_id);
        let terms = terminationsRaw.filter(item => item.po_provider_id == data.po_id);
        
        let combined = [];
        acts.forEach(item => combined.push({ type: 'act', date: item.activation_date, qty: item.active_qty }));
        terms.forEach(item => combined.push({ type: 'term', date: item.termination_date, qty: item.terminated_qty }));
        
        combined.sort((a, b) => new Date(b.date) - new Date(a.date));
        
        let html = '';
        if(combined.length === 0) {
            html = '<div class="text-center text-muted py-3">No logs found.</div>';
        } else {
            combined.forEach(log => {
                let isAct = log.type === 'act';
                let colorClass = isAct ? 'act' : 'term';
                let badgeClass = isAct ? 'bg-success' : 'bg-danger';
                let label = isAct ? 'Activation' : 'Termination';
                let sign = isAct ? '+' : '-';
                
                html += `
                <div class="timeline-item ${colorClass}">
                    <span class="timeline-date">${log.date}</span>
                    <div class="timeline-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">${label}</span>
                            <span class="badge ${badgeClass}">${sign} ${parseInt(log.qty).toLocaleString()}</span>
                        </div>
                    </div>
                </div>`;
            });
        }
        
        $('#timeline_content').html(html);
        var myModal = new bootstrap.Modal(document.getElementById('modalDetail'));
        myModal.show();
    }
    
    // 7. CHART RENDER
    document.addEventListener('DOMContentLoaded', function () {
        if(typeof chartLabels !== 'undefined' && chartLabels.length > 0){
             var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 280, toolbar: { show: false } },
                colors: ['#10b981', '#ef4444'], stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>