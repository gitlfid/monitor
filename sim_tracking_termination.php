<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
$current_page = 'sim_tracking_termination.php';
if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; require_once 'includes/sidebar.php';

// DB Connection
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }

// Fetch Data
$terminations = [];
try {
    $sql = "SELECT t.*, c.company_name, p.project_name, 
            pop.po_number as prov_po, poc.po_number as client_po
            FROM sim_terminations t
            LEFT JOIN companies c ON t.company_id = c.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN sim_tracking_po pop ON t.po_provider_id = pop.id
            LEFT JOIN sim_tracking_po poc ON t.po_client_id = poc.id
            ORDER BY t.termination_date DESC";
    if ($db_type === 'pdo') $terminations = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    else { $res = mysqli_query($db, $sql); while($r=mysqli_fetch_assoc($res)) $terminations[]=$r; }
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
    <h3>SIM Termination Management</h3>
    <p class="text-muted">Track SIM terminations / deactivations</p>
</div>

<section class="section">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4>Termination List</h4>
            <button class="btn btn-danger" onclick="openModal()"><i class="bi bi-slash-circle"></i> Add Termination</button>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover" id="table1">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Company / Project</th>
                        <th>PO Info</th>
                        <th>SIM Status</th>
                        <th>Term. Batch</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($terminations as $row): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($row['termination_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['company_name'] ?? '-') ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($row['project_name'] ?? '-') ?></small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark mb-1"><?= htmlspecialchars($row['po_batch_sim'] ?? '-') ?></span><br>
                            <small>Prov: <?= htmlspecialchars($row['prov_po'] ?? '-') ?></small><br>
                            <small>Client: <?= htmlspecialchars($row['client_po'] ?? '-') ?></small>
                        </td>
                        <td>
                            <div class="fw-bold">Total: <?= number_format($row['total_sim']) ?></div>
                            <div class="text-danger small">Terminated: <?= number_format($row['terminated_qty']) ?></div>
                            <div class="text-success small">Remaining: <?= number_format($row['unterminated_qty']) ?></div>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['termination_batch']) ?></strong><br>
                            <span class="badge bg-danger">- <?= number_format($row['termination_qty']) ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick='edit(<?= json_encode($row) ?>)'><i class="bi bi-pencil"></i></button>
                            <a href="process_sim_tracking.php?action=delete_termination&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
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
            <input type="hidden" name="action" id="action" value="create_termination">
            <input type="hidden" name="id" id="id">
            
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Termination Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-danger mb-3 border-bottom pb-2">Project & PO Info</h6>
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
                        <label>PO Batch SIM</label>
                        <input type="text" name="po_batch_sim" id="po_batch_sim" class="form-control">
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

                <h6 class="text-danger mb-3 border-bottom pb-2 mt-2">SIM Status (Auto Calc)</h6>
                <div class="row bg-light p-2 rounded">
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">Total All SIM</label>
                        <input type="number" name="total_sim" id="total_sim" class="form-control" placeholder="0" oninput="calc()">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-danger fw-bold">Total Terminated</label>
                        <input type="number" name="terminated_qty" id="terminated_qty" class="form-control" placeholder="0" oninput="calc()">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-success fw-bold">Remaining (Auto)</label>
                        <input type="number" name="unterminated_qty" id="unterminated_qty" class="form-control bg-secondary text-white" readonly>
                    </div>
                </div>

                <h6 class="text-danger mb-3 border-bottom pb-2 mt-3">Current Termination</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Termination Date</label>
                        <input type="date" name="termination_date" id="termination_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Qty Terminated Now</label>
                        <input type="number" name="termination_qty" id="termination_qty" class="form-control" placeholder="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Termination Batch Name</label>
                        <input type="text" name="termination_batch" id="termination_batch" class="form-control" placeholder="e.g. Term-001">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger">Save Data</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() { $('#table1').DataTable(); });

    function calc() {
        let total = parseInt($('#total_sim').val()) || 0;
        let term = parseInt($('#terminated_qty').val()) || 0;
        let unterminated = total - term;
        if(unterminated < 0) unterminated = 0; 
        $('#unterminated_qty').val(unterminated);
    }

    function openModal() {
        $('#action').val('create_termination'); 
        $('#id').val(''); 
        $('form')[0].reset();
        $('#termination_date').val(new Date().toISOString().split('T')[0]);
        new bootstrap.Modal(document.getElementById('modalForm')).show();
    }

    function edit(d) {
        $('#action').val('update_termination'); 
        $('#id').val(d.id);
        $('#company_id').val(d.company_id); 
        $('#project_id').val(d.project_id);
        $('#po_batch_sim').val(d.po_batch_sim); 
        $('#po_provider_id').val(d.po_provider_id); 
        $('#po_client_id').val(d.po_client_id);
        $('#total_sim').val(d.total_sim); 
        $('#terminated_qty').val(d.terminated_qty); 
        $('#unterminated_qty').val(d.unterminated_qty);
        $('#termination_date').val(d.termination_date); 
        $('#termination_qty').val(d.termination_qty); 
        $('#termination_batch').val(d.termination_batch);
        new bootstrap.Modal(document.getElementById('modalForm')).show();
    }
</script>
<?php require_once 'includes/footer.php'; ?>