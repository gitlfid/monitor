<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// UPDATE: Live Tracking Fetch + Smart Origin/Destination Replacer
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
$opt_projects = []; $opt_couriers = []; 

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
    }
} catch (Exception $e) {}

// Filter Logic
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; 
if (!empty($search_track)) $where_clause .= " AND (l.awb LIKE '%$search_track%' OR l.pic_name LIKE '%$search_track%')";
if (!empty($filter_project)) $where_clause .= " AND c.company_name = '$filter_project'";
if (!empty($filter_courier)) $where_clause .= " AND l.courier = '$filter_courier'";
if (!empty($filter_status)) $where_clause .= " AND l.status = '$filter_status'";

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
    body { background-color: #f3f4f6; font-family: 'Inter', sans-serif; color: #1f2937; }
    
    .card { border: none; border-radius: 8px; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); background: white; margin-bottom: 20px; }
    .page-title { font-size: 1.25rem; font-weight: 600; color: #111827; }
    
    .table-custom { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .table-custom thead th {
        background-color: #f9fafb; color: #6b7280; font-weight: 600; text-transform: uppercase;
        font-size: 0.7rem; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; letter-spacing: 0.05em;
    }
    .table-custom tbody td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
    .table-custom tr:hover td { background-color: #f9fafb; }

    .status-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
    .bg-green { background-color: #d1fae5; color: #065f46; }
    .bg-blue { background-color: #dbeafe; color: #1e40af; }
    .bg-yellow { background-color: #fef3c7; color: #92400e; }
    
    .tracking-code { 
        font-family: 'Roboto Mono', monospace; 
        font-weight: 700; 
        color: #2563eb; 
        font-size: 0.85rem; 
        cursor: pointer; 
        text-decoration: none; 
        transition: 0.2s;
        display: inline-block;
    }
    .tracking-code:hover { color: #1d4ed8; text-decoration: underline !important; }
    .courier-tag { background: #1f2937; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; text-transform: uppercase; font-weight: bold; margin-top: 4px; display: inline-block; }

    .btn-action { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid #d1d5db; background: white; color: #4b5563; margin-right: 4px; transition: 0.2s; cursor: pointer; }
    .btn-action:hover { background: #f3f4f6; color: #111827; border-color: #9ca3af; }
    .btn-primary-custom { background-color: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 0.875rem; transition: 0.2s; }
    .btn-primary-custom:hover { background-color: #1d4ed8; color: white; }
    
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
</style>

<div class="px-4 py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Delivery Management</h1>
            <p class="text-muted small m-0">Monitor status pengiriman dan riwayat logistik.</p>
        </div>
        <div>
            <button class="btn btn-primary-custom me-2" onclick="openReceiveModal()"><i class="bi bi-box-arrow-in-down me-2"></i> Receive</button>
            <button class="btn btn-primary-custom" onclick="openDeliveryModal()"><i class="bi bi-plus me-2"></i> Input Delivery</button>
        </div>
    </div>

    <div class="card p-3 mb-4 bg-white border-0 shadow-sm">
        <form method="GET" action="sim_tracking_receive.php">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1 text-uppercase" style="font-size:0.7rem">Search Tracking</label>
                    <input type="text" name="search_track" class="form-control form-control-sm" placeholder="Resi / Name..." value="<?= htmlspecialchars($search_track ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-1 text-uppercase" style="font-size:0.7rem">Project / Client</label>
                    <select name="filter_project" class="form-select form-select-sm">
                        <option value="">- All Projects -</option>
                        <?php foreach ($opt_projects as $p) echo "<option value='$p' ".($filter_project==$p?'selected':'').">$p</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold text-muted mb-1 text-uppercase" style="font-size:0.7rem">Courier</label>
                    <select name="filter_courier" class="form-select form-select-sm">
                        <option value="">- All Couriers -</option>
                        <?php foreach ($opt_couriers as $c) echo "<option value='$c' ".($filter_courier==$c?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold text-muted mb-1 text-uppercase" style="font-size:0.7rem">Status</label>
                    <select name="filter_status" class="form-select form-select-sm">
                        <option value="">- All Status -</option>
                        <option value="Process" <?= ($filter_status=='Process'?'selected':'') ?>>Process</option>
                        <option value="Shipped" <?= ($filter_status=='Shipped'?'selected':'') ?>>Shipped</option>
                        <option value="Delivered" <?= ($filter_status=='Delivered'?'selected':'') ?>>Delivered</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white border-bottom pt-3 pb-0">
            <ul class="nav nav-tabs border-0" id="logisticsTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active border-0 border-bottom border-3 border-primary text-primary fw-bold" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button">Outbound (Delivery)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link border-0 text-muted" id="receive-tab" data-bs-toggle="tab" data-bs-target="#receive" type="button">Inbound (Receive)</button>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content">
                
                <div class="tab-pane fade show active" id="delivery">
                    <div class="table-responsive">
                        <table class="table-custom" id="table-delivery">
                            <thead>
                                <tr>
                                    <th>Sent Date</th>
                                    <th>Status</th>
                                    <th>Project / Client</th>
                                    <th>Tracking Info</th>
                                    <th>Sender</th>
                                    <th>Receiver</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data_delivery)): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted">No delivery data found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($data_delivery as $row): 
                                        $st = strtolower($row['status'] ?? '');
                                        $statusClass = 'bg-yellow'; $icon = 'bi-clock';
                                        if(strpos($st, 'shipped')!==false) { $statusClass = 'bg-blue'; $icon = 'bi-truck'; }
                                        if(strpos($st, 'delivered')!==false) { $statusClass = 'bg-green'; $icon = 'bi-check-circle-fill'; }
                                        
                                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= date('d M Y', strtotime($row['delivery_date'])) ?></div>
                                            <div class="small text-muted"><?= date('H:i', strtotime($row['delivery_date'])) ?></div>
                                        </td>
                                        <td>
                                            <div class="status-badge <?= $statusClass ?>">
                                                <i class="bi <?= $icon ?> me-1"></i> <?= ucfirst($row['status']) ?>
                                            </div>
                                            <?php if(!empty($row['delivered_date'])): ?>
                                                <div class="small text-success fw-bold mt-1" style="font-size:0.7rem">
                                                    <?= date('d M Y', strtotime($row['delivered_date'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm bg-light text-primary rounded me-2 d-flex align-items-center justify-content-center fw-bold" style="width:30px;height:30px">
                                                    <?= substr($row['client_name'],0,1) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['client_name']) ?></div>
                                                    <div class="small text-muted" style="font-size:0.7rem">PO: <?= htmlspecialchars($row['client_po']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0)" class="tracking-code" onclick='trackResi("<?= htmlspecialchars($row['tracking_number']) ?>", "<?= htmlspecialchars($row['courier_name']) ?>", "<?= htmlspecialchars($row['client_name']) ?>")'>
                                                <?= htmlspecialchars($row['tracking_number'] ?: 'NO-RESI') ?>
                                            </a><br>
                                            <div class="courier-tag"><?= htmlspecialchars($row['courier_name']) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">PT LinksField</div>
                                            <div class="small text-muted">Warehouse Jakarta</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($row['receiver_phone']) ?></div>
                                        </td>
                                        <td class="text-center fw-bold"><?= number_format($row['qty']) ?></td>
                                        <td class="text-center">
                                            <button class="btn-action" title="Detail" onclick='viewDetail(<?= $rowJson ?>)'><i class="bi bi-eye"></i></button>
                                            <button class="btn-action" title="Edit" onclick='editDelivery(<?= $rowJson ?>)'><i class="bi bi-pencil"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="receive">
                    <div class="table-responsive">
                        <table class="table-custom" id="table-receive">
                            <thead>
                                <tr>
                                    <th>Received Date</th>
                                    <th>Status</th>
                                    <th>Provider / Sender</th>
                                    <th>Internal PO</th>
                                    <th>Receiver (WH)</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($data_receive as $row): 
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= date('d M Y', strtotime($row['logistic_date'])) ?></div>
                                    </td>
                                    <td><span class="status-badge bg-green"><i class="bi bi-check-circle-fill me-1"></i> Received</span></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['provider_name']) ?></div>
                                        <div class="small text-muted">Batch: <?= htmlspecialchars($row['batch_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="font-monospace text-primary fw-bold"><?= htmlspecialchars($row['provider_po']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold">Internal Warehouse</div>
                                        <div class="small text-muted">PIC: <?= htmlspecialchars($row['pic_name']) ?></div>
                                    </td>
                                    <td class="text-center fw-bold text-success">+<?= number_format($row['qty']) ?></td>
                                    <td class="text-center">
                                        <button class="btn-action" onclick='editReceive(<?= $rowJson ?>)'><i class="bi bi-pencil"></i></button>
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
</div>

<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom pb-3">
                <div>
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center">
                        <i class="bi bi-box-seam me-2 text-primary fs-4"></i> Tracking Details
                    </h5>
                    <div class="small text-muted mt-1">Destination: <span id="trackDestSubtitle" class="fw-bold text-dark"></span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div id="trackingResult" class="p-0">
                    <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-md"><div class="modal-content border-0 shadow"><div class="modal-header bg-white border-bottom-0 pb-0"><h5 class="modal-title fw-bold text-dark">Delivery Detail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailContent"></div></div></div></div>

<div class="modal fade" id="modalDelivery" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="del_action" value="create_logistic">
            <input type="hidden" name="type" value="delivery">
            <input type="hidden" name="id" id="del_id">
            <div class="modal-header bg-primary text-white"><h6 class="modal-title fw-bold">Outbound Delivery</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Destination (Client)</h6>
                        <div class="mb-3"><label class="small fw-bold">Date</label><input type="date" name="logistic_date" id="del_date" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold">Client PO</label><select name="po_id" id="del_po_id" class="form-select" required><option value="">-- Select --</option><?php foreach($client_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                        <div class="row"><div class="col-6 mb-3"><label class="small fw-bold">Recipient</label><input type="text" name="pic_name" id="del_pic" class="form-control"></div><div class="col-6 mb-3"><label class="small fw-bold">Phone</label><input type="text" name="pic_phone" id="del_phone" class="form-control"></div></div>
                        <div class="mb-3"><label class="small fw-bold">Address</label><textarea name="delivery_address" id="del_address" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Shipping Info</h6>
                        <div class="row"><div class="col-6 mb-3"><label class="small fw-bold">Courier</label><input type="text" name="courier" id="del_courier" class="form-control"></div><div class="col-6 mb-3"><label class="small fw-bold">AWB / Resi</label><input type="text" name="awb" id="del_awb" class="form-control"></div></div>
                        <div class="mb-3"><label class="small fw-bold">Qty</label><input type="number" name="qty" id="del_qty" class="form-control" required></div>
                        <div class="mb-3"><label class="small fw-bold">Status</label><select name="status" id="del_status" class="form-select"><option value="Process">Process</option><option value="Shipped">Shipped</option><option value="Delivered">Delivered</option></select></div>
                        <div class="p-3 bg-light rounded"><label class="small fw-bold text-muted d-block mb-1">Proof of Delivery</label><div class="row g-1"><div class="col-6"><input type="date" name="received_date" id="del_recv_date" class="form-control form-control-sm"></div><div class="col-6"><input type="text" name="receiver_name" id="del_recv_name" class="form-control form-control-sm" placeholder="Accepted By"></div></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalReceive" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive">
            <input type="hidden" name="id" id="recv_id">
            <div class="modal-header bg-success text-white"><h6 class="modal-title fw-bold">Inbound Receive</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="mb-3"><label class="small fw-bold">Date</label><input type="date" name="logistic_date" id="recv_date" class="form-control" required></div>
                <div class="mb-3"><label class="small fw-bold">Provider PO</label><select name="po_id" id="recv_po_id" class="form-select" required><option value="">-- Select --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                <div class="row"><div class="col-6 mb-3"><label class="small fw-bold">PIC</label><input type="text" name="pic_name" id="recv_pic" class="form-control"></div><div class="col-6 mb-3"><label class="small fw-bold">Qty</label><input type="number" name="qty" id="recv_qty" class="form-control" required></div></div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success fw-bold">Save</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#table-delivery').DataTable({ dom: 't<"d-flex justify-content-between px-3 py-3"ip>', pageLength: 10, searching: false });
        $('#table-receive').DataTable({ dom: 't<"d-flex justify-content-between px-3 py-3"ip>', pageLength: 10, searching: false });
    });

    let modalReceive, modalDelivery, modalTracking, modalDetail;
    document.addEventListener('DOMContentLoaded', function() {
        modalReceive = new bootstrap.Modal(document.getElementById('modalReceive'));
        modalDelivery = new bootstrap.Modal(document.getElementById('modalDelivery'));
        modalTracking = new bootstrap.Modal(document.getElementById('trackingModal'));
        modalDetail = new bootstrap.Modal(document.getElementById('detailModal'));
    });

    // --- TRACKING FUNCTION (SMART DOM REPLACEMENT) ---
    function trackResi(resi, kurir, clientName) {
        if(!resi || !kurir) { alert('No tracking data'); return; }
        
        $('#trackDestSubtitle').text(clientName || 'Unknown Client');
        modalTracking.show();
        $('#trackingResult').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted small">Memuat data dari kurir...</div></div>');
        
        // Pass query params fallback
        let fetchUrl = `ajax_track_delivery.php?resi=${resi}&kurir=${kurir}&origin=PT+LinksField&destination=${encodeURIComponent(clientName)}`;
        
        fetch(fetchUrl)
            .then(r => r.text())
            .then(html => { 
                // Bungkus HTML yang didapat ke dalam DOM element bayangan untuk di-manipulasi
                let $dom = $('<div>').html(html);
                
                // Cari elemen yang isinya persis kata "ORIGIN" dan "DESTINATION"
                let leafNodes = $dom.find('*:not(:has(*))');
                
                // Ganti Value Origin (Jika ada 2 label/value "ORIGIN", ganti yang terakhir alias valuenya)
                let origins = leafNodes.filter(function() { return $(this).text().trim().toUpperCase() === 'ORIGIN'; });
                if(origins.length > 1) origins.last().text('PT LinksField').addClass('text-primary');
                else if(origins.length === 1) origins.first().text('PT LinksField').addClass('text-primary');
                
                // Ganti Value Destination
                let dests = leafNodes.filter(function() { return $(this).text().trim().toUpperCase() === 'DESTINATION'; });
                if(dests.length > 1) dests.last().text(clientName).addClass('text-primary');
                else if(dests.length === 1) dests.first().text(clientName).addClass('text-primary');

                // Tampilkan HTML yang sudah dimodifikasi
                $('#trackingResult').html($dom.html());
            })
            .catch(e => { 
                $('#trackingResult').html('<div class="alert alert-danger text-center m-4">Gagal terhubung ke server kurir.</div>'); 
            });
    }

    // --- DETAIL MODAL FUNCTION ---
    function viewDetail(data) {
        let html = `
            <div class="text-center mb-4 pt-3">
                <h5 class="fw-bold mb-0 text-primary">${data.tracking_number || '-'}</h5>
                <span class="badge bg-secondary text-uppercase">${data.courier_name || '-'}</span>
            </div>
            <ul class="list-group list-group-flush border-top">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted small">Client (Dest)</span><span class="fw-bold text-end text-dark">${data.client_name || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted small">Client PO</span><span class="fw-bold text-end font-monospace">${data.client_po || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted small">Recipient</span><span class="fw-bold text-end">${data.receiver_name || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted small">Phone</span><span class="fw-bold text-end">${data.receiver_phone || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted small">Sent Date</span><span class="fw-bold text-end">${data.delivery_date}</span></li>
                <li class="list-group-item"><span class="text-muted small d-block mb-1">Address</span><span class="d-block small bg-light p-2 rounded border">${data.receiver_address || '-'}</span></li>
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