<?php
/*
 File: index.php
 ===========================================================
 Status: FIXED (Anti-Blank, Robust Chart Loading) + Multi-tenant Filter
*/

require_once 'includes/auth_check.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
// Sidebar otomatis membuka layout & header. Langsung isi konten.

$db = db_connect();

// --- LOGIKA FILTER USER (MULTI-TENANT) ---
$user_company_id = $_SESSION['company_id'] ?? null;

try {
    if ($user_company_id) {
        // Jika User Terbatas: Hanya ambil perusahaan miliknya
        $companies_stmt = $db->prepare("SELECT id, company_name FROM companies WHERE id = ? ORDER BY company_name ASC");
        $companies_stmt->execute([$user_company_id]);
    } else {
        // Jika Admin (Global): Ambil semua perusahaan
        $companies_stmt = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
    }
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $companies = [];
}
?>

<style>
    /* CSS FIXES */
    .form-group { position: relative; } 
    .form-select { cursor: pointer; position: relative; }
    
    /* Grafik Layout */
    .chart-wrapper-donut { position: relative; height: 200px !important; width: 100%; }
    .chart-wrapper-bar { position: relative; height: 150px !important; width: 100%; }
    canvas { display: block; width: 100%; }
    
    /* Stats & Cards */
    .stats-box { padding: 15px; border-radius: 8px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; background-color: #f8f9fa; transition: transform 0.2s; }
    .stats-box:hover { transform: translateY(-2px); }
    .stats-box h6 { font-size: 0.75rem; text-transform: uppercase; color: #6c757d; font-weight: 700; margin-bottom: 5px; }
    .stats-box h3 { font-size: 1.25rem; font-weight: 800; margin-bottom: 0; color: #212529; }

    .bg-light-success { background-color: #e8f5e9 !important; }
    .bg-light-danger { background-color: #ffebee !important; }
    .bg-light-primary { background-color: #e3f2fd !important; }
    .text-success-dark { color: #1b5e20 !important; }
    .text-danger-dark { color: #b71c1c !important; }
    .text-primary-dark { color: #0d47a1 !important; }

    /* Rows */
    .stat-row { border-bottom: 1px solid #eee; padding: 8px 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
    .stat-row:last-child { border-bottom: none; }
    .stat-label { color: #6c757d; }
    .stat-value { font-weight: 600; color: #333; }
    
    .card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-radius: 10px; margin-bottom: 1.5rem; }
    
    /* Dark Mode */
    body.theme-dark .stats-box { background-color: #1e1e2d; }
    body.theme-dark .stat-value, body.theme-dark .stats-box h3 { color: #fff !important; }
</style>

<div class="page-heading">
    <h3>Datapool Monitoring Dashboard</h3>
</div>

<div class="page-content">
    
    <section class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-3 mb-md-0">
                            <label for="filter_company_id" class="form-label fw-bold">Filter Company</label>
                            <select class="form-select" id="filter_company_id">
                                <?php if (!$user_company_id): ?>
                                    <option value="">-- All Company --</option>
                                <?php endif; ?>

                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" <?php echo ($user_company_id == $company['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5 mb-3 mb-md-0">
                            <label for="filter_project_id" class="form-label fw-bold">Filter Project</label>
                            <select class="form-select" id="filter_project_id" disabled>
                                <option value="">-- All Project --</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary w-100" id="reset_filter">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row" id="datapool-container"></div>

    <div class="row" id="datapool-loader">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
            <p class="mt-3 text-muted fs-5">Loading Data...</p>
        </div>
    </div>

    <template id="template-card-summary">
        <div class="col-12 mb-4">
            <div class="card h-100">
                <div class="card-header border-bottom pb-2 pt-3"><h5 class="card-title">All Datapool Summary</h5></div>
                <div class="card-body pt-4">
                    <div class="row">
                        <div class="col-lg-4 col-md-12 d-flex flex-column align-items-center justify-content-center border-end-lg mb-4 mb-lg-0">
                            <div class="chart-wrapper-donut"><canvas id="{CANVAS_ID}"></canvas></div>
                            <div class="text-center mt-2 small">
                                <span class="badge bg-success me-1">Remaining</span>
                                <span class="badge bg-danger">Used</span>
                            </div>
                        </div>
                        <div class="col-lg-8 col-md-12 ps-lg-4">
                            <h6 class="mb-3 text-muted">Total Data All Project</h6>
                            <div class="row mb-4">
                                <div class="col-md-4 col-12 mb-2"><div class="stats-box bg-light-success"><h6>TOTAL REMAINING</h6><h3 class="text-success-dark" data-id="sisa-saldo">{SISA_SALDO}</h3></div></div>
                                <div class="col-md-4 col-12 mb-2"><div class="stats-box bg-light-danger"><h6>TOTAL USED</h6><h3 class="text-danger-dark" data-id="total-terpakai">{TOTAL_TERPAKAI}</h3></div></div>
                                <div class="col-md-4 col-12 mb-2"><div class="stats-box bg-light-primary"><h6>TOTAL QUOTA</h6><h3 class="text-primary-dark" data-id="total-kuota">{TOTAL_KUOTA}</h3></div></div>
                            </div>
                            <div class="mb-4"><h6 class="text-center text-muted mb-2 small">Quota Summary (GB)</h6><div class="chart-wrapper-bar"><canvas id="{BAR_CANVAS_ID}"></canvas></div></div>
                            <div class="row text-center border-top pt-3">
                                <div class="col-4"><small class="text-muted d-block">COMPANIES</small><span class="fw-bold fs-5" data-id="company-count">{COMPANY_COUNT}</span></div>
                                <div class="col-4 border-start border-end"><small class="text-muted d-block">PROJECTS</small><span class="fw-bold fs-5" data-id="project-count">{PROJECT_COUNT}</span></div>
                                <div class="col-4"><small class="text-muted d-block">SUCCESS INJECT</small><span class="fw-bold fs-5 text-info" data-id="total-injecksi">{TOTAL_INJEKSI}</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template id="template-card-success">
        <div class="col-lg-6 col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center" 
                     data-id="project-name" data-project-id="{PROJECT_ID}" 
                     style="cursor: pointer;" title="Click for Details">
                    <h6 class="card-title mb-0 text-primary fw-bold"><i class="bi bi-folder2-open me-2"></i>{PROJECT_NAME}</h6>
                    <i class="bi bi-calendar-week text-muted"></i>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-sm-5 text-center mb-3 mb-sm-0">
                            <div class="chart-wrapper-donut" style="height: 160px !important;"><canvas id="{CANVAS_ID}"></canvas></div>
                            <div class="mt-2 small text-muted fw-bold text-truncate">{PACKAGE_NAME}</div>
                        </div>
                        <div class="col-sm-7">
                            <div class="stat-row"><span class="stat-label">Remaining</span><span class="stat-value text-success" data-id="sisa-saldo">{SISA_SALDO}</span></div>
                            <div class="stat-row"><span class="stat-label">Used</span><span class="stat-value text-danger" data-id="total-terpakai">{TOTAL_TERPAKAI}</span></div>
                            <div class="stat-row"><span class="stat-label">Success Inject</span><span class="stat-value text-info fw-bold" data-id="total-injeksi">{TOTAL_INJEKSI}</span></div>
                            <div class="stat-row"><span class="stat-label">Expiry</span><span class="stat-value" data-id="tgl-kadaluwarsa">{TGL_KADALUWARSA}</span></div>
                            <div class="stat-row"><span class="stat-label">Status</span><span class="stat-value badge bg-success" data-id="status">{STATUS}</span></div>
                            <div class="stat-row"><span class="stat-label">Quota</span><span class="stat-value" data-id="total-kuota">{TOTAL_KUOTA}</span></div>
                            <div class="stat-row"><span class="stat-label">Last Upd</span><span class="stat-value text-primary small" data-id="last-inject">{LAST_INJECT}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template id="template-card-error">
         <div class="col-lg-6 col-md-12 mb-4">
            <div class="card border-danger h-100">
                <div class="card-header bg-danger text-white" data-id="project-name">{PROJECT_NAME}</div>
                <div class="card-body text-center py-5">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-3"></i>
                    <h5 class="text-danger">Failed to Load</h5>
                    <p class="text-muted mb-1" data-id="error-message">{ERROR_MESSAGE}</p>
                    <small class="text-secondary">{SUB_KEY}</small>
                </div>
            </div>
        </div>
    </template>

</div> 

<div class="modal fade" id="calendarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl"> 
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="calendarModalLabel">Injection Log</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="calendarModalBody">
                <div class="row mb-3 text-center">
                    <div class="col-md-4"><div class="p-3 bg-success text-white rounded shadow-sm"><h5>Total Quota</h5><h4 id="cal-total-gb-box">0 GB</h4></div></div>
                    <div class="col-md-4"><div class="p-3 bg-info text-white rounded shadow-sm"><h5>Success</h5><h4 id="cal-total-success-box">0</h4></div></div>
                    <div class="col-md-4"><div class="p-3 bg-danger text-white rounded shadow-sm"><h5>Failed</h5><h4 id="cal-total-failed-box">0</h4></div></div>
                </div>
                <div id="calendar-in-modal"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="detailModalLabel">Detail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detailModalBody">
                <div class="loading-spinner text-center p-4"><div class="spinner-border text-primary"></div></div>
                <div class="modal-content-container" style="display:none">
                    <div class="row mb-3 text-center">
                        <div class="col-6"><div class="p-2 bg-light-success rounded shadow-sm"><h4 class="text-success" id="daily-total-gb">0 GB</h4><small>Success Quota</small></div></div>
                        <div class="col-6"><div class="p-2 bg-light-danger rounded shadow-sm"><h4 class="text-danger" id="daily-total-failed">0</h4><small>Failed</small></div></div>
                    </div>
                    <div id="detailTableContainer" class="table-responsive" style="max-height:300px;overflow-y:auto"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>var GLOBAL_TOTAL_COMPANIES = <?php echo count($companies); ?>;</script>

<?php ob_start(); ?>
<script src="assets/extensions/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>

<script>
$(document).ready(function() {
    var $filterCompany = $('#filter_company_id');
    var $filterProject = $('#filter_project_id');
    var $btnReset = $('#reset_filter');
    var $datapoolContainer = $('#datapool-container');
    var $datapoolLoader = $('#datapool-loader');
    var activeCharts = [];

    // Cache Templates
    var templateCardSuccess = $('#template-card-success').html();
    var templateCardError = $('#template-card-error').html();
    var templateCardSummary = $('#template-card-summary').html();

    // Modals
    var bsCalendarModal = new bootstrap.Modal(document.getElementById('calendarModal'));
    var bsDetailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    var calendarInstance = null;
    var currentCalendarProjectId = null;
    var currentRequest = null;

    function resetModalState() {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css({'overflow': 'auto', 'padding-right': ''});
    }
    if(document.getElementById('calendarModal')) document.getElementById('calendarModal').addEventListener('hidden.bs.modal', resetModalState);
    if(document.getElementById('detailModal')) document.getElementById('detailModal').addEventListener('hidden.bs.modal', resetModalState);

    // --- FUNCTION: LOAD DASHBOARD ---
    function updateDatapoolDashboard(companyId = '', projectId = '') {
        if (currentRequest != null) { currentRequest.abort(); currentRequest = null; }
        
        $datapoolContainer.hide();
        $datapoolLoader.show();
        
        $filterCompany.prop('disabled', true);
        $filterProject.prop('disabled', true);
        $btnReset.prop('disabled', true);

        activeCharts.forEach(c => c.destroy());
        activeCharts = [];

        var ajaxUrl = 'ajax/get_datapool_cards.php?nocache=' + new Date().getTime();
        if (companyId) ajaxUrl += '&company_id=' + companyId;
        if (projectId) ajaxUrl += '&project_id=' + projectId;

        currentRequest = $.ajax({
            url: ajaxUrl, type: 'GET', dataType: 'json',
            success: function(response) {
                $datapoolLoader.hide();
                $datapoolContainer.empty().show();

                if (!response || response.length === 0) {
                    $datapoolContainer.html('<div class="col-12"><div class="alert alert-info text-center shadow-sm"><i class="bi bi-info-circle me-2"></i> No data available.</div></div>');
                    return;
                }
                
                if (companyId === '' && projectId === '') {
                    renderSummaryCard(response);
                } else {
                    var processedIds = new Set();
                    response.forEach(p => {
                        var uniqueKey = String(p.project_id);
                        if (processedIds.has(uniqueKey)) return;
                        processedIds.add(uniqueKey);
                        if (p.error) renderErrorCard(p); else renderSuccessCard(p);
                    });
                }
            },
            error: function(e) {
                if (e.statusText !== 'abort') {
                    $datapoolLoader.hide();
                    $datapoolContainer.html('<div class="col-12"><div class="alert alert-danger text-center">Failed to load data. Server Error.</div></div>').show();
                }
            },
            complete: function() {
                // Jangan enable jika hanya ada 1 opsi (User Terbatas)
                if ($filterCompany.find('option').length > 1) {
                    $filterCompany.prop('disabled', false);
                }
                
                if ($filterCompany.val() !== "") $filterProject.prop('disabled', false);
                $btnReset.prop('disabled', false);
            }
        });
    }

    // RENDERERS
    function renderSummaryCard(projects) {
        let totalQuota = 0, totalSisa = 0, totalInjeksi = 0;
        let processedSummaryIds = new Set();
        let uniqueCount = 0;

        projects.forEach(p => {
             let uKey = String(p.project_id);
             if (!processedSummaryIds.has(uKey)) {
                processedSummaryIds.add(uKey); uniqueCount++;
                totalInjeksi += parseInt(p.total_success_inject || 0);
                if (!p.error && p.api_data) {
                    let u = (p.api_data.unit || 'GB').toUpperCase();
                    let s = parseFloat(p.api_data.balance || 0);
                    let t = parseFloat(p.api_data.limitUsage || 0);
                    if (u === 'MB') { s /= 1000; t /= 1000; }
                    totalSisa += s; totalQuota += t;
                }
             }
        });
        let totalTerpakai = totalQuota - totalSisa;
        let html = templateCardSummary
            .replace(/{CANVAS_ID}/g, 'chart-summary-donut').replace(/{BAR_CANVAS_ID}/g, 'chart-summary-bar')
            .replace(/{SISA_SALDO}/g, totalSisa.toFixed(0) + ' GB').replace(/{TOTAL_TERPAKAI}/g, totalTerpakai.toFixed(0) + ' GB')
            .replace(/{TOTAL_KUOTA}/g, totalQuota.toFixed(0) + ' GB').replace(/{TOTAL_INJEKSI}/g, totalInjeksi.toLocaleString('id-ID'))
            .replace(/{PROJECT_COUNT}/g, uniqueCount).replace(/{COMPANY_COUNT}/g, GLOBAL_TOTAL_COMPANIES);
        $datapoolContainer.append(html);
        initDonutChart('chart-summary-donut', totalSisa, totalTerpakai);
        initBarChart('chart-summary-bar', totalSisa, totalTerpakai, totalQuota);
    }

    function renderSuccessCard(p) {
        let api = p.api_data;
        let u = (api.unit || 'GB').toUpperCase();
        let s = parseFloat(api.balance || 0);
        let t = parseFloat(api.limitUsage || 0);
        if (u === 'MB') { s /= 1000; t /= 1000; }
        let tp = t - s;
        let lastInj = p.last_success_inject_date || '-';
        let cvId = 'chart-' + p.project_id; 
        let html = templateCardSuccess
            .replace(/{PROJECT_ID}/g, p.project_id).replace(/{PROJECT_NAME}/g, p.project_name)
            .replace(/{CANVAS_ID}/g, cvId).replace(/{PACKAGE_NAME}/g, api.servicePackageName || 'N/A')
            .replace(/{SISA_SALDO}/g, s.toFixed(0) + ' GB').replace(/{TOTAL_TERPAKAI}/g, tp.toFixed(0) + ' GB')
            .replace(/{TGL_KADALUWARSA}/g, api.expiryDate ? api.expiryDate.substring(0,10) : '-')
            .replace(/{STATUS}/g, api.status || '-').replace(/{TOTAL_KUOTA}/g, t.toFixed(0) + ' GB')
            .replace(/{TOTAL_INJEKSI}/g, parseInt(p.total_success_inject || 0).toLocaleString('id-ID'))
            .replace(/{LAST_INJECT}/g, lastInj);
        $datapoolContainer.append(html);
        initDonutChart(cvId, s, tp);
    }

    function renderErrorCard(p) {
        $datapoolContainer.append(templateCardError.replace(/{PROJECT_NAME}/g, p.project_name).replace(/{ERROR_MESSAGE}/g, p.message).replace(/{SUB_KEY}/g, p.subscription_key));
    }

    function initDonutChart(id, sisa, terpakai) {
        var ctx = document.getElementById(id); if(!ctx || typeof Chart === 'undefined') return;
        if(sisa < 0) sisa = 0; if(terpakai < 0) terpakai = 0;
        let bg = ['#198754', '#dc3545']; if(sisa == 0 && terpakai == 0) { sisa = 1; bg = ['#e9ecef', '#e9ecef']; }
        activeCharts.push(new Chart(ctx, { type: 'doughnut', data: { labels: ['Remaining', 'Used'], datasets: [{ data: [sisa, terpakai], backgroundColor: bg, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 75, legend: { display: false }, animation: { duration: 1000 } } }));
    }

    function initBarChart(id, sisa, terpakai, total) {
        var ctx = document.getElementById(id); if(!ctx || typeof Chart === 'undefined') return;
        activeCharts.push(new Chart(ctx, { type: 'horizontalBar', data: { labels: ['Total', 'Rem.', 'Used'], datasets: [{ data: [total, sisa, terpakai], backgroundColor: ['#435ebe', '#198754', '#dc3545'], barPercentage: 0.6 }] }, options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { xAxes: [{ ticks: { beginAtZero: true }, gridLines: {display:false} }], yAxes: [{ gridLines: {display:false} }] }, animation: { duration: 1000 } } }));
    }

    // Filters
    $filterCompany.on('change', function() {
        var cid = $(this).val(); $filterProject.prop('disabled', true).html('<option>Loading...</option>');
        if(cid) { $.get('ajax/get_projects_by_company.php', {company_id: cid}, function(res) { var html = '<option value="">-- All Project --</option>'; if(res.projects) { res.projects.forEach(p => { html += `<option value="${p.id}">${p.project_name}</option>`; }); } $filterProject.html(html).prop('disabled', false); }, 'json'); } else { $filterProject.html('<option value="">-- All Project --</option>'); }
        updateDatapoolDashboard(cid, '');
    });
    $filterProject.on('change', function() { updateDatapoolDashboard($filterCompany.val(), $(this).val()); });
    
    $btnReset.click(function() { 
        // Jangan reset ke kosong jika user terbatas
        if ($filterCompany.find('option[value=""]').length > 0) {
            $filterCompany.val('').trigger('change'); 
        } else {
             // Reset ke pilihan pertama (perusahaan user itu sendiri)
            $filterCompany.val($filterCompany.find('option:first').val()).trigger('change');
        }
    });

    // Calendar Events
    $(document).on('click', '.card-header[data-project-id]', function() {
        currentCalendarProjectId = $(this).data('project-id');
        $('#calendarModalLabel').text('Log: ' + $(this).find('.card-title').text());
        bsCalendarModal.show();
    });

    document.getElementById('calendarModal').addEventListener('shown.bs.modal', function() {
        if(calendarInstance) calendarInstance.destroy();
        var el = document.getElementById('calendar-in-modal');
        if(typeof FullCalendar !== 'undefined') {
            calendarInstance = new FullCalendar.Calendar(el, {
                themeSystem: 'bootstrap', initialView: 'dayGridMonth',
                events: { url: 'ajax/get_injection_events.php', extraParams: function(){ return {project_id: currentCalendarProjectId}; } },
                dateClick: function(info) {
                    $('#detailModalLabel').text(info.dateStr); $('#detailModalBody .loading-spinner').show(); $('#detailModalBody .modal-content-container').hide(); bsDetailModal.show();
                    $.get('ajax/get_injection_details.php', {date: info.dateStr, project_id: currentCalendarProjectId}, function(res){
                        $('#detailModalBody .loading-spinner').hide(); $('#detailModalBody .modal-content-container').show();
                        if(res.status) {
                            $('#daily-total-gb').text(res.totals.total_gb_success + ' GB'); $('#daily-total-failed').text(res.totals.total_failed);
                            var tr = ''; res.logs.forEach(l => { var bg = l.status == 'SUCCESS' ? 'bg-success' : 'bg-danger'; tr += `<tr><td>${l.msisdn_target}</td><td>${l.denom_name}</td><td><span class="badge ${bg}">${l.status}</span></td></tr>`; });
                            $('#detailTableContainer').html('<table class="table table-sm"><thead><tr><th>MSISDN</th><th>Pkg</th><th>Sts</th></tr></thead><tbody>'+tr+'</tbody></table>');
                        }
                    }, 'json');
                },
                datesSet: function(info) {
                    var d = info.view.currentStart;
                    $.get('ajax/get_injection_monthly_totals.php', {year: d.getFullYear(), month: d.getMonth()+1, project_id: currentCalendarProjectId}, function(res){
                        if(res.status) { $('#cal-total-gb-box').text(res.totals.total_gb_success + ' GB'); $('#cal-total-success-box').text(res.totals.total_success); $('#cal-total-failed-box').text(res.totals.total_failed); }
                    }, 'json');
                }
            });
            calendarInstance.render();
        }
    });

    // Load Data Awal dengan nilai filter yang terpilih
    // Jika User Terbatas, ini akan langsung load perusahaan mereka
    updateDatapoolDashboard($filterCompany.val());
});
</script>

<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>