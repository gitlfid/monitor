<?php
// =========================================================================
// 1. SETUP & LOGIC PHP
// =========================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
$current_page = 'sim_tracking_client_po.php';

if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// Database Connection
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }

// FETCH DATA
$data = [];
$chart_data_grouped = [];

try {
    $sql = "SELECT st.*, 
            COALESCE(c.company_name, st.manual_company_name) as display_company,
            COALESCE(p.project_name, st.manual_project_name) as display_project
            FROM sim_tracking_po st
            LEFT JOIN companies c ON st.company_id = c.id
            LEFT JOIN projects p ON st.project_id = p.id
            WHERE st.type = 'client' 
            ORDER BY st.id DESC"; 

    if ($db_type === 'pdo') {
        $data = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = mysqli_query($db, $sql);
        if ($res) { while ($row = mysqli_fetch_assoc($res)) $data[] = $row; }
    }

    // Chart Data
    foreach ($data as $row) {
        $raw_date = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
        $date_label = date('d M Y', strtotime($raw_date));
        $qty = (int)preg_replace('/[^0-9]/', '', $row['sim_qty']);
        if (!isset($chart_data_grouped[$date_label])) $chart_data_grouped[$date_label] = 0;
        $chart_data_grouped[$date_label] += $qty;
    }
    $chart_data_grouped = array_reverse(array_slice($chart_data_grouped, 0, 7, true));
} catch (Exception $e) {}

$js_chart_labels = array_keys($chart_data_grouped);
$js_chart_series = array_values($chart_data_grouped);

// FETCH DROPDOWN OPTIONS
$clients = []; $providers = []; $projects_raw = [];
try {
    if ($db_type === 'pdo') {
        $clients = $db->query("SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($clients)) $clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $providers = $db->query("SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($providers)) $providers = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $projects_raw = $db->query("SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $r1 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC");
        while($x=mysqli_fetch_assoc($r1)) $clients[]=$x;
        
        $rP = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC");
        while($x=mysqli_fetch_assoc($rP)) $providers[]=$x;
        
        $r2 = mysqli_query($db, "SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC");
        while($y=mysqli_fetch_assoc($r2)) $projects_raw[]=$y;
    }
} catch (Exception $e) {}
?>

<style>
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom th { background-color: #f9fafb; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .table-custom td { padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #334155; }
    .table-custom tr:hover td { background-color: #f8fafc; }

    .col-date   { width: 140px; min-width: 140px; }
    .col-po     { width: 150px; font-family: 'Consolas', monospace; color: #475569; font-weight: 600; }
    .col-qty    { width: 100px; text-align: right; font-weight: bold; }
    .col-file   { width: 60px; text-align: center; }
    .col-action { width: 80px; text-align: center; }

    .filter-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .form-control-sm, .form-select-sm { border-radius: 6px; border-color: #e2e8f0; }
    .search-wrapper { position: relative; }
    .search-icon { position: absolute; right: 10px; top: 9px; color: #94a3b8; }

    .badge-batch { background-color: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; margin-top: 4px; }
    .btn-action-menu { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-action-menu:hover { background-color: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
    .icon-file { color: #94a3b8; font-size: 1.2rem; transition: 0.2s; }
    .icon-file:hover { color: #2563eb; }
    
    .dataTables_wrapper .row:last-child { padding: 15px 24px; border-top: 1px solid #f1f5f9; align-items: center; }
    .page-link { border-radius: 6px; margin: 0 3px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; padding: 6px 12px; }
    .page-item.active .page-link { background-color: #435ebe; border-color: #435ebe; color: white; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Client PO Tracking</h3>
            <p class="text-muted mb-0 small">Monitor Purchase Orders and Inventory.</p>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-graph-up me-2"></i>Daily Quantity Analysis</h6>
            <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
                <div class="text-center py-5 bg-light rounded text-muted"><p class="mb-0 small">No data to display.</p></div>
            <?php else: ?>
                <div id="clientChart" style="height: 280px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-list-check me-2"></i>PO Data List</h6>
                <button class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" onclick="openAddModal()">
                    <i class="bi bi-plus me-1"></i> New PO
                </button>
            </div>

            <div class="filter-box">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Search</label>
                        <div class="search-wrapper">
                            <input type="text" id="customSearch" class="form-control form-control-sm ps-3" placeholder="Type PO Number, Client...">
                            <i class="bi bi-search search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Client</label>
                        <select id="filterClient" class="form-select form-select-sm"><option value="">All Clients</option><?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Project</label>
                        <select id="filterProject" class="form-select form-select-sm"><option value="">All Projects</option><?php $unique_projects = []; foreach($projects_raw as $p) $unique_projects[$p['project_name']] = $p['project_name']; foreach($unique_projects as $pname): ?><option value="<?= htmlspecialchars($pname) ?>"><?= htmlspecialchars($pname) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Show</label>
                        <select id="customLength" class="form-select form-select-sm"><option value="10">10 Rows</option><option value="50">50 Rows</option><option value="100">100 Rows</option></select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0"> 
            <div class="table-responsive">
                <table class="table-custom" id="table-client">
                    <thead>
                        <tr>
                            <th class="col-date ps-4">PO Date</th>
                            <th>Client Name</th>
                            <th>Project / Batch</th>
                            <th class="col-po">PO Number</th>
                            <th class="col-qty">Qty</th>
                            <th class="col-file">File</th>
                            <th class="col-action pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): 
                            $q_raw = preg_replace('/[^0-9]/', '', $row['sim_qty']);
                            $q_fmt = number_format((int)$q_raw);
                            $d_val = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
                            $p_date = date('d/m/Y', strtotime($d_val));
                        ?>
                        <tr>
                            <td class="col-date ps-4 text-secondary fw-bold"><?= $p_date ?></td>
                            <td>
                                <span class="fw-bold text-dark d-block text-wrap text-uppercase" style="max-width: 250px; line-height:1.4;">
                                    <?= htmlspecialchars($row['display_company'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="d-none"><?= htmlspecialchars($row['display_project'] ?? '') ?></span>
                                <div class="text-muted small mb-1 text-wrap text-uppercase" style="max-width: 200px;">
                                    <?= htmlspecialchars($row['display_project'] ?? '-') ?>
                                </div>
                                <span class="badge-batch text-uppercase"><?= htmlspecialchars($row['batch_name'] ?? '-') ?></span>
                            </td>
                            <td class="col-po text-uppercase"><?= htmlspecialchars($row['po_number']) ?></td>
                            <td class="col-qty text-success"><?= $q_fmt ?></td>
                            <td class="col-file">
                                <?php if(!empty($row['po_file'])): ?>
                                    <a href="uploads/po/<?= $row['po_file'] ?>" target="_blank" class="icon-file" title="View Document"><i class="bi bi-file-earmark-text"></i></a>
                                <?php else: ?>
                                    <span class="text-muted opacity-25">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-action pe-4">
                                <div class="dropdown">
                                    <button class="btn-action-menu" type="button" data-bs-toggle="dropdown">Action <i class="bi bi-chevron-down small ms-1"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-1">
                                        <li><a class="dropdown-item" href="#" onclick='openToProviderModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-truck me-2 text-primary"></i> To Provider</a></li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li><a class="dropdown-item" href="#" onclick='printPO(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-printer me-2 text-dark"></i> Print PO</a></li>
                                        <li><a class="dropdown-item" href="#" onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=client" onclick="return confirm('Delete this record?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
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
</section>

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="create"><input type="hidden" name="type" value="client">
            <div class="modal-header bg-primary text-white py-3"><h6 class="modal-title m-0 fw-bold">Create New Client PO</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12 mb-2"><div class="btn-group w-100"><input type="radio" class="btn-check" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')"><label class="btn btn-outline-primary btn-sm" for="add_mode_datapool">Database</label><input type="radio" class="btn-check" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')"><label class="btn btn-outline-primary btn-sm" for="add_mode_manual">Manual</label></div></div>
                    <div class="col-md-6">
                        <div id="add_section_datapool"><label class="form-label fw-bold">Client</label><select name="company_id" id="add_company_id" class="form-select" onchange="filterProjects('add')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select><label class="form-label mt-3 fw-bold">Project</label><select name="project_id" id="add_project_id" class="form-select"><option value="">-- Select --</option></select></div>
                        <div id="add_section_manual" class="d-none">
                            <label class="form-label fw-bold">Client Name</label>
                            <input type="text" name="manual_company_name" id="add_manual_company" class="form-control" oninput="this.value = this.value.toUpperCase()">
                            <label class="form-label mt-3 fw-bold">Project Name</label>
                            <input type="text" name="manual_project_name" id="add_manual_project" class="form-control" oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row"><div class="col-6 mb-3"><label class="form-label fw-bold">Date</label><input type="date" name="po_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div><div class="col-6 mb-3"><label class="form-label fw-bold">Qty</label><input type="number" name="sim_qty" class="form-control" required placeholder="0"></div></div>
                        <div class="mb-3"><label class="form-label fw-bold">PO Number</label><input type="text" name="po_number" class="form-control" required oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mb-3"><label class="form-label fw-bold">Batch Name</label><input type="text" name="batch_name" class="form-control" required oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mb-0"><label class="form-label fw-bold">Attachment</label><input type="file" name="po_file" class="form-control"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary px-4 fw-bold">Save Record</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="update"><input type="hidden" name="type" value="client"><input type="hidden" name="id" id="edit_id"><input type="hidden" name="existing_file" id="edit_existing_file">
            <div class="modal-header bg-warning text-dark py-3"><h6 class="modal-title m-0 fw-bold">Edit PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12 mb-2"><div class="btn-group w-100"><input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_datapool" value="datapool" onchange="toggleInputMode('edit')"><label class="btn btn-outline-warning btn-sm" for="edit_mode_datapool">Database</label><input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_manual" value="manual" onchange="toggleInputMode('edit')"><label class="btn btn-outline-warning btn-sm" for="edit_mode_manual">Manual</label></div></div>
                    <div class="col-md-6">
                        <div id="edit_section_datapool"><label class="form-label fw-bold">Client</label><select name="company_id" id="edit_company_id" class="form-select" onchange="filterProjects('edit')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select><label class="form-label mt-3 fw-bold">Project</label><select name="project_id" id="edit_project_id" class="form-select"><option value="">-- Select --</option></select></div>
                        <div id="edit_section_manual" class="d-none">
                            <label class="form-label fw-bold">Client Name</label>
                            <input type="text" name="manual_company_name" id="edit_manual_company" class="form-control" oninput="this.value = this.value.toUpperCase()">
                            <label class="form-label mt-3 fw-bold">Project Name</label>
                            <input type="text" name="manual_project_name" id="edit_manual_project" class="form-control" oninput="this.value = this.value.toUpperCase()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row"><div class="col-6 mb-3"><label class="form-label fw-bold">Date</label><input type="date" name="po_date" id="edit_po_date" class="form-control" required></div><div class="col-6 mb-3"><label class="form-label fw-bold">Qty</label><input type="number" name="sim_qty" id="edit_sim_qty" class="form-control" required></div></div>
                        <div class="mb-3"><label class="form-label fw-bold">PO Number</label><input type="text" name="po_number" id="edit_po_number" class="form-control" required oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mb-3"><label class="form-label fw-bold">Batch Name</label><input type="text" name="batch_name" id="edit_batch_name" class="form-control" required oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mb-0"><label class="form-label fw-bold">Attachment</label><input type="file" name="po_file" class="form-control"><small id="current_file_info" class="text-muted d-block mt-1 small"></small></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-warning px-4 fw-bold">Update</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalToProvider" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="create_provider_from_client"><input type="hidden" name="link_client_po_id" id="tp_client_po_id">
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title m-0 fw-bold"><i class="bi bi-truck me-2"></i>Create Provider PO</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 bg-light">
                <div class="card p-3 mb-4 border-success" style="border-left: 4px solid #198754;">
                    <h6 class="fw-bold text-success mb-2 small text-uppercase">Source: Client PO</h6>
                    <div class="row small text-muted"><div class="col-md-6">Client: <strong id="tp_display_client" class="text-dark"></strong><br>Project: <span id="tp_display_project"></span></div><div class="col-md-6">PO: <strong id="tp_display_po" class="text-dark"></strong><br>Batch: <span id="tp_display_batch"></span></div></div>
                </div>
                <h6 class="fw-bold text-dark mb-3 small text-uppercase">Provider Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Provider <span class="text-danger">*</span></label>
                        <select name="provider_company_id" class="form-select" required><option value="">-- Choose --</option><?php foreach($providers as $p): ?><option value="<?= $p['id'] ?>"><?= $p['company_name'] ?></option><?php endforeach; ?></select>
                        <div class="mt-2"><input type="text" name="manual_provider_name" class="form-control form-control-sm" placeholder="Manual Provider Name" oninput="this.value = this.value.toUpperCase()"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3"><label class="form-label fw-bold small text-uppercase">Provider PO No. <span class="text-danger">*</span></label><input type="text" name="provider_po_number" class="form-control border-success" required placeholder="Enter PO No." oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="row"><div class="col-6"><label class="form-label fw-bold small text-uppercase">Date</label><input type="date" name="po_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div><div class="col-6"><label class="form-label fw-bold small text-uppercase">Qty</label><input type="number" name="sim_qty" id="tp_sim_qty" class="form-control bg-white" required></div></div>
                    </div>
                    <input type="hidden" name="batch_name" id="tp_batch_name"><div class="col-12 mt-2"><label class="form-label fw-bold small text-uppercase">Document</label><input type="file" name="po_file" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer bg-white py-3"><button type="submit" class="btn btn-success px-4 fw-bold">Process & Save</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    const allProjects = <?php echo json_encode($projects_raw); ?>;
    const chartLabels = <?php echo json_encode($js_chart_labels); ?>;
    const chartSeries = <?php echo json_encode($js_chart_series); ?>;

    // 1. Chart
    document.addEventListener('DOMContentLoaded', function () {
        if (chartSeries.length > 0) {
            var options = {
                series: [{ name: 'Quantity', data: chartSeries }],
                chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 } },
                dataLabels: { enabled: true, style: { colors: ['#435ebe'] } },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px', fontWeight: 'bold' } } },
                colors: ['#435ebe'],
                grid: { borderColor: '#f1f5f9' },
                tooltip: { y: { formatter: function (val) { return new Intl.NumberFormat('id-ID').format(val) + ' Pcs' } } }
            };
            var chart = new ApexCharts(document.querySelector('#clientChart'), options);
            chart.render();
        }
    });

    // 2. Logic Form
    function toggleInputMode(mode) {
        let isDP = $('#' + mode + '_mode_datapool').is(':checked');
        if (isDP) {
            $('#' + mode + '_section_datapool').removeClass('d-none'); $('#' + mode + '_section_manual').addClass('d-none');
            $('#' + mode + '_company_id').attr('required', 'required'); $('#' + mode + '_manual_company').removeAttr('required');
        } else {
            $('#' + mode + '_section_datapool').addClass('d-none'); $('#' + mode + '_section_manual').removeClass('d-none');
            $('#' + mode + '_company_id').removeAttr('required'); $('#' + mode + '_manual_company').attr('required', 'required');
        }
    }

    function filterProjects(mode, selId = null) {
        let compId = $('#' + mode + '_company_id').val();
        let projSel = $('#' + mode + '_project_id');
        projSel.empty().append('<option value="">-- Select --</option>');
        if (compId) {
            allProjects.forEach(p => {
                if (p.company_id == compId) {
                    let s = (selId && p.id == selId) ? 'selected' : '';
                    projSel.append(`<option value="${p.id}" ${s}>${p.project_name}</option>`);
                }
            });
        }
    }

    // 3. Modals
    function openAddModal() {
        $('#add_mode_datapool').prop('checked', true); toggleInputMode('add'); 
        $('#add_company_id').val(''); $('#add_manual_company').val('');
        new bootstrap.Modal(document.getElementById('modalAdd')).show();
    }

    function openEditModal(data) {
        $('#edit_id').val(data.id);
        $('#edit_po_number').val(data.po_number);
        let pd = (data.po_date && data.po_date !== '0000-00-00') ? data.po_date : new Date().toISOString().split('T')[0];
        $('#edit_po_date').val(pd);
        let qtyClean = String(data.sim_qty).replace(/[^0-9]/g, '');
        $('#edit_sim_qty').val(qtyClean);
        $('#edit_batch_name').val(data.batch_name);
        $('#edit_existing_file').val(data.po_file);
        $('#current_file_info').text(data.po_file ? data.po_file : 'No file');
        if (data.company_id && data.company_id != 0) {
            $('#edit_mode_datapool').prop('checked', true); toggleInputMode('edit');
            $('#edit_company_id').val(data.company_id);
            filterProjects('edit', data.project_id);
        } else {
            $('#edit_mode_manual').prop('checked', true); toggleInputMode('edit');
            $('#edit_manual_company').val(data.manual_company_name);
            $('#edit_manual_project').val(data.manual_project_name);
        }
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    function openToProviderModal(data) {
        $('#tp_client_po_id').val(data.id);
        $('#tp_display_client').text(data.display_company || 'Manual');
        $('#tp_display_project').text(data.display_project || '-');
        $('#tp_display_po').text(data.po_number);
        $('#tp_display_batch').text(data.batch_name || '-');
        let qtyClean = String(data.sim_qty).replace(/[^0-9]/g, '');
        $('#tp_sim_qty').val(qtyClean);
        $('#tp_batch_name').val(data.batch_name);
        new bootstrap.Modal(document.getElementById('modalToProvider')).show();
    }

    function printPO(data) {
        let poDate = data.po_date ? new Date(data.po_date).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'}) : '-';
        let qty = new Intl.NumberFormat('id-ID').format(String(data.sim_qty).replace(/[^0-9]/g, ''));
        let company = data.display_company || '-';
        let project = data.display_project || '-';
        let batch = data.batch_name || '-';
        let poNum = data.po_number || '-';
        let win = window.open('', '', 'width=800,height=600');
        win.document.write(`<html><head><title>Print PO</title><style>body{font-family:Arial;padding:40px;color:#333}.header{text-align:center;border-bottom:2px solid #333;margin-bottom:30px}.meta td{padding:5px 15px 5px 0}.label{font-weight:bold}.content{width:100%;border-collapse:collapse;margin-top:20px}.content th,.content td{border:1px solid #ddd;padding:10px}.footer{margin-top:60px;display:flex;justify-content:space-between}.sig{text-align:center;border-top:1px solid #333;width:200px;margin-top:50px}</style></head><body><div class="header"><h1>Purchase Order</h1><p>${poNum}</p></div><table class="meta"><tr><td class="label">Client:</td><td>${company}</td><td class="label">Date:</td><td>${poDate}</td></tr><tr><td class="label">Project:</td><td>${project}</td><td class="label">Batch:</td><td>${batch}</td></tr></table><table class="content"><thead><tr><th>Item</th><th style="text-align:right">Qty</th></tr></thead><tbody><tr><td>SIM Card Procurement</td><td style="text-align:right"><strong>${qty} Pcs</strong></td></tr></tbody></table><div class="footer"><div class="sig">Prepared By</div><div class="sig">Approved By</div></div></body></html>`);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); }, 500);
    }

    $(document).ready(function() {
        var table = $('#table-client').DataTable({
            language: { search: '', searchPlaceholder: '' },
            searching: true, ordering: false, autoWidth: false,
            dom: 't<"row px-4 py-3 border-top align-items-center"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>',
            pageLength: 10
        });
        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
        $('#customLength').on('change', function() { table.page.len(this.value).draw(); });
        $('#filterClient').on('change', function() { table.column(1).search(this.value).draw(); });
        $('#filterProject').on('change', function() { table.column(2).search(this.value).draw(); });
    });
</script>

<?php require_once 'includes/footer.php'; ?>