<?php
// =========================================================================
// FILE: sim_tracking_delivery.php
// DESC: Outbound Delivery Tracking (Ultra-Modern Tailwind CSS)
// FEAT: Auto-Fill Form from Client PO & Separated from Inbound
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);
$current_page = 'sim_tracking_delivery.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

if (!function_exists('e')) {
    function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

// =========================================================================
// 1. DATA LOGIC (QUERY)
// =========================================================================
$opt_projects = []; $opt_couriers = [];

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
    }
} catch (Exception $e) {}

// Filter Logic Outbound
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; 
if (!empty($search_track)) $where_clause .= " AND (l.awb LIKE :search OR l.pic_name LIKE :search)";
if (!empty($filter_project)) $where_clause .= " AND c.company_name = :project";
if (!empty($filter_courier)) $where_clause .= " AND l.courier = :courier";

$data_delivery = [];
try {
    if ($db) {
        $stmt = $db->prepare("SELECT l.*, 
                po.po_number as client_po, 
                po.batch_name,
                COALESCE(c.company_name, po.manual_company_name) as client_name,
                l.logistic_date as delivery_date,
                l.awb as tracking_number,
                l.courier as courier_name,
                l.pic_name as receiver_name,
                l.pic_phone as receiver_phone,
                l.delivery_address as receiver_address,
                l.received_date as delivered_date,
                GROUP_CONCAT(DISTINCT provider_po.po_number SEPARATOR ', ') as ref_provider_po
                FROM sim_tracking_logistics l
                LEFT JOIN sim_tracking_po po ON l.po_id = po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN sim_tracking_po provider_po ON provider_po.link_client_po_id = po.id
                $where_clause
                GROUP BY l.id
                ORDER BY l.logistic_date DESC, l.id DESC");
        
        if (!empty($search_track)) $stmt->bindValue(':search', "%$search_track%");
        if (!empty($filter_project)) $stmt->bindValue(':project', $filter_project);
        if (!empty($filter_courier)) $stmt->bindValue(':courier', $filter_courier);
        
        $stmt->execute();
        $data_delivery = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// DATA PO CLIENT (Untuk Auto-Fill Form)
$client_pos = [];
try {
    // Ambil data detail (pic_name, pic_phone) dari table companies untuk keperluan Auto-fill
    $stmt = $db->query("SELECT po.id, po.po_number, COALESCE(c.company_name, po.manual_company_name) as company_name, c.pic_name, c.pic_phone 
                        FROM sim_tracking_po po 
                        LEFT JOIN companies c ON po.company_id = c.id 
                        WHERE po.type = 'client' ORDER BY po.id DESC");
    if($stmt) {
        $client_pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.5rem 1.25rem; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.6); }
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

    /* DATA TABLES PAGINATION */
    .dataTables_wrapper .dataTables_paginate { display: flex !important; justify-content: flex-end !important; align-items: center !important; gap: 0.5rem !important; margin: 1.5rem 2rem 1.5rem 0 !important; }
    .dataTables_wrapper .dataTables_paginate span { display: flex !important; gap: 0.3rem !important; }
    .paginate_button { display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 0.5rem 1rem !important; border-radius: 0.5rem !important; border: 1px solid #e2e8f0 !important; background-color: #ffffff !important; color: #475569 !important; font-size: 0.875rem !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.2s ease !important; margin: 0 !important; }
    .paginate_button.current { background-color: #3b82f6 !important; border-color: #3b82f6 !important; color: #ffffff !important; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3) !important; }
    .dark .paginate_button { background-color: #1e293b !important; border-color: #334155 !important; color: #cbd5e1 !important; }
    .dark .paginate_button.current { background-color: #3b82f6 !important; border-color: #3b82f6 !important; }
    
    /* Auto-fill Animation */
    .input-highlight { animation: highlightInput 1s ease-out; }
    @keyframes highlightInput { 0% { background-color: #eff6ff; border-color: #3b82f6; } 100% { background-color: #ffffff; border-color: #e2e8f0; } }
    .dark .input-highlight { animation: highlightInputDark 1s ease-out; }
    @keyframes highlightInputDark { 0% { background-color: rgba(59,130,246,0.2); border-color: #3b82f6; } 100% { background-color: #1e293b; border-color: #334155; } }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 tracking-tight">
            Outbound Delivery
        </h2>
        <p class="text-sm font-medium text-slate-500 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-paper-plane-tilt text-lg text-blue-500"></i> Manage outbound shipments to clients.
        </p>
    </div>
    <div class="animate-fade-in-up flex gap-3">
        <button onclick="openDeliveryModal()" class="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700 shadow-lg shadow-blue-500/30 active:scale-95 transition-all">
            <i class="ph-bold ph-plus text-lg"></i> Create Outbound
        </button>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden block">
    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
    <div class="border-b border-slate-100 dark:border-slate-800 p-6 bg-slate-50/50 dark:bg-slate-800/50">
        <form method="GET" action="sim_tracking_delivery.php" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
            <div class="md:col-span-4">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Search Tracking</label>
                <div class="relative">
                    <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <input type="text" name="search_track" value="<?= e($search_track) ?>" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-4 text-sm font-medium outline-none focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200" placeholder="AWB, Receiver...">
                </div>
            </div>
            <div class="md:col-span-4">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Project Filter</label>
                <div class="relative">
                    <i class="ph ph-folder-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <select name="filter_project" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 pl-12 pr-10 text-sm font-medium outline-none focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 cursor-pointer">
                        <option value="">All Projects</option>
                        <?php foreach ($opt_projects as $p) echo "<option value='".e($p)."' ".($filter_project==$p?'selected':'').">".e($p)."</option>"; ?>
                    </select>
                    <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>
            </div>
            <div class="md:col-span-4 flex justify-end gap-2">
                <?php if(!empty($search_track) || !empty($filter_project) || !empty($filter_courier)): ?>
                    <a href="sim_tracking_delivery.php" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition-all border border-slate-200 dark:border-slate-700 flex items-center">Reset</a>
                <?php endif; ?>
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-700 shadow-md flex items-center gap-2 active:scale-95"><i class="ph-bold ph-funnel"></i> Apply Filter</button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-delivery">
            <thead>
                <tr>
                    <th class="ps-8 w-[15%]">Status</th>
                    <th class="w-[20%]">Project / Client</th>
                    <th class="w-[18%]">PO References</th>
                    <th class="w-[18%]">Shipment / AWB</th>
                    <th class="w-[12%]">Dest</th>
                    <th class="text-right w-[8%]">Qty</th>
                    <th class="text-center w-[12%] pe-8">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach($data_delivery as $row): 
                    $st = strtolower($row['status'] ?? '');
                    $statusClass = $st == 'delivered' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="table-row-hover transition-colors">
                    <td class="ps-8 align-top">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border <?= $statusClass ?>"><?= e($row['status']) ?></span>
                        <div class="mt-2 text-[10px] font-bold text-slate-500">Sent: <?= date('d/m/Y', strtotime($row['delivery_date'])) ?></div>
                    </td>
                    <td class="align-top">
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-800 dark:text-white text-sm"><?= e($row['client_name']) ?></span>
                            <span class="text-[10px] text-slate-400 mt-1 uppercase tracking-widest"><i class="ph-fill ph-buildings"></i> Client Entity</span>
                        </div>
                    </td>
                    <td class="align-top">
                        <div class="flex flex-col gap-1.5">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-lg border border-blue-100 dark:border-blue-800 w-max" title="Client PO"><i class="ph-bold ph-receipt text-blue-500"></i> <?= e($row['client_po']) ?></span>
                            <?php if(!empty($row['ref_provider_po'])): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-lg border border-emerald-100 dark:border-emerald-800 w-max" title="Provider PO"><i class="ph-bold ph-truck text-emerald-500"></i> <?= e($row['ref_provider_po']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="align-top">
                        <span class="text-[10px] uppercase font-black tracking-widest text-slate-400 mb-1.5 block"><?= e($row['courier_name']) ?></span>
                        <button onclick='trackResi("<?= e($row['tracking_number']) ?>", "<?= e($row['courier_name']) ?>")' class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[11px] font-mono font-bold border border-slate-200 hover:bg-indigo-50 transition-all shadow-sm"><i class="ph-bold ph-crosshair text-indigo-500"></i> <?= e($row['tracking_number'] ?: 'NO-AWB') ?></button>
                    </td>
                    <td class="align-top"><div class="text-[11px]"><span class="text-slate-400 font-bold uppercase block mb-0.5">To:</span> <span class="dark:text-slate-200 font-bold line-clamp-2" title="<?= e($row['receiver_name']) ?>"><?= e($row['receiver_name']) ?></span></div></td>
                    <td class="text-right align-top"><span class="font-black text-slate-800 dark:text-white font-mono text-xl"><?= number_format($row['qty']) ?></span></td>
                    <td class="pe-8 text-center align-top">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick='viewDetail(<?= $rowJson ?>)' class="h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-blue-600 transition-all shadow-sm flex justify-center items-center"><i class="ph-bold ph-eye text-lg"></i></button>
                            <button onclick='editDelivery(<?= $rowJson ?>)' class="h-9 w-9 rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-amber-600 transition-all shadow-sm flex justify-center items-center"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalDelivery" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <form action="process_sim_tracking.php" method="POST" class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] border border-slate-200 dark:border-slate-700 modal-animate-in">
        <input type="hidden" name="action" id="del_action" value="create_logistic">
        <input type="hidden" name="type" value="delivery"><input type="hidden" name="id" id="del_id">
        <div class="flex items-center justify-between border-b border-blue-500 px-8 py-6 bg-blue-600 text-white shrink-0">
            <div>
                <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-paper-plane-right text-2xl"></i> Automated Outbound Form</h5>
                <p class="text-[10px] text-blue-200 uppercase tracking-widest mt-1">Select Client PO to auto-fill destination data</p>
            </div>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        
        <div class="overflow-y-auto p-8 bg-slate-50/50 dark:bg-slate-900/50 custom-scrollbar flex-1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-slate-800 dark:text-white">
                
                <div class="space-y-5 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                    <h6 class="text-[11px] font-black text-blue-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-target text-lg"></i> Destination Target</h6>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Send Date *</label>
                        <input type="date" name="logistic_date" id="del_date" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm outline-none focus:border-blue-500 shadow-sm">
                    </div>
                    
                    <div class="relative">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Target Client PO *</label>
                        <select name="po_id" id="del_po_id" required onchange="autoFillDelivery()" class="w-full appearance-none rounded-xl border border-blue-300 dark:border-blue-700 bg-blue-50/30 dark:bg-blue-900/20 py-3 pl-4 pr-10 text-sm font-bold text-blue-700 dark:text-blue-400 outline-none focus:ring-2 focus:ring-blue-500/30 shadow-sm cursor-pointer transition-all">
                            <option value="">-- Select Client PO --</option>
                            <?php foreach($client_pos as $po): ?>
                                <option value="<?= $po['id'] ?>" 
                                        data-company="<?= e($po['company_name']) ?>" 
                                        data-pic="<?= e($po['pic_name']) ?>" 
                                        data-phone="<?= e($po['pic_phone']) ?>">
                                    <?= e($po['company_name']) ?> - <?= e($po['po_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="ph ph-caret-down absolute right-4 top-[38px] text-blue-500 pointer-events-none"></i>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Recipient Name</label><input type="text" name="pic_name" id="del_pic" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm transition-all duration-300"></div>
                        <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Contact Phone</label><input type="text" name="pic_phone" id="del_phone" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm transition-all duration-300"></div>
                    </div>
                    <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Full Delivery Address</label><textarea name="delivery_address" id="del_address" rows="3" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900 py-2.5 px-4 text-sm outline-none focus:border-blue-500 shadow-sm resize-none transition-all duration-300"></textarea></div>
                </div>

                <div class="space-y-5 bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
                    <h6 class="text-[11px] font-black text-indigo-500 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="ph-fill ph-truck text-lg"></i> Logistics Provider</h6>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Courier Service</label><input type="text" name="courier" id="del_courier" placeholder="e.g. JNE, Sicepat" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm uppercase font-bold outline-none focus:border-indigo-500 shadow-sm"></div>
                        <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">AWB / Receipt No</label><input type="text" name="awb" id="del_awb" placeholder="Input Resi" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-mono font-bold outline-none focus:border-indigo-500 shadow-sm"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Quantity Sent *</label><input type="number" name="qty" id="del_qty" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-indigo-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-black text-indigo-600 outline-none focus:border-indigo-500 shadow-sm"></div>
                        <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Delivery Status</label><select name="status" id="del_status" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 py-3 px-4 text-sm font-bold outline-none focus:border-indigo-500 shadow-sm"><option value="Process">Process (Packing)</option><option value="Shipped">Shipped (In Transit)</option><option value="Delivered">Delivered (Done)</option></select></div>
                    </div>
                    
                    <div class="mt-6 p-5 bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800 rounded-2xl">
                        <label class="block text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-3"><i class="ph-fill ph-check-circle text-lg"></i> Proof of Delivery (POD)</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div><input type="date" name="received_date" id="del_recv_date" class="w-full rounded-xl border border-indigo-200 dark:border-indigo-700 bg-white dark:bg-slate-800 py-2.5 px-3 text-xs outline-none focus:border-indigo-500 shadow-sm"></div>
                            <div><input type="text" name="receiver_name" id="del_recv_name" placeholder="Accepted By (Name)" class="w-full rounded-xl border border-indigo-200 dark:border-indigo-700 bg-white dark:bg-slate-800 py-2.5 px-3 text-xs outline-none focus:border-indigo-500 shadow-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="border-t border-slate-100 dark:border-slate-800 px-8 py-5 bg-white dark:bg-slate-800 flex justify-end gap-3 shrink-0 rounded-b-3xl">
            <button type="button" class="btn-close-modal px-6 py-3 text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition-all">Cancel</button>
            <button type="submit" class="rounded-xl bg-blue-600 px-8 py-3 text-sm font-bold text-white transition-all hover:bg-blue-700 shadow-md active:scale-95 flex items-center gap-2"><i class="ph-bold ph-paper-plane-tilt"></i> Execute Outbound</button>
        </div>
    </form>
</div>

<div id="trackingModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-blue-500 px-7 py-5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <h5 class="text-lg font-bold flex items-center gap-3"><i class="ph-bold ph-crosshair text-2xl"></i> Live Shipment Tracking</h5>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-8 bg-slate-50 dark:bg-slate-900/50 max-h-[60vh] overflow-y-auto custom-scrollbar" id="trackingResult"></div>
        <div class="border-t border-slate-100 dark:border-slate-800 p-5 bg-white dark:bg-slate-800 flex justify-end">
            <button type="button" class="btn-close-modal px-8 py-2.5 rounded-xl text-sm font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 transition-all">Close Panel</button>
        </div>
    </div>
</div>

<div id="detailModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-7 py-5 bg-white dark:bg-[#24303F]">
            <h5 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-ticket text-blue-500 text-2xl"></i> Delivery Summary</h5>
            <button type="button" class="btn-close-modal text-slate-400 hover:text-slate-700 transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-6 bg-slate-50/50 dark:bg-slate-900/30" id="detailContent"></div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        $('#table-delivery').DataTable({ 
            dom: 't<"dataTables_wrapper"p>',
            pageLength: 50, 
            searching: false, 
            ordering: false,
            language: { paginate: { previous: "Previous", next: "Next" } }
        });

        $('.btn-close-modal').click(function() {
            $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
            $('body').css('overflow', 'auto');
        });
    });

    // ========================================================
    // AUTO FILL LOGIC FOR DELIVERY FORM
    // ========================================================
    function autoFillDelivery() {
        const selected = $('#del_po_id').find(':selected');
        
        // Prevent action if default option is selected
        if(!selected.val()) {
            $('#del_pic').val('');
            $('#del_phone').val('');
            $('#del_address').val('');
            return;
        }

        const pic = selected.data('pic') || 'PIC Name';
        const phone = selected.data('phone') || '';
        const company = selected.data('company') || 'Company Name';
        
        // Create an automated formal address template
        const autoAddress = `TO: ${company}\nATTN: ${pic}\n\n(Please input full building/street address here)`;

        // Apply values and highlight animation
        $('#del_pic').val(pic).addClass('input-highlight');
        $('#del_phone').val(phone).addClass('input-highlight');
        $('#del_address').val(autoAddress).addClass('input-highlight');

        // Remove animation class after it plays (1s)
        setTimeout(() => {
            $('#del_pic, #del_phone, #del_address').removeClass('input-highlight');
        }, 1000);
    }

    // ========================================================
    // ORIGINAL TRACKING JS 
    // ========================================================
    function trackResi(resi, kurir) {
        if(!resi || !kurir) { alert('No tracking data available.'); return; }
        
        $('body').css('overflow', 'hidden'); 
        $('#trackingModal').removeClass('hidden').addClass('flex');
        
        $('#trackingResult').html(`
            <div class="flex flex-col items-center justify-center py-16">
                <div class="animate-spin rounded-full h-12 w-12 border-b-4 border-blue-600 mb-4"></div>
                <p class="text-sm font-bold text-slate-500 tracking-widest uppercase">Connecting to Courier API...</p>
            </div>
        `);
        
        $.ajax({
            url: `ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`,
            method: 'GET',
            success: function(html_response) {
                if(html_response.includes('Data tidak ditemukan') || html_response.includes('Not Found')) {
                    $('#trackingResult').html(`
                        <div class="flex flex-col items-center justify-center py-12 text-slate-500 dark:text-slate-400">
                            <i class="ph-fill ph-warning-circle text-6xl mb-4 text-amber-400 block"></i>
                            <h4 class="font-black text-xl text-slate-800 dark:text-white mb-2 text-center">Data tidak ditemukan.</h4>
                            <p class="text-sm font-medium text-center">Pastikan nomor resi dan kurir benar.</p>
                        </div>
                    `);
                } else {
                    $('#trackingResult').html(html_response);
                }
            },
            error: function() {
                $('#trackingResult').html(`
                    <div class="text-center py-10">
                        <i class="ph-fill ph-wifi-x text-5xl text-red-500 mb-3 block"></i>
                        <span class="text-red-600 font-bold">Failed to fetch API</span>
                    </div>
                `);
            }
        });
    }

    function viewDetail(d) {
        let html = `
            <div class="text-center mb-6 mt-2 relative">
                <div class="absolute top-1/2 left-0 w-full h-px bg-slate-200 -z-10"></div>
                <div class="inline-flex items-center justify-center bg-white border border-slate-200 shadow-sm rounded-full p-4 mb-4 relative z-10"><i class="ph-fill ph-package text-blue-600 text-4xl"></i></div>
                <h4 class="text-2xl font-black text-slate-800 tracking-tight font-mono">${d.tracking_number || 'NO AWB'}</h4>
                <span class="inline-block mt-2 bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-md">${d.courier_name || 'UNKNOWN COURIER'}</span>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm text-sm">
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Client PO Ref</span><span class="font-mono font-bold bg-slate-100 px-2 py-0.5 rounded text-slate-700">${d.client_po || '-'}</span></div>
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Recipient Name</span><span class="font-bold text-slate-800">${d.receiver_name || '-'}</span></div>
                <div class="flex justify-between items-center p-4 border-b border-slate-100"><span class="font-bold text-slate-500">Contact Phone</span><span class="font-bold text-slate-800">${d.receiver_phone || '-'}</span></div>
                <div class="p-4 bg-slate-50"><span class="font-bold text-slate-500 block mb-2">Delivery Address</span><p class="text-slate-700 leading-relaxed">${d.receiver_address || 'No address provided.'}</p></div>
            </div>`;
        $('#detailContent').html(html);
        $('#detailModal').removeClass('hidden').addClass('flex'); $('body').css('overflow', 'hidden');
    }

    function openDeliveryModal() { $('#del_action').val('create_logistic'); $('#del_id').val(''); $('#del_date').val(new Date().toISOString().split('T')[0]); $('#del_po_id').val(''); $('#del_pic').val(''); $('#del_phone').val(''); $('#del_address').val(''); $('#del_courier').val(''); $('#del_awb').val(''); $('#del_qty').val(''); $('#del_status').val('Process'); $('#del_recv_date').val(''); $('#del_recv_name').val(''); $('body').css('overflow', 'hidden'); $('#modalDelivery').removeClass('hidden').addClass('flex'); }
    function editDelivery(d) { $('#del_action').val('update_logistic'); $('#del_id').val(d.id); $('#del_date').val(d.delivery_date); $('#del_po_id').val(d.po_id); $('#del_pic').val(d.receiver_name); $('#del_phone').val(d.receiver_phone); $('#del_address').val(d.receiver_address); $('#del_courier').val(d.courier_name); $('#del_awb').val(d.tracking_number); $('#del_qty').val(d.qty); $('#del_status').val(d.status); $('#del_recv_date').val(d.delivered_date); $('#del_recv_name').val(d.receiver_name); $('body').css('overflow', 'hidden'); $('#modalDelivery').removeClass('hidden').addClass('flex'); }
</script>

<?php require_once 'includes/footer.php'; ?>