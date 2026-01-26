<?php
// =========================================================================
// 1. SETUP & LOGIC PHP
// =========================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
$current_page = 'sim_tracking_status.php';

if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// Database Connection
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }

// FETCH DATA
$activations = [];
$terminations = [];
$chart_act = []; 
$chart_term = [];

try {
    // 1. Fetch Activations
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
                 c.company_name, p.project_name,
                 sa.activation_batch as source_activation_batch
                 FROM sim_terminations st
                 LEFT JOIN companies c ON st.company_id = c.id
                 LEFT JOIN projects p ON st.project_id = p.id
                 LEFT JOIN sim_activations sa ON st.activation_id = sa.id
                 ORDER BY st.termination_date DESC, st.id DESC";

    if ($db_type === 'pdo') {
        $activations = $db->query($sql_act)->fetchAll(PDO::FETCH_ASSOC);
        $terminations = $db->query($sql_term)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $r1 = mysqli_query($db, $sql_act); while($row = mysqli_fetch_assoc($r1)) $activations[] = $row;
        $r2 = mysqli_query($db, $sql_term); while($row = mysqli_fetch_assoc($r2)) $terminations[] = $row;
    }

    // --- CHART LOGIC ---
    foreach ($activations as $row) {
        $d = date('d M', strtotime($row['activation_date']));
        if(!isset($chart_act[$d])) $chart_act[$d] = 0;
        $chart_act[$d] += (int)$row['active_qty'];
    }
    foreach ($terminations as $row) {
        $d = date('d M', strtotime($row['termination_date']));
        if(!isset($chart_term[$d])) $chart_term[$d] = 0;
        $chart_term[$d] += (int)$row['terminated_qty'];
    }
    
    $all_dates = array_unique(array_merge(array_keys($chart_act), array_keys($chart_term)));
    usort($all_dates, function($a, $b) { return strtotime($a) - strtotime($b); });
    $all_dates = array_slice($all_dates, -10);

    $js_labels = $all_dates;
    $js_series_act = [];
    $js_series_term = [];

    foreach($all_dates as $d) {
        $js_series_act[] = isset($chart_act[$d]) ? $chart_act[$d] : 0;
        $js_series_term[] = isset($chart_term[$d]) ? $chart_term[$d] : 0;
    }

} catch (Exception $e) {}

// DROPDOWN DATA
$clients = []; $projects = []; $provider_pos = [];
try {
    if ($db_type === 'pdo') {
        $clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $projects = $db->query("SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $sql_pos = "SELECT st.id, st.po_number, st.batch_name, st.sim_qty, 
                           COALESCE(linked.company_id, st.company_id) as final_company_id, 
                           COALESCE(linked.project_id, st.project_id) as final_project_id
                    FROM sim_tracking_po st 
                    LEFT JOIN sim_tracking_po linked ON st.link_client_po_id = linked.id
                    WHERE st.type='provider' 
                    ORDER BY st.id DESC";
        $provider_pos = $db->query($sql_pos)->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $r1 = mysqli_query($db, "SELECT id, company_name FROM companies ORDER BY company_name ASC"); while($x=mysqli_fetch_assoc($r1)) $clients[]=$x;
        $r2 = mysqli_query($db, "SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC"); while($x=mysqli_fetch_assoc($r2)) $projects[]=$x;
        
        $sql_pos = "SELECT st.id, st.po_number, st.batch_name, st.sim_qty, 
                           COALESCE(linked.company_id, st.company_id) as final_company_id, 
                           COALESCE(linked.project_id, st.project_id) as final_project_id
                    FROM sim_tracking_po st 
                    LEFT JOIN sim_tracking_po linked ON st.link_client_po_id = linked.id
                    WHERE st.type='provider' 
                    ORDER BY st.id DESC";
        $r3 = mysqli_query($db, $sql_pos); while($x=mysqli_fetch_assoc($r3)) $provider_pos[]=$x;
    }
} catch (Exception $e) {}
?>

<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    /* Tabs */
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
    .tab-content { padding-top: 20px; }

    /* Table */
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom th { background-color: #f9fafb; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .table-custom td { padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #334155; }
    .table-custom tr:hover td { background-color: #f8fafc; }

    /* Columns */
    .col-date   { width: 130px; min-width: 130px; }
    .col-info   { min-width: 200px; }
    .col-stats  { width: 180px; }
    .col-batch  { width: 100px; text-align: center; }
    .col-action { width: 80px; text-align: center; }

    /* Components */
    .badge-batch { background-color: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
    .stat-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-val { font-weight: 700; font-size: 0.95rem; }
    .text-success-dark { color: #059669; }
    .text-danger-dark { color: #dc2626; }
    
    /* Filter & Search - FIXED ICON POSITION */
    .filter-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .form-control-sm, .form-select-sm { border-radius: 6px; border-color: #e2e8f0; height: 38px; } 
    
    .search-wrapper { position: relative; width: 100%; }
    .search-wrapper input { padding-right: 40px; }
    .search-icon { 
        position: absolute; 
        right: 12px; 
        top: 50%; 
        transform: translateY(-50%); 
        color: #94a3b8; 
        pointer-events: none;
        font-size: 1rem;
    }

    /* DataTables & Actions */
    .dataTables_wrapper .row:last-child { padding: 15px 24px; border-top: 1px solid #f1f5f9; align-items: center; }
    .page-link { border-radius: 6px; margin: 0 3px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; padding: 6px 12px; }
    .page-item.active .page-link { background-color: #435ebe; border-color: #435ebe; color: white; }
    .btn-action-menu { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; transition: 0.2s; }
    .btn-action-menu:hover { background-color: #f8fafc; color: #1e293b; }

    /* Timeline in Modal */
    .timeline-item { position: relative; padding-left: 30px; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: 0; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: #e2e8f0; border: 2px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
    .timeline-item::after { content: ''; position: absolute; left: 5px; top: 17px; bottom: -25px; width: 2px; background: #e2e8f0; }
    .timeline-item:last-child::after { display: none; }
    .timeline-item.active::before { background: #435ebe; box-shadow: 0 0 0 2px #c7d2fe; }
    .timeline-date { font-size: 0.75rem; color: #64748b; font-weight: 600; margin-bottom: 4px; display: block; }
    .timeline-content { background: #f8fafc; padding: 10px 15px; border-radius: 8px; border: 1px solid #f1f5f9; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Activation & Termination</h3>
            <p class="text-muted mb-0 small">Manage SIM Lifecycle Status and Sync History.</p>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Analysis (Last 10 Days)</h6>
            <?php if(empty($js_labels)): ?>
                <div class="text-center py-5 bg-light rounded text-muted"><p class="mb-0 small">No data available for analysis.</p></div>
            <?php else: ?>
                <div id="lifecycleChart" style="height: 300px;"></div>
            <?php endif; ?>
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
                        <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm" onclick="openModal('act', 'create')"><i class="bi bi-plus me-1"></i> New Activation</button>
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
                            <thead><tr><th class="col-date ps-4">Date</th><th class="col-info">Company / Project</th><th>PO Info</th><th class="col-stats">Status (Calc)</th><th class="col-batch">Batch</th><th class="col-action pe-4">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($activations as $row): 
                                    $date = date('d M Y', strtotime($row['activation_date']));
                                    $total = number_format($row['total_sim']);
                                    $active = number_format($row['active_qty']);
                                    $inactive = number_format($row['inactive_qty']);
                                ?>
                                <tr>
                                    <td class="col-date ps-4 text-secondary fw-bold"><?= $date ?></td>
                                    <td><div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div><div class="small text-muted text-uppercase"><?= htmlspecialchars($row['project_name'] ?? '-') ?></div></td>
                                    <td><div class="small text-muted text-uppercase"><?php if($row['po_provider_id']): ?>P: #<?= $row['po_provider_id'] ?><br><?php endif; ?><?php if($row['po_client_id']): ?>C: #<?= $row['po_client_id'] ?><?php endif; ?></div></td>
                                    <td><div class="d-flex flex-column"><div class="d-flex justify-content-between"><span class="stat-label">Total:</span> <span class="fw-bold"><?= $total ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-success">Active:</span> <span class="stat-val text-success-dark"><?= $active ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-danger">Inactive:</span> <span class="stat-val text-muted"><?= $inactive ?></span></div></div></td>
                                    <td class="text-center"><span class="badge-batch text-uppercase"><?= htmlspecialchars($row['activation_batch'] ?? '') ?></span></td>
                                    <td class="col-action pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-action-menu" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="#" onclick='openDetailModal("act", <?= json_encode($row) ?>)'><i class="bi bi-eye me-2 text-info"></i> View Detail & History</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" onclick='openModal("act", "update", <?= json_encode($row) ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                                <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete_activation&id=<?= $row['id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="termination" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Termination Data</h6>
                        <button class="btn btn-danger btn-sm px-4 fw-bold shadow-sm" onclick="openModal('term', 'create')"><i class="bi bi-plus me-1"></i> New Termination</button>
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
                            <thead><tr><th class="col-date ps-4">Date</th><th class="col-info">Company / Project</th><th>PO Info</th><th class="col-stats">Status (Calc)</th><th class="col-batch">Batch</th><th class="col-action pe-4">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($terminations as $row): 
                                    $date = date('d M Y', strtotime($row['termination_date']));
                                    $total = number_format($row['total_sim']);
                                    $term = number_format($row['terminated_qty']);
                                    $unterm = number_format($row['unterminated_qty']);
                                ?>
                                <tr>
                                    <td class="col-date ps-4 text-secondary fw-bold"><?= $date ?></td>
                                    <td><div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div><div class="small text-muted text-uppercase"><?= htmlspecialchars($row['project_name'] ?? '-') ?></div></td>
                                    <td><div class="small text-muted text-uppercase"><?php if($row['po_provider_id']): ?>P: #<?= $row['po_provider_id'] ?><br><?php endif; ?><?php if($row['po_client_id']): ?>C: #<?= $row['po_client_id'] ?><?php endif; ?></div></td>
                                    <td><div class="d-flex flex-column"><div class="d-flex justify-content-between"><span class="stat-label">Total:</span> <span class="fw-bold"><?= $total ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-danger">Terminated:</span> <span class="stat-val text-danger-dark"><?= $term ?></span></div><div class="d-flex justify-content-between"><span class="stat-label text-success">Active:</span> <span class="stat-val text-muted"><?= $unterm ?></span></div></div></td>
                                    <td class="text-center"><span class="badge-batch text-uppercase"><?= htmlspecialchars($row['termination_batch'] ?? '') ?></span></td>
                                    <td class="col-action pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-action-menu" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="#" onclick='openDetailModal("term", <?= json_encode($row) ?>)'><i class="bi bi-eye me-2 text-info"></i> View Detail & History</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="#" onclick='openModal("term", "update", <?= json_encode($row) ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                                <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete_termination&id=<?= $row['id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-3">
                    
                    <div class="col-12" id="div_source_po" style="display:none;">
                        <label class="form-label fw-bold small text-uppercase text-success"><i class="bi bi-link-45deg"></i> Link to Provider PO (Source)</label>
                        <select id="inp_source_po" class="form-select border-success" onchange="fillFromSourcePO()">
                            <option value="">-- Select Source PO --</option>
                            <?php foreach($provider_pos as $po): 
                                $poNum = htmlspecialchars($po['po_number'] ?? '-');
                                $poBatch = htmlspecialchars($po['batch_name'] ?? '-');
                                $poQty = number_format($po['sim_qty'] ?? 0);
                                $rawQty = $po['sim_qty'] ?? 0;
                                $compId = $po['final_company_id'] ?? '';
                                $projId = $po['final_project_id'] ?? '';
                            ?>
                                <option value="<?= $po['id'] ?>" 
                                    data-batch="<?= $poBatch ?>" 
                                    data-qty="<?= $rawQty ?>"
                                    data-comp="<?= $compId ?>"
                                    data-proj="<?= $projId ?>">
                                    PO: <?= $poNum ?> - Batch: <?= $poBatch ?> (Qty: <?= $poQty ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted fst-italic">Auto-fill Client, Project & Total SIM.</small>
                    </div>

                    <div class="col-12" id="div_source_act" style="display:none;">
                        <label class="form-label fw-bold small text-uppercase text-danger"><i class="bi bi-link-45deg"></i> Link to Activation (Source)</label>
                        <select id="inp_source_act" class="form-select border-danger" onchange="fillFromSourceAct()">
                            <option value="">-- Select Source Activation Batch --</option>
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
                        <input type="number" name="total_sim" id="inp_total" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-success" id="lbl_qty_1">Active Qty</label>
                        <input type="number" name="qty_1" id="inp_qty_1" class="form-control" required oninput="calculateRemaining()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-danger" id="lbl_qty_2">Inactive Qty</label>
                        <input type="number" name="qty_2" id="inp_qty_2" class="form-control" required readonly> </div>
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
            <div class="modal-header bg-white border-bottom py-3">
                <h6 class="modal-title m-0 fw-bold text-dark" id="detailTitle">Batch Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4 bg-light">
                    <div class="row g-2 text-center mb-3">
                        <div class="col-6">
                            <div class="bg-white p-3 rounded border">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Batch</small>
                                <span class="fw-bold text-dark h5" id="det_batch">-</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-white p-3 rounded border">
                                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Total SIM</small>
                                <span class="fw-bold text-primary h5" id="det_total">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-0 border-0 shadow-sm">
                        <div class="card-body p-3">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2">Sync Information</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small text-muted">Client:</span>
                                <span class="small fw-bold text-end" id="det_client">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="small text-muted">Project:</span>
                                <span class="small fw-bold text-end" id="det_project">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-0">
                                <span class="small text-muted">Source Data:</span>
                                <span class="small fw-bold text-end text-success" id="det_source">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <h6 class="text-uppercase text-muted fw-bold small mb-3">Timeline (Change Log)</h6>
                    <div class="timeline-box">
                        <div class="timeline-item active">
                            <span class="timeline-date" id="det_date">-</span>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark" id="det_action_title">Created / Updated</span>
                                    <span class="badge bg-primary" id="det_main_qty">0</span>
                                </div>
                                <div class="mt-2 small text-muted">
                                    Remaining: <span id="det_sub_qty" class="fw-bold">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    const chartLabels = <?php echo json_encode($js_labels); ?>;
    const seriesAct = <?php echo json_encode($js_series_act); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term); ?>;
    
    // Data for Logic
    const activationData = <?php echo json_encode($activations); ?>;
    const allProjects = <?php echo json_encode($projects); ?>;

    // 1. CHART
    document.addEventListener('DOMContentLoaded', function () {
        if (chartLabels.length > 0) {
            var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                stroke: { curve: 'smooth', width: 2 },
                colors: ['#198754', '#dc3545'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px', fontWeight: 'bold' } } },
                grid: { borderColor: '#f1f5f9' },
                dataLabels: { enabled: false }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });

    // 2. PROJECT FILTER LOGIC
    function updateProjectDropdown(companyId, selectedProjectId = null) {
        let projSelect = $('#inp_project_id');
        projSelect.empty().append('<option value="">-- Select Project --</option>');

        if (companyId) {
            let filteredProjects = allProjects.filter(p => p.company_id == companyId);
            filteredProjects.forEach(p => {
                let isSelected = (selectedProjectId && p.id == selectedProjectId) ? 'selected' : '';
                projSelect.append(`<option value="${p.id}" ${isSelected}>${p.project_name}</option>`);
            });
        }
    }

    // 3. MODAL CREATE/UPDATE LOGIC
    let currentMode = ''; 

    function openModal(type, action, data = null) {
        currentMode = type;
        let modalTitle = '';
        let headerClass = '';
        let formAction = '';
        
        // Reset Form & Ensure it's clean
        $('#formUniversal')[0].reset();
        $('#inp_source_act').empty().append('<option value="">-- Select Source Activation Batch --</option>');
        $('#inp_source_po').val('');
        updateProjectDropdown(null);

        if(type === 'act') {
            modalTitle = (action === 'create') ? 'New Activation' : 'Edit Activation';
            headerClass = 'bg-success';
            formAction = (action === 'create') ? 'create_activation' : 'update_activation';
            
            $('#inp_date').attr('name', 'activation_date');
            $('#inp_batch').attr('name', 'activation_batch');
            $('#inp_qty_1').attr('name', 'active_qty'); 
            $('#inp_qty_2').attr('name', 'inactive_qty'); 
            
            $('#lbl_qty_1').text('Active Quantity').removeClass('text-danger').addClass('text-success');
            $('#lbl_qty_2').text('Inactive Quantity').removeClass('text-success').addClass('text-muted');
            
            $('#div_source_po').show();
            $('#div_source_act').hide();
            $('#inp_qty_2').prop('readonly', true);
        } 
        else {
            modalTitle = (action === 'create') ? 'New Termination' : 'Edit Termination';
            headerClass = 'bg-danger';
            formAction = (action === 'create') ? 'create_termination' : 'update_termination';
            
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
                let label = `${act.activation_batch} - ${act.company_name} (Active: ${act.active_qty})`;
                $('#inp_source_act').append(`<option value="${act.id}" data-total="${act.total_sim}" data-active="${act.active_qty}" data-comp="${act.company_id}" data-proj="${act.project_id}">${label}</option>`);
            });
        }

        $('#modalTitle').text(modalTitle);
        $('#modalHeader').removeClass('bg-success bg-danger').addClass(headerClass);
        $('#formAction').val(formAction);

        if (data) {
            $('#formId').val(data.id);
            $('#inp_company_id').val(data.company_id);
            updateProjectDropdown(data.company_id, data.project_id);
            $('#inp_total').val(data.total_sim);
            
            let dName = (type === 'act') ? 'activation_date' : 'termination_date';
            let bName = (type === 'act') ? 'activation_batch' : 'termination_batch';
            let q1Name = (type === 'act') ? 'active_qty' : 'terminated_qty';
            let q2Name = (type === 'act') ? 'inactive_qty' : 'unterminated_qty';

            $('#inp_date').val(data[dName]);
            $('#inp_batch').val(data[bName]);
            $('#inp_qty_1').val(data[q1Name]);
            $('#inp_qty_2').val(data[q2Name]);
        } else {
            $('#formId').val('');
            $('#inp_date').val(new Date().toISOString().split('T')[0]);
        }

        new bootstrap.Modal(document.getElementById('modalUniversal')).show();
    }

    // 4. NEW FEATURE: DETAIL MODAL
    function openDetailModal(type, data) {
        let batchName, totalSim, clientName, projectName, sourceInfo, dateLog, mainQty, subQty, mainLabel, subLabel;

        clientName = data.company_name || '-';
        projectName = data.project_name || '-';
        totalSim = parseInt(data.total_sim).toLocaleString();

        if (type === 'act') {
            batchName = data.activation_batch;
            // Sync Logic: Show Source PO
            sourceInfo = (data.source_po_number) ? `Linked to PO: ${data.source_po_number}` : 'Manual Input / No Source Link';
            dateLog = data.activation_date;
            mainQty = parseInt(data.active_qty).toLocaleString();
            subQty = parseInt(data.inactive_qty).toLocaleString();
            mainLabel = "Active Qty";
            subLabel = "Inactive Qty";
            $('#detailTitle').text("Activation Details");
        } else {
            batchName = data.termination_batch;
            // Sync Logic: Show Source Activation
            sourceInfo = (data.source_activation_batch) ? `Linked to Activation: ${data.source_activation_batch}` : 'Manual Input / No Source Link';
            dateLog = data.termination_date;
            mainQty = parseInt(data.terminated_qty).toLocaleString();
            subQty = parseInt(data.unterminated_qty).toLocaleString();
            mainLabel = "Terminated Qty";
            subLabel = "Remaining Active";
            $('#detailTitle').text("Termination Details");
        }

        $('#det_batch').text(batchName);
        $('#det_total').text(totalSim);
        $('#det_client').text(clientName);
        $('#det_project').text(projectName);
        $('#det_source').text(sourceInfo);
        
        $('#det_date').text(dateLog);
        $('#det_action_title').text(mainLabel);
        $('#det_main_qty').text(mainQty);
        $('#det_sub_qty').text(subQty);

        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // 5. AUTO FILL & CALCULATION LOGIC
    function fillFromSourcePO() {
        let selected = $('#inp_source_po option:selected');
        let qty = selected.data('qty');
        let batch = selected.data('batch');
        let comp = selected.data('comp');
        let proj = selected.data('proj');

        if(qty) {
            $('#inp_total').val(qty);
            $('#inp_batch').val(batch);
            if(comp) {
                $('#inp_company_id').val(comp);
                updateProjectDropdown(comp, proj);
            }
            $('#inp_qty_1').val('');
            $('#inp_qty_2').val(qty); 
        }
    }

    function fillFromSourceAct() {
        let selected = $('#inp_source_act option:selected');
        let total = selected.data('total');
        let active = selected.data('active');
        let comp = selected.data('comp');
        let proj = selected.data('proj');

        if(total) {
            $('#inp_total').val(total); 
            if(comp) {
                $('#inp_company_id').val(comp);
                updateProjectDropdown(comp, proj);
            }
            $('#inp_qty_1').data('max-active', active); 
            $('#inp_qty_1').val(0); 
            $('#inp_qty_2').val(active); 
        }
    }

    function calculateRemaining() {
        let total = parseInt($('#inp_total').val()) || 0;
        let qty1 = parseInt($('#inp_qty_1').val()) || 0;

        if(currentMode === 'act') {
            let inactive = total - qty1;
            $('#inp_qty_2').val(inactive < 0 ? 0 : inactive);
        } else {
            let initialActive = $('#inp_qty_1').data('max-active') || total;
            let remaining = initialActive - qty1;
            $('#inp_qty_2').val(remaining < 0 ? 0 : remaining);
        }
    }

    // 6. DATATABLES
    $(document).ready(function() {
        var tableAct = $('#table-activation').DataTable({
            language: { search: '', searchPlaceholder: '' },
            searching: true, ordering: false, autoWidth: false, pageLength: 10,
            dom: 't<"row px-4 py-3 border-top align-items-center"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>'
        });
        $('#searchAct').on('keyup', function() { tableAct.search(this.value).draw(); });
        $('#lengthAct').on('change', function() { tableAct.page.len(this.value).draw(); });
        $('#filterClientAct').on('change', function() { tableAct.column(1).search(this.value).draw(); });

        var tableTerm = $('#table-termination').DataTable({
            language: { search: '', searchPlaceholder: '' },
            searching: true, ordering: false, autoWidth: false, pageLength: 10,
            dom: 't<"row px-4 py-3 border-top align-items-center"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>'
        });
        $('#searchTerm').on('keyup', function() { tableTerm.search(this.value).draw(); });
        $('#lengthTerm').on('change', function() { tableTerm.page.len(this.value).draw(); });
        $('#filterClientTerm').on('change', function() { tableTerm.column(1).search(this.value).draw(); });
    });
</script>

<?php require_once 'includes/footer.php'; ?>