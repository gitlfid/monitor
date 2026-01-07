<?php
// 1. Inisialisasi
ini_set('display_errors', 1);
error_reporting(E_ALL);
$current_page = 'sim_tracking_dashboard.php';

if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// 2. Koneksi Database
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }

// 3. FUNGSI HITUNG TOTAL
function getCount($db, $db_type, $sql) {
    try {
        if ($db_type === 'pdo') {
            return $db->query($sql)->fetchColumn() ?: 0;
        } else {
            $res = mysqli_query($db, $sql);
            $row = mysqli_fetch_array($res);
            return $row[0] ?: 0;
        }
    } catch (Exception $e) { return 0; }
}

// 4. HITUNG STATISTIK
// A. Total PO
$total_po_prov = getCount($db, $db_type, "SELECT COUNT(*) FROM sim_tracking_po WHERE type='provider'");
$total_po_client = getCount($db, $db_type, "SELECT COUNT(*) FROM sim_tracking_po WHERE type='client'");

// B. Stok Fisik (Received - Delivered)
$total_in = getCount($db, $db_type, "SELECT SUM(qty) FROM sim_tracking_logistics WHERE type='receive'");
$total_out = getCount($db, $db_type, "SELECT SUM(qty) FROM sim_tracking_logistics WHERE type='delivery'");
$current_stock = $total_in - $total_out;

// C. Status SIM (Active vs Terminated)
$total_active = getCount($db, $db_type, "SELECT SUM(active_qty) FROM sim_activations");
$total_terminated = getCount($db, $db_type, "SELECT SUM(terminated_qty) FROM sim_terminations");

// 5. AMBIL 5 AKTIVITAS TERAKHIR (LOGISTICS)
$recent_logs = [];
try {
    $sql_log = "SELECT l.*, po.po_number, COALESCE(c.company_name, po.manual_company_name) as company_name 
                FROM sim_tracking_logistics l
                LEFT JOIN sim_tracking_po po ON l.po_id = po.id
                LEFT JOIN companies c ON po.company_id = c.id
                ORDER BY l.created_at DESC LIMIT 5";
    if ($db_type === 'pdo') $recent_logs = $db->query($sql_log)->fetchAll(PDO::FETCH_ASSOC);
    else { $res = mysqli_query($db, $sql_log); while($r=mysqli_fetch_assoc($res)) $recent_logs[]=$r; }
} catch (Exception $e) {}
?>

<div class="page-heading">
    <h3>SIM Tracking Dashboard</h3>
    <p class="text-subtitle text-muted">Overview of your SIM inventory and status.</p>
</div>

<section class="section">
    <div class="row">
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon purple"><i class="bi bi-file-earmark-text-fill"></i></div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total PO Provider</h6>
                            <h6 class="font-extrabold mb-0"><?= number_format($total_po_prov) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon blue"><i class="bi bi-file-earmark-person-fill"></i></div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total PO Client</h6>
                            <h6 class="font-extrabold mb-0"><?= number_format($total_po_client) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon green"><i class="bi bi-box-seam"></i></div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Current Stock (In-Out)</h6>
                            <h6 class="font-extrabold mb-0 text-success"><?= number_format($current_stock) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-icon red"><i class="bi bi-truck"></i></div>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Delivered</h6>
                            <h6 class="font-extrabold mb-0"><?= number_format($total_out) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-md-6">
            <div class="card bg-light-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?= number_format($total_active) ?></h3>
                    <span class="fw-bold">Total SIM Activated</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card bg-light-danger">
                <div class="card-body text-center">
                    <h3 class="text-danger"><?= number_format($total_terminated) ?></h3>
                    <span class="fw-bold">Total SIM Terminated</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Recent Logistics Activity</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>PO / Company</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recent_logs)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No data yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach($recent_logs as $log): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($log['logistic_date'])) ?></td>
                                        <td>
                                            <?php if($log['type'] == 'receive'): ?>
                                                <span class="badge bg-success">Inbound</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Outbound</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($log['po_number']) ?></strong><br>
                                            <small><?= htmlspecialchars($log['company_name'] ?? '-') ?></small>
                                        </td>
                                        <td><?= number_format($log['qty']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($log['status']) ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="sim_tracking_receive.php" class="btn btn-outline-primary">View All Logistics</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>