<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// DESC: Logistics & Delivery Tracking (Ultra-Modern Tailwind CSS)
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);
$current_page = 'sim_tracking_receive.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

// =========================================================================
// 1. DATA LOGIC (QUERY)
// =========================================================================

// --- A. DATA RECEIVE (INBOUND) ---
$data_receive = [];
try {
    $sql_recv = "SELECT l.*, 
            po.po_number as provider_po, 
            po.batch_name,
            COALESCE(c.company_name, po.manual_company_name) as provider_name
            FROM sim_tracking_logistics l
            LEFT JOIN sim_tracking_po po ON l.po_id = po.id
            LEFT JOIN companies c ON po.company_id = c.id
            WHERE l.type = 'receive'
            ORDER BY l.logistic_date DESC, l.id DESC";
    if ($db) {
        $stmt = $db->query($sql_recv);
        if($stmt) $data_receive = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// --- B. DATA DELIVERY (OUTBOUND) ---
$opt_projects = []; $opt_couriers = []; $opt_receivers = [];

try {
    if ($db) {
        $q_proj = "SELECT DISTINCT c.company_name as project_name 
                   FROM sim_tracking_logistics l 
                   JOIN sim_tracking_po po ON l.po_id = po.id
                   JOIN companies c ON po.company_id = c.id
                   WHERE l.type='delivery' ORDER BY c.company_name ASC";
        $stmt = $db->query($q_proj);
        if($stmt) $opt_projects = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $q_cour = "SELECT DISTINCT courier FROM sim_tracking_logistics WHERE type='delivery' AND courier != '' ORDER BY courier ASC";
        $stmt = $db->query($q_cour);
        if($stmt) $opt_couriers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $q_recv = "SELECT DISTINCT pic_name FROM sim_tracking_logistics WHERE type='delivery' AND pic_name != '' ORDER BY pic_name ASC";
        $stmt = $db->query($q_recv);
        if($stmt) $opt_receivers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {}

// Filter Logic
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';
$filter_receiver = $_GET['filter_receiver'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; 
if (!empty($search_track)) $where_clause .= " AND (l.awb LIKE '%$search_track%' OR l.pic_name LIKE '%$search_track%')";
if (!empty($filter_project)) $where_clause .= " AND c.company_name = '$filter_project'";
if (!empty($filter_courier)) $where_clause .= " AND l.courier = '$filter_courier'";
if (!empty($filter_receiver)) $where_clause .= " AND l.pic_name = '$filter_receiver'";

// Main Query Delivery
$data_delivery = [];
try {
    $sql_del = "SELECT l.*, 
                po.po_number as client_po, 
                po.batch_name,
                COALESCE(c.company_name, po.manual_company_name) as client_name,
                l.logistic_date as delivery_date,
                l.awb as tracking_number,
                l.courier as courier_name,
                l.pic_name as receiver_name,
                l.pic_phone as receiver_phone,
                l.delivery_address as receiver_address,
                l.received_date as delivered_date
                FROM sim_tracking_logistics l
                LEFT JOIN sim_tracking_po po ON l.po_id = po.id
                LEFT JOIN companies c ON po.company_id = c.id
                $where_clause
                ORDER BY l.logistic_date DESC, l.id DESC";
    if ($db) {
        $stmt = $db->query($sql_del);
        if($stmt) $data_delivery = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// --- C. DATA PO OPTIONS ---
$provider_pos = []; $client_pos = [];
try {
    $stmt = $db->query("SELECT po.id, po.po_number, po.type, COALESCE(c.company_name, po.manual_company_name) as company_name FROM sim_tracking_po po LEFT JOIN companies c ON po.company_id = c.id ORDER BY po.id DESC");
    if($stmt) {
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($all as $p) {
            if($p['type']=='provider') $provider_pos[]=$p; else $client_pos[]=$p;
        }
    }
} catch (Exception $e) {}
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* Table Enhancements */
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.5rem 1.25rem; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.6); }

    /* Custom Scrollbar for Modal content */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }

    /* DataTables Pagination Alignment */
    .dataTables_wrapper .dataTables_paginate { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.35rem; padding-top: 0 !important; }
    .dataTables_wrapper .dataTables_paginate span { display: flex; gap: 0.35rem; }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        display: inline-flex; align-items: center; justify-content: center; padding: 0.375rem 0.85rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; background: #fff; color: #475569 !important; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-left: 0 !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) { background: #f8fafc !important; border-color: #cbd5e1 !important; color: #0f172a !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.5; cursor: not-allowed; background: #f8fafc !important; border-color: #e2e8f0 !important; color: #94a3b8 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #3b82f6 !important; border-color: #3b82f6 !important; color: #fff !important; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3); }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button { background: #1e293b; border-color: #334155; color: #cbd5e1 !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #4f46e5 !important; border-color: #4f46e5 !important; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400 tracking-tight">
            Logistics & Delivery
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-package text-lg text-blue-500"></i> Monitor inbound supplies and outbound shipments.
        </p>
    </div>
    <div class="animate-fade-in-up flex gap-3" style="animation-delay: 0.1s;">
        <button onclick="openReceiveModal()" class="flex items-center gap-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm active:scale-95 transition-all">
            <i class="ph-bold ph-download-simple text-emerald-500"></i> Log Inbound (Receive)
        </button>
        <button onclick="openDeliveryModal()" class="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700 shadow-lg shadow-blue-500/30 active:scale-95 transition-all">
            <i class="ph-bold ph-paper-plane-right"></i> Log Outbound (Delivery)
        </button>
    </div>
</div>

<div class="flex gap-2 border-b border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.2s;">
    <button onclick="switchTab('delivery')" id="tab-btn-delivery" class="px-6 py-3.5 text-sm font-bold border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-paper-plane-tilt"></i> Outbound (Sent)
    </button>
    <button onclick="switchTab('receive')" id="tab-btn-receive" class="px-6 py-3.5 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-download-simple"></i> Inbound (Received)
    </button>
</div>

<div id="tab-content-delivery" class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden block" style="animation-delay: 0.3s;">
    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
    
    <div class="border-b border-slate-100 dark:border-slate-800 p-6 bg-slate-50/50 dark:bg-slate-800/50">
        <form method="GET" action="sim_tracking_receive.php" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
            <div class="md:col-span-3">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Search Tracking</label>
                <div class="relative">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <input type="text" name="search_track" value="<?= htmlspecialchars($search_track ?? '') ?>" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm font-medium outline-none focus:border-blue-500 shadow-sm transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200" placeholder="AWB, Receiver...">
                </div>
            </div>
            <div class="md:col-span-3">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Filter Project</label>
                <select name="filter_project" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-medium outline-none focus:border-blue-500 shadow-sm cursor-pointer transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                    <option value="">All Projects</option>
                    <?php foreach ($opt_projects as $p) echo "<option value='$p' ".($filter_project==$p?'selected':'').">$p</option>"; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Courier</label>
                <select name="filter_courier" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-medium outline-none focus:border-blue-500 shadow-sm cursor-pointer transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                    <option value="">All Couriers</option>
                    <?php foreach ($opt_couriers as $c) echo "<option value='$c' ".($filter_courier==$c?'selected':'').">$c</option>"; ?>
                </select>
            </div>
            <div class="md:col-span-4 flex justify-end gap-2 h-[42px]">
                <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier) || !empty($filter_receiver)): ?>
                    <a href="sim_tracking_receive.php" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition-all flex items-center border border-slate-200">Reset</a>
                <?php endif; ?>
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white transition-all hover:bg-blue-700 shadow-md active:scale-95 flex items-center gap-2"><i class="ph-bold ph-funnel"></i> Apply Filter</button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-delivery">
            <thead>
                <tr>
                    <th class="ps-8 w-32">Status</th>
                    <th class="w-48">Project / Client</th>
                    <th class="w-48">Shipment / AWB</th>
                    <th class="w-32">Origin (WH)</th>
                    <th class="w-48">Recipient</th>
                    <th class="text-right w-24">Qty</th>
                    <th class="text-center w-32 pe-8">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if(empty($data_delivery)): ?>
                    <tr><td colspan="7" class="px-8 py-12 text-center text-slate-500 dark:text-slate-400"><div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-3"><i class="ph-fill ph-package text-3xl opacity-50"></i></div><p class="font-medium">No delivery records found.</p></td></tr>
                <?php else: ?>
                    <?php foreach($data_delivery as $index => $row): 
                        $st = strtolower($row['status'] ?? '');
                        $statusClass = 'bg-amber-50 text-amber-700 border-amber-200'; $icon = 'ph-clock';
                        if(strpos($st, 'shipped')!==false) { $statusClass = 'bg-blue-50 text-blue-700 border-blue-200'; $icon = 'ph-truck'; }
                        if(strpos($st, 'delivered')!==false) { $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200'; $icon = 'ph-check-circle'; }
                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        $animDelay = min($index * 0.05, 0.5);
                    ?>
                    <tr class="table-row-hover transition-colors animate-fade-in-up opacity-0 group" style="animation-delay: <?= $animDelay ?>s;">
                        <td class="ps-8">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border shadow-sm <?= $statusClass ?>">
                                <i class="ph-bold <?= $icon ?> text-sm"></i> <?= htmlspecialchars($row['status']) ?>
                            </span>
                            <div class="mt-2 text-[11px] font-bold text-slate-500"><span class="text-slate-400">Sent:</span> <?= date('d M Y', strtotime($row['delivery_date'])) ?></div>
                            <?php if(!empty($row['delivered_date'])): ?>
                                <div class="mt-0.5 text-[11px] font-bold text-emerald-600"><span class="text-emerald-400">Rcvd:</span> <?= date('d M Y', strtotime($row['delivered_date'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-start gap-2.5">
                                <div class="mt-1 h-8 w-8 rounded-lg bg-blue-50 text-blue-500 border border-blue-100 flex items-center justify-center shrink-0"><i class="ph-fill ph-buildings"></i></div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 text-sm line-clamp-1" title="<?= htmlspecialchars($row['client_name']) ?>"><?= htmlspecialchars($row['client_name']) ?></span>
                                    <span class="text-[10px] uppercase font-black tracking-widest text-slate-400 mt-1">PO: <?= htmlspecialchars($row['client_po']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-black tracking-widest text-slate-400 mb-1"><?= htmlspecialchars($row['courier_name']) ?></span>
                                <button onclick='trackResi("<?= htmlspecialchars($row['tracking_number']) ?>", "<?= htmlspecialchars($row['courier_name']) ?>")' class="w-max inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-200 text-slate-700 hover:text-indigo-700 text-xs font-mono font-bold transition-all shadow-sm">
                                    <i class="ph-bold ph-crosshair"></i> <?= htmlspecialchars($row['tracking_number'] ?: 'NO-AWB') ?>
                                </button>
                            </div>
                        </td>
                        <td>
                            <span class="font-bold text-slate-800 text-sm block">PT LinksField</span>
                            <span class="text-[10px] uppercase font-black tracking-widest text-slate-400">WH Jakarta</span>
                        </td>
                        <td>
                            <span class="font-bold text-slate-800 text-sm block line-clamp-1" title="<?= htmlspecialchars($row['receiver_name']) ?>"><i class="ph-fill ph-user text-slate-400 mr-1"></i> <?= htmlspecialchars($row['receiver_name']) ?></span>
                            <span class="text-xs font-medium text-slate-500 mt-1 block"><i class="ph-fill ph-phone text-slate-400 mr-1"></i> <?= htmlspecialchars($row['receiver_phone']) ?></span>
                        </td>
                        <td class="text-right">
                            <span class="font-black text-slate-800 font-mono text-xl"><?= number_format($row['qty']) ?></span>
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block">PCS</span>
                        </td>
                        <td class="pe-8 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick='viewDetail(<?= $rowJson ?>)' class="h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 flex items-center justify-center transition-all shadow-sm" title="View Details"><i class="ph-bold ph-eye text-lg"></i></button>
                                <button onclick='editDelivery(<?= $rowJson ?>)' class="h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 flex items-center justify-center transition-all shadow-sm" title="Edit"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="tab-content-receive" class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden hidden">
    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
    <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50">
        <h4 class="text-lg font-bold text-slate-800 dark:text-white">Internal Warehouse Inbound</h4>
        <p class="text-xs text-slate-500">Log history of physical SIM cards received at the internal warehouse.</p>
    </div>
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-receive">
            <thead>
                <tr>
                    <th class="ps-8">Received Date</th>
                    <th>Status</th>
                    <th>Supplier Origin</th>
                    <th>Reference PO</th>
                    <th>WH PIC</th>
                    <th class="text-right">Received Qty</th>
                    <th class="pe-8 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach($data_receive as $index => $row): 
                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); 
                    $animDelay = min($index * 0.05, 0.5);
                ?>
                <tr class="table-row-hover transition-colors animate-fade-in-up opacity-0" style="animation-delay: <?= $animDelay ?>s;">
                    <td class="ps-8 font-bold text-slate-700 text-sm"><i class="ph-fill ph-calendar-check text-emerald-500 mr-2"></i> <?= date('d M Y', strtotime($row['logistic_date'])) ?></td>
                    <td><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border shadow-sm bg-emerald-50 text-emerald-700 border-emerald-200"><i class="ph-bold ph-check-circle text-sm"></i> Safely Arrived</span></td>
                    <td>
                        <span class="font-bold text-slate-800 text-sm block"><?= htmlspecialchars($row['provider_name']) ?></span>
                        <span class="text-[10px] uppercase font-black tracking-widest text-slate-400">Batch: <?= htmlspecialchars($row['batch_name']) ?></span>
                    </td>
                    <td><code class="text-xs font-bold text-indigo-500 bg-indigo-50 px-2 py-1 rounded-md border border-indigo-100"><?= htmlspecialchars($row['provider_po']) ?></code></td>
                    <td>
                        <span class="font-bold text-slate-800 text-sm block">Internal HQ</span>
                        <span class="text-[10px] uppercase font-black tracking-widest text-slate-400"><i class="ph-fill ph-user text-emerald-500"></i> <?= htmlspecialchars($row['pic_name']) ?></span>
                    </td>
                    <td class="text-right">
                        <span class="font-black text-emerald-600 font-mono text-xl">+<?= number_format($row['qty']) ?></span>
                    </td>
                    <td class="pe-8 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick='editReceive(<?= $rowJson ?>)' class="h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 flex items-center justify-center transition-all shadow-sm"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                            <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" onclick="return confirm('Delete this inbound log?')" class="h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-red-50 hover:text-red-600 hover:border-red-200 flex items-center justify-center transition-all shadow-sm"><i class="ph-fill ph-trash text-lg"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="trackingModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl flex flex-col overflow-hidden border border-slate-200 modal-animate-in">
        <div class="flex items-center justify-between border-b border-blue-500 px-7 py-5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <h5 class="text-lg font-bold flex items-center gap-3"><i class="ph-bold ph-crosshair text-2xl"></i> Live Shipment Tracking</h5>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-8 bg-slate-50 max-h-[60vh] overflow-y-auto custom-scrollbar" id="trackingResult">
            </div>
        <div class="border-t border-slate-100 p-5 bg-white flex justify-end">
            <button type="button" class="btn-close-modal px-8 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-50 transition-all border border-slate-200">Close Panel</button>
        </div>
    </div>
</div>

<div id="detailModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl flex flex-col overflow-hidden border border-slate-200 modal-animate-in">
        <div class="flex items-center justify-between border-b border-slate-100 px-7 py-5 bg-white">
            <h5 class="text-lg font-bold text-slate-800 flex items-center gap-2"><i class="ph-fill ph-ticket text-blue-500 text-2xl"></i> Delivery Summary</h5>
            <button type="button" class="btn-close-modal text-slate-400 hover:text-slate-700 transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-6 bg-slate-50/50" id="detailContent">
            </div>
    </div>
</div>

<div id="modalDelivery" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <form action="process_sim_tracking.php" method="POST" class="w-full max-w-4xl rounded-3xl bg-white shadow-2xl flex flex-col max-h-[95vh] border border-slate-200 modal-animate-in">
        <input type="hidden" name="action" id="del_action" value="create_logistic">
        <input type="hidden" name="type" value="delivery"><input type="hidden" name="id" id="del_id">
        <div class="flex items-center justify-between border-b border-blue-500 px-7 py-5 bg-blue-600 text-white"><h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-paper-plane-right text-xl"></i> Outbound Shipment Form</h5><button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-xl"></i></button></div>
        
        <div class="overflow-y-auto p-8 bg-slate-50/50 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-4">
                    <h6 class="text-[11px] font-black text-blue-500 uppercase tracking-widest border-b border-slate-200 pb-2 mb-4">Destination Target</h6>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Send Date</label><input type="date" name="logistic_date" id="del_date" required class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Target Client PO</label><select name="po_id" id="del_po_id" required class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-bold outline-none focus:border-blue-500 shadow-sm"><option value="">-- Select Client PO --</option><?php foreach($client_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Recipient Name</label><input type="text" name="pic_name" id="del_pic" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm"></div>
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Contact Phone</label><input type="text" name="pic_phone" id="del_phone" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm"></div>
                    </div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Full Delivery Address</label><textarea name="delivery_address" id="del_address" rows="3" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm resize-none"></textarea></div>
                </div>

                <div class="space-y-4">
                    <h6 class="text-[11px] font-black text-indigo-500 uppercase tracking-widest border-b border-slate-200 pb-2 mb-4">Logistics Provider</h6>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Courier Service</label><input type="text" name="courier" id="del_courier" placeholder="e.g. JNE, Sicepat" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm uppercase font-bold outline-none focus:border-blue-500 shadow-sm"></div>
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">AWB / Receipt Number</label><input type="text" name="awb" id="del_awb" placeholder="Input Resi" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-mono font-bold outline-none focus:border-blue-500 shadow-sm"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Quantity Sent</label><input type="number" name="qty" id="del_qty" required class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-black text-blue-600 outline-none focus:border-blue-500 shadow-sm"></div>
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Delivery Status</label><select name="status" id="del_status" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-bold outline-none focus:border-blue-500 shadow-sm"><option value="Process">Process (Packing)</option><option value="Shipped">Shipped (In Transit)</option><option value="Delivered">Delivered (Done)</option></select></div>
                    </div>
                    <div class="mt-4 p-4 bg-indigo-50/50 border border-indigo-100 rounded-2xl">
                        <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-3"><i class="ph-fill ph-check-circle"></i> Proof of Delivery (POD)</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div><input type="date" name="received_date" id="del_recv_date" class="w-full rounded-xl border border-indigo-200 bg-white py-2 px-3 text-xs outline-none focus:border-indigo-500 shadow-sm"></div>
                            <div><input type="text" name="receiver_name" id="del_recv_name" placeholder="Accepted By (Name)" class="w-full rounded-xl border border-indigo-200 bg-white py-2 px-3 text-xs outline-none focus:border-indigo-500 shadow-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="border-t border-slate-100 px-7 py-5 bg-white flex justify-end gap-3 rounded-b-3xl">
            <button type="button" class="btn-close-modal px-6 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition-all">Cancel</button>
            <button type="submit" class="rounded-xl bg-blue-600 px-8 py-2.5 text-sm font-bold text-white transition-all hover:bg-blue-700 shadow-md active:scale-95"><i class="ph-bold ph-floppy-disk mr-1"></i> Save Outbound</button>
        </div>
    </form>
</div>

<div id="modalReceive" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl flex flex-col overflow-hidden border border-slate-200 modal-animate-in">
        <form action="process_sim_tracking.php" method="POST">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive"><input type="hidden" name="id" id="recv_id">
            <div class="flex items-center justify-between border-b border-emerald-500 px-6 py-4 bg-emerald-600 text-white"><h5 class="text-base font-bold flex items-center gap-2"><i class="ph-bold ph-download-simple text-xl"></i> Internal WH Receive</h5><button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 transition-colors"><i class="ph ph-x text-lg"></i></button></div>
            <div class="p-6 bg-slate-50/50">
                <div class="mb-4"><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Receive Date</label><input type="date" name="logistic_date" id="recv_date" required class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm outline-none focus:border-emerald-500 shadow-sm"></div>
                <div class="mb-4"><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Source Provider PO</label><select name="po_id" id="recv_po_id" required class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm font-bold outline-none focus:border-emerald-500 shadow-sm"><option value="">-- Select Source PO --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">WH Receiver PIC</label><input type="text" name="pic_name" id="recv_pic" placeholder="Internal Name" class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm outline-none focus:border-emerald-500 shadow-sm"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Total Qty Received</label><input type="number" name="qty" id="recv_qty" required placeholder="0" class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm font-black text-emerald-600 outline-none focus:border-emerald-500 shadow-sm"></div>
                </div>
            </div>
            <div class="border-t border-slate-100 px-6 py-4 bg-white flex justify-end gap-2"><button type="button" class="btn-close-modal px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl">Cancel</button><button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 shadow-md active:scale-95">Save Inbound</button></div>
        </form>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    // Tab Logic
    function switchTab(tabId) {
        if(tabId === 'delivery') {
            $('#tab-btn-delivery').removeClass('border-transparent text-slate-500 dark:text-slate-400').addClass('border-blue-600 text-blue-600 dark:text-blue-400');
            $('#tab-btn-receive').removeClass('border-blue-600 text-blue-600 dark:text-blue-400').addClass('border-transparent text-slate-500 dark:text-slate-400');
            $('#tab-content-delivery').removeClass('hidden').addClass('block');
            $('#tab-content-receive').removeClass('block').addClass('hidden');
        } else {
            $('#tab-btn-receive').removeClass('border-transparent text-slate-500 dark:text-slate-400').addClass('border-blue-600 text-blue-600 dark:text-blue-400');
            $('#tab-btn-delivery').removeClass('border-blue-600 text-blue-600 dark:text-blue-400').addClass('border-transparent text-slate-500 dark:text-slate-400');
            $('#tab-content-receive').removeClass('hidden').addClass('block');
            $('#tab-content-delivery').removeClass('block').addClass('hidden');
        }
    }

    $(document).ready(function() {
        // DataTables Init
        $('#table-delivery').DataTable({ dom: 't<"dataTables_wrapper"p>', pageLength: 50, searching: false, ordering: false });
        $('#table-receive').DataTable({ dom: 't<"dataTables_wrapper"p>', pageLength: 50, searching: false, ordering: false });

        // Global Modal Close
        $('.btn-close-modal').click(function() {
            $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
            $('body').css('overflow', 'auto');
        });
        $('.modal-container').click(function(e) {
            if(e.target === this) { $(this).removeClass('flex').addClass('hidden'); $('body').css('overflow', 'auto'); }
        });
    });

    // Tracking API Call
    function trackResi(resi, kurir) {
        if(!resi || !kurir) { alert('No tracking data available.'); return; }
        
        $('body').css('overflow', 'hidden'); $('#trackingModal').removeClass('hidden').addClass('flex');
        $('#trackingResult').html('<div class="flex flex-col items-center justify-center py-16"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div><p class="text-sm font-bold text-slate-500 tracking-widest uppercase">Connecting to Courier API...</p></div>');
        
        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`)
            .then(r => r.text())
            .then(html => { $('#trackingResult').html(html); })
            .catch(e => { $('#trackingResult').html('<div class="text-center py-10"><i class="ph-fill ph-wifi-x text-5xl text-red-500 mb-3 block"></i><span class="text-red-600 font-bold">Failed to fetch API</span></div>'); });
    }

    // Detail UI Render
    function viewDetail(d) {
        let html = `
            <div class="text-center mb-6 mt-2 relative">
                <div class="absolute top-1/2 left-0 w-full h-px bg-slate-200 -z-10"></div>
                <div class="inline-flex items-center justify-center bg-white border border-slate-200 shadow-sm rounded-full p-4 mb-4 relative z-10"><i class="ph-fill ph-package text-blue-600 text-4xl"></i></div>
                <h4 class="text-2xl font-black text-slate-800 tracking-tight font-mono">${d.tracking_number || 'NO AWB'}</h4>
                <span class="inline-block mt-2 bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-md">${d.courier_name || 'UNKNOWN COURIER'}</span>
            </div>
            
            <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-5 shadow-sm">
                <div class="flex justify-between items-center relative">
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-slate-300"><i class="ph-fill ph-arrow-right text-2xl"></i></div>
                    <div class="w-1/2 pr-4">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">SENDER</label>
                        <p class="font-bold text-slate-800 text-sm">PT LinksField</p>
                    </div>
                    <div class="w-1/2 pl-4 text-right">
                        <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">RECEIVER (CLIENT)</label>
                        <p class="font-bold text-blue-600 text-sm truncate" title="${d.client_name || '-'}">${d.client_name || '-'}</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm text-sm">
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Client PO Ref</span><span class="font-mono font-bold bg-slate-100 px-2 py-0.5 rounded text-slate-700">${d.client_po || '-'}</span></div>
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Recipient Name</span><span class="font-bold text-slate-800">${d.receiver_name || '-'}</span></div>
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Contact Phone</span><span class="font-bold text-slate-800">${d.receiver_phone || '-'}</span></div>
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Shipment Date</span><span class="font-bold text-slate-800">${d.delivery_date}</span></div>
                <div class="p-4 bg-slate-50"><span class="font-bold text-slate-500 block mb-2">Delivery Address</span><p class="text-slate-700 leading-relaxed">${d.receiver_address || 'No address provided.'}</p></div>
            </div>`;
        document.getElementById('detailContent').innerHTML = html;
        $('body').css('overflow', 'hidden'); $('#detailModal').removeClass('hidden').addClass('flex');
    }

    // Modal Triggers
    function openReceiveModal() { $('#recv_action').val('create_logistic'); $('#recv_id').val(''); $('#recv_date').val(new Date().toISOString().split('T')[0]); $('#recv_pic').val(''); $('#recv_po_id').val(''); $('#recv_qty').val(''); $('body').css('overflow', 'hidden'); $('#modalReceive').removeClass('hidden').addClass('flex'); }
    function editReceive(d) { $('#recv_action').val('update_logistic'); $('#recv_id').val(d.id); $('#recv_date').val(d.logistic_date); $('#recv_pic').val(d.pic_name); $('#recv_po_id').val(d.po_id); $('#recv_qty').val(d.qty); $('body').css('overflow', 'hidden'); $('#modalReceive').removeClass('hidden').addClass('flex'); }
    function openDeliveryModal() { $('#del_action').val('create_logistic'); $('#del_id').val(''); $('#del_date').val(new Date().toISOString().split('T')[0]); $('#del_po_id').val(''); $('#del_pic').val(''); $('#del_phone').val(''); $('#del_address').val(''); $('#del_courier').val(''); $('#del_awb').val(''); $('#del_qty').val(''); $('#del_status').val('Process'); $('#del_recv_date').val(''); $('#del_recv_name').val(''); $('body').css('overflow', 'hidden'); $('#modalDelivery').removeClass('hidden').addClass('flex'); }
    function editDelivery(d) { $('#del_action').val('update_logistic'); $('#del_id').val(d.id); $('#del_date').val(d.delivery_date); $('#del_po_id').val(d.po_id); $('#del_pic').val(d.receiver_name); $('#del_phone').val(d.receiver_phone); $('#del_address').val(d.receiver_address); $('#del_courier').val(d.courier_name); $('#del_awb').val(d.tracking_number); $('#del_qty').val(d.qty); $('#del_status').val(d.status); $('#del_recv_date').val(d.delivered_date); $('#del_recv_name').val(d.receiver_name); $('body').css('overflow', 'hidden'); $('#modalDelivery').removeClass('hidden').addClass('flex'); }
</script>

<?php require_once 'includes/footer.php'; ?>