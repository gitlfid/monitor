<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
$current_page = 'sim_tracking_activation.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; require_once 'includes/sidebar.php';

// Koneksi DB (Copy dari sebelumnya)
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }

// Fetch Data Activation
$activations = [];
try {
    $sql = "SELECT a.*, c.company_name, p.project_name, 
            pop.po_number as prov_po, poc.po_number as client_po
            FROM sim_activations a
            LEFT JOIN companies c ON a.company_id = c.id
            LEFT JOIN projects p ON a.project_id = p.id
            LEFT JOIN sim_tracking_po pop ON a.po_provider_id = pop.id
            LEFT JOIN sim_tracking_po poc ON a.po_client_id = poc.id
            ORDER BY a.activation_date DESC";
    if ($db_type === 'pdo') $activations = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    else { $res = mysqli_query($db, $sql); while($r=mysqli_fetch_assoc($res)) $activations[]=$r; }
} catch (Exception $e) {}

// Options
$companies = []; $projects = []; $po_prov = []; $po_cli = [];
if ($db_type === 'pdo') {
    $companies = $db->query("SELECT * FROM companies ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
    $projects = $db->query("SELECT * FROM projects ORDER BY project_name")->fetchAll(PDO::FETCH_ASSOC);
    $po_prov = $db->query("SELECT * FROM sim_tracking_po WHERE type='provider'")->fetchAll(PDO::FETCH_ASSOC);
    $po_cli = $db->query("SELECT * FROM sim_tracking_po WHERE type='client'")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="page-heading">
    <h3>SIM Activation Management</h3>
    <p class="text-muted">Track SIM activations status per Batch/PO</p>
</div>

<section class="section">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4>Activation List</h4>
            <button class="btn btn-success" onclick="openModal()"><i class="bi bi-plus-lg"></i> Add Activation</button>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover" id="table1">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Company / Project</th>
                        <th>PO Info</th>
                        <th>SIM Status (Total)</th>
                        <th>Batch Activation</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($activations as $row): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($row['activation_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['company_name'] ?? '-') ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($row['project_name'] ?? '-') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary mb-1"><?= htmlspecialchars($row['po_batch_sim'] ?? '-') ?></span><br>
                            <small>Prov: <?= htmlspecialchars($row['prov_po'] ?? '-') ?></small><br>
                            <small>Client: <?= htmlspecialchars($row['client_po'] ?? '-') ?></small>
                        </td>
                        <td>
                            <div class="fw-bold">Total: <?= number_format($row['total_sim']) ?></div>
                            <div class="text-success small">Active: <?= number_format($row['active_qty']) ?></div>
                            <div class="text-danger small">Inactive: <?= number_format($row['inactive_qty']) ?></div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['activation_batch']) ?></strong><br>
                            <span class="badge bg-success">+ <?= number_format($row['activation_qty']) ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick='edit(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                            <a href="process_sim_tracking.php?action=delete_activation&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalForm" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" class="modal-content">
            <input type="hidden" name="action" id="action" value="create_activation">
            <input type="hidden" name="id" id="id">
            
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Activation Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-success mb-3 border-bottom pb-2">Project & PO Info</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Company</label>
                        <select name="company_id" id="company_id" class="form-select" required>
                            <option value="">-- Select Company --</option>
                            <?php foreach($companies as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Project</label>
                        <select name="project_id" id="project_id" class="form-select">
                            <option value="">-- Select Project --</option>
                            <?php foreach($projects as $p) echo "<option value='{$p['id']}'>{$p['project_name']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>PO Batch Name</label>
                        <input type="text" name="po_batch_sim" id="po_batch_sim" class="form-control" placeholder="e.g. Batch Q1">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Provider PO</label>
                        <select name="po_provider_id" id="po_provider_id" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach($po_prov as $p) echo "<option value='{$p['id']}'>{$p['po_number']}</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Client PO</label>
                        <select name="po_client_id" id="po_client_id" class="form-select">
                            <option value="">-- Select --</option>
                            <?php foreach($po_cli as $p) echo "<option value='{$p['id']}'>{$p['po_number']}</option>"; ?>
                        </select>
                    </div>
                </div>

                <h6 class="text-success mb-3 border-bottom pb-2 mt-2">SIM Status (Auto Calc)</h6>
                <div class="row bg-light p-2 rounded">
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Total All SIM</label>
                        <input type="number" name="total_sim" id="total_sim" class="form-control" placeholder="0" oninput="calc()">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-success fw-bold">Total Active</label>
                        <input type="number" name="active_qty" id="active_qty" class="form-control" placeholder="0" oninput="calc()">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-danger fw-bold">Total Inactive (Auto)</label>
                        <input type="number" name="inactive_qty" id="inactive_qty" class="form-control bg-secondary text-white" readonly>
                    </div>
                </div>

                <h6 class="text-success mb-3 border-bottom pb-2 mt-3">Current Activation</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Activation Date</label>
                        <input type="date" name="activation_date" id="activation_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Qty Activated Now</label>
                        <input type="number" name="activation_qty" id="activation_qty" class="form-control" placeholder="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Activation Batch Name</label>
                        <input type="text" name="activation_batch" id="activation_batch" class="form-control" placeholder="e.g. Act-001">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save Data</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() { $('#table1').DataTable(); });

    function calc() {
        let total = parseInt($('#total_sim').val()) || 0;
        let active = parseInt($('#active_qty').val()) || 0;
        let inactive = total - active;
        if(inactive < 0) inactive = 0; 
        $('#inactive_qty').val(inactive);
    }

    function openModal() {
        $('#action').val('create_activation'); 
        $('#id').val(''); 
        $('form')[0].reset();
        $('#activation_date').val(new Date().toISOString().split('T')[0]);
        new bootstrap.Modal(document.getElementById('modalForm')).show();
    }

    function edit(d) {
        $('#action').val('update_activation'); 
        $('#id').val(d.id);
        $('#company_id').val(d.company_id); 
        $('#project_id').val(d.project_id);
        $('#po_batch_sim').val(d.po_batch_sim); 
        $('#po_provider_id').val(d.po_provider_id); 
        $('#po_client_id').val(d.po_client_id);
        $('#total_sim').val(d.total_sim); 
        $('#active_qty').val(d.active_qty); 
        $('#inactive_qty').val(d.inactive_qty);
        $('#activation_date').val(d.activation_date); 
        $('#activation_qty').val(d.activation_qty); 
        $('#activation_batch').val(d.activation_batch);
        new bootstrap.Modal(document.getElementById('modalForm')).show();
    }
</script>
<?php require_once 'includes/footer.php'; ?>