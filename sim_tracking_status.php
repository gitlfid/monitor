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
// 2. FETCH RAW DATA (UNTUK CHART & TIMELINE MODAL)
// =========================================================================
$activations_raw = [];
$terminations_raw = [];
$chart_data_act = []; 
$chart_data_term = [];

if ($db) {
    // Ambil semua data raw untuk keperluan chart & history log
    $sql_act_raw = "SELECT * FROM sim_activations ORDER BY activation_date DESC, id DESC";
    $stmt = $db->query($sql_act_raw);
    if($stmt) $activations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_term_raw = "SELECT * FROM sim_terminations ORDER BY termination_date DESC, id DESC";
    $stmt = $db->query($sql_term_raw);
    if($stmt) $terminations_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Chart Logic (Preserved)
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
// 3. MAIN DASHBOARD DATA (GROUPED BY PO) - THE FIX
// =========================================================================
$dashboard_data = [];
if ($db) {
    // FIX: Menggunakan 'po_provider_id' untuk join ke sim_terminations
    // Ini memperbaiki error "Unknown column activation_id"
    
    $sql_main = "SELECT 
                    po.id as po_id,
                    po.po_number as provider_po,
                    po.batch_name as batch_name,
                    po.sim_qty as total_allocation,
                    client_po.po_number as client_po,
                    c.company_name,
                    p.project_name,
                    c.id as company_id,
                    p.id as project_id,
                    
                    -- Total Pernah Aktif (Kumulatif)
                    (SELECT COALESCE(SUM(active_qty + inactive_qty), 0) 
                     FROM sim_activations WHERE po_provider_id = po.id) as total_activated_hist,
                    
                    -- Total Sudah Mati (Terminated) - FIX QUERY
                    (SELECT COALESCE(SUM(terminated_qty), 0) 
                     FROM sim_terminations 
                     WHERE po_provider_id = po.id) as total_terminated_hist

                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider'
                ORDER BY po.id DESC";
    
    try {
        $stmt = $db->query($sql_main);
        if($stmt) $dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback warning jika ada masalah database lain
        echo "<div class='alert alert-danger m-3'>Database Error: " . $e->getMessage() . "</div>";
    }
}
?>

<style>
    /* PROFESSIONAL UI SYSTEM */
    body { background-color: #f3f4f6; font-family: 'Inter', system-ui, sans-serif; }
    
    .card { border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 24px; }
    .card-header { background: #fff; border-bottom: 1px solid #f3f4f6; padding: 20px 24px; border-radius: 12px 12px 0 0 !important; }
    
    .table-pro { width: 100%; border-collapse: separate; border-spacing: 0; }
    .table-pro th { background-color: #f9fafb; color: #6b7280; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 16px 20px; border-bottom: 1px solid #e5e7eb; }
    .table-pro td { padding: 20px; vertical-align: middle; border-bottom: 1px solid #f3f4f6; font-size: 0.9rem; color: #1f2937; background: #fff; }
    .table-pro tr:hover td { background-color: #f9fafb; }

    /* Badges & Icons */
    .source-box { display: flex; flex-direction: column; gap: 6px; }
    .source-item { display: flex; align-items: center; font-size: 0.8rem; }
    .source-icon { width: 24px; text-align: center; margin-right: 8px; color: #9ca3af; }
    .badge-po-prov { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; padding: 3px 8px; border-radius: 6px; font-family: monospace; font-weight: 600; }
    .badge-po-cli { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; padding: 3px 8px; border-radius: 6px; font-family: monospace; }
    .badge-batch { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 6px; font-weight: 700; }

    /* Lifecycle Status Bar */
    .lifecycle-container { background: #fff; border-radius: 8px; }
    .lifecycle-stats { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
    .progress-multi { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: #f3f4f6; border: 1px solid #e5e7eb; margin-bottom: 12px; }
    .bar-term { background-color: #ef4444; }
    .bar-active { background-color: #10b981; }
    .bar-empty { background-color: #e5e7eb; }

    /* Quick Actions */
    .btn-quick { padding: 6px 12px; font-size: 0.75rem; font-weight: 700; border-radius: 6px; display: inline-flex; align-items: center; transition: all 0.2s; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
    .btn-quick-act { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .btn-quick-act:hover { background: #059669; color: #fff; border-color: #059669; }
    .btn-quick-term { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .btn-quick-term:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
    .btn-quick.disabled { opacity: 0.5; pointer-events: none; filter: grayscale(100%); }

    /* Timeline */
    .timeline-box { position: relative; padding-left: 20px; border-left: 2px solid #e5e7eb; margin-left: 10px; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before { content: ''; position: absolute; left: -26px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 2px solid #9ca3af; }
    .timeline-item.act::before { border-color: #10b981; background: #10b981; }
    .timeline-item.term::before { border-color: #ef4444; background: #ef4444; }
    .timeline-date { font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 4px; display: block; }
    .timeline-card { background: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #f3f4f6; }
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
                        <th width="25%">Client & Project</th>
                        <th width="25%">PO Source (Hierarchy)</th>
                        <th width="35%">Lifecycle Status</th>
                        <th width="15%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($dashboard_data)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No PO data found. Please input Provider PO first.</td></tr>
                    <?php else: ?>
                        <?php foreach($dashboard_data as $row): 
                            // 1. HITUNGAN REAL-TIME
                            $totalAllocated = (int)$row['total_allocation'];
                            $totalActivatedHist = (int)$row['total_activated_hist']; 
                            $totalTerminatedHist = (int)$row['total_terminated_hist']; 
                            
                            // Active Saat Ini = (Total Yg Pernah Aktif) - (Total Yg Sudah Mati)
                            $currentActive = $totalActivatedHist - $totalTerminatedHist;
                            if($currentActive < 0) $currentActive = 0;

                            // Sisa Kuota PO = Total Alloc - Total Yg Pernah Aktif
                            // (Logika: PO yg sudah diaktifkan sekali, kuotanya terpakai, meskipun nanti mati)
                            $remainingToActivate = $totalAllocated - $totalActivatedHist;
                            if($remainingToActivate < 0) $remainingToActivate = 0;

                            // Persentase Bar Visual
                            if($totalAllocated > 0) {
                                $pctTerm = ($totalTerminatedHist / $totalAllocated) * 100;
                                $pctActive = ($currentActive / $totalAllocated) * 100;
                                $pctEmpty = 100 - $pctTerm - $pctActive;
                            } else {
                                $pctTerm = 0; $pctActive = 0; $pctEmpty = 100;
                            }

                            // Data JSON untuk Modal (Action & Detail)
                            $rowJson = htmlspecialchars(json_encode([
                                'po_id' => $row['po_id'],
                                'po_number' => $row['provider_po'],
                                'batch_name' => $row['batch_name'],
                                'company_id' => $row['company_id'],
                                'project_id' => $row['project_id'],
                                'max_activate' => $remainingToActivate,
                                'max_terminate' => $currentActive,
                                'current_active' => $currentActive,
                                'total_alloc' => $totalAllocated,
                                'company_name' => $row['company_name'],
                                'project_name' => $row['project_name']
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['company_name']) ?></div>
                                <div class="text-muted small"><i class="bi bi-folder2-open me-1"></i> <?= htmlspecialchars($row['project_name']) ?></div>
                            </td>
                            
                            <td>
                                <div class="source-box">
                                    <div class="source-item">
                                        <div class="source-icon" title="Provider PO"><i class="bi bi-box-seam"></i></div>
                                        <span class="badge-po-prov"><?= htmlspecialchars($row['provider_po']) ?></span>
                                    </div>
                                    <div class="source-item">
                                        <div class="source-icon" title="Client PO"><i class="bi bi-person-badge"></i></div>
                                        <span class="badge-po-cli"><?= htmlspecialchars($row['client_po'] ?? '-') ?></span>
                                    </div>
                                    <div class="source-item">
                                        <div class="source-icon" title="Batch"><i class="bi bi-layers"></i></div>
                                        <span class="badge-batch"><?= htmlspecialchars($row['batch_name'] ?? 'BATCH 1') ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="lifecycle-container">
                                    <div class="lifecycle-stats">
                                        <span class="text-muted small">Total: <span class="text-dark fw-bold"><?= number_format($totalAllocated) ?></span></span>
                                        <div>
                                            <span class="text-success me-2" style="font-size:0.75rem">Active: <b><?= number_format($currentActive) ?></b></span>
                                            <span class="text-danger" style="font-size:0.75rem">Term: <b><?= number_format($totalTerminatedHist) ?></b></span>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-multi" title="Usage Visualization">
                                        <div class="bar-term" style="width: <?= $pctTerm ?>%" title="Terminated"></div>
                                        <div class="bar-active" style="width: <?= $pctActive ?>%" title="Active"></div>
                                        <div class="bar-empty" style="width: <?= $pctEmpty ?>%" title="Available"></div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn-quick btn-quick-act <?= ($remainingToActivate <= 0) ? 'disabled' : '' ?>" 
                                                onclick='openActionModal("activate", <?= $rowJson ?>)' 
                                                title="Add New Activation">
                                            <i class="bi bi-plus-lg me-1"></i> Activate
                                        </button>
                                        
                                        <button class="btn-quick btn-quick-term <?= ($currentActive <= 0) ? 'disabled' : '' ?>" 
                                                onclick='openActionModal("terminate", <?= $rowJson ?>)'
                                                title="Terminate Active SIMs">
                                            <i class="bi bi-x-lg me-1"></i> Terminate
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                <button class="btn btn-light btn-sm border text-muted fw-bold" onclick='openDetailModal(<?= $rowJson ?>)'>
                                    <i class="bi bi-list-ul me-1"></i> Logs
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

<div class="modal fade" id="modalAction" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <form action="process_sim_tracking.php" method="POST" class="modal-content border-0">
            <input type="hidden" name="action" id="act_form_action"> 
            <input type="hidden" name="po_provider_id" id="act_po_id">
            <input type="hidden" name="company_id" id="act_comp_id">
            <input type="hidden" name="project_id" id="act_proj_id">
            
            <input type="hidden" name="activation_batch" id="act_batch_name_hidden"> 
            <input type="hidden" name="termination_batch" id="term_batch_name_hidden"> 

            <div class="modal-header text-white" id="act_header">
                <h6 class="modal-title fw-bold" id="act_title">Action</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-layers-fill me-2 text-secondary"></i>
                        <strong class="text-dark" id="act_po_display">-</strong>
                    </div>
                    <div class="small text-muted" id="act_limit_display">Checking limits...</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Transaction Date</label>
                    <input type="date" name="date_field" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small" id="act_qty_label">Quantity</label>
                    <div class="input-group">
                        <input type="number" name="qty_input" id="act_qty_input" class="form-control fw-bold" required min="1" placeholder="0">
                        <span class="input-group-text text-muted">SIMs</span>
                    </div>
                    <div class="form-text text-danger fw-bold small mt-1" id="act_error_msg" style="display:none;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Cannot exceed limit!
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-light text-muted fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn fw-bold px-4" id="act_btn_save">Confirm</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header border-bottom bg-white">
                <h6 class="modal-title fw-bold text-dark">History Logs</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="mb-4">
                    <h5 class="fw-bold mb-1" id="det_po">-</h5>
                    <p class="text-muted small m-0" id="det_client">-</p>
                </div>
                
                <h6 class="text-uppercase text-muted fw-bold small mb-3">Activity Timeline</h6>
                <div class="timeline-box" id="timeline_content">
                    </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    // LOAD DATA RAW DARI PHP
    const activationsRaw = <?php echo json_encode($activations_raw ?? []); ?>;
    const terminationsRaw = <?php echo json_encode($terminations_raw ?? []); ?>;
    
    // Chart Data
    const chartLabels = <?php echo json_encode($js_labels ?? []); ?>;
    const seriesAct = <?php echo json_encode($js_series_act ?? []); ?>;
    const seriesTerm = <?php echo json_encode($js_series_term ?? []); ?>;

    // --- CHART RENDER ---
    document.addEventListener('DOMContentLoaded', function () {
        if(chartLabels.length > 0){
            var options = {
                series: [{ name: 'Activations', data: seriesAct }, { name: 'Terminations', data: seriesTerm }],
                chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                colors: ['#10b981', '#ef4444'], 
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: chartLabels, labels: { style: { fontSize: '11px' } } },
                dataLabels: { enabled: false }
            };
            new ApexCharts(document.querySelector('#lifecycleChart'), options).render();
        }
    });

    // --- ACTION MODAL ---
    let maxLimit = 0;

    function openActionModal(type, data) {
        // Reset Inputs
        $('#act_qty_input').val('').removeClass('is-invalid');
        $('#act_error_msg').hide();
        $('#act_btn_save').prop('disabled', false);
        
        // Fill Hidden Fields
        $('#act_po_id').val(data.po_id);
        $('#act_comp_id').val(data.company_id);
        $('#act_proj_id').val(data.project_id);
        
        // UI Display
        $('#act_po_display').text(data.po_number + " (" + data.batch_name + ")");
        
        if (type === 'activate') {
            $('#act_title').text('New Activation');
            $('#act_header').removeClass('bg-danger').addClass('bg-success');
            $('#act_form_action').val('create_activation_simple'); 
            
            // Name input -> active_qty (sesuai db structure activation)
            $('#act_qty_input').attr('name', 'active_qty'); 
            $('#act_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.max_activate);
            $('#act_limit_display').html(`Available: <b class="text-success">${maxLimit.toLocaleString()}</b> (of ${parseInt(data.total_alloc).toLocaleString()})`);
            
            $('#act_btn_save').removeClass('btn-danger').addClass('btn-success').text('Process Activation');
        } 
        else {
            $('#act_title').text('New Termination');
            $('#act_header').removeClass('bg-success').addClass('bg-danger');
            $('#act_form_action').val('create_termination_simple'); 
            
            // Name input -> terminated_qty (sesuai db structure termination)
            $('#act_qty_input').attr('name', 'terminated_qty');
            $('#term_batch_name_hidden').val(data.batch_name); 
            
            maxLimit = parseInt(data.current_active);
            $('#act_limit_display').html(`Active SIMs: <b class="text-danger">${maxLimit.toLocaleString()}</b> (Ready to Terminate)`);
            
            $('#act_btn_save').removeClass('btn-success').addClass('btn-danger').text('Process Termination');
        }

        // Input Validation (Client Side)
        $('#act_qty_input').off('input').on('input', function() {
            let val = parseInt($(this).val()) || 0;
            if (val > maxLimit) {
                $(this).addClass('is-invalid');
                $('#act_error_msg').text(`Limit exceeded! Max: ${maxLimit.toLocaleString()}`).show();
                $('#act_btn_save').prop('disabled', true);
            } else if (val <= 0) {
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

    // --- DETAIL TIMELINE MODAL ---
    function openDetailModal(data) {
        $('#det_po').text(data.po_number);
        $('#det_client').text(data.company_name + " / " + data.batch_name);
        
        // Filter Data by PO ID
        // Note: terminations_raw juga harus punya po_provider_id
        let acts = activationsRaw.filter(item => item.po_provider_id == data.po_id);
        let terms = terminationsRaw.filter(item => item.po_provider_id == data.po_id);
        
        // Gabungkan dan Sortir berdasarkan Tanggal
        let combined = [];
        
        acts.forEach(item => {
            combined.push({
                type: 'act',
                date: item.activation_date,
                qty: item.active_qty,
                batch: item.activation_batch
            });
        });
        
        terms.forEach(item => {
            combined.push({
                type: 'term',
                date: item.termination_date,
                qty: item.terminated_qty,
                batch: item.termination_batch
            });
        });
        
        // Sort Descending (Terbaru diatas)
        combined.sort((a, b) => new Date(b.date) - new Date(a.date));
        
        let html = '';
        if(combined.length === 0) {
            html = '<div class="text-center text-muted py-3">No activity logs found.</div>';
        } else {
            combined.forEach(log => {
                let isAct = log.type === 'act';
                let colorClass = isAct ? 'act' : 'term';
                let badgeClass = isAct ? 'bg-success' : 'bg-danger';
                let label = isAct ? 'Activation' : 'Termination';
                let sign = isAct ? '+' : '-';
                
                html += `
                <div class="timeline-item ${colorClass}">
                    <span class="timeline-date">${log.date}</span>
                    <div class="timeline-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">${label}</span>
                            <span class="badge ${badgeClass}">${sign} ${parseInt(log.qty).toLocaleString()}</span>
                        </div>
                        <div class="small text-muted mt-1">Batch: ${log.batch}</div>
                    </div>
                </div>`;
            });
        }
        
        $('#timeline_content').html(html);
        var myModal = new bootstrap.Modal(document.getElementById('modalDetail'));
        myModal.show();
    }
</script>

<?php require_once 'includes/footer.php'; ?>