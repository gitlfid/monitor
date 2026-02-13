<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// UPDATE: Restore Live Tracking Fetch + Dynamic PO Info
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

// --- C. DATA PO UNTUK MODAL ---
$provider_pos = []; $client_pos = [];
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
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8f9fa; color: #334155; }
    .card { border: 1px solid #eef2f6; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 12px 12px 0 0 !important; }
    
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-modern thead th {
        font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;
        background-color: #f8f9fa; color: #64748b; font-weight: 700;
        border-bottom: 1px solid #e2e8f0; padding: 14px 16px; white-space: nowrap;
    }
    .table-modern tbody td {
        font-size: 0.9rem; padding: 16px 16px; vertical-align: middle;
        color: #334155; border-bottom: 1px solid #f1f5f9; background: #fff;
    }
    .table-modern tr:hover td { background-color: #f8fafc; }
    
    .badge-soft-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-soft-info { background-color: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
    .badge-soft-warning { background-color: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    .badge-soft-secondary { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    
    .text-po { font-family: 'Consolas', monospace; font-weight: 700; color: #435ebe; letter-spacing: -0.5px; }
    .text-company { font-weight: 700; color: #1e293b; }
    
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
</style>

<div class="page-heading mb-4 px-3 pt-3">
    <h3 class="mb-1 text-dark fw-bold">Logistics Tracking</h3>
    <p class="text-muted mb-0 small">Manage Inbound (Provider) and Outbound (Client) Logistics.</p>
</div>

<section class="px-3">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-0">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold text-dark m-0">Inbound History (From Provider)</h6>
                        <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm rounded-pill" onclick="openReceiveModal()"><i class="bi bi-plus me-1"></i> Add Receive</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table-modern" style="width:100%" id="table-receive">
                            <thead>
                                <tr>
                                    <th class="ps-4">Date Received</th>
                                    <th>Provider Source</th> 
                                    <th>Receiver (Internal)</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_receive as $row): $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                <tr>
                                    <td class="ps-4"><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span></td>
                                    <td>
                                        <div class="text-company"><?= htmlspecialchars($row['provider_name'] ?? 'Unknown Provider') ?></div>
                                        <div class="small text-muted mt-1"><i class="bi bi-file-earmark-text me-1"></i>PO: <span class="text-po"><?= htmlspecialchars($row['provider_po'] ?? '-') ?></span></div>
                                    </td>
                                    <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['pic_name'] ?? '-') ?></div></td>
                                    <td class="text-center"><span class="badge badge-soft-success px-3 py-1 rounded-pill">+ <?= number_format($row['qty'] ?? 0) ?></span></td>
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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Outbound Data List (To Client)</h6>
                        <button class="btn btn-primary btn-sm px-4 fw-bold shadow-sm rounded-pill" onclick="openDeliveryModal()"><i class="bi bi-plus me-1"></i> Input Delivery</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="table-delivery">
                            <thead>
                                <tr>
                                    <th class="ps-4">Sent Date</th>
                                    <th>Status</th>
                                    <th>Client Destination</th> 
                                    <th>Tracking / Courier</th>
                                    <th>Recipient</th>
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
                                        $badgeClass = 'badge-soft-secondary';
                                        if(strpos($st, 'process')!==false) $badgeClass='badge-soft-warning';
                                        if(strpos($st, 'shipped')!==false) $badgeClass='badge-soft-info';
                                        if(strpos($st, 'delivered')!==false) $badgeClass='badge-soft-success';
                                        
                                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                        $clientName = htmlspecialchars($row['client_name'] ?? 'Unknown Client'); 
                                    ?>
                                    <tr>
                                        <td class="ps-4"><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['delivery_date'])) ?></span></td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?> px-2 py-1 rounded-pill"><?= ucfirst($row['status'] ?? 'Process') ?></span>
                                            <?php if(!empty($row['delivered_date'])): ?>
                                                <div class="small text-success mt-1" style="font-size:0.7rem"><i class="bi bi-check-all"></i> <?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-company"><?= $clientName ?></div>
                                            <div class="small text-muted mt-1"><i class="bi bi-file-earmark-text me-1"></i>PO: <span class="text-po"><?= htmlspecialchars($row['client_po'] ?? '-') ?></span></div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <a href="#" onclick='trackResi("<?= $row['tracking_number'] ?? '' ?>", "<?= $row['courier_name'] ?? '' ?>", "<?= $clientName ?>")' class="text-decoration-none fw-bold font-monospace text-dark">
                                                    <?= htmlspecialchars($row['tracking_number'] ?? '-') ?>
                                                </a>
                                                <span class="badge bg-light text-secondary border mt-1" style="width:fit-content;font-size:0.65rem;">
                                                    <?= htmlspecialchars($row['courier_name'] ?? '-') ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold small"><?= htmlspecialchars($row['receiver_name'] ?? '-') ?></div>
                                            <?php if(!empty($row['receiver_phone'])): ?>
                                                <div class="small text-muted" style="font-size:0.7rem"><i class="bi bi-telephone"></i> <?= htmlspecialchars($row['receiver_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold"><?= number_format($row['qty'] ?? 0) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group shadow-sm">
                                                <button class="btn btn-sm btn-outline-primary" title="Lacak" onclick='trackResi("<?= $row['tracking_number'] ?? '' ?>", "<?= $row['courier_name'] ?? '' ?>", "<?= $clientName ?>")'><i class="bi bi-geo-alt-fill"></i></button>
                                                <button class="btn btn-sm btn-outline-secondary" title="Edit" onclick='editDelivery(<?= $rowJson ?>)'><i class="bi bi-pencil-square"></i></button>
                                                <button class="btn btn-sm btn-outline-info" title="Detail" onclick='viewDetail(<?= $rowJson ?>)'><i class="bi bi-eye"></i></button>
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
            <div class="modal-header bg-success text-white py-3"><h6 class="modal-title m-0 fw-bold">Inbound Receive</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="mb-3"><label class="form-label small fw-bold">Date</label><input type="date" name="logistic_date" id="recv_date" class="form-control" required></div>
                <div class="mb-3"><label class="form-label small fw-bold">Provider PO (Source)</label><select name="po_id" id="recv_po_id" class="form-select" required><option value="">-- Select --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                <div class="row"><div class="col-6 mb-3"><label class="form-label small fw-bold">Receiver (Internal)</label><input type="text" name="pic_name" id="recv_pic" class="form-control"></div><div class="col-6 mb-3"><label class="form-label small fw-bold">Qty</label><input type="number" name="qty" id="recv_qty" class="form-control" required></div></div>
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
            <div class="modal-header bg-primary text-white py-3"><h6 class="modal-title m-0 fw-bold">Outbound Delivery</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Client Destination</h6>
                        <div class="mb-3"><label class="form-label small fw-bold">Date</label><input type="date" name="logistic_date" id="del_date" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Client PO</label><select name="po_id" id="del_po_id" class="form-select" required><option value="">-- Select --</option><?php foreach($client_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                        <div class="row"><div class="col-6 mb-3"><label class="form-label small fw-bold">Recipient Name</label><input type="text" name="pic_name" id="del_pic" class="form-control"></div><div class="col-6 mb-3"><label class="form-label small fw-bold">Phone</label><input type="text" name="pic_phone" id="del_phone" class="form-control"></div></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Address</label><textarea name="delivery_address" id="del_address" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Shipping Details</h6>
                        <div class="row"><div class="col-6 mb-3"><label class="form-label small fw-bold">Courier</label><input type="text" name="courier" id="del_courier" class="form-control"></div><div class="col-6 mb-3"><label class="form-label small fw-bold">AWB / Resi</label><input type="text" name="awb" id="del_awb" class="form-control"></div></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Qty</label><input type="number" name="qty" id="del_qty" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Status</label><select name="status" id="del_status" class="form-select"><option value="Process">Process</option><option value="Shipped">Shipped</option><option value="Delivered">Delivered</option></select></div>
                        <div class="p-3 bg-light rounded border"><label class="fw-bold small text-muted mb-2 d-block">PROOF OF DELIVERY</label><div class="row g-2"><div class="col-6"><input type="date" name="received_date" id="del_recv_date" class="form-control form-control-sm"></div><div class="col-6"><input type="text" name="receiver_name" id="del_recv_name" class="form-control form-control-sm" placeholder="Accepted By"></div></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary" id="trackTitle"><i class="bi bi-truck me-2"></i> Shipment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button> 
            </div>
            <div class="modal-body bg-light">
                <div class="bg-white p-3 rounded shadow-sm mb-3 text-center border-bottom border-primary border-3">
                    <small class="text-muted text-uppercase fw-bold">Destination</small>
                    <h5 class="fw-bold text-dark m-0" id="trackDest"></h5>
                </div>
                <div id="trackingResult"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-md"><div class="modal-content border-0 shadow"><div class="modal-header bg-white border-bottom-0 pb-0"><h5 class="modal-title fw-bold text-dark">Delivery Detail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailContent"></div></div></div></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#table-receive').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
        $('#table-delivery').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10, searching: false });
    });

    let modalReceive, modalDelivery, modalTracking, modalDetail;
    document.addEventListener('DOMContentLoaded', function() {
        modalReceive = new bootstrap.Modal(document.getElementById('modalReceive'));
        modalDelivery = new bootstrap.Modal(document.getElementById('modalDelivery'));
        modalTracking = new bootstrap.Modal(document.getElementById('trackingModal'));
        modalDetail = new bootstrap.Modal(document.getElementById('detailModal'));
    });

    // --- TRACKING FUNCTION (FIXED: RESTORED FETCH) ---
    function trackResi(resi, kurir, clientName) {
        if(!resi || !kurir) { alert('No tracking data available'); return; }
        
        document.getElementById('trackDest').textContent = clientName || 'Unknown Client';
        
        modalTracking.show();
        document.getElementById('trackingResult').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Tracking...</p></div>';
        
        // --- LOGIKA LIVE TRACKING DIKEMBALIKAN DISINI ---
        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(r => r.text())
            .then(d => { 
                document.getElementById('trackingResult').innerHTML = d; 
            })
            .catch(e => { 
                document.getElementById('trackingResult').innerHTML = '<div class="alert alert-danger text-center">Error loading tracking data. Check connection.</div>'; 
            });
    }

    function viewDetail(data) {
        let html = `
            <div class="text-center mb-4 pt-3">
                <h5 class="fw-bold mb-0">${data.tracking_number || '-'}</h5>
                <span class="badge bg-secondary text-uppercase">${data.courier_name || '-'}</span>
            </div>
            <ul class="list-group list-group-flush border-top">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted small">Client (Dest)</span><span class="fw-bold text-end text-primary">${data.client_name || '-'}</span></li>
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