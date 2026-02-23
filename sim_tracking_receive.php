<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// UPDATE: Exact Replica of "Helpdesk Delivery" UI (Tables, Filters, Tabs)
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);
$current_page = 'sim_tracking_receive.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

// =========================================================================
// 1. DATA LOGIC (QUERY)
// =========================================================================

// --- A. DATA RECEIVE (INBOUND) ---
$data_receive = [];
try {
    $sql_recv = "SELECT l.*, 
            po.po_number as provider_po, 
            po.batch_name,
            COALESCE(c.company_name, po.manual_company_name) as provider_name
            FROM sim_tracking_logistics l
            LEFT JOIN sim_tracking_po po ON l.po_id = po.id
            LEFT JOIN companies c ON po.company_id = c.id
            WHERE l.type = 'receive'
            ORDER BY l.logistic_date DESC, l.id DESC";
    if ($db) {
        $stmt = $db->query($sql_recv);
        if($stmt) $data_receive = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// --- B. DATA DELIVERY (OUTBOUND) ---
$opt_projects = []; $opt_couriers = []; $opt_receivers = [];

try {
    if ($db) {
        $q_proj = "SELECT DISTINCT c.company_name as project_name 
                   FROM sim_tracking_logistics l 
                   JOIN sim_tracking_po po ON l.po_id = po.id
                   JOIN companies c ON po.company_id = c.id
                   WHERE l.type='delivery' ORDER BY c.company_name ASC";
        $stmt = $db->query($q_proj);
        if($stmt) $opt_projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $q_cour = "SELECT DISTINCT courier FROM sim_tracking_logistics WHERE type='delivery' AND courier != '' ORDER BY courier ASC";
        $stmt = $db->query($q_cour);
        if($stmt) $opt_couriers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $q_recv = "SELECT DISTINCT pic_name FROM sim_tracking_logistics WHERE type='delivery' AND pic_name != '' ORDER BY pic_name ASC";
        $stmt = $db->query($q_recv);
        if($stmt) $opt_receivers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}

// Filter Logic
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';
$filter_receiver = $_GET['filter_receiver'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; 
if (!empty($search_track)) $where_clause .= " AND (l.awb LIKE '%$search_track%' OR l.pic_name LIKE '%$search_track%')";
if (!empty($filter_project)) $where_clause .= " AND c.company_name = '$filter_project'";
if (!empty($filter_courier)) $where_clause .= " AND l.courier = '$filter_courier'";
if (!empty($filter_receiver)) $where_clause .= " AND l.pic_name = '$filter_receiver'";

// Main Query Delivery
$data_delivery = [];
try {
    $sql_del = "SELECT l.*, 
                po.po_number as client_po, 
                po.batch_name,
                COALESCE(c.company_name, po.manual_company_name) as client_name,
                l.logistic_date as delivery_date,
                l.awb as tracking_number,
                l.courier as courier_name,
                l.pic_name as receiver_name,
                l.pic_phone as receiver_phone,
                l.delivery_address as receiver_address,
                l.received_date as delivered_date
                FROM sim_tracking_logistics l
                LEFT JOIN sim_tracking_po po ON l.po_id = po.id
                LEFT JOIN companies c ON po.company_id = c.id
                $where_clause
                ORDER BY l.logistic_date DESC, l.id DESC";
    if ($db) {
        $stmt = $db->query($sql_del);
        if($stmt) $data_delivery = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// --- C. DATA PO OPTIONS ---
$provider_pos = []; $client_pos = [];
try {
    $stmt = $db->query("SELECT po.id, po.po_number, po.type, COALESCE(c.company_name, po.manual_company_name) as company_name FROM sim_tracking_po po LEFT JOIN companies c ON po.company_id = c.id ORDER BY po.id DESC");
    if($stmt) {
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($all as $p) {
            if($p['type']=='provider') $provider_pos[]=$p; else $client_pos[]=$p;
        }
    }
} catch (Exception $e) {}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; color: #1f2937; }
    
    .page-title { font-size: 1.25rem; font-weight: 700; color: #1e3a8a; margin-bottom: 4px; }
    .page-desc { color: #6b7280; font-size: 0.85rem; margin-bottom: 0; }
    
    /* BUTTONS */
    .btn-primary-custom { background-color: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: 0.2s; }
    .btn-primary-custom:hover { background-color: #2563eb; color: white; box-shadow: 0 4px 6px -1px rgba(37,99,235,0.2); }
    .btn-outline-custom { background-color: #fff; color: #3b82f6; border: 1px solid #3b82f6; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; transition: 0.2s; }
    .btn-outline-custom:hover { background-color: #eff6ff; color: #1d4ed8; }

    /* CARD & TABS */
    .main-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: none; overflow: hidden; margin-bottom: 2rem; }
    .nav-tabs { border-bottom: 1px solid #e5e7eb; padding: 0 1rem; background: #fff; }
    .nav-link { border: none; color: #6b7280; font-weight: 600; padding: 16px 20px; font-size: 0.9rem; background: transparent; position: relative; }
    .nav-link:hover { color: #3b82f6; border: none; background: transparent; }
    .nav-link.active { color: #3b82f6; border: none; background: transparent; }
    .nav-link.active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background-color: #3b82f6; border-radius: 3px 3px 0 0; }

    /* FILTERS */
    .filter-section { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid #f3f4f6; }
    .filter-label { font-size: 0.65rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; display: block; }
    .form-control-sm, .form-select-sm { border-color: #d1d5db; border-radius: 6px; font-size: 0.85rem; padding: 0.4rem 0.75rem; color: #374151; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); }

    /* TABLE MODERN */
    .table-modern { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f9fafb; color: #6b7280; font-weight: 700; text-transform: uppercase;
        font-size: 0.7rem; padding: 14px 16px; border-bottom: 1px solid #e5e7eb; letter-spacing: 0.05em; white-space: nowrap;
    }
    .table-modern tbody td { padding: 16px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
    .table-modern tr:hover td { background-color: #f9fafb; }
    .table-footer { padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; background: #fff; font-size: 0.85rem; color: #6b7280; display: flex; justify-content: space-between; align-items: center; }

    /* TABLE ELEMENTS */
    .badge-status { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
    .badge-success-soft { background-color: #d1fae5; color: #065f46; }
    .badge-warning-soft { background-color: #fef3c7; color: #92400e; }
    .badge-info-soft { background-color: #dbeafe; color: #1e40af; }
    
    .proj-badge { display: inline-flex; align-items: center; background-color: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; border: 1px solid #bae6fd; }
    .proj-icon { background-color: #bae6fd; color: #0284c7; width: 20px; height: 20px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; margin-right: 6px; font-size: 0.65rem; }
    
    .track-link { font-family: 'SFMono-Regular', Consolas, monospace; font-weight: 700; color: #2563eb; font-size: 0.9rem; text-decoration: none; transition: 0.2s; }
    .track-link:hover { color: #1d4ed8; text-decoration: underline; }
    .courier-pill { background: #374151; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; margin-top: 4px; display: inline-block; }

    .action-btn { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #d1d5db; background: white; color: #4b5563; margin-right: 4px; transition: 0.2s; cursor: pointer; text-decoration: none; }
    .action-btn:hover { background: #f3f4f6; color: #111827; border-color: #9ca3af; }

    /* MODAL SHELL */
    .modal-content-clean { border: none; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow: hidden; }
    .modal-header-clean { background-color: #fff; padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
    .modal-title-clean { font-weight: 700; font-size: 1rem; color: #111827; display: flex; align-items: center; gap: 8px; }
</style>

<div class="px-4 py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Delivery Management</h1>
            <p class="page-desc">Monitor status pengiriman dan riwayat logistik.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn-outline-custom" onclick="openReceiveModal()"><i class="bi bi-box-arrow-in-down me-1"></i> Receive</button>
            <button class="btn-primary-custom" onclick="openDeliveryModal()"><i class="bi bi-plus me-1"></i> Input Delivery</button>
        </div>
    </div>

    <div class="main-card">
        <ul class="nav nav-tabs" id="logisticsTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button">Outbound (Delivery)</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="receive-tab" data-bs-toggle="tab" data-bs-target="#receive" type="button">Inbound (Receive)</button>
            </li>
        </ul>

        <div class="tab-content p-0">
            <div class="tab-pane fade show active" id="delivery">
                
                <div class="filter-section">
                    <form method="GET" action="sim_tracking_receive.php">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="filter-label">Search Tracking</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search_track" class="form-control form-control-sm border-start-0 ps-0" placeholder="Nomor Resi..." value="<?= htmlspecialchars($search_track ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="filter-label">Project</label>
                                <select name="filter_project" class="form-select form-select-sm">
                                    <option value="">- All Projects -</option>
                                    <?php foreach ($opt_projects as $p) echo "<option value='$p' ".($filter_project==$p?'selected':'').">$p</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="filter-label">Courier</label>
                                <select name="filter_courier" class="form-select form-select-sm">
                                    <option value="">- All Couriers -</option>
                                    <?php foreach ($opt_couriers as $c) echo "<option value='$c' ".($filter_courier==$c?'selected':'').">$c</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="filter-label">Receiver</label>
                                <select name="filter_receiver" class="form-select form-select-sm">
                                    <option value="">- All Receivers -</option>
                                    <?php foreach ($opt_receivers as $r) echo "<option value='$r' ".($filter_receiver==$r?'selected':'').">$r</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary-custom w-100 py-1">Filter</button>
                                <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier) || !empty($filter_receiver)): ?>
                                    <a href="sim_tracking_receive.php" class="btn btn-outline-secondary w-100 py-1" style="font-size:0.85rem; font-weight:600;">Reset</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table-modern" id="table-delivery">
                        <thead>
                            <tr>
                                <th class="ps-4">Sent Date</th>
                                <th>Delivered</th>
                                <th>Project / Client</th>
                                <th>Tracking Info</th>
                                <th>Sender</th>
                                <th>Receiver</th>
                                <th class="text-center">Qty</th>
                                <th class="text-center pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($data_delivery)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 text-gray-300"></i>Data tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach($data_delivery as $row): 
                                    $st = strtolower($row['status'] ?? '');
                                    $statusClass = 'badge-warning-soft'; $icon = 'bi-clock';
                                    if(strpos($st, 'shipped')!==false) { $statusClass = 'badge-info-soft'; $icon = 'bi-truck'; }
                                    if(strpos($st, 'delivered')!==false) { $statusClass = 'badge-success-soft'; $icon = 'bi-check-circle-fill'; }
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= date('d M Y', strtotime($row['delivery_date'])) ?></div>
                                        <div class="small text-muted mt-1" style="font-size:0.75rem;"><?= date('H:i', strtotime($row['delivery_date'])) ?> WIB</div>
                                    </td>
                                    <td>
                                        <div class="badge-status <?= $statusClass ?>"><i class="bi <?= $icon ?> me-1"></i> <?= ucfirst($row['status']) ?></div>
                                        <?php if(!empty($row['delivered_date'])): ?>
                                            <div class="small text-success fw-bold mt-1" style="font-size:0.7rem;"><i class="bi bi-check-all"></i> <?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="proj-badge">
                                            <div class="proj-icon"><i class="bi bi-hdd-network-fill"></i></div>
                                            <?= htmlspecialchars($row['client_name']) ?>
                                        </div>
                                        <div class="small text-muted mt-1 font-monospace" style="font-size:0.7rem;">PO: <?= htmlspecialchars($row['client_po']) ?></div>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" class="track-link" onclick='trackResi("<?= htmlspecialchars($row['tracking_number']) ?>", "<?= htmlspecialchars($row['courier_name']) ?>")'>
                                            <?= htmlspecialchars($row['tracking_number'] ?: 'NO-RESI') ?>
                                        </a><br>
                                        <div class="courier-pill"><?= htmlspecialchars($row['courier_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark small mb-1">PT LinksField</div>
                                        <div class="small text-muted" style="font-size:0.75rem;">Warehouse Jakarta</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark small mb-1"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                        <div class="small text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($row['receiver_phone']) ?></div>
                                    </td>
                                    <td class="text-center fw-bold text-dark"><?= number_format($row['qty']) ?></td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center">
                                            <button class="action-btn" title="Track" onclick='trackResi("<?= htmlspecialchars($row['tracking_number']) ?>", "<?= htmlspecialchars($row['courier_name']) ?>")'><i class="bi bi-geo-alt"></i></button>
                                            <button class="action-btn" title="Detail" onclick='viewDetail(<?= $rowJson ?>)'><i class="bi bi-eye"></i></button>
                                            <button class="action-btn" title="Edit" onclick='editDelivery(<?= $rowJson ?>)'><i class="bi bi-pencil"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <div>Menampilkan hasil data pengiriman terbaru.</div>
                    <div>2026 &copy; LinksField Helpdesk</div>
                </div>
            </div>

            <div class="tab-pane fade" id="receive">
                <div class="table-responsive">
                    <table class="table-modern" id="table-receive">
                        <thead>
                            <tr>
                                <th class="ps-4">Received Date</th>
                                <th>Status</th>
                                <th>Origin / Provider</th>
                                <th>Internal PO</th>
                                <th>Receiver (WH)</th>
                                <th class="text-center">Qty</th>
                                <th class="text-center pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data_receive as $row): $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                            <tr>
                                <td class="ps-4"><div class="fw-bold text-dark"><?= date('d M Y', strtotime($row['logistic_date'])) ?></div></td>
                                <td><div class="badge-status badge-success-soft"><i class="bi bi-check-circle-fill me-1"></i> Received</div></td>
                                <td>
                                    <div class="fw-bold text-dark small mb-1"><?= htmlspecialchars($row['provider_name']) ?></div>
                                    <div class="small text-muted" style="font-size:0.75rem;">Batch: <?= htmlspecialchars($row['batch_name']) ?></div>
                                </td>
                                <td><div class="font-monospace text-primary fw-bold" style="font-size:0.85rem;"><?= htmlspecialchars($row['provider_po']) ?></div></td>
                                <td>
                                    <div class="fw-bold text-dark small mb-1">Internal Warehouse</div>
                                    <div class="small text-muted" style="font-size:0.75rem;">PIC: <?= htmlspecialchars($row['pic_name']) ?></div>
                                </td>
                                <td class="text-center fw-bold text-success fs-6">+<?= number_format($row['qty']) ?></td>
                                <td class="text-center pe-4">
                                    <button class="action-btn" onclick='editReceive(<?= $rowJson ?>)'><i class="bi bi-pencil"></i></button>
                                    <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" class="action-btn text-danger border-danger-subtle" onclick="return confirm('Hapus data ini?')"><i class="bi bi-trash"></i></a>
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

<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-clean">
            <div class="modal-header-clean">
                <div class="modal-title-clean">
                    <i class="bi bi-box-seam text-primary fs-4"></i> Shipment Status
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light" id="trackingResult">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-3 text-muted small fw-bold">Memuat data dari kurir...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content modal-content-clean">
            <div class="modal-header-clean pb-3">
                <h5 class="modal-title-clean mb-0"><i class="bi bi-card-text text-primary me-2 fs-5"></i> Delivery Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" id="detailContent"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDelivery" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="del_action" value="create_logistic">
            <input type="hidden" name="type" value="delivery">
            <input type="hidden" name="id" id="del_id">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title fw-bold">Outbound Delivery Form</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 border-end pe-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3 small fw-bold text-uppercase">Destination (Client)</h6>
                        <div class="mb-3"><label class="small fw-bold text-muted">Date</label><input type="date" name="logistic_date" id="del_date" class="form-control form-control-sm" required></div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Client PO</label><select name="po_id" id="del_po_id" class="form-select form-select-sm" required><option value="">-- Select --</option><?php foreach($client_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                        <div class="row"><div class="col-6 mb-3"><label class="small fw-bold text-muted">Recipient</label><input type="text" name="pic_name" id="del_pic" class="form-control form-control-sm"></div><div class="col-6 mb-3"><label class="small fw-bold text-muted">Phone</label><input type="text" name="pic_phone" id="del_phone" class="form-control form-control-sm"></div></div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Address</label><textarea name="delivery_address" id="del_address" class="form-control form-control-sm" rows="2"></textarea></div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3 small fw-bold text-uppercase">Shipping Info</h6>
                        <div class="row"><div class="col-6 mb-3"><label class="small fw-bold text-muted">Courier</label><input type="text" name="courier" id="del_courier" class="form-control form-control-sm"></div><div class="col-6 mb-3"><label class="small fw-bold text-muted">AWB / Resi</label><input type="text" name="awb" id="del_awb" class="form-control form-control-sm"></div></div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Qty</label><input type="number" name="qty" id="del_qty" class="form-control form-control-sm" required></div>
                        <div class="mb-3"><label class="small fw-bold text-muted">Status</label><select name="status" id="del_status" class="form-select form-select-sm"><option value="Process">Process</option><option value="Shipped">Shipped</option><option value="Delivered">Delivered</option></select></div>
                        <div class="p-3 bg-light rounded border"><label class="small fw-bold text-muted d-block mb-1 text-uppercase" style="font-size:0.65rem;">Proof of Delivery</label><div class="row g-2"><div class="col-6"><input type="date" name="received_date" id="del_recv_date" class="form-control form-control-sm"></div><div class="col-6"><input type="text" name="receiver_name" id="del_recv_name" class="form-control form-control-sm" placeholder="Accepted By"></div></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary fw-bold px-4">Save Data</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalReceive" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive">
            <input type="hidden" name="id" id="recv_id">
            <div class="modal-header bg-success text-white"><h6 class="modal-title fw-bold">Inbound Receive Form</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="mb-3"><label class="small fw-bold text-muted">Date</label><input type="date" name="logistic_date" id="recv_date" class="form-control form-control-sm" required></div>
                <div class="mb-3"><label class="small fw-bold text-muted">Provider PO</label><select name="po_id" id="recv_po_id" class="form-select form-select-sm" required><option value="">-- Select --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                <div class="row"><div class="col-6 mb-3"><label class="small fw-bold text-muted">Receiver PIC</label><input type="text" name="pic_name" id="recv_pic" class="form-control form-control-sm"></div><div class="col-6 mb-3"><label class="small fw-bold text-muted">Qty</label><input type="number" name="qty" id="recv_qty" class="form-control form-control-sm" required></div></div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success fw-bold px-4">Save Data</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#table-delivery').DataTable({ dom: 't<"d-none"ip>', pageLength: 50, searching: false, ordering: false });
        $('#table-receive').DataTable({ dom: 't<"d-none"ip>', pageLength: 50, searching: false, ordering: false });
    });

    let modalReceive, modalDelivery, modalTracking, modalDetail;
    document.addEventListener('DOMContentLoaded', function() {
        modalReceive = new bootstrap.Modal(document.getElementById('modalReceive'));
        modalDelivery = new bootstrap.Modal(document.getElementById('modalDelivery'));
        modalTracking = new bootstrap.Modal(document.getElementById('trackingModal'));
        modalDetail = new bootstrap.Modal(document.getElementById('detailModal'));
    });

    // --- TRACKING FUNCTION (FETCHES PART 1 API HTML) ---
    function trackResi(resi, kurir) {
        if(!resi || !kurir) { alert('No tracking data available.'); return; }
        
        modalTracking.show();
        $('#trackingResult').html('<div class="text-center py-5 my-5"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div><div class="mt-3 text-muted fw-bold">Menghubungkan ke API Kurir...</div></div>');
        
        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(r => r.text())
            .then(html => { 
                $('#trackingResult').html(html);
            })
            .catch(e => { 
                $('#trackingResult').html('<div class="alert alert-danger text-center m-4 shadow-sm"><i class="bi bi-wifi-off fs-3 d-block mb-2"></i>Koneksi ke server kurir terputus.</div>'); 
            });
    }

    // --- DETAIL MODAL FUNCTION ---
    function viewDetail(data) {
        let html = `
            <div class="text-center mb-4 mt-2">
                <div class="d-inline-flex align-items-center justify-content-center bg-white shadow-sm rounded-circle mb-3" style="width:64px; height:64px; border: 1px solid #e5e7eb;">
                    <i class="bi bi-box-seam text-primary fs-3"></i>
                </div>
                <h4 class="fw-bold mb-1 text-dark">${data.tracking_number || '-'}</h4>
                <span class="badge bg-secondary text-uppercase px-3 py-1">${data.courier_name || '-'}</span>
            </div>
            <div class="bg-white p-3 rounded-3 mb-3 shadow-sm border border-light">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size:0.65rem; letter-spacing:0.5px;">SENDER</small>
                        <span class="fw-bold text-dark" style="font-size:0.9rem;">PT LinksField</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size:0.65rem; letter-spacing:0.5px;">RECEIVER</small>
                        <span class="fw-bold text-primary" style="font-size:0.9rem;">${data.client_name || '-'}</span>
                    </div>
                </div>
            </div>
            <ul class="list-group list-group-flush border-top border-light shadow-sm rounded-3 overflow-hidden bg-white">
                <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3"><span class="text-muted small fw-bold">Client PO</span><span class="fw-bold font-monospace bg-light px-2 py-1 rounded border">${data.client_po || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3"><span class="text-muted small fw-bold">Recipient Name</span><span class="fw-bold text-dark">${data.receiver_name || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3"><span class="text-muted small fw-bold">Contact Phone</span><span class="fw-bold text-dark">${data.receiver_phone || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3"><span class="text-muted small fw-bold">Sent Date</span><span class="fw-bold text-dark">${data.delivery_date}</span></li>
                <li class="list-group-item px-4 py-3"><span class="text-muted small fw-bold d-block mb-2">Delivery Address</span><div class="small bg-light border p-3 rounded-3 text-dark lh-base">${data.receiver_address || 'No address provided.'}</div></li>
            </ul>`;
        document.getElementById('detailContent').innerHTML = html;
        modalDetail.show();
    }

    function openReceiveModal() { $('#recv_action').val('create_logistic'); $('#recv_id').val(''); $('#recv_date').val(new Date().toISOString().split('T')[0]); $('#recv_pic').val(''); $('#recv_po_id').val(''); $('#recv_qty').val(''); modalReceive.show(); }
    function editReceive(d) { $('#recv_action').val('update_logistic'); $('#recv_id').val(d.id); $('#recv_date').val(d.logistic_date); $('#recv_pic').val(d.pic_name); $('#recv_po_id').val(d.po_id); $('#recv_qty').val(d.qty); modalReceive.show(); }
    function openDeliveryModal() { $('#del_action').val('create_logistic'); $('#del_id').val(''); $('#del_date').val(new Date().toISOString().split('T')[0]); $('#del_po_id').val(''); $('#del_pic').val(''); $('#del_phone').val(''); $('#del_address').val(''); $('#del_courier').val(''); $('#del_awb').val(''); $('#del_qty').val(''); $('#del_status').val('Process'); $('#del_recv_date').val(''); $('#del_recv_name').val(''); modalDelivery.show(); }
    function editDelivery(d) { $('#del_action').val('update_logistic'); $('#del_id').val(d.id); $('#del_date').val(d.delivery_date); $('#del_po_id').val(d.po_id); $('#del_pic').val(d.receiver_name); $('#del_phone').val(d.receiver_phone); $('#del_address').val(d.receiver_address); $('#del_courier').val(d.courier_name); $('#del_awb').val(d.tracking_number); $('#del_qty').val(d.qty); $('#del_status').val(d.status); $('#del_recv_date').val(d.delivered_date); $('#del_recv_name').val(d.receiver_name); modalDelivery.show(); }
</script>

<?php require_once 'includes/footer.php'; ?>