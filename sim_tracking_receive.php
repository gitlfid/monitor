<?php
// =========================================================================
// FILE: sim_tracking_receive.php
// DESC: Inbound Tracking (Ultra-Modern Tailwind CSS)
// FEAT: Separated from Delivery
// =========================================================================
ini_set('display_errors', 0); 
error_reporting(E_ALL);
$current_page = 'sim_tracking_receive.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 

$db = db_connect();

if (!function_exists('e')) {
    function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

// =========================================================================
// 1. DATA LOGIC (QUERY INBOUND ONLY)
// =========================================================================
$data_receive = [];
try {
    // FIX DUPLICATE: Menggunakan subquery untuk client po link
    $sql_recv = "SELECT l.*, 
            po.po_number as provider_po, 
            po.batch_name,
            COALESCE(c.company_name, po.manual_company_name) as provider_name,
            (SELECT po_number FROM sim_tracking_po WHERE id = po.link_client_po_id LIMIT 1) as linked_client_po
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

// DATA PO PROVIDER
$provider_pos = []; 
try {
    $stmt = $db->query("SELECT po.id, po.po_number, COALESCE(c.company_name, po.manual_company_name) as company_name FROM sim_tracking_po po LEFT JOIN companies c ON po.company_id = c.id WHERE po.type = 'provider' ORDER BY po.id DESC");
    if($stmt) {
        $provider_pos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    /* DATATABLES PAGINATION */
    .dataTables_wrapper .dataTables_paginate { display: flex !important; justify-content: flex-end !important; align-items: center !important; gap: 0.5rem !important; margin: 1.5rem 2rem 1.5rem 0 !important; }
    .dataTables_wrapper .dataTables_paginate span { display: flex !important; gap: 0.3rem !important; }
    .paginate_button { display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 0.5rem 1rem !important; border-radius: 0.5rem !important; border: 1px solid #e2e8f0 !important; background-color: #ffffff !important; color: #475569 !important; font-size: 0.875rem !important; font-weight: 600 !important; cursor: pointer !important; transition: all 0.2s ease !important; margin: 0 !important; }
    .paginate_button.current { background-color: #10b981 !important; border-color: #10b981 !important; color: #ffffff !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3) !important; }
    .dark .paginate_button { background-color: #1e293b !important; border-color: #334155 !important; color: #cbd5e1 !important; }
    .dark .paginate_button.current { background-color: #10b981 !important; border-color: #10b981 !important; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600 tracking-tight">
            Inbound Logs
        </h2>
        <p class="text-sm font-medium text-slate-500 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-download-simple text-lg text-emerald-500"></i> Track physical SIM cards received at internal warehouse.
        </p>
    </div>
    <div class="animate-fade-in-up flex gap-3">
        <button onclick="openReceiveModal()" class="flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700 shadow-lg shadow-emerald-500/30 active:scale-95 transition-all">
            <i class="ph-bold ph-plus text-lg"></i> Log Inbound
        </button>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden block">
    <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
    <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50">
        <h4 class="text-lg font-bold text-slate-800 dark:text-white">Warehouse Inbound Data</h4>
    </div>
    
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-receive">
            <thead>
                <tr>
                    <th class="ps-8 w-[15%]">Date</th>
                    <th class="w-[20%]">Supplier Origin</th>
                    <th class="w-[20%]">PO References</th>
                    <th class="w-[15%]">WH PIC</th>
                    <th class="text-right w-[15%]">Received Qty</th>
                    <th class="pe-8 text-center w-[15%]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach($data_receive as $row): 
                    $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); 
                ?>
                <tr class="table-row-hover transition-colors">
                    <td class="ps-8 align-top">
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-sm block"><?= date('d M Y', strtotime($row['logistic_date'])) ?></span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 mt-1.5 rounded text-[9px] font-black uppercase border bg-emerald-50 text-emerald-700 border-emerald-200">Arrived</span>
                    </td>
                    <td class="align-top">
                        <span class="font-bold text-slate-800 dark:text-white text-sm block"><?= e($row['provider_name']) ?></span>
                        <span class="text-[10px] uppercase font-black text-slate-400">Batch: <?= e($row['batch_name']) ?></span>
                    </td>
                    <td class="align-top">
                        <div class="flex flex-col gap-1.5">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 px-2 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800 w-max" title="Provider PO"><i class="ph-bold ph-truck text-indigo-500"></i> <?= e($row['provider_po']) ?></span>
                            <?php if(!empty($row['linked_client_po'])): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-lg border border-blue-100 dark:border-blue-800 w-max" title="Linked Client PO"><i class="ph-bold ph-link text-blue-500"></i> <?= e($row['linked_client_po']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="align-top"><span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1.5 mt-1"><i class="ph-fill ph-user text-emerald-500"></i> <?= e($row['pic_name']) ?></span></td>
                    <td class="text-right align-top"><span class="font-black text-emerald-600 dark:text-emerald-400 font-mono text-xl">+<?= number_format($row['qty']) ?></span></td>
                    <td class="pe-8 text-center align-top">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick='editReceive(<?= $rowJson ?>)' class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-amber-600 hover:border-amber-200 transition-all shadow-sm flex items-center justify-center"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                            <a href="process_sim_tracking.php?action=delete_logistic&id=<?= $row['id'] ?>" onclick="return confirm('Delete this inbound data?')" class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-red-600 hover:border-red-200 transition-all shadow-sm flex items-center justify-center"><i class="ph-fill ph-trash text-lg"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalReceive" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <form action="process_sim_tracking.php" method="POST">
            <input type="hidden" name="action" id="recv_action" value="create_logistic">
            <input type="hidden" name="type" value="receive"><input type="hidden" name="id" id="recv_id">
            <div class="flex items-center justify-between border-b border-emerald-500 px-6 py-5 bg-emerald-600 text-white"><h5 class="text-base font-bold flex items-center gap-2"><i class="ph-bold ph-download-simple text-xl"></i> Internal WH Receive</h5><button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 transition-colors"><i class="ph ph-x text-lg"></i></button></div>
            
            <div class="p-6 bg-slate-50/50 dark:bg-slate-900/30 text-slate-800 dark:text-white space-y-4">
                <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Receive Date *</label><input type="date" name="logistic_date" id="recv_date" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-4 text-sm outline-none focus:border-emerald-500 shadow-sm"></div>
                <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Source Provider PO *</label><select name="po_id" id="recv_po_id" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-4 text-sm font-bold outline-none focus:border-emerald-500 shadow-sm cursor-pointer"><option value="">-- Select Source PO --</option><?php foreach($provider_pos as $po) echo "<option value='{$po['id']}' class='dark:bg-slate-800'>{$po['company_name']} - {$po['po_number']}</option>"; ?></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">WH PIC</label><input type="text" name="pic_name" id="recv_pic" placeholder="Internal Name" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-4 text-sm outline-none focus:border-emerald-500 shadow-sm"></div>
                    <div><label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 tracking-widest">Total Qty *</label><input type="number" name="qty" id="recv_qty" required placeholder="0" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3 px-4 text-sm font-black text-emerald-600 outline-none focus:border-emerald-500 shadow-sm"></div>
                </div>
            </div>
            <div class="border-t border-slate-100 dark:border-slate-800 px-6 py-5 bg-white dark:bg-slate-800 flex justify-end gap-2 shrink-0 rounded-b-3xl">
                <button type="button" class="btn-close-modal px-6 py-2.5 text-sm font-bold text-slate-500 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-xl transition-all">Cancel</button>
                <button type="submit" class="rounded-xl bg-emerald-600 px-8 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 shadow-md active:scale-95 flex items-center gap-2"><i class="ph-bold ph-floppy-disk"></i> Save Inbound</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        $('#table-receive').DataTable({ 
            dom: 't<"dataTables_wrapper"p>',
            pageLength: 50, searching: false, ordering: false,
            language: { paginate: { previous: "Previous", next: "Next" } }
        });

        $('.btn-close-modal').click(function() {
            $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
            $('body').css('overflow', 'auto');
        });
    });

    function openReceiveModal() { $('#recv_action').val('create_logistic'); $('#recv_id').val(''); $('#recv_date').val(new Date().toISOString().split('T')[0]); $('#recv_pic').val(''); $('#recv_po_id').val(''); $('#recv_qty').val(''); $('body').css('overflow', 'hidden'); $('#modalReceive').removeClass('hidden').addClass('flex'); }
    function editReceive(d) { $('#recv_action').val('update_logistic'); $('#recv_id').val(d.id); $('#recv_date').val(d.logistic_date); $('#recv_pic').val(d.pic_name); $('#recv_po_id').val(d.po_id); $('#recv_qty').val(d.qty); $('body').css('overflow', 'hidden'); $('#modalReceive').removeClass('hidden').addClass('flex'); }
</script>

<?php require_once 'includes/footer.php'; ?>