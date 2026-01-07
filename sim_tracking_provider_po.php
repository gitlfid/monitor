<?php
// =========================================================================
// 1. SETUP & LOGIC PHP
// =========================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
$current_page = 'sim_tracking_provider_po.php';

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
    // Query Utama: Provider PO + Detail Linked Client PO (Termasuk Project Name)
    $sql = "SELECT st.*, 
            COALESCE(c.company_name, st.manual_company_name) as display_provider,
            
            -- Detail Linked Client PO
            linked.po_number as linked_po_number,
            linked.po_date as linked_po_date,
            linked.sim_qty as linked_sim_qty,
            linked.batch_name as linked_batch_name,
            linked.po_file as linked_po_file,
            COALESCE(linked_comp.company_name, linked.manual_company_name) as linked_client_name,
            COALESCE(linked_proj.project_name, linked.manual_project_name) as linked_project_name

            FROM sim_tracking_po st
            LEFT JOIN companies c ON st.company_id = c.id
            -- Join ke diri sendiri untuk ambil data Client PO yang terhubung
            LEFT JOIN sim_tracking_po linked ON st.link_client_po_id = linked.id
            LEFT JOIN companies linked_comp ON linked.company_id = linked_comp.id
            LEFT JOIN projects linked_proj ON linked.project_id = linked_proj.id
            
            WHERE st.type = 'provider' 
            ORDER BY st.id DESC";

    if ($db_type === 'pdo') {
        $data = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = mysqli_query($db, $sql);
        if ($res) { while ($row = mysqli_fetch_assoc($res)) $data[] = $row; }
    }

    // --- LOGIKA GRAFIK (GROUP BY DATE) ---
    foreach ($data as $row) {
        $raw_date = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
        $date_label = date('d M Y', strtotime($raw_date));
        
        $qty = (int)preg_replace('/[^0-9]/', '', $row['sim_qty']);

        if (!isset($chart_data_grouped[$date_label])) $chart_data_grouped[$date_label] = 0;
        $chart_data_grouped[$date_label] += $qty;
    }
    // Ambil 7 data terakhir & balik urutan
    $chart_data_grouped = array_reverse(array_slice($chart_data_grouped, 0, 7, true));

} catch (Exception $e) {}

$js_chart_labels = array_keys($chart_data_grouped);
$js_chart_series = array_values($chart_data_grouped);

// FETCH DROPDOWN
$providers = []; $clients = []; $client_pos = [];
try {
    if ($db_type === 'pdo') {
        $providers = $db->query("SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($providers)) $providers = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $clients = $db->query("SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // List PO Client untuk Dropdown Link
        $client_pos = $db->query("SELECT st.id, st.po_number, st.company_id, st.batch_name, COALESCE(c.company_name, st.manual_company_name) as display_client_name FROM sim_tracking_po st LEFT JOIN companies c ON st.company_id = c.id WHERE st.type='client' ORDER BY st.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $r1 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC");
        while($x=mysqli_fetch_assoc($r1)) $providers[]=$x;
        if(empty($providers)) { $r1b=mysqli_query($db,"SELECT id, company_name FROM companies ORDER BY company_name ASC"); while($x=mysqli_fetch_assoc($r1b)) $providers[]=$x; }
        
        $r2 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC");
        while($x=mysqli_fetch_assoc($r2)) $clients[]=$x;
        
        $r3 = mysqli_query($db, "SELECT st.id, st.po_number, st.company_id, st.batch_name, COALESCE(c.company_name, st.manual_company_name) as display_client_name FROM sim_tracking_po st LEFT JOIN companies c ON st.company_id = c.id WHERE st.type='client' ORDER BY st.id DESC");
        while($x=mysqli_fetch_assoc($r3)) $client_pos[]=$x;
    }
} catch (Exception $e) {}
?>

<style>
    /* Base Font */
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    
    /* Card Styling */
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    /* Table Styling */
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom th {
        background-color: #f9fafb; color: #64748b; font-size: 0.75rem; font-weight: 700;
        text-transform: uppercase; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .table-custom td {
        padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #334155;
    }
    .table-custom tr:last-child td { border-bottom: none; }
    .table-custom tr:hover td { background-color: #f8fafc; }

    /* DataTables Customization */
    .dataTables_wrapper .row:first-child { padding: 20px 24px 10px 24px; margin: 0; }
    .dataTables_wrapper .row:last-child { padding: 15px 24px 20px 24px; border-top: 1px solid #f1f5f9; margin: 0; align-items: center; }
    .dataTables_info { font-size: 0.85rem; color: #64748b; font-weight: 500; padding-top: 0 !important; }
    .page-link { border-radius: 6px; margin: 0 3px; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; padding: 6px 12px; }
    .page-item.active .page-link { background-color: #198754; border-color: #198754; color: white; } /* Green Theme */
    .dataTables_length label { font-size: 0.85rem; color: #64748b; font-weight: 500; }
    
    /* Filter Box */
    .filter-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .form-control-sm, .form-select-sm { border-radius: 6px; border-color: #e2e8f0; }
    .search-wrapper { position: relative; }
    .search-icon { position: absolute; right: 10px; top: 9px; color: #94a3b8; }

    /* Column Widths */
    .col-date   { width: 140px; min-width: 140px; }
    .col-po     { width: 150px; font-family: 'Consolas', monospace; color: #475569; font-weight: 600; }
    .col-qty    { width: 100px; text-align: right; font-weight: bold; }
    .col-file   { width: 60px; text-align: center; }
    .col-action { width: 80px; text-align: center; }
    .col-link   { width: 220px; min-width: 200px; }

    /* Components */
    .badge-batch { background-color: #d1e7dd; color: #0f5132; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; display: inline-block; margin-top: 4px; }
    .btn-action-menu { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
    .btn-action-menu:hover { background-color: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
    .icon-file { color: #94a3b8; font-size: 1.2rem; transition: 0.2s; }
    .icon-file:hover { color: #198754; }
    
    /* Linked PO Button */
    .btn-link-po {
        background-color: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1;
        padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; cursor: pointer; text-decoration: none;
    }
    .btn-link-po:hover { background-color: #bae6fd; color: #075985; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Provider PO Tracking</h3>
            <p class="text-muted mb-0 small">Manage Inbound Stock from Providers.</p>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body pt-4">
            <h6 class="text-success fw-bold mb-3 ms-2"><i class="bi bi-graph-up me-2"></i>Daily Quantity Analysis</h6>
            <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
                <div class="text-center py-5 bg-light rounded text-muted"><p class="mb-0 small">No data to display.</p></div>
            <?php else: ?>
                <div id="providerChart" style="height: 280px;"></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="m-0 fw-bold text-dark"><i class="bi bi-list-check me-2"></i>Provider PO List</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success btn-sm fw-bold" onclick="openMasterProviderModal()">
                        <i class="bi bi-building-add me-1"></i> New Provider
                    </button>
                    <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm" onclick="openAddModal()">
                        <i class="bi bi-plus me-1"></i> Add PO
                    </button>
                </div>
            </div>

            <div class="filter-box">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Search</label>
                        <div class="search-wrapper">
                            <input type="text" id="customSearch" class="form-control form-control-sm ps-3" placeholder="Search Provider, PO...">
                            <i class="bi bi-search search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Provider</label>
                        <select id="filterProvider" class="form-select form-select-sm">
                            <option value="">All Providers</option>
                            <?php foreach($providers as $p): ?>
                                <option value="<?= htmlspecialchars($p['company_name']) ?>"><?= htmlspecialchars($p['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-1">Show</label>
                        <select id="customLength" class="form-select form-select-sm">
                            <option value="10">10 Rows</option>
                            <option value="50">50 Rows</option>
                            <option value="100">100 Rows</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0"> 
            <div class="table-responsive">
                <table class="table-custom" id="table-provider">
                    <thead>
                        <tr>
                            <th class="col-date ps-4">PO Date</th>
                            <th>Provider Name</th>
                            <th>Batch</th>
                            <th class="col-po">Provider PO</th>
                            <th class="col-link">Linked Client PO</th>
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
                                    <?= htmlspecialchars($row['display_provider'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-batch text-uppercase"><?= htmlspecialchars($row['batch_name'] ?? '-') ?></span>
                            </td>
                            <td class="col-po text-uppercase"><?= htmlspecialchars($row['po_number']) ?></td>
                            
                            <td class="col-link">
                                <?php if(!empty($row['linked_po_number'])): ?>
                                    <div class="btn-link-po text-uppercase" onclick='viewClientPO(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'>
                                        <i class="bi bi-link-45deg fs-6"></i>
                                        <?= htmlspecialchars($row['linked_po_number']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic ms-2">Unlinked</span>
                                <?php endif; ?>
                            </td>

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
                                    <button class="btn-action-menu" type="button" data-bs-toggle="dropdown">
                                        Action <i class="bi bi-chevron-down small ms-1"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-1">
                                        <li><a class="dropdown-item" href="#" onclick='printPO(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-printer me-2 text-dark"></i> Print PO</a></li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li><a class="dropdown-item" href="#" onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=provider" onclick="return confirm('Delete record?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
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

<div class="modal fade" id="modalViewClientPO" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-link-45deg me-2"></i>Linked Client PO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="card p-3 border-0 shadow-sm mb-0">
                    <div class="mb-3 pb-3 border-bottom">
                        <label class="small text-muted text-uppercase fw-bold mb-1">Client Name</label>
                        <div class="fw-bold text-dark fs-6 text-uppercase" id="v_client_name">-</div>
                    </div>
                    <div class="mb-3 pb-3 border-bottom">
                        <label class="small text-muted text-uppercase fw-bold mb-1">Project Name</label>
                        <div class="fw-bold text-dark fs-6 text-uppercase" id="v_project_name">-</div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom">
                        <div class="col-6">
                            <label class="small text-muted text-uppercase fw-bold mb-1">PO Number</label>
                            <div class="fw-bold text-primary font-monospace text-uppercase" id="v_po_number">-</div>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted text-uppercase fw-bold mb-1">Date</label>
                            <div class="fw-bold text-dark" id="v_po_date">-</div>
                        </div>
                    </div>
                    <div class="row mb-3 pb-3 border-bottom">
                        <div class="col-6">
                            <label class="small text-muted text-uppercase fw-bold mb-1">Quantity</label>
                            <div class="fw-bold text-success fs-5" id="v_qty">-</div>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted text-uppercase fw-bold mb-1">Batch</label>
                            <div class="fw-bold text-dark text-uppercase" id="v_batch">-</div>
                        </div>
                    </div>
                    <div>
                        <label class="small text-muted text-uppercase fw-bold mb-2">Attachment</label>
                        <div id="v_file_container"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-2">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMasterProvider" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="create_company">
            <input type="hidden" name="company_type" value="provider">
            <input type="hidden" name="redirect" value="provider">
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title m-0 fw-bold">New Provider</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <label class="form-label fw-bold">Provider Name</label>
                <input type="text" name="company_name" class="form-control" placeholder="e.g. TELKOMSEL" required oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success px-4 fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAdd" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="type" value="provider">
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title m-0 fw-bold">Add Provider PO</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12 mb-2"><div class="btn-group w-100"><input type="radio" class="btn-check" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')"><label class="btn btn-outline-success btn-sm" for="add_mode_datapool">Database</label><input type="radio" class="btn-check" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')"><label class="btn btn-outline-success btn-sm" for="add_mode_manual">Manual</label></div></div>
                    <div class="col-md-6">
                        <div id="add_section_datapool"><label class="form-label fw-bold">Provider</label><select name="company_id" id="add_company_id" class="form-select"><option value="">-- Choose --</option><?php foreach($providers as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select></div>
                        <div id="add_section_manual" class="d-none"><label class="form-label fw-bold">Provider Name</label><input type="text" name="manual_company_name" id="add_manual_company" class="form-control" oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mt-3 p-3 bg-light rounded border">
                            <label class="form-label small fw-bold text-success mb-2">Link to Client PO (Optional)</label>
                            <select id="add_filter_customer" class="form-select form-select-sm mb-2" onchange="filterClientPOs('add')"><option value="">All Customers...</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select>
                            <select name="link_client_po_id" id="add_link_client_po_id" class="form-select" onchange="autoFillBatch('add')"><option value="">-- No Link --</option></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row"><div class="col-6 mb-3"><label class="form-label fw-bold">Date</label><input type="date" name="po_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div><div class="col-6 mb-3"><label class="form-label fw-bold">Qty</label><input type="number" name="sim_qty" class="form-control" required placeholder="0"></div></div>
                        <div class="mb-3"><label class="form-label fw-bold">PO Number</label><input type="text" name="po_number" class="form-control" required oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mb-3"><label class="form-label fw-bold">Batch Name</label><input type="text" name="batch_name" id="add_batch_name" class="form-control" required oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mb-0"><label class="form-label fw-bold">Attachment</label><input type="file" name="po_file" class="form-control"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success px-4 fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="update"><input type="hidden" name="type" value="provider"><input type="hidden" name="id" id="edit_id"><input type="hidden" name="existing_file" id="edit_existing_file">
            <div class="modal-header bg-warning text-dark py-3"><h6 class="modal-title m-0 fw-bold">Edit Provider PO</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12 mb-2"><div class="btn-group w-100"><input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_datapool" value="datapool" onchange="toggleInputMode('edit')"><label class="btn btn-outline-warning btn-sm" for="edit_mode_datapool">Database</label><input type="radio" class="btn-check" name="edit_input_mode" id="edit_mode_manual" value="manual" onchange="toggleInputMode('edit')"><label class="btn btn-outline-warning btn-sm" for="edit_mode_manual">Manual</label></div></div>
                    <div class="col-md-6">
                        <div id="edit_section_datapool"><label class="form-label fw-bold">Provider</label><select name="company_id" id="edit_company_id" class="form-select"><option value="">-- Choose --</option><?php foreach($providers as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select></div>
                        <div id="edit_section_manual" class="d-none"><label class="form-label fw-bold">Provider Name</label><input type="text" name="manual_company_name" id="edit_manual_company" class="form-control" oninput="this.value = this.value.toUpperCase()"></div>
                        <div class="mt-3 p-3 bg-light rounded border">
                            <label class="form-label small fw-bold text-success mb-2">Link to Client PO</label>
                            <select id="edit_filter_customer" class="form-select form-select-sm mb-2" onchange="filterClientPOs('edit')"><option value="">All Customers...</option><?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?></select>
                            <select name="link_client_po_id" id="edit_link_client_po_id" class="form-select" onchange="autoFillBatch('edit')"><option value="">-- No Link --</option></select>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    const allClientPOs = <?php echo json_encode($client_pos); ?>;
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
                dataLabels: { enabled: true, style: { colors: ['#198754'] } },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px', fontWeight: 'bold' } } },
                colors: ['#198754'],
                grid: { borderColor: '#f1f5f9' },
                tooltip: { y: { formatter: function (val) { return new Intl.NumberFormat('id-ID').format(val) + ' Pcs' } } }
            };
            var chart = new ApexCharts(document.querySelector('#providerChart'), options);
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

    // 3. Filter PO & Set Data Attribute for Batch
    function filterClientPOs(mode, selectedPoId = null) {
        let filterCustomerId = $('#' + mode + '_filter_customer').val();
        let targetSelect = $('#' + mode + '_link_client_po_id');
        targetSelect.empty().append('<option value="">-- No Link --</option>');

        allClientPOs.forEach(function(po) {
            let show = false;
            if (filterCustomerId === '') { show = true; } 
            else { if (po.company_id == filterCustomerId) show = true; }

            if (show) {
                let isSelected = (selectedPoId == po.id) ? 'selected' : '';
                let clientName = po.display_client_name ? po.display_client_name : 'Manual';
                let batch = po.batch_name ? `(${po.batch_name})` : '';
                let batchValue = (po.batch_name || '').replace(/"/g, '&quot;'); // Escape quote for attr
                
                // Add data-batch to option
                targetSelect.append(`<option value="${po.id}" ${isSelected} data-batch="${batchValue}">${clientName} - ${po.po_number} ${batch}</option>`);
            }
        });
    }

    // 4. Auto Fill Batch Name (INCREMENT LOGIC)
    function autoFillBatch(mode) {
        let selectId = '#' + mode + '_link_client_po_id';
        let batchInputId = (mode === 'add') ? '#add_batch_name' : '#edit_batch_name';
        
        let selectedOption = $(selectId).find(':selected');
        let currentBatch = selectedOption.data('batch');
        
        if (currentBatch) {
            // Find number at the end of the string and increment it
            let newBatch = currentBatch.replace(/(\d+)(?!.*\d)/, function(match) {
                return parseInt(match) + 1;
            });

            // If no number found, append " 2"
            if (newBatch === currentBatch) {
                newBatch = currentBatch + " 2";
            }

            $(batchInputId).val(newBatch);
        }
    }

    // 5. Modals
    function openMasterProviderModal() { new bootstrap.Modal(document.getElementById('modalMasterProvider')).show(); }
    
    function openAddModal() {
        $('#add_mode_datapool').prop('checked', true); toggleInputMode('add'); 
        $('#add_company_id').val(''); $('#add_manual_company').val(''); $('#add_filter_customer').val(''); 
        filterClientPOs('add');
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
        } else {
            $('#edit_mode_manual').prop('checked', true); toggleInputMode('edit');
            $('#edit_manual_company').val(data.manual_company_name);
        }

        $('#edit_filter_customer').val(''); 
        filterClientPOs('edit', data.link_client_po_id);
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    }

    // 6. View Linked Client PO (POPUP)
    function viewClientPO(data) {
        $('#v_client_name').text(data.linked_client_name || '-');
        $('#v_project_name').text(data.linked_project_name || '-'); // ADDED Project Name
        $('#v_po_number').text(data.linked_po_number || '-');
        $('#v_batch').text(data.linked_batch_name || '-');
        
        let d = (data.linked_po_date && data.linked_po_date !== '0000-00-00') ? data.linked_po_date : '-';
        $('#v_po_date').text(d);
        
        let q = data.linked_sim_qty ? new Intl.NumberFormat('id-ID').format(String(data.linked_sim_qty).replace(/[^0-9]/g, '')) : '-';
        $('#v_qty').text(q);

        let fileContainer = $('#v_file_container');
        fileContainer.empty();
        if(data.linked_po_file) {
            fileContainer.html(`<a href="uploads/po/${data.linked_po_file}" target="_blank" class="btn btn-outline-success btn-sm fw-bold"><i class="bi bi-download me-2"></i>Download Client Document</a>`);
        } else {
            fileContainer.html('<span class="text-muted small fst-italic">No attachment available</span>');
        }

        new bootstrap.Modal(document.getElementById('modalViewClientPO')).show();
    }

    // 7. Print
    function printPO(data) {
        let poDate = data.po_date ? new Date(data.po_date).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'}) : '-';
        let qty = new Intl.NumberFormat('id-ID').format(String(data.sim_qty).replace(/[^0-9]/g, ''));
        let company = data.display_provider || '-';
        let batch = data.batch_name || '-';
        let poNum = data.po_number || '-';

        let win = window.open('', '', 'width=800,height=600');
        win.document.write(`<html><head><title>Print PO</title><style>body{font-family:Arial;padding:40px;color:#333}.header{text-align:center;border-bottom:2px solid #333;margin-bottom:30px}.meta td{padding:5px 15px 5px 0}.label{font-weight:bold}.content{width:100%;border-collapse:collapse;margin-top:20px}.content th,.content td{border:1px solid #ddd;padding:10px}.footer{margin-top:60px;display:flex;justify-content:space-between}.sig{text-align:center;border-top:1px solid #333;width:200px;margin-top:50px}</style></head><body><div class="header"><h1>Provider Purchase Order</h1><p>Ref: ${poNum}</p></div><table class="meta"><tr><td class="label">Provider:</td><td>${company}</td><td class="label">Date:</td><td>${poDate}</td></tr><tr><td class="label">Batch:</td><td>${batch}</td></tr></table><table class="content"><thead><tr><th>Item</th><th style="text-align:right">Qty</th></tr></thead><tbody><tr><td>Stock Inbound</td><td style="text-align:right"><strong>${qty} Pcs</strong></td></tr></tbody></table><div class="footer"><div class="sig">Prepared By</div><div class="sig">Received By</div></div></body></html>`);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); }, 500);
    }

    $(document).ready(function() {
        var table = $('#table-provider').DataTable({
            language: { search: '', searchPlaceholder: '' },
            searching: true,
            ordering: false,
            autoWidth: false,
            pageLength: 10,
            dom: 't<"row px-4 py-3 border-top align-items-center"<"col-md-6"i><"col-md-6 d-flex justify-content-end"p>>'
        });

        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
        $('#customLength').on('change', function() { table.page.len(this.value).draw(); });
        $('#filterProvider').on('change', function() { table.column(1).search(this.value).draw(); });
    });
</script>

<?php require_once 'includes/footer.php'; ?>