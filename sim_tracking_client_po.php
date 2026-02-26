<?php
// =========================================================================
// FILE: sim_tracking_client_po.php
// DESC: Client Purchase Order Management (Ultra-Modern Tailwind CSS Theme)
// =========================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
$current_page = 'sim_tracking_client_po.php';

// INCLUDE SYSTEM FILES
if (file_exists('includes/config.php')) require_once 'includes/config.php';
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

// DATABASE CONNECTION SETUP
$db = null; 
$db_type = '';

$candidates = ['pdo', 'conn', 'db', 'link', 'mysqli'];
foreach ($candidates as $var) { 
    if (isset($$var)) { 
        if ($$var instanceof PDO) { $db = $$var; $db_type = 'pdo'; break; } 
        if ($$var instanceof mysqli) { $db = $$var; $db_type = 'mysqli'; break; } 
    } 
    if (isset($GLOBALS[$var])) { 
        if ($GLOBALS[$var] instanceof PDO) { $db = $GLOBALS[$var]; $db_type = 'pdo'; break; } 
        if ($GLOBALS[$var] instanceof mysqli) { $db = $GLOBALS[$var]; $db_type = 'mysqli'; break; } 
    } 
}

if (!$db && defined('DB_HOST')) { 
    try { 
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS); 
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $db_type = 'pdo'; 
    } catch (Exception $e) {
        die("Database Connection Error: " . $e->getMessage());
    } 
}

// -------------------------------------------------------------------------
// DATA FETCHING LOGIC
// -------------------------------------------------------------------------
$data = [];
$chart_data_grouped = [];
$existing_products = []; 

try {
    // 1. MAIN QUERY: FETCH CLIENT PO DATA
    $sql = "SELECT st.*, 
            COALESCE(c.company_name, st.manual_company_name) as display_company,
            COALESCE(p.project_name, st.manual_project_name) as display_project
            FROM sim_tracking_po st
            LEFT JOIN companies c ON st.company_id = c.id
            LEFT JOIN projects p ON st.project_id = p.id
            WHERE st.type = 'client' 
            ORDER BY st.id DESC"; 

    if ($db_type === 'pdo') {
        $data = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. FETCH PRODUCTS FOR DATALIST (AUTO-COMPLETE)
        $prodSql = "SELECT DISTINCT product_name FROM sim_tracking_po WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name ASC";
        $existing_products = $db->query($prodSql)->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $res = mysqli_query($db, $sql);
        if ($res) { while ($row = mysqli_fetch_assoc($res)) $data[] = $row; }
        
        $prodRes = mysqli_query($db, "SELECT DISTINCT product_name FROM sim_tracking_po WHERE product_name IS NOT NULL AND product_name != '' ORDER BY product_name ASC");
        while($r = mysqli_fetch_assoc($prodRes)) $existing_products[] = $r['product_name'];
    }

    // 3. PROCESS CHART DATA (Group by Date)
    foreach ($data as $row) {
        $raw_date = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
        $date_label = date('d M Y', strtotime($raw_date));
        $qty = (int)preg_replace('/[^0-9]/', '', $row['sim_qty']);
        
        if (!isset($chart_data_grouped[$date_label])) {
            $chart_data_grouped[$date_label] = 0;
        }
        $chart_data_grouped[$date_label] += $qty;
    }
    $chart_data_grouped = array_reverse(array_slice($chart_data_grouped, 0, 10, true));

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error Fetching Data: " . $e->getMessage() . "</div>";
}

$js_chart_labels = array_keys($chart_data_grouped);
$js_chart_series = array_values($chart_data_grouped);

// 4. FETCH DROPDOWN OPTIONS
$clients = []; $providers = []; $projects_raw = [];
try {
    if ($db_type === 'pdo') {
        $clients = $db->query("SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($clients)) $clients = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $providers = $db->query("SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        if(empty($providers)) $providers = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $projects_raw = $db->query("SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $r1 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='client' ORDER BY company_name ASC"); 
        while($r=mysqli_fetch_assoc($r1))$clients[]=$r;
        
        $r2 = mysqli_query($db, "SELECT id, company_name FROM companies WHERE company_type='provider' ORDER BY company_name ASC"); 
        while($r=mysqli_fetch_assoc($r2))$providers[]=$r;
        
        $r3 = mysqli_query($db, "SELECT id, project_name, company_id FROM projects ORDER BY project_name ASC"); 
        while($r=mysqli_fetch_assoc($r3))$projects_raw[]=$r;
    }
} catch (Exception $e) {}
?>

<style>
    /* Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* Table Core Formatting */
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.5rem 1rem; vertical-align: top; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-bottom: 1px solid #334155; }
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
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #4f46e5 !important; border-color: #4f46e5 !important; color: #fff !important; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3); }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button { background: #1e293b; border-color: #334155; color: #cbd5e1 !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #334155 !important; border-color: #475569 !important; color: #fff !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #4f46e5 !important; border-color: #4f46e5 !important; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">
            Client Purchase Orders
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-shopping-cart text-lg text-indigo-500"></i> Manage and track incoming client requests.
        </p>
    </div>
    <div class="animate-fade-in-up" style="animation-delay: 0.1s;">
        <button onclick="openAddModal()" class="flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95 transition-all w-full sm:w-auto">
            <i class="ph-bold ph-plus text-base"></i> Create New PO
        </button>
    </div>
</div>

<div class="mb-8 rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up overflow-hidden relative" style="animation-delay: 0.2s;">
    <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
    <div class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 shadow-sm"><i class="ph-fill ph-chart-line-up text-xl"></i></div>
            <div>
                <h6 class="text-lg font-bold text-slate-800 dark:text-white">Order Volume Analysis</h6>
                <p class="text-xs text-slate-500 dark:text-slate-400">Total SIM quantity requested based on PO Date</p>
            </div>
        </div>
        
        <?php if(empty($js_chart_series) || array_sum($js_chart_series) == 0): ?>
            <div class="flex flex-col items-center justify-center py-10 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-200 dark:border-slate-700">
                <i class="ph-fill ph-chart-line-down text-4xl text-slate-300 dark:text-slate-600 mb-2"></i>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No data available to display.</p>
            </div>
        <?php else: ?>
            <div id="clientChart" style="height: 260px;"></div>
        <?php endif; ?>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden mb-10" style="animation-delay: 0.3s;">
    
    <div class="border-b border-slate-100 dark:border-slate-800 p-6 bg-slate-50/50 dark:bg-slate-800/50">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
            <div class="md:col-span-4">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Search Keyword</label>
                <div class="relative">
                    <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <input type="text" id="customSearch" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-4 text-sm font-medium outline-none focus:border-indigo-500 shadow-sm transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200" placeholder="PO Number, Batch...">
                </div>
            </div>
            <div class="md:col-span-3">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Filter Client</label>
                <div class="relative">
                    <i class="ph ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <select id="filterClient" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-10 text-sm font-medium outline-none focus:border-indigo-500 shadow-sm cursor-pointer transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                        <option value="">All Clients</option>
                        <?php foreach($clients as $c): ?><option value="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endendforeach; ?>
                    </select>
                    <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>
            </div>
            <div class="md:col-span-3">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Filter Project</label>
                <div class="relative">
                    <i class="ph ph-folder-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <select id="filterProject" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-10 text-sm font-medium outline-none focus:border-indigo-500 shadow-sm cursor-pointer transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                        <option value="">All Projects</option>
                        <?php 
                            $unique_projects = []; 
                            foreach($projects_raw as $p) $unique_projects[$p['project_name']] = $p['project_name']; 
                            foreach($unique_projects as $pname): 
                        ?>
                            <option value="<?= htmlspecialchars($pname) ?>"><?= htmlspecialchars($pname) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>
            </div>
            <div class="md:col-span-2 flex justify-end items-center h-[42px]">
                <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mr-3">Rows</label>
                <select id="customLength" class="appearance-none rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-indigo-600 outline-none shadow-sm cursor-pointer dark:bg-slate-900 dark:border-slate-700 transition-all">
                    <option value="10">10</option><option value="50">50</option><option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-client">
            <thead>
                <tr>
                    <th class="ps-8 w-[20%]">PO Reference</th>
                    <th class="w-[22%]">Client Entity & Project</th>
                    <th class="w-[20%]">Product Specifications</th>
                    <th class="text-right w-[12%]">Requested Volume</th>
                    <th class="text-center w-[12%]">Documents</th>
                    <th class="text-center pe-8 w-[14%]">Security Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($data as $index => $row): 
                    $q_fmt = number_format((int)preg_replace('/[^0-9]/', '', $row['sim_qty']));
                    $d_val = (!empty($row['po_date']) && $row['po_date'] != '0000-00-00') ? $row['po_date'] : $row['created_at'];
                    $p_date = date('d M Y', strtotime($d_val));
                    $prod = !empty($row['product_name']) ? htmlspecialchars($row['product_name']) : 'General SIM';
                    $detail = !empty($row['detail']) ? htmlspecialchars($row['detail']) : 'No further specification provided.';
                    $batch = htmlspecialchars($row['batch_name'] ?? 'N/A');
                    $jsonRow = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    $animDelay = min($index * 0.05, 0.5) + 0.3; 
                ?>
                <tr class="table-row-hover transition-colors animate-fade-in-up opacity-0 group" style="animation-delay: <?= $animDelay ?>s;">
                    
                    <td class="ps-8 align-top">
                        <div class="flex flex-col gap-1.5">
                            <span class="inline-flex items-center w-max rounded-md bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-1 text-[11px] font-black text-indigo-700 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-500/20 font-mono tracking-tight shadow-sm uppercase">
                                <i class="ph-bold ph-receipt text-indigo-500 mr-1.5"></i> <?= htmlspecialchars($row['po_number']) ?>
                            </span>
                            <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-400 mt-1">
                                <i class="ph-fill ph-tag text-slate-300 dark:text-slate-600"></i> BATCH: <?= $batch ?>
                            </div>
                            <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                                <i class="ph-fill ph-calendar-blank text-slate-300 dark:text-slate-600"></i> <?= $p_date ?>
                            </div>
                        </div>
                    </td>

                    <td class="align-top">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 h-7 w-7 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 shrink-0 border border-slate-200 dark:border-slate-700 shadow-sm">
                                    <i class="ph-fill ph-buildings"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 dark:text-white text-sm line-clamp-1 max-w-[200px]" title="<?= htmlspecialchars($row['display_company'] ?? '-') ?>"><?= htmlspecialchars($row['display_company'] ?? '-') ?></span>
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Client Name</span>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <div class="mt-0.5 h-7 w-7 rounded-lg bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-500 shrink-0 border border-amber-100 dark:border-amber-500/20 shadow-sm">
                                    <i class="ph-fill ph-folder-open"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300 text-sm line-clamp-1 max-w-[200px]" title="<?= htmlspecialchars($row['display_project'] ?? '-') ?>"><?= htmlspecialchars($row['display_project'] ?? '-') ?></span>
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Project Assignment</span>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td class="align-top">
                        <div class="flex flex-col gap-2 max-w-[280px]">
                            <span class="inline-flex w-max items-center rounded-md bg-emerald-50 dark:bg-emerald-500/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-500/20 shadow-sm">
                                <i class="ph-fill ph-sim-card mr-1.5"></i> <?= $prod ?>
                            </span>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-2 italic" title="<?= $detail ?>">
                                "<?= $detail ?>"
                            </p>
                        </div>
                    </td>

                    <td class="text-right align-top">
                        <div class="flex flex-col items-end gap-1 mt-1">
                            <span class="font-black text-slate-800 dark:text-white font-mono text-xl tracking-tight"><?= $q_fmt ?></span>
                            <span class="inline-flex items-center rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[9px] font-black text-slate-400 uppercase tracking-widest">PCS / SIMs</span>
                        </div>
                    </td>

                    <td class="text-center align-top">
                        <div class="flex justify-center mt-1">
                            <?php if(!empty($row['po_file'])): ?>
                                <a href="uploads/po/<?= $row['po_file'] ?>" target="_blank" class="flex flex-col items-center justify-center gap-1.5 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors group/doc">
                                    <div class="h-10 w-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center border border-indigo-100 dark:border-indigo-500/20 group-hover/doc:bg-indigo-100 dark:group-hover/doc:bg-indigo-500/30 transition-all shadow-sm">
                                        <i class="ph-fill ph-file-pdf text-2xl"></i>
                                    </div>
                                    <span class="text-[9px] font-black uppercase tracking-widest">View PDF</span>
                                </a>
                            <?php else: ?>
                                <div class="flex flex-col items-center justify-center gap-1.5 text-slate-300 dark:text-slate-600 cursor-not-allowed" title="No file attached">
                                    <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-800/50 flex items-center justify-center border border-slate-100 dark:border-slate-700">
                                        <i class="ph-bold ph-minus text-xl"></i>
                                    </div>
                                    <span class="text-[9px] font-black uppercase tracking-widest opacity-50">Empty</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>

                    <td class="pe-8 text-center align-top">
                        <div class="flex items-center justify-center gap-1.5 mt-1">
                            <button onclick='openEditModal(<?= $jsonRow ?>)' class="group/btn relative h-9 w-9 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/40 hover:scale-105 active:scale-95 flex items-center justify-center transition-all shadow-sm">
                                <i class="ph-fill ph-pencil-simple text-lg"></i>
                                <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Edit Details</span>
                            </button>
                            
                            <button onclick='openToProviderModal(<?= $jsonRow ?>)' class="group/btn relative h-9 w-9 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/40 hover:scale-105 active:scale-95 flex items-center justify-center transition-all shadow-sm">
                                <i class="ph-fill ph-share-network text-lg"></i>
                                <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Forward to Provider</span>
                            </button>
                            
                            <button onclick='printPO(<?= $jsonRow ?>)' class="group/btn relative h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:scale-105 active:scale-95 flex items-center justify-center transition-all shadow-sm">
                                <i class="ph-fill ph-printer text-lg"></i>
                                <span class="absolute -top-8 bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded opacity-0 group-hover/btn:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">Print Record</span>
                            </button>

                            <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-0.5"></div>
                            
                            <a href="process_sim_tracking.php?action=delete&id=<?= $row['id'] ?>&type=client" onclick="return confirm('Are you sure you want to permanently delete this PO? This action cannot be undone.')" class="group/btn relative h-9 w-9 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 hover:scale-105 active:scale-95 flex items-center justify-center transition-all shadow-sm">
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

<datalist id="product_list">
    <?php foreach($existing_products as $prod): ?><option value="<?= htmlspecialchars($prod) ?>"><?php endforeach; ?>
</datalist>

<div id="modalAdd" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <input type="hidden" name="action" value="create"><input type="hidden" name="type" value="client">
        
        <div class="flex items-center justify-between border-b border-indigo-500 px-8 py-5 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white">
            <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-plus-circle text-2xl"></i> Create New Client PO</h5>
            <button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-lg"></i></button>
        </div>
        
        <div class="overflow-y-auto p-8 flex-1 custom-scrollbar bg-slate-50 dark:bg-slate-900/50">
            <div class="flex justify-center mb-6">
                <div class="inline-flex bg-slate-200 dark:bg-slate-800 p-1 rounded-xl shadow-inner border border-slate-300 dark:border-slate-700">
                    <label class="cursor-pointer relative text-sm font-bold">
                        <input type="radio" name="add_input_mode" id="add_mode_datapool" value="datapool" checked onchange="toggleInputMode('add')" class="peer sr-only">
                        <span class="block px-6 py-2 rounded-lg peer-checked:bg-white dark:peer-checked:bg-slate-700 peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400 peer-checked:shadow-sm text-slate-500 dark:text-slate-400 transition-all">Select from Database</span>
                    </label>
                    <label class="cursor-pointer relative text-sm font-bold">
                        <input type="radio" name="add_input_mode" id="add_mode_manual" value="manual" onchange="toggleInputMode('add')" class="peer sr-only">
                        <span class="block px-6 py-2 rounded-lg peer-checked:bg-white dark:peer-checked:bg-slate-700 peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400 peer-checked:shadow-sm text-slate-500 dark:text-slate-400 transition-all">Manual Entry</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
                        <h6 class="text-[11px] font-black text-indigo-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-identification-badge text-lg"></i> Client Assignment</h6>
                        
                        <div id="add_section_datapool" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Client Name *</label>
                                <select name="company_id" id="add_company_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-indigo-500 dark:text-white transition-all shadow-sm cursor-pointer" onchange="filterProjects('add')" required>
                                    <option value="" class="dark:bg-slate-800">-- Select Client --</option>
                                    <?php foreach($clients as $c) echo "<option value='{$c['id']}' class='dark:bg-slate-800'>{$c['company_name']}</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Project Assignment *</label>
                                <select name="project_id" id="add_project_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-indigo-500 dark:text-white transition-all shadow-sm cursor-pointer" required>
                                    <option value="" class="dark:bg-slate-800">-- Select Project --</option>
                                </select>
                            </div>
                        </div>
                        <div id="add_section_manual" class="hidden space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Manual Client Name *</label>
                                <input type="text" name="manual_company_name" id="add_manual_company" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 dark:text-white transition-all shadow-sm" placeholder="PT Example Indonesia">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Manual Project Name</label>
                                <input type="text" name="manual_project_name" id="add_manual_project" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 dark:text-white transition-all shadow-sm" placeholder="IoT Procurement Phase 1">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-amber-500"></div>
                        <h6 class="text-[11px] font-black text-amber-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-package text-lg"></i> Product Specs</h6>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Product Category</label>
                                <input type="text" name="product_name" list="product_list" placeholder="Search or Type..." class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-amber-500 dark:text-white transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Detail / Note</label>
                                <textarea name="detail" rows="2" placeholder="Specific technical requirements..." class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-amber-500 dark:text-white transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                    <h6 class="text-[11px] font-black text-emerald-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-clipboard-text text-lg"></i> Order Details</h6>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Date *</label>
                                <input type="date" name="po_date" value="<?= date('Y-m-d') ?>" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Total Quantity *</label>
                                <input type="number" name="sim_qty" placeholder="0" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-emerald-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-black font-mono text-emerald-600 dark:text-emerald-400 outline-none focus:border-emerald-500 transition-all shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Reference Number *</label>
                            <input type="text" name="po_number" required placeholder="e.g. PO/2026/001" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-mono font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Batch Name Group *</label>
                            <input type="text" name="batch_name" required placeholder="e.g. BATCH 1" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                        
                        <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-5">
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Upload Document (PDF)</label>
                            <input type="file" name="po_file" class="w-full text-xs text-slate-500 file:cursor-pointer file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 hover:file:shadow-sm dark:file:bg-emerald-900/30 dark:file:text-emerald-400 transition-all border border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-1.5 cursor-pointer bg-slate-50 dark:bg-slate-800">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-slate-800 flex justify-end gap-3 rounded-b-3xl">
            <button type="button" class="btn-close-modal rounded-xl px-6 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-600">Cancel</button>
            <button type="submit" class="flex items-center gap-2 rounded-xl bg-indigo-600 px-8 py-2.5 text-sm font-bold text-white transition-all hover:bg-indigo-700 shadow-md hover:shadow-lg hover:shadow-indigo-500/30 active:scale-95">
                <i class="ph-bold ph-floppy-disk"></i> Save PO Record
            </button>
        </div>
    </form>
</div>

<div id="modalEdit" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <input type="hidden" name="action" value="update"><input type="hidden" name="type" value="client">
        <input type="hidden" name="id" id="edit_id"><input type="hidden" name="existing_file" id="edit_existing_file">
        
        <div class="flex items-center justify-between border-b border-amber-500 px-8 py-5 bg-gradient-to-r from-amber-500 to-orange-500 text-white">
            <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-pencil-simple text-2xl"></i> Edit Client PO</h5>
            <button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-lg"></i></button>
        </div>
        
        <div class="overflow-y-auto p-8 flex-1 custom-scrollbar bg-slate-50 dark:bg-slate-900/50">
            <div class="flex justify-center mb-6">
                <div class="inline-flex bg-slate-200 dark:bg-slate-800 p-1 rounded-xl shadow-inner border border-slate-300 dark:border-slate-700">
                    <label class="cursor-pointer relative text-sm font-bold">
                        <input type="radio" name="edit_input_mode" id="edit_mode_datapool" value="datapool" onchange="toggleInputMode('edit')" class="peer sr-only">
                        <span class="block px-6 py-2 rounded-lg peer-checked:bg-white dark:peer-checked:bg-slate-700 peer-checked:text-amber-600 dark:peer-checked:text-amber-400 peer-checked:shadow-sm text-slate-500 dark:text-slate-400 transition-all">Database</span>
                    </label>
                    <label class="cursor-pointer relative text-sm font-bold">
                        <input type="radio" name="edit_input_mode" id="edit_mode_manual" value="manual" onchange="toggleInputMode('edit')" class="peer sr-only">
                        <span class="block px-6 py-2 rounded-lg peer-checked:bg-white dark:peer-checked:bg-slate-700 peer-checked:text-amber-600 dark:peer-checked:text-amber-400 peer-checked:shadow-sm text-slate-500 dark:text-slate-400 transition-all">Manual</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-amber-500"></div>
                        <h6 class="text-[11px] font-black text-amber-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-identification-badge text-lg"></i> Client Assignment</h6>
                        
                        <div id="edit_section_datapool" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Client Name *</label>
                                <select name="company_id" id="edit_company_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-amber-500 dark:text-white transition-all shadow-sm cursor-pointer" onchange="filterProjects('edit')">
                                    <option value="" class="dark:bg-slate-800">-- Select --</option>
                                    <?php foreach($clients as $c) echo "<option value='{$c['id']}' class='dark:bg-slate-800'>{$c['company_name']}</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Project *</label>
                                <select name="project_id" id="edit_project_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-amber-500 dark:text-white transition-all shadow-sm cursor-pointer">
                                    <option value="" class="dark:bg-slate-800">-- Select --</option>
                                </select>
                            </div>
                        </div>
                        <div id="edit_section_manual" class="hidden space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Manual Client Name *</label>
                                <input type="text" name="manual_company_name" id="edit_manual_company" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-amber-500 dark:text-white transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Manual Project Name</label>
                                <input type="text" name="manual_project_name" id="edit_manual_project" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-amber-500 dark:text-white transition-all shadow-sm">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-orange-500"></div>
                        <h6 class="text-[11px] font-black text-orange-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-package text-lg"></i> Product Specs</h6>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Product</label>
                                <input type="text" name="product_name" id="edit_product_name" list="product_list" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-orange-500 dark:text-white transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Detail / Note</label>
                                <textarea name="detail" id="edit_detail" rows="2" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-orange-500 dark:text-white transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                    <h6 class="text-[11px] font-black text-emerald-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-clipboard-text text-lg"></i> Order Details</h6>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Date *</label>
                                <input type="date" name="po_date" id="edit_po_date" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Total Qty *</label>
                                <input type="number" name="sim_qty" id="edit_sim_qty" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-emerald-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-black font-mono text-emerald-600 dark:text-emerald-400 outline-none focus:border-emerald-500 transition-all shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">PO Number *</label>
                            <input type="text" name="po_number" id="edit_po_number" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-mono font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Batch Name *</label>
                            <input type="text" name="batch_name" id="edit_batch_name" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-bold outline-none focus:border-emerald-500 dark:text-white transition-all shadow-sm uppercase">
                        </div>
                        
                        <div class="mt-6 border-t border-slate-100 dark:border-slate-700 pt-5">
                            <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Update Document (PDF)</label>
                            <input type="file" name="po_file" class="w-full text-xs text-slate-500 file:cursor-pointer file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 hover:file:shadow-sm dark:file:bg-amber-900/30 dark:file:text-amber-400 transition-all border border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-1.5 bg-slate-50 dark:bg-slate-800">
                            
                            <div class="mt-3 flex items-center gap-2 px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-inner">
                                <i class="ph-fill ph-file-pdf text-amber-500 text-lg"></i>
                                <span id="current_file_info" class="text-xs font-bold text-slate-600 dark:text-slate-400 truncate w-full">No file</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-slate-800 flex justify-end gap-3 rounded-b-3xl">
            <button type="button" class="btn-close-modal rounded-xl px-6 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-600">Cancel</button>
            <button type="submit" class="flex items-center gap-2 rounded-xl bg-amber-500 px-8 py-2.5 text-sm font-bold text-white transition-all hover:bg-amber-600 shadow-md hover:shadow-lg hover:shadow-amber-500/30 active:scale-95">
                <i class="ph-bold ph-check-circle"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<div id="modalToProvider" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <form action="process_sim_tracking.php" method="POST" enctype="multipart/form-data" class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <input type="hidden" name="action" value="create_provider_from_client">
        <input type="hidden" name="link_client_po_id" id="tp_client_po_id">
        <input type="hidden" name="batch_name" id="tp_batch_name">
        
        <div class="flex items-center justify-between border-b border-blue-500 px-8 py-5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <h5 class="text-lg font-bold flex items-center gap-2">
                <i class="ph-fill ph-share-network text-2xl"></i> Forward PO to Provider
            </h5>
            <button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-lg"></i></button>
        </div>
        
        <div class="overflow-y-auto p-8 flex-1 custom-scrollbar bg-slate-50 dark:bg-slate-900/50">
            
            <div class="flex items-center gap-4 bg-white dark:bg-slate-800 p-5 rounded-2xl border border-blue-100 dark:border-blue-900/30 shadow-sm mb-6 relative overflow-hidden">
                <div class="absolute left-0 top-0 h-full w-1 bg-blue-500"></div>
                <div class="h-12 w-12 rounded-full bg-blue-50 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center flex-shrink-0 border border-blue-100 dark:border-blue-800/50 shadow-sm">
                    <i class="ph-fill ph-arrow-right text-2xl"></i>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Source Reference</p>
                    <div class="text-sm font-bold text-slate-800 dark:text-white flex items-center flex-wrap gap-2">
                        <span id="tp_display_client" class="text-blue-600 dark:text-blue-400"></span> 
                        <span class="text-slate-300 dark:text-slate-600">|</span> 
                        <span id="tp_display_po" class="font-mono bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-600"></span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
                <h6 class="text-[11px] font-black text-indigo-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-truck text-lg"></i> Provider Setup</h6>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Select Provider *</label>
                        <select name="provider_company_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-medium outline-none focus:border-indigo-500 dark:text-white shadow-sm cursor-pointer" required>
                            <option value="" class="dark:bg-slate-800">-- Choose Provider --</option>
                            <?php foreach($providers as $p): ?>
                                <option value="<?= $p['id'] ?>" class="dark:bg-slate-800"><?= $p['company_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="relative mt-3">
                            <span class="absolute -top-2 left-3 bg-white dark:bg-slate-800 px-1 text-[9px] font-black text-slate-400 uppercase">OR MANUAL NAME</span>
                            <input type="text" name="manual_provider_name" placeholder="Type here if not in list..." class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 text-sm font-medium outline-none focus:border-indigo-500 dark:text-white shadow-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Provider PO No. *</label>
                        <input type="text" name="provider_po_number" required placeholder="e.g. PRV/2026/001" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-sm font-mono font-bold mb-4 outline-none focus:border-indigo-500 dark:text-white shadow-sm uppercase">
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Date</label>
                                <input type="date" name="po_date" value="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 px-3 py-2.5 text-xs font-medium outline-none focus:border-indigo-500 dark:text-white shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Qty</label>
                                <input type="number" name="sim_qty" id="tp_sim_qty" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-indigo-50 dark:bg-slate-900/50 px-3 py-2.5 text-xs font-black font-mono text-indigo-600 dark:text-indigo-400 outline-none focus:border-indigo-500 shadow-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="sm:col-span-2 border-t border-slate-100 dark:border-slate-700 pt-4 mt-2">
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Upload Provider Document</label>
                        <input type="file" name="po_file" class="w-full text-xs text-slate-500 file:cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 hover:file:shadow-sm dark:file:bg-blue-900/30 dark:file:text-blue-400 transition-all border border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-1.5 bg-slate-50 dark:bg-slate-800">
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-slate-800 flex justify-end gap-3 rounded-b-3xl">
            <button type="button" class="btn-close-modal rounded-xl px-6 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
            <button type="submit" class="flex items-center gap-2 rounded-xl bg-blue-600 px-8 py-2.5 text-sm font-bold text-white transition-all hover:bg-blue-700 shadow-md hover:shadow-lg hover:shadow-blue-500/30 active:scale-95">
                <i class="ph-bold ph-paper-plane-right"></i> Execute Transfer
            </button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    const allProjects = <?php echo json_encode($projects_raw); ?>;
    const chartLabels = <?php echo json_encode($js_chart_labels); ?>;
    const chartSeries = <?php echo json_encode($js_chart_series); ?>;

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

        new ApexCharts(document.querySelector('#clientChart'), {
            series: [{ name: 'Quantity', data: chartSeries }],
            chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
            stroke: { curve: 'smooth', width: 2, colors: ['#4f46e5'] },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }, colors: ['#4f46e5'] },
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
            tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: function (val) { return new Intl.NumberFormat('id-ID').format(val) + ' Pcs' } } }
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

    // 3. Logic Filter Projects based on Company
    function filterProjects(mode, selId = null) {
        let compId = $('#' + mode + '_company_id').val();
        let projSel = $('#' + mode + '_project_id');
        let darkClass = document.documentElement.classList.contains('dark') ? 'class="dark:bg-slate-800"' : '';
        projSel.empty().append(`<option value="" ${darkClass}>-- Select Project --</option>`);
        if (compId) {
            allProjects.forEach(p => {
                if (p.company_id == compId) {
                    let s = (selId && p.id == selId) ? 'selected' : '';
                    projSel.append(`<option value="${p.id}" ${s} ${darkClass}>${p.project_name}</option>`);
                }
            });
        }
    }

    // 4. Modal Open Functions
    function openAddModal() {
        $('#add_mode_datapool').prop('checked', true); toggleInputMode('add'); 
        $('#add_company_id').val(''); $('#add_manual_company').val('');
        $('input[name="product_name"]').val(''); $('textarea[name="detail"]').val('');
        
        $('body').css('overflow', 'hidden');
        $('#modalAdd').removeClass('hidden').addClass('flex');
    }

    function openEditModal(data) {
        $('#edit_id').val(data.id);
        $('#edit_po_number').val(data.po_number);
        let pd = (data.po_date && data.po_date !== '0000-00-00') ? data.po_date : new Date().toISOString().split('T')[0];
        $('#edit_po_date').val(pd);
        let qtyClean = String(data.sim_qty).replace(/[^0-9]/g, '');
        $('#edit_sim_qty').val(qtyClean);
        $('#edit_batch_name').val(data.batch_name);
        $('#edit_product_name').val(data.product_name);
        $('#edit_detail').val(data.detail);
        $('#edit_existing_file').val(data.po_file);
        $('#current_file_info').text(data.po_file ? "Current file: " + data.po_file : 'No file attached');
        
        if (data.company_id && data.company_id != 0) {
            $('#edit_mode_datapool').prop('checked', true); toggleInputMode('edit');
            $('#edit_company_id').val(data.company_id);
            filterProjects('edit', data.project_id);
        } else {
            $('#edit_mode_manual').prop('checked', true); toggleInputMode('edit');
            $('#edit_manual_company').val(data.manual_company_name);
            $('#edit_manual_project').val(data.manual_project_name);
        }
        
        $('body').css('overflow', 'hidden');
        $('#modalEdit').removeClass('hidden').addClass('flex');
    }

    function openToProviderModal(data) {
        $('#tp_client_po_id').val(data.id);
        $('#tp_display_client').text(data.display_company || 'Manual');
        $('#tp_display_po').text(data.po_number);
        let qtyClean = String(data.sim_qty).replace(/[^0-9]/g, '');
        $('#tp_sim_qty').val(qtyClean);
        $('#tp_batch_name').val(data.batch_name);
        
        $('body').css('overflow', 'hidden');
        $('#modalToProvider').removeClass('hidden').addClass('flex');
    }

    // 5. Print Function
    function printPO(data) {
        let poDate = data.po_date ? new Date(data.po_date).toLocaleDateString('en-US', {day: 'numeric', month: 'short', year: 'numeric'}) : '-';
        let qty = new Intl.NumberFormat('en-US').format(String(data.sim_qty).replace(/[^0-9]/g, ''));
        let company = data.display_company || '-';
        let project = data.display_project || '-';
        let batch = data.batch_name || '-';
        let poNum = data.po_number || '-';
        let product = data.product_name || 'SIM Card Procurement';
        let specs = data.detail || 'Standard Specification';

        let win = window.open('', '', 'width=900,height=700');
        win.document.write(`
        <html>
        <head>
            <title>Purchase Order - ${poNum}</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 40px; color: #1e293b; line-height: 1.5; }
                .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 40px; }
                .header h1 { font-size: 28px; margin: 0 0 10px 0; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; }
                .header p { font-family: monospace; font-size: 16px; margin: 0; color: #64748b; font-weight: bold; }
                .info-grid { display: flex; justify-content: space-between; margin-bottom: 40px; }
                .info-col { width: 45%; }
                .info-row { margin-bottom: 10px; display: flex; }
                .info-label { width: 100px; font-weight: bold; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;}
                .info-val { font-weight: bold; color: #0f172a; font-size: 14px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th { background-color: #f8fafc; color: #64748b; font-size: 11px; text-transform: uppercase; text-align: left; padding: 12px 15px; border-bottom: 2px solid #e2e8f0; letter-spacing: 1px;}
                .table td { padding: 15px; border-bottom: 1px solid #e2e8f0; font-size: 14px; vertical-align: top;}
                .text-right { text-align: right !important; }
                .footer { margin-top: 80px; display: flex; justify-content: space-between; }
                .sig-box { width: 200px; text-align: center; }
                .sig-line { border-top: 1px solid #94a3b8; padding-top: 10px; font-size: 14px; font-weight: bold; color: #475569; }
                .print-badge { display: inline-block; padding: 4px 8px; background: #f1f5f9; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 12px; font-family: monospace;}
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Client Purchase Order</h1>
                <p>REF: ${poNum}</p>
            </div>
            
            <div class="info-grid">
                <div class="info-col">
                    <div class="info-row"><div class="info-label">CLIENT</div><div class="info-val">${company}</div></div>
                    <div class="info-row"><div class="info-label">PROJECT</div><div class="info-val">${project}</div></div>
                </div>
                <div class="info-col text-right">
                    <div class="info-row" style="justify-content: flex-end;"><div class="info-label">DATE</div><div class="info-val">${poDate}</div></div>
                    <div class="info-row" style="justify-content: flex-end;"><div class="info-label">BATCH</div><div class="info-val"><span class="print-badge">${batch}</span></div></div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th width="5%">NO</th>
                        <th width="75%">ITEM DESCRIPTION & SPECIFICATION</th>
                        <th width="20%" class="text-right">QUANTITY</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>
                            <strong>${product}</strong><br>
                            <span style="color: #64748b; font-size: 12px; font-style: italic; display: block; margin-top: 5px;">Note: ${specs}</span>
                        </td>
                        <td class="text-right"><strong style="font-size: 16px;">${qty}</strong> <span style="font-size:12px; color:#64748b;">PCS</span></td>
                    </tr>
                </tbody>
            </table>

            <div class="footer">
                <div class="sig-box">
                    <br><br><br>
                    <div class="sig-line">Prepared By</div>
                </div>
                <div class="sig-box">
                    <br><br><br>
                    <div class="sig-line">Approved By</div>
                </div>
            </div>
        </body>
        </html>
        `);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); win.close(); }, 800);
    }

    // 6. DataTables Init (Tailwind Design Integration)
    $(document).ready(function() {
        var table = $('#table-client').DataTable({
            language: { 
                search: '', 
                searchPlaceholder: '',
                emptyTable: `<div class="flex flex-col items-center justify-center py-10">
                                <i class="ph-fill ph-inbox text-5xl text-slate-300 dark:text-slate-600 mb-3"></i>
                                <span class="text-slate-500 dark:text-slate-400 font-medium">No records found matching your filters.</span>
                             </div>`
            },
            searching: true, ordering: false, autoWidth: false, pageLength: 10,
            dom: 't<"dataTables_wrapper"p>'
        });
        $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
        $('#customLength').on('change', function() { table.page.len(this.value).draw(); });
        $('#filterClient').on('change', function() { table.column(1).search(this.value).draw(); });
        $('#filterProject').on('change', function() { table.column(1).search(this.value).draw(); }); 
    });
</script>

<?php require_once 'includes/footer.php'; ?>