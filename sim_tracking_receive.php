<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$current_page = 'sim_tracking_receive.php';

if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// DB CONNECTION
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }
if (!$db) die("Error DB");

// 1. FETCH LOGISTICS DATA
$logistics = [];
try {
    $sql = "SELECT l.*, 
            po.po_number, po.batch_name, po.type as po_type,
            COALESCE(c.company_name, po.manual_company_name) as company_name
            FROM sim_tracking_logistics l
            LEFT JOIN sim_tracking_po po ON l.po_id = po.id
            LEFT JOIN companies c ON po.company_id = c.id
            ORDER BY l.logistic_date DESC, l.id DESC";

    if ($db_type === 'pdo') {
        $logistics = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = mysqli_query($db, $sql);
        while ($row = mysqli_fetch_assoc($res)) $logistics[] = $row;
    }
} catch (Exception $e) {}

$data_receive = array_filter($logistics, function($item) { return $item['type'] === 'receive'; });
$data_delivery = array_filter($logistics, function($item) { return $item['type'] === 'delivery'; });

// 2. FETCH PO OPTIONS
$provider_pos = [];
$client_pos = [];
try {
    $sql_po = "SELECT po.id, po.po_number, po.batch_name, po.type,
               COALESCE(c.company_name, po.manual_company_name) as company_name
               FROM sim_tracking_po po
               LEFT JOIN companies c ON po.company_id = c.id
               ORDER BY po.id DESC";
               
    if ($db_type === 'pdo') {
        $all_pos = $db->query($sql_po)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = mysqli_query($db, $sql_po);
        $all_pos = []; while($r=mysqli_fetch_assoc($res)) $all_pos[]=$r;
    }

    foreach($all_pos as $po) {
        if($po['type'] === 'provider') $provider_pos[] = $po;
        else $client_pos[] = $po;
    }
} catch (Exception $e) {}
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6">
                <h3>Receive & Delivery</h3>
                <p class="text-subtitle text-muted">Manage Inbound and Outbound Logistics.</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="logisticsTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="receive-tab" data-bs-toggle="tab" data-bs-target="#receive" type="button">
                            <i class="bi bi-box-arrow-in-down me-2"></i> Receive (Inbound)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button">
                            <i class="bi bi-truck me-2"></i> Delivery (Outbound)
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body mt-3">
                <div class="tab-content">
                    
                    <div class="tab-pane fade show active" id="receive">
                        <div class="d-flex justify-content-between mb-3">
                            <h5 class="text-success">Inbound from Providers</h5>
                            <button class="btn btn-success" onclick="openReceiveModal()"><i class="bi bi-plus-lg"></i> Add Receive</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="table-receive">
                                <thead>
                                    <tr>
                                        <th>Date Receive</th>
                                        <th>PIC Project</th>
                                        <th>Provider (PO Link)</th>
                                        <th>Qty</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_receive as $row): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($row['logistic_date'])) ?></td>
                                        <td>
                                            <i class="bi bi-person-badge me-1 text-muted"></i> 
                                            <?= htmlspecialchars($row['pic_name'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['company_name'] ?? '-') ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['po_number']) ?></small>
                                        </td>
                                        <td><span class="badge bg-success">+ <?= number_format($row['qty']) ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick='editReceive(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                            <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="delivery">
                        <div class="d-flex justify-content-between mb-3">
                            <h5 class="text-primary">Outbound to Clients</h5>
                            <button class="btn btn-primary" onclick="openDeliveryModal()"><i class="bi bi-plus-lg"></i> Add Delivery</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="table-delivery">
                                <thead>
                                    <tr>
                                        <th>Date Delivery</th>
                                        <th>Dest. Company / Address</th>
                                        <th>PIC Contact</th>
                                        <th>Courier / Tracking</th>
                                        <th>Status</th>
                                        <th>Received By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_delivery as $row): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($row['logistic_date'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['company_name'] ?? '-') ?></strong><br>
                                            <small class="text-muted d-block text-wrap" style="max-width:200px;">
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['delivery_address'] ?? 'No Address') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($row['pic_name'] ?? '-') ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['pic_phone'] ?? '-') ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($row['courier']) ?></div>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($row['awb']) ?>" readonly>
                                                <button class="btn btn-outline-primary" type="button" onclick="trackPackage('<?= $row['courier'] ?>', '<?= $row['awb'] ?>')">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $st = strtolower($row['status'] ?? '');
                                            $badge = 'bg-secondary';
                                            if(strpos($st, 'process')!==false) $badge='bg-warning text-dark';
                                            if(strpos($st, 'shipped')!==false) $badge='bg-info text-dark';
                                            if(strpos($st, 'delivered')!==false) $badge='bg-success';
                                            ?>
                                            <span class="badge <?= $badge ?>"><?= htmlspecialchars($row['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if($row['receiver_name']): ?>
                                                <?= htmlspecialchars($row['receiver_name']) ?><br>
                                                <small class="text-muted"><?= date('d/m/y', strtotime($row['received_date'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick='editDelivery(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                                            <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
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
</div>

<div class="modal fade" id="modalReceive" tabindex="-1">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive">
            <input type="hidden" name="id" id="recv_id">

            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Inbound / Receive</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Date Receive</label>
                    <input type="date" name="logistic_date" id="recv_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>PIC Receive (Project PIC)</label>
                    <input type="text" name="pic_name" id="recv_pic" class="form-control" placeholder="Who received it?">
                </div>
                <div class="mb-3">
                    <label>Link PO Provider</label>
                    <select name="po_id" id="recv_po_id" class="form-select" required>
                        <option value="">-- Select Provider PO --</option>
                        <?php foreach($provider_pos as $po): ?>
                            <option value="<?= $po['id'] ?>"><?= $po['company_name'].' - '.$po['po_number'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Quantity</label>
                    <input type="number" name="qty" id="recv_qty" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success">Save Receive</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDelivery" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" class="modal-content">
            <input type="hidden" name="action" id="del_action" value="create_logistic">
            <input type="hidden" name="type" value="delivery">
            <input type="hidden" name="id" id="del_id">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Outbound / Delivery</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <div class="mb-3">
                            <label>Date Delivery</label>
                            <input type="date" name="logistic_date" id="del_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Link Client PO (Company)</label>
                            <select name="po_id" id="del_po_id" class="form-select" required>
                                <option value="">-- Select Client PO --</option>
                                <?php foreach($client_pos as $po): ?>
                                    <option value="<?= $po['id'] ?>"><?= $po['company_name'].' - '.$po['po_number'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecting PO auto-links the Company.</small>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label>PIC Name</label>
                                <input type="text" name="pic_name" id="del_pic" class="form-control">
                            </div>
                            <div class="col-6 mb-3">
                                <label>PIC Phone</label>
                                <input type="text" name="pic_phone" id="del_phone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Address Delivery</label>
                            <textarea name="delivery_address" id="del_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label>Courier</label>
                                <input type="text" name="courier" id="del_courier" class="form-control" placeholder="e.g. JNE">
                            </div>
                            <div class="col-6 mb-3">
                                <label>Tracking No (AWB)</label>
                                <input type="text" name="awb" id="del_awb" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Quantity Sent</label>
                            <input type="number" name="qty" id="del_qty" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label>Status Process</label>
                            <select name="status" id="del_status" class="form-select">
                                <option value="On Process">On Process</option>
                                <option value="Shipped">Shipped</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Returned">Returned</option>
                            </select>
                        </div>
                        <div class="p-3 bg-light rounded">
                            <label class="fw-bold small mb-2">Proof of Delivery (Optional)</label>
                            <div class="row">
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
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Delivery</button></div>
        </form>
    </div>
</div>

<?php $page_scripts = "
<script src='https://code.jquery.com/jquery-3.7.1.min.js'></script>
<script>
    $(document).ready(function() {
        $('#table-receive').DataTable();
        $('#table-delivery').DataTable();
    });

    // OPEN LIVE TRACKING (Generic Link)
    function trackPackage(courier, awb) {
        if(!courier || !awb) { alert('Courier or AWB missing'); return; }
        // Menggunakan aggregator cek resi umum
        let url = 'https://berdu.id/cek-resi?courier='+courier.toLowerCase()+'&resi='+awb;
        window.open(url, '_blank');
    }

    // RECEIVE FUNCTIONS
    function openReceiveModal() {
        $('#recv_action').val('create_logistic');
        $('#recv_id').val('');
        $('#recv_date').val(new Date().toISOString().split('T')[0]);
        $('#recv_pic').val('');
        $('#recv_po_id').val('');
        $('#recv_qty').val('');
        var m = new bootstrap.Modal(document.getElementById('modalReceive'));
        m.show();
    }

    function editReceive(data) {
        $('#recv_action').val('update_logistic');
        $('#recv_id').val(data.id);
        $('#recv_date').val(data.logistic_date);
        $('#recv_pic').val(data.pic_name);
        $('#recv_po_id').val(data.po_id);
        $('#recv_qty').val(data.qty);
        var m = new bootstrap.Modal(document.getElementById('modalReceive'));
        m.show();
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
        var m = new bootstrap.Modal(document.getElementById('modalDelivery'));
        m.show();
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
        var m = new bootstrap.Modal(document.getElementById('modalDelivery'));
        m.show();
    }
</script>";
require_once 'includes/footer.php'; 
?>