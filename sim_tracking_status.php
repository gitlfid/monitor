<?php
// =========================================================================
// 1. SETUP & DATABASE CONNECTION
// =========================================================================
ini_set('display_errors', 0); // Matikan error display agar UI tidak berantakan
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// GUNAKAN KONEKSI STANDAR
$db = db_connect();

// --- FETCH DATA ---
$activations = [];
$terminations = [];
$chart_data_act = []; 
$chart_data_term = [];

// 1. Fetch Activations (Cek apakah kolom po_provider_id ada atau tidak, kita gunakan try-catch safe query)
$sql_act = "SELECT sa.*, 
            c.company_name, p.project_name,
            po.po_number as source_po_number, po.batch_name as source_po_batch
            FROM sim_activations sa
            LEFT JOIN companies c ON sa.company_id = c.id
            LEFT JOIN projects p ON sa.project_id = p.id
            LEFT JOIN sim_tracking_po po ON sa.po_provider_id = po.id
            ORDER BY sa.activation_date DESC, sa.id DESC";

// 2. Fetch Terminations
$sql_term = "SELECT st.*, 
             c.company_name, p.project_name
             FROM sim_terminations st
             LEFT JOIN companies c ON st.company_id = c.id
             LEFT JOIN projects p ON st.project_id = p.id
             ORDER BY st.termination_date DESC, st.id DESC";

// Eksekusi Query
if ($db) {
    try {
        $stmtA = $db->query($sql_act);
        if ($stmtA) $activations = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback jika query aktivasi bermasalah
        $fallbackAct = "SELECT * FROM sim_activations ORDER BY id DESC";
        $stmtA = $db->query($fallbackAct);
        if ($stmtA) $activations = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    }

    try {
        $stmtT = $db->query($sql_term);
        if ($stmtT) $terminations = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback
        $fallbackTerm = "SELECT * FROM sim_terminations ORDER BY id DESC";
        $stmtT = $db->query($fallbackTerm);
        if ($stmtT) $terminations = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- CHART LOGIC (ALL TIME) ---
foreach ($activations as $row) {
    $d = date('Y-m-d', strtotime($row['activation_date']));
    if(!isset($chart_data_act[$d])) $chart_data_act[$d] = 0;
    $chart_data_act[$d] += (int)$row['active_qty'];
}
foreach ($terminations as $row) {
    $d = date('Y-m-d', strtotime($row['termination_date']));
    if(!isset($chart_data_term[$d])) $chart_data_term[$d] = 0;
    $chart_data_term[$d] += (int)$row['terminated_qty'];
}

// Gabungkan Tanggal & Urutkan
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term)));
sort($all_dates); 

$js_labels = [];
$js_series_act = [];
$js_series_term = [];

foreach ($all_dates as $dateKey) {
    $js_labels[] = date('d M Y', strtotime($dateKey));
    $js_series_act[] = isset($chart_data_act[$dateKey]) ? $chart_data_act[$dateKey] : 0;
    $js_series_term[] = isset($chart_data_term[$dateKey]) ? $chart_data_term[$dateKey] : 0;
}

// DROPDOWN DATA (CLIENTS & PROJECTS)
$clients = []; $projects = []; $provider_pos = [];
if ($db) {
    try {
        $clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $projects = $db->query("SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // FIX: TAMBAHKAN GROUP BY AGAR TIDAK DOUBLE/DUPLIKAT
        $sql_pos = "SELECT st.id, st.po_number, st.batch_name, st.sim_qty, 
                    COALESCE(linked.company_id, st.company_id) as final_company_id, 
                    COALESCE(linked.project_id, st.project_id) as final_project_id
                    FROM sim_tracking_po st 
                    LEFT JOIN sim_tracking_po linked ON st.link_client_po_id = linked.id
                    WHERE st.type='provider' 
                    GROUP BY st.po_number, st.batch_name 
                    ORDER BY st.id DESC";
        $provider_pos = $db->query($sql_pos)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore error dropdown
    }
}
?>

<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
    .tab-content { padding-top: 20px; }
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom th { background-color: #f9fafb; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .table-custom td { padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #334155; }
    .table-custom tr:hover td { background-color: #f8fafc; }
    .col-date   { width: 130px; min-width: 130px; }
    .col-info   { min-width: 200px; }
    .col-stats  { width: 180px; }
    .col-batch  { width: 100px; text-align: center; }
    .col-action { width: 80px; text-align: center; }
    .badge-batch { background-color: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .stat-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-val { font-weight: 700; font-size: 0.95rem; }
    .text-success-dark { color: #059669; }
    .text-danger-dark { color: #dc2626; }
    .filter-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .form-control-sm, .form-select-sm { border-radius: 6px; border-color: #e2e8f0; height: 38px; } 
    .search-wrapper { position: relative; width: 100%; }
    .search-wrapper input { padding-right: 40px; }
    .search-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 1rem; }
    .dataTables_wrapper .row:last-child { padding: 15px 24px; border-top: 1px solid #f1f5f9; align-items: center; }
    .page-link { border-radius: 6px; margin: 0 3px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; padding: 6px 12px; }
    .page-item.active .page-link { background-color: #435ebe; border-color: #435ebe; color: white; }
    .btn-action-menu { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; transition: 0.2s; }
    .btn-action-menu:hover { background-color: #f8fafc; color: #1e293b; }
    .timeline-box { position: relative; padding-left: 20px; border-left: 2px solid #e2e8f0; margin-left: 10px; }
    .timeline-item { position: relative; margin-bottom: 15px; }
    .timeline-item::before { content: ''; position: absolute; left: -26px; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: #435ebe; border: 3px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; }
    .timeline-date { font-size: 0.75rem; color: #64748b; font-weight: bold; margin-bottom: 2px; display: block; }
    .timeline-content { background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #f1f5f9; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Activation & Termination</h3>
            <p class="text-muted mb-0 small">Manage SIM Lifecycle Status (All Time Data).</p>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Analysis (All Time)</h6>
            <div id="lifecycleChart" style="height: 300px;"></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="activation-tab" data-bs-toggle="tab" data-bs-target="#activation" type="button"><i class="bi bi-check-circle-fill me-2 text-success"></i> Activation List</button></li>
                <li class="nav-item"><button class="nav-link" id="termination-tab" data-bs-toggle="tab" data-bs-target="#termination" type="button"><i class="bi bi-x-circle-fill me-2 text-danger"></i> Termination List</button></li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content" id="myTabContent">
                
                <div class="tab-pane fade show active" id="activation" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Activation Data</h6>
                        <button type="button" class="btn btn-success btn-sm px-4 fw-bold shadow-sm" onclick="safeOpenModal('modalUniversal', 'act', 'create')"><i class="bi bi-plus me-1"></i> New Activation</button>
                    </div>
                    
                    <div class="filter-box">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="search-wrapper">
                                    <input type="text" id="searchAct" class="form-control form-control-sm ps-3" placeholder="Search Client, Project...">
                                    <i class="bi bi-search search-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-4"><select id="filterClientAct" class="form-select form-select-sm"><option value="">All Clients</option><?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-3"><select id="lengthAct" class="form-select form-select-sm"><option value="10">10 Rows</option><option value="50">50 Rows</option></select></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-custom" id="table-activation">
                            <thead><tr><th class="col-date ps-4">Date</th><th class="col-info">Company / Project</th><th>Source Info</th><th class="col-stats">Status (Calc)</th><th class="col-batch">Batch</th><th class="col-action pe-4">Action</th></tr></thead>
                            <tbody>
                                <?php if(empty($activations)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No activation data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($activations as $row): 
                                        $date = date('d M Y', strtotime($row['activation_date']));
                                        $total = number_format($row['total_sim']);
                                        $active = number_format($row['active_qty']);
                                        $inactive = number_format($row['inactive_qty']);
                                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                        
                                        $sourceInfo = "Manual Input";
                                        if (isset($row['po_provider_id']) && $row['po_provider_id']) {
                                            $sourceInfo = "Src: PO #" . ($row['source_po_number'] ?? $row['po_provider_id']);
                                        }
                                    ?>
                                    <tr>
                                        <td class="col-date ps-4 text-secondary fw-bold"><?= $date ?></td>
                                        <td><div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div><div class="small text-muted text-uppercase"><?= htmlspecialchars($row['project_name'] ?? '-') ?></div></td>
                                        <td><div class="small text-muted text-uppercase"><?= $sourceInfo ?></div></td>
                                        <td><div class="d-flex flex-column"><div class="d-flex justify-content-between"><span class="stat-label">Total:</span> <span class="fw-bold"><?= $total ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-success">Active:</span> <span class="stat-val text-success-dark"><?= $active ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-danger">Inactive:</span> <span class="stat-val text-muted"><?= $inactive ?></span></div></div></td>
                                        <td class="text-center"><span class="badge-batch text-uppercase"><?= htmlspecialchars($row['activation_batch'] ?? '') ?></span></td>
                                        <td class="col-action pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-action-menu" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick='safeOpenDetail("act", <?= $rowJson ?>)'><i class="bi bi-eye me-2 text-info"></i> View Detail</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick='safeOpenModal("modalUniversal", "act", "update", <?= $rowJson ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                                    <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete_activation&id=<?= $row['id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="termination" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Termination Data</h6>
                        <button type="button" class="btn btn-danger btn-sm px-4 fw-bold shadow-sm" onclick="safeOpenModal('modalUniversal', 'term', 'create')"><i class="bi bi-plus me-1"></i> New Termination</button>
                    </div>
                    
                    <div class="filter-box">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="search-wrapper">
                                    <input type="text" id="searchTerm" class="form-control form-control-sm ps-3" placeholder="Search...">
                                    <i class="bi bi-search search-icon"></i>
                                </div>
                            </div>
                            <div class="col-md-4"><select id="filterClientTerm" class="form-select form-select-sm"><option value="">All Clients</option><?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-3"><select id="lengthTerm" class="form-select form-select-sm"><option value="10">10 Rows</option><option value="50">50 Rows</option></select></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-custom" id="table-termination">
                            <thead><tr><th class="col-date ps-4">Date</th><th class="col-info">Company / Project</th><th>Source Info</th><th class="col-stats">Status (Calc)</th><th class="col-batch">Batch</th><th class="col-action pe-4">Action</th></tr></thead>
                            <tbody>
                                <?php if(empty($terminations)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No termination data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($terminations as $row): 
                                        $date = date('d M Y', strtotime($row['termination_date']));
                                        $total = number_format($row['total_sim']);
                                        $term = number_format($row['terminated_qty']);
                                        $unterm = number_format($row['unterminated_qty']);
                                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                                        $sourceInfo = "Manual Input";
                                        if (isset($row['activation_id']) && $row['activation_id']) {
                                            $sourceInfo = "Src: ACT #" . ($row['source_activation_batch'] ?? $row['activation_id']);
                                        }
                                    ?>
                                    <tr>
                                        <td class="col-date ps-4 text-secondary fw-bold"><?= $date ?></td>
                                        <td><div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div><div class="small text-muted text-uppercase"><?= htmlspecialchars($row['project_name'] ?? '-') ?></div></td>
                                        <td><div class="small text-muted text-uppercase"><?= $sourceInfo ?></div></td>
                                        <td><div class="d-flex flex-column"><div class="d-flex justify-content-between"><span class="stat-label">Total:</span> <span class="fw-bold"><?= $total ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-danger">Terminated:</span> <span class="stat-val text-danger-dark"><?= $term ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-success">Active:</span> <span class="stat-val text-muted"><?= $unterm ?></span></div></div></td>
                                        <td class="text-center"><span class="badge-batch text-uppercase"><?= htmlspecialchars($row['termination_batch'] ?? '') ?></span></td>
                                        <td class="col-action pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-action-menu" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick='safeOpenDetail("term", <?= $rowJson ?>)'><i class="bi bi-eye me-2 text-info"></i> View Detail</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick='safeOpenModal("modalUniversal", "term", "update", <?= $rowJson ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                                    <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete_termination&id=<?= $row['id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalUniversal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" id="formUniversal" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="formAction">
            <input type="hidden" name="id" id="formId">
            
            <div class="modal-header text-white py-3" id="modalHeader">
                <h6 class="modal-title m-0 fw-bold" id="modalTitle">Title</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12" id="div_source_po" style="display:none;">
                        <label class="form-label fw-bold small text-uppercase text-success"><i class="bi bi-link-45deg"></i> Link to Provider PO (Source)</label>
                        <select id="inp_source_po" class="form-select border-success" onchange="fillFromSourcePO()">
                            <option value="">-- Manual Input (No Link) --</option>
                            <?php foreach($provider_pos as $po): 
                                $poNum = htmlspecialchars($po['po_number'] ?? '-');
                                $poBatch = htmlspecialchars($po['batch_name'] ?? '-');
                                $poQty = number_format($po['sim_qty'] ?? 0);
                                $rawQty = $po['sim_qty'] ?? 0;
                                $compId = $po['final_company_id'] ?? '';
                                $projId = $po['final_project_id'] ?? '';
                            ?>
                                <option value="<?= $po['id'] ?>" data-batch="<?= $poBatch ?>" data-qty="<?= $rawQty ?>" data-comp="<?= $compId ?>" data-proj="<?= $projId ?>">
                                    PO: <?= $poNum ?> - Batch: <?= $poBatch ?> (Qty: <?= $poQty ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted fst-italic">Auto-fill Client, Project & Total SIM.</small>
                    </div>

                    <div class="col-12" id="div_source_act" style="display:none;">
                        <label class="form-label fw-bold small text-uppercase text-danger"><i class="bi bi-link-45deg"></i> Link to Activation (Source)</label>
                        <select id="inp_source_act" class="form-select border-danger" onchange="fillFromSourceAct()">
                            <option value="">-- Manual Input (No Link) --</option>
                            </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Client</label>
                        <select name="company_id" id="inp_company_id" class="form-select" required onchange="updateProjectDropdown(this.value)">
                            <option value="">-- Select --</option>
                            <?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Project</label>
                        <select name="project_id" id="inp_project_id" class="form-select">
                            <option value="">-- Select --</option>
                            </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase">Date</label>
                        <input type="date" name="date_field" id="inp_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase">Batch Name</label>
                        <input type="text" name="batch_field" id="inp_batch" class="form-control" required oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase">Total SIM</label>
                        <input type="number" name="total_sim" id="inp_total" class="form-control" required oninput="calculateRemaining()">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-success" id="lbl_qty_1">Active Qty</label>
                        <input type="number" name="qty_1" id="inp_qty_1" class="form-control" required oninput="calculateRemaining()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-danger" id="lbl_qty_2">Inactive Qty</label>
                        <input type="number" name="qty_2" id="inp_qty_2" class="form-control" required readonly> 
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light">
                <button type="submit" class="btn btn-primary px-4 fw-bold">Save Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom py-3">
                <h6 class="modal-title m-0 fw-bold text-dark" id="detailTitle">Batch Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4">
                    <div class="row g-2 text-center mb-4">
                        <div class="col-6"><div class="p-3 rounded border bg-white"><small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.7rem">Batch Name</small><span class="fw-bold text-dark h5" id="det_batch">-</span></div></div>
                        <div class="col-6"><div class="p-3 rounded border bg-white"><small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.7rem">Total SIM</small><span class="fw-bold text-primary h5" id="det_total">-</span></div></div>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted fw-bold small mb-2 border-bottom pb-1">Information</h6>
                        <div class="d-flex justify-content-between mb-1"><span class="small text-muted">Client</span><span class="small fw-bold text-end" id="det_client">-</span></div>
                        <div class="d-flex justify-content-between mb-1"><span class="small text-muted">Project</span><span class="small fw-bold text-end" id="det_project">-</span></div>
                        <div class="d-flex justify-content-between mb-1"><span class="small text-muted">Source Link</span><span class="small fw-bold text-end text-success" id="det_source">-</span></div>
                    </div>

                    <div class="timeline-box">
                        <div class="timeline-item">
                            <span class="timeline-date" id="det_date">-</span>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark" id="det_action_title">-</span>
                                    <span class="badge bg-primary" id="det_main_qty">0</span>
                                </div>
                                <div class="mt-2 small text-muted d-flex justify-content-between">
                                    <span id="det_sub_label">Inactive</span> 
                                    <span id="det_sub_qty" class="fw-bold">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // PREPARE DATA FROM PHP (SAFE MODE)
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;
    const activationData = <?php echo json_encode($activations ?? []); ?>;
    const allProjects = <?php echo json_encode($projects ?? []); ?>;

    // --- FIX 1: UNIVERSAL MODAL OPENER (Supports BS4 & BS5) ---
    function safeOpenModalInstance(modalId) {
        var el = document.getElementById(modalId);
        if (!el) return console.error('Modal not found:', modalId);

        // Try Bootstrap 5
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            try {
                var modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                modal.show();
                return;
            } catch(e) { console.log('BS5 failed, trying jQuery...'); }
        }
        
        // Try Bootstrap 4 / jQuery
        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
            $(el).modal('show');
        } else {
            alert('Error: Bootstrap Library not loaded properly.');
        }
    }

    // --- CHART LOGIC (Wrapped in try-catch to prevent JS crash) ---
    document.addEventListener('DOMContentLoaded', function () {
        try {
            if (!chartLabels || chartLabels.length === 0) {
                document.querySelector("#lifecycleChart").innerHTML = '<div class="text-center py-5 text-muted small">No data available for chart.</div>';
                return;
            }

            var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 300, toolbar: { show: true }, fontFamily: 'Inter, sans-serif' },
                stroke: { curve: 'smooth', width: 2 },
                colors: ['#198754', '#dc3545'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px', fontWeight: 'bold' } } },
                grid: { borderColor: '#f1f5f9' },
                dataLabels: { enabled: false }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        } catch (e) {
            console.error("Chart Error:", e);
        }
    });

    // --- LOGIC: CREATE / EDIT ---
    let currentMode = ''; 
    function safeOpenModal(modalId, type, action, data = null) {
        currentMode = type;
        
        // Reset Form
        $('#formUniversal')[0].reset();
        $('#inp_source_act').empty().append('<option value="">-- Manual Input (No Link) --</option>');
        $('#inp_source_po').val(''); 
        $('#inp_date').val(new Date().toISOString().split('T')[0]);
        $('#formId').val('');
        updateProjectDropdown(null);

        // UI Configuration
        if(type === 'act') {
            $('#modalTitle').text(action === 'create' ? 'New Activation' : 'Edit Activation');
            $('#modalHeader').removeClass('bg-danger').addClass('bg-success');
            $('#formAction').val(action === 'create' ? 'create_activation' : 'update_activation');
            
            $('#inp_date').attr('name', 'activation_date');
            $('#inp_batch').attr('name', 'activation_batch');
            $('#inp_qty_1').attr('name', 'active_qty'); 
            $('#inp_qty_2').attr('name', 'inactive_qty'); 
            
            $('#lbl_qty_1').text('Active Quantity').removeClass('text-danger').addClass('text-success');
            $('#lbl_qty_2').text('Inactive Quantity').removeClass('text-success').addClass('text-muted');
            
            $('#div_source_po').show();
            $('#div_source_act').hide();
            $('#inp_qty_2').prop('readonly', true);
        } else {
            $('#modalTitle').text(action === 'create' ? 'New Termination' : 'Edit Termination');
            $('#modalHeader').removeClass('bg-success').addClass('bg-danger');
            $('#formAction').val(action === 'create' ? 'create_termination' : 'update_termination');
            
            $('#inp_date').attr('name', 'termination_date');
            $('#inp_batch').attr('name', 'termination_batch');
            $('#inp_qty_1').attr('name', 'terminated_qty'); 
            $('#inp_qty_2').attr('name', 'unterminated_qty'); 
            
            $('#lbl_qty_1').text('Qty to Terminate').removeClass('text-success').addClass('text-danger');
            $('#lbl_qty_2').text('Remaining Active (Auto)').removeClass('text-muted').addClass('text-success');
            
            $('#div_source_po').hide();
            $('#div_source_act').show();
            $('#inp_qty_2').prop('readonly', true);
            
            activationData.forEach(act => {
                let lbl = `${act.activation_batch} - ${act.company_name} (Active: ${act.active_qty})`;
                $('#inp_source_act').append(`<option value="${act.id}" data-total="${act.total_sim}" data-active="${act.active_qty}" data-comp="${act.company_id}" data-proj="${act.project_id}">${lbl}</option>`);
            });
        }

        // Fill Data if Edit
        if (data) {
            $('#formId').val(data.id);
            $('#inp_company_id').val(data.company_id);
            updateProjectDropdown(data.company_id, data.project_id);
            $('#inp_total').val(data.total_sim);
            
            if (type === 'act') {
                $('#inp_date').val(data.activation_date);
                $('#inp_batch').val(data.activation_batch);
                $('#inp_qty_1').val(data.active_qty);
                $('#inp_qty_2').val(data.inactive_qty);
            } else {
                $('#inp_date').val(data.termination_date);
                $('#inp_batch').val(data.termination_batch);
                $('#inp_qty_1').val(data.terminated_qty);
                $('#inp_qty_2').val(data.unterminated_qty);
            }
        }

        // Open Modal Safely
        safeOpenModalInstance(modalId);
    }

    // --- LOGIC: DETAIL VIEW ---
    function safeOpenDetail(type, data) {
        let title, date, mainQty, subQty, mainLabel, subLabel, source;
        
        $('#det_batch').text(type === 'act' ? data.activation_batch : data.termination_batch);
        $('#det_total').text(parseInt(data.total_sim).toLocaleString());
        $('#det_client').text(data.company_name || '-');
        $('#det_project').text(data.project_name || '-');

        if (type === 'act') {
            title = "Activation Details";
            date = data.activation_date;
            mainQty = parseInt(data.active_qty).toLocaleString();
            subQty = parseInt(data.inactive_qty).toLocaleString();
            mainLabel = "Active Qty";
            subLabel = "Inactive";
            source = data.source_po_number ? 'PO: ' + data.source_po_number : 'Manual / No Link';
        } else {
            title = "Termination Details";
            date = data.termination_date;
            mainQty = parseInt(data.terminated_qty).toLocaleString();
            subQty = parseInt(data.unterminated_qty).toLocaleString();
            mainLabel = "Terminated Qty";
            subLabel = "Remaining Active";
            source = data.source_activation_batch ? 'ACT Batch: ' + data.source_activation_batch : 'Manual / No Link';
        }

        $('#detailTitle').text(title);
        $('#det_date').text(date);
        $('#det_action_title').text(mainLabel);
        $('#det_main_qty').text(mainQty);
        $('#det_sub_label').text(subLabel);
        $('#det_sub_qty').text(subQty);
        $('#det_source').text(source);

        safeOpenModalInstance('modalDetail');
    }

    // --- HELPER FUNCTIONS ---
    function updateProjectDropdown(companyId, selectedProjectId = null) {
        let projSelect = $('#inp_project_id');
        projSelect.empty().append('<option value="">-- Select Project --</option>');
        if (companyId) {
            let filtered = allProjects.filter(p => p.company_id == companyId);
            filtered.forEach(p => {
                let sel = (selectedProjectId && p.id == selectedProjectId) ? 'selected' : '';
                projSelect.append(`<option value="${p.id}" ${sel}>${p.project_name}</option>`);
            });
        }
    }

    function fillFromSourcePO() {
        let sel = $('#inp_source_po option:selected');
        let qty = sel.data('qty');
        if(qty) {
            $('#inp_total').val(qty);
            $('#inp_batch').val(sel.data('batch'));
            $('#inp_company_id').val(sel.data('comp'));
            
            // Sync: Update Project Dropdown based on company
            updateProjectDropdown(sel.data('comp'), sel.data('proj'));
            
            $('#inp_qty_1').val(''); 
            $('#inp_qty_2').val(qty); 
        }
    }

    function fillFromSourceAct() {
        let sel = $('#inp_source_act option:selected');
        let active = sel.data('active');
        if(active) {
            $('#inp_total').val(sel.data('total'));
            $('#inp_company_id').val(sel.data('comp'));
            
            // Sync: Update Project Dropdown
            updateProjectDropdown(sel.data('comp'), sel.data('proj'));
            
            $('#inp_qty_1').val(0); 
            $('#inp_qty_2').val(active); 
            $('#inp_qty_1').data('max', active); 
        }
    }

    function calculateRemaining() {
        let total = parseInt($('#inp_total').val()) || 0;
        let qty1 = parseInt($('#inp_qty_1').val()) || 0;
        if (currentMode === 'act') {
            // Activation: Input Active, Calc Inactive
            if (qty1 > total) { qty1 = total; $('#inp_qty_1').val(total); }
            $('#inp_qty_2').val(Math.max(0, total - qty1));
        } else {
            // Termination: Input Terminated, Calc Remaining
            let maxActive = $('#inp_qty_1').data('max') || total; 
            if (qty1 > maxActive) { qty1 = maxActive; $('#inp_qty_1').val(maxActive); }
            $('#inp_qty_2').val(Math.max(0, maxActive - qty1));
        }
    }

    // --- DATATABLES (Only if library exists) ---
    $(document).ready(function() {
        if ($.fn.DataTable) {
            var tAct = $('#table-activation').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
            $('#searchAct').on('keyup', function() { tAct.search(this.value).draw(); });
            $('#filterClientAct').on('change', function() { tAct.column(1).search(this.value).draw(); });

            var tTerm = $('#table-termination').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
            $('#searchTerm').on('keyup', function() { tTerm.search(this.value).draw(); });
            $('#filterClientTerm').on('change', function() { tTerm.column(1).search(this.value).draw(); });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>