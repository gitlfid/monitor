<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// UPDATE: UI Polish (Symmetrical Tracking Modal & Professional Tables)
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
    
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); background: white; margin-bottom: 24px; overflow: hidden; }
    .page-title { font-size: 1.5rem; font-weight: 700; color: #111827; letter-spacing: -0.025em; }
    
    /* TABLE STYLES */
    .table-custom { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .table-custom thead th {
        background-color: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase;
        font-size: 0.75rem; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; letter-spacing: 0.05em;
    }
    .table-custom tbody td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
    .table-custom tr:hover td { background-color: #f8fafc; }

    /* BADGES */
    .status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .bg-green { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .bg-blue { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    .bg-yellow { background-color: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    
    /* TRACKING TEXT */
    .tracking-code { 
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; 
        font-weight: 700; color: #2563eb; font-size: 0.9rem; 
        cursor: pointer; text-decoration: none; transition: 0.2s; display: inline-block;
    }
    .tracking-code:hover { color: #1d4ed8; text-decoration: underline !important; }
    .courier-tag { background: #334155; color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; margin-top: 6px; display: inline-block; letter-spacing: 0.5px; }

    /* BUTTONS */
    .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #e2e8f0; background: white; color: #64748b; margin-right: 4px; transition: all 0.2s; cursor: pointer; }
    .btn-action:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; transform: translateY(-1px); }
    .btn-primary-custom { background-color: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.875rem; transition: all 0.2s; box-shadow: 0 1px 2px rgba(37,99,235,0.3); }
    .btn-primary-custom:hover { background-color: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(37,99,235,0.4); color: white; }
    
    /* TABS */
    .nav-tabs { border-bottom: 2px solid #e2e8f0; padding: 0 20px; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 16px 24px; font-size: 0.95rem; transition: 0.2s; background: transparent; }
    .nav-link:hover { color: #2563eb; background: #f8fafc; border-radius: 8px 8px 0 0; }
    .nav-link.active { color: #2563eb; border-bottom: 3px solid #2563eb; background: transparent; font-weight: 700; }

    /* MODAL UI POLISH */
    .modal-header-custom {
        background-color: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 20px 24px;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        border-radius: 12px 12px 0 0;
    }
    .modal-icon-box {
        width: 42px;
        height: 42px;
        background-color: #eff6ff;
        color: #2563eb;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
</style>

<div class="px-4 py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Delivery Management</h1>
            <p class="text-muted small m-0 mt-1">Monitor status pengiriman dan riwayat logistik secara real-time.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary-custom" style="background-color:#10b981; box-shadow: 0 1px 2px rgba(16,185,129,0.3);" onclick="openReceiveModal()">
                <i class="bi bi-box-arrow-in-down me-2"></i> Receive
            </button>
            <button class="btn btn-primary-custom" onclick="openDeliveryModal()">
                <i class="bi bi-truck me-2"></i> Input Delivery
            </button>
        </div>
    </div>

    <div class="card p-4 bg-white border-0">
        <form method="GET" action="sim_tracking_receive.php">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:0.7rem; letter-spacing:0.5px;">Search Tracking</label>
                    <input type="text" name="search_track" class="form-control" placeholder="No. Resi / Penerima..." value="<?= htmlspecialchars($search_track ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:0.7rem; letter-spacing:0.5px;">Project / Client</label>
                    <select name="filter_project" class="form-select">
                        <option value="">- All Projects -</option>
                        <?php foreach ($opt_projects as $p) echo "<option value='$p' ".($filter_project==$p?'selected':'').">$p</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:0.7rem; letter-spacing:0.5px;">Courier</label>
                    <select name="filter_courier" class="form-select">
                        <option value="">- All Couriers -</option>
                        <?php foreach ($opt_couriers as $c) echo "<option value='$c' ".($filter_courier==$c?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold text-muted mb-2 text-uppercase" style="font-size:0.7rem; letter-spacing:0.5px;">Status</label>
                    <select name="filter_status" class="form-select">
                        <option value="">- All Status -</option>
                        <option value="Process" <?= ($filter_status=='Process'?'selected':'') ?>>Process</option>
                        <option value="Shipped" <?= ($filter_status=='Shipped'?'selected':'') ?>>Shipped</option>
                        <option value="Delivered" <?= ($filter_status=='Delivered'?'selected':'') ?>>Delivered</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary-custom w-100"><i class="bi bi-search me-1"></i> Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white p-0">
            <ul class="nav nav-tabs" id="logisticsTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button">Outbound (Delivery)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="receive-tab" data-bs-toggle="tab" data-bs-target="#receive" type="button">Inbound (Receive)</button>
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
                                    <th>Delivered</th>
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
                                            <div class="fw-bold text-dark"><?= date('d M Y', strtotime($row['delivery_date'])) ?></div>
                                            <div class="small text-muted mt-1"><?= date('H:i', strtotime($row['delivery_date'])) ?> WIB</div>
                                        </td>
                                        <td>
                                            <div class="status-badge <?= $statusClass ?>">
                                                <i class="bi <?= $icon ?> me-1"></i> <?= ucfirst($row['status']) ?>
                                            </div>
                                            <?php if(!empty($row['delivered_date'])): ?>
                                                <div class="small text-success fw-bold mt-2" style="font-size:0.7rem">
                                                    <?= date('d M Y', strtotime($row['delivered_date'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm bg-light text-primary border rounded me-3 d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px; font-size:1rem;">
                                                    <?= substr($row['client_name'],0,1) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['client_name']) ?></div>
                                                    <div class="small text-muted font-monospace" style="font-size:0.75rem">PO: <?= htmlspecialchars($row['client_po']) ?></div>
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
                                            <div class="small text-muted mt-1">Warehouse Jakarta</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                            <div class="small text-muted mt-1"><?= htmlspecialchars($row['receiver_phone']) ?></div>
                                        </td>
                                        <td class="text-center fw-bold text-dark fs-6"><?= number_format($row['qty']) ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center">
                                                <button class="btn-action" title="Detail" onclick='viewDetail(<?= $rowJson ?>)'><i class="bi bi-eye"></i></button>
                                                <button class="btn-action" title="Edit" onclick='editDelivery(<?= $rowJson ?>)'><i class="bi bi-pencil"></i></button>
                                            </div>
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
                                        <div class="fw-bold text-dark"><?= date('d M Y', strtotime($row['logistic_date'])) ?></div>
                                    </td>
                                    <td><span class="status-badge bg-green"><i class="bi bi-check-circle-fill me-1"></i> Received</span></td>
                                    <td>
                                        <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['provider_name']) ?></div>
                                        <div class="small text-muted" style="font-size:0.75rem">Batch: <?= htmlspecialchars($row['batch_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="font-monospace text-primary fw-bold"><?= htmlspecialchars($row['provider_po']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark mb-1">Internal Warehouse</div>
                                        <div class="small text-muted" style="font-size:0.75rem">PIC: <?= htmlspecialchars($row['pic_name']) ?></div>
                                    </td>
                                    <td class="text-center fw-bold text-success fs-6">+<?= number_format($row['qty']) ?></td>
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
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            
            <div class="modal-header-custom">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-box shadow-sm">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="ms-3">
                        <h5 class="modal-title fw-bold text-dark mb-1" style="font-size: 1.15rem;">Tracking Details</h5>
                        <div class="small text-muted">Destination: <span id="trackDestSubtitle" class="fw-bold text-dark"></span></div>
                    </div>
                </div>
                <button type="button" class="btn-close mt-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0" style="background-color: #f8fafc;">
                <div id="trackingResult" class="w-100 h-100">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-3 text-muted small fw-bold">Memuat data dari kurir...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-white border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">Delivery Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4" id="detailContent"></div>
        </div>
    </div>
</div>

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
                        <div class="p-3 bg-light rounded border"><label class="small fw-bold text-muted d-block mb-1">Proof of Delivery</label><div class="row g-1"><div class="col-6"><input type="date" name="received_date" id="del_recv_date" class="form-control form-control-sm"></div><div class="col-6"><input type="text" name="receiver_name" id="del_recv_name" class="form-control form-control-sm" placeholder="Accepted By"></div></div></div>
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
        $('#table-delivery').DataTable({ dom: 't<"d-flex justify-content-between px-4 py-3 bg-white border-top"ip>', pageLength: 10, searching: false });
        $('#table-receive').DataTable({ dom: 't<"d-flex justify-content-between px-4 py-3 bg-white border-top"ip>', pageLength: 10, searching: false });
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
        $('#trackingResult').html('<div class="text-center py-5 my-5"><div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div><div class="mt-3 text-muted fw-bold">Menghubungkan ke server kurir...</div></div>');
        
        let fetchUrl = `ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`;
        
        fetch(fetchUrl)
            .then(r => r.text())
            .then(html => { 
                let $dom = $('<div>').html(html);
                
                // Smart Replacement untuk Origin & Destination
                let leafNodes = $dom.find('*:not(:has(*))');
                
                let origins = leafNodes.filter(function() { return $(this).text().trim().toUpperCase() === 'ORIGIN'; });
                if(origins.length > 1) origins.last().text('PT LinksField').addClass('text-primary fw-bold');
                else if(origins.length === 1) origins.first().text('PT LinksField').addClass('text-primary fw-bold');
                
                let dests = leafNodes.filter(function() { return $(this).text().trim().toUpperCase() === 'DESTINATION'; });
                if(dests.length > 1) dests.last().text(clientName).addClass('text-primary fw-bold');
                else if(dests.length === 1) dests.first().text(clientName).addClass('text-primary fw-bold');

                $('#trackingResult').html($dom.html());
            })
            .catch(e => { 
                $('#trackingResult').html('<div class="alert alert-danger text-center m-4 shadow-sm"><i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>Gagal terhubung ke server kurir.</div>'); 
            });
    }

    // --- DETAIL MODAL FUNCTION ---
    function viewDetail(data) {
        let html = `
            <div class="text-center mb-4 mt-2">
                <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3" style="width:60px; height:60px;">
                    <i class="bi bi-box-seam text-primary fs-3"></i>
                </div>
                <h4 class="fw-bold mb-1 text-dark">${data.tracking_number || '-'}</h4>
                <span class="badge bg-secondary text-uppercase px-3 py-1">${data.courier_name || '-'}</span>
            </div>
            <div class="bg-light p-3 rounded-3 mb-3 border">
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">SENDER</small>
                        <span class="fw-bold text-dark">PT LinksField</span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size:0.65rem;">RECEIVER</small>
                        <span class="fw-bold text-primary">${data.client_name || '-'}</span>
                    </div>
                </div>
            </div>
            <ul class="list-group list-group-flush border-top">
                <li class="list-group-item d-flex justify-content-between px-0 py-3"><span class="text-muted small">Client PO</span><span class="fw-bold text-end font-monospace">${data.client_po || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between px-0 py-3"><span class="text-muted small">Recipient Name</span><span class="fw-bold text-end">${data.receiver_name || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between px-0 py-3"><span class="text-muted small">Contact Phone</span><span class="fw-bold text-end">${data.receiver_phone || '-'}</span></li>
                <li class="list-group-item d-flex justify-content-between px-0 py-3"><span class="text-muted small">Sent Date</span><span class="fw-bold text-end">${data.delivery_date}</span></li>
                <li class="list-group-item px-0 py-3 border-bottom-0"><span class="text-muted small d-block mb-2">Delivery Address</span><div class="small bg-white border p-3 rounded-3 text-dark lh-base">${data.receiver_address || 'No address provided.'}</div></li>
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