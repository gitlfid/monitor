<?php
// =========================================================================
// FILE: sim_tracking_client_po.php
// UPDATE: UI Modern + Product & Detail Columns
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
$existing_products = []; // Untuk dropdown otomatis

try {
    // 1. Fetch Main Data
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
        
        // 2. Fetch Unique Products for Dropdown
        $prodSql = "SELECT DISTINCT product_name FROM sim_tracking_po WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name ASC";
        $existing_products = $db->query($prodSql)->fetchAll(PDO::FETCH_COLUMN);
        
    } else {
        $res = mysqli_query($db, $sql);
        if ($res) { while ($row = mysqli_fetch_assoc($res)) $data[] = $row; }
        
        $prodRes = mysqli_query($db, "SELECT DISTINCT product_name FROM sim_tracking_po WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name ASC");
        while($r = mysqli_fetch_assoc($prodRes)) $existing_products[] = $r['product_name'];
    }

    // 3. Chart Data Processing
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
        // MySQLi Fallback...
        // (Simplified for brevity, assuming standard fetching logic similar to PDO)
    }
} catch (Exception $e) {}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; color: #1f2937; }

    /* CARD STYLING */
    .card-modern { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: #fff; margin-bottom: 24px; transition: transform 0.2s; }
    .card-modern:hover { box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
    .card-header-modern { background: #fff; padding: 20px 24px; border-bottom: 1px solid #f3f4f6; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; }
    
    /* TABLE STYLING */
    .table-responsive { border-radius: 0 0 12px 12px; }
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-modern th { background-color: #f9fafb; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 14px 20px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; letter-spacing: 0.03em; }
    .table-modern td { padding: 16px 20px; vertical-align: top; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; }
    .table-modern tr:hover td { background-color: #f8fafc; }
    
    /* BADGES & TEXT */
    .text-po { font-family: 'Consolas', monospace; font-weight: 600; color: #4f46e5; background: #eef2ff; padding: 2px 6px; border-radius: 4px; }
    .badge-product { background-color: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; margin-bottom: 4px; }
    .text-company { font-weight: 700; color: #111827; display: block; margin-bottom: 2px; }
    .text-project { color: #6b7280; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; }
    .detail-text { font-size: 0.8rem; color: #6b7280; font-style: italic; display: block; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    /* BUTTONS */
    .btn-new { background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: 0.2s; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2); }
    .btn-new:hover { background: #4338ca; transform: translateY(-1px); color: white; }
    .btn-action { background: white; border: 1px solid #e5e7eb; color: #374151; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; transition: 0.2s; }
    .btn-action:hover { background: #f9fafb; border-color: #d1d5db; }
    
    /* FILTERS */
    .filter-container { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Client PO Management</h3>
            <p class="text-muted mb-0 small">Track orders, manage products, and monitor details.</p>
        </div>
        <button class="btn-new" onclick="openAddModal()">
            <i class="bi bi-plus-lg me-2"></i>New Client PO
        </button>
    </div>
</div>

<section>
    <div class="card-modern">
        <div class="card-body pt-4">
            <h6 class="fw-bold text-dark mb-3 ms-2"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Order Quantity Trends</h6>
            <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
                <div class="text-center py-5 bg-light rounded text-muted"><p class="mb-0 small">No data to display.</p></div>
            <?php else: ?>
                <div id="clientChart" style="height: 250px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-modern">
        <div class="card-header-modern border-0 pb-0">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-table me-2"></i>PO Database</h6>
        </div>
        
        <div class="p-4">
            <div class="filter-container">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Search</label>
                        <input type="text" id="customSearch" class="form-control form-control-sm" placeholder="PO, Client, Product...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Client</label>
                        <select id="filterClient" class="form-select form-select-sm"><option value="">All Clients</option><?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Project</label>
                        <select id="filterProject" class="form-select form-select-sm"><option value="">All Projects</option><?php $unique_projects = []; foreach($projects_raw as $p) $unique_projects[$p['project_name']] = $p['project_name']; foreach($unique_projects as $pname): ?><option value="<?= htmlspecialchars($pname) ?>"><?= htmlspecialchars($pname) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-3 text-end">
                        <label class="form-label small fw-bold text-muted mb-1 d-block">&nbsp;</label>
                        <select id="customLength" class="form-select form-select-sm d-inline-block w-auto"><option value="10">10</option><option value="50">50</option><option value="100">100</option></select>
                        <span class="small text-muted ms-1">rows</span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-modern" id="table-client">
                    <thead>
                        <tr>
                            <th>Date & PO Info</th>
                            <th>Client & Project</th>
                            <th>Product & Details</th> <th class="text-end">Qty</th>
                            <th class="text-center">File</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): 
                            $q_fmt = number_format((int)preg_replace('/[^0-9]/', '', $row['sim_qty']));
                            $d_val = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
                            $p_date = date('d M Y', strtotime($d_val));
                            
                            // Handle New Fields
                            $prod = !empty($row['product_name']) ? htmlspecialchars($row['product_name']) : 'General';
                            $detail = !empty($row['detail']) ? htmlspecialchars($row['detail']) : '-';
                        ?>
                        <tr>
                            <td>
                                <div class="text-muted small fw-bold mb-1"><?= $p_date ?></div>
                                <span class="text-po"><?= htmlspecialchars($row['po_number']) ?></span>
                                <div class="small text-muted mt-1">Batch: <?= htmlspecialchars($row['batch_name']) ?></div>
                            </td>
                            <td>
                                <span class="text-company"><?= htmlspecialchars($row['display_company'] ?? '-') ?></span>
                                <div class="text-project"><i class="bi bi-folder2 text-secondary"></i> <?= htmlspecialchars($row['display_project'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="badge-product"><?= $prod ?></span>
                                <span class="detail-text" title="<?= $detail ?>"><?= $detail ?></span>
                            </td>
                            <td class="text-end fw-bold text-dark fs-6"><?= $q_fmt ?></td>
                            <td class="text-center">
                                <?php if(!empty($row['po_file'])): ?>
                                    <a href="uploads/po/<?= $row['po_file'] ?>" target="_blank" class="text-primary fs-5"><i class="bi bi-file-earmark-text-fill"></i></a>
                                <?php else: ?>
                                    <span class="text-muted opacity-25">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn-action" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li><a class="dropdown-item small" href="#" onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-pencil-square me-2 text-warning"></i> Edit</a></li>
                                        <li><a class="dropdown-item small" href="#" onclick='openToProviderModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-truck me-2 text-info"></i> To Provider</a></li>
                                        <li><a class="dropdown-item small" href="#" onclick='printPO(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-printer me-2 text-secondary"></i> Print</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item small text-danger" href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=client" onclick="return confirm('Delete this PO?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
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

<datalist id="product_list">
    <?php foreach($existing_products as $prod): ?>
        <option value="<?= htmlspecialchars($prod) ?>">
    <?php endforeach; ?>
</datalist>

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="create"><input type="hidden" name="type" value="client">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title fw-bold">Create New Client PO</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="mb-4 text-center">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')">
                        <label class="btn btn-outline-primary" for="add_mode_datapool">Existing Client</label>
                        <input type="radio" class="btn-check" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')">
                        <label class="btn btn-outline-primary" for="add_mode_manual">New Manual Client</label>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-primary mb-3 small text-uppercase">Client Information</h6>
                        <div id="add_section_datapool">
                            <label class="form-label small fw-bold">Client Name</label>
                            <select name="company_id" id="add_company_id" class="form-select mb-3" onchange="filterProjects('add')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select>
                            <label class="form-label small fw-bold">Project</label>
                            <select name="project_id" id="add_project_id" class="form-select mb-3"><option value="">-- Select --</option></select>
                        </div>
                        <div id="add_section_manual" class="d-none">
                            <label class="form-label small fw-bold">Client Name (Manual)</label>
                            <input type="text" name="manual_company_name" id="add_manual_company" class="form-control mb-3" oninput="this.value = this.value.toUpperCase()">
                            <label class="form-label small fw-bold">Project Name (Manual)</label>
                            <input type="text" name="manual_project_name" id="add_manual_project" class="form-control mb-3" oninput="this.value = this.value.toUpperCase()">
                        </div>

                        <h6 class="fw-bold text-primary mt-4 mb-3 small text-uppercase">Product Details (New)</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Product Name</label>
                            <input type="text" name="product_name" class="form-control" list="product_list" placeholder="Select or type new product...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Detail / Specs</label>
                            <textarea name="detail" class="form-control" rows="2" placeholder="e.g. 4G, 128K, Triple Cut"></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="fw-bold text-primary mb-3 small text-uppercase">Order Details</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label small fw-bold">PO Date</label><input type="date" name="po_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-6"><label class="form-label small fw-bold">Quantity</label><input type="number" name="sim_qty" class="form-control fw-bold" placeholder="0"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">PO Number</label>
                            <input type="text" name="po_number" class="form-control" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Batch Name</label>
                            <input type="text" name="batch_name" class="form-control" required oninput="this.value = this.value.toUpperCase()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Attachment</label>
                            <input type="file" name="po_file" class="form-control">
                        </div>
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
            <div class="modal-header bg-warning text-dark"><h6 class="modal-title fw-bold">Edit Client PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="btn-group">
                        <input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_datapool" value="datapool" onchange="toggleInputMode('edit')"><label class="btn btn-outline-warning text-dark" for="edit_mode_datapool">Database</label>
                        <input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_manual" value="manual" onchange="toggleInputMode('edit')"><label class="btn btn-outline-warning text-dark" for="edit_mode_manual">Manual</label>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <div id="edit_section_datapool">
                            <label class="form-label small fw-bold">Client</label><select name="company_id" id="edit_company_id" class="form-select mb-3" onchange="filterProjects('edit')"><option value="">-- Select --</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select>
                            <label class="form-label small fw-bold">Project</label><select name="project_id" id="edit_project_id" class="form-select mb-3"><option value="">-- Select --</option></select>
                        </div>
                        <div id="edit_section_manual" class="d-none">
                            <label class="form-label small fw-bold">Client Name</label><input type="text" name="manual_company_name" id="edit_manual_company" class="form-control mb-3">
                            <label class="form-label small fw-bold">Project Name</label><input type="text" name="manual_project_name" id="edit_manual_project" class="form-control mb-3">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Product Name</label>
                            <input type="text" name="product_name" id="edit_product_name" class="form-control" list="product_list">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Detail</label>
                            <textarea name="detail" id="edit_detail" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-2 mb-3"><div class="col-6"><label class="form-label small fw-bold">Date</label><input type="date" name="po_date" id="edit_po_date" class="form-control"></div><div class="col-6"><label class="form-label small fw-bold">Qty</label><input type="number" name="sim_qty" id="edit_sim_qty" class="form-control"></div></div>
                        <div class="mb-3"><label class="form-label small fw-bold">PO Number</label><input type="text" name="po_number" id="edit_po_number" class="form-control"></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Batch Name</label><input type="text" name="batch_name" id="edit_batch_name" class="form-control"></div>
                        <div><label class="form-label small fw-bold">File</label><input type="file" name="po_file" class="form-control"><small id="current_file_info" class="text-muted d-block mt-1 fst-italic"></small></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-warning px-4 fw-bold">Update Changes</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalToProvider" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="create_provider_from_client"><input type="hidden" name="link_client_po_id" id="tp_client_po_id">
            <div class="modal-header bg-info text-white"><h6 class="modal-title fw-bold">Create Provider PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                    <div>Source: <span id="tp_display_client" class="fw-bold"></span> | <span id="tp_display_po"></span></div>
                </div>
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
                    <input type="hidden" name="batch_name" id="tp_batch_name"><div class="col-12 mt-2"><label class="form-label small fw-bold">File</label><input type="file" name="po_file" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-info text-white px-4 fw-bold">Process</button></div>
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

    // 1. Chart
    if (chartSeries.length > 0) {
        new ApexCharts(document.querySelector('#clientChart'), {
            series: [{ name: 'Quantity', data: chartSeries }],
            chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'Inter' },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1 } },
            xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px' } } },
            colors: ['#4f46e5'],
            grid: { borderColor: '#f3f4f6' }
        }).render();
    }

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
            allProjects.filter(p => p.company_id == compId).forEach(p => {
                let s = (selId && p.id == selId) ? 'selected' : '';
                projSel.append(`<option value="${p.id}" ${s}>${p.project_name}</option>`);
            });
        }
    }

    // 3. Modals
    function openAddModal() {
        $('#add_mode_datapool').prop('checked', true); toggleInputMode('add'); 
        $('#add_company_id').val(''); $('#add_manual_company').val('');
        // Clear new fields
        $('input[name="product_name"]').val('');
        $('textarea[name="detail"]').val('');
        new bootstrap.Modal(document.getElementById('modalAdd')).show();
    }

    function openEditModal(data) {
        $('#edit_id').val(data.id);
        $('#edit_po_number').val(data.po_number);
        let pd = (data.po_date && data.po_date !== '0000-00-00') ? data.po_date : new Date().toISOString().split('T')[0];
        $('#edit_po_date').val(pd);
        $('#edit_sim_qty').val(String(data.sim_qty).replace(/[^0-9]/g, ''));
        $('#edit_batch_name').val(data.batch_name);
        
        // Populate New Fields
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
        $('#tp_display_po').text(data.po_number);
        $('#tp_sim_qty').val(String(data.sim_qty).replace(/[^0-9]/g, ''));
        $('#tp_batch_name').val(data.batch_name);
        new bootstrap.Modal(document.getElementById('modalToProvider')).show();
    }

    // 4. Print & DataTable
    function printPO(data) {
        let poDate = data.po_date ? new Date(data.po_date).toLocaleDateString('id-ID') : '-';
        let qty = new Intl.NumberFormat('id-ID').format(String(data.sim_qty).replace(/[^0-9]/g, ''));
        let win = window.open('', '', 'width=800,height=600');
        win.document.write(`<html><head><title>Print PO</title><style>body{font-family:sans-serif;padding:30px;}.header{border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:20px;display:flex;justify-content:space-between}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}</style></head><body><div class="header"><h2>Purchase Order</h2><h3>${data.po_number}</h3></div><p><strong>Client:</strong> ${data.display_company||data.manual_company_name}<br><strong>Date:</strong> ${poDate}<br><strong>Product:</strong> ${data.product_name||'-'}</p><table><thead><tr><th>Description</th><th>Qty</th></tr></thead><tbody><tr><td>${data.detail||'SIM Cards'}</td><td>${qty}</td></tr></tbody></table></body></html>`);
        win.document.close(); win.print();
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
        $('#filterProject').on('change', function() { table.column(1).search(this.value).draw(); });
    });
</script>

<?php require_once 'includes/footer.php'; ?>