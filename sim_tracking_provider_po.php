<?php
// =========================================================================
// FILE: sim_tracking_provider_po.php
// DESC: Provider Purchase Order Management (Ultra-Modern Tailwind CSS Theme)
// =========================================================================
ini_set('display_errors', 0); // Set 0 untuk production
error_reporting(E_ALL);
$current_page = 'sim_tracking_provider_po.php';

if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// Database Connection
$db = null; $db_type = '';
$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { if (isset($$var)) { if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } } if (isset($GLOBALS[$var])) { if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } } }
if (!$db && defined('DB_HOST')) { try { $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db_type = 'pdo'; } catch (Exception $e) {} }

// Helper untuk Keamanan
if (!function_exists('e')) {
    function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

// FETCH DATA
$data = [];
$chart_data_grouped = [];

try {
    // Query Utama: Provider PO + Detail Linked Client PO (Termasuk Project Name)
    $sql = "SELECT st.*, 
            COALESCE(c.company_name, st.manual_company_name) as display_provider,
            
            -- Detail Linked Client PO
            linked.po_number as linked_po_number,
            linked.po_date as linked_po_date,
            linked.sim_qty as linked_sim_qty,
            linked.batch_name as linked_batch_name,
            linked.po_file as linked_po_file,
            COALESCE(linked_comp.company_name, linked.manual_company_name) as linked_client_name,
            COALESCE(linked_proj.project_name, linked.manual_project_name) as linked_project_name

            FROM sim_tracking_po st
            LEFT JOIN companies c ON st.company_id = c.id
            LEFT JOIN sim_tracking_po linked ON st.link_client_po_id = linked.id
            LEFT JOIN companies linked_comp ON linked.company_id = linked_comp.id
            LEFT JOIN projects linked_proj ON linked.project_id = linked_proj.id
            
            WHERE st.type = 'provider' 
            ORDER BY st.id DESC";

    if ($db_type === 'pdo') {
        $data = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = mysqli_query($db, $sql);
        if ($res) { while ($row = mysqli_fetch_assoc($res)) $data[] = $row; }
    }

    // --- LOGIKA GRAFIK (GROUP BY DATE) ---
    foreach ($data as $row) {
        $raw_date = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
        $date_label = date('d M Y', strtotime($raw_date));
        $qty = (int)preg_replace('/[^0-9]/', '', $row['sim_qty']);

        if (!isset($chart_data_grouped[$date_label])) $chart_data_grouped[$date_label] = 0;
        $chart_data_grouped[$date_label] += $qty;
    }
    // Ambil 10 data terakhir & balik urutan
    $chart_data_grouped = array_reverse(array_slice($chart_data_grouped, 0, 10, true));

} catch (Exception $e) {}

$js_chart_labels = array_keys($chart_data_grouped);
$js_chart_series = array_values($chart_data_grouped);

// FETCH DROPDOWN
$providers = []; $clients = []; $client_pos = [];
try {
    if ($db_type === 'pdo') {
        $providers = $db->query("SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($providers)) $providers = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $clients = $db->query("SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // List PO Client untuk Dropdown Link
        $client_pos = $db->query("SELECT st.id, st.po_number, st.company_id, st.batch_name, COALESCE(c.company_name, st.manual_company_name) as display_client_name FROM sim_tracking_po st LEFT JOIN companies c ON st.company_id = c.id WHERE st.type='client' ORDER BY st.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $r1 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC");
        while($x=mysqli_fetch_assoc($r1)) $providers[]=$x;
        if(empty($providers)) { $r1b=mysqli_query($db,"SELECT id, company_name FROM companies ORDER BY company_name ASC"); while($x=mysqli_fetch_assoc($r1b)) $providers[]=$x; }
        
        $r2 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC");
        while($x=mysqli_fetch_assoc($r2)) $clients[]=$x;
        
        $r3 = mysqli_query($db, "SELECT st.id, st.po_number, st.company_id, st.batch_name, COALESCE(c.company_name, st.manual_company_name) as display_client_name FROM sim_tracking_po st LEFT JOIN companies c ON st.company_id = c.id WHERE st.type='client' ORDER BY st.id DESC");
        while($x=mysqli_fetch_assoc($r3)) $client_pos[]=$x;
    }
} catch (Exception $e) {}
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* Table Core Formatting */
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.5rem 1rem; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.5); }

    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }

    /* Datatables Pagination Symmetry */
    .dataTables_wrapper .dataTables_paginate { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.35rem; padding-top: 0 !important; }
    .dataTables_wrapper .dataTables_paginate span { display: flex; gap: 0.35rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem 0.85rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: #fff; color: #475569 !important; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-left: 0 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #f8fafc !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; background: #f8fafc !important; border-color: #e2e8f0 !important; color: #94a3b8 !important; }
    /* Emerald Active Theme */
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #10b981 !important; border-color: #10b981 !important; color: #fff !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3); }
    
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button { background: #1e293b; border-color: #334155; color: #cbd5e1 !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #334155 !important; border-color: #475569 !important; color: #fff !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #10b981 !important; border-color: #10b981 !important; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-400 tracking-tight">
            Provider PO Tracking
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-truck text-lg text-emerald-500"></i> Manage inbound stock and supply chain logistics.
        </p>
    </div>
    <div class="animate-fade-in-up flex gap-3" style="animation-delay: 0.1s;">
        <button onclick="openMasterProviderModal()" class="flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-white border border-slate-200 px-5 py-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50 shadow-sm active:scale-95 transition-all dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
            <i class="ph-bold ph-plus"></i> New Provider
        </button>
        <button onclick="openAddModal()" class="flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3.5 text-sm font-bold text-white transition-all hover:bg-emerald-700 shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 active:scale-95">
            <i class="ph-bold ph-receipt text-base"></i> Add Inbound PO
        </button>
    </div>
</div>

<div class="mb-8 rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up overflow-hidden relative" style="animation-delay: 0.2s;">
    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
    <div class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-500/20 shadow-sm">
                <i class="ph-fill ph-chart-line-up text-xl"></i>
            </div>
            <div>
                <h6 class="text-lg font-bold text-slate-800 dark:text-white">Daily Inbound Volume</h6>
                <p class="text-xs text-slate-500 dark:text-slate-400">Monitoring SIM quantity received from providers over time</p>
            </div>
        </div>
        
        <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
            <div class="flex flex-col items-center justify-center py-10 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-200 dark:border-slate-700">
                <i class="ph-fill ph-chart-line-down text-4xl text-slate-300 dark:text-slate-600 mb-2"></i>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No data available to display.</p>
            </div>
        <?php else: ?>
            <div id="providerChart" style="height: 260px;"></div>
        <?php endif; ?>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden mb-10" style="animation-delay: 0.3s;">
    
    <div class="border-b border-slate-100 dark:border-slate-800 p-6 bg-slate-50/50 dark:bg-slate-800/50">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
            <div class="md:col-span-5">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Search Keyword</label>
                <div class="relative">
                    <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <input type="text" id="customSearch" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-4 text-sm font-medium outline-none focus:border-emerald-500 shadow-sm transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200" placeholder="PO Number, Batch...">
                </div>
            </div>
            <div class="md:col-span-4">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Filter Provider</label>
                <div class="relative">
                    <i class="ph ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <select id="filterProvider" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-10 text-sm font-medium outline-none focus:border-emerald-500 shadow-sm cursor-pointer transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                        <option value="">All Providers</option>
                        <?php foreach($providers as $p): ?>
                            <option value="<?= e($p['company_name']) ?>"><?= e($p['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>
            </div>
            <div class="md:col-span-3 flex justify-start md:justify-end items-center h-[42px]">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mr-3">Rows</label>
                <select id="customLength" class="appearance-none rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-emerald-600 outline-none shadow-sm cursor-pointer dark:bg-slate-900 dark:border-slate-700 transition-all">
                    <option value="10">10</option><option value="50">50</option><option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-provider">
            <thead>
                <tr>
                    <th class="ps-8 w-[20%]">PO Reference</th>
                    <th class="w-[20%]">Supplier Entity</th>
                    <th class="w-[15%]">Batch Group</th>
                    <th class="w-[18%]">Client Link</th>
                    <th class="text-right w-[12%]">Volume</th>
                    <th class="text-center pe-8 w-[15%]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($data as $index => $row): 
                    $q_fmt = number_format((int)preg_replace('/[^0-9]/', '', $row['sim_qty']));
                    $d_val = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
                    $p_date = date('d M Y', strtotime($d_val));
                    $jsonRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    $animDelay = min($index * 0.05, 0.5) + 0.3; 
                ?>
                <tr class="table-row-hover transition-colors animate-fade-in-up opacity-0 group" style="animation-delay: <?= $animDelay ?>s;">
                    
                    <td class="ps-8 align-top">
                        <div class="flex flex-col gap-1.5">
                            <span class="inline-flex items-center w-max rounded-md bg-emerald-50 dark:bg-emerald-500/10 px-2.5 py-1 text-[11px] font-black text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-500/20 font-mono tracking-tight shadow-sm uppercase">
                                <i class="ph-bold ph-receipt text-emerald-500 mr-1.5"></i> <?= e($row['po_number']) ?>
                            </span>
                            <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 mt-1">
                                <i class="ph-fill ph-calendar-blank text-slate-300 dark:text-slate-600"></i> <?= $p_date ?>
                            </div>
                        </div>
                    </td>

                    <td class="align-top">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white flex items-center justify-center font-black text-sm shadow-sm shrink-0">
                                <?= strtoupper(substr($row['display_provider'], 0, 1)) ?>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-800 dark:text-white text-sm line-clamp-2" title="<?= e($row['display_provider']) ?>">
                                    <?= e($row['display_provider'] ?? '-') ?>
                                </span>
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Supplier</span>
                            </div>
                        </div>
                    </td>

                    <td class="align-top">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shadow-sm mt-1">
                            <i class="ph-fill ph-stack text-slate-400"></i> <?= e($row['batch_name'] ?? '-') ?>
                        </span>
                    </td>

                    <td class="align-top">
                        <?php if(!empty($row['linked_po_number'])): ?>
                            <button onclick='viewClientPO(<?= $jsonRow ?>)' class="inline-flex items-center w-max gap-2 px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200/60 dark:border-blue-500/20 hover:bg-blue-100 dark:hover:bg-blue-500/30 transition-all text-[10px] font-black uppercase tracking-wider shadow-sm group/link mt-1">
                                <i class="ph-bold ph-link-simple-horizontal text-blue-500 group-hover/link:rotate-45 transition-transform"></i> <?= e($row['linked_po_number']) ?>
                            </button>
                        <?php else: ?>
                            <span class="inline-flex items-center w-max gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 border-dashed mt-1">
                                <i class="ph-bold ph-link-break"></i> Unlinked
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="text-right align-top">
                        <div class="flex flex-col items-end gap-1 mt-1">
                            <span class="font-black text-slate-800 dark:text-white font-mono text-xl tracking-tight"><?= $q_fmt ?></span>
                            <span class="inline-flex items-center rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[9px] font-black text-slate-400 uppercase tracking-widest">PCS</span>
                        </div>
                    </td>

                    <td class="pe-8 text-center align-top">
                        <div class="flex items-center justify-center gap-1.5 mt-1">
                            <?php if(!empty($row['po_file'])): ?>
                                <a href="uploads/po/<?= $row['po_file'] ?>" target="_blank" class="group/btn relative h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all shadow-sm">
                                    <i class="ph-fill ph-file-pdf text-lg"></i>
                                    <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Document</span>
                                </a>
                            <?php endif; ?>
                            
                            <button onclick='printPO(<?= $jsonRow ?>)' class="group/btn relative h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-all shadow-sm">
                                <i class="ph-fill ph-printer text-lg"></i>
                                <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Print</span>
                            </button>
                            
                            <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-0.5"></div>
                            
                            <button onclick='openEditModal(<?= $jsonRow ?>)' class="group/btn relative h-9 w-9 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/40 hover:scale-105 active:scale-95 flex items-center justify-center transition-all shadow-sm">
                                <i class="ph-fill ph-pencil-simple text-lg"></i>
                                <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Edit</span>
                            </button>
                            
                            <a href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=provider" onclick="return confirm('Are you sure you want to permanently delete this Provider PO?')" class="group/btn relative h-9 w-9 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 hover:scale-105 active:scale-95 flex items-center justify-center transition-all shadow-sm">
                                <i class="ph-fill ph-trash text-lg"></i>
                                <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Delete</span>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalViewClientPO" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-blue-500 px-8 py-6 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
            <div class="flex items-center gap-3">
                <i class="ph-fill ph-link-simple-horizontal text-3xl"></i>
                <div>
                    <h5 class="text-xl font-bold leading-tight">Linked Client PO</h5>
                    <p class="text-[10px] uppercase font-bold text-blue-200 tracking-widest mt-0.5">Reference Details</p>
                </div>
            </div>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-8 space-y-6 bg-slate-50/50 dark:bg-slate-900/50">
            <div class="grid grid-cols-2 gap-y-6 gap-x-6">
                <div class="col-span-2 bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute left-0 top-0 w-1 h-full bg-blue-500"></div>
                    <label class="text-[10px] font-black uppercase text-slate-400 block mb-1.5 tracking-widest">Client Identity</label>
                    <p id="v_client_name" class="font-black text-slate-800 dark:text-white text-lg">-</p>
                    <p id="v_project_name" class="text-sm font-bold text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5"><i class="ph-fill ph-folder-open text-amber-500"></i> <span id="v_project_span">-</span></p>
                </div>
                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Date</label><p id="v_po_date" class="font-bold text-slate-700 dark:text-slate-300">-</p></div>
                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Batch ID</label><p id="v_batch" class="inline-block bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg font-bold text-slate-700 dark:text-slate-300 text-xs uppercase border border-slate-200 dark:border-slate-700 shadow-sm">-</p></div>
                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Client PO Reference</label><p id="v_po_number" class="font-mono font-bold text-blue-600 dark:text-blue-400">-</p></div>
                <div><label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Total Quantity</label><p id="v_qty" class="font-black text-slate-800 dark:text-white text-lg">-</p></div>
            </div>
            <div id="v_file_container" class="pt-6 border-t border-slate-200 dark:border-slate-700"></div>
        </div>
        <div class="border-t border-slate-100 dark:border-slate-800 p-5 bg-white dark:bg-slate-800 flex justify-end">
            <button type="button" class="btn-close-modal px-8 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all border border-slate-200 dark:border-slate-600">Close Panel</button>
        </div>
    </div>
</div>

<div id="modalAdd" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <input type="hidden" name="action" id="form_action" value="create">
        <input type="hidden" name="type" value="provider">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="existing_file" id="edit_existing_file">

        <div class="flex items-center justify-between border-b border-emerald-500 px-8 py-5 bg-gradient-to-r from-emerald-600 to-emerald-800 text-white shrink-0">
            <h5 class="text-lg font-bold flex items-center gap-2">
                <i class="ph-bold ph-receipt text-2xl"></i> <span id="modal_title">Provider Stock Inbound</span>
            </h5>
            <button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"><i class="ph ph-x text-lg"></i></button>
        </div>
        
        <div class="overflow-y-auto p-8 flex-1 custom-scrollbar bg-slate-50 dark:bg-slate-900/50">
            <div class="flex justify-center mb-6">
                <div class="inline-flex bg-slate-200 dark:bg-slate-800 p-1 rounded-xl shadow-inner border border-slate-300 dark:border-slate-700">
                    <label class="cursor-pointer relative text-sm font-bold">
                        <input type="radio" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')" class="peer sr-only">
                        <span class="block px-6 py-2 rounded-lg peer-checked:bg-white dark:peer-checked:bg-slate-700 peer-checked:text-emerald-600 dark:peer-checked:text-emerald-400 peer-checked:shadow-sm text-slate-500 dark:text-slate-400 transition-all">Database Provider</span>
                    </label>
                    <label class="cursor-pointer relative text-sm font-bold">
                        <input type="radio" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')" class="peer sr-only">
                        <span class="block px-6 py-2 rounded-lg peer-checked:bg-white dark:peer-checked:bg-slate-700 peer-checked:text-emerald-600 dark:peer-checked:text-emerald-400 peer-checked:shadow-sm text-slate-500 dark:text-slate-400 transition-all">Manual Entry</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                        <h6 class="text-[11px] font-black text-emerald-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-truck text-lg"></i> Supplier Entity</h6>
                        
                        <div id="add_section_datapool">
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Select Provider *</label>
                            <select name="company_id" id="add_company_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm cursor-pointer">
                                <option value="" class="dark:bg-slate-800">-- Choose Provider --</option>
                                <?php foreach($providers as $p) echo "<option value='{$p['id']}' class='dark:bg-slate-800'>{$p['company_name']}</option>"; ?>
                            </select>
                        </div>
                        <div id="add_section_manual" class="hidden">
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Manual Provider Name *</label>
                            <input type="text" name="manual_company_name" id="add_manual_company" placeholder="e.g. INDOSAT" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                    </div>

                    <div class="bg-blue-50/50 dark:bg-blue-900/10 p-6 rounded-2xl border border-blue-100 dark:border-blue-900/30 relative overflow-hidden">
                        <h6 class="text-[11px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-bold ph-link-simple text-lg"></i> Link to Client Reference (Optional)</h6>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Quick Client Filter</label>
                                <select id="add_filter_customer" class="w-full rounded-xl border border-blue-200 dark:border-blue-800 bg-white dark:bg-slate-800 px-4 py-2.5 text-xs font-medium outline-none focus:border-blue-500 dark:text-white transition-all shadow-sm cursor-pointer" onchange="filterClientPOs('add')">
                                    <option value="" class="dark:bg-slate-800">Show All Clients...</option>
                                    <?php foreach($clients as $c) echo "<option value='{$c['id']}' class='dark:bg-slate-800'>{$c['company_name']}</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Select Client PO to Link</label>
                                <select name="link_client_po_id" id="add_link_client_po_id" class="w-full rounded-xl border border-blue-300 dark:border-blue-700 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-blue-600 dark:text-white transition-all shadow-sm cursor-pointer" onchange="autoFillBatch('add')">
                                    <option value="" class="dark:bg-slate-800">-- No Link --</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-teal-500"></div>
                    <h6 class="text-[11px] font-black text-teal-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-clipboard-text text-lg"></i> Stock Specification</h6>
                    
                    <div class="space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Date *</label>
                                <input type="date" name="po_date" id="add_po_date" value="<?= date('Y-m-d') ?>" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">SIM Quantity *</label>
                                <input type="number" name="sim_qty" id="add_sim_qty" required placeholder="0" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-emerald-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-black text-emerald-600 dark:text-emerald-400 outline-none focus:border-emerald-500 transition-all shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Provider PO Number *</label>
                            <input type="text" name="po_number" id="add_po_number" required placeholder="REF-PRV-..." class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-mono font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Batch ID *</label>
                            <input type="text" name="batch_name" id="add_batch_name" required placeholder="e.g. XL-PRO-01" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                        
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Proof of Document (PDF)</label>
                            <input type="file" name="po_file" class="w-full text-xs text-slate-500 file:cursor-pointer file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 hover:file:shadow-sm dark:file:bg-emerald-900/30 dark:file:text-emerald-400 transition-all border border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-1.5 bg-slate-50 dark:bg-slate-800">
                            
                            <div id="current_file_info_container" class="mt-3 hidden">
                                <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-inner">
                                    <i class="ph-fill ph-file-pdf text-emerald-500 text-lg"></i>
                                    <span id="current_file_info" class="text-xs font-bold text-slate-600 dark:text-slate-400 truncate w-full">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-slate-800 flex justify-end gap-3 shrink-0 rounded-b-3xl">
            <button type="button" class="btn-close-modal rounded-xl px-6 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
            <button type="submit" class="flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-2.5 text-sm font-bold text-white transition-all hover:bg-emerald-700 shadow-md hover:shadow-lg hover:shadow-emerald-500/30 active:scale-95">
                <i class="ph-bold ph-floppy-disk"></i> Save Inbound PO
            </button>
        </div>
    </form>
</div>

<div id="modalMasterProvider" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <form action="process_sim_tracking.php" method="POST">
            <input type="hidden" name="action" value="create_company"><input type="hidden" name="company_type" value="provider"><input type="hidden" name="redirect" value="provider">
            <div class="flex items-center justify-between border-b border-emerald-500 px-8 py-6 bg-gradient-to-r from-emerald-600 to-teal-600 text-white">
                <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-plus-circle text-2xl"></i> Register Provider</h5>
                <button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-lg"></i></button>
            </div>
            <div class="p-8 bg-slate-50 dark:bg-slate-900/50">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">New Provider Entity Name</label>
                <div class="relative">
                    <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl"></i>
                    <input type="text" name="company_name" required placeholder="e.g. XL AXIATA" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-4 pl-12 text-sm font-black uppercase focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none shadow-sm dark:text-white transition-all">
                </div>
            </div>
            <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-slate-800 flex justify-end gap-3">
                <button type="button" class="btn-close-modal rounded-xl px-6 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
                <button type="submit" class="flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-3 text-sm font-bold text-white transition-all hover:bg-emerald-700 shadow-md hover:shadow-lg hover:shadow-emerald-500/30 active:scale-95"><i class="ph-bold ph-check"></i> Submit Registration</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    const allClientPOs = <?= json_encode($client_pos); ?>;
    const chartLabels = <?= json_encode($js_chart_labels); ?>;
    const chartSeries = <?= json_encode($js_chart_series); ?>;

    // --- MODAL LOGIC (TAILWIND) ---
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

    // 1. Chart Rendering
    if (chartSeries.length > 0) {
        let isDark = document.documentElement.classList.contains('dark');
        let gridColor = isDark ? '#334155' : '#f1f5f9';
        let labelColor = isDark ? '#94a3b8' : '#64748b';

        new ApexCharts(document.querySelector('#providerChart'), {
            series: [{ name: 'Quantity', data: chartSeries }],
            chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
            stroke: { curve: 'smooth', width: 2, colors: ['#10b981'] },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }, colors: ['#10b981'] },
            dataLabels: { enabled: false },
            xaxis: { 
                categories: chartLabels, 
                labels: { style: { fontSize: '11px', fontWeight: 'bold', colors: labelColor } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { style: { colors: labelColor, fontWeight: 'bold' } }
            },
            grid: { borderColor: gridColor, strokeDashArray: 4, yaxis: { lines: { show: true } }, xaxis: { lines: { show: false } } },
            tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: function (val) { return new Intl.NumberFormat('en-US').format(val) + ' Pcs' } } }
        }).render();
    }

    // 2. Logic Toggle Input (Database / Manual)
    function toggleInputMode(mode) {
        let isDP = $('#' + mode + '_mode_datapool').is(':checked');
        if (isDP) {
            $('#' + mode + '_section_datapool').removeClass('hidden'); $('#' + mode + '_section_manual').addClass('hidden');
            $('#' + mode + '_company_id').attr('required', 'required'); $('#' + mode + '_manual_company').removeAttr('required');
        } else {
            $('#' + mode + '_section_datapool').addClass('hidden'); $('#' + mode + '_section_manual').removeClass('hidden');
            $('#' + mode + '_company_id').removeAttr('required'); $('#' + mode + '_manual_company').attr('required', 'required');
        }
    }

    // 3. Logic Filter Client POs (Link Feature)
    function filterClientPOs(mode, selectedPoId = null) {
        let filterCustomerId = $('#' + mode + '_filter_customer').val();
        let targetSelect = $('#' + mode + '_link_client_po_id');
        let darkClass = document.documentElement.classList.contains('dark') ? 'class="dark:bg-slate-800"' : '';
        targetSelect.empty().append(`<option value="" ${darkClass}>-- No Link --</option>`);

        allClientPOs.forEach(function(po) {
            let show = false;
            if (filterCustomerId === '') { show = true; } 
            else { if (po.company_id == filterCustomerId) show = true; }

            if (show) {
                let isSelected = (selectedPoId == po.id) ? 'selected' : '';
                let clientName = po.display_client_name ? po.display_client_name : 'Manual';
                let batchValue = (po.batch_name || '').replace(/"/g, '&quot;');
                
                targetSelect.append(`<option value="${po.id}" ${isSelected} data-batch="${batchValue}" ${darkClass}>${clientName} - ${po.po_number}</option>`);
            }
        });
    }

    function autoFillBatch(mode) {
        let selectId = '#' + mode + '_link_client_po_id';
        let batchInputId = (mode === 'add') ? '#add_batch_name' : '#edit_batch_name';
        let selectedOption = $(selectId).find(':selected');
        let currentBatch = selectedOption.data('batch');
        
        if (currentBatch) {
            let newBatch = String(currentBatch).replace(/(\d+)(?!.*\d)/, function(match) {
                return parseInt(match) + 1;
            });
            if (newBatch === currentBatch) { newBatch = currentBatch + " 2"; }
            $(batchInputId).val(newBatch);
        }
    }

    // 4. Modal Triggers
    function viewClientPO(d) {
        $('#v_client_name').text(d.linked_client_name || '-');
        $('#v_project_span').text(d.linked_project_name || 'No Project Assigned'); 
        $('#v_po_number').text(d.linked_po_number || '-');
        $('#v_batch').text(d.linked_batch_name || '-');
        
        let dt = (d.linked_po_date && d.linked_po_date !== '0000-00-00') ? d.linked_po_date : '-';
        $('#v_po_date').text(dt);
        
        $('#v_qty').text(new Intl.NumberFormat('en-US').format(String(d.linked_sim_qty).replace(/\D/g,'')) + ' Pcs');
        
        if(d.linked_po_file) {
            $('#v_file_container').html(`<a href="uploads/po/${d.linked_po_file}" target="_blank" class="flex w-max mx-auto items-center justify-center gap-3 rounded-2xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 px-6 py-4 text-blue-700 dark:text-blue-400 font-black text-sm hover:bg-blue-100 dark:hover:bg-blue-500/30 transition-all shadow-sm"><i class="ph-fill ph-file-pdf text-3xl"></i> DOWNLOAD CLIENT PO DOCUMENT</a>`);
        } else {
            $('#v_file_container').html('<div class="text-center py-6 bg-slate-100 dark:bg-slate-800 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 text-slate-400 text-xs italic"><i class="ph-fill ph-file-dashed text-4xl mb-2 opacity-50 block"></i>No document attached to this link</div>');
        }
        
        $('body').css('overflow', 'hidden'); $('#modalViewClientPO').removeClass('hidden').addClass('flex');
    }

    function openAddModal() {
        $('#form_action').val('create'); $('#modal_title').text('Add Provider Stock Inbound');
        $('#add_mode_datapool').prop('checked', true); toggleInputMode('add');
        $('#add_company_id').val(''); $('#add_manual_company').val(''); $('#add_po_number').val('');
        $('#add_batch_name').val(''); $('#add_sim_qty').val(''); $('#edit_id').val(''); $('#edit_existing_file').val('');
        $('#current_file_info_container').addClass('hidden');
        $('#add_filter_customer').val(''); filterClientPOs('add');
        
        $('body').css('overflow', 'hidden'); $('#modalAdd').removeClass('hidden').addClass('flex');
    }

    function openEditModal(d) {
        $('#form_action').val('update'); $('#modal_title').text('Edit Provider PO');
        $('#edit_id').val(d.id); $('#add_po_number').val(d.po_number);
        $('#add_po_date').val(d.po_date !== '0000-00-00' ? d.po_date : '');
        $('#add_sim_qty').val(String(d.sim_qty).replace(/\D/g,''));
        $('#add_batch_name').val(d.batch_name);
        
        if(d.po_file) {
            $('#edit_existing_file').val(d.po_file);
            $('#current_file_info').text(d.po_file);
            $('#current_file_info_container').removeClass('hidden');
        } else {
            $('#edit_existing_file').val('');
            $('#current_file_info_container').addClass('hidden');
        }

        if (d.company_id && d.company_id != 0) {
            $('#add_mode_datapool').prop('checked', true); toggleInputMode('add'); $('#add_company_id').val(d.company_id);
        } else {
            $('#add_mode_manual').prop('checked', true); toggleInputMode('add'); $('#add_manual_company').val(d.manual_company_name);
        }
        
        $('#add_filter_customer').val('');
        filterClientPOs('add', d.link_client_po_id);
        
        $('body').css('overflow', 'hidden'); $('#modalAdd').removeClass('hidden').addClass('flex');
    }

    function openMasterProviderModal() { $('body').css('overflow', 'hidden'); $('#modalMasterProvider').removeClass('hidden').addClass('flex'); }

    // 5. Print Function
    function printPO(d) {
        let poDate = d.po_date ? new Date(d.po_date).toLocaleDateString('en-US', {day: 'numeric', month: 'long', year: 'numeric'}) : '-';
        let qty = new Intl.NumberFormat('en-US').format(String(d.sim_qty).replace(/[^0-9]/g, ''));
        let company = d.display_provider || '-';
        let batch = d.batch_name || '-';
        let poNum = d.po_number || '-';

        let win = window.open('', '', 'width=800,height=600');
        win.document.write(`
        <html><head><title>Print PO</title>
        <style>body{font-family:'Helvetica Neue', Helvetica, Arial, sans-serif;padding:50px;color:#1e293b}.h{text-align:center;border-bottom:2px solid #e2e8f0;margin-bottom:30px;padding-bottom:20px}.h h1{margin:0 0 10px 0;font-size:24px;text-transform:uppercase}.h p{margin:0;font-family:monospace;color:#64748b;font-weight:bold;font-size:16px}table{width:100%;border-collapse:collapse;margin-top:20px}th{background:#f8fafc;padding:12px;text-align:left;border-bottom:2px solid #e2e8f0;font-size:12px;text-transform:uppercase;color:#64748b}td{border-bottom:1px solid #e2e8f0;padding:15px;font-size:14px}.footer{margin-top:80px;display:flex;justify-content:space-between}.sig-line{border-top:1px solid #94a3b8;width:200px;padding-top:10px;text-align:center;font-weight:bold;color:#475569}</style>
        </head><body>
        <div class="h"><h1>Provider Stock Inbound</h1><p>REF: ${poNum}</p></div>
        <div style="display:flex; justify-content:space-between; margin-bottom:30px;">
            <div><strong style="color:#64748b;font-size:12px;display:block;">PROVIDER:</strong><span style="font-size:16px;font-weight:bold;">${company}</span></div>
            <div style="text-align:right"><strong style="color:#64748b;font-size:12px;display:block;">DATE:</strong><span style="font-size:16px;font-weight:bold;">${poDate}</span></div>
        </div>
        <table><tr><th width="70%">Description</th><th width="30%" style="text-align:right">Quantity</th></tr>
        <tr><td><strong>Stock Inbound Processing</strong><br><span style="color:#64748b;font-size:13px;display:block;margin-top:5px;">Batch ID: ${batch}</span></td>
        <td style="text-align:right;font-size:18px;"><strong>${qty} Pcs</strong></td></tr></table>
        <div class="footer"><div class="sig-line">Prepared By</div><div class="sig-line">Received By</div></div>
        </body></html>`);
        win.document.close(); win.focus(); setTimeout(() => { win.print(); win.close(); }, 500);
    }

    // 6. DataTables Init
    $(document).ready(function() {
        var table = $('#table-provider').DataTable({
            language: { 
                search: '', 
                emptyTable: `<div class="flex flex-col items-center justify-center py-10"><i class="ph-fill ph-inbox text-5xl text-slate-300 dark:text-slate-600 mb-3"></i><span class="text-slate-500 font-bold">No inbound records found.</span></div>` 
            },
            searching: true, ordering: false, autoWidth: false, pageLength: 10,
            dom: 't<"flex flex-col sm:flex-row justify-between items-center px-6 py-5 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 rounded-b-3xl"ip>'
        });
        
        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
        $('#customLength').on('change', function() { table.page.len(this.value).draw(); });
        $('#filterProvider').on('change', function() { table.column(1).search(this.value).draw(); });
    });
</script>

<?php require_once 'includes/footer.php'; ?>