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

// =========================================================================
// 2. CHART DATA GENERATION (DO NOT MODIFY - PRESERVED)
// =========================================================================
// Kita tetap perlu fetch detail row untuk chart chronological order
$activations_raw = [];
$terminations_raw = [];
$chart_data_act = []; 
$chart_data_term = [];

if ($db) {
    $sql_act_raw = "SELECT * FROM sim_activations ORDER BY activation_date ASC";
    $stmt = $db->query($sql_act_raw);
    if($stmt) $activations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_term_raw = "SELECT * FROM sim_terminations ORDER BY termination_date ASC";
    $stmt = $db->query($sql_term_raw);
    if($stmt) $terminations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($activations_raw as $row) {
    $d = date('Y-m-d', strtotime($row['activation_date']));
    if(!isset($chart_data_act[$d])) $chart_data_act[$d] = 0;
    $chart_data_act[$d] += (int)$row['active_qty'];
}
foreach ($terminations_raw as $row) {
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

// =========================================================================
// 3. MAIN DASHBOARD DATA (GROUPED BY PO/BATCH)
// =========================================================================
// Logic: Menggabungkan PO Provider, Client, dan Batch menjadi 1 Source of Truth
// Menghitung akumulasi Aktivasi dan Terminasi per PO.

$dashboard_data = [];
if ($db) {
    $sql_main = "SELECT 
                    po.id as po_id,
                    po.po_number as provider_po,
                    po.batch_name as batch_name,
                    po.sim_qty as total_allocation, -- TOTAL MASTER
                    client_po.po_number as client_po,
                    c.company_name,
                    p.project_name,
                    -- Hitung Total yang SUDAH Diaktivasi (Cumulative)
                    (SELECT COALESCE(SUM(active_qty + inactive_qty), 0) 
                     FROM sim_activations WHERE po_provider_id = po.id) as total_activated_cumulative,
                    
                    -- Hitung Total yang SUDAH Diterminasi (Cumulative)
                    -- Asumsi: Termination dikaitkan via activation_id atau project logic (disini kita pakai logic project/po link)
                    (SELECT COALESCE(SUM(t.terminated_qty), 0) 
                     FROM sim_terminations t 
                     JOIN sim_activations a ON t.activation_id = a.id
                     WHERE a.po_provider_id = po.id) as total_terminated_cumulative

                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider'
                ORDER BY po.id DESC";
    
    $stmt = $db->query($sql_main);
    if($stmt) $dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    /* UI SYSTEM - PROFESSIONAL GRADE */
    body { background-color: #f3f4f6; } /* Soft Gray Background */
    
    .card { border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 24px; }
    .card-header { background: #fff; border-bottom: 1px solid #f3f4f6; padding: 20px 24px; border-radius: 12px 12px 0 0 !important; }
    
    /* Table Styling */
    .table-pro { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-pro th { 
        background-color: #f9fafb; color: #6b7280; font-size: 0.75rem; 
        font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; 
        padding: 16px 20px; border-bottom: 1px solid #e5e7eb; 
    }
    .table-pro td { 
        padding: 20px; vertical-align: middle; 
        border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #1f2937; 
        background: #fff;
    }
    .table-pro tr:last-child td { border-bottom: none; }
    .table-pro tr:hover td { background-color: #f9fafb; }

    /* Column Widths */
    .col-client { width: 25%; }
    .col-source { width: 25%; }
    .col-status { width: 35%; }
    .col-action { width: 15%; text-align: center; }

    /* Hierarchy Source Display */
    .source-box { display: flex; flex-direction: column; gap: 4px; }
    .source-item { display: flex; align-items: center; font-size: 0.8rem; }
    .source-icon { width: 20px; text-align: center; margin-right: 8px; color: #9ca3af; }
    .badge-po-prov { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; padding: 2px 8px; border-radius: 4px; font-family: monospace; }
    .badge-po-cli { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; padding: 2px 8px; border-radius: 4px; font-family: monospace; }
    .badge-batch { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 4px; font-weight: 700; }

    /* Lifecycle Status Bar */
    .lifecycle-container { background: #f3f4f6; border-radius: 8px; padding: 12px; border: 1px solid #e5e7eb; }
    .lifecycle-stats { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .stat-total { color: #6b7280; }
    .stat-active { color: #059669; }
    .stat-term { color: #dc2626; }
    
    .progress-multi { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: #e5e7eb; }
    .bar-term { background-color: #ef4444; } /* Red */
    .bar-active { background-color: #10b981; } /* Green */
    .bar-empty { background-color: #d1d5db; } /* Gray */

    /* Quick Actions */
    .quick-actions { display: flex; gap: 6px; margin-top: 10px; justify-content: flex-end; }
    .btn-quick { 
        padding: 4px 10px; font-size: 0.75rem; font-weight: 600; border-radius: 6px; 
        display: flex; align-items: center; transition: all 0.2s; border: 1px solid transparent;
    }
    .btn-quick-act { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .btn-quick-act:hover { background: #059669; color: #fff; }
    .btn-quick-term { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .btn-quick-term:hover { background: #dc2626; color: #fff; }

    /* Modal Styling */
    .modal-content { border: none; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .modal-header { border-bottom: 1px solid #f3f4f6; padding: 20px 24px; }
    .modal-body { padding: 24px; }
    .form-label { font-weight: 600; color: #374151; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 6px; }
    .form-control, .form-select { border-radius: 8px; border-color: #d1d5db; padding: 10px 12px; }
    .form-control:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
</style>

<div class="page-heading mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 text-dark fw-bold">SIM Lifecycle Management</h3>
            <p class="text-muted mb-0 small">Unified Dashboard for Activation & Termination Tracking.</p>
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
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark m-0"><i class="bi bi-grid-1x2 me-2"></i> PO & Batch Status</h6>
                </div>
        </div>
        
        <div class="table-responsive">
            <table class="table-pro">
                <thead>
                    <tr>
                        <th class="col-client">Client & Project</th>
                        <th class="col-source">PO Source (Hierarchy)</th>
                        <th class="col-status">Lifecycle Status</th>
                        <th class="col-action">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No PO data found. Please input Provider PO first.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            // 1. CALCULATE LOGIC
                            $totalAllocated = (int)$row['total_allocation'];
                            $totalActivatedHist = (int)$row['total_activated_cumulative']; // History total pernah aktif
                            $totalTerminatedHist = (int)$row['total_terminated_cumulative']; // History total mati
                            
                            // REAL CURRENT STATUS
                            // Active saat ini = (Pernah Aktif) - (Sudah Mati)
                            $currentActive = $totalActivatedHist - $totalTerminatedHist;
                            if($currentActive < 0) $currentActive = 0; // Safety

                            // Sisa Kuota (Belum Aktif) = Total Alloc - Pernah Aktif
                            $remainingToActivate = $totalAllocated - $totalActivatedHist;
                            if($remainingToActivate < 0) $remainingToActivate = 0;

                            // Percentage for Bar
                            if($totalAllocated > 0) {
                                $pctTerm = ($totalTerminatedHist / $totalAllocated) * 100;
                                $pctActive = ($currentActive / $totalAllocated) * 100;
                                $pctEmpty = 100 - $pctTerm - $pctActive;
                            } else {
                                $pctTerm = 0; $pctActive = 0; $pctEmpty = 100;
                            }

                            // Data for JS
                            $rowJson = htmlspecialchars(json_encode([
                                'po_id' => $row['po_id'],
                                'po_number' => $row['provider_po'],
                                'batch_name' => $row['batch_name'],
                                'max_activate' => $remainingToActivate,
                                'max_terminate' => $currentActive,
                                'current_active' => $currentActive,
                                'total_alloc' => $totalAllocated
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark mb-1"><?= $row['company_name'] ?></div>
                                <div class="text-muted small"><i class="bi bi-folder2-open me-1"></i> <?= $row['project_name'] ?></div>
                            </td>
                            
                            <td>
                                <div class="source-box">
                                    <div class="source-item">
                                        <div class="source-icon" title="Provider PO"><i class="bi bi-box-seam"></i></div>
                                        <span class="badge-po-prov"><?= $row['provider_po'] ?></span>
                                    </div>
                                    <div class="source-item">
                                        <div class="source-icon" title="Client PO"><i class="bi bi-person-badge"></i></div>
                                        <span class="badge-po-cli"><?= $row['client_po'] ?? '-' ?></span>
                                    </div>
                                    <div class="source-item">
                                        <div class="source-icon" title="Batch"><i class="bi bi-layers"></i></div>
                                        <span class="badge-batch"><?= $row['batch_name'] ?? 'BATCH 1' ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="lifecycle-container">
                                    <div class="lifecycle-stats">
                                        <span class="stat-total" title="Total Allocation">Total: <?= number_format($totalAllocated) ?></span>
                                        <div>
                                            <span class="stat-active me-2" title="Currently Active"><i class="bi bi-circle-fill" style="font-size:6px; vertical-align:middle"></i> Active: <?= number_format($currentActive) ?></span>
                                            <span class="stat-term" title="Terminated"><i class="bi bi-circle-fill" style="font-size:6px; vertical-align:middle"></i> Term: <?= number_format($totalTerminatedHist) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-multi" title="Usage Visualization">
                                        <div class="bar-term" style="width: <?= $pctTerm ?>%"></div>
                                        <div class="bar-active" style="width: <?= $pctActive ?>%"></div>
                                        <div class="bar-empty" style="width: <?= $pctEmpty ?>%"></div>
                                    </div>

                                    <div class="quick-actions">
                                        <?php if($remainingToActivate > 0): ?>
                                            <button class="btn-quick btn-quick-act" onclick='openActionModal("activate", <?= $rowJson ?>)'>
                                                <i class="bi bi-plus-lg me-1"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if($currentActive > 0): ?>
                                            <button class="btn-quick btn-quick-term" onclick='openActionModal("terminate", <?= $rowJson ?>)'>
                                                <i class="bi bi-x-lg me-1"></i> Terminate
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td class="col-action">
                                <a href="sim_tracking_detail.php?po_id=<?= $row['po_id'] ?>" class="btn btn-sm btn-light border text-muted">
                                    <i class="bi bi-eye"></i> Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="modalAction" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content">
            <input type="hidden" name="action" id="act_form_action"> <input type="hidden" name="po_provider_id" id="act_po_id">
            
            <input type="hidden" name="batch_field" id="act_batch_name">
            <input type="hidden" name="company_id" value=""> <div class="modal-header text-white" id="act_header">
                <h6 class="modal-title fw-bold" id="act_title">Action</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-light border mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-layers-fill me-2 text-secondary"></i>
                        <strong class="text-dark" id="act_po_display">-</strong>
                    </div>
                    <div class="small text-muted" id="act_limit_display">Available: 0</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" id="act_qty_label">Quantity</label>
                    <div class="input-group">
                        <input type="number" name="qty_input" id="act_qty_input" class="form-control fw-bold" required min="1">
                        <span class="input-group-text text-muted">SIMs</span>
                    </div>
                    <div class="form-text text-danger" id="act_error_msg" style="display:none;">Exceeds limit!</div>
                </div>
            </div>
            
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn fw-bold" id="act_btn_save">Execute</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // PRESERVED CHART DATA
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;

    // --- CHART RENDER ---
    document.addEventListener('DOMContentLoaded', function () {
        if(chartLabels.length > 0){
            var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'sans-serif' },
                colors: ['#10b981', '#ef4444'], // Green & Red
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px' } } },
                dataLabels: { enabled: false }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });

    // --- ACTION MODAL LOGIC ---
    let maxLimit = 0;

    function openActionModal(type, data) {
        // Reset
        $('#act_qty_input').val('').removeClass('is-invalid');
        $('#act_error_msg').hide();
        
        // Set Context Data
        $('#act_po_id').val(data.po_id);
        $('#act_batch_name').val(data.batch_name);
        $('#act_po_display').text(data.po_number + " (" + data.batch_name + ")");

        if (type === 'activate') {
            // Setup for Activation
            $('#act_title').text('Add Activation (Start Lifecycle)');
            $('#act_header').removeClass('bg-danger').addClass('bg-success');
            $('#act_form_action').val('create_activation_simple'); // Backend need to handle this
            
            // Set Input Names for Backend
            $('#act_qty_input').attr('name', 'active_qty'); 
            
            // Logic Limit
            maxLimit = parseInt(data.max_activate);
            $('#act_limit_display').html(`Available to Activate: <b class="text-success">${maxLimit.toLocaleString()}</b> (from Total ${parseInt(data.total_alloc).toLocaleString()})`);
            
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success').text('Confirm Activation');
        } 
        else {
            // Setup for Termination
            $('#act_title').text('Add Termination (End Lifecycle)');
            $('#act_header').removeClass('bg-success').addClass('bg-danger');
            $('#act_form_action').val('create_termination_simple'); // Backend need to handle this
            
            // Set Input Names
            $('#act_qty_input').attr('name', 'terminated_qty');
            
            // Logic Limit
            maxLimit = parseInt(data.max_terminate);
            $('#act_limit_display').html(`Current Active: <b class="text-danger">${maxLimit.toLocaleString()}</b> (Available to Terminate)`);
            
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger').text('Confirm Termination');
        }

        // Validate Input on Type
        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) {
                $(this).addClass('is-invalid');
                $('#act_error_msg').text(`Max limit is ${maxLimit.toLocaleString()}`).show();
                $('#act_btn_save').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid');
                $('#act_error_msg').hide();
                $('#act_btn_save').prop('disabled', false);
            }
        });

        var myModal = new bootstrap.Modal(document.getElementById('modalAction'));
        myModal.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>