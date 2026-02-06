<?php
// =========================================================================
// FILE: sim_tracking_client_po.php
// UPDATE: UI High Contrast, Better Spacing & Precision
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
        // Fallback MySQLi logic omitted for brevity
    }
} catch (Exception $e) {}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
    body { background-color: #f1f5f9; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }

    /* CARD STYLING */
    .card-modern { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); background: #fff; margin-bottom: 24px; }
    .card-header-modern { background: #fff; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
    
    /* TABLE PRESISI & COLOR FIX */
    .table-responsive { border-radius: 0 0 12px 12px; overflow-x: auto; }
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
    
    /* Header Tabel Lebih Tegas */
    .table-modern th { 
        background-color: #f8fafc; color: #475569; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
        padding: 14px 20px; border-bottom: 2px solid #e2e8f0; border-top: 1px solid #e2e8f0; white-space: nowrap; 
    }
    
    /* Isi Tabel Vertical Center */
    .table-modern td { 
        padding: 16px 20px; vertical-align: middle; 
        border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: #1e293b;
    }
    .table-modern tr:last-child td { border-bottom: none; }
    .table-modern tr:hover td { background-color: #f8fafc; }

    /* COLUMN WIDTHS (Agar Rapi) */
    .col-date   { width: 15%; min-width: 130px; }
    .col-info   { width: 25%; min-width: 220px; }
    .col-prod   { width: 25%; min-width: 200px; }
    .col-qty    { width: 15%; text-align: right; white-space: nowrap; font-family: 'Consolas', monospace; }
    .col-file   { width: 10%; text-align: center; }
    .col-action { width: 10%; text-align: center; }

    /* BADGES */
    .po-badge { font-family: 'Consolas', monospace; font-weight: 600; color: #4f46e5; background: #e0e7ff; padding: 4px 8px; border-radius: 6px; border: 1px solid #c7d2fe; display: inline-block; }
    .product-badge { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; display: inline-block; margin-bottom: 4px; }
    
    /* BUTTONS & ICONS */
    .btn-icon-soft { 
        width: 32px; height: 32px; 
        display: inline-flex; align-items: center; justify-content: center; 
        border-radius: 8px; border: 1px solid #e2e8f0; 
        color: #64748b; transition: all 0.2s; background: #fff;
    }
    .btn-icon-soft:hover { background-color: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
    
    .btn-new { 
        background: #4f46e5; color: white; border: none; padding: 10px 20px; 
        border-radius: 8px; font-weight: 600; font-size: 0.9rem;
        display: inline-flex; align-items: center; gap: 8px; 
        box-shadow: 0 2px 5px rgba(79, 70, 229, 0.2); transition: 0.2s; 
    }
    .btn-new:hover { background: #4338ca; transform: translateY(-1px); color: white; }

    /* FORM STYLES (Agar tidak menyaru) */
    .form-control, .form-select { border-color: #cbd5e1; }
    .form-control:focus, .form-select:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    
    /* MODAL IMPROVEMENT */
    .modal-content { border: none; border-radius: 16px; overflow: hidden; }
    .modal-header { border-bottom: 1px solid #e2e8f0; padding: 20px 24px; }
    .modal-body { background-color: #f8fafc; padding: 24px; } /* Background abu agar card putih terlihat */
    .modal-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.03); height: 100%; }
    .modal-footer { background: #fff; padding: 15px 24px; border-top: 1px solid #e2e8f0; }

    /* FILTER BOX */
    .filter-container { background: #fff; padding: 20px; border-bottom: 1px solid #e2e8f0; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Client PO</h3>
            <p class="text-muted mb-0 small">Manage purchase orders & product details.</p>
        </div>
        <button class="btn-new" onclick="openAddModal()">
            <i class="bi bi-plus-lg"></i> New PO
        </button>
    </div>
</div>

<section>
    <div class="card-modern">
        <div class="card-body pt-4">
            <div class="d-flex align-items-center mb-3 ms-2">
                <div class="bg-primary bg-opacity-10 p-2 rounded me-3"><i class="bi bi-bar-chart-fill text-primary"></i></div>
                <h6 class="fw-bold text-dark m-0">Order Trends</h6>
            </div>
            <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
                <div class="text-center py-5 bg-light rounded text-muted small">No chart data available.</div>
            <?php else: ?>
                <div id="clientChart" style="height: 240px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-modern">
        <div class="card-header-modern border-0 pb-0">
            <div class="d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 p-2 rounded me-3"><i class="bi bi-table text-warning"></i></div>
                <h6 class="m-0 fw-bold text-dark">PO Database</h6>
            </div>
        </div>
        
        <div class="filter-container">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Search Keyword</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="customSearch" class="form-control border-start-0" placeholder="PO, Client, Product...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Client Filter</label>
                    <select id="filterClient" class="form-select form-select-sm"><option value="">All Clients</option><?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase">Project Filter</label>
                    <select id="filterProject" class="form-select form-select-sm"><option value="">All Projects</option><?php $unique_projects = []; foreach($projects_raw as $p) $unique_projects[$p['project_name']] = $p['project_name']; foreach($unique_projects as $pname): ?><option value="<?= htmlspecialchars($pname) ?>"><?= htmlspecialchars($pname) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-md-3 text-end d-flex align-items-end justify-content-end">
                    <div class="d-flex align-items-center">
                        <span class="small text-muted me-2">Show:</span>
                        <select id="customLength" class="form-select form-select-sm w-auto"><option value="10">10</option><option value="50">50</option><option value="100">100</option></select>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-modern" id="table-client">
                <thead>
                    <tr>
                        <th class="col-date">Date & PO Info</th>
                        <th class="col-info">Client & Project</th>
                        <th class="col-prod">Product & Detail</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-file">File</th>
                        <th class="col-action">Action</th>
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
                        <td class="col-date">
                            <div class="d-flex flex-column">
                                <span class="small fw-bold text-secondary mb-1"><?= $p_date ?></span>
                                <span class="po-badge"><?= htmlspecialchars($row['po_number']) ?></span>
                                <small class="text-muted mt-1" style="font-size: 0.75rem;">Batch: <?= $batch ?></small>
                            </div>
                        </td>
                        <td class="col-info">
                            <div class="fw-bold text-dark text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($row['display_company']) ?>">
                                <?= htmlspecialchars($row['display_company'] ?? '-') ?>
                            </div>
                            <div class="small text-muted mt-1 d-flex align-items-center">
                                <i class="bi bi-folder2 me-1"></i> 
                                <span class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($row['display_project'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td class="col-prod">
                            <span class="product-badge"><?= $prod ?></span>
                            <span class="d-block small text-secondary text-truncate fst-italic" style="max-width: 200px;" title="<?= $detail ?>">
                                <?= $detail ?>
                            </span>
                        </td>
                        <td class="col-qty">
                            <span class="fw-bold text-dark fs-6"><?= $q_fmt ?></span>
                        </td>
                        <td class="col-file">
                            <?php if(!empty($row['po_file'])): ?>
                                <a href="uploads/po/<?= $row['po_file'] ?>" target="_blank" class="btn-icon-soft text-primary border-primary bg-primary bg-opacity-10" title="View Document">
                                    <i class="bi bi-file-earmark-text-fill fs-6"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted opacity-25"><i class="bi bi-dash-lg"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="col-action">
                            <div class="dropdown">
                                <button class="btn-icon-soft" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width: 160px;">
                                    <li><a class="dropdown-item py-2" href="#" onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-pencil-square me-2 text-warning"></i> Edit</a></li>
                                    <li><a class="dropdown-item py-2" href="#" onclick='openToProviderModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-truck me-2 text-info"></i> To Provider</a></li>
                                    <li><a class="dropdown-item py-2" href="#" onclick='printPO(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-printer me-2 text-secondary"></i> Print</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=client" onclick="return confirm('Permanently delete this PO?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<datalist id="product_list">
    <?php foreach($existing_products as $prod): ?>
        <option value="<?= htmlspecialchars($prod) ?>">
    <?php endforeach; ?>
</datalist>

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
                        <input type="radio" class="btn-check" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')">
                        <label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="add_mode_datapool">From Database</label>
                        
                        <input type="radio" class="btn-check" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')">
                        <label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="add_mode_manual">Manual Input</label>
                    </div>
                </div>

                <div class="row g-4 h-100">
                    <div class="col-md-6 d-flex">
                        <div class="modal-card w-100">
                            <h6 class="text-uppercase text-primary fw-bold small mb-3 border-bottom pb-2">Client Information</h6>
                            <div id="add_section_datapool">
                                <div class="mb-3"><label class="form-label small fw-bold">Client Name</label><select name="company_id" id="add_company_id" class="form-select" onchange="filterProjects('add')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select></div>
                                <div class="mb-3"><label class="form-label small fw-bold">Project</label><select name="project_id" id="add_project_id" class="form-select"><option value="">-- Select --</option></select></div>
                            </div>
                            <div id="add_section_manual" class="d-none">
                                <div class="mb-3"><label class="form-label small fw-bold">Client Name</label><input type="text" name="manual_company_name" id="add_manual_company" class="form-control"></div>
                                <div class="mb-3"><label class="form-label small fw-bold">Project Name</label><input type="text" name="manual_project_name" id="add_manual_project" class="form-control"></div>
                            </div>
                            
                            <h6 class="text-uppercase text-primary fw-bold small mt-4 mb-3 border-bottom pb-2">Product Info</h6>
                            <div class="mb-3"><label class="form-label small fw-bold">Product</label><input type="text" name="product_name" class="form-control" list="product_list" placeholder="Type or select..."></div>
                            <div><label class="form-label small fw-bold">Detail</label><textarea name="detail" class="form-control" rows="2" placeholder="Specs, Notes..."></textarea></div>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex">
                        <div class="modal-card w-100">
                            <h6 class="text-uppercase text-primary fw-bold small mb-3 border-bottom pb-2">Order Details</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-6"><label class="form-label small fw-bold">Date</label><input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                                <div class="col-6"><label class="form-label small fw-bold">Qty</label><input type="number" name="sim_qty" class="form-control fw-bold" placeholder="0"></div>
                            </div>
                            <div class="mb-3"><label class="form-label small fw-bold">PO Number</label><input type="text" name="po_number" class="form-control font-monospace" required></div>
                            <div class="mb-3"><label class="form-label small fw-bold">Batch Name</label><input type="text" name="batch_name" class="form-control" required></div>
                            <div><label class="form-label small fw-bold">Attachment</label><input type="file" name="po_file" class="form-control"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">Save Record</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" value="update"><input type="hidden" name="type" value="client"><input type="hidden" name="id" id="edit_id"><input type="hidden" name="existing_file" id="edit_existing_file">
            <div class="modal-header bg-warning text-dark py-3"><h6 class="modal-title m-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="d-flex justify-content-center mb-4">
                    <div class="btn-group bg-white shadow-sm p-1 rounded-pill border">
                        <input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_datapool" value="datapool" onchange="toggleInputMode('edit')"><label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="edit_mode_datapool">Database</label>
                        <input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_manual" value="manual" onchange="toggleInputMode('edit')"><label class="btn btn-sm btn-light rounded-pill fw-bold px-4" for="edit_mode_manual">Manual</label>
                    </div>
                </div>
                <div class="row g-4 h-100">
                    <div class="col-md-6 d-flex">
                        <div class="modal-card w-100">
                            <h6 class="text-uppercase text-warning fw-bold small mb-3 border-bottom pb-2">Client & Product</h6>
                            <div id="edit_section_datapool">
                                <div class="mb-3"><label class="form-label small fw-bold">Client</label><select name="company_id" id="edit_company_id" class="form-select" onchange="filterProjects('edit')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select></div>
                                <div class="mb-3"><label class="form-label small fw-bold">Project</label><select name="project_id" id="edit_project_id" class="form-select"><option value="">-- Select --</option></select></div>
                            </div>
                            <div id="edit_section_manual" class="d-none">
                                <div class="mb-3"><label class="form-label small fw-bold">Client Name</label><input type="text" name="manual_company_name" id="edit_manual_company" class="form-control"></div>
                                <div class="mb-3"><label class="form-label small fw-bold">Project Name</label><input type="text" name="manual_project_name" id="edit_manual_project" class="form-control"></div>
                            </div>
                            <div class="mb-3"><label class="form-label small fw-bold">Product</label><input type="text" name="product_name" id="edit_product_name" class="form-control" list="product_list"></div>
                            <div><label class="form-label small fw-bold">Detail</label><textarea name="detail" id="edit_detail" class="form-control" rows="2"></textarea></div>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex">
                        <div class="modal-card w-100">
                            <h6 class="text-uppercase text-warning fw-bold small mb-3 border-bottom pb-2">Order Details</h6>
                            <div class="row g-2 mb-3"><div class="col-6"><label class="form-label small fw-bold">Date</label><input type="date" name="po_date" id="edit_po_date" class="form-control"></div><div class="col-6"><label class="form-label small fw-bold">Qty</label><input type="number" name="sim_qty" id="edit_sim_qty" class="form-control"></div></div>
                            <div class="mb-3"><label class="form-label small fw-bold">PO No</label><input type="text" name="po_number" id="edit_po_number" class="form-control font-monospace"></div>
                            <div class="mb-3"><label class="form-label small fw-bold">Batch</label><input type="text" name="batch_name" id="edit_batch_name" class="form-control"></div>
                            <div><label class="form-label small fw-bold">File</label><input type="file" name="po_file" class="form-control"><small id="current_file_info" class="text-muted d-block mt-1 fst-italic"></small></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-warning px-4 fw-bold shadow-sm">Update Changes</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalToProvider" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="action" value="create_provider_from_client"><input type="hidden" name="link_client_po_id" id="tp_client_po_id">
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title m-0 fw-bold"><i class="bi bi-truck me-2"></i>To Provider</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4 bg-light">
                <div class="alert alert-white border shadow-sm d-flex align-items-center mb-4">
                    <i class="bi bi-link-45deg fs-3 text-info me-3"></i>
                    <div><small class="text-uppercase text-muted fw-bold">Source PO</small><div class="fw-bold text-dark"><span id="tp_display_client"></span> | <span id="tp_display_po" class="font-monospace"></span></div></div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Provider</label>
                                <select name="provider_company_id" class="form-select mb-2" required><option value="">-- Choose --</option><?php foreach($providers as $p): ?><option value="<?= $p['id'] ?>"><?= $p['company_name'] ?></option><?php endforeach; ?></select>
                                <input type="text" name="manual_provider_name" class="form-control form-control-sm" placeholder="Or Manual Provider Name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Provider PO No.</label><input type="text" name="provider_po_number" class="form-control mb-2" required>
                                <div class="row"><div class="col-6"><label class="form-label small fw-bold">Date</label><input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>"></div><div class="col-6"><label class="form-label small fw-bold">Qty</label><input type="number" name="sim_qty" id="tp_sim_qty" class="form-control" required></div></div>
                            </div>
                            <input type="hidden" name="batch_name" id="tp_batch_name"><div class="col-12"><label class="form-label small fw-bold">File</label><input type="file" name="po_file" class="form-control"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">Process</button></div>
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
            chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 } },
            dataLabels: { enabled: true, style: { colors: ['#435ebe'] } },
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
        $('#edit_sim_qty').val(String(data.sim_qty).replace(/[^0-9]/g, ''));
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