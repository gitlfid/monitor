<?php
// =========================================================================
// FILE: sim_tracking_client_po.php
// UPDATE: Pixel Perfect Icon Alignment (Folder & Button)
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
$existing_products = []; 

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
        $prodSql = "SELECT DISTINCT product_name FROM sim_tracking_po WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name ASC";
        $existing_products = $db->query($prodSql)->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $res = mysqli_query($db, $sql);
        if ($res) { while ($row = mysqli_fetch_assoc($res)) $data[] = $row; }
        $prodRes = mysqli_query($db, "SELECT DISTINCT product_name FROM sim_tracking_po WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name ASC");
        while($r = mysqli_fetch_assoc($prodRes)) $existing_products[] = $r['product_name'];
    }

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

// DROPDOWN DATA
$clients = []; $providers = []; $projects_raw = [];
try {
    if ($db_type === 'pdo') {
        $clients = $db->query("SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($clients)) $clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $providers = $db->query("SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($providers)) $providers = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $projects_raw = $db->query("SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $r1 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC"); while($r=mysqli_fetch_assoc($r1))$clients[]=$r;
        $r2 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC"); while($r=mysqli_fetch_assoc($r2))$providers[]=$r;
        $r3 = mysqli_query($db, "SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC"); while($r=mysqli_fetch_assoc($r3))$projects_raw[]=$r;
    }
} catch (Exception $e) {}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
    
    :root {
        --bg-body: #f8fafc;
        --border-color: #e2e8f0;
        --text-primary: #0f172a;
        --text-secondary: #64748b;
        --brand-color: #4f46e5;
        --brand-hover: #4338ca;
        --tbl-header: #f1f5f9;
    }

    body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-primary); }

    /* CARD MODERN */
    .card-modern { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; overflow: hidden; }
    .card-header-modern { padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fff; }

    /* TABLE STYLING */
    .table-responsive { border-radius: 0 0 12px 12px; overflow-x: auto; }
    .table-modern { width: 100%; border-collapse: collapse; white-space: nowrap; }
    .table-modern th { background-color: var(--tbl-header); color: var(--text-secondary); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; padding: 16px 24px; border-bottom: 1px solid var(--border-color); text-align: left; }
    .table-modern td { padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-primary); }
    .table-modern tr:last-child td { border-bottom: none; }
    .table-modern tr:hover td { background-color: #fcfcfc; }

    /* COLUMN WIDTHS */
    .w-date   { width: 15%; min-width: 160px; }
    .w-client { width: 25%; min-width: 240px; } 
    .w-prod   { width: 20%; min-width: 200px; }
    .w-qty    { width: 10%; min-width: 100px; text-align: right !important; }
    .w-file   { width: 8%; min-width: 80px; text-align: center !important; }
    .w-action { width: 10%; min-width: 80px; text-align: center !important; }

    /* BADGES & TEXT */
    .badge-po { font-family: 'Consolas', monospace; font-weight: 600; color: var(--brand-color); background: #eef2ff; padding: 4px 8px; border-radius: 6px; border: 1px solid #c7d2fe; font-size: 0.8rem; }
    .badge-batch { font-size: 0.7rem; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; border: 1px solid #e2e8f0; text-transform: uppercase; }
    .text-date { font-weight: 600; color: #64748b; font-size: 0.8rem; display: block; margin-bottom: 4px; }
    .client-name { font-weight: 700; color: #0f172a; display: block; margin-bottom: 3px; font-size: 0.9rem; white-space: normal; line-height: 1.3; }
    
    /* --- FIX: PROJECT NAME ALIGNMENT --- */
    .project-name { 
        font-size: 0.85rem; 
        color: var(--text-secondary); 
        display: flex; 
        align-items: center; /* Kunci vertical center */
        gap: 6px; 
    }
    .project-name i {
        font-size: 1.1em;
        line-height: 1; /* Reset line height icon */
        display: flex;
        margin-top: -2px; /* Visual tweak agar lurus dengan text */
    }
    
    /* --- FIX: BUTTON CREATE NEW PO --- */
    .btn-primary-new { 
        background: var(--brand-color); 
        color: white; 
        border: none; 
        padding: 10px 24px; /* Padding balanced */
        border-radius: 8px; 
        font-weight: 600; 
        font-size: 0.9rem; 
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); 
        transition: 0.2s; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; /* Center content */
        gap: 8px; 
        text-decoration: none; 
        line-height: 1.2; /* Consistent line height */
    }
    .btn-primary-new i {
        font-size: 1.2rem;
        line-height: 0; 
        display: flex; 
        margin-top: -2px; /* Visual tweak for plus icon */
    }
    .btn-primary-new:hover { background: var(--brand-hover); color: white; transform: translateY(-1px); }

    /* Other Styles */
    .badge-product { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px; display: inline-block; margin-bottom: 4px; }
    .detail-text { font-size: 0.85rem; color: #64748b; font-style: italic; white-space: normal; line-height: 1.3; max-width: 250px; display: block; }
    .qty-text { font-family: 'Inter', sans-serif; font-weight: 700; font-size: 0.95rem; color: #0f172a; }
    .btn-icon-soft { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; transition: all 0.2s; }
    .btn-icon-soft:hover { background: #f8fafc; color: var(--text-primary); border-color: #cbd5e1; }
    .filter-bar { background: #fff; padding: 20px 24px; border-bottom: 1px solid var(--border-color); }
    .form-label-sm { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
    .form-control, .form-select { border-color: #e2e8f0; font-size: 0.9rem; padding: 0.5rem 0.75rem; border-radius: 6px; }
    .form-control:focus, .form-select:focus { border-color: var(--brand-color); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    .modal-content { border: none; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
    .modal-body { padding: 24px; background-color: #f8fafc; }
    .card-form { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.02); height: 100%; }
    .modal-footer { padding: 16px 24px; background: #fff; border-top: 1px solid #e2e8f0; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 px-1">
    <div>
        <h4 class="mb-1 fw-bold text-dark">Client Purchase Orders</h4>
        <p class="text-muted mb-0 small">Track and manage incoming client orders efficiently.</p>
    </div>
    <button class="btn-primary-new" onclick="openAddModal()">
        <i class="bi bi-plus"></i> Create New PO
    </button>
</div>

<div class="card-modern">
    <div class="card-body pt-4 px-4">
        <div class="d-flex align-items-center mb-4">
            <span class="bg-primary bg-opacity-10 p-2 rounded me-3 text-primary"><i class="bi bi-graph-up-arrow"></i></span>
            <h6 class="fw-bold m-0">Order Volume Analysis</h6>
        </div>
        <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
            <div class="text-center py-5 bg-light rounded text-muted small">No data available to display.</div>
        <?php else: ?>
            <div id="clientChart" style="height: 260px;"></div>
        <?php endif; ?>
    </div>
</div>

<div class="card-modern">
    <div class="card-header-modern pb-0 border-0">
        <div class="d-flex align-items-center">
            <span class="bg-warning bg-opacity-10 p-2 rounded me-3 text-warning"><i class="bi bi-database"></i></span>
            <h6 class="m-0 fw-bold text-dark">PO Database</h6>
        </div>
    </div>
    
    <div class="filter-bar">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label-sm">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="customSearch" class="form-control border-start-0 ps-0" placeholder="Type to search...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Client</label>
                <select id="filterClient" class="form-select"><option value="">All Clients</option><?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-3">
                <label class="form-label-sm">Project</label>
                <select id="filterProject" class="form-select"><option value="">All Projects</option><?php $unique_projects = []; foreach($projects_raw as $p) $unique_projects[$p['project_name']] = $p['project_name']; foreach($unique_projects as $pname): ?><option value="<?= htmlspecialchars($pname) ?>"><?= htmlspecialchars($pname) ?></option><?php endforeach; ?></select>
            </div>
            <div class="col-md-3 text-end d-flex align-items-end justify-content-end">
                <div class="d-flex align-items-center">
                    <span class="small text-muted me-2 fw-bold text-uppercase" style="font-size: 0.7rem;">Show</span>
                    <select id="customLength" class="form-select w-auto"><option value="10">10</option><option value="50">50</option><option value="100">100</option></select>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-modern" id="table-client">
            <thead>
                <tr>
                    <th class="w-date ps-4">Date & PO Info</th>
                    <th class="w-client">Client & Project</th>
                    <th class="w-prod">Product & Details</th>
                    <th class="w-qty">Qty</th>
                    <th class="w-file">File</th>
                    <th class="w-action pe-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): 
                    $q_fmt = number_format((int)preg_replace('/[^0-9]/', '', $row['sim_qty']));
                    $d_val = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
                    $p_date = date('d M Y', strtotime($d_val));
                    
                    $prod = !empty($row['product_name']) ? htmlspecialchars($row['product_name']) : 'General';
                    $detail = !empty($row['detail']) ? htmlspecialchars($row['detail']) : '-';
                    $batch = htmlspecialchars($row['batch_name'] ?? '-');
                ?>
                <tr>
                    <td class="ps-4">
                        <span class="text-date"><?= $p_date ?></span>
                        <span class="badge-po"><?= htmlspecialchars($row['po_number']) ?></span>
                        <div class="mt-1"><span class="badge-batch"><?= $batch ?></span></div>
                    </td>
                    <td>
                        <span class="client-name"><?= htmlspecialchars($row['display_company'] ?? '-') ?></span>
                        <div class="project-name">
                            <i class="bi bi-folder2 text-warning"></i> 
                            <?= htmlspecialchars($row['display_project'] ?? '-') ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge-product"><?= $prod ?></span>
                        <span class="detail-text" title="<?= $detail ?>"><?= $detail ?></span>
                    </td>
                    <td style="text-align: right;">
                        <span class="qty-text"><?= $q_fmt ?></span>
                    </td>
                    <td style="text-align: center;">
                        <?php if(!empty($row['po_file'])): ?>
                            <a href="uploads/po/<?= $row['po_file'] ?>" target="_blank" class="btn-icon-soft text-primary bg-primary bg-opacity-10 border-primary" title="View Document">
                                <i class="bi bi-file-earmark-text-fill"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted opacity-25"><i class="bi bi-dash"></i></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;" class="pe-4">
                        <div class="dropdown">
                            <button class="btn-icon-soft" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2">
                                <li><a class="dropdown-item py-2 px-3" href="#" onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Details</a></li>
                                <li><a class="dropdown-item py-2 px-3" href="#" onclick='openToProviderModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-box-seam me-2 text-info"></i> Send to Provider</a></li>
                                <li><a class="dropdown-item py-2 px-3" href="#" onclick='printPO(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-printer me-2 text-secondary"></i> Print Document</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-2 px-3 text-danger" href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=client" onclick="return confirm('Are you sure you want to delete this record?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<datalist id="product_list"><?php foreach($existing_products as $prod): ?><option value="<?= htmlspecialchars($prod) ?>"><?php endforeach; ?></datalist>

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" value="create"><input type="hidden" name="type" value="client">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Create New Client PO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-center mb-4">
                    <div class="btn-group bg-white shadow-sm p-1 rounded-pill border">
                        <input type="radio" class="btn-check" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')"><label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="add_mode_datapool">Database</label>
                        <input type="radio" class="btn-check" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')"><label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="add_mode_manual">Manual</label>
                    </div>
                </div>
                <div class="row g-4 h-100">
                    <div class="col-md-6 d-flex"><div class="card-form w-100">
                        <h6 class="text-uppercase text-primary fw-bold small mb-3 border-bottom pb-2">Client Info</h6>
                        <div id="add_section_datapool">
                            <div class="mb-3"><label class="form-label-sm">Client Name</label><select name="company_id" id="add_company_id" class="form-select" onchange="filterProjects('add')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select></div>
                            <div class="mb-3"><label class="form-label-sm">Project</label><select name="project_id" id="add_project_id" class="form-select"><option value="">-- Select --</option></select></div>
                        </div>
                        <div id="add_section_manual" class="d-none">
                            <div class="mb-3"><label class="form-label-sm">Client Name</label><input type="text" name="manual_company_name" id="add_manual_company" class="form-control"></div>
                            <div class="mb-3"><label class="form-label-sm">Project Name</label><input type="text" name="manual_project_name" id="add_manual_project" class="form-control"></div>
                        </div>
                        <h6 class="text-uppercase text-primary fw-bold small mt-4 mb-3 border-bottom pb-2">Product Info</h6>
                        <div class="mb-3"><label class="form-label-sm">Product</label><input type="text" name="product_name" class="form-control" list="product_list" placeholder="Search or Type..."></div>
                        <div><label class="form-label-sm">Detail</label><textarea name="detail" class="form-control" rows="2" placeholder="e.g. Specification..."></textarea></div>
                    </div></div>
                    <div class="col-md-6 d-flex"><div class="card-form w-100">
                        <h6 class="text-uppercase text-primary fw-bold small mb-3 border-bottom pb-2">Order Details</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label-sm">Date</label><input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-6"><label class="form-label-sm">Qty</label><input type="number" name="sim_qty" class="form-control fw-bold" placeholder="0"></div>
                        </div>
                        <div class="mb-3"><label class="form-label-sm">PO Number</label><input type="text" name="po_number" class="form-control font-monospace" required></div>
                        <div class="mb-3"><label class="form-label-sm">Batch Name</label><input type="text" name="batch_name" class="form-control" required></div>
                        <div><label class="form-label-sm">Attachment</label><input type="file" name="po_file" class="form-control"></div>
                    </div></div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top"><button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">Save Record</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" value="update"><input type="hidden" name="type" value="client"><input type="hidden" name="id" id="edit_id"><input type="hidden" name="existing_file" id="edit_existing_file">
            <div class="modal-header bg-warning text-dark py-3"><h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="d-flex justify-content-center mb-4">
                    <div class="btn-group bg-white shadow-sm p-1 rounded-pill border">
                        <input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_datapool" value="datapool" onchange="toggleInputMode('edit')"><label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="edit_mode_datapool">Database</label>
                        <input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_manual" value="manual" onchange="toggleInputMode('edit')"><label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="edit_mode_manual">Manual</label>
                    </div>
                </div>
                <div class="row g-4 h-100">
                    <div class="col-md-6 d-flex"><div class="card-form w-100">
                        <h6 class="text-uppercase text-warning fw-bold small mb-3 border-bottom pb-2">Client & Product</h6>
                        <div id="edit_section_datapool">
                            <div class="mb-3"><label class="form-label-sm">Client</label><select name="company_id" id="edit_company_id" class="form-select" onchange="filterProjects('edit')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select></div>
                            <div class="mb-3"><label class="form-label-sm">Project</label><select name="project_id" id="edit_project_id" class="form-select"><option value="">-- Select --</option></select></div>
                        </div>
                        <div id="edit_section_manual" class="d-none">
                            <div class="mb-3"><label class="form-label-sm">Client</label><input type="text" name="manual_company_name" id="edit_manual_company" class="form-control"></div>
                            <div class="mb-3"><label class="form-label-sm">Project</label><input type="text" name="manual_project_name" id="edit_manual_project" class="form-control"></div>
                        </div>
                        <div class="mb-3"><label class="form-label-sm">Product</label><input type="text" name="product_name" id="edit_product_name" class="form-control" list="product_list"></div>
                        <div><label class="form-label-sm">Detail</label><textarea name="detail" id="edit_detail" class="form-control" rows="2"></textarea></div>
                    </div></div>
                    <div class="col-md-6 d-flex"><div class="card-form w-100">
                        <h6 class="text-uppercase text-warning fw-bold small mb-3 border-bottom pb-2">Order Details</h6>
                        <div class="row g-2 mb-3"><div class="col-6"><label class="form-label-sm">Date</label><input type="date" name="po_date" id="edit_po_date" class="form-control"></div><div class="col-6"><label class="form-label-sm">Qty</label><input type="number" name="sim_qty" id="edit_sim_qty" class="form-control"></div></div>
                        <div class="mb-3"><label class="form-label-sm">PO No</label><input type="text" name="po_number" id="edit_po_number" class="form-control font-monospace"></div>
                        <div class="mb-3"><label class="form-label-sm">Batch</label><input type="text" name="batch_name" id="edit_batch_name" class="form-control"></div>
                        <div><label class="form-label-sm">File</label><input type="file" name="po_file" class="form-control"><small id="current_file_info" class="text-muted d-block mt-1 fst-italic"></small></div>
                    </div></div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top"><button type="submit" class="btn btn-warning px-4 fw-bold shadow-sm">Update Changes</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalToProvider" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" value="create_provider_from_client"><input type="hidden" name="link_client_po_id" id="tp_client_po_id">
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>To Provider</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 bg-light">
                <div class="alert alert-white border-success shadow-sm d-flex align-items-center mb-4">
                    <i class="bi bi-arrow-right-circle-fill fs-3 text-success me-3"></i>
                    <div><small class="text-uppercase text-muted fw-bold">Source Reference</small><div class="fw-bold text-dark"><span id="tp_display_client"></span> | <span id="tp_display_po" class="font-monospace"></span></div></div>
                </div>
                <div class="card border-0 shadow-sm p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-sm">Provider</label>
                            <select name="provider_company_id" class="form-select mb-2" required><option value="">-- Choose --</option><?php foreach($providers as $p): ?><option value="<?= $p['id'] ?>"><?= $p['company_name'] ?></option><?php endforeach; ?></select>
                            <input type="text" name="manual_provider_name" class="form-control form-control-sm" placeholder="Or Manual Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-sm">Provider PO No.</label><input type="text" name="provider_po_number" class="form-control mb-2" required>
                            <div class="row"><div class="col-6"><label class="form-label-sm">Date</label><input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>"></div><div class="col-6"><label class="form-label-sm">Qty</label><input type="number" name="sim_qty" id="tp_sim_qty" class="form-control" required></div></div>
                        </div>
                        <input type="hidden" name="batch_name" id="tp_batch_name"><div class="col-12"><label class="form-label-sm">File</label><input type="file" name="po_file" class="form-control"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top"><button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">Process Transfer</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    const allProjects = <?php echo json_encode($projects_raw); ?>;
    const chartLabels = <?php echo json_encode($js_chart_labels); ?>;
    const chartSeries = <?php echo json_encode($js_chart_series); ?>;

    if (chartSeries.length > 0) {
        new ApexCharts(document.querySelector('#clientChart'), {
            series: [{ name: 'Quantity', data: chartSeries }],
            chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 } },
            dataLabels: { enabled: true, style: { colors: ['#4f46e5'] } },
            xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px', fontWeight: 'bold' } } },
            colors: ['#4f46e5'],
            grid: { borderColor: '#f1f5f9' },
            tooltip: { y: { formatter: function (val) { return new Intl.NumberFormat('id-ID').format(val) + ' Pcs' } } }
        }).render();
    }

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

    function openAddModal() {
        $('#add_mode_datapool').prop('checked', true); toggleInputMode('add'); 
        $('#add_company_id').val(''); $('#add_manual_company').val('');
        $('input[name="product_name"]').val(''); $('textarea[name="detail"]').val('');
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
        $('#edit_product_name').val(data.product_name);
        $('#edit_detail').val(data.detail);
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