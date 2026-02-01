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

// --- HANDLE FILTERS (PHP) ---
$filter_search = $_GET['search_track'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

// --- FETCH DATA LOGISTICS ---
$logistics = [];
try {
    $sql = "SELECT l.*, 
            po.po_number, po.batch_name, po.type as po_type,
            COALESCE(c.company_name, po.manual_company_name) as company_name
            FROM sim_tracking_logistics l
            LEFT JOIN sim_tracking_po po ON l.po_id = po.id
            LEFT JOIN companies c ON po.company_id = c.id
            WHERE 1=1";
    
    // Apply Filter jika ada
    if(!empty($filter_search)) {
        $sql .= " AND (l.awb LIKE '%$filter_search%' OR l.pic_name LIKE '%$filter_search%')";
    }
    if(!empty($filter_courier)) {
        $sql .= " AND l.courier = '$filter_courier'";
    }
    if(!empty($filter_status)) {
        $sql .= " AND l.status = '$filter_status'";
    }

    $sql .= " ORDER BY l.logistic_date DESC, l.id DESC";

    if ($db) {
        $stmt = $db->query($sql);
        if($stmt) $logistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// Pisahkan Data Receive & Delivery
$data_receive = array_filter($logistics, function($item) { return $item['type'] === 'receive'; });
$data_delivery = array_filter($logistics, function($item) { return $item['type'] === 'delivery'; });

// --- FETCH OPTIONS FOR DROPDOWN ---
$provider_pos = [];
$client_pos = [];
$opt_couriers = []; // Untuk filter
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
        
        // Get Distinct Couriers for Filter
        $stmtCour = $db->query("SELECT DISTINCT courier FROM sim_tracking_logistics WHERE type='delivery' AND courier != ''");
        if($stmtCour) $opt_couriers = $stmtCour->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}
?>

<style>
    /* BASE STYLES */
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    
    /* CARDS & TABS */
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
    .tab-content { padding-top: 20px; }

    /* --- MODERN TABLE STYLE (CLONED FROM DELIVERY_LIST) --- */
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
        padding: 14px 12px;
        vertical-align: middle;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        background: #fff;
    }
    .table-modern tr:hover td { background-color: #f8fafc; }
    
    /* Filter Card Style */
    .filter-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    .text-label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; margin-bottom: 6px; display: block; text-transform: uppercase; }
    
    /* Components */
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
                        <table class="table-modern" id="table-receive">
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
                                            <div class="avatar avatar-sm bg-light-success me-2 text-success rounded-circle d-flex align-items-center justify-content-center" style="width:30px;height:30px"><i class="bi bi-person"></i></div>
                                            <span class="fw-bold text-dark ms-2"><?= htmlspecialchars($row['pic_name'] ?? '-') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div>
                                        <div class="small text-muted">PO: <?= htmlspecialchars($row['po_number']) ?></div>
                                    </td>
                                    <td><span class="badge bg-success text-white px-3 py-2 rounded-pill">+ <?= number_format($row['qty']) ?></span></td>
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
                    
                    <div class="filter-card shadow-sm">
                        <form method="GET" action="sim_tracking_receive.php">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="text-label">Search Tracking / PIC</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" name="search_track" class="form-control border-start-0" placeholder="AWB or Name..." value="<?= htmlspecialchars($filter_search) ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="text-label">Courier</label>
                                    <select name="filter_courier" class="form-select form-select-sm">
                                        <option value="">- All Couriers -</option>
                                        <?php foreach($opt_couriers as $c): ?>
                                            <option value="<?= $c ?>" <?= ($filter_courier == $c) ? 'selected' : '' ?>><?= strtoupper($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="text-label">Status</label>
                                    <select name="filter_status" class="form-select form-select-sm">
                                        <option value="">- All Status -</option>
                                        <option value="On Process" <?= ($filter_status == 'On Process') ? 'selected' : '' ?>>On Process</option>
                                        <option value="Shipped" <?= ($filter_status == 'Shipped') ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Delivered" <?= ($filter_status == 'Delivered') ? 'selected' : '' ?>>Delivered</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Filter</button>
                                        <a href="sim_tracking_receive.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Outbound Data List</h6>
                        <button class="btn btn-primary btn-sm px-4 fw-bold shadow-sm" onclick="openDeliveryModal()"><i class="bi bi-plus me-1"></i> Add Delivery</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="table-delivery">
                            <thead>
                                <tr>
                                    <th>Sent Date</th>
                                    <th>Destination</th>
                                    <th>Tracking Info</th>
                                    <th>Status</th>
                                    <th>Received By</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($data_delivery)): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted">Data tidak ditemukan.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($data_delivery as $row): 
                                        $st = strtolower($row['status'] ?? '');
                                        $badgeClass = 'bg-secondary';
                                        if(strpos($st, 'process')!==false) $badgeClass='bg-warning text-dark';
                                        if(strpos($st, 'shipped')!==false) $badgeClass='bg-info text-white';
                                        if(strpos($st, 'delivered')!==false) $badgeClass='bg-success text-white';
                                        
                                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 200px;"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($row['delivery_address'] ?? '-') ?></div>
                                            <div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($row['pic_name'] ?? '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-primary"><?= htmlspecialchars($row['courier']) ?></div>
                                            <div class="input-group input-group-sm mt-1" style="width: 160px;">
                                                <input type="text" class="form-control form-control-sm bg-white" value="<?= htmlspecialchars($row['awb']) ?>" readonly>
                                                <button class="btn btn-outline-primary" type="button" onclick='openTrackingModal(<?= $rowJson ?>)' title="Track Package">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?> px-3 py-1 rounded-pill text-uppercase" style="font-size: 0.7rem;"><?= htmlspecialchars($row['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if($row['receiver_name']): ?>
                                                <div class="d-flex align-items-center text-success">
                                                    <i class="bi bi-check-circle-fill me-2"></i>
                                                    <div>
                                                        <div class="fw-bold small"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                                        <div class="small text-muted" style="font-size:0.7rem;"><?= date('d M Y', strtotime($row['received_date'])) ?></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small fst-italic">- Pending -</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
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
        // Init DataTable
        // Note: Delivery tab uses standard PHP loop filters now, so DataTables search can be secondary or we disable it to avoid confusion
        $('#table-receive').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
        $('#table-delivery').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10, searching: false }); // Disable client-side search since we have server-side filter card
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
            url = `https://berdu.id/cek-resi?courier=${courier.toLowerCase()}&resi=${awb}`;
        }
        $('#btn_track_external').attr('href', url);

        // Timeline Logic
        $('.track-step').removeClass('active completed');
        $('#date_process, #date_shipped, #date_delivered').text('-');

        $('#step_process').addClass('completed');
        $('#date_process').text(dateSent);

        if(status.includes('ship') || status.includes('deliver') || status.includes('receiv')) {
            $('#step_shipped').addClass('completed');
            $('#date_shipped').text('In Transit');
        } else if (status.includes('process')) {
            $('#step_shipped').addClass('active');
        }

        if(status.includes('deliver') || status.includes('receiv') || dateRecv) {
            $('#step_shipped').addClass('completed');
            $('#step_delivered').addClass('completed');
            $('#date_delivered').text(dateRecv || 'Delivered');
        }

        modalTracking.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>