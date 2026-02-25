<?php
/*
 File: injection_calendar.php (Ultra-Modern Tailwind CSS)
 =====================================================
*/
require_once 'includes/auth_check.php'; 
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
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* FULLCALENDAR TAILWIND OVERRIDES */
    #calendar { min-height: 600px; font-family: inherit; }
    .fc-theme-standard .fc-scrollgrid { border-color: #e2e8f0; border-radius: 12px; overflow: hidden; }
    .fc-theme-standard td, .fc-theme-standard th { border-color: #f1f5f9; }
    .fc-col-header-cell-cushion { padding: 12px 8px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
    .fc .fc-toolbar-title { font-size: 1.25rem; font-weight: 800; color: #1e293b; }
    .dark .fc .fc-toolbar-title { color: #f8fafc; }
    .fc .fc-button-primary { background-color: #4f46e5; border-color: #4f46e5; border-radius: 8px; font-weight: 600; text-transform: capitalize; padding: 0.4rem 1rem; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); }
    .fc .fc-button-primary:hover { background-color: #4338ca; border-color: #4338ca; }
    .fc .fc-button-primary:disabled { background-color: #94a3b8; border-color: #94a3b8; }
    .fc .fc-button-active { background-color: #3730a3 !important; border-color: #3730a3 !important; }
    .fc-daygrid-day-number { font-weight: 600; color: #475569; padding: 8px !important; }
    .dark .fc-daygrid-day-number { color: #cbd5e1; }
    .fc-day-today { background-color: #f8fafc !important; }
    .dark .fc-day-today { background-color: #1e293b !important; }
    
    /* Event Styling */
    .fc-event.status-success { background-color: #10b981; border-color: #10b981; color: white; border-radius: 4px; padding: 2px 4px; font-weight: 600; font-size: 0.7rem; cursor: pointer; transition: transform 0.2s; }
    .fc-event.status-failed { background-color: #ef4444; border-color: #ef4444; color: white; border-radius: 4px; padding: 2px 4px; font-weight: 600; font-size: 0.7rem; cursor: pointer; transition: transform 0.2s; }
    .fc-event:hover { transform: scale(1.02); z-index: 10; }
    
    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">
            Injection Event Calendar
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-calendar-check text-lg text-indigo-500"></i> Track daily injection logs, successes, and failures.
        </p>
    </div>
</div>

<div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 p-6 mb-8 animate-fade-in-up" style="animation-delay: 0.1s;">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
        <div class="md:col-span-5">
            <label class="mb-1.5 block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Target Company</label>
            <div class="relative">
                <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <select id="filter_company_id" class="w-full appearance-none rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 pl-11 text-sm font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-all cursor-pointer shadow-sm">
                    <?php if (!$user_company_id): ?><option value="">-- All Companies --</option><?php endif; ?>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>" <?php echo ($user_company_id == $company['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
            </div>
        </div>
        <div class="md:col-span-5">
            <label class="mb-1.5 block text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Project Specific</label>
            <div class="relative">
                <i class="ph-fill ph-folder-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <select id="filter_project_id" disabled class="w-full appearance-none rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 pl-11 text-sm font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                    <option value="">-- All Projects --</option>
                </select>
                <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
            </div>
        </div>
        <div class="md:col-span-2">
            <button id="reset_filter" class="w-full rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-all shadow-sm flex items-center justify-center gap-2">
                <i class="ph-bold ph-arrows-counter-clockwise"></i> Reset
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 animate-fade-in-up" style="animation-delay: 0.2s;">
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-indigo-50 dark:bg-indigo-500/5 blur-2xl pointer-events-none"></div>
        <div class="h-14 w-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0 shadow-sm border border-indigo-100 dark:border-indigo-500/20"><i class="ph-fill ph-database"></i></div>
        <div class="z-10">
            <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Quota (Month)</h6>
            <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none" id="month-gb"><?php echo round($month_gb, 2); ?> <span class="text-sm font-bold text-slate-500">GB</span></h4>
            <small class="text-[10px] font-bold text-indigo-500 uppercase mt-1 block" id="label-month-1"><?php echo $month_name; ?></small>
        </div>
    </div>
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-emerald-50 dark:bg-emerald-500/5 blur-2xl pointer-events-none"></div>
        <div class="h-14 w-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-3xl shrink-0 shadow-sm border border-emerald-100 dark:border-emerald-500/20"><i class="ph-fill ph-check-circle"></i></div>
        <div class="z-10">
            <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Success (Month)</h6>
            <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none" id="month-success"><?php echo number_format($month_success); ?></h4>
            <small class="text-[10px] font-bold text-emerald-500 uppercase mt-1 block" id="label-month-2"><?php echo $month_name; ?></small>
        </div>
    </div>
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-red-50 dark:bg-red-500/5 blur-2xl pointer-events-none"></div>
        <div class="h-14 w-14 rounded-2xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 flex items-center justify-center text-3xl shrink-0 shadow-sm border border-red-100 dark:border-red-500/20"><i class="ph-fill ph-x-circle"></i></div>
        <div class="z-10">
            <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Failed (Month)</h6>
            <h4 class="text-2xl font-black text-slate-800 dark:text-white leading-none" id="month-failed"><?php echo number_format($month_failed); ?></h4>
            <small class="text-[10px] font-bold text-red-500 uppercase mt-1 block" id="label-month-3"><?php echo $month_name; ?></small>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 animate-fade-in-up" style="animation-delay: 0.3s;">
    <div class="rounded-3xl bg-gradient-to-br from-slate-800 to-slate-900 p-6 shadow-xl relative overflow-hidden group">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20"></div>
        <div class="absolute -right-8 -bottom-8 h-32 w-32 bg-indigo-500 rounded-full blur-3xl opacity-30 group-hover:opacity-50 transition-opacity"></div>
        <div class="relative z-10 flex items-center gap-5">
            <div class="h-14 w-14 rounded-2xl bg-white/10 backdrop-blur-md text-indigo-300 flex items-center justify-center text-3xl shrink-0 border border-white/10"><i class="ph-fill ph-stack"></i></div>
            <div>
                <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Quota (All Time)</h6>
                <h3 class="text-3xl font-black text-white leading-none tracking-tight" id="grand-gb"><?php echo round($grand_gb, 2); ?> <span class="text-sm font-bold text-indigo-300">GB</span></h3>
                <small class="text-[10px] text-slate-400 mt-1 block">Accumulated historical data</small>
            </div>
        </div>
    </div>
    
    <div class="rounded-3xl bg-gradient-to-br from-slate-800 to-slate-900 p-6 shadow-xl relative overflow-hidden group">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20"></div>
        <div class="absolute -right-8 -bottom-8 h-32 w-32 bg-emerald-500 rounded-full blur-3xl opacity-30 group-hover:opacity-50 transition-opacity"></div>
        <div class="relative z-10 flex items-center gap-5">
            <div class="h-14 w-14 rounded-2xl bg-white/10 backdrop-blur-md text-emerald-300 flex items-center justify-center text-3xl shrink-0 border border-white/10"><i class="ph-fill ph-medal"></i></div>
            <div>
                <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Success Inject (All Time)</h6>
                <h3 class="text-3xl font-black text-white leading-none tracking-tight" id="grand-success"><?php echo number_format($grand_success); ?></h3>
                <small class="text-[10px] text-slate-400 mt-1 block">Overall successful transactions</small>
            </div>
        </div>
    </div>
</div>

<div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden animate-fade-in-up" style="animation-delay: 0.4s;">
    <div class="p-6">
        <div id="calendar"></div>
    </div>
</div>

<div id="detailModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col h-[85vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-[#24303F] shrink-0">
            <div class="flex items-center gap-3">
                <i class="ph-fill ph-calendar-check text-2xl text-indigo-500"></i>
                <h5 class="text-xl font-bold text-slate-800 dark:text-white" id="detailModalLabel">Details</h5>
            </div>
            <button type="button" class="btn-close-modal h-10 w-10 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        
        <div class="flex-1 overflow-hidden flex flex-col bg-slate-50/50 dark:bg-slate-900/30 p-6" id="detailModalBody">
            
            <div class="loading-overlay h-full flex flex-col items-center justify-center text-slate-400">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                <p class="text-sm font-bold uppercase tracking-widest">Fetching Data...</p>
            </div>
            
            <div class="modal-content-container hidden flex-col h-full">
                <div class="grid grid-cols-2 gap-4 mb-6 shrink-0">
                    <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 p-4 rounded-2xl text-center">
                        <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400" id="daily-gb">0 GB</h3>
                        <span class="text-[10px] font-black uppercase tracking-widest text-emerald-800 dark:text-emerald-500">Success Quota</span>
                    </div>
                    <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-4 rounded-2xl text-center">
                        <h3 class="text-2xl font-black text-red-600 dark:text-red-400" id="daily-failed">0</h3>
                        <span class="text-[10px] font-black uppercase tracking-widest text-red-800 dark:text-red-500">Failed Transactions</span>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl flex-1 overflow-hidden flex flex-col">
                    <div class="overflow-y-auto custom-scrollbar flex-1 p-2" id="detailTableContainer"></div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-slate-200 dark:border-slate-700 p-5 bg-white dark:bg-[#24303F] flex justify-end shrink-0">
            <button type="button" class="btn-close-modal px-8 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all">Close Panel</button>
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
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek' },
        initialView: 'dayGridMonth',
        height: 'auto',
        contentHeight: 650,
        dayMaxEvents: 3, // Allow "more" link
        events: {
            url: 'ajax/get_injection_events.php',
            extraParams: function() { 
                return { 
                    company_id: $('#filter_company_id').val(), 
                    project_id: $('#filter_project_id').val() 
                }; 
            }
        },
        dateClick: function(info) {
            // Update Modal UI
            $('#detailModalLabel').text('Injection Logs: ' + info.dateStr);
            $('.loading-overlay').removeClass('hidden').addClass('flex'); 
            $('.modal-content-container').addClass('hidden').removeClass('flex');
            
            // Show Tailwind Modal
            $('body').css('overflow', 'hidden');
            $('#detailModal').removeClass('hidden').addClass('flex');

            $.ajax({
                url: 'ajax/get_injection_details.php', type: 'GET',
                data: { date: info.dateStr, company_id: $('#filter_company_id').val(), project_id: $('#filter_project_id').val() },
                dataType: 'json',
                success: function(res) {
                    $('.loading-overlay').addClass('hidden').removeClass('flex'); 
                    $('.modal-content-container').removeClass('hidden').addClass('flex');
                    
                    if (res.status) {
                        $('#daily-gb').text(res.totals.total_gb_success + ' GB');
                        $('#daily-failed').text(res.totals.total_failed);
                        
                        if (res.logs.length > 0) {
                            var html = `
                            <table class="w-full text-left border-collapse">
                                <thead class="sticky top-0 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500">MSISDN</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500">Package</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500">Data</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">`;
                            
                            res.logs.forEach(l => {
                                var badge = l.status === 'SUCCESS' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : (l.status === 'FAILED' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-blue-100 text-blue-700 border-blue-200');
                                
                                html += `
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                    <td class="px-4 py-3 font-mono text-sm font-bold text-slate-800 dark:text-slate-300">${l.msisdn_target}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-slate-600 dark:text-slate-400">${l.denom_name}</td>
                                    <td class="px-4 py-3 text-xs font-bold text-slate-700 dark:text-slate-300">${l.quota_value} ${l.quota_unit}</td>
                                    <td class="px-4 py-3 text-center"><span class="inline-flex px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border ${badge}">${l.status}</span></td>
                                </tr>`;
                            });
                            $('#detailTableContainer').html(html + '</tbody></table>');
                        } else { 
                            $('#detailTableContainer').html('<div class="flex flex-col items-center justify-center py-10 opacity-50"><i class="ph-fill ph-folder-dashed text-5xl mb-2 text-slate-400"></i><p class="font-bold text-slate-500">No logs found for this date.</p></div>'); 
                        }
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

    // Modal Close Handler
    $('.btn-close-modal').click(function() {
        $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
        $('body').css('overflow', 'auto');
    });

    // --- FUNGSI UTAMA: Update Semua Angka (Bulanan + All Time) ---
    function updateDashboardStats(year, month) {
        var cid = $('#filter_company_id').val();
        var pid = $('#filter_project_id').val();
        var mName = new Date(year, month-1).toLocaleDateString('en-US', {month:'long', year:'numeric'});

        // Set Loading State
        let spin = '<i class="ph-bold ph-spinner animate-spin"></i>';
        $('#month-gb').html(spin); $('#month-success').html(spin); $('#month-failed').html(spin);
        $('#grand-gb').html(spin); $('#grand-success').html(spin);

        $.ajax({
            url: 'ajax/get_injection_monthly_totals.php',
            type: 'GET',
            data: { year: year, month: month, company_id: cid, project_id: pid },
            dataType: 'json',
            success: function(res) {
                if (res.status) {
                    $('#month-gb').html(`${res.totals.total_gb_success} <span class="text-sm font-bold text-slate-500">GB</span>`);
                    $('#month-success').text(parseInt(res.totals.total_success).toLocaleString());
                    $('#month-failed').text(parseInt(res.totals.total_failed).toLocaleString());
                    $('#label-month-1, #label-month-2, #label-month-3').text(mName);

                    if(res.grand_totals) {
                        $('#grand-gb').html(`${res.grand_totals.total_gb_success} <span class="text-sm font-bold text-indigo-300">GB</span>`);
                        $('#grand-success').text(parseInt(res.grand_totals.total_success).toLocaleString());
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
                $p.prop('disabled', false).html('<option value="">-- All Projects --</option>');
                if(res.projects) res.projects.forEach(p => $p.append(`<option value="${p.id}">${p.project_name}</option>`));
            }, 'json');
        } else { $p.html('<option value="">-- All Projects --</option>'); }
        
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

    // Trigger change saat load awal agar project terload (jika user terbatas)
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