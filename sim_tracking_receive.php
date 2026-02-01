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

// GUNAKAN KONEKSI STANDAR (PDO)
$db = db_connect();

// --- A. LOGIC DATA (RECEIVE TAB - TETAP SAMA) ---
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

// --- B. LOGIC DATA (DELIVERY TAB - CLONED FROM MASTER) ---

// 1. PREPARE FILTER OPTIONS (Dropdowns)
$opt_projects = [];
$opt_couriers = [];
$opt_receivers = []; // Di tabel kita receiver = pic_name

try {
    if ($db) {
        // Get Projects/Companies from PO
        $q_proj = "SELECT DISTINCT c.company_name as project_name 
                   FROM sim_tracking_logistics l 
                   JOIN sim_tracking_po po ON l.po_id = po.id
                   JOIN companies c ON po.company_id = c.id
                   WHERE l.type='delivery' ORDER BY c.company_name ASC";
        $opt_projects = $db->query($q_proj)->fetchAll(PDO::FETCH_COLUMN);

        // Get Couriers
        $q_cour = "SELECT DISTINCT courier FROM sim_tracking_logistics WHERE type='delivery' AND courier != '' ORDER BY courier ASC";
        $opt_couriers = $db->query($q_cour)->fetchAll(PDO::FETCH_COLUMN);

        // Get Receivers (PIC)
        $q_recv = "SELECT DISTINCT pic_name FROM sim_tracking_logistics WHERE type='delivery' AND pic_name != '' ORDER BY pic_name ASC";
        $opt_receivers = $db->query($q_recv)->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}

// 2. HANDLE FILTER LOGIC
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';
$filter_receiver = $_GET['filter_receiver'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; // Base condition

if (!empty($search_track)) {
    $where_clause .= " AND (l.awb LIKE '%$search_track%' OR l.pic_name LIKE '%$search_track%')";
}
if (!empty($filter_project)) {
    $where_clause .= " AND c.company_name = '$filter_project'";
}
if (!empty($filter_courier)) {
    $where_clause .= " AND l.courier = '$filter_courier'";
}
if (!empty($filter_receiver)) {
    $where_clause .= " AND l.pic_name = '$filter_receiver'";
}

// 3. MAIN DELIVERY QUERY
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


// --- C. FETCH PO OPTIONS (FOR MODALS) ---
$provider_pos = [];
$client_pos = [];
try {
    $sql_po = "SELECT po.id, po.po_number, po.batch_name, po.type,
               COALESCE(c.company_name, po.manual_company_name) as company_name
               FROM sim_tracking_po po
               LEFT JOIN companies c ON po.company_id = c.id
               ORDER BY po.id DESC";
    if ($db) {
        $all_pos = $db->query($sql_po)->fetchAll(PDO::FETCH_ASSOC);
        foreach($all_pos as $po) {
            if($po['type'] === 'provider') $provider_pos[] = $po;
            else $client_pos[] = $po;
        }
    }
} catch (Exception $e) {}
?>

<style>
    /* STYLES FROM MASTER CLONE */
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #f8f9fa; }
    
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 24px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    /* Table Modern Style */
    .table-modern thead th {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background-color: #f8f9fa;
        color: #6c757d;
        border-bottom: 1px solid #dee2e6;
        padding: 12px 10px;
        font-weight: 700;
        white-space: nowrap;
    }
    .table-modern tbody td {
        font-size: 0.9rem;
        padding: 12px 10px;
        vertical-align: middle;
        color: #495057;
        border-bottom: 1px solid #f1f5f9;
    }
    
    /* Filter Card Style */
    .filter-card { border: none; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); margin-bottom: 20px; }
    .text-label { font-size: 0.75rem; font-weight: 700; color: #adb5bd; margin-bottom: 4px; display: block; text-transform: uppercase; }
    
    /* Badges & Text */
    .fw-bold { font-weight: 600 !important; }
    .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
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
                        <table class="table-modern" style="width:100%">
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
                                <?php foreach ($data_receive as $row): $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                <tr>
                                    <td><span class="fw-bold text-secondary"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span></td>
                                    <td><div class="fw-bold text-dark"><?= htmlspecialchars($row['pic_name'] ?? '-') ?></div></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['company_name'] ?? '-') ?></div>
                                        <div class="small text-muted">PO: <?= htmlspecialchars($row['po_number']) ?></div>
                                    </td>
                                    <td><span class="badge bg-success text-white px-3 py-1 rounded-pill">+ <?= number_format($row['qty']) ?></span></td>
                                    <td>
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
                    
                    <div class="card filter-card mb-4">
                        <div class="card-body py-3">
                            <form method="GET" action="sim_tracking_receive.php">
                                <input type="hidden" name="tab" value="delivery">
                                
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="text-label">Search Tracking</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                            <input type="text" name="search_track" class="form-control border-start-0" placeholder="Nomor Resi..." value="<?= htmlspecialchars($search_track) ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="text-label">Project / Client</label>
                                        <select name="filter_project" class="form-select form-select-sm">
                                            <option value="">- All Projects -</option>
                                            <?php foreach ($opt_projects as $p): ?>
                                                <option value="<?= $p ?>" <?= ($filter_project == $p) ? 'selected' : '' ?>><?= $p ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="text-label">Courier</label>
                                        <select name="filter_courier" class="form-select form-select-sm">
                                            <option value="">- All Couriers -</option>
                                            <?php foreach ($opt_couriers as $c): ?>
                                                <option value="<?= $c ?>" <?= ($filter_courier == $c) ? 'selected' : '' ?>><?= strtoupper($c) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="text-label">Receiver (PIC)</label>
                                        <select name="filter_receiver" class="form-select form-select-sm">
                                            <option value="">- All Receivers -</option>
                                            <?php foreach ($opt_receivers as $r): ?>
                                                <option value="<?= $r ?>" <?= ($filter_receiver == $r) ? 'selected' : '' ?>><?= $r ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Filter</button>
                                            <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier)): ?>
                                                <a href="sim_tracking_receive.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row align-items-center mb-3">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark m-0">Delivery Management</h6>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-primary shadow-sm btn-sm px-3 py-2" onclick="openDeliveryModal()">
                                <i class="bi bi-plus-lg me-2"></i> Input Delivery
                            </button>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-modern mb-0 text-nowrap">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">Sent Date</th>
                                            <th>Delivered</th>
                                            <th>Project / Client</th> 
                                            <th>Tracking Info</th>
                                            <th>Sender</th>
                                            <th>Receiver</th>
                                            <th>Item Name</th>
                                            <th>Package</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($data_delivery)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-5 text-muted">
                                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                                    Data tidak ditemukan dengan filter saat ini.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach($data_delivery as $row): 
                                                // Adjust data for view detail json
                                                $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <?= date('d M Y', strtotime($row['delivery_date'])) ?>
                                                </td>
                                                
                                                <td>
                                                    <?php if($row['delivered_date']): ?>
                                                        <div class="d-flex align-items-center text-success">
                                                            <i class="bi bi-check-circle-fill me-2"></i>
                                                            <div>
                                                                <div class="fw-bold" style="font-size:0.85rem;"><?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-secondary border">In Progress</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php if(!empty($row['company_name'])): ?>
                                                        <span class="badge bg-info text-dark bg-opacity-10 border border-info">
                                                            <i class="bi bi-kanban me-1"></i> <?= htmlspecialchars($row['company_name']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <a href="#" onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')" class="text-decoration-none fw-bold font-monospace text-primary">
                                                            <?= htmlspecialchars($row['tracking_number']) ?>
                                                        </a>
                                                        <span class="badge bg-secondary text-uppercase mt-1" style="width: fit-content; font-size: 0.65rem;">
                                                            <?= htmlspecialchars($row['courier_name']) ?>
                                                        </span>
                                                    </div>
                                                </td>

                                                <td>
                                                    <div class="fw-bold small">LinksField</div>
                                                    <div class="text-muted small text-truncate">Warehouse</div>
                                                </td>

                                                <td>
                                                    <div class="fw-bold small"><?= htmlspecialchars($row['receiver_name']) ?></div>
                                                    <div class="text-muted small text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($row['company_name']) ?>">
                                                        <?= htmlspecialchars($row['company_name']) ?>
                                                    </div>
                                                </td>
                                                
                                                <td>SIM Card</td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">Batch: <?= htmlspecialchars($row['batch_name']) ?></span>
                                                </td>
                                                <td class="text-center fw-bold"><?= number_format($row['qty']) ?></td>

                                                <td class="text-center">
                                                    <div class="btn-group shadow-sm" role="group">
                                                        <button class="btn btn-sm btn-outline-primary" title="Lacak Paket" onclick="trackResi('<?= $row['tracking_number'] ?>', '<?= $row['courier_name'] ?>')">
                                                            <i class="bi bi-geo-alt-fill"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" title="Edit Data" onclick='editDelivery(<?= $rowJson ?>)'>
                                                            <i class="bi bi-pencil-square"></i>
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
                        <?php if(!empty($data_delivery)): ?>
                        <div class="card-footer bg-white border-top py-2">
                            <small class="text-muted">Menampilkan hasil data pengiriman terbaru.</small>
                        </div>
                        <?php endif; ?>
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
            
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title m-0 fw-bold">Inbound / Receive</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Date Receive</label>
                    <input type="date" name="logistic_date" id="recv_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Link PO Provider</label>
                    <select name="po_id" id="recv_po_id" class="form-select" required>
                        <option value="">-- Select Provider PO --</option>
                        <?php foreach($provider_pos as $po): ?>
                            <option value="<?= $po['id'] ?>"><?= $po['company_name'].' - '.$po['po_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">PIC</label>
                        <input type="text" name="pic_name" id="recv_pic" class="form-control" placeholder="Name">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label small fw-bold">Quantity</label>
                        <input type="number" name="qty" id="recv_qty" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light"><button type="submit" class="btn btn-success px-4 fw-bold">Save</button></div>
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
                        <h6 class="text-primary border-bottom pb-2 mb-3">Destination</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Date Delivery</label>
                            <input type="date" name="logistic_date" id="del_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Link Client PO</label>
                            <select name="po_id" id="del_po_id" class="form-select" required>
                                <option value="">-- Select Client PO --</option>
                                <?php foreach($client_pos as $po): ?>
                                    <option value="<?= $po['id'] ?>"><?= $po['company_name'].' - '.$po['po_number'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">PIC Name</label>
                                <input type="text" name="pic_name" id="del_pic" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">PIC Phone</label>
                                <input type="text" name="pic_phone" id="del_phone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Address</label>
                            <textarea name="delivery_address" id="del_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="col-md-6 ps-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3">Shipping</h6>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">Courier</label>
                                <input type="text" name="courier" id="del_courier" class="form-control" placeholder="JNE/J&T">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-bold">AWB / Resi</label>
                                <input type="text" name="awb" id="del_awb" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Quantity</label>
                            <input type="number" name="qty" id="del_qty" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status</label>
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

<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2 text-primary"></i> Shipment Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button> 
            </div>
            <div class="modal-body bg-light" id="trackingResult">
                </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Init DataTable for Receive only. Delivery uses server-side PHP filter loop (cloned behavior)
        $('#table-receive').DataTable({ dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>', pageLength: 10 });
        
        // Check URL for tab activation
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('tab') === 'delivery' || urlParams.get('search_track')) {
            var triggerEl = document.querySelector('#logisticsTab button[data-bs-target="#delivery"]');
            bootstrap.Tab.getInstance(triggerEl).show();
        }
    });

    // OPEN MODALS
    let modalReceive, modalDelivery, modalTracking;
    document.addEventListener('DOMContentLoaded', function() {
        modalReceive = new bootstrap.Modal(document.getElementById('modalReceive'));
        modalDelivery = new bootstrap.Modal(document.getElementById('modalDelivery'));
        modalTracking = new bootstrap.Modal(document.getElementById('trackingModal'));
        
        // Auto open delivery tab if search param exists
        if(window.location.search.includes('search_track') || window.location.search.includes('filter_')) {
             var firstTabEl = document.querySelector('#logisticsTab #delivery-tab');
             var firstTab = new bootstrap.Tab(firstTabEl);
             firstTab.show();
        }
    });

    // --- CLONED TRACKING LOGIC ---
    function trackResi(resi, kurir) {
        if(!resi || !kurir) { alert('No tracking data available'); return; }
        
        modalTracking.show();
        
        // Mocking the view because we don't have ajax_track_delivery.php
        // Use external link for actual tracking + Internal simulation
        let trackingHtml = `
            <div class="p-4 bg-white rounded shadow-sm">
                <div class="d-flex justify-content-between mb-4">
                    <div>
                        <h5 class="fw-bold mb-1">${kurir.toUpperCase()}</h5>
                        <div class="text-primary fw-bold font-monospace fs-5">${resi}</div>
                    </div>
                    <div class="text-end">
                        <a href="https://berdu.id/cek-resi?courier=${kurir.toLowerCase()}&resi=${resi}" target="_blank" class="btn btn-primary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Open Provider
                        </a>
                    </div>
                </div>
                <div class="alert alert-info border-0 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                    <div>
                        <strong>Live Tracking Info</strong><br>
                        Click "Open Provider" above for real-time location details from ${kurir}.
                    </div>
                </div>
            </div>
        `;
        document.getElementById('trackingResult').innerHTML = trackingHtml;
    }

    // RECEIVE FUNCTIONS
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

    // DELIVERY FUNCTIONS
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
        $('#del_date').val(data.logistic_date); // mapped from delivery_date
        $('#del_po_id').val(data.po_id);
        $('#del_pic').val(data.pic_name); // mapped from receiver_name logic
        $('#del_phone').val(data.pic_phone);
        $('#del_address').val(data.delivery_address);
        $('#del_courier').val(data.courier_name);
        $('#del_awb').val(data.tracking_number);
        $('#del_qty').val(data.qty);
        $('#del_status').val(data.status);
        $('#del_recv_date').val(data.delivered_date);
        $('#del_recv_name').val(data.receiver_name);
        modalDelivery.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>