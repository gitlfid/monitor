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

// =========================================================================
// 2. FETCH DATA (DROPDOWNS)
// =========================================================================
$list_providers = [];
$list_clients   = [];
$list_projects  = [];

if ($db) {
    // A. PROVIDER PO (Yg belum di-inject)
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

    // B. LIST MASTER
    $list_clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $list_projects = $db->query("SELECT id, company_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
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
                    po.sim_qty as total_pool, -- TOTAL ALLOCATION
                    client_po.po_number as client_po,
                    c.company_name,
                    p.project_name,
                    c.id as company_id,
                    p.id as project_id,
                    
                    -- Berapa kali inject/aktivasi dilakukan (Mengurangi Stok)
                    (SELECT COALESCE(SUM(active_qty + inactive_qty), 0) 
                     FROM sim_activations WHERE po_provider_id = po.id) as total_used_stock,
                    
                    -- Berapa yang sudah mati
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

// Chart Data (Placeholder logic)
$js_labels = []; $js_series_act = []; $js_series_term = [];
if ($db) {
    $rawAct = $db->query("SELECT activation_date, SUM(active_qty) as qty FROM sim_activations GROUP BY activation_date ORDER BY activation_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    $rawTerm = $db->query("SELECT termination_date, SUM(terminated_qty) as qty FROM sim_terminations GROUP BY termination_date ORDER BY termination_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $dates = [];
    foreach($rawAct as $r) $dates[$r['activation_date']] = true;
    foreach($rawTerm as $r) $dates[$r['termination_date']] = true;
    ksort($dates);
    
    foreach(array_keys($dates) as $d) {
        $js_labels[] = date('d M', strtotime($d));
        // Simple logic for chart (accumulative or daily)
        $actVal = 0; foreach($rawAct as $r) if($r['activation_date']==$d) $actVal = $r['qty'];
        $termVal = 0; foreach($rawTerm as $r) if($r['termination_date']==$d) $termVal = $r['qty'];
        $js_series_act[] = $actVal;
        $js_series_term[] = $termVal;
    }
}
?>

<style>
    /* UI SYSTEM - CLEAN & PROFESSIONAL */
    body { background-color: #f4f6f8; font-family: 'Inter', system-ui, sans-serif; }
    
    /* CARD & LAYOUT */
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #fff; margin-bottom: 24px; transition: transform 0.2s; }
    .card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .card-header { background: #fff; border-bottom: 1px solid #edf2f7; padding: 20px 24px; border-radius: 12px 12px 0 0 !important; }
    
    /* TABLE STYLING */
    .table-custom { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
    .table-custom thead th { 
        background: transparent; color: #8a92a6; font-size: 0.75rem; 
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; 
        padding: 0 20px 8px 20px; border: none; 
    }
    .table-custom tbody tr { background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border-radius: 12px; }
    .table-custom td { padding: 20px; vertical-align: top; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; }
    .table-custom td:first-child { border-left: 1px solid #f1f5f9; border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
    .table-custom td:last-child { border-right: 1px solid #f1f5f9; border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

    /* INFO BLOCKS */
    .entity-title { font-weight: 700; color: #1e293b; font-size: 1rem; margin-bottom: 4px; display: block; }
    .entity-subtitle { font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 6px; }
    
    .meta-box { background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-block; min-width: 200px; }
    .meta-label { font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 4px; }
    .meta-value { font-size: 0.85rem; font-weight: 600; color: #334155; }
    .meta-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
    .meta-row:last-child { margin-bottom: 0; }

    /* STATS GRID (LOGIC FIX UI) */
    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .stat-item { padding: 10px; border-radius: 8px; text-align: center; }
    .stat-item.stock { background: #ecfdf5; border: 1px solid #d1fae5; } /* Green for Available */
    .stat-item.active { background: #eff6ff; border: 1px solid #bfdbfe; } /* Blue for Active */
    .stat-item.term { background: #fef2f2; border: 1px solid #fecaca; } /* Red for Dead */
    
    .stat-val { font-size: 1.1rem; font-weight: 800; display: block; line-height: 1.2; }
    .stat-lbl { font-size: 0.65rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    
    .stock-text { color: #047857; }
    .active-text { color: #1d4ed8; }
    .term-text { color: #b91c1c; }

    /* PROGRESS BAR */
    .pool-bar-container { position: relative; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 8px; }
    .pool-bar { height: 100%; position: absolute; top: 0; left: 0; }
    .bar-used { background: #94a3b8; z-index: 1; } /* Base used */
    .bar-active { background: #3b82f6; z-index: 2; } /* Active on top */
    
    /* BUTTONS */
    .btn-action { width: 100%; margin-bottom: 6px; font-size: 0.8rem; font-weight: 600; padding: 8px; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s; border: 1px solid transparent; }
    .btn-act { background: #fff; color: #059669; border-color: #a7f3d0; }
    .btn-act:hover { background: #059669; color: #fff; }
    .btn-term { background: #fff; color: #dc2626; border-color: #fecaca; }
    .btn-term:hover { background: #dc2626; color: #fff; }
    .btn-log { background: #f1f5f9; color: #64748b; border: 1px solid transparent; }
    .btn-log:hover { background: #e2e8f0; color: #334155; }
    .disabled { opacity: 0.5; pointer-events: none; filter: grayscale(1); }

    /* UPLOAD MODAL */
    .upload-area { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 12px; padding: 40px; text-align: center; transition: 0.2s; position: relative; }
    .upload-area:hover { border-color: #6366f1; background: #eef2ff; }
    .upload-icon { font-size: 2.5rem; color: #94a3b8; margin-bottom: 10px; }
    
    /* MASTER BUTTON */
    .btn-master { background: #4f46e5; color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); transition: 0.2s; }
    .btn-master:hover { background: #4338ca; transform: translateY(-2px); color: white; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">SIM Lifecycle Dashboard</h3>
            <p class="text-muted mb-0 small">Monitor Availability, Activation & Termination Status.</p>
        </div>
        <div>
            <button class="btn-master" onclick="openMasterModal()">
                <i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Master Data
            </button>
        </div>
    </div>
</div>

<section>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body pt-4">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h6 class="text-primary fw-bold m-0"><i class="bi bi-graph-up-arrow me-2"></i>Traffic Overview</h6>
            </div>
            <div id="lifecycleChart" style="height: 250px;"></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th width="30%">Entity & Source</th>
                    <th width="25%">Allocation Details</th>
                    <th width="30%">Live Status</th>
                    <th width="15%" class="text-center">Quick Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($dashboard_data)): ?>
                    <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">No active pools found. Please "Upload Master Data" to begin.</td></tr>
                <?php else: ?>
                    <?php foreach($dashboard_data as $row): 
                        // --- LOGIKA STOK YANG DIPERBAIKI ---
                        // 1. Total Pool = Kapasitas Awal PO Provider
                        $totalPool = (int)$row['total_pool']; 
                        
                        // 2. Used Stock = Total yang pernah di-inject/aktivasi (Apapun statusnya sekarang)
                        //    Ini yang "MENGURANGI TOTAL" sesuai request.
                        $usedStock = (int)$row['total_used_stock'];
                        
                        // 3. Available Stock = Sisa yang belum pernah di-apa-apakan
                        $availableStock = max(0, $totalPool - $usedStock);

                        // 4. Breakdown dari Used Stock
                        $terminated = (int)$row['total_terminated'];
                        $active = max(0, $usedStock - $terminated); // Active = (Total Used) - (Sudah Mati)

                        // Data JSON untuk Modal
                        $rowJson = htmlspecialchars(json_encode([
                            'po_id' => $row['po_id'],
                            'po_number' => $row['provider_po'],
                            'batch_name' => $row['batch_name'],
                            'company_id' => $row['company_id'],
                            'project_id' => $row['project_id'],
                            'rem_alloc' => $availableStock, // Untuk Activate
                            'curr_active' => $active,       // Untuk Terminate
                            'total_alloc' => $totalPool
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr>
                        <td>
                            <span class="entity-title"><?= e($row['company_name']) ?></span>
                            <div class="entity-subtitle mb-3"><i class="bi bi-folder2-open text-primary"></i> <?= e($row['project_name']) ?></div>
                            
                            <div class="meta-box">
                                <div class="meta-row">
                                    <span class="meta-label">PROVIDER PO</span>
                                    <span class="meta-value text-primary"><?= e($row['provider_po']) ?></span>
                                </div>
                                <div class="meta-row mt-2">
                                    <span class="meta-label">BATCH ID</span>
                                    <span class="meta-value"><?= e($row['batch_name']) ?: 'BATCH 1' ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="d-flex flex-column justify-content-center h-100">
                                <div class="stats-grid">
                                    <div class="stat-item stock">
                                        <span class="stat-val stock-text"><?= number_format($availableStock) ?></span>
                                        <span class="stat-lbl stock-text">Available</span>
                                    </div>
                                    <div class="stat-item bg-light border">
                                        <span class="stat-val text-muted"><?= number_format($totalPool) ?></span>
                                        <span class="stat-lbl text-muted">Total Pool</span>
                                    </div>
                                </div>
                                <div class="small text-muted text-center fst-italic" style="font-size:0.75rem;">
                                    *Available stock ready for upload/activation
                                </div>
                            </div>
                        </td>

                        <td>
                            <div class="d-flex flex-column justify-content-center h-100">
                                <div class="stats-grid">
                                    <div class="stat-item active">
                                        <span class="stat-val active-text"><?= number_format($active) ?></span>
                                        <span class="stat-lbl active-text">On-Air (Active)</span>
                                    </div>
                                    <div class="stat-item term">
                                        <span class="stat-val term-text"><?= number_format($terminated) ?></span>
                                        <span class="stat-lbl term-text">Off-Air (Dead)</span>
                                    </div>
                                </div>
                                
                                <?php 
                                    $pctUsed = ($totalPool > 0) ? ($usedStock / $totalPool) * 100 : 0;
                                    $pctActive = ($totalPool > 0) ? ($active / $totalPool) * 100 : 0;
                                ?>
                                <div class="pool-bar-container" title="Usage Visualization">
                                    <div class="pool-bar bar-used" style="width: <?= $pctUsed ?>%"></div>
                                    <div class="pool-bar bar-active" style="width: <?= $pctActive ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-1" style="font-size:0.65rem; color:#94a3b8; font-weight:600;">
                                    <span>0</span>
                                    <span>USAGE: <?= number_format($pctUsed, 1) ?>%</span>
                                    <span><?= number_format($totalPool) ?></span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <button class="btn-action btn-act <?= ($availableStock <= 0) ? 'disabled' : '' ?>" onclick='openActionModal("activate", <?= $rowJson ?>)'>
                                <i class="bi bi-plus-lg"></i> Activate
                            </button>
                            
                            <button class="btn-action btn-term <?= ($active <= 0) ? 'disabled' : '' ?>" onclick='openActionModal("terminate", <?= $rowJson ?>)'>
                                <i class="bi bi-x-lg"></i> Terminate
                            </button>
                            
                            <button class="btn-action btn-log" onclick='openDetailModal(<?= $rowJson ?>)'>
                                <i class="bi bi-list-ul"></i> View Logs
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="modalMaster" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0">
            <input type="hidden" name="action" value="upload_master_bulk">

            <div class="modal-header bg-white border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Upload Master Data (New Batch)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">1. SOURCE (PROVIDER PO)</label>
                            <select name="po_provider_id" id="inj_provider" class="form-select fw-bold border-primary" required onchange="autoFillClient(this)">
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

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">2. DESTINATION</label>
                            <select name="company_id" id="inj_client" class="form-select bg-light mb-2" required onchange="filterProjects(this.value)">
                                <option value="">-- Client --</option>
                                <?php foreach($list_clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="project_id" id="inj_project" class="form-select bg-light">
                                <option value="">-- Project --</option>
                            </select>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">BATCH NAME</label>
                                <input type="text" name="activation_batch" id="inj_batch" class="form-control" placeholder="BATCH 1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">DATE</label>
                                <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex flex-column justify-content-center">
                        <label class="form-label fw-bold small text-muted mb-2">3. FILE UPLOAD</label>
                        <div class="upload-area">
                            <input type="file" name="upload_file" accept=".csv, .xlsx, .xls" required onchange="handleFile(this)" style="position:absolute; width:100%; height:100%; top:0; left:0; opacity:0; cursor:pointer;">
                            <div class="upload-icon"><i class="bi bi-file-earmark-excel"></i></div>
                            <h6 class="fw-bold text-dark" id="fileNameDisplay">Click to Browse</h6>
                            <p class="text-muted small mb-0">Supports: CSV, Excel (.xlsx)</p>
                            <div class="mt-3 badge bg-light text-dark border">Header: SN, ICCID, IMSI, MSISDN</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 pt-0 pb-4 pe-4">
                <button type="button" class="btn btn-light fw-bold text-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">Start Upload</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAction" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" id="act_form_action"> 
            <input type="hidden" name="po_provider_id" id="act_po_id">
            <input type="hidden" name="company_id" id="act_comp_id">
            <input type="hidden" name="project_id" id="act_proj_id">
            <input type="hidden" name="activation_batch" id="act_batch_name_hidden"> 
            <input type="hidden" name="termination_batch" id="term_batch_name_hidden"> 

            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span id="act_icon_display" style="font-size:2rem;"></span>
                </div>
                <h5 class="fw-bold mb-1" id="act_title">Action</h5>
                <p class="text-muted small mb-4" id="act_limit_display">Checking...</p>

                <div class="form-floating mb-2">
                    <input type="number" name="qty_input" id="act_qty_input" class="form-control fw-bold text-center fs-5" placeholder="Qty" required min="1">
                    <label>Quantity</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="date" name="date_field" class="form-control text-center" value="<?= date('Y-m-d') ?>" required>
                    <label>Date</label>
                </div>
                
                <div class="text-danger small fw-bold mb-3" id="act_error_msg" style="display:none;">Limit Exceeded!</div>

                <div class="mb-3 text-end">
                    <a href="#" class="text-decoration-none small fw-bold" onclick="$('#sim_detail_box').slideToggle(); return false;">+ Add SIM Details</a>
                </div>
                
                <div id="sim_detail_box" class="text-start bg-light p-3 rounded mb-3" style="display:none;">
                    <div class="mb-2"><input type="text" name="msisdn" id="inp_msisdn" class="form-control form-control-sm" placeholder="MSISDN (Required)"></div>
                    <div class="mb-2"><input type="text" name="iccid" class="form-control form-control-sm" placeholder="ICCID"></div>
                    <div class="mb-2"><input type="text" name="imsi" class="form-control form-control-sm" placeholder="IMSI"></div>
                    <div><input type="text" name="sn" class="form-control form-control-sm" placeholder="SN"></div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-lg fw-bold" id="act_btn_save">Confirm</button>
                    <button type="button" class="btn btn-link text-muted text-decoration-none small" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-white border-bottom">
                <h6 class="modal-title fw-bold">History Logs</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-3" id="timeline_content"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // DATA
    const projects = <?php echo json_encode($list_projects); ?>;
    const activationsRaw = <?php echo json_encode($activations_raw ?? []); ?>;
    const terminationsRaw = <?php echo json_encode($terminations_raw ?? []); ?>;
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;

    // 1. OPEN MODAL UPLOAD
    function openMasterModal() {
        new bootstrap.Modal(document.getElementById('modalMaster')).show();
    }

    // 2. FILE NAME
    function handleFile(input) {
        if(input.files && input.files[0]) {
            document.getElementById('fileNameDisplay').innerText = input.files[0].name;
            document.getElementById('fileNameDisplay').classList.add('text-primary');
        }
    }

    // 3. FILTER PROJECT
    function filterProjects(compId) {
        let $sel = $('#inj_project');
        $sel.empty().append('<option value="">-- Project --</option>');
        if (compId) {
            let filtered = projects.filter(p => p.company_id == compId);
            filtered.forEach(p => { $sel.append(`<option value="${p.id}">${p.project_name}</option>`); });
        }
    }

    // 4. AUTO LINK
    function autoFillClient(selectObj) {
        let opt = selectObj.options[selectObj.selectedIndex];
        let compId = opt.getAttribute('data-comp');
        let projId = opt.getAttribute('data-proj');
        let batch = opt.getAttribute('data-batch');

        if(compId) { document.getElementById('inj_client').value = compId; filterProjects(compId); }
        if(projId) { setTimeout(() => { document.getElementById('inj_project').value = projId; }, 50); }
        if(batch) document.getElementById('inj_batch').value = batch; else document.getElementById('inj_batch').value = 'BATCH 1';
    }

    // 5. ACTION MODAL
    let maxLimit = 0;
    function openActionModal(type, data) {
        // Reset
        $('#act_qty_input').val(''); $('#act_error_msg').hide(); $('#act_btn_save').prop('disabled', false);
        $('#sim_detail_box input').val(''); $('#sim_detail_box').hide(); $('#inp_msisdn').removeClass('is-invalid');

        // Fill Hidden
        $('#act_po_id').val(data.po_id);
        $('#act_comp_id').val(data.company_id);
        $('#act_proj_id').val(data.project_id);

        if (type === 'activate') {
            $('#act_title').text('Activate Stock');
            $('#act_icon_display').html('<i class="bi bi-check-circle-fill text-success"></i>');
            $('#act_form_action').val('create_activation_simple'); 
            $('#act_qty_input').attr('name', 'active_qty'); 
            $('#act_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.rem_alloc);
            $('#act_limit_display').html(`Available Stock: <b>${maxLimit.toLocaleString()}</b>`);
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success');
        } else {
            $('#act_title').text('Terminate SIM');
            $('#act_icon_display').html('<i class="bi bi-x-circle-fill text-danger"></i>');
            $('#act_form_action').val('create_termination_simple'); 
            $('#act_qty_input').attr('name', 'terminated_qty');
            $('#term_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.curr_active);
            $('#act_limit_display').html(`Active SIMs: <b>${maxLimit.toLocaleString()}</b>`);
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger');
        }

        // Validasi
        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) {
                $(this).addClass('is-invalid'); $('#act_error_msg').show(); $('#act_btn_save').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid'); $('#act_error_msg').hide(); $('#act_btn_save').prop('disabled', false);
            }
        });

        // Sim Detail Check
        $('#act_btn_save').off('click').on('click', function(e) {
            let hasDetail = false;
            $('#sim_detail_box input').each(function(){ if($(this).val().trim()!=='') hasDetail=true; });
            if(hasDetail && $('#inp_msisdn').val().trim()==='') {
                e.preventDefault(); $('#inp_msisdn').addClass('is-invalid');
                if(!$('#sim_detail_box').is(':visible')) $('#sim_detail_box').slideDown();
                alert("MSISDN required if detail is filled!");
            }
        });

        new bootstrap.Modal(document.getElementById('modalAction')).show();
    }

    // 6. TIMELINE LOGS
    function openDetailModal(data) {
        let acts = activationsRaw.filter(i => i.po_provider_id == data.po_id);
        let terms = terminationsRaw.filter(i => i.po_provider_id == data.po_id);
        
        let combined = [];
        acts.forEach(i => combined.push({type:'act', date:i.activation_date, qty:i.active_qty, batch:i.activation_batch}));
        terms.forEach(i => combined.push({type:'term', date:i.termination_date, qty:i.terminated_qty, batch:i.termination_batch}));
        combined.sort((a,b) => new Date(b.date) - new Date(a.date));

        let html = '<div style="border-left:2px solid #e2e8f0; margin-left:10px; padding-left:20px;">';
        if(combined.length===0) html += '<div class="text-muted small text-center">No transactions history.</div>';
        
        combined.forEach(log => {
            let isAct = log.type==='act';
            let color = isAct ? 'text-success' : 'text-danger';
            let icon = isAct ? 'bi-plus-lg' : 'bi-dash-lg';
            let label = isAct ? 'Activated' : 'Terminated';
            let bg = isAct ? 'bg-success' : 'bg-danger';
            
            html += `
            <div class="mb-4 position-relative">
                <div class="position-absolute rounded-circle ${bg}" style="width:10px; height:10px; left:-25px; top:6px; border:2px solid white; box-shadow:0 0 0 1px #e2e8f0;"></div>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold ${color}" style="font-size:0.9rem;">${label}</div>
                        <div class="text-muted small">${log.date} &bull; ${log.batch}</div>
                    </div>
                    <div class="fw-bold text-dark fs-6"><i class="bi ${icon}"></i> ${parseInt(log.qty).toLocaleString()}</div>
                </div>
            </div>`;
        });
        html += '</div>';
        $('#timeline_content').html(html);
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // 7. CHART
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