<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// DESC: Logistics & Delivery Tracking (Ultra-Modern Tailwind CSS)
// FIX: Duplicate Rows Fix, Pagination Fix, & Null Parameter Deprecation Fix
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);
$current_page = 'sim_tracking_receive.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

// Helper untuk keamanan teks (Fix Deprecated Null Parameter #1)
if (!function_exists('e')) {
    function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

// =========================================================================
// 1. DATA LOGIC (QUERY)
// =========================================================================

// --- A. DATA RECEIVE (INBOUND) ---
$data_receive = [];
try {
    // Menambahkan GROUP BY l.id untuk mencegah duplikasi jika satu PO punya banyak link
    $sql_recv = "SELECT l.*, 
            po.po_number as provider_po, 
            po.batch_name,
            COALESCE(c.company_name, po.manual_company_name) as provider_name,
            GROUP_CONCAT(DISTINCT linked_client.po_number SEPARATOR ', ') as linked_client_po
            FROM sim_tracking_logistics l
            LEFT JOIN sim_tracking_po po ON l.po_id = po.id
            LEFT JOIN companies c ON po.company_id = c.id
            LEFT JOIN sim_tracking_po linked_client ON po.link_client_po_id = linked_client.id
            WHERE l.type = 'receive'
            GROUP BY l.id
            ORDER BY l.logistic_date DESC, l.id DESC";
    if ($db) {
        $stmt = $db->query($sql_recv);
        if($stmt) $data_receive = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

// --- B. DATA DELIVERY (OUTBOUND) ---
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

// Filter Logic
$search_track = $_GET['search_track'] ?? '';
$filter_project = $_GET['filter_project'] ?? '';
$filter_courier = $_GET['filter_courier'] ?? '';

$where_clause = "WHERE l.type = 'delivery'"; 
if (!empty($search_track)) $where_clause .= " AND (l.awb LIKE :search OR l.pic_name LIKE :search)";
if (!empty($filter_project)) $where_clause .= " AND c.company_name = :project";
if (!empty($filter_courier)) $where_clause .= " AND l.courier = :courier";

// Main Query Delivery (Outbound)
$data_delivery = [];
try {
    if ($db) {
        // Menggunakan GROUP BY l.id untuk memastikan tidak ada row double akibat join PO provider
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
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.5rem 1.25rem; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    
    /* Pagination Style Fix */
    .dataTables_wrapper .dataTables_paginate { display: flex !important; justify-content: center !important; gap: 4px !important; margin-top: 1.5rem !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { border-radius: 8px !important; border: 1px solid #e2e8f0 !important; padding: 0.4rem 0.8rem !important; font-size: 0.8rem !important; font-weight: 600 !important; cursor: pointer !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #3b82f6 !important; color: white !important; border-color: #3b82f6 !important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) { background: #f1f5f9 !important; }
    .dark .dataTables_wrapper .dataTables_paginate .paginate_button { border-color: #334155 !important; color: #cbd5e1 !important; }
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
    <div class="animate-fade-in-up flex gap-3">
        <button onclick="openReceiveModal()" class="flex items-center gap-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200 shadow-sm active:scale-95 transition-all">
            <i class="ph-bold ph-download-simple text-emerald-500"></i> Log Inbound
        </button>
        <button onclick="openDeliveryModal()" class="flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white hover:bg-blue-700 shadow-lg shadow-blue-500/30 active:scale-95 transition-all">
            <i class="ph-bold ph-paper-plane-right"></i> Log Outbound
        </button>
    </div>
</div>

<div class="flex gap-2 border-b border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up">
    <button onclick="switchTab('delivery')" id="tab-btn-delivery" class="px-6 py-3.5 text-sm font-bold border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-paper-plane-tilt"></i> Outbound (Sent)
    </button>
    <button onclick="switchTab('receive')" id="tab-btn-receive" class="px-6 py-3.5 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-download-simple"></i> Inbound (Received)
    </button>
</div>

<div id="tab-content-delivery" class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden block">
    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
    <div class="border-b border-slate-100 dark:border-slate-800 p-6 bg-slate-50/50 dark:bg-slate-800/50">
        <form method="GET" action="sim_tracking_receive.php" class="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
            <div class="md:col-span-4">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Search Tracking</label>
                <input type="text" name="search_track" value="<?= e($search_track) ?>" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-medium outline-none focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200" placeholder="AWB, Receiver...">
            </div>
            <div class="md:col-span-4">
                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-widest text-slate-400">Project</label>
                <select name="filter_project" class="w-full appearance-none rounded-2xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-medium outline-none focus:border-blue-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
                    <option value="">All Projects</option>
                    <?php foreach ($opt_projects as $p) echo "<option value='".e($p)."' ".($filter_project==$p?'selected':'').">".e($p)."</option>"; ?>
                </select>
            </div>
            <div class="md:col-span-4 flex justify-end gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-blue-700 shadow-md flex items-center gap-2"><i class="ph-bold ph-funnel"></i> Apply Filter</button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-delivery">
            <thead>
                <tr>
                    <th class="ps-8 w-32">Status</th>
                    <th class="w-56">Project / Client</th>
                    <th class="w-48">Shipment / AWB</th>
                    <th class="w-48">Origin / Destination</th>
                    <th class="text-right w-24">Qty</th>
                    <th class="text-center w-32 pe-8">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if(empty($data_delivery)): ?>
                    <tr><td colspan="7" class="px-8 py-12 text-center text-slate-500 dark:text-slate-400"><p class="font-medium">No delivery records found.</p></td></tr>
                <?php else: ?>
                    <?php foreach($data_delivery as $row): 
                        $st = strtolower($row['status'] ?? '');
                        $statusClass = $st == 'delivered' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                        $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="table-row-hover transition-colors">
                        <td class="ps-8">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border <?= $statusClass ?>">
                                <?= e($row['status']) ?>
                            </span>
                            <div class="mt-2 text-[10px] font-bold text-slate-500">Sent: <?= date('d/m/Y', strtotime($row['delivery_date'])) ?></div>
                        </td>
                        <td>
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-800 dark:text-white text-sm"><?= e($row['client_name']) ?></span>
                                <div class="flex flex-col gap-0.5 mt-1">
                                    <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-1.5 rounded border border-blue-100 dark:border-blue-800 w-max">Client PO: <?= e($row['client_po']) ?></span>
                                    <?php if(!empty($row['ref_provider_po'])): ?>
                                    <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-1.5 rounded border border-emerald-100 dark:border-emerald-800 w-max">Prov PO: <?= e($row['ref_provider_po']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-[10px] uppercase font-black tracking-widest text-slate-400 mb-1"><?= e($row['courier_name']) ?></span>
                            <button onclick='trackResi("<?= e($row['tracking_number']) ?>", "<?= e($row['courier_name']) ?>")' class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[11px] font-mono font-bold border border-slate-200">
                                <i class="ph-bold ph-crosshair"></i> <?= e($row['tracking_number'] ?: 'NO-AWB') ?>
                            </button>
                        </td>
                        <td>
                            <div class="text-[11px]"><span class="text-slate-400 font-bold uppercase">From:</span> <span class="dark:text-slate-200">LinksField WH</span></div>
                            <div class="text-[11px] mt-1"><span class="text-slate-400 font-bold uppercase">To:</span> <span class="dark:text-slate-200"><?= e($row['receiver_name']) ?></span></div>
                        </td>
                        <td class="text-right"><span class="font-black text-slate-800 dark:text-white font-mono text-xl"><?= number_format($row['qty']) ?></span></td>
                        <td class="pe-8 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <button onclick='viewDetail(<?= $rowJson ?>)' class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-blue-600 transition-all shadow-sm"><i class="ph-bold ph-eye"></i></button>
                                <button onclick='editDelivery(<?= $rowJson ?>)' class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-amber-600 transition-all shadow-sm"><i class="ph-fill ph-pencil-simple"></i></button>
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
    <div class="border-b border-slate-100 px-8 py-6 bg-slate-50/50">
        <h4 class="text-lg font-bold text-slate-800 dark:text-white">Warehouse Inbound Logs</h4>
    </div>
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-receive">
            <thead>
                <tr>
                    <th class="ps-8 w-32">Date</th>
                    <th>Supplier / Origin</th>
                    <th>Provider PO</th>
                    <th>Linked Client PO</th>
                    <th>WH PIC</th>
                    <th class="text-right w-32">Received Qty</th>
                    <th class="pe-8 text-center w-24">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach($data_receive as $row): 
                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); 
                ?>
                <tr class="table-row-hover transition-colors">
                    <td class="ps-8 font-bold text-slate-700 dark:text-slate-300 text-sm"><?= date('d M Y', strtotime($row['logistic_date'])) ?></td>
                    <td>
                        <span class="font-bold text-slate-800 dark:text-white text-sm block"><?= e($row['provider_name']) ?></span>
                        <span class="text-[10px] uppercase font-black tracking-widest text-slate-400">Batch: <?= e($row['batch_name']) ?></span>
                    </td>
                    <td><code class="text-xs font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded border border-indigo-100"><?= e($row['provider_po']) ?></code></td>
                    <td>
                        <?php if(!empty($row['linked_client_po'])): ?>
                            <code class="text-xs font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded border border-blue-100"><?= e($row['linked_client_po']) ?></code>
                        <?php else: ?>
                            <span class="text-[10px] text-slate-400 italic font-medium">Not Linked</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5"><i class="ph-fill ph-user text-emerald-500"></i> <?= e($row['pic_name']) ?></span></td>
                    <td class="text-right"><span class="font-black text-emerald-600 font-mono text-xl">+<?= number_format($row['qty']) ?></span></td>
                    <td class="pe-8 text-center">
                        <div class="flex gap-1.5">
                            <button onclick='editReceive(<?= $rowJson ?>)' class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-amber-600 transition-all shadow-sm"><i class="ph-fill ph-pencil-simple"></i></button>
                            <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" onclick="return confirm('Hapus data?')" class="h-8 w-8 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-red-600 transition-all shadow-sm"><i class="ph-fill ph-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="trackingModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-blue-500 px-7 py-5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white">
            <h5 class="text-lg font-bold flex items-center gap-3"><i class="ph-bold ph-crosshair text-2xl"></i> Live Shipment Tracking</h5>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-8 bg-slate-50 dark:bg-slate-900/50 max-h-[60vh] overflow-y-auto custom-scrollbar" id="trackingResult"></div>
        <div class="border-t border-slate-100 p-5 bg-white flex justify-end">
            <button type="button" class="btn-close-modal px-8 py-2.5 rounded-xl text-sm font-bold text-slate-500 border border-slate-200">Close Panel</button>
        </div>
    </div>
</div>

<div id="detailModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-slate-100 px-7 py-5 bg-white dark:bg-[#24303F]">
            <h5 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2"><i class="ph-fill ph-ticket text-blue-500 text-2xl"></i> Delivery Summary</h5>
            <button type="button" class="btn-close-modal text-slate-400 hover:text-slate-700 transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-6 bg-slate-50/50 dark:bg-slate-900/30 text-slate-800 dark:text-white" id="detailContent"></div>
    </div>
</div>

<div id="modalDelivery" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <form action="process_sim_tracking.php" method="POST" class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[95vh] border border-slate-200 modal-animate-in">
        <input type="hidden" name="action" id="del_action" value="create_logistic">
        <input type="hidden" name="type" value="delivery"><input type="hidden" name="id" id="del_id">
        <div class="flex items-center justify-between border-b border-blue-500 px-7 py-5 bg-blue-600 text-white"><h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-paper-plane-right text-xl"></i> Outbound Form</h5><button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-xl"></i></button></div>
        <div class="overflow-y-auto p-8 bg-slate-50/50 dark:bg-slate-900/50 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-slate-800 dark:text-white">
                <div class="space-y-4">
                    <h6 class="text-[11px] font-black text-blue-500 uppercase tracking-widest border-b pb-2">Target</h6>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Send Date</label><input type="date" name="logistic_date" id="del_date" required class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm outline-none focus:border-blue-500"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Client PO</label><select name="po_id" id="del_po_id" required class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-bold outline-none focus:border-blue-500"><option value="">-- Select --</option><?php foreach($client_pos as $po) echo "<option value='{$po['id']}' class='dark:bg-slate-800'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Receiver Name</label><input type="text" name="pic_name" id="del_pic" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm"></div>
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Phone</label><input type="text" name="pic_phone" id="del_phone" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm"></div>
                    </div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Address</label><textarea name="delivery_address" id="del_address" rows="3" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm resize-none"></textarea></div>
                </div>
                <div class="space-y-4">
                    <h6 class="text-[11px] font-black text-indigo-500 uppercase tracking-widest border-b pb-2">Logistics</h6>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Courier</label><input type="text" name="courier" id="del_courier" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm"></div>
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">AWB</label><input type="text" name="awb" id="del_awb" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-mono"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Qty Sent</label><input type="number" name="qty" id="del_qty" required class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm"></div>
                        <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Status</label><select name="status" id="del_status" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 px-4 text-sm font-bold"><option value="Process">Process</option><option value="Shipped">Shipped</option><option value="Delivered">Delivered</option></select></div>
                    </div>
                    <div class="mt-4 p-4 bg-indigo-50/50 dark:bg-indigo-900/20 border border-indigo-100 rounded-2xl">
                        <label class="block text-[10px] font-black text-indigo-600 uppercase mb-3">POD</label>
                        <div class="grid grid-cols-2 gap-4">
                            <input type="date" name="received_date" id="del_recv_date" class="w-full rounded-xl border border-indigo-200 py-2 px-3 text-xs">
                            <input type="text" name="receiver_name" id="del_recv_name" placeholder="Accepted By" class="w-full rounded-xl border border-indigo-200 py-2 px-3 text-xs">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="border-t border-slate-100 px-7 py-5 bg-white flex justify-end gap-3 rounded-b-3xl">
            <button type="button" class="btn-close-modal px-6 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition-all">Cancel</button>
            <button type="submit" class="rounded-xl bg-blue-600 px-8 py-2.5 text-sm font-bold text-white shadow-md active:scale-95">Save Outbound</button>
        </div>
    </form>
</div>

<div id="modalReceive" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 modal-animate-in">
        <form action="process_sim_tracking.php" method="POST">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive"><input type="hidden" name="id" id="recv_id">
            <div class="flex items-center justify-between border-b border-emerald-500 px-6 py-4 bg-emerald-600 text-white"><h5 class="text-base font-bold flex items-center gap-2"><i class="ph-bold ph-download-simple text-xl"></i> Internal WH Receive</h5><button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 transition-colors"><i class="ph ph-x text-lg"></i></button></div>
            <div class="p-6 bg-slate-50/50 text-slate-800 dark:text-white">
                <div class="mb-4"><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Receive Date</label><input type="date" name="logistic_date" id="recv_date" required class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm outline-none focus:border-emerald-500"></div>
                <div class="mb-4"><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Source Provider PO</label><select name="po_id" id="recv_po_id" required class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm font-bold outline-none focus:border-emerald-500 shadow-sm"><option value="">-- Select --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}' class='dark:bg-slate-800'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">WH PIC</label><input type="text" name="pic_name" id="recv_pic" placeholder="Internal Name" class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Total Qty</label><input type="number" name="qty" id="recv_qty" required class="w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm font-black text-emerald-600"></div>
                </div>
            </div>
            <div class="border-t border-slate-100 px-6 py-4 bg-white flex justify-end gap-2 shrink-0 rounded-b-3xl"><button type="button" class="btn-close-modal px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-xl">Cancel</button><button type="submit" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 shadow-md active:scale-95">Save Inbound</button></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    function switchTab(tabId) {
        if(tabId === 'delivery') {
            $('#tab-btn-delivery').removeClass('border-transparent text-slate-500 dark:text-slate-400').addClass('border-blue-600 text-blue-600 dark:text-blue-400');
            $('#tab-btn-receive').removeClass('border-blue-600 text-blue-600 dark:text-blue-400').addClass('border-transparent text-slate-500 dark:text-slate-400');
            $('#tab-content-delivery').show(); $('#tab-content-receive').hide();
        } else {
            $('#tab-btn-receive').removeClass('border-transparent text-slate-500 dark:text-slate-400').addClass('border-blue-600 text-blue-600 dark:text-blue-400');
            $('#tab-btn-delivery').removeClass('border-blue-600 text-blue-600 dark:text-blue-400').addClass('border-transparent text-slate-500 dark:text-slate-400');
            $('#tab-content-receive').show(); $('#tab-content-delivery').hide();
        }
    }

    $(document).ready(function() {
        $('#table-delivery').DataTable({ dom: 't<"dataTables_wrapper"p>', pageLength: 50, searching: false, ordering: false });
        $('#table-receive').DataTable({ dom: 't<"dataTables_wrapper"p>', pageLength: 50, searching: false, ordering: false });
        $('.btn-close-modal').click(function() { $(this).closest('.modal-container').hide(); $('body').css('overflow', 'auto'); });
    });

    function trackResi(resi, kurir) {
        if(!resi || !kurir) { alert('No tracking data available.'); return; }
        $('#trackingModal').show().addClass('flex'); $('body').css('overflow', 'hidden');
        $('#trackingResult').html('<div class="text-center py-10"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div><p class="mt-4">Connecting to Courier...</p></div>');
        fetch(`ajax_track_delivery.php?resi=${resi}&kurir=${kurir}`).then(r => r.text()).then(html => { $('#trackingResult').html(html); }).catch(e => { $('#trackingResult').html('Error'); });
    }

    function viewDetail(d) {
        let html = `
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-sm text-sm p-4">
                <h4 class="font-black text-xl mb-4 text-slate-800 dark:text-white">${d.tracking_number}</h4>
                <div class="space-y-3 text-slate-600 dark:text-slate-400 font-medium">
                    <p><span class="text-xs font-black uppercase text-slate-400 block mb-0.5">Project</span> <span class="text-slate-800 dark:text-white font-bold">${d.client_name}</span></p>
                    <p><span class="text-xs font-black uppercase text-slate-400 block mb-0.5">PO Reference</span> <span class="text-slate-800 dark:text-white font-bold">${d.client_po}</span></p>
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700">
                        <span class="text-xs font-black uppercase text-slate-400 block mb-0.5">Delivery Address</span>
                        <p class="text-slate-700 dark:text-slate-300 leading-relaxed">${d.receiver_address || 'No address provided.'}</p>
                    </div>
                </div>
            </div>`;
        $('#detailContent').html(html);
        $('#detailModal').show().addClass('flex'); $('body').css('overflow', 'hidden');
    }

    function openReceiveModal() { $('#recv_action').val('create_logistic'); $('#recv_id').val(''); $('#recv_date').val(new Date().toISOString().split('T')[0]); $('#modalReceive').show().addClass('flex'); $('body').css('overflow', 'hidden'); }
    function editReceive(d) { $('#recv_action').val('update_logistic'); $('#recv_id').val(d.id); $('#recv_date').val(d.logistic_date); $('#recv_pic').val(d.pic_name); $('#recv_po_id').val(d.po_id); $('#recv_qty').val(d.qty); $('#modalReceive').show().addClass('flex'); $('body').css('overflow', 'hidden'); }
    function openDeliveryModal() { $('#del_action').val('create_logistic'); $('#del_id').val(''); $('#del_date').val(new Date().toISOString().split('T')[0]); $('#modalDelivery').show().addClass('flex'); $('body').css('overflow', 'hidden'); }
    function editDelivery(d) { $('#del_action').val('update_logistic'); $('#del_id').val(d.id); $('#del_date').val(d.delivery_date); $('#del_po_id').val(d.po_id); $('#del_pic').val(d.receiver_name); $('#del_phone').val(d.receiver_phone); $('#del_address').val(d.receiver_address); $('#del_courier').val(d.courier_name); $('#del_awb').val(d.tracking_number); $('#del_qty').val(d.qty); $('#del_status').val(d.status); $('#del_recv_date').val(d.delivered_date); $('#del_recv_name').val(d.receiver_name); $('#modalDelivery').show().addClass('flex'); $('body').css('overflow', 'hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>