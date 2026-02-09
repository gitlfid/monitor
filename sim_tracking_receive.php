<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// UPDATE: Fix Deprecated Null & Clear Origin/Destination Info
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
// 2. LOGIC DATA
// =========================================================================

// --- A. DATA RECEIVE (INBOUND: FROM PROVIDER TO LINKSFIELD) ---
$data_receive = [];
try {
    $sql_recv = "SELECT l.*, 
            po.po_number, po.batch_name,
            COALESCE(c.company_name, po.manual_company_name) as company_name
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

// --- B. DATA DELIVERY (OUTBOUND: FROM LINKSFIELD TO CLIENT) ---

// 1. Opsi Filter
$opt_projects = [];
$opt_couriers = [];
$opt_receivers = [];

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

// 2. Filter Logic
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';
$filter_receiver = $_GET['filter_receiver'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; 

if (!empty($search_track)) {
    $where_clause .= " AND (l.awb LIKE '%$search_track%' OR l.pic_name LIKE '%$search_track%')";
}
if (!empty($filter_project)) {
    $where_clause .= " AND c.company_name = '$filter_project'";
}
if (!empty($filter_courier)) {
    $where_clause .= " AND l.courier = '$filter_courier'";
}
if (!empty($filter_status)) {
    $where_clause .= " AND l.status = '$filter_status'";
}

// 3. Main Query Delivery
$data_delivery = [];
try {
    $sql_del = "SELECT l.*, 
                po.po_number, po.batch_name,
                COALESCE(c.company_name, po.manual_company_name) as company_name,
                l.logistic_date as delivery_date,
                l.awb as tracking_number,
                l.courier as courier_name,
                l.pic_name as receiver_name,
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

// --- C. FETCH DATA PO UNTUK MODAL ---
$provider_pos = [];
$client_pos = [];
try {
    $sql_po = "SELECT po.id, po.po_number, po.batch_name, po.type,
               COALESCE(c.company_name, po.manual_company_name) as company_name
               FROM sim_tracking_po po
               LEFT JOIN companies c ON po.company_id = c.id
               ORDER BY po.id DESC";
    if ($db) {
        $stmt = $db->query($sql_po);
        if($stmt) {
            $all_pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($all_pos as $po) {
                if($po['type'] === 'provider') $provider_pos[] = $po;
                else $client_pos[] = $po;
            }
        }
    }
} catch (Exception $e) {}
?>

<style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    .fw-bold { font-weight: 600 !important; }
    
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-modern thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background-color: #f8f9fa;
        color: #64748b;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
        padding: 16px 12px;
        white-space: nowrap;
    }
    .table-modern tbody td {
        font-size: 0.9rem;
        padding: 16px 12px;
        vertical-align: middle;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
    }
    .table-modern tr:hover td { background-color: #f8fafc; }
    
    .filter-card { border: none; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); margin-bottom: 20px; padding: 20px; }
    .text-label { font-size: 0.75rem; font-weight: 700; color: #adb5bd; margin-bottom: 4px; display: block; text-transform: uppercase; }
    
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
    
    .btn-action-menu { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; transition: 0.2s; }
    .btn-action-menu:hover { background-color: #f8fafc; color: #1e293b; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Logistics Tracking</h3>
            <p class="text-muted mb-0 small">Manage Inbound (Receive) and Outbound (Delivery).</p>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs" id="logisticsTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="receive-tab" data-bs-toggle="tab" data-bs-target="#receive" type="button">
                        <i class="bi bi-box-arrow-in-down me-2 text-success"></i> Receive (Inbound)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button">
                        <i class="bi bi-truck me-2 text-primary"></i> Delivery (Outbound)
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body p-4">
            <div class="tab-content">
                
                <div class="tab-pane fade show active" id="receive">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Inbound History</h6>
                        <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm" onclick="openReceiveModal()"><i class="bi bi-plus me-1"></i> Add Receive</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table-modern" style="width:100%" id="table-receive">
                            <thead>
                                <tr>
                                    <th class="ps-4">Date Received</th>
                                    <th>PIC (Receiver)</th>
                                    <th>Origin (Provider PT)</th> <th class="text-center">Qty</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_receive as $row): $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                <tr>
                                    <td class="ps-4"><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span></td>
                                    <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['pic_name'] ?? '-') ?></div></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div>
                                        <div class="small text-muted">PO: <?= htmlspecialchars($row['po_number'] ?? '-') ?></div>
                                    </td>
                                    <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-1 rounded-pill">+ <?= number_format($row['qty'] ?? 0) ?></span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-warning" onclick='editReceive(<?= $rowJson ?>)'><i class="bi bi-pencil"></i></button>
                                        <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="delivery">
                    
                    <div class="filter-card shadow-sm">
                        <form method="GET" action="sim_tracking_receive.php">
                            <input type="hidden" name="tab" value="delivery">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="text-label">Search</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" name="search_track" class="form-control border-start-0" placeholder="AWB / PIC Name..." value="<?= htmlspecialchars($search_track) ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <label class="text-label">Destination PT</label>
                                    <select name="filter_project" class="form-select form-select-sm">
                                        <option value="">- All -</option>
                                        <?php foreach ($opt_projects as $p) echo "<option value='$p' ".($filter_project==$p?'selected':'').">$p</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="text-label">Courier</label>
                                    <select name="filter_courier" class="form-select form-select-sm">
                                        <option value="">- All -</option>
                                        <?php foreach ($opt_couriers as $c) echo "<option value='$c' ".($filter_courier==$c?'selected':'').">".strtoupper($c)."</option>"; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="text-label">Status</label>
                                    <select name="filter_status" class="form-select form-select-sm">
                                        <option value="">- All -</option>
                                        <option value="Process" <?= ($filter_status=='Process'?'selected':'') ?>>Process</option>
                                        <option value="Shipped" <?= ($filter_status=='Shipped'?'selected':'') ?>>Shipped</option>
                                        <option value="Delivered" <?= ($filter_status=='Delivered'?'selected':'') ?>>Delivered</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Filter</button>
                                        <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_status)): ?>
                                            <a href="sim_tracking_receive.php?tab=delivery" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Outbound History</h6>
                        <button class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" onclick="openDeliveryModal()"><i class="bi bi-plus me-1"></i> Input Delivery</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="table-delivery">
                            <thead>
                                <tr>
                                    <th class="ps-4">Sent Date</th>
                                    <th>Status</th>
                                    <th>Destination (Client PT)</th> <th>Tracking Info</th>
                                    <th>PIC Recipient</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data_delivery)): ?>
                                    <tr><td colspan="7" class="text-center py-5 text-muted">No delivery data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($data_delivery as $row): 
                                        $st = strtolower($row['status'] ?? '');
                                        $badgeClass = 'bg-secondary';
                                        if(strpos($st, 'process')!==false) $badgeClass='bg-warning text-dark';
                                        if(strpos($st, 'shipped')!==false) $badgeClass='bg-info text-white';
                                        if(strpos($st, 'delivered')!==false) $badgeClass='bg-success text-white';
                                        
                                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="ps-4"><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['delivery_date'])) ?></span></td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?> border bg-opacity-25 px-2 py-1"><?= ucfirst($row['status'] ?? 'Process') ?></span>
                                            <?php if(!empty($row['delivered_date'])): ?>
                                                <div class="small text-success mt-1"><i class="bi bi-check-all"></i> <?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($row['company_name'])): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-xs bg-primary text-white rounded-circle me-2" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:10px;"><i class="bi bi-building"></i></span>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['company_name']) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <a href="#" onclick='trackResi("<?= $row['tracking_number'] ?? '' ?>", "<?= $row['courier_name'] ?? '' ?>")' class="text-decoration-none fw-bold font-monospace text-primary">
                                                    <?= htmlspecialchars($row['tracking_number'] ?? '-') ?>
                                                </a>
                                                <span class="badge bg-secondary text-uppercase mt-1" style="width:fit-content;font-size:0.65rem;">
                                                    <?= htmlspecialchars($row['courier_name'] ?? '-') ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold small"><?= htmlspecialchars($row['receiver_name'] ?? '-') ?></div>
                                            <div class="text-muted small text-truncate" style="max-width:120px;" title="<?= htmlspecialchars($row['receiver_address'] ?? '') ?>">
                                                <?= htmlspecialchars($row['receiver_address'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td class="text-center fw-bold"><?= number_format($row['qty'] ?? 0) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group shadow-sm">
                                                <button class="btn btn-sm btn-outline-primary" title="Lacak" onclick='trackResi("<?= $row['tracking_number'] ?? '' ?>", "<?= $row['courier_name'] ?? '' ?>")'>
                                                    <i class="bi bi-geo-alt-fill"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary" title="Edit" onclick='editDelivery(<?= $rowJson ?>)'>
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Detail" onclick='viewDetail(<?= $rowJson ?>)'>
                                                    <i class="bi bi-eye"></i>
                                                </button>
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

<div class="modal fade" id="modalReceive" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive">
            <input type="hidden" name="id" id="recv_id">
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title m-0 fw-bold">Inbound / Receive</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="mb-3"><label class="form-label small fw-bold">Date Receive</label><input type="date" name="logistic_date" id="recv_date" class="form-control" required></div>
                <div class="mb-3"><label class="form-label small fw-bold">From Provider (PO)</label><select name="po_id" id="recv_po_id" class="form-select" required><option value="">-- Select --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}'>{$po['po_number']}</option>"; ?></select></div>
                <div class="row"><div class="col-6 mb-3"><label class="form-label small fw-bold">PIC</label><input type="text" name="pic_name" id="recv_pic" class="form-control"></div><div class="col-6 mb-3"><label class="form-label small fw-bold">Qty</label><input type="number" name="qty" id="recv_qty" class="form-control" required></div></div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDelivery" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="del_action" value="create_logistic">
            <input type="hidden" name="type" value="delivery">
            <input type="hidden" name="id" id="del_id">
            <div class="modal-header bg-primary text-white py-3"><h6 class="modal-title m-0 fw-bold">Outbound / Delivery</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Destination Info</h6>
                        <div class="mb-3"><label class="form-label small fw-bold">Date Delivery</label><input type="date" name="logistic_date" id="del_date" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Link Client PO</label><select name="po_id" id="del_po_id" class="form-select" required><option value="">-- Select --</option><?php foreach($client_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                        <div class="row"><div class="col-6 mb-3"><label class="form-label small fw-bold">PIC Recipient</label><input type="text" name="pic_name" id="del_pic" class="form-control"></div><div class="col-6 mb-3"><label class="form-label small fw-bold">Phone</label><input type="text" name="pic_phone" id="del_phone" class="form-control"></div></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Address</label><textarea name="delivery_address" id="del_address" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Shipping Info</h6>
                        <div class="row"><div class="col-6 mb-3"><label class="form-label small fw-bold">Courier</label><input type="text" name="courier" id="del_courier" class="form-control" placeholder="JNE/J&T"></div><div class="col-6 mb-3"><label class="form-label small fw-bold">AWB / Resi</label><input type="text" name="awb" id="del_awb" class="form-control"></div></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Qty</label><input type="number" name="qty" id="del_qty" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Status</label><select name="status" id="del_status" class="form-select"><option value="Process">Process</option><option value="Shipped">Shipped</option><option value="Delivered">Delivered</option><option value="Returned">Returned</option></select></div>
                        <div class="p-3 bg-light rounded border"><label class="fw-bold small text-muted mb-2 d-block">PROOF OF DELIVERY</label><div class="row g-2"><div class="col-6"><input type="date" name="received_date" id="del_recv_date" class="form-control form-control-sm"></div><div class="col-6"><input type="text" name="receiver_name" id="del_recv_name" class="form-control form-control-sm" placeholder="Receiver Name"></div></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="trackingModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content border-0 shadow"><div class="modal-header border-bottom-0 pb-0"><h5 class="modal-title fw-bold text-primary"><i class="bi bi-truck me-2"></i> Shipment Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light" id="trackingResult"><div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Connecting to API...</p></div></div></div></div></div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-white border-bottom-0 pb-0"><h5 class="modal-title fw-bold text-dark">Detail Shipment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detailContent"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#table-receive').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
        $('#table-delivery').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10, searching: false });
        
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('tab') === 'delivery' || urlParams.get('search_track')) {
            var triggerEl = document.querySelector('#logisticsTab button[data-bs-target="#delivery"]');
            bootstrap.Tab.getInstance(triggerEl).show();
        }
    });

    let modalReceive, modalDelivery, modalTracking, modalDetail;
    document.addEventListener('DOMContentLoaded', function() {
        modalReceive = new bootstrap.Modal(document.getElementById('modalReceive'));
        modalDelivery = new bootstrap.Modal(document.getElementById('modalDelivery'));
        modalTracking = new bootstrap.Modal(document.getElementById('trackingModal'));
        modalDetail = new bootstrap.Modal(document.getElementById('detailModal'));
    });

    function trackResi(resi, kurir) {
        if(!resi || !kurir) { alert('No tracking data'); return; }
        modalTracking.show();
        document.getElementById('trackingResult').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Tracking...</p></div>';
        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`).then(r => r.text()).then(d => { document.getElementById('trackingResult').innerHTML = d; }).catch(e => { document.getElementById('trackingResult').innerHTML = '<div class="alert alert-danger">Error tracking.</div>'; });
    }

    // --- UPDATED DETAIL FUNCTION (WITH ORIGIN & DESTINATION PT) ---
    function viewDetail(data) {
        let html = `
            <div class="text-center mb-4 pt-3">
                <div class="avatar avatar-lg bg-light-primary text-primary mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center" style="width:60px;height:60px;font-size:1.5rem"><i class="bi bi-box-seam"></i></div>
                <h5 class="fw-bold mb-0">${data.tracking_number || '-'}</h5>
                <span class="badge bg-secondary text-uppercase">${data.courier_name || '-'}</span>
            </div>
            <ul class="list-group list-group-flush border-top">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Origin</span>
                    <span class="fw-bold text-end">PT LinksField</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Destination (Client)</span>
                    <span class="fw-bold text-end text-primary text-truncate" style="max-width:200px">${data.company_name || '-'}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Receiver Name</span>
                    <span class="fw-bold text-end">${data.receiver_name || '-'}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted small">Sent Date</span>
                    <span class="fw-bold text-end">${data.delivery_date}</span>
                </li>
                <li class="list-group-item">
                    <span class="text-muted small d-block mb-1">Address</span>
                    <span class="d-block small bg-light p-2 rounded">${data.receiver_address || '-'}</span>
                </li>
            </ul>
        `;
        document.getElementById('detailContent').innerHTML = html;
        modalDetail.show();
    }

    function openReceiveModal() {
        $('#recv_action').val('create_logistic'); $('#recv_id').val(''); $('#recv_date').val(new Date().toISOString().split('T')[0]); $('#recv_pic').val(''); $('#recv_po_id').val(''); $('#recv_qty').val(''); modalReceive.show();
    }
    function editReceive(data) {
        $('#recv_action').val('update_logistic'); $('#recv_id').val(data.id); $('#recv_date').val(data.logistic_date); $('#recv_pic').val(data.pic_name); $('#recv_po_id').val(data.po_id); $('#recv_qty').val(data.qty); modalReceive.show();
    }
    function openDeliveryModal() {
        $('#del_action').val('create_logistic'); $('#del_id').val(''); $('#del_date').val(new Date().toISOString().split('T')[0]); $('#del_po_id').val(''); $('#del_pic').val(''); $('#del_phone').val(''); $('#del_address').val(''); $('#del_courier').val(''); $('#del_awb').val(''); $('#del_qty').val(''); $('#del_status').val('Process'); $('#del_recv_date').val(''); $('#del_recv_name').val(''); modalDelivery.show();
    }
    function editDelivery(data) {
        $('#del_action').val('update_logistic'); $('#del_id').val(data.id); $('#del_date').val(data.delivery_date); $('#del_po_id').val(data.po_id); $('#del_pic').val(data.receiver_name); $('#del_phone').val(data.pic_phone); $('#del_address').val(data.receiver_address); $('#del_courier').val(data.courier_name); $('#del_awb').val(data.tracking_number); $('#del_qty').val(data.qty); $('#del_status').val(data.status); $('#del_recv_date').val(data.delivered_date); $('#del_recv_name').val(data.receiver_name); modalDelivery.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>