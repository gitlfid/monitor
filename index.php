<?php
/*
 File: index.php
 ===========================================================
 Status: FIXED (Anti-Duplicate & Robust Chart Loading)
 Theme: Tailwind CSS Modern + Animations
*/

require_once 'includes/auth_check.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 

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
    /* Chart Dimensions */
    .chart-wrapper-donut { position: relative; height: 200px !important; width: 100%; }
    .chart-wrapper-bar { position: relative; height: 150px !important; width: 100%; }
    canvas { display: block; width: 100%; }

    /* Custom Animations */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up {
        animation: fadeInUp 0.5s ease-out forwards;
    }
    
    /* Modal entry animations */
    @keyframes scaleIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .modal-animate-in {
        animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>

<div class="mb-8 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400">
            Datapool Monitor
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
            <i class="ph ph-chart-line-up text-lg"></i> Overview of your data usages and injection logs.
        </p>
    </div>
</div>

<div class="mb-8 rounded-2xl bg-white/80 backdrop-blur-xl p-5 shadow-soft dark:bg-[#24303F]/80 border border-slate-100 dark:border-slate-800 transition-colors animate-fade-in-up" style="animation-delay: 0.1s;">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
        <div class="md:col-span-5">
            <label for="filter_company_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Filter Company</label>
            <div class="relative">
                <i class="ph ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <select id="filter_company_id" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/50 py-3 pl-11 pr-10 text-sm font-medium text-slate-700 outline-none transition-all hover:bg-slate-50 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:hover:bg-slate-800 cursor-pointer">
                    <?php if (!$user_company_id): ?>
                        <option value="">-- All Companies --</option>
                    <?php endif; ?>
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
            <label for="filter_project_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Filter Project</label>
            <div class="relative">
                <i class="ph ph-folder-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <select id="filter_project_id" disabled class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50/50 py-3 pl-11 pr-10 text-sm font-medium text-slate-700 outline-none transition-all hover:bg-slate-50 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300 dark:hover:bg-slate-800 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <option value="">-- All Projects --</option>
                </select>
                <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
            </div>
        </div>
        <div class="md:col-span-2">
            <button id="reset_filter" class="group flex w-full items-center justify-center gap-2 rounded-xl bg-slate-100 py-3 text-sm font-bold text-slate-600 transition-all hover:bg-slate-200 hover:text-slate-900 active:scale-95 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                <i class="ph ph-arrows-counter-clockwise text-lg transition-transform group-hover:-rotate-180 duration-500"></i> Reset
            </button>
        </div>
    </div>
</div>

<div id="datapool-container" class="grid grid-cols-1 md:grid-cols-12 gap-6 hidden"></div>

<div id="datapool-loader" class="flex flex-col items-center justify-center py-20">
    <div class="relative flex h-14 w-14 items-center justify-center">
        <div class="absolute h-full w-full animate-ping rounded-full bg-indigo-400 opacity-20 duration-1000"></div>
        <svg class="relative h-10 w-10 animate-spin text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
    <p class="mt-4 text-sm font-bold text-slate-500 dark:text-slate-400 tracking-wide animate-pulse">SYNCING DATA...</p>
</div>

<template id="template-card-summary">
    <div class="md:col-span-12 animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="flex flex-col rounded-2xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
            <div class="border-b border-slate-100 dark:border-slate-800 px-7 py-5">
                <h5 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-chart-pie-slice text-indigo-500"></i> Global Summary
                </h5>
            </div>
            <div class="p-7">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div class="lg:col-span-4 flex flex-col items-center justify-center lg:border-r border-slate-100 dark:border-slate-800 pb-6 lg:pb-0 pr-0 lg:pr-6 relative">
                        <div class="chart-wrapper-donut"><canvas id="{CANVAS_ID}"></canvas></div>
                        <div class="mt-5 flex gap-4 text-xs font-bold">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20 shadow-sm"><span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Remaining</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1.5 text-red-700 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20 shadow-sm"><span class="h-2 w-2 rounded-full bg-red-500"></span> Used</span>
                        </div>
                    </div>
                    <div class="lg:col-span-8 pl-0 lg:pl-2">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                            <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/10 p-5 text-center border border-emerald-100 dark:border-emerald-800/30 shadow-sm hover:-translate-y-1 transition-transform duration-300">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-800/50 dark:text-emerald-400 mb-3">
                                    <i class="ph-fill ph-database text-xl"></i>
                                </div>
                                <h6 class="text-xs font-bold text-emerald-600/80 dark:text-emerald-400 mb-1 tracking-wide">REMAINING</h6>
                                <h3 class="text-2xl font-black text-emerald-700 dark:text-emerald-300" data-id="sisa-saldo">{SISA_SALDO}</h3>
                            </div>
                            <div class="rounded-2xl bg-gradient-to-br from-red-50 to-red-100/50 dark:from-red-900/20 dark:to-red-800/10 p-5 text-center border border-red-100 dark:border-red-800/30 shadow-sm hover:-translate-y-1 transition-transform duration-300">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-800/50 dark:text-red-400 mb-3">
                                    <i class="ph-fill ph-trend-down text-xl"></i>
                                </div>
                                <h6 class="text-xs font-bold text-red-600/80 dark:text-red-400 mb-1 tracking-wide">USED</h6>
                                <h3 class="text-2xl font-black text-red-700 dark:text-red-300" data-id="total-terpakai">{TOTAL_TERPAKAI}</h3>
                            </div>
                            <div class="rounded-2xl bg-gradient-to-br from-indigo-50 to-indigo-100/50 dark:from-indigo-900/20 dark:to-indigo-800/10 p-5 text-center border border-indigo-100 dark:border-indigo-800/30 shadow-sm hover:-translate-y-1 transition-transform duration-300">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-800/50 dark:text-indigo-400 mb-3">
                                    <i class="ph-fill ph-circles-three-plus text-xl"></i>
                                </div>
                                <h6 class="text-xs font-bold text-indigo-600/80 dark:text-indigo-400 mb-1 tracking-wide">TOTAL QUOTA</h6>
                                <h3 class="text-2xl font-black text-indigo-700 dark:text-indigo-300" data-id="total-kuota">{TOTAL_KUOTA}</h3>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-slate-100 dark:border-slate-800 pt-6 text-center">
                            <div><span class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Companies</span><span class="text-xl font-black text-slate-700 dark:text-slate-200" data-id="company-count">{COMPANY_COUNT}</span></div>
                            <div class="border-x border-slate-100 dark:border-slate-800"><span class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Projects</span><span class="text-xl font-black text-slate-700 dark:text-slate-200" data-id="project-count">{PROJECT_COUNT}</span></div>
                            <div><span class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Inject</span><span class="text-xl font-black text-indigo-600 dark:text-indigo-400" data-id="total-injecksi">{TOTAL_INJEKSI}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="template-card-success">
    <div class="md:col-span-6 animate-fade-in-up">
        <div class="group flex flex-col rounded-2xl bg-white shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 h-full overflow-hidden">
            <div class="bg-slate-50/80 dark:bg-slate-800/50 px-6 py-4 flex justify-between items-center cursor-pointer border-b border-slate-100 dark:border-slate-800/80 card-header transition-colors group-hover:bg-indigo-50/50 dark:group-hover:bg-indigo-900/10" data-id="project-name" data-project-id="{PROJECT_ID}" title="View detailed logs">
                <h6 class="text-base font-bold text-indigo-700 dark:text-indigo-400 flex items-center gap-2 group-hover:text-indigo-600 transition-colors">
                    <i class="ph-fill ph-folder text-xl"></i> {PROJECT_NAME}
                </h6>
                <div class="h-8 w-8 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center shadow-sm text-slate-400 group-hover:text-indigo-500 transition-colors">
                    <i class="ph-fill ph-calendar-blank"></i>
                </div>
            </div>
            <div class="p-6 flex-1">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
                    <div class="sm:col-span-5 text-center flex flex-col items-center">
                        <div class="chart-wrapper-donut" style="height: 160px !important; width: 100%;"><canvas id="{CANVAS_ID}"></canvas></div>
                        <div class="mt-4 text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 truncate max-w-full inline-block shadow-sm">
                            <i class="ph-fill ph-package mr-1 text-indigo-500"></i> {PACKAGE_NAME}
                        </div>
                    </div>
                    <div class="sm:col-span-7 flex flex-col gap-3">
                        <div class="flex justify-between items-center border-b border-slate-50 dark:border-slate-700/50 pb-2.5">
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph ph-database text-emerald-500"></i> Remaining</span>
                            <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-md" data-id="sisa-saldo">{SISA_SALDO}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-50 dark:border-slate-700/50 pb-2.5">
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph ph-trend-down text-red-500"></i> Used</span>
                            <span class="text-sm font-black text-red-600 dark:text-red-400" data-id="total-terpakai">{TOTAL_TERPAKAI}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-50 dark:border-slate-700/50 pb-2.5">
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph ph-check-circle text-indigo-500"></i> Success Inject</span>
                            <span class="text-sm font-black text-indigo-600 dark:text-indigo-400" data-id="total-injeksi">{TOTAL_INJEKSI}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-50 dark:border-slate-700/50 pb-2.5">
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph ph-calendar text-orange-500"></i> Expiry</span>
                            <span class="text-sm font-bold text-slate-700 dark:text-slate-300" data-id="tgl-kadaluwarsa">{TGL_KADALUWARSA}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-50 dark:border-slate-700/50 pb-2.5">
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><i class="ph ph-pulse text-emerald-500"></i> Status</span>
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400 shadow-sm" data-id="status">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{STATUS}
                            </span>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-xs font-medium text-slate-400 flex items-center gap-1"><i class="ph ph-clock text-slate-400"></i> Last Update</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded" data-id="last-inject">{LAST_INJECT}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<template id="template-card-error">
    <div class="md:col-span-6 animate-fade-in-up">
        <div class="flex flex-col rounded-2xl bg-white shadow-soft dark:bg-[#24303F] border border-red-200 dark:border-red-900/50 h-full overflow-hidden hover:shadow-lg transition-all duration-300 relative">
            <div class="absolute top-0 left-0 w-full h-1 bg-red-500"></div>
            <div class="bg-red-50/50 dark:bg-red-900/10 px-6 py-4 border-b border-red-100 dark:border-red-900/30 flex items-center gap-2">
                <i class="ph-fill ph-warning-circle text-red-500 text-lg"></i>
                <h6 class="text-sm font-bold text-red-700 dark:text-red-400 truncate" data-id="project-name">{PROJECT_NAME}</h6>
            </div>
            <div class="flex-1 p-8 flex flex-col items-center justify-center text-center">
                <div class="h-16 w-16 rounded-full bg-red-100 dark:bg-red-500/20 flex items-center justify-center mb-4">
                    <i class="ph-fill ph-warning text-3xl text-red-600 dark:text-red-500"></i>
                </div>
                <h5 class="text-lg font-bold text-slate-800 dark:text-white mb-2">API Connection Failed</h5>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-4 px-4" data-id="error-message">{ERROR_MESSAGE}</p>
                <code class="text-xs bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg text-slate-500 dark:text-slate-400 font-mono border border-slate-200 dark:border-slate-700 shadow-inner max-w-full overflow-hidden text-ellipsis">{SUB_KEY}</code>
            </div>
        </div>
    </div>
</template>


<div id="calendarModal" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-5xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 px-7 py-5 bg-slate-50/50 dark:bg-slate-800/50">
            <h5 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2" id="calendarModalLabel">
                <i class="ph-fill ph-calendar-check text-indigo-500 text-xl"></i> Injection Log
            </h5>
            <button class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white dark:bg-slate-700 text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:text-white dark:hover:bg-slate-600 shadow-sm transition-all">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="overflow-y-auto p-7 flex-1 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <div class="rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-600 p-5 text-white text-center shadow-lg shadow-indigo-500/30 transform transition hover:-translate-y-1">
                    <div class="bg-white/20 h-10 w-10 rounded-full flex items-center justify-center mx-auto mb-2"><i class="ph-fill ph-database text-xl"></i></div>
                    <h5 class="text-xs font-bold opacity-90 uppercase tracking-widest mb-1">Total Quota</h5>
                    <h4 class="text-3xl font-black tracking-tight" id="cal-total-gb-box">0 GB</h4>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-5 text-white text-center shadow-lg shadow-emerald-500/30 transform transition hover:-translate-y-1">
                    <div class="bg-white/20 h-10 w-10 rounded-full flex items-center justify-center mx-auto mb-2"><i class="ph-fill ph-check-circle text-xl"></i></div>
                    <h5 class="text-xs font-bold opacity-90 uppercase tracking-widest mb-1">Success</h5>
                    <h4 class="text-3xl font-black tracking-tight" id="cal-total-success-box">0</h4>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-red-500 to-red-600 p-5 text-white text-center shadow-lg shadow-red-500/30 transform transition hover:-translate-y-1">
                    <div class="bg-white/20 h-10 w-10 rounded-full flex items-center justify-center mx-auto mb-2"><i class="ph-fill ph-warning-circle text-xl"></i></div>
                    <h5 class="text-xs font-bold opacity-90 uppercase tracking-widest mb-1">Failed</h5>
                    <h4 class="text-3xl font-black tracking-tight" id="cal-total-failed-box">0</h4>
                </div>
            </div>
            <div id="calendar-in-modal" class="bg-white dark:bg-slate-800 rounded-xl p-4 border border-slate-100 dark:border-slate-700 shadow-sm"></div>
        </div>
    </div>
</div>

<div id="detailModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[90vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 px-7 py-5 bg-slate-50 dark:bg-slate-800/50">
            <h5 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="ph-fill ph-list-magnifying-glass text-indigo-500 text-xl"></i> 
                <span id="detailModalLabel">Detail</span>
            </h5>
            <button class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white dark:bg-slate-700 text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:text-white dark:hover:bg-slate-600 shadow-sm transition-all">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="overflow-y-auto p-7 flex-1 custom-scrollbar">
            <div class="loading-spinner flex flex-col items-center justify-center py-16">
                <div class="relative flex h-12 w-12 items-center justify-center mb-4">
                    <div class="absolute h-full w-full animate-ping rounded-full bg-indigo-400 opacity-20 duration-1000"></div>
                    <svg class="relative h-8 w-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>
                <p class="text-sm font-bold text-slate-500 tracking-wide">Fetching Records...</p>
            </div>
            
            <div class="modal-content-container hidden">
                <div class="grid grid-cols-2 gap-5 mb-8">
                    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 p-5 text-center border border-emerald-100 dark:border-emerald-500/20 shadow-sm">
                        <h4 class="text-3xl font-black text-emerald-600 dark:text-emerald-400" id="daily-total-gb">0 GB</h4>
                        <small class="text-xs font-bold text-emerald-800/60 dark:text-emerald-400/70 uppercase tracking-widest mt-1 block">Success Quota</small>
                    </div>
                    <div class="rounded-2xl bg-red-50 dark:bg-red-500/10 p-5 text-center border border-red-100 dark:border-red-500/20 shadow-sm">
                        <h4 class="text-3xl font-black text-red-600 dark:text-red-400" id="daily-total-failed">0</h4>
                        <small class="text-xs font-bold text-red-800/60 dark:text-red-400/70 uppercase tracking-widest mt-1 block">Failed Inject</small>
                    </div>
                </div>
                
                <div id="detailTableContainer" class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm"></div>
            </div>
        </div>
    </div>
</div>

<script>var GLOBAL_TOTAL_COMPANIES = <?php echo count($companies); ?>;</script>

<?php ob_start(); ?>
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

    var templateCardSuccess = $('#template-card-success').html();
    var templateCardError = $('#template-card-error').html();
    var templateCardSummary = $('#template-card-summary').html();

    var calendarInstance = null;
    var currentCalendarProjectId = null;
    var currentRequest = null;

    // --- MODAL LOGIC ---
    $('.btn-close-modal').click(function() {
        $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
        if($('.modal-container.flex').length === 0) $('body').css('overflow', 'auto');
    });
    $('.modal-container').click(function(e) {
        if(e.target === this) {
            $(this).removeClass('flex').addClass('hidden');
            if($('.modal-container.flex').length === 0) $('body').css('overflow', 'auto');
        }
    });

    // --- LOAD DASHBOARD ---
    function updateDatapoolDashboard(companyId = '', projectId = '') {
        if (currentRequest != null) { currentRequest.abort(); currentRequest = null; }
        
        $datapoolLoader.show();
        $filterCompany.prop('disabled', true);
        $filterProject.prop('disabled', true);
        $btnReset.prop('disabled', true);

        var ajaxUrl = 'ajax/get_datapool_cards.php?nocache=' + new Date().getTime();
        if (companyId) ajaxUrl += '&company_id=' + companyId;
        if (projectId) ajaxUrl += '&project_id=' + projectId;

        currentRequest = $.ajax({
            url: ajaxUrl, type: 'GET', dataType: 'json',
            success: function(response) {
                // KUNCI PERBAIKAN DUPLIKAT: 
                // Kosongkan container TEPAT SEBELUM merender data yang berhasil didapat
                activeCharts.forEach(c => { if(c && typeof c.destroy === 'function') c.destroy(); });
                activeCharts = [];
                $datapoolContainer.empty();
                
                $datapoolLoader.hide();
                $datapoolContainer.css('display', 'grid'); 

                if (!response || response.length === 0) {
                    $datapoolContainer.html('<div class="col-span-1 md:col-span-12 animate-fade-in-up"><div class="rounded-2xl bg-indigo-50/50 dark:bg-indigo-900/10 p-8 text-center text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/30 flex flex-col items-center"><div class="h-16 w-16 bg-indigo-100 dark:bg-indigo-800/50 rounded-full flex items-center justify-center mb-4"><i class="ph-fill ph-folder-open text-3xl"></i></div><h4 class="text-lg font-bold mb-1">No Data Available</h4><p class="text-sm opacity-80">There are no projects matching your filter criteria.</p></div></div>');
                    return;
                }
                
                if (companyId === '' && projectId === '') {
                    renderSummaryCard(response);
                } else {
                    var processedIds = new Set();
                    var delayIndex = 0; // Stagger animation
                    response.forEach((p) => {
                        var uniqueKey = String(p.project_id);
                        if (processedIds.has(uniqueKey)) return;
                        processedIds.add(uniqueKey);
                        if (p.error) {
                            renderErrorCard(p, delayIndex++); 
                        } else {
                            renderSuccessCard(p, delayIndex++);
                        }
                    });
                }
            },
            error: function(e) {
                if (e.statusText !== 'abort') {
                    $datapoolLoader.hide();
                    $datapoolContainer.html('<div class="col-span-1 md:col-span-12 animate-fade-in-up"><div class="rounded-2xl bg-red-50 p-6 text-center text-red-600 font-bold border border-red-100 shadow-sm flex flex-col items-center"><i class="ph-fill ph-warning-octagon text-4xl mb-3"></i>Failed to load data. Server Error.</div></div>').css('display', 'grid');
                }
            },
            complete: function() {
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

    function renderSuccessCard(p, index) {
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
        
        // Add dynamic stagger animation
        let $html = $(html);
        $html.css('animation-delay', (index * 0.1) + 's');
        $datapoolContainer.append($html);
        initDonutChart(cvId, s, tp);
    }

    function renderErrorCard(p, index) {
        let html = templateCardError.replace(/{PROJECT_NAME}/g, p.project_name).replace(/{ERROR_MESSAGE}/g, p.message).replace(/{SUB_KEY}/g, p.subscription_key);
        let $html = $(html);
        $html.css('animation-delay', (index * 0.1) + 's');
        $datapoolContainer.append($html);
    }

    // Modern Chart Styling
    function initDonutChart(id, sisa, terpakai) {
        var ctx = document.getElementById(id); if(!ctx || typeof Chart === 'undefined') return;
        if(sisa < 0) sisa = 0; if(terpakai < 0) terpakai = 0;
        let bg = ['#10b981', '#ef4444']; 
        if(sisa == 0 && terpakai == 0) { sisa = 1; bg = ['#e2e8f0', '#e2e8f0']; }
        activeCharts.push(new Chart(ctx, { 
            type: 'doughnut', 
            data: { labels: ['Remaining', 'Used'], datasets: [{ data: [sisa, terpakai], backgroundColor: bg, borderWidth: 0, hoverOffset: 4 }] }, 
            options: { responsive: true, maintainAspectRatio: false, cutoutPercentage: 78, legend: { display: false }, animation: { duration: 1500, easing: 'easeOutQuart' }, tooltips: { backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 12, bodyFontFamily: "'Inter', sans-serif", cornerRadius: 8 } } 
        }));
    }

    function initBarChart(id, sisa, terpakai, total) {
        var ctx = document.getElementById(id); if(!ctx || typeof Chart === 'undefined') return;
        activeCharts.push(new Chart(ctx, { 
            type: 'horizontalBar', 
            data: { labels: ['Total', 'Rem.', 'Used'], datasets: [{ data: [total, sisa, terpakai], backgroundColor: ['#6366f1', '#10b981', '#ef4444'], barPercentage: 0.5, borderRadius: 4 }] }, 
            options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: { xAxes: [{ ticks: { beginAtZero: true, fontColor: '#94a3b8', fontFamily: "'Inter', sans-serif" }, gridLines: {display:false} }], yAxes: [{ ticks: {fontColor: '#64748b', fontFamily: "'Inter', sans-serif", fontStyle: 'bold'}, gridLines: {display:false} }] }, animation: { duration: 1500, easing: 'easeOutQuart' }, tooltips: { backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 12, bodyFontFamily: "'Inter', sans-serif", cornerRadius: 8 } } 
        }));
    }

    // FILTERS
    $filterCompany.on('change', function() {
        var cid = $(this).val(); $filterProject.prop('disabled', true).html('<option>Loading...</option>');
        if(cid) { $.get('ajax/get_projects_by_company.php', {company_id: cid}, function(res) { var html = '<option value="">-- All Projects --</option>'; if(res.projects) { res.projects.forEach(p => { html += `<option value="${p.id}">${p.project_name}</option>`; }); } $filterProject.html(html).prop('disabled', false); }, 'json'); } else { $filterProject.html('<option value="">-- All Projects --</option>'); }
        updateDatapoolDashboard(cid, '');
    });
    $filterProject.on('change', function() { updateDatapoolDashboard($filterCompany.val(), $(this).val()); });
    
    $btnReset.click(function() { 
        if ($filterCompany.find('option[value=""]').length > 0) {
            $filterCompany.val('').trigger('change'); 
        } else {
            $filterCompany.val($filterCompany.find('option:first').val()).trigger('change');
        }
    });

    // CALENDAR MODAL
    $(document).on('click', '.card-header[data-project-id]', function() {
        currentCalendarProjectId = $(this).data('project-id');
        $('#calendarModalLabel').html('<i class="ph-fill ph-calendar-check text-indigo-500 text-xl mr-2"></i> Log: ' + $(this).find('h6').text());
        
        $('body').css('overflow', 'hidden');
        $('#calendarModal').removeClass('hidden').addClass('flex');
        
        setTimeout(() => {
            if(calendarInstance) calendarInstance.destroy();
            var el = document.getElementById('calendar-in-modal');
            if(typeof FullCalendar !== 'undefined') {
                calendarInstance = new FullCalendar.Calendar(el, {
                    themeSystem: 'standard', 
                    initialView: 'dayGridMonth',
                    height: 'auto',
                    events: { url: 'ajax/get_injection_events.php', extraParams: function(){ return {project_id: currentCalendarProjectId}; } },
                    dateClick: function(info) {
                        $('#detailModalLabel').text(info.dateStr); 
                        $('#detailModal .loading-spinner').show(); 
                        $('#detailModal .modal-content-container').hide(); 
                        $('#detailModal').removeClass('hidden').addClass('flex');

                        $.get('ajax/get_injection_details.php', {date: info.dateStr, project_id: currentCalendarProjectId}, function(res){
                            $('#detailModal .loading-spinner').hide(); 
                            $('#detailModal .modal-content-container').show();
                            
                            if(res.status) {
                                $('#daily-total-gb').text(res.totals.total_gb_success + ' GB'); 
                                $('#daily-total-failed').text(res.totals.total_failed);
                                
                                var tr = ''; 
                                res.logs.forEach(l => { 
                                    var bg = l.status == 'SUCCESS' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400 border-red-200 dark:border-red-800'; 
                                    tr += `<tr class="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                            <td class="px-5 py-4 font-mono font-medium text-slate-700 dark:text-slate-300">${l.msisdn_target}</td>
                                            <td class="px-5 py-4 font-semibold text-slate-600 dark:text-slate-400">${l.denom_name}</td>
                                            <td class="px-5 py-4"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold border ${bg}">${l.status}</span></td>
                                           </tr>`; 
                                });
                                $('#detailTableContainer').html(`
                                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300 border-collapse">
                                        <thead class="bg-slate-50 dark:bg-slate-800/80 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                            <tr><th class="px-5 py-4 font-bold">MSISDN</th><th class="px-5 py-4 font-bold">Package</th><th class="px-5 py-4 font-bold">Status</th></tr>
                                        </thead>
                                        <tbody>${tr}</tbody>
                                    </table>
                                `);
                            }
                        }, 'json');
                    },
                    datesSet: function(info) {
                        var d = info.view.currentStart;
                        $.get('ajax/get_injection_monthly_totals.php', {year: d.getFullYear(), month: d.getMonth()+1, project_id: currentCalendarProjectId}, function(res){
                            if(res.status) { 
                                $('#cal-total-gb-box').text(res.totals.total_gb_success + ' GB'); 
                                $('#cal-total-success-box').text(res.totals.total_success); 
                                $('#cal-total-failed-box').text(res.totals.total_failed); 
                            }
                        }, 'json');
                    }
                });
                calendarInstance.render();
            }
        }, 50);
    });

    // Initial Load
    updateDatapoolDashboard($filterCompany.val());
});
</script>

<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>