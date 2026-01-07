<?php
/*
 File: injection_calendar.php (FIXED: Multi-tenant Support)
 =====================================================
*/
require_once 'includes/auth_check.php'; // Pastikan auth check ada
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 

$db = db_connect();

// --- LOGIKA FILTER USER (MULTI-TENANT) ---
$user_company_id = $_SESSION['company_id'] ?? null;

// --- 1. PHP: Hitung Statistik Awal (Saat Load Pertama) ---

// A. Data TOTAL BULAN INI (Monthly)
$current_year = date('Y');
$current_month = date('m');
$month_name = date('F Y');

// Siapkan Query Dasar
$sql_monthly = "SELECT status, COUNT(*) as count, SUM(CASE WHEN quota_unit = 'MB' THEN quota_value / 1024 ELSE quota_value END) as total_gb
                FROM inject_history
                WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? AND status IN ('SUCCESS', 'FAILED')";
$params_monthly = [$current_year, $current_month];

// Filter jika User Terbatas
if ($user_company_id) {
    $sql_monthly .= " AND company_id = ?";
    $params_monthly[] = $user_company_id;
}

$sql_monthly .= " GROUP BY status";

$stmt = $db->prepare($sql_monthly);
$stmt->execute($params_monthly);
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$month_success = 0; $month_failed = 0; $month_gb = 0;
foreach ($monthly_stats as $stat) {
    if ($stat['status'] == 'SUCCESS') { $month_success = $stat['count']; $month_gb = $stat['total_gb']; } 
    elseif ($stat['status'] == 'FAILED') { $month_failed = $stat['count']; }
}

// B. Data TOTAL KESELURUHAN (All Time)
$sql_all = "SELECT status, COUNT(*) as count, SUM(CASE WHEN quota_unit = 'MB' THEN quota_value / 1024 ELSE quota_value END) as total_gb
            FROM inject_history
            WHERE status IN ('SUCCESS', 'FAILED')";
$params_all = [];

// Filter jika User Terbatas
if ($user_company_id) {
    $sql_all .= " AND company_id = ?";
    $params_all[] = $user_company_id;
}

$sql_all .= " GROUP BY status";

$stmt_all = $db->prepare($sql_all);
$stmt_all->execute($params_all);
$all_stats = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$grand_success = 0; $grand_gb = 0;
foreach ($all_stats as $stat) {
    if ($stat['status'] == 'SUCCESS') { $grand_success = $stat['count']; $grand_gb = $stat['total_gb']; }
}

// C. Ambil daftar company untuk dropdown
try {
    if ($user_company_id) {
        // User Terbatas: Hanya ambil perusahaannya sendiri
        $stmt_comp = $db->prepare("SELECT id, company_name FROM companies WHERE id = ? ORDER BY company_name ASC");
        $stmt_comp->execute([$user_company_id]);
    } else {
        // Admin: Ambil semua
        $stmt_comp = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
    }
    $companies = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $companies = []; }
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">

<style>
    /* Style Tambahan */
    .fc-event.status-success { background-color: #198754; border-color: #198754; }
    .fc-event.status-failed { background-color: #dc3545; border-color: #dc3545; }
    .fc-event-main { font-size: 0.85em; padding: 2px; }
    .loading-overlay { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 3rem; }
    
    /* Pembeda Visual Kartu All Time */
    .card-all-time { border: 1px solid #435ebe; background-color: #fdfdfd; }
    .text-label-small { font-size: 0.8rem; color: #888; }
    
    /* Pastikan kalender punya tinggi minimal agar terlihat */
    #calendar { min-height: 600px; }
</style>

<div class="page-heading">
    <h3>Injection Log Calendar</h3>
</div>

<div class="page-content">
    
    <section class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-5 mb-2">
                            <label class="form-label fw-bold">Filter Company</label>
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
                        <div class="col-md-5 mb-2">
                            <label class="form-label fw-bold">Filter Project</label>
                            <select class="form-select" id="filter_project_id" disabled><option value="">-- All Project --</option></select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button class="btn btn-secondary w-100" id="reset_filter">Reset</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row">
        <div class="col-12 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4"><div class="stats-icon green mb-2"><i class="bi bi-database-fill"></i></div></div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Total Quota</h6>
                            <h4 class="font-extrabold mb-0" id="month-gb"><?php echo round($month_gb, 2); ?> <span class="fs-6">GB</span></h4>
                            <small class="text-success" id="label-month-1">Month: <?php echo $month_name; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4"><div class="stats-icon blue mb-2"><i class="bi bi-check-circle-fill"></i></div></div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Success</h6>
                            <h4 class="font-extrabold mb-0" id="month-success"><?php echo $month_success; ?></h4>
                            <small class="text-primary" id="label-month-2">Month: <?php echo $month_name; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4"><div class="stats-icon red mb-2"><i class="bi bi-x-circle-fill"></i></div></div>
                        <div class="col-md-8">
                            <h6 class="text-muted font-semibold">Failed</h6>
                            <h4 class="font-extrabold mb-0" id="month-failed"><?php echo $month_failed; ?></h4>
                            <small class="text-danger" id="label-month-3">Month: <?php echo $month_name; ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row">
        <div class="col-12">
            <h5 class="mb-3 text-muted">All Time Summary</h5>
        </div>
        <div class="col-12 col-md-6">
            <div class="card card-all-time">
                <div class="card-body px-3 py-4">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon purple me-3"><i class="bi bi-layers-fill"></i></div>
                        <div>
                            <h6 class="text-muted font-semibold">Total Quota (All Time)</h6>
                            <h3 class="font-extrabold mb-0 text-primary" id="grand-gb"><?php echo round($grand_gb, 2); ?> GB</h3>
                            <small class="text-label-small">Accumulated from all months</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card card-all-time">
                <div class="card-body px-3 py-4">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon purple me-3"><i class="bi bi-award-fill"></i></div>
                        <div>
                            <h6 class="text-muted font-semibold">Total Success Inject (All Time)</h6>
                            <h3 class="font-extrabold mb-0 text-primary" id="grand-success"><?php echo $grand_success; ?></h3>
                            <small class="text-label-small">Total successful transactions</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div id="calendar" class="p-3"></div>
                </div>
            </div>
        </div>
    </section>

</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="detailModalLabel">Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detailModalBody">
                <div class="loading-overlay"><div class="spinner-border text-primary"></div><p class="mt-2">Loading...</p></div>
                <div class="modal-content-container" style="display:none;">
                    <div class="row mb-3">
                        <div class="col-md-6"><div class="p-3 bg-light-success rounded text-center"><h3 class="text-success" id="daily-gb">0 GB</h3><small>Success Quota</small></div></div>
                        <div class="col-md-6"><div class="p-3 bg-light-danger rounded text-center"><h3 class="text-danger" id="daily-failed">0</h3><small>Failed</small></div></div>
                    </div>
                    <div id="detailTableContainer" class="table-responsive" style="max-height:400px;overflow-y:auto;"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="assets/extensions/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

<script>
$(function () {
    // Init Calendar
    var calendarEl = document.getElementById('calendar');
    var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek' },
        themeSystem: 'bootstrap', 
        initialView: 'dayGridMonth',
        height: 650, 
        events: {
            url: 'ajax/get_injection_events.php',
            // Kirim parameter filter ke backend
            extraParams: function() { 
                return { 
                    company_id: $('#filter_company_id').val(), 
                    project_id: $('#filter_project_id').val() 
                }; 
            }
        },
        dateClick: function(info) {
            $('#detailModalLabel').text('Details: ' + info.dateStr);
            $('#detailModalBody .loading-overlay').show(); $('#detailModalBody .modal-content-container').hide();
            detailModal.show();

            $.ajax({
                url: 'ajax/get_injection_details.php', type: 'GET',
                data: { date: info.dateStr, company_id: $('#filter_company_id').val(), project_id: $('#filter_project_id').val() },
                dataType: 'json',
                success: function(res) {
                    $('#detailModalBody .loading-overlay').hide(); $('#detailModalBody .modal-content-container').show();
                    if (res.status) {
                        $('#daily-gb').text(res.totals.total_gb_success + ' GB');
                        $('#daily-failed').text(res.totals.total_failed);
                        if (res.logs.length > 0) {
                            var html = '<table class="table table-sm table-hover"><thead><tr><th>MSISDN</th><th>Pkg</th><th>Data</th><th>Status</th></tr></thead><tbody>';
                            res.logs.forEach(l => {
                                var badge = l.status === 'SUCCESS' ? 'bg-success' : (l.status === 'FAILED' ? 'bg-danger' : 'bg-info');
                                html += `<tr><td>${l.msisdn_target}</td><td>${l.denom_name}</td><td>${l.quota_value} ${l.quota_unit}</td><td><span class="badge ${badge}">${l.status}</span></td></tr>`;
                            });
                            $('#detailTableContainer').html(html + '</tbody></table>');
                        } else { $('#detailTableContainer').html('<div class="alert alert-warning text-center">No data found.</div>'); }
                    }
                }
            });
        },
        datesSet: function(info) {
            var d = info.view.currentStart;
            updateDashboardStats(d.getFullYear(), d.getMonth() + 1);
        }
    });
    calendar.render();

    // --- FUNGSI UTAMA: Update Semua Angka (Bulanan + All Time) ---
    function updateDashboardStats(year, month) {
        var cid = $('#filter_company_id').val();
        var pid = $('#filter_project_id').val();
        var mName = new Date(year, month-1).toLocaleDateString('en-US', {month:'long', year:'numeric'});

        // Set Loading State
        $('#month-gb, #month-success, #month-failed').html('<div class="spinner-border spinner-border-sm"></div>');
        $('#grand-gb, #grand-success').html('<div class="spinner-border spinner-border-sm"></div>');

        $.ajax({
            url: 'ajax/get_injection_monthly_totals.php', // File Logic Backend
            type: 'GET',
            data: { year: year, month: month, company_id: cid, project_id: pid },
            dataType: 'json',
            success: function(res) {
                if (res.status) {
                    // 1. Update Bulanan
                    $('#month-gb').html(res.totals.total_gb_success + ' <span class="fs-6">GB</span>');
                    $('#month-success').text(res.totals.total_success);
                    $('#month-failed').text(res.totals.total_failed);
                    $('#label-month-1, #label-month-2, #label-month-3').text('Month: ' + mName);

                    // 2. Update All Time
                    if(res.grand_totals) {
                        $('#grand-gb').text(res.grand_totals.total_gb_success + ' GB');
                        $('#grand-success').text(res.grand_totals.total_success);
                    }
                }
            }
        });
    }

    // Filter Listeners
    $('#filter_company_id').change(function() {
        var cid = $(this).val();
        var $p = $('#filter_project_id').prop('disabled', true).html('<option>Loading...</option>');
        if(cid) {
            $.get('ajax/get_projects_by_company.php', {company_id: cid}, function(res){
                $p.prop('disabled', false).html('<option value="">-- All Project --</option>');
                if(res.projects) res.projects.forEach(p => $p.append(`<option value="${p.id}">${p.project_name}</option>`));
            }, 'json');
        } else { $p.html('<option value="">-- All Project --</option>'); }
        
        calendar.refetchEvents();
        var d = calendar.getDate();
        updateDashboardStats(d.getFullYear(), d.getMonth()+1);
    });

    $('#filter_project_id').change(function() {
        calendar.refetchEvents();
        var d = calendar.getDate();
        updateDashboardStats(d.getFullYear(), d.getMonth()+1);
    });

    $('#reset_filter').click(function() {
        // Cek jika user terbatas, jangan kosongkan semua, tapi pilih opsi pertama
        var $comp = $('#filter_company_id');
        if ($comp.find('option[value=""]').length > 0) {
            $comp.val(''); // Admin: Reset ke kosong
        } else {
            $comp.val($comp.find('option:first').val()); // User: Reset ke company sendiri
        }
        
        $('#filter_project_id').val('').prop('disabled',true);
        calendar.refetchEvents();
        var d = calendar.getDate();
        updateDashboardStats(d.getFullYear(), d.getMonth()+1);
    });

    // PENTING: Trigger change saat load awal agar project terload (jika user terbatas)
    if ($('#filter_company_id').val() !== "") {
        $('#filter_company_id').trigger('change');
    }
});
</script>
<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>