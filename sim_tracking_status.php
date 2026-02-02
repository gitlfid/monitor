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

// --- A. FETCH DATA (ACTIVATIONS) ---
$activations = [];
$terminations = [];
$chart_data_act = []; 
$chart_data_term = [];

if ($db) {
    // 1. Fetch Activations (Pastikan po_provider_id dan data PO terambil)
    $sql_act = "SELECT sa.*, 
                c.company_name, p.project_name,
                po.po_number as source_po_number, po.batch_name as source_po_batch,
                sa.po_provider_id 
                FROM sim_activations sa
                LEFT JOIN companies c ON sa.company_id = c.id
                LEFT JOIN projects p ON sa.project_id = p.id
                LEFT JOIN sim_tracking_po po ON sa.po_provider_id = po.id
                ORDER BY sa.activation_date DESC, sa.id DESC";
    $stmt = $db->query($sql_act);
    if($stmt) $activations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Terminations
    $sql_term = "SELECT st.*, 
                 c.company_name, p.project_name
                 FROM sim_terminations st
                 LEFT JOIN companies c ON st.company_id = c.id
                 LEFT JOIN projects p ON st.project_id = p.id
                 ORDER BY st.termination_date DESC, st.id DESC";
    $stmt = $db->query($sql_term);
    if($stmt) $terminations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- B. CHART DATA GENERATION ---
foreach ($activations as $row) {
    $d = date('Y-m-d', strtotime($row['activation_date']));
    if(!isset($chart_data_act[$d])) $chart_data_act[$d] = 0;
    $chart_data_act[$d] += (int)$row['active_qty'];
}
foreach ($terminations as $row) {
    $d = date('Y-m-d', strtotime($row['termination_date']));
    if(!isset($chart_data_term[$d])) $chart_data_term[$d] = 0;
    $chart_data_term[$d] += (int)$row['terminated_qty'];
}
$all_dates = array_unique(array_merge(array_keys($chart_data_act), array_keys($chart_data_term)));
sort($all_dates); 
$js_labels = []; $js_series_act = []; $js_series_term = [];
foreach ($all_dates as $dateKey) {
    $js_labels[] = date('d M Y', strtotime($dateKey));
    $js_series_act[] = $chart_data_act[$dateKey] ?? 0;
    $js_series_term[] = $chart_data_term[$dateKey] ?? 0;
}

// --- C. SYNC DATA (PO PROVIDER + REMAINING STOCK CALCULATION) ---
$clients = []; $projects = []; $provider_pos = [];
if ($db) {
    $clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $projects = $db->query("SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // HITUNG SISA STOK PO (Initial - Total Used)
    $sql_pos = "SELECT st.id, st.po_number, st.batch_name, st.sim_qty as initial_qty,
                COALESCE(linked.company_id, st.company_id) as final_company_id, 
                COALESCE(linked.project_id, st.project_id) as final_project_id,
                (SELECT COALESCE(SUM(total_sim), 0) FROM sim_activations WHERE po_provider_id = st.id) as total_used
                FROM sim_tracking_po st 
                LEFT JOIN sim_tracking_po linked ON st.link_client_po_id = linked.id
                WHERE st.type='provider' 
                GROUP BY st.id
                ORDER BY st.id DESC";
    $provider_pos = $db->query($sql_pos)->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    /* Card Styles */
    .card { border: 1px solid #eef2f6; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 20px; background: #fff; }
    .card-header { background: #fff; border-bottom: 1px solid #f1f5f9; padding: 20px 25px; border-radius: 10px 10px 0 0 !important; }
    
    /* Locked Input Style */
    .field-locked {
        background-color: #f8f9fa !important;
        pointer-events: none;
        border-color: #dee2e6;
        color: #6c757d;
        font-weight: 600;
        cursor: not-allowed;
    }
    
    /* Sync Badge */
    .sync-badge { font-size: 0.7rem; font-weight: 700; margin-top: 5px; display: inline-block; padding: 3px 8px; border-radius: 4px; }
    .sync-active { color: #0f5132; background: #d1e7dd; border: 1px solid #badbcc; } 
    
    /* Progress Bar */
    .progress-bar { transition: width 0.4s ease; font-size: 0.7rem; line-height: 14px; }
    
    /* Tables & Tabs */
    .nav-tabs { border-bottom: 2px solid #f1f5f9; }
    .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; font-size: 0.9rem; transition: 0.2s; }
    .nav-link:hover { color: #435ebe; background: #f8fafc; }
    .nav-link.active { color: #435ebe; border-bottom: 2px solid #435ebe; background: transparent; }
    .badge-batch { background-color: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; }
    
    /* Action Buttons */
    .btn-action-group .btn { padding: 4px 10px; font-size: 0.85rem; }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">Activation & Termination</h3>
            <p class="text-muted mb-0 small">Manage SIM Lifecycle Status.</p>
        </div>
    </div>
</div>

<section>
    <div class="card shadow-sm border-0">
        <div class="card-body pt-4">
            <h6 class="text-primary fw-bold mb-3 ms-2"><i class="bi bi-bar-chart-line me-2"></i>Lifecycle Analysis (All Time)</h6>
            <div id="lifecycleChart" style="height: 300px;"></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="activation-tab" data-bs-toggle="tab" data-bs-target="#activation"><i class="bi bi-check-circle-fill me-2 text-success"></i> Activation List</button></li>
                <li class="nav-item"><button class="nav-link" id="termination-tab" data-bs-toggle="tab" data-bs-target="#termination"><i class="bi bi-x-circle-fill me-2 text-danger"></i> Termination List</button></li>
            </ul>
        </div>

        <div class="card-body p-4">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="activation">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Activation Data</h6>
                        <button class="btn btn-success btn-sm px-4 fw-bold shadow-sm" onclick="openModal('act', 'create')"><i class="bi bi-plus me-1"></i> New Activation</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover w-100" id="table-activation">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3 ps-3">Date</th><th class="py-3">Client / Project</th><th class="py-3">Source Sync</th><th class="py-3">Status</th><th class="py-3 text-center">Batch</th><th class="py-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activations as $row): 
                                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                                    $src = $row['source_po_number'] ? "<span class='badge bg-info text-dark bg-opacity-25 border border-info'><i class='bi bi-link-45deg'></i> ".$row['source_po_number']."</span>" : "<span class='badge bg-light text-secondary border'>Manual</span>";
                                    
                                    // Logic Next Batch Name (Simple Increment)
                                    $nextBatchName = "BATCH 2"; // Fallback
                                    if(preg_match('/(\d+)/', $row['activation_batch'], $m)) {
                                        $nextBatchName = preg_replace('/(\d+)/', intval($m[0])+1, $row['activation_batch']);
                                    }
                                    // Pack data for "Add Next Batch" button
                                    $nextBatchData = [
                                        'po_id' => $row['po_provider_id'],
                                        'batch_name' => $nextBatchName
                                    ];
                                    $nextBatchJson = htmlspecialchars(json_encode($nextBatchData), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-secondary"><?= date('d M Y', strtotime($row['activation_date'])) ?></td>
                                    <td><div class="fw-bold text-dark"><?= $row['company_name'] ?></div><small class="text-muted"><?= $row['project_name'] ?></small></td>
                                    <td><?= $src ?></td>
                                    <td><div class="d-flex flex-column small"><span>Total: <b><?= number_format($row['total_sim']) ?></b></span><span class="text-success fw-bold">Active: <?= number_format($row['active_qty']) ?></span></div></td>
                                    <td class="text-center"><span class="badge-batch"><?= $row['activation_batch'] ?></span></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-action-group">
                                            <button class="btn btn-outline-success" onclick='openModal("act", "create", null, <?= $nextBatchJson ?>)' title="Add Next Batch"><i class="bi bi-plus-lg"></i></button>
                                            
                                            <button class="btn btn-outline-secondary" onclick='openModal("act", "update", <?= $rowJson ?>)' title="Edit"><i class="bi bi-pencil-square"></i></button>
                                            
                                            <a href="process_sim_tracking.php?action=delete_activation&id=<?= $row['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Delete?')" title="Delete"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="termination">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark m-0">Termination Data</h6>
                        <button class="btn btn-danger btn-sm px-4 fw-bold shadow-sm" onclick="openModal('term', 'create')"><i class="bi bi-plus me-1"></i> New Termination</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover w-100" id="table-termination">
                            <thead class="bg-light">
                                <tr><th class="py-3 ps-3">Date</th><th class="py-3">Client / Project</th><th class="py-3">Status</th><th class="py-3 text-center">Batch</th><th class="py-3 text-center">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($terminations as $row): $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-secondary"><?= date('d M Y', strtotime($row['termination_date'])) ?></td>
                                    <td><div class="fw-bold text-dark"><?= $row['company_name'] ?></div><small class="text-muted"><?= $row['project_name'] ?></small></td>
                                    <td><span class="text-danger fw-bold">Terminated: <?= number_format($row['terminated_qty']) ?></span></td>
                                    <td class="text-center"><span class="badge-batch"><?= $row['termination_batch'] ?></span></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-action-group">
                                            <button class="btn btn-outline-secondary" onclick='openModal("term", "update", <?= $rowJson ?>)'><i class="bi bi-pencil-square"></i></button>
                                            <a href="process_sim_tracking.php?action=delete_termination&id=<?= $row['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
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

<div class="modal fade" id="modalUniversal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <form action="process_sim_tracking.php" method="POST" id="formUniversal" class="modal-content border-0 shadow">
            <input type="hidden" name="action" id="formAction">
            <input type="hidden" name="id" id="formId">
            
            <div class="modal-header text-white py-3" id="modalHeader">
                <h6 class="modal-title m-0 fw-bold" id="modalTitle">Form</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                
                <div class="card bg-light border-0 mb-4 shadow-sm" id="div_source_po_wrapper">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-success small m-0"><i class="bi bi-link-45deg"></i> SYNC WITH PROVIDER PO</label>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="btn_reset_sync" onclick="resetSync()" style="font-size: 0.7rem;">Reset Manual</button>
                        </div>
                        
                        <select id="inp_source_po" name="po_provider_id" class="form-select border-success shadow-none" onchange="syncWithPO()">
                            <option value="">-- Select Source PO --</option>
                            <?php foreach($provider_pos as $po): 
                                $initial = (int)$po['initial_qty'];
                                $used = (int)$po['total_used'];
                                $rem = $initial - $used;
                                
                                $statusText = ($rem <= 0) ? "(Full / Used Up)" : "| Avail: " . number_format($rem);
                                $displayLabel = $po['po_number'] . " " . $statusText;
                            ?>
                                <option value="<?= $po['id'] ?>"
                                    data-batch="<?= $po['batch_name'] ?>" 
                                    data-initial="<?= $initial ?>" 
                                    data-used="<?= $used ?>"
                                    data-rem="<?= $rem ?>"
                                    data-comp="<?= $po['final_company_id'] ?>"
                                    data-proj="<?= $po['final_project_id'] ?>">
                                    <?= $displayLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="stock_indicator" class="mt-3 bg-white p-3 rounded border" style="display:none;">
                            <div class="d-flex justify-content-between small mb-1 fw-bold">
                                <span class="text-muted">PO Capacity</span>
                                <span class="text-dark" id="stock_text_top">0 / 0</span>
                            </div>
                            
                            <div class="progress mb-2" style="height: 14px; background-color: #e9ecef;">
                                <div id="bar_others" class="progress-bar bg-secondary opacity-50" role="progressbar" style="width: 0%" title="Used by Others"></div>
                                <div id="bar_this" class="progress-bar bg-primary" role="progressbar" style="width: 0%" title="Your Allocation"></div>
                                <div id="bar_free" class="progress-bar bg-success opacity-25" role="progressbar" style="width: 0%" title="Free Space"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted" style="font-size:0.7rem">
                                    <i class="bi bi-square-fill text-secondary opacity-50"></i> Used by Others &nbsp; 
                                    <i class="bi bi-square-fill text-primary"></i> This Batch &nbsp;
                                    <i class="bi bi-square-fill text-success opacity-25"></i> Free
                                </small>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark border" id="po_batch_badge">BATCH: -</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Client</label>
                        <select name="company_id" id="inp_company_id" class="form-select" required onchange="updateProjectDropdown(this.value)">
                            <option value="">-- Select Client --</option>
                            <?php foreach($clients as $c) echo "<option value='{$c['id']}'>{$c['company_name']}</option>"; ?>
                        </select>
                        <div id="status_client"></div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase">Project</label>
                        <select name="project_id" id="inp_project_id" class="form-select">
                            <option value="">-- Select Project --</option>
                        </select>
                        <div id="status_project"></div>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase">Date</label>
                        <input type="date" name="date_field" id="inp_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase">Batch Name</label>
                        <input type="text" name="batch_field" id="inp_batch" class="form-control" required placeholder="e.g., BATCH 1">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-uppercase">Total SIM</label>
                        <input type="number" name="total_sim" id="inp_total" class="form-control fw-bold" required oninput="updateReactiveBar(); calculateRemaining();">
                        <div id="status_total"></div>
                    </div>

                    <div class="col-12"><hr class="my-2 border-light"></div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-success" id="lbl_qty_1">Active Qty</label>
                        <input type="number" name="qty_1" id="inp_qty_1" class="form-control" required oninput="calculateRemaining()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-uppercase text-danger" id="lbl_qty_2">Inactive Qty</label>
                        <input type="number" name="qty_2" id="inp_qty_2" class="form-control bg-light" required readonly> 
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    const allProjects = <?php echo json_encode($projects); ?>;
    const chartLabels = <?php echo json_encode($js_labels); ?>;
    const seriesAct = <?php echo json_encode($js_series_act); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        if(chartLabels.length > 0){
            new ApexCharts(document.querySelector('#lifecycleChart'), {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'sans-serif' },
                colors: ['#198754', '#dc3545'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px' } } },
                dataLabels: { enabled: false }
            }).render();
        }
        $('#table-activation, #table-termination').DataTable({ pageLength: 10, dom: 't<"row px-4 py-3"<"col-6"i><"col-6"p>>' });
    });

    function updateProjectDropdown(companyId, selectedProjectId = null) {
        let projSelect = $('#inp_project_id');
        if(!projSelect.hasClass('field-locked')) {
            projSelect.empty().append('<option value="">-- Select Project --</option>');
        } else {
            projSelect.empty();
        }
        if (companyId) {
            let filtered = allProjects.filter(p => p.company_id == companyId);
            filtered.forEach(p => {
                let sel = (selectedProjectId && p.id == selectedProjectId) ? 'selected' : '';
                projSelect.append(`<option value="${p.id}" ${sel}>${p.project_name}</option>`);
            });
        }
    }

    // --- SYNC VARIABLES ---
    let poInitial = 0;
    let poUsedByOthers = 0; 
    let maxAllocation = 0; 
    let currentMode = 'act';
    let isEditingSync = false; 
    let savedTotalForEdit = 0; 

    function resetSync() {
        $('#inp_source_po').val('').trigger('change').prop('disabled', false); // Enable back if manual reset
        $('#btn_reset_sync').show();
    }

    // --- 1. SYNC LOGIC ---
    function syncWithPO() {
        let $sel = $('#inp_source_po option:selected');
        let poId = $('#inp_source_po').val();

        // RESET UI
        $('#stock_indicator').slideUp();
        $('#status_client, #status_project, #status_total').html('');
        $('#inp_company_id, #inp_project_id, #inp_total').removeClass('field-locked').prop('readonly', false);

        if (!poId) {
            maxAllocation = 999999999; 
            return;
        }

        // FETCH DATA
        poInitial = parseInt($sel.data('initial')) || 0;
        let dbUsed = parseInt($sel.data('used')) || 0;
        let dbRem = parseInt($sel.data('rem')) || 0;
        
        let compId = $sel.data('comp');
        let projId = $sel.data('proj');
        let batch = $sel.data('batch');

        // CALCULATE
        poUsedByOthers = dbUsed;
        if (isEditingSync) {
            poUsedByOthers = dbUsed - savedTotalForEdit;
        }
        maxAllocation = poInitial - poUsedByOthers;

        // VISUALS
        $('#stock_indicator').slideDown();
        $('#po_batch_badge').text('PO: ' + $sel.text().split('|')[0].trim());

        // LOCK FIELDS
        $('#inp_company_id').val(compId).trigger('change').addClass('field-locked');
        $('#status_client').html('<span class="sync-badge sync-active"><i class="bi bi-lock-fill"></i> Locked</span>');

        updateProjectDropdown(compId); 
        setTimeout(() => {
            $('#inp_project_id').val(projId).addClass('field-locked'); 
            $('#status_project').html('<span class="sync-badge sync-active"><i class="bi bi-lock-fill"></i> Locked</span>');
        }, 50); 

        // AUTO FILL
        if($('#inp_batch').val() === '') $('#inp_batch').val(batch);
        
        // ** DO NOT FILL TOTAL SIM ON CREATE (Allow manual input) **
        if (!isEditingSync) {
            $('#inp_total').val(''); // Clear, let user decide allocation
        }
        
        $('#status_total').html(`<span class="sync-badge sync-active"><i class="bi bi-info-circle"></i> Max Alloc: ${maxAllocation.toLocaleString()}</span>`);
        
        updateReactiveBar();
        calculateRemaining();
    }

    // --- 2. BAR UPDATE ---
    function updateReactiveBar() {
        if (!$('#inp_source_po').val()) return;

        let myInput = parseInt($('#inp_total').val()) || 0;
        let visualInput = (myInput > maxAllocation) ? maxAllocation : myInput;

        let pctOthers = (poUsedByOthers / poInitial) * 100;
        let pctMy = (visualInput / poInitial) * 100;

        $('#bar_others').css('width', pctOthers + '%');
        $('#bar_this').css('width', pctMy + '%');
        $('#bar_free').css('width', (100 - pctOthers - pctMy) + '%'); 

        $('#stock_text_top').html(
            `${poInitial.toLocaleString()} Total &bull; <span class="text-primary fw-bold">${visualInput.toLocaleString()} Allocated</span>`
        );
    }

    // --- 3. CALCULATION ---
    function calculateRemaining() {
        let totalVal = parseInt($('#inp_total').val()) || 0;
        let activeVal = parseInt($('#inp_qty_1').val()) || 0;

        if (currentMode === 'act' && maxAllocation > 0 && totalVal > maxAllocation) {
            alert(`Maximum allocation available is ${maxAllocation.toLocaleString()}`);
            $('#inp_total').val(maxAllocation);
            totalVal = maxAllocation;
            updateReactiveBar();
        }

        if (activeVal > totalVal) {
            $('#inp_qty_1').val(totalVal);
            activeVal = totalVal;
        }

        $('#inp_qty_2').val(totalVal - activeVal);
    }

    // --- 4. OPEN MODAL (IMPROVED) ---
    function openModal(type, action, data = null, preset = null) {
        currentMode = type;
        $('#formUniversal')[0].reset();
        resetSync(); 
        
        $('#div_source_po_wrapper').toggle(type === 'act'); 
        isEditingSync = false; 
        savedTotalForEdit = 0;
        poInitial = 0; poUsedByOthers = 0; maxAllocation = 0; 

        // Title Setup
        let title = (action === 'create' ? 'New ' : 'Edit ') + (type === 'act' ? 'Activation' : 'Termination');
        let color = (type === 'act' ? 'bg-success' : 'bg-danger');
        let act = (action === 'create' ? `create_${type === 'act' ? 'activation' : 'termination'}` : `update_${type === 'act' ? 'activation' : 'termination'}`);

        $('#modalTitle').text(title);
        $('#modalHeader').removeClass('bg-success bg-danger').addClass(color);
        $('#formAction').val(act);
        
        // Input Names
        if(type === 'act') {
            $('#inp_date').attr('name', 'activation_date'); $('#inp_batch').attr('name', 'activation_batch');
            $('#inp_qty_1').attr('name', 'active_qty'); $('#inp_qty_2').attr('name', 'inactive_qty');
            $('#lbl_qty_1').text('Active Qty').removeClass('text-danger').addClass('text-success'); $('#lbl_qty_2').text('Inactive Qty');
        } else {
            $('#inp_date').attr('name', 'termination_date'); $('#inp_batch').attr('name', 'termination_batch');
            $('#inp_qty_1').attr('name', 'terminated_qty'); $('#inp_qty_2').attr('name', 'unterminated_qty');
            $('#lbl_qty_1').text('Terminated Qty').removeClass('text-success').addClass('text-danger'); $('#lbl_qty_2').text('Remaining Qty');
        }

        if(data) {
            // EDIT MODE
            $('#formId').val(data.id);
            $('#inp_date').val(type === 'act' ? data.activation_date : data.termination_date);
            $('#inp_batch').val(type === 'act' ? data.activation_batch : data.termination_batch);
            $('#inp_total').val(data.total_sim);
            $('#inp_qty_1').val(type === 'act' ? data.active_qty : data.terminated_qty);
            $('#inp_qty_2').val(type === 'act' ? data.inactive_qty : data.unterminated_qty);
            
            $('#inp_company_id').val(data.company_id);
            updateProjectDropdown(data.company_id, data.project_id);

            // AUTO SYNC + STRICT LOCK
            if(type === 'act' && data.po_provider_id) {
                isEditingSync = true;
                savedTotalForEdit = parseInt(data.total_sim); 
                
                $('#inp_source_po').val(data.po_provider_id);
                // STRICT MODE: Disable dropdown so user cannot change PO
                $('#inp_source_po').prop('disabled', true);
                $('#btn_reset_sync').hide(); // Hide Reset Button on Edit

                setTimeout(() => { syncWithPO(); }, 100);
            }
        } else {
            // CREATE MODE
            $('#inp_date').val(new Date().toISOString().split('T')[0]);
            
            // PRESET LOGIC (For Add Next Batch Button)
            if(preset) {
                if(preset.batch_name) $('#inp_batch').val(preset.batch_name);
                if(preset.po_id) {
                    $('#inp_source_po').val(preset.po_id);
                    setTimeout(() => { syncWithPO(); }, 100);
                }
            }
        }

        var myModal = new bootstrap.Modal(document.getElementById('modalUniversal'));
        myModal.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>