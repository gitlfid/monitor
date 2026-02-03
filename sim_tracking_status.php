<?php
// =========================================================================
// 1. SETUP & DATABASE CONNECTION
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

// Helper Function untuk mencegah error htmlspecialchars null
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// =========================================================================
// 2. FETCH DATA FOR DROPDOWNS (FILTERED & AUTO-LINK)
// =========================================================================
$list_providers = [];
$list_clients   = [];
$list_projects  = [];

// Arrays untuk menampung data raw history (logs)
$activations_raw = [];
$terminations_raw = [];

if ($db) {
    // A. FILTER PROVIDER PO:
    // Hanya tampilkan PO Provider yang BELUM pernah di-upload (belum ada di tabel sim_activations)
    // Sekaligus join ke Client PO untuk mendapatkan data Client/Project otomatis (Auto-Link)
    // Logika: Jika link_client_po_id ada, ambil company_id dari tabel PO client. Jika tidak, ambil dari PO provider.
    $sql_prov = "SELECT 
                    p.id, p.po_number, p.batch_name, p.sim_qty,
                    COALESCE(cpo.company_id, p.company_id) as client_comp_id, 
                    COALESCE(cpo.project_id, p.project_id) as client_proj_id  
                 FROM sim_tracking_po p 
                 LEFT JOIN sim_tracking_po cpo ON p.link_client_po_id = cpo.id
                 WHERE p.type='provider' 
                 AND p.id NOT IN (SELECT DISTINCT po_provider_id FROM sim_activations) 
                 ORDER BY p.id DESC";
    
    try {
        $stmt = $db->query($sql_prov);
        if ($stmt) $list_providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignore */ }

    // B. LIST MASTER DATA (Untuk Dropdown Client/Project)
    try {
        $list_clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $list_projects = $db->query("SELECT id, company_id, project_name FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignore */ }

    // C. FETCH RAW HISTORY (ALL DATA) UNTUK LOGS & CHART
    // Penting: Ambil semua data tanpa limit agar history log lengkap
    try {
        $sql_act_raw = "SELECT * FROM sim_activations ORDER BY activation_date DESC, id DESC";
        $stmt = $db->query($sql_act_raw);
        if($stmt) $activations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql_term_raw = "SELECT * FROM sim_terminations ORDER BY termination_date DESC, id DESC";
        $stmt = $db->query($sql_term_raw);
        if($stmt) $terminations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}

// =========================================================================
// 3. CHART DATA LOGIC GENERATION
// =========================================================================
$chart_data_act = []; 
$chart_data_term = [];
$js_labels = []; 
$js_series_act = []; 
$js_series_term = [];

// Proses Aktivasi
foreach ($activations_raw as $row) {
    $d = date('Y-m-d', strtotime($row['activation_date']));
    if(!isset($chart_data_act[$d])) $chart_data_act[$d] = 0;
    $chart_data_act[$d] += (int)$row['active_qty'];
}
// Proses Terminasi
foreach ($terminations_raw as $row) {
    $d = date('Y-m-d', strtotime($row['termination_date']));
    if(!isset($chart_data_term[$d])) $chart_data_term[$d] = 0;
    $chart_data_term[$d] += (int)$row['terminated_qty'];
}

// Gabungkan semua tanggal unik dan sort
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term)));
sort($all_dates); 

// Format untuk ApexCharts
foreach ($all_dates as $dateKey) {
    $js_labels[] = date('d M', strtotime($dateKey));
    $js_series_act[] = $chart_data_act[$dateKey] ?? 0;
    $js_series_term[] = $chart_data_term[$dateKey] ?? 0;
}

// =========================================================================
// 4. MAIN DASHBOARD DATA (GROUPED BY PO)
// =========================================================================
$dashboard_data = [];
if ($db) {
    // Query Utama: Menampilkan PO yang sudah aktif (sudah di-upload)
    // Menggunakan Subquery untuk menghitung total penggunaan (used stock) dan total terminasi
    $sql_main = "SELECT 
                    po.id as po_id,
                    po.po_number as provider_po,
                    po.batch_name as batch_name,
                    po.sim_qty as total_pool,
                    client_po.po_number as client_po,
                    c.company_name,
                    p.project_name,
                    c.id as company_id,
                    p.id as project_id,
                    
                    (SELECT COALESCE(SUM(active_qty + inactive_qty), 0) 
                     FROM sim_activations WHERE po_provider_id = po.id) as total_used_stock,
                    
                    (SELECT COALESCE(SUM(terminated_qty), 0) 
                     FROM sim_terminations WHERE po_provider_id = po.id) as total_terminated

                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider'
                -- FILTER: Hanya tampilkan yang sudah ada di tabel activation (sudah di-upload)
                HAVING po.id IN (SELECT DISTINCT po_provider_id FROM sim_activations)
                ORDER BY po.id DESC";
    
    try {
        $stmt = $db->query($sql_main);
        if($stmt) $dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { }
}
?>

<style>
    :root {
        --color-bg: #f4f6f8;
        --color-white: #ffffff;
        --color-text-main: #334155;
        --color-text-sub: #64748b;
        --color-primary: #4f46e5;
        --color-primary-hover: #4338ca;
        --color-border: #e2e8f0;
        --color-success: #10b981;
        --color-danger: #ef4444;
        --color-warning: #f59e0b;
        --color-info: #3b82f6;
    }

    body { 
        background-color: var(--color-bg); 
        font-family: 'Inter', system-ui, -apple-system, sans-serif; 
        color: var(--color-text-main); 
        font-size: 0.9rem;
    }
    
    /* CARDS & CONTAINERS */
    .card-custom { 
        border: none; 
        border-radius: 12px; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02); 
        background: var(--color-white); 
        margin-bottom: 24px; 
        transition: transform 0.2s ease, box-shadow 0.2s ease; 
    }
    .card-custom:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); 
    }
    .card-header-custom { 
        background: var(--color-white); 
        border-bottom: 1px solid var(--color-border); 
        padding: 20px 24px; 
        border-radius: 12px 12px 0 0 !important; 
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-title-custom {
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        font-size: 1rem;
    }
    
    /* TABLE STYLING */
    .table-responsive {
        border-radius: 0 0 12px 12px;
    }
    .table-pro { 
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0; 
    }
    .table-pro th { 
        background-color: #f8fafc; 
        color: #64748b; 
        font-size: 0.7rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        padding: 16px 24px; 
        border-bottom: 1px solid var(--color-border); 
    }
    .table-pro td { 
        padding: 24px; 
        vertical-align: top; 
        border-bottom: 1px solid var(--color-border); 
        background: #fff;
    }
    .table-pro tr:last-child td { border-bottom: none; }
    .table-pro tr:hover td { background-color: #fcfdfe; }

    /* TYPOGRAPHY LABELS */
    .lbl-meta { 
        font-size: 0.65rem; 
        font-weight: 700; 
        color: #94a3b8; 
        text-transform: uppercase; 
        display: block; 
        margin-bottom: 4px; 
        letter-spacing: 0.03em; 
    }
    .val-meta { 
        font-size: 0.95rem; 
        font-weight: 600; 
        color: #1e293b; 
        display: block; 
    }
    .val-sub { 
        font-size: 0.8rem; 
        color: #64748b; 
        display: flex; 
        align-items: center; 
        gap: 5px; 
    }

    /* BADGES */
    .badge-soft { 
        padding: 4px 8px; 
        border-radius: 6px; 
        font-size: 0.75rem; 
        font-weight: 600; 
        font-family: monospace; 
        display: inline-block; 
    }
    .badge-prov { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .badge-cli { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .badge-batch { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

    /* LIFECYCLE STATUS BAR */
    .status-wrapper { 
        background: #fff; 
        border-radius: 8px;
    }
    .status-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end; 
        margin-bottom: 8px; 
        font-size: 0.75rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: #64748b; 
    }
    .progress-stacked { 
        display: flex; 
        height: 10px; 
        border-radius: 5px; 
        overflow: hidden; 
        background: #e2e8f0; 
        width: 100%; 
        margin-bottom: 12px; 
    }
    .bar-seg { 
        height: 100%; 
        transition: width 0.6s ease; 
        position: relative;
    }
    
    /* Colors for Bar */
    .bg-act { background-color: var(--color-success); } 
    .bg-term { background-color: var(--color-danger); } 
    .bg-rem { background-color: #cbd5e1; } 
    
    /* Legend Dots */
    .status-legend { display: flex; gap: 16px; font-size: 0.75rem; margin-top: 8px; }
    .legend-item { display: flex; align-items: center; gap: 6px; }
    .dot { width: 8px; height: 8px; border-radius: 50%; }

    /* ACTION BUTTONS */
    .btn-action-row { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 8px; 
        margin-bottom: 8px; 
    }
    .btn-custom { 
        padding: 8px; 
        font-size: 0.75rem; 
        font-weight: 700; 
        border-radius: 8px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 6px; 
        transition: all 0.2s; 
        border: 1px solid transparent; 
        text-decoration: none; 
        cursor: pointer;
        width: 100%;
    }
    .btn-act { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .btn-act:hover { background: #059669; color: white; transform: translateY(-1px); }
    
    .btn-term { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .btn-term:hover { background: #dc2626; color: white; transform: translateY(-1px); }
    
    .btn-log { background: #fff; color: #64748b; border-color: #e2e8f0; width: 100%; }
    .btn-log:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }
    
    .btn-disabled { opacity: 0.5; pointer-events: none; filter: grayscale(1); cursor: not-allowed; }

    /* MASTER BUTTON (Top Right) */
    .btn-master { 
        background: var(--color-primary); 
        color: white; 
        border: none; 
        padding: 10px 24px; 
        border-radius: 8px; 
        font-weight: 600; 
        font-size: 0.85rem; 
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); 
        display: flex; 
        align-items: center; 
        gap: 8px; 
        transition: 0.2s; 
    }
    .btn-master:hover { 
        background: var(--color-primary-hover); 
        transform: translateY(-2px); 
        color: white; 
    }

    /* UPLOAD AREA (Drag & Drop Look) */
    .upload-zone { 
        border: 2px dashed #cbd5e1; 
        background: #f8fafc; 
        border-radius: 8px; 
        text-align: center; 
        padding: 30px; 
        position: relative; 
        cursor: pointer; 
        transition: 0.2s; 
    }
    .upload-zone:hover { 
        border-color: var(--color-primary); 
        background: #eef2ff; 
    }
    .upload-icon { 
        font-size: 2rem; 
        color: #94a3b8; 
        margin-bottom: 10px; 
    }

    /* TIMELINE (History Logs) */
    .timeline { 
        position: relative; 
        padding-left: 20px; 
        border-left: 2px solid #e2e8f0; 
        margin-left: 8px; 
    }
    .t-item { 
        position: relative; 
        margin-bottom: 20px; 
    }
    .t-dot { 
        position: absolute; 
        left: -26px; 
        top: 4px; 
        width: 14px; 
        height: 14px; 
        border-radius: 50%; 
        border: 3px solid #fff; 
        box-shadow: 0 0 0 1px #e2e8f0; 
    }
    .t-dot.act { background: var(--color-success); } 
    .t-dot.term { background: var(--color-danger); }
    
    .t-date { 
        font-size: 0.7rem; 
        color: #94a3b8; 
        font-weight: 700; 
        margin-bottom: 4px; 
        display: block; 
    }
    .t-card { 
        background: #fff; 
        padding: 12px; 
        border: 1px solid #f1f5f9; 
        border-radius: 8px; 
        box-shadow: 0 1px 2px rgba(0,0,0,0.03); 
    }
    
    /* MODAL TABS */
    .nav-tabs .nav-link { 
        font-size: 0.85rem; 
        font-weight: 600; 
        color: #64748b; 
        border: none; 
        border-bottom: 2px solid transparent; 
        padding-bottom: 10px;
    }
    .nav-tabs .nav-link.active { 
        color: var(--color-primary); 
        border-bottom-color: var(--color-primary); 
        background: transparent; 
    }
    
    /* SIM DETAIL BOX */
    .sim-detail-toggle { 
        font-size: 0.8rem; 
        font-weight: 600; 
        color: var(--color-primary); 
        text-decoration: none; 
        display: inline-flex; 
        align-items: center; 
        gap: 4px; 
        cursor: pointer;
    }
    .sim-detail-toggle:hover { text-decoration: underline; }
    .sim-detail-box { 
        background: #f8fafc; 
        padding: 15px; 
        border-radius: 8px; 
        border: 1px dashed #cbd5e1; 
        display: none; 
        margin-top: 10px; 
    }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">SIM Lifecycle Dashboard</h3>
            <p class="text-muted mb-0 small">Centralized Management for Activation & Termination Status.</p>
        </div>
        <div>
            <button class="btn-master" onclick="openMasterModal()">
                <i class="bi bi-cloud-arrow-up-fill"></i> Upload Master Data
            </button>
        </div>
    </div>
</div>

<section>
    <div class="card-custom">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Activity Trends</h6>
            <div id="lifecycleChart" style="height: 280px;"></div>
        </div>
    </div>

    <div class="card-custom">
        <div class="card-header-custom">
            <h6 class="card-title-custom"><i class="bi bi-hdd-stack me-2"></i> Active SIM Pools</h6>
        </div>
        <div class="table-responsive">
            <table class="table-pro">
                <thead>
                    <tr>
                        <th width="30%">Entity Information</th>
                        <th width="25%">Source Hierarchy</th>
                        <th width="30%">Lifecycle Status</th>
                        <th width="15%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">No active pools found. Click "Upload Master Data" to start.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            // ---------------------------------------------
                            // LOGIKA PERHITUNGAN STOK (SESUAI REQUEST)
                            // ---------------------------------------------
                            $totalPool = (int)$row['total_pool']; // Total Allocation dari PO Provider
                            $usedStock = (int)$row['total_used_stock']; // Pernah di-inject/aktivasi
                            $terminated = (int)$row['total_terminated']; // Sudah dimatikan
                            
                            // Available = Total Pool - (Semua yg pernah aktif)
                            // Ini memastikan aktivasi mengurangi stok available
                            $available = max(0, $totalPool - $usedStock);

                            // Active = (Semua yg pernah aktif) - (Yang sudah mati)
                            $active = max(0, $usedStock - $terminated);

                            // Persentase Visual Bar
                            $pctActive = ($totalPool > 0) ? ($active / $totalPool) * 100 : 0;
                            $pctTerm = ($totalPool > 0) ? ($terminated / $totalPool) * 100 : 0;
                            $pctAvail = 100 - $pctActive - $pctTerm;

                            // Data untuk Modal (Action & Logs)
                            $rowJson = htmlspecialchars(json_encode([
                                'po_id' => $row['po_id'],
                                'po_number' => $row['provider_po'],
                                'batch_name' => $row['batch_name'],
                                'company_id' => $row['company_id'],
                                'project_id' => $row['project_id'],
                                'rem_alloc' => $available,  // Batas untuk aktivasi
                                'curr_active' => $active,   // Batas untuk terminasi
                                'total_alloc' => $totalPool
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <div class="mb-3">
                                    <span class="lbl-meta">Client Name</span>
                                    <span class="val-meta"><?= e($row['company_name']) ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="lbl-meta">Client PO</span>
                                    <span class="badge-soft badge-cli"><?= e($row['client_po']) ?: 'N/A' ?></span>
                                </div>
                                <div class="mt-3">
                                    <span class="lbl-meta">Project</span>
                                    <div class="val-sub"><i class="bi bi-folder2-open text-primary me-1"></i> <?= e($row['project_name']) ?></div>
                                </div>
                            </td>

                            <td>
                                <div class="mb-3">
                                    <span class="lbl-meta">Provider Source</span>
                                    <span class="badge-soft badge-prov"><?= e($row['provider_po']) ?></span>
                                </div>
                                <div>
                                    <span class="lbl-meta">Batch ID</span>
                                    <span class="badge-soft badge-batch"><?= e($row['batch_name']) ?: 'BATCH 1' ?></span>
                                </div>
                            </td>

                            <td>
                                <div class="status-wrapper">
                                    <div class="status-header">
                                        <span>Total: <?= number_format($totalPool) ?></span>
                                        <span class="text-success">Avail: <?= number_format($available) ?></span>
                                    </div>
                                    
                                    <div class="progress-stacked">
                                        <div class="bar-seg bg-act" style="width: <?= $pctActive ?>%" title="Active: <?= $pctActive ?>%"></div>
                                        <div class="bar-seg bg-term" style="width: <?= $pctTerm ?>%" title="Terminated: <?= $pctTerm ?>%"></div>
                                        <div class="bar-seg bg-rem" style="width: <?= $pctAvail ?>%" title="Available: <?= $pctAvail ?>%"></div>
                                    </div>

                                    <div class="status-legend">
                                        <div class="legend-item">
                                            <div class="dot bg-act"></div>
                                            <div>Active: <b><?= number_format($active) ?></b></div>
                                        </div>
                                        <div class="legend-item">
                                            <div class="dot bg-term"></div>
                                            <div>Terminated: <b><?= number_format($terminated) ?></b></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="btn-action-row">
                                    <button class="btn-custom btn-act <?= ($available <= 0) ? 'btn-disabled' : '' ?>" onclick='openActionModal("activate", <?= $rowJson ?>)' title="Activate Stock">
                                        <i class="bi bi-play-fill"></i> Activate
                                    </button>
                                    
                                    <button class="btn-custom btn-term <?= ($active <= 0) ? 'btn-disabled' : '' ?>" onclick='openActionModal("terminate", <?= $rowJson ?>)' title="Terminate SIM">
                                        <i class="bi bi-stop-fill"></i> Terminate
                                    </button>
                                </div>
                                <button class="btn-custom btn-log" onclick='openDetailModal(<?= $rowJson ?>)'>
                                    <i class="bi bi-clock-history"></i> History Logs
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalMaster" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="upload_master_bulk">

            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Master Data</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6 border-end">
                        <div class="mb-3">
                            <label class="lbl-meta text-dark mb-1">1. Select Source (Provider PO)</label>
                            <select name="po_provider_id" id="inj_provider" class="form-select fw-bold border-primary" required onchange="autoFillClient(this)">
                                <option value="">-- Choose New Provider PO --</option>
                                <?php foreach($list_providers as $p): ?>
                                    <option value="<?= $p['id'] ?>" 
                                        data-comp="<?= $p['client_comp_id'] ?>" 
                                        data-proj="<?= $p['client_proj_id'] ?>"
                                        data-batch="<?= $p['batch_name'] ?>">
                                        <?= $p['po_number'] ?> (Total: <?= number_format($p['sim_qty']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted small" style="font-size:0.7rem">* Only POs not yet uploaded shown here.</div>
                        </div>

                        <div class="mb-3">
                            <label class="lbl-meta text-dark mb-1">2. Destination</label>
                            <select name="company_id" id="inj_client" class="form-select bg-light mb-2" required onchange="filterProjects(this.value)">
                                <option value="">-- Client --</option>
                                <?php foreach($list_clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="project_id" id="inj_project" class="form-select bg-light">
                                <option value="">-- Project --</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex flex-column">
                        <label class="lbl-meta text-dark mb-2">3. Upload File</label>
                        <div class="upload-zone h-100 d-flex flex-column justify-content-center">
                            <input type="file" name="upload_file" accept=".csv, .xlsx, .xls" required onchange="handleFile(this, 'fileNameDisplay')" style="position:absolute; width:100%; height:100%; top:0; left:0; opacity:0; cursor:pointer;">
                            <i class="bi bi-file-earmark-spreadsheet text-primary display-4 mb-2"></i>
                            <h6 class="fw-bold text-dark" id="fileNameDisplay">Click or Drag File Here</h6>
                            <p class="text-muted small mb-0">Accepted: .csv, .xlsx</p>
                            <div class="mt-3 badge bg-light text-dark border">Header: SN, ICCID, IMSI, MSISDN</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="lbl-meta mb-1">Batch Name</label>
                        <input type="text" name="activation_batch" id="inj_batch" class="form-control" placeholder="e.g. BATCH 1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="lbl-meta mb-1">Upload Date</label>
                        <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-light fw-bold text-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold px-4">Start Upload</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAction" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="act_form_action"> 
            <input type="hidden" name="po_provider_id" id="act_po_id">
            <input type="hidden" name="company_id" id="act_comp_id">
            <input type="hidden" name="project_id" id="act_proj_id">
            <input type="hidden" name="activation_batch" id="act_batch_name_hidden"> 
            <input type="hidden" name="termination_batch" id="term_batch_name_hidden"> 

            <div class="modal-header bg-white border-0 pb-0">
                <div>
                    <h6 class="modal-title fw-bold" id="act_title">Action</h6>
                    <div class="text-muted small" id="act_po_display">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body pt-3">
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3 py-2 px-3">
                    <span class="small fw-bold text-uppercase text-muted">Limit Status</span>
                    <span id="act_limit_display" class="fw-bold">Checking...</span>
                </div>

                <ul class="nav nav-tabs nav-fill mb-3" id="actionTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">Manual Input</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="file-tab" data-bs-toggle="tab" data-bs-target="#file" type="button" role="tab">Bulk Upload File</button>
                    </li>
                </ul>

                <div class="tab-content" id="actionTabContent">
                    <div class="tab-pane fade show active" id="manual" role="tabpanel">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="lbl-meta mb-1">Date</label>
                                <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="lbl-meta mb-1">Quantity</label>
                                <input type="number" name="qty_input" id="act_qty_input" class="form-control fw-bold text-center" placeholder="0" min="1">
                                <div class="text-danger small mt-1 fw-bold" id="act_error_msg" style="display:none;">Exceeds Limit!</div>
                            </div>
                        </div>
                        
                        <div class="text-end mb-2">
                            <a href="#" class="sim-detail-toggle" onclick="$('#sim_detail_box').slideToggle(); return false;">
                                <i class="bi bi-plus-circle"></i> Input SIM Details (Optional)
                            </a>
                        </div>
                        <div id="sim_detail_box" class="sim-detail-box">
                            <div class="mb-2">
                                <label class="lbl-meta mb-1">MSISDN <span class="text-danger">*</span></label>
                                <input type="text" name="msisdn" id="inp_msisdn" class="form-control form-control-sm" placeholder="Required if details opened">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="lbl-meta mb-1">ICCID</label>
                                    <input type="text" name="iccid" class="form-control form-control-sm" placeholder="Optional">
                                </div>
                                <div class="col-6">
                                    <label class="lbl-meta mb-1">IMSI</label>
                                    <input type="text" name="imsi" class="form-control form-control-sm" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="file" role="tabpanel">
                        <div class="upload-zone py-4">
                            <input type="file" name="action_file" accept=".csv, .xlsx, .xls" onchange="handleFile(this, 'actFileDisplay')" style="position:absolute; width:100%; height:100%; top:0; left:0; opacity:0; cursor:pointer;">
                            <i class="bi bi-cloud-arrow-up fs-3 text-secondary"></i>
                            <div class="mt-2 fw-bold small text-dark" id="actFileDisplay">Click to Upload List</div>
                            <div class="text-muted" style="font-size:0.7rem">Contains MSISDNs to process</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="submit" class="btn btn-primary fw-bold w-100" id="act_btn_save">Confirm Action</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-white border-bottom pb-3">
                <div>
                    <h6 class="modal-title fw-bold">Activity History</h6>
                    <div class="small text-muted" id="det_po_title">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" id="timeline_content"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // PREPARE DATA FROM PHP
    const projects = <?php echo json_encode($list_projects); ?>;
    const activationsRaw = <?php echo json_encode($activations_raw ?? []); ?>;
    const terminationsRaw = <?php echo json_encode($terminations_raw ?? []); ?>;
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;

    // 1. OPEN MODAL UPLOAD
    function openMasterModal() {
        var myModal = new bootstrap.Modal(document.getElementById('modalMaster'));
        myModal.show();
    }

    // 2. FILENAME DISPLAY (Universal)
    function handleFile(input, targetId) {
        if(input.files && input.files[0]) {
            let el = document.getElementById(targetId);
            el.innerText = input.files[0].name;
            el.classList.add('text-success');
        }
    }

    // 3. AUTO LINK (PO -> Client/Project)
    function autoFillClient(selectObj) {
        let opt = selectObj.options[selectObj.selectedIndex];
        let cId = opt.getAttribute('data-comp');
        let pId = opt.getAttribute('data-proj');
        let batch = opt.getAttribute('data-batch');

        // Fill Client & Trigger Filter
        if(cId) {
            document.getElementById('inj_client').value = cId;
            filterProjects(cId); 
        }
        // Fill Project (Delay needed to wait for filterProjects)
        if(pId) {
            setTimeout(() => {
                document.getElementById('inj_project').value = pId;
            }, 50);
        }
        // Fill Batch
        document.getElementById('inj_batch').value = batch || 'BATCH 1';
    }

    // Filter Project based on Client
    function filterProjects(compId) {
        let $sel = $('#inj_project');
        $sel.empty().append('<option value="">-- Select Project --</option>');
        if (compId) {
            let filtered = projects.filter(p => p.company_id == compId);
            filtered.forEach(p => {
                $sel.append(`<option value="${p.id}">${p.project_name}</option>`);
            });
        }
    }

    // 4. ACTION MODAL (MANUAL / FILE)
    let maxLimit = 0;
    function openActionModal(type, data) {
        // Reset UI
        $('#act_qty_input').val(''); 
        $('#act_error_msg').hide(); 
        $('#act_btn_save').prop('disabled', false);
        $('#sim_detail_box input').val(''); 
        $('#sim_detail_box').hide(); 
        $('#inp_msisdn').removeClass('is-invalid');
        $('#actFileDisplay').text('Click to Upload List').removeClass('text-success');
        
        // Fill Hidden Fields
        $('#act_po_id').val(data.po_id);
        $('#act_po_display').text(data.po_number);
        $('#act_comp_id').val(data.company_id);
        $('#act_proj_id').val(data.project_id);

        // Configure Type (Activate vs Terminate)
        if (type === 'activate') {
            $('#act_title').text('Activate Stock');
            $('#act_form_action').val('create_activation_simple'); 
            $('#act_qty_input').attr('name', 'active_qty'); 
            $('#act_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.rem_alloc);
            $('#act_limit_display').html(`<span class="text-success fw-bold">Available: ${maxLimit.toLocaleString()}</span>`);
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success');
        } else {
            $('#act_title').text('Terminate Stock');
            $('#act_form_action').val('create_termination_simple'); 
            $('#act_qty_input').attr('name', 'terminated_qty');
            $('#term_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.curr_active);
            $('#act_limit_display').html(`<span class="text-danger fw-bold">Active: ${maxLimit.toLocaleString()}</span>`);
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger');
        }

        // Limit Check (Manual Input Only)
        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) {
                $(this).addClass('is-invalid');
                $('#act_error_msg').show();
                $('#act_btn_save').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid');
                $('#act_error_msg').hide();
                $('#act_btn_save').prop('disabled', false);
            }
        });

        // Detail Check (Only if Manual Tab & Detail box Visible)
        $('#act_btn_save').off('click').on('click', function(e) {
            if($('#manual-tab').hasClass('active')) {
                let hasDetail = false;
                $('#sim_detail_box input').each(function(){ if($(this).val().trim()!=='') hasDetail=true; });
                
                if(hasDetail && $('#inp_msisdn').val().trim()==='') {
                    e.preventDefault();
                    $('#inp_msisdn').addClass('is-invalid');
                    if(!$('#sim_detail_box').is(':visible')) $('#sim_detail_box').slideDown();
                    alert("MSISDN is required if detail is filled!");
                }
            }
        });

        var myModal = new bootstrap.Modal(document.getElementById('modalAction'));
        myModal.show();
    }

    // 5. HISTORY LOGS (FIXED ALL RECORDS)
    function openDetailModal(data) {
        $('#det_po_title').text(data.po_number + " (" + data.batch_name + ")");
        
        // Filter Logs strictly by PO Provider ID
        let acts = activationsRaw.filter(i => i.po_provider_id == data.po_id);
        let terms = terminationsRaw.filter(i => i.po_provider_id == data.po_id);
        
        let combined = [];
        acts.forEach(i => combined.push({type:'act', date:i.activation_date, qty:i.active_qty, batch:i.activation_batch, msisdn:i.msisdn}));
        terms.forEach(i => combined.push({type:'term', date:i.termination_date, qty:i.terminated_qty, batch:i.termination_batch, msisdn:i.msisdn}));
        
        // Sort descending by date
        combined.sort((a,b) => new Date(b.date) - new Date(a.date));

        let html = '<div class="timeline">';
        if(combined.length===0) html = '<div class="text-center text-muted small py-4">No history records found.</div>';
        
        combined.forEach(log => {
            let isAct = log.type==='act';
            let dotCls = isAct ? 'act' : 'term';
            let label = isAct ? 'Activated' : 'Terminated';
            let color = isAct ? 'text-success' : 'text-danger';
            let sign = isAct ? '+' : '-';
            let detail = log.msisdn ? `<div class="small text-muted mt-1 fst-italic">MSISDN: ${log.msisdn}</div>` : '';

            html += `
            <div class="t-item">
                <div class="t-dot ${dotCls}"></div>
                <div class="t-date">${log.date}</div>
                <div class="t-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="fw-bold ${color}" style="font-size:0.9rem;">${label}</span>
                            <div class="small text-muted">${log.batch}</div>
                            ${detail}
                        </div>
                        <div class="fw-bold fs-6">${sign} ${parseInt(log.qty).toLocaleString()}</div>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        
        $('#timeline_content').html(html);
        var myModal = new bootstrap.Modal(document.getElementById('modalDetail'));
        myModal.show();
    }

    // 6. CHART RENDER
    document.addEventListener('DOMContentLoaded', function () {
        if(typeof chartLabels !== 'undefined' && chartLabels.length > 0){
             var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'Inter' },
                colors: ['#10b981', '#ef4444'], 
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels, labels:{style:{fontSize:'10px'}} },
                dataLabels: { enabled: false }, 
                grid: { borderColor: '#f1f5f9' },
                tooltip: { theme: 'light' }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>