<?php
// =========================================================================
// 1. SETUP & DATABASE CONNECTION
// =========================================================================
ini_set('display_errors', 0);
error_reporting(E_ALL);
$current_page = 'sim_tracking_receive.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; // Load db_connect()
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// GUNAKAN KONEKSI STANDAR
$db = db_connect();

// --- FETCH DATA LOGISTICS ---
$logistics = [];
try {
    $sql = "SELECT l.*, 
            po.po_number, po.batch_name, po.type as po_type,
            COALESCE(c.company_name, po.manual_company_name) as company_name
            FROM sim_tracking_logistics l
            LEFT JOIN sim_tracking_po po ON l.po_id = po.id
            LEFT JOIN companies c ON po.company_id = c.id
            ORDER BY l.logistic_date DESC, l.id DESC";

    if ($db) {
        $stmt = $db->query($sql);
        if($stmt) $logistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

$data_receive = array_filter($logistics, function($item) { return $item['type'] === 'receive'; });
$data_delivery = array_filter($logistics, function($item) { return $item['type'] === 'delivery'; });

// --- FETCH PO OPTIONS ---
$provider_pos = [];
$client_pos = [];
try {
    $sql_po = "SELECT po.id, po.po_number, po.batch_name, po.type,
               COALESCE(c.company_name, po.manual_company_name) as company_name
               FROM sim_tracking_po po
               LEFT JOIN companies c ON po.company_id = c.id
               ORDER BY po.id DESC";
               
    if ($db) {
        $stmtPO = $db->query($sql_po);
        if($stmtPO) {
            $all_pos = $stmtPO->fetchAll(PDO::FETCH_ASSOC);
            foreach($all_pos as $po) {
                if($po['type'] === 'provider') $provider_pos[] = $po;
                else $client_pos[] = $po;
            }
        }
    }
} catch (Exception $e) {}
?>

<style>
    /* UI SYNC WITH ACTIVATION PAGE */
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    
    /* Cards */
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    /* Tabs */
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
    .tab-content { padding-top: 20px; }

    /* Tables */
    .table-custom { width: 100%; border-collapse: collapse; }
    .table-custom th { background-color: #f9fafb; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 16px 24px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .table-custom td { padding: 18px 24px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #334155; }
    .table-custom tr:hover td { background-color: #f8fafc; }

    /* Buttons & Actions */
    .btn-action-menu { background: #fff; border: 1px solid #e2e8f0; color: #64748b; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; transition: 0.2s; }
    .btn-action-menu:hover { background-color: #f8fafc; color: #1e293b; }
    
    /* Tracking Timeline Styles */
    .track-step { position: relative; padding-bottom: 20px; padding-left: 30px; border-left: 2px solid #e2e8f0; }
    .track-step:last-child { border-left: 2px solid transparent; }
    .track-step::before { content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #fff; border: 2px solid #cbd5e1; }
    .track-step.active::before { background: #435ebe; border-color: #435ebe; box-shadow: 0 0 0 3px #e0e7ff; }
    .track-step.completed::before { background: #10b981; border-color: #10b981; }
    .track-step.completed { border-left-color: #10b981; }
    
    .track-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; }
    .track-date { font-size: 0.75rem; color: #64748b; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Receive & Delivery</h3>
            <p class="text-muted mb-0 small">Manage Inbound and Outbound Logistics.</p>
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
                        <h6 class="fw-bold text-dark m-0">Inbound Data</h6>
                        <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm" onclick="openReceiveModal()"><i class="bi bi-plus me-1"></i> Add Receive</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table-custom" id="table-receive">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>PIC Project</th>
                                    <th>Provider / Source</th>
                                    <th>Qty</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_receive as $row): 
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-light-success me-2 text-success rounded-circle"><i class="bi bi-person"></i></div>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($row['pic_name'] ?? '-') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div>
                                        <div class="small text-muted">PO: <?= htmlspecialchars($row['po_number']) ?></div>
                                    </td>
                                    <td><span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">+ <?= number_format($row['qty']) ?></span></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-action-menu" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="#" onclick='editReceive(<?= $rowJson ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                                <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="delivery">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Outbound Data</h6>
                        <button class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" onclick="openDeliveryModal()"><i class="bi bi-plus me-1"></i> Add Delivery</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table-custom" id="table-delivery">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Destination</th>
                                    <th>Courier / AWB</th>
                                    <th>Status</th>
                                    <th>Received By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_delivery as $row): 
                                    $st = strtolower($row['status'] ?? '');
                                    $badgeClass = 'bg-secondary';
                                    if(strpos($st, 'process')!==false) $badgeClass='bg-warning text-dark';
                                    if(strpos($st, 'shipped')!==false) $badgeClass='bg-info text-white';
                                    if(strpos($st, 'delivered')!==false) $badgeClass='bg-success';
                                    
                                    // JSON safe for JS
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div>
                                        <div class="small text-muted text-truncate" style="max-width: 200px;"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($row['delivery_address'] ?? '-') ?></div>
                                        <div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($row['pic_name'] ?? '-') ?> (<?= htmlspecialchars($row['pic_phone'] ?? '') ?>)</div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-between bg-light rounded p-2 border" style="width: 180px;">
                                            <div>
                                                <div class="fw-bold text-uppercase text-primary" style="font-size:0.75rem"><?= htmlspecialchars($row['courier']) ?></div>
                                                <div class="small text-dark font-monospace"><?= htmlspecialchars($row['awb']) ?></div>
                                            </div>
                                            <button class="btn btn-sm btn-white border shadow-sm text-primary" onclick='openTrackingModal(<?= $rowJson ?>)' title="Track Live">
                                                <i class="bi bi-crosshair"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td><span class="badge <?= $badgeClass ?> px-3 py-1 rounded-pill text-uppercase" style="font-size: 0.7rem;"><?= htmlspecialchars($row['status']) ?></span></td>
                                    <td>
                                        <?php if($row['receiver_name']): ?>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                            <div class="small text-muted"><?= date('d/m/y', strtotime($row['received_date'])) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-action-menu" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                <li><a class="dropdown-item" href="#" onclick='editDelivery(<?= $rowJson ?>)'><i class="bi bi-pencil me-2 text-warning"></i> Edit</a></li>
                                                <li><a class="dropdown-item text-danger" href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" onclick="return confirm('Delete?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
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

<div class="modal fade" id="modalReceive" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive">
            <input type="hidden" name="id" id="recv_id">
            
            <input type="hidden" name="status" value="Delivered">
            <input type="hidden" name="courier" value="-">
            <input type="hidden" name="awb" value="-">

            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title m-0 fw-bold">Inbound / Receive</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Date Receive</label>
                    <input type="date" name="logistic_date" id="recv_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">Link PO Provider</label>
                    <select name="po_id" id="recv_po_id" class="form-select" required>
                        <option value="">-- Select Provider PO --</option>
                        <?php foreach($provider_pos as $po): ?>
                            <option value="<?= $po['id'] ?>"><?= $po['company_name'].' - '.$po['po_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-bold small text-uppercase">PIC Receive</label>
                        <input type="text" name="pic_name" id="recv_pic" class="form-control" placeholder="Name">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-bold small text-uppercase">Quantity</label>
                        <input type="number" name="qty" id="recv_qty" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success fw-bold px-4">Save Receive</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDelivery" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="del_action" value="create_logistic">
            <input type="hidden" name="type" value="delivery">
            <input type="hidden" name="id" id="del_id">

            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title m-0 fw-bold">Outbound / Delivery</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Destination Info</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Date Delivery</label>
                            <input type="date" name="logistic_date" id="del_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Link Client PO</label>
                            <select name="po_id" id="del_po_id" class="form-select" required>
                                <option value="">-- Select Client PO --</option>
                                <?php foreach($client_pos as $po): ?>
                                    <option value="<?= $po['id'] ?>"><?= $po['company_name'].' - '.$po['po_number'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">PIC Name</label>
                                <input type="text" name="pic_name" id="del_pic" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">PIC Phone</label>
                                <input type="text" name="pic_phone" id="del_phone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Address</label>
                            <textarea name="delivery_address" id="del_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Shipping Info</h6>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">Courier</label>
                                <input type="text" name="courier" id="del_courier" class="form-control" placeholder="JNE/J&T">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold small text-uppercase">AWB / Resi</label>
                                <input type="text" name="awb" id="del_awb" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Quantity</label>
                            <input type="number" name="qty" id="del_qty" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase">Internal Status</label>
                            <select name="status" id="del_status" class="form-select">
                                <option value="On Process">On Process</option>
                                <option value="Shipped">Shipped</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Returned">Returned</option>
                            </select>
                        </div>
                        <div class="p-3 bg-light rounded border">
                            <label class="fw-bold small text-muted mb-2 d-block">PROOF OF DELIVERY</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="date" name="received_date" id="del_recv_date" class="form-control form-control-sm" title="Date Received">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="receiver_name" id="del_recv_name" class="form-control form-control-sm" placeholder="Received By">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary fw-bold px-4">Save Delivery</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalTracking" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h6 class="modal-title m-0 fw-bold"><i class="bi bi-geo-alt-fill me-2"></i>Live Tracking</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-light p-4 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-uppercase text-muted fw-bold small">Courier</span>
                        <span class="fw-bold text-dark" id="track_courier">JNE</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-uppercase text-muted fw-bold small">Tracking No (AWB)</span>
                        <span class="fw-bold text-primary fs-5 font-monospace" id="track_awb">1234567890</span>
                    </div>
                </div>
                
                <div class="p-4">
                    <h6 class="text-muted fw-bold small mb-3 text-uppercase">Internal Status Timeline</h6>
                    <div class="ps-2">
                        <div class="track-step completed" id="step_process">
                            <div class="track-title">Order Processed</div>
                            <div class="track-date" id="date_process">-</div>
                        </div>
                        <div class="track-step" id="step_shipped">
                            <div class="track-title">Shipped (On Courier)</div>
                            <div class="track-date" id="date_shipped">-</div>
                        </div>
                        <div class="track-step" id="step_delivered">
                            <div class="track-title">Delivered</div>
                            <div class="track-date" id="date_delivered">-</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top text-center">
                        <p class="small text-muted mb-2">Need detailed location?</p>
                        <a href="#" id="btn_track_external" target="_blank" class="btn btn-outline-primary w-100 fw-bold">
                            <i class="bi bi-box-seam me-2"></i> Track on <span id="track_provider_name">Provider</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Init DataTable with matching style
        $('#table-receive').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
        $('#table-delivery').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
    });

    // OPEN MODALS
    let modalReceive, modalDelivery, modalTracking;
    document.addEventListener('DOMContentLoaded', function() {
        modalReceive = new bootstrap.Modal(document.getElementById('modalReceive'));
        modalDelivery = new bootstrap.Modal(document.getElementById('modalDelivery'));
        modalTracking = new bootstrap.Modal(document.getElementById('modalTracking'));
    });

    // RECEIVE
    function openReceiveModal() {
        $('#recv_action').val('create_logistic');
        $('#recv_id').val('');
        $('#recv_date').val(new Date().toISOString().split('T')[0]);
        $('#recv_pic').val('');
        $('#recv_po_id').val('');
        $('#recv_qty').val('');
        modalReceive.show();
    }

    function editReceive(data) {
        $('#recv_action').val('update_logistic');
        $('#recv_id').val(data.id);
        $('#recv_date').val(data.logistic_date);
        $('#recv_pic').val(data.pic_name);
        $('#recv_po_id').val(data.po_id);
        $('#recv_qty').val(data.qty);
        modalReceive.show();
    }

    // DELIVERY
    function openDeliveryModal() {
        $('#del_action').val('create_logistic');
        $('#del_id').val('');
        $('#del_date').val(new Date().toISOString().split('T')[0]);
        $('#del_po_id').val('');
        $('#del_pic').val('');
        $('#del_phone').val('');
        $('#del_address').val('');
        $('#del_courier').val('');
        $('#del_awb').val('');
        $('#del_qty').val('');
        $('#del_status').val('On Process');
        $('#del_recv_date').val('');
        $('#del_recv_name').val('');
        modalDelivery.show();
    }

    function editDelivery(data) {
        $('#del_action').val('update_logistic');
        $('#del_id').val(data.id);
        $('#del_date').val(data.logistic_date);
        $('#del_po_id').val(data.po_id);
        $('#del_pic').val(data.pic_name);
        $('#del_phone').val(data.pic_phone);
        $('#del_address').val(data.delivery_address);
        $('#del_courier').val(data.courier);
        $('#del_awb').val(data.awb);
        $('#del_qty').val(data.qty);
        $('#del_status').val(data.status);
        $('#del_recv_date').val(data.received_date);
        $('#del_recv_name').val(data.receiver_name);
        modalDelivery.show();
    }

    // TRACKING REALTIME LOGIC
    function openTrackingModal(data) {
        let courier = (data.courier || '').toUpperCase();
        let awb = data.awb || '';
        let status = (data.status || '').toLowerCase();
        let dateSent = data.logistic_date;
        let dateRecv = data.received_date;

        $('#track_courier').text(courier || 'UNKNOWN');
        $('#track_awb').text(awb || '-');
        $('#track_provider_name').text(courier || 'Provider');

        // Generate Smart Link
        let url = '#';
        if(awb) {
            // General Aggregator (Works for JNE, J&T, SiCepat, etc)
            url = `https://berdu.id/cek-resi?courier=${courier.toLowerCase()}&resi=${awb}`;
        }
        $('#btn_track_external').attr('href', url);

        // Timeline Logic (Simulated based on Internal Status)
        $('.track-step').removeClass('active completed');
        $('#date_process, #date_shipped, #date_delivered').text('-');

        // Step 1: Processed
        $('#step_process').addClass('completed');
        $('#date_process').text(dateSent);

        // Step 2: Shipped
        if(status.includes('ship') || status.includes('deliver') || status.includes('receiv')) {
            $('#step_shipped').addClass('completed');
            $('#date_shipped').text('In Transit');
        } else if (status.includes('process')) {
            $('#step_shipped').addClass('active');
        }

        // Step 3: Delivered
        if(status.includes('deliver') || status.includes('receiv') || dateRecv) {
            $('#step_shipped').addClass('completed'); // Ensure previous is done
            $('#step_delivered').addClass('completed');
            $('#date_delivered').text(dateRecv || 'Delivered');
        }

        modalTracking.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>