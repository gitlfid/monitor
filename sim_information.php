<?php
/*
 File: sim_information.php
 ===========================================================
 Status: UPDATED (Multi-tenant / Company Filter Support)
 Theme: Ultra-Modern Tailwind CSS
*/
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
require_once 'includes/config.php'; 
$db = db_connect();

// --- LOGIKA FILTER USER (MULTI-TENANT) ---
$user_company_id = $_SESSION['company_id'] ?? null;

// 1. Ambil Data Company (Filter jika user terbatas)
if ($user_company_id) {
    $stmt_comp = $db->prepare("SELECT * FROM companies WHERE id = ? ORDER BY company_name ASC");
    $stmt_comp->execute([$user_company_id]);
    $companies = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
} else {
    $companies = $db->query("SELECT * FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Ambil Data Batches (History) - Filter jika user terbatas
$sql_batches = "SELECT b.*, c.company_name, p.project_name 
                FROM sim_batches b 
                LEFT JOIN companies c ON b.company_id = c.id 
                LEFT JOIN sim_projects p ON b.project_id = p.id";

if ($user_company_id) {
    $sql_batches .= " WHERE b.company_id = " . intval($user_company_id);
}

$sql_batches .= " ORDER BY b.upload_date DESC";
$batches = $db->query($sql_batches)->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* Upload Zone */
    .upload-zone { border: 2px dashed #c7d2fe; background-color: #f8fafc; transition: all 0.3s ease; cursor: pointer; }
    .upload-zone:hover, .upload-zone.dragover { border-color: #6366f1; background-color: #eef2ff; }
    .dark .upload-zone { background-color: rgba(30, 41, 59, 0.5); border-color: rgba(99, 102, 241, 0.3); }
    .dark .upload-zone:hover, .dark .upload-zone.dragover { border-color: #6366f1; background-color: rgba(99, 102, 241, 0.1); }

    /* Modern Table */
    .table-modern { width: 100%; border-collapse: collapse; }
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.25rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.6); }

    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }

    /* DataTables Pagination Override */
    .dataTables_wrapper .dataTables_paginate { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.35rem; padding-top: 0 !important; }
    .dataTables_wrapper .dataTables_paginate span { display: flex; gap: 0.35rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem 0.85rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: #fff; color: #475569 !important; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-left: 0 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #f8fafc !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #4f46e5 !important; border-color: #4f46e5 !important; color: #fff !important; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button { background: #1e293b; border-color: #334155; color: #cbd5e1 !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #6366f1 !important; border-color: #6366f1 !important; }
    .dataTables_info { font-size: 0.8rem; color: #64748b; margin-top: 1rem; }
</style>

<div id="progress-container" class="hidden fixed inset-0 z-[9999] bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm flex-col items-center justify-center">
    <div class="w-full max-w-md bg-white dark:bg-slate-800 p-8 rounded-3xl shadow-2xl border border-slate-100 dark:border-slate-700 text-center">
        <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-indigo-600 mx-auto mb-6"></div>
        <h5 class="text-xl font-black text-slate-800 dark:text-white mb-2" id="progress-title">Processing Upload</h5>
        <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3 mb-3 overflow-hidden">
            <div id="progress-bar" class="bg-indigo-600 h-3 rounded-full transition-all duration-300 relative overflow-hidden" style="width: 0%">
                <div class="absolute top-0 left-0 w-full h-full bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+PGRlZnM+PHBhdHRlcm4gaWQ9InBhdHRlcm4iIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTTAgNDBsNDAtNDBIMjBMMCAyMHptNDAgMEg2MEwwIDIweiIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjIpIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI3BhdHRlcm4pIi8+PC9zdmc+')] animate-[slide_1s_linear_infinite]"></div>
            </div>
        </div>
        <div class="flex justify-between items-center text-xs font-bold text-slate-500 uppercase tracking-widest">
            <span id="progress-status">Initializing...</span>
            <span id="progress-pct" class="text-indigo-600 dark:text-indigo-400">0%</span>
        </div>
    </div>
</div>
<style>@keyframes slide { from { background-position: 0 0; } to { background-position: 40px 0; } }</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">
            SIM Information Center
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-database text-lg text-indigo-500"></i> Batch Upload & Advanced Global Search
        </p>
    </div>
</div>

<div class="flex gap-2 border-b border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
    <button onclick="switchTab('list')" id="tab-btn-list" class="px-6 py-3.5 text-sm font-bold border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-magnifying-glass"></i> Data & Search
    </button>
    <button onclick="switchTab('upload')" id="tab-btn-upload" class="px-6 py-3.5 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-cloud-arrow-up"></i> New Upload
    </button>
</div>

<div id="tab-content-list" class="block animate-fade-in-up" style="animation-delay: 0.2s;">
    
    <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 mb-8 overflow-hidden">
        <div class="px-8 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" onclick="$('#searchCollapse').slideToggle(); $('#searchIcon').toggleClass('ph-caret-down ph-caret-up');">
            <h5 class="text-base font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-2"><i class="ph-bold ph-funnel text-xl"></i> Advanced Search Filter</h5>
            <i id="searchIcon" class="ph-bold ph-caret-down text-slate-400"></i>
        </div>
        
        <div id="searchCollapse" class="hidden">
            <form id="searchForm" class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Company</label>
                        <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-500 dark:text-white" name="company_id" id="search_company_id" onchange="loadProjectsAndBatches(this.value)">
                            <?php if (!$user_company_id): ?><option value="">-- All Companies --</option><?php endif; ?>
                            <?php foreach($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($user_company_id == $c['id']) ? 'selected' : '' ?>><?= $c['company_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Project</label>
                        <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-500 dark:text-white" name="project_id" id="search_project_id" onchange="loadBatches($('#search_company_id').val(), this.value)">
                            <option value="">-- All Projects --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Batch Name</label>
                        <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-500 dark:text-white" name="batch_search" id="search_batch_name">
                            <option value="">-- All Batches --</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">General Search</label>
                        <input type="text" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 dark:text-white" name="general_search" placeholder="MSISDN, ICCID, IMSI, or SN...">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-2"><i class="ph-fill ph-list-numbers"></i> Bulk Search (Multiple)</label>
                    <textarea class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm outline-none focus:border-indigo-500 dark:text-white custom-scrollbar resize-none" name="bulk_search" rows="3" placeholder="Paste multiple numbers here (separated by comma, space, or newline)..."></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" class="px-6 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all border border-slate-200 dark:border-slate-700" id="btn-reset-search">Reset Filter</button>
                    <button type="button" class="bg-indigo-600 text-white px-8 py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-indigo-700 transition-all active:scale-95 flex items-center gap-2" id="btn-do-search"><i class="ph-bold ph-magnifying-glass"></i> Search Data</button>
                </div>
            </form>
        </div>
    </div>

    <div id="historyView" class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
            <div class="h-10 w-10 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 rounded-xl flex items-center justify-center text-xl"><i class="ph-fill ph-clock-counter-clockwise"></i></div>
            <h4 class="text-lg font-bold text-slate-800 dark:text-white">Upload History Logs</h4>
        </div>
        
        <div class="overflow-x-auto w-full">
            <table class="table-modern text-left" id="table-batches">
                <thead>
                    <tr>
                        <th class="ps-8 w-40">Upload Date</th>
                        <th>Target Info</th>
                        <th class="text-center">Batch Name</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-center">Documents</th>
                        <th class="text-center pe-8">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach($batches as $b): ?>
                    <tr class="table-row-hover transition-colors">
                        <td class="ps-8">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-300"><?= date('d M Y', strtotime($b['upload_date'])) ?></span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mt-0.5"><?= date('H:i', strtotime($b['upload_date'])) ?> WIB</span>
                        </td>
                        <td>
                            <div class="flex flex-col">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400 text-sm"><?= htmlspecialchars($b['company_name'] ?? '-') ?></span>
                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400"><i class="ph-fill ph-folder-open text-amber-500 mr-1"></i> <?= htmlspecialchars($b['project_name'] ?? '-') ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-black uppercase tracking-widest cursor-pointer hover:border-indigo-400 transition-colors shadow-sm" onclick="viewBatchDetails(<?= $b['id'] ?>, '<?= htmlspecialchars($b['batch_name'], ENT_QUOTES) ?>')">
                                <i class="ph-fill ph-stack"></i> <?= htmlspecialchars($b['batch_name']) ?>
                            </span>
                        </td>
                        <td class="text-right">
                            <span class="font-mono font-black text-lg text-slate-800 dark:text-white"><?= number_format($b['quantity'] ?? 0) ?></span>
                        </td>
                        <td class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <?php if($b['po_client_file']): ?>
                                    <a href="<?= $b['po_client_file'] ?>" target="_blank" class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-100 transition-colors" title="Client PO"><i class="ph-fill ph-file-text text-lg"></i></a>
                                <?php endif; ?>
                                <?php if($b['po_linksfield_file']): ?>
                                    <a href="<?= $b['po_linksfield_file'] ?>" target="_blank" class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-100 transition-colors" title="LinksField PO"><i class="ph-fill ph-file-pdf text-lg"></i></a>
                                <?php endif; ?>
                                <?php if(!$b['po_client_file'] && !$b['po_linksfield_file']): ?>
                                    <span class="text-slate-300 dark:text-slate-600">-</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="pe-8 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick="viewBatchDetails(<?= $b['id'] ?>, '<?= htmlspecialchars($b['batch_name'], ENT_QUOTES) ?>')" class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-indigo-600 transition-colors flex items-center justify-center shadow-sm" title="View Detail"><i class="ph-bold ph-eye text-lg"></i></button>
                                <?php if (!$user_company_id): ?>
                                    <button onclick="bukaModalEdit(<?= $b['id'] ?>)" class="h-9 w-9 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 text-amber-600 hover:bg-amber-100 transition-colors flex items-center justify-center shadow-sm" title="Edit Batch"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                                    <button onclick="deleteBatch(<?= $b['id'] ?>)" class="h-9 w-9 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 text-red-600 hover:bg-red-100 transition-colors flex items-center justify-center shadow-sm" title="Delete Batch"><i class="ph-fill ph-trash text-lg"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="searchView" class="hidden rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-5 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
            <h4 class="text-lg font-bold text-indigo-600 dark:text-indigo-400 flex items-center gap-2"><i class="ph-fill ph-magnifying-glass"></i> Search Results</h4>
            <button onclick="$('#btn-reset-search').click()" class="px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 shadow-sm hover:bg-slate-50 transition-all flex items-center gap-2"><i class="ph-bold ph-arrow-left"></i> Back to History</button>
        </div>
        <div class="p-6 overflow-x-auto w-full">
            <table class="table-modern text-left" id="table-search-results">
                <thead>
                    <tr>
                        <th class="ps-4">MSISDN</th>
                        <th>ICCID</th>
                        <th>IMSI</th>
                        <th>SN</th>
                        <th class="text-center">Batch</th>
                        <th class="pe-4">Project</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800"></tbody>
            </table>
        </div>
    </div>

</div>

<div id="tab-content-upload" class="hidden animate-fade-in-up" style="animation-delay: 0.2s;">
    <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50">
            <h4 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-cloud-arrow-up text-indigo-500 text-2xl"></i> Form Upload Report</h4>
            <p class="text-xs text-slate-500 mt-1">Assign data to a specific company and project.</p>
        </div>
        
        <form id="uploadForm" class="p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Target Company *</label>
                        <div class="flex gap-2">
                            <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 shadow-sm dark:text-white" name="company_id" id="upload_company_id" onchange="loadProjects(this.value)" required>
                                <?php if (!$user_company_id): ?><option value="">-- Select --</option><?php endif; ?>
                                <?php foreach($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($user_company_id == $c['id']) ? 'selected' : '' ?>><?= $c['company_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$user_company_id): ?>
                                <button type="button" class="h-[46px] w-[46px] rounded-xl bg-slate-100 border border-slate-200 text-indigo-600 flex items-center justify-center shrink-0 hover:bg-indigo-50 transition-colors" onclick="showModal('crudModal', 'company', 'add')"><i class="ph-bold ph-plus text-lg"></i></button>
                                <button type="button" class="h-[46px] w-[46px] rounded-xl bg-slate-100 border border-slate-200 text-red-500 flex items-center justify-center shrink-0 hover:bg-red-50 transition-colors" onclick="deleteData('company')"><i class="ph-fill ph-trash text-lg"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Target Project *</label>
                        <div class="flex gap-2">
                            <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 shadow-sm disabled:opacity-50 disabled:bg-slate-50 dark:text-white" name="project_id" id="upload_project_id" disabled required>
                                <option value="">-- Select Company First --</option>
                            </select>
                            <?php if (!$user_company_id): ?>
                                <button type="button" class="h-[46px] w-[46px] rounded-xl bg-slate-100 border border-slate-200 text-indigo-600 flex items-center justify-center shrink-0 hover:bg-indigo-50 transition-colors" onclick="showModal('crudModal', 'project', 'add')"><i class="ph-bold ph-plus text-lg"></i></button>
                                <button type="button" class="h-[46px] w-[46px] rounded-xl bg-slate-100 border border-slate-200 text-red-500 flex items-center justify-center shrink-0 hover:bg-red-50 transition-colors" onclick="deleteData('project')"><i class="ph-fill ph-trash text-lg"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Auto Batch Name</label>
                        <input type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-500 shadow-sm cursor-not-allowed" name="batch_name" id="batch_name" readonly placeholder="Will be generated automatically">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Source File (.CSV / .XLSX) *</label>
                    <div class="upload-zone relative rounded-2xl flex flex-col items-center justify-center h-[230px] text-center" id="dropZoneContainer" onclick="$('#excel_file').click()">
                        <input type="file" id="excel_file" class="hidden" accept=".xlsx,.xls,.csv" onchange="updateFileName(this)">
                        
                        <div class="pointer-events-none flex flex-col items-center" id="uploadStateIdle">
                            <i class="ph-fill ph-file-xls text-5xl text-indigo-300 dark:text-indigo-500/50 mb-3 transition-transform group-hover:-translate-y-1"></i>
                            <h5 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Click or drag file here</h5>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 bg-white dark:bg-slate-800 px-2 py-1 rounded shadow-sm mt-2">Format: MSISDN, IMSI, ICCID...</p>
                        </div>

                        <div class="pointer-events-none flex flex-col items-center hidden" id="uploadStateSelected">
                            <i class="ph-fill ph-file-text text-5xl text-emerald-400 mb-3"></i>
                            <h5 class="text-sm font-black text-emerald-600 dark:text-emerald-400 mb-1 break-all px-4" id="fileNameDisplay">filename.csv</h5>
                            <p class="text-xs font-medium text-slate-500">Ready to preview</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" class="rounded-xl bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-6 py-3 text-sm font-bold transition-all shadow-sm w-full md:w-auto" id="btn-preview">Validate & Preview Data</button>
                    </div>
                </div>
            </div>

            <div id="preview-section" class="hidden border-t border-slate-200 dark:border-slate-700 pt-8 mt-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 p-5 rounded-2xl flex flex-col justify-center items-center text-center">
                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-1">Total Valid Rows</span>
                        <h3 class="text-4xl font-black text-indigo-600 dark:text-indigo-400" id="disp_qty">0</h3>
                        <input type="hidden" id="quantity" name="quantity"><input type="hidden" id="temp_csv_path">
                    </div>
                    
                    <div class="md:col-span-2 grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-700">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Client PO Document (Optional)</label>
                            <input type="file" name="po_client_file" class="w-full text-xs mb-2 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 cursor-pointer" onchange="autoFillPO(this,'po_client_number')">
                            <input type="text" name="po_client_number" id="po_client_number" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs outline-none focus:border-indigo-500" placeholder="Auto-fill from filename">
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-700">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">LinksField PO Document (Optional)</label>
                            <input type="file" name="po_linksfield_file" class="w-full text-xs mb-2 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 cursor-pointer" onchange="autoFillPO(this,'po_linksfield_number')">
                            <input type="text" name="po_linksfield_number" id="po_linksfield_number" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs outline-none focus:border-indigo-500" placeholder="Auto-fill from filename">
                        </div>
                    </div>
                </div>

                <div class="border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden mb-6">
                    <div class="bg-slate-100 dark:bg-slate-800 px-4 py-2 border-b border-slate-200 dark:border-slate-700"><span class="text-xs font-bold text-slate-600 dark:text-slate-300">Data Preview (Top 5 rows)</span></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 dark:bg-slate-900 text-slate-500 font-bold uppercase tracking-wider">
                                <tr><th class="px-4 py-2">#</th><th class="px-4 py-2">MSISDN</th><th class="px-4 py-2">IMSI</th><th class="px-4 py-2">SN</th><th class="px-4 py-2">ICCID</th></tr>
                            </thead>
                            <tbody id="preview-body" class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-800"></tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-700">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="checkConfirm" class="w-5 h-5 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-300">I confirm the data and mapping are correct</span>
                    </label>
                    <button type="button" id="btn-start-upload" class="rounded-xl bg-emerald-500 text-white px-8 py-3 text-sm font-black uppercase tracking-wider shadow-lg hover:bg-emerald-600 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="ph-bold ph-upload-simple mr-1"></i> Start Upload
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<div id="modalEditBatch" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl flex flex-col overflow-hidden border border-slate-200 modal-animate-in">
        <div class="flex items-center justify-between border-b border-amber-500 px-7 py-5 bg-gradient-to-r from-amber-500 to-amber-600 text-white">
            <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-fill ph-pencil-simple text-2xl"></i> Edit Batch Metadata</h5>
            <button type="button" onclick="hideModal('modalEditBatch')" class="text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <form id="formEditBatch" class="p-8 space-y-5 bg-slate-50/50">
            <input type="hidden" name="action" value="edit_batch"><input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Company</label>
                    <select class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold outline-none focus:border-amber-500 shadow-sm" name="company_id" id="edit_company_id" onchange="loadProjects(this.value, 'edit_project_id')">
                        <?php foreach($companies as $c): ?><option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Project</label>
                    <div class="flex gap-2">
                        <select class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold outline-none focus:border-amber-500 shadow-sm" name="project_id" id="edit_project_id"></select>
                        <button type="button" class="px-3 rounded-xl bg-slate-100 border border-slate-200 text-amber-600 hover:bg-amber-50" onclick="showModal('crudModal', 'project', 'add')"><i class="ph-bold ph-plus"></i></button>
                    </div>
                </div>
            </div>
            <div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Batch Name</label><input type="text" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold outline-none focus:border-amber-500 shadow-sm" name="batch_name" id="edit_batch_name"></div>
            <div class="grid grid-cols-2 gap-5 p-4 bg-white rounded-2xl border border-slate-200">
                <div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Update PO Client</label><input type="file" name="po_client_file" class="w-full text-xs mb-2" onchange="autoFillPO(this,'edit_po_client_number')"><input type="text" name="po_client_number" id="edit_po_client_number" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs outline-none focus:border-amber-500"></div>
                <div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Update PO LinksField</label><input type="file" name="po_linksfield_file" class="w-full text-xs mb-2" onchange="autoFillPO(this,'edit_po_linksfield_number')"><input type="text" name="po_linksfield_number" id="edit_po_linksfield_number" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs outline-none focus:border-amber-500"></div>
            </div>
        </form>
        <div class="border-t border-slate-200 p-5 bg-white flex justify-end gap-3">
            <button type="button" onclick="hideModal('modalEditBatch')" class="px-6 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 transition-all">Cancel</button>
            <button type="button" onclick="simpanEdit()" class="bg-amber-500 text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md hover:bg-amber-600 transition-all">Save Changes</button>
        </div>
    </div>
</div>

<div id="crudModal" class="modal-container fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-sm rounded-3xl bg-white shadow-2xl flex flex-col overflow-hidden border border-slate-200 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-6 py-4 bg-indigo-600 text-white">
            <h5 class="text-base font-bold" id="crudTitle">Form</h5>
            <button type="button" onclick="hideModal('crudModal')" class="text-white/70 hover:text-white transition-all"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="p-6 bg-slate-50/50">
            <input type="hidden" id="crud_type"><input type="hidden" id="crud_action"><input type="hidden" id="crud_id">
            <div id="div_company_select" class="mb-4 hidden">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Select Company</label>
                <select id="modal_company_id" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-500 shadow-sm">
                    <?php foreach($companies as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Name</label>
                <input type="text" id="crud_name" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-500 shadow-sm">
            </div>
        </div>
        <div class="border-t border-slate-200 p-4 bg-white flex justify-end gap-2">
            <button type="button" onclick="hideModal('crudModal')" class="px-5 py-2 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 transition-all">Cancel</button>
            <button type="button" onclick="submitCrud()" class="bg-indigo-600 text-white px-6 py-2 rounded-xl text-sm font-bold shadow-md hover:bg-indigo-700 transition-all">Save Data</button>
        </div>
    </div>
</div>

<div id="detailModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-5xl rounded-3xl bg-white shadow-2xl flex flex-col h-[85vh] overflow-hidden border border-slate-200 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-8 py-5 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white shrink-0">
            <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-list-magnifying-glass text-2xl"></i> <span id="detailModalLabel">Details</span></h5>
            <button type="button" onclick="hideModal('detailModal')" class="text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="flex-1 overflow-hidden flex flex-col p-6 bg-slate-50/50">
            <div class="bg-white border border-slate-200 rounded-2xl flex-1 overflow-hidden flex flex-col shadow-sm">
                <div class="overflow-x-auto w-full flex-1 p-2">
                    <table class="w-full text-left table-modern" id="table-detail-content">
                        <thead class="bg-slate-50"><tr><th class="ps-6">No</th><th>MSISDN</th><th>IMSI</th><th>SN</th><th>ICCID</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="border-t border-slate-200 p-5 bg-white flex justify-end shrink-0">
            <button type="button" onclick="hideModal('detailModal')" class="px-8 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100 transition-all border border-slate-200">Close Panel</button>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="assets/extensions/jquery/jquery.min.js"></script>
<script src="assets/extensions/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>

<script>
const API_URL = 'api_sim_upload.php';
const CHUNK_SIZE = 1000; 
var tableDetail;

// Tailwind Tab Toggle
function switchTab(tabId) {
    if(tabId === 'list') {
        $('#tab-btn-list').addClass('border-indigo-600 text-indigo-600 dark:text-indigo-400').removeClass('border-transparent text-slate-500');
        $('#tab-btn-upload').removeClass('border-indigo-600 text-indigo-600 dark:text-indigo-400').addClass('border-transparent text-slate-500');
        $('#tab-content-list').removeClass('hidden').addClass('block');
        $('#tab-content-upload').removeClass('block').addClass('hidden');
    } else {
        $('#tab-btn-upload').addClass('border-indigo-600 text-indigo-600 dark:text-indigo-400').removeClass('border-transparent text-slate-500');
        $('#tab-btn-list').removeClass('border-indigo-600 text-indigo-600 dark:text-indigo-400').addClass('border-transparent text-slate-500');
        $('#tab-content-upload').removeClass('hidden').addClass('block');
        $('#tab-content-list').removeClass('block').addClass('hidden');
    }
}

// Tailwind Modal Toggles
function showModal(id, type='', act='') {
    if(id === 'crudModal') {
        $('#crud_type').val(type); $('#crud_action').val(act); $('#crud_name').val(''); 
        $('#crudTitle').text((act==='add'?'Add ':'Edit ') + type.charAt(0).toUpperCase() + type.slice(1));
        if(type==='project' && act==='add') $('#div_company_select').removeClass('hidden'); else $('#div_company_select').addClass('hidden');
    }
    $('#' + id).removeClass('hidden').addClass('flex');
    $('body').css('overflow', 'hidden');
}

function hideModal(id) {
    $('#' + id).removeClass('flex').addClass('hidden');
    $('body').css('overflow', 'auto');
}

// Drag & Drop UI Update
function updateFileName(input) {
    if (input.files && input.files[0]) {
        $('#fileNameDisplay').text(input.files[0].name);
        $('#uploadStateIdle').addClass('hidden');
        $('#uploadStateSelected').removeClass('hidden');
    } else {
        $('#uploadStateIdle').removeClass('hidden');
        $('#uploadStateSelected').addClass('hidden');
    }
}

$(document).ready(function() {
    
    // Custom Search Datatables DOM
    const dtDom = 't<"dataTables_wrapper"<"flex flex-col sm:flex-row justify-between items-center p-4 border-t border-slate-100"ip>>';

    if($.fn.DataTable) { 
        $('#table-batches').DataTable({ 
            "order": [[ 0, "desc" ]], "pageLength": 10, dom: dtDom,
            language: { search: '', emptyTable: '<div class="text-center py-8 text-slate-400 font-bold">No batches uploaded yet.</div>' }
        }); 
    }
    
    var initialCid = $('#search_company_id').val();
    loadBatches(initialCid, '');
    if (initialCid) {
        loadProjects(initialCid, 'search_project_id');
        loadProjects(initialCid, 'upload_project_id');
    }

    $('#upload_project_id').change(function(){ 
        let pid=$(this).val(); 
        if(pid) $.post(API_URL,{action:'get_next_batch_name',project_id:pid},function(res){if(res.status)$('#batch_name').val(res.next_name)},'json'); 
    });

    // SEARCH LOGIC
    $('#btn-do-search').click(function() {
        let btn = $(this); let ori = btn.html(); btn.html('<i class="ph-bold ph-spinner animate-spin"></i> Searching...').prop('disabled', true);
        $('#historyView').hide(); $('#searchView').fadeIn();
        
        if ($.fn.DataTable.isDataTable('#table-search-results')) { $('#table-search-results').DataTable().destroy(); }

        $('#table-search-results').DataTable({
            "processing": true, "serverSide": true, "pageLength": 50, "lengthMenu": [[50, 100, 200, 500], [50, 100, 200, 500]],
            dom: dtDom,
            "ajax": {
                "url": API_URL, "type": "POST",
                "data": function(d) {
                    d.action = 'search_sim_data'; d.company_id = $('#search_company_id').val(); d.project_id = $('#search_project_id').val();
                    d.batch_search = $('#search_batch_name').val(); d.general_search = $('input[name="general_search"]').val(); d.bulk_search = $('textarea[name="bulk_search"]').val();
                }
            },
            "columns": [
                { "data": 0, "className": "ps-4 font-mono font-bold" }, 
                { "data": 1, "className": "text-xs" }, 
                { "data": 2, "className": "text-xs" }, 
                { "data": 3, "className": "text-xs" }, 
                { "data": 4, "className": "text-center", "render": function(d){ return '<span class="px-2 py-1 rounded bg-slate-100 text-[10px] font-black uppercase border border-slate-200 text-slate-600">'+d+'</span>'} }, 
                { "data": 5, "className": "pe-4 font-bold text-sm text-indigo-600" }
            ]
        });
        btn.html(ori).prop('disabled', false);
    });

    $('#btn-reset-search').click(function() {
        var $comp = $('#search_company_id');
        if ($comp.find('option[value=""]').length > 0) { $comp.val(''); } else { $comp.val($comp.find('option:first').val()); }
        $('#search_project_id').val(''); $('#search_batch_name').val(''); $('input[name="general_search"]').val(''); $('textarea[name="bulk_search"]').val('');
        $('#table-search-results').DataTable().clear().destroy();
        $('#searchView').hide(); $('#historyView').fadeIn();
    });

    // PREVIEW & UPLOAD
    $('#btn-preview').click(function(e) {
        e.stopPropagation();
        let file = $('#excel_file')[0].files[0];
        if(!file) return alert("Please select a file!");
        let btn = $(this).html('Loading...').prop('disabled', true);
        let fd = new FormData(); fd.append('action', 'preview_excel'); fd.append('excel_file', file);
        $.ajax({
            url: API_URL, type: 'POST', data: fd, contentType:false, processData:false, dataType:'json',
            success: function(res){
                btn.html('Validate & Preview Data').prop('disabled', false);
                if(res.status) {
                    $('#preview-section').slideDown(); $('#quantity').val(res.quantity); $('#disp_qty').text(res.quantity.toLocaleString()); $('#temp_csv_path').val(res.temp_csv_path);
                    if(res.po_client_val) $('#po_client_number').val(res.po_client_val); if(res.po_lf_val) $('#po_linksfield_number').val(res.po_lf_val);
                    let h=''; res.preview_rows.forEach((r,i)=>h+=`<tr class="hover:bg-slate-50"><td class="px-4 py-2 border-b border-slate-100">${i+1}</td><td class="px-4 py-2 font-mono font-bold text-indigo-600 border-b border-slate-100">${r.msisdn}</td><td class="px-4 py-2 border-b border-slate-100">${r.imsi}</td><td class="px-4 py-2 border-b border-slate-100">${r.sn}</td><td class="px-4 py-2 border-b border-slate-100">${r.iccid}</td></tr>`);
                    $('#preview-body').html(h);
                } else { alert(res.message); }
            }, error: function(){ btn.html('Retry').prop('disabled',false); alert('Connection Error'); }
        });
    });

    $('#checkConfirm').change(function(){ 
        let btn = $('#btn-start-upload');
        btn.prop('disabled', !this.checked); 
        if(this.checked) btn.removeClass('opacity-50 cursor-not-allowed'); else btn.addClass('opacity-50 cursor-not-allowed');
    });

    $('#btn-start-upload').click(async function() {
        if(!confirm("Start Upload Process?")) return;
        $('#progress-container').removeClass('hidden').addClass('flex'); $(this).prop('disabled', true);
        try {
            let fd = new FormData($('#uploadForm')[0]); fd.append('action', 'save_batch_header');
            let head = await $.ajax({url:API_URL, type:'POST', data:fd, contentType:false, processData:false});
            if(!head.status) throw new Error(head.message);
            let bid=head.batch_id, total=parseInt($('#quantity').val()), csv=$('#temp_csv_path').val(), proc=0;
            while(proc < total) {
                let pct = Math.round((proc/total)*100); $('#progress-bar').css('width', pct+'%'); $('#progress-pct').text(pct+'%'); $('#progress-status').text(`Processing row ${proc} of ${total}`);
                let res = await $.post(API_URL, {action:'process_chunk', batch_id:bid, csv_path:csv, start_line:proc, chunk_size:CHUNK_SIZE}, null, 'json');
                if(!res.status) throw new Error(res.message);
                proc += res.processed_count; if(res.processed_count==0) break;
            }
            $('#progress-bar').css('width', '100%'); $('#progress-pct').text('100%'); $('#progress-status').text('Finalizing...');
            $.post(API_URL, {action:'delete_temp_file', csv_path:csv});
            setTimeout(()=>{alert('Upload Success!'); location.reload();}, 1000);
        } catch(e) { alert(e.message); $('#progress-container').addClass('hidden').removeClass('flex'); $(this).prop('disabled',false); }
    });

    // Drag Drop Zone Events
    const dropZone = document.getElementById('dropZoneContainer');
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { dropZone.addEventListener(eventName, preventDefaults, false); });
    function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
    ['dragenter', 'dragover'].forEach(eventName => { dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false); });
    ['dragleave', 'drop'].forEach(eventName => { dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false); });
    dropZone.addEventListener('drop', handleDrop, false);
    function handleDrop(e) {
        let dt = e.dataTransfer; let files = dt.files;
        if(files.length > 0) {
            document.getElementById('csv_file').files = files;
            updateFileName(document.getElementById('csv_file'));
        }
    }
});

function loadProjectsAndBatches(cid) { loadProjects(cid, 'search_project_id'); loadBatches(cid, ''); }

function loadProjects(cid, target='upload_project_id', selId=null) {
    let $el = $('#'+target).html('<option>Loading...</option>').prop('disabled', true);
    if(!cid) { $el.html('<option value="">-- Select Company First --</option>'); return; }
    $.post(API_URL, {action:'get_projects', company_id:cid}, function(res){
        let h = '<option value="">-- Select Project --</option>';
        if(res.status && res.data) res.data.forEach(p => { let s = (selId == p.id) ? 'selected' : ''; h += `<option value="${p.id}" ${s}>${p.project_name}</option>`; });
        $el.html(h).prop('disabled', false);
    }, 'json');
    if(target == 'search_project_id') { loadBatches(cid, ''); }
}

function loadBatches(cid, pid) {
    let $el = $('#search_batch_name').html('<option>Loading...</option>').prop('disabled', true);
    $.post(API_URL, {action:'get_batches', company_id:cid, project_id:pid}, function(res){
        let h = '<option value="">-- All Batches --</option>';
        if(res.status && res.data) res.data.forEach(b => { h += `<option value="${b.batch_name}">${b.batch_name}</option>`; });
        $el.html(h).prop('disabled', false);
    }, 'json');
}

function bukaModalEdit(id) {
    $.post(API_URL, {action:'get_batch_header', id:id}, function(res){
        if(res.status) {
            let d = res.data;
            $('#edit_id').val(d.id); $('#edit_company_id').val(d.company_id); $('#edit_batch_name').val(d.batch_name);
            $('#edit_po_client_number').val(d.po_client_number); $('#edit_po_linksfield_number').val(d.po_linksfield_number);
            loadProjects(d.company_id, 'edit_project_id', d.project_id);
            showModal('modalEditBatch');
        } else { alert(res.message); }
    }, 'json').fail(function(){ alert("Connection Error"); });
}

function simpanEdit() {
    let fd = new FormData(document.getElementById('formEditBatch'));
    $.ajax({ url: API_URL, type: 'POST', data: fd, contentType: false, processData: false, dataType: 'json', success: function(res) { alert(res.message); if(res.status) location.reload(); }, error: function() { alert('Error'); } });
}

function viewBatchDetails(id,name) {
    $('#detailModalLabel').text('Details: '+name);
    showModal('detailModal');
    if($.fn.DataTable.isDataTable('#table-detail-content')) $('#table-detail-content').DataTable().destroy();
    tableDetail=$('#table-detail-content').DataTable({
        processing:true, serverSide:true, pageLength:50, lengthMenu:[[50,100,200,500],[50,100,200,500]],
        dom: 't<"dataTables_wrapper"<"flex justify-between p-4"ip>>',
        ajax:{url:API_URL,type:'POST',data:function(d){d.action='get_batch_details_server_side';d.batch_id=id;}},
        columns:[
            {className:'ps-6 text-slate-500',orderable:false},
            {className:'font-mono font-bold text-indigo-600', orderable:true},
            {className:'text-xs text-slate-600', orderable:true},
            {className:'text-xs text-slate-600', orderable:true},
            {className:'text-xs font-mono', orderable:true}
        ]
    });
}

function deleteBatch(id) { if(confirm("Are you sure you want to delete this batch entirely?")) $.post(API_URL, {action:'delete_batch', id:id}, function(){location.reload()}, 'json'); }
function autoFillPO(inpt, tgt) { if(inpt.files[0]) $('#'+tgt).val(inpt.files[0].name.replace(/\.[^/.]+$/, "")); }
function submitCrud() { let d={action:$('#crud_action').val()+'_'+$('#crud_type').val(), name:$('#crud_name').val(), company_id:$('#modal_company_id').val(), id:$('#crud_id').val()}; $.post(API_URL,d,function(r){alert(r.message);if(r.status)location.reload();},'json'); }
function deleteData(type) { let id=(type=='company')?$('#upload_company_id').val():$('#upload_project_id').val(); if(!id)return alert('Select data to delete'); if(confirm('Delete?'))$.post(API_URL,{action:'delete_'+type,id:id},function(r){alert(r.message);if(r.status)location.reload();},'json'); }
</script>

<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>