<?php
// =========================================================================
// FILE: sim_tracking_status.php
// DESC: SIM Lifecycle Dashboard & Management (SaaS Table, Splitted Columns)
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);
$current_page = 'sim_tracking_status.php';

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; 

$db = db_connect();
function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 1. FETCH DATA DASHBOARD ---
$list_providers_new = [];
$dashboard_data = [];
$activations_raw = [];
$terminations_raw = [];
$total_sys_sims = 0; $total_sys_act = 0; $total_sys_term = 0;

if($db) {
    try { 
        $list_providers_new = $db->query("SELECT id, po_number, sim_qty FROM sim_tracking_po WHERE type='provider' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); 
    } catch(Exception $e){}
    
    // Data Dashboard Utama
    $sql_main = "SELECT 
                    po.id as po_id, po.po_number as provider_po, po.batch_name as batch_name, po.sim_qty as total_pool,
                    client_po.po_number as client_po, c.company_name, p.project_name,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Available') as cnt_avail,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Active') as cnt_active,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id AND status = 'Terminated') as cnt_term,
                    (SELECT COUNT(*) FROM sim_inventory WHERE po_provider_id = po.id) as total_uploaded
                FROM sim_tracking_po po
                LEFT JOIN sim_tracking_po client_po ON po.link_client_po_id = client_po.id
                LEFT JOIN companies c ON po.company_id = c.id
                LEFT JOIN projects p ON po.project_id = p.id
                WHERE po.type = 'provider' 
                HAVING total_uploaded > 0 
                ORDER BY po.id DESC";
    try { 
        $dashboard_data = $db->query($sql_main)->fetchAll(PDO::FETCH_ASSOC); 
        foreach($dashboard_data as $d) {
            $total_sys_sims += $d['cnt_avail']; 
            $total_sys_act += $d['cnt_active'];
            $total_sys_term += $d['cnt_term'];
        }
    } catch(Exception $e){}

    // Data Grafik
    try {
        $activations_raw = $db->query("SELECT * FROM sim_activations ORDER BY activation_date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $terminations_raw = $db->query("SELECT * FROM sim_terminations ORDER BY termination_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Proses Data Grafik
$cd_a=[]; $cd_t=[]; $lbls=[]; $s_a=[]; $s_t=[];
foreach($activations_raw as $r){ $d=date('Y-m-d', strtotime($r['activation_date'])); if(!isset($cd_a[$d]))$cd_a[$d]=0; $cd_a[$d]+=$r['active_qty']; }
foreach($terminations_raw as $r){ $d=date('Y-m-d', strtotime($r['termination_date'])); if(!isset($cd_t[$d]))$cd_t[$d]=0; $cd_t[$d]+=$r['terminated_qty']; }
$dates = array_unique(array_merge(array_keys($cd_a), array_keys($cd_t))); sort($dates);
foreach($dates as $d){ $lbls[]=date('d M', strtotime($d)); $s_a[]=$cd_a[$d]??0; $s_t[]=$cd_t[$d]??0; }
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    @keyframes growWidth { from { width: 0; } }
    .animate-grow { animation: growWidth 1s ease-out forwards; }
    
    /* Table Adjustments (Tighter spacing & Column unbundling) */
    .table-modern { width: 100%; border-collapse: collapse; }
    .table-modern thead th { 
        background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; 
        text-transform: uppercase; letter-spacing: 0.05em; padding: 1rem; border-bottom: 1px solid #e2e8f0; 
    }
    .table-modern tbody td { padding: 1.25rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.6); }

    /* Custom Scrollbar for Modal content */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }

    /* Progress Bar Segments */
    .bar-seg { height: 100%; }
    .bg-a { background-color: #10b981; } .bg-t { background-color: #ef4444; } .bg-v { background-color: #94a3b8; }
    .dark .bg-v { background-color: #475569; }

    /* TOAST */
    #toastCont { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
    .toast-item { min-width: 300px; padding: 16px; border-radius: 12px; background: #fff; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-left: 4px solid; display: flex; gap: 12px; align-items: center; animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    .dark .toast-item { background: #1e293b; color: #fff; }
    .toast-success { border-color: #10b981; } .toast-error { border-color: #ef4444; }
    @keyframes slideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
</style>

<div id="toastCont"></div>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">
            SIM Lifecycle Dashboard
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-sim-card text-lg text-indigo-500"></i> Full inventory and activation management.
        </p>
    </div>
    <div class="animate-fade-in-up flex gap-3" style="animation-delay: 0.1s;">
        <button onclick="openUploadModal()" class="flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95 transition-all">
            <i class="ph-bold ph-cloud-arrow-up text-lg"></i> Upload Master Batch
        </button>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8 animate-fade-in-up" style="animation-delay: 0.1s;">
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden">
        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-slate-100 dark:bg-slate-700 opacity-50 blur-2xl pointer-events-none"></div>
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 shadow-sm border border-slate-200 dark:border-slate-700 z-10">
            <i class="ph-fill ph-sim-card text-3xl"></i>
        </div>
        <div class="z-10">
            <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Total Available</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white"><?php echo number_format($total_sys_sims); ?></h3>
        </div>
    </div>
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden">
        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-emerald-50 dark:bg-emerald-500/5 opacity-50 blur-2xl pointer-events-none"></div>
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shadow-sm border border-emerald-100 dark:border-emerald-500/20 z-10">
            <i class="ph-fill ph-check-circle text-3xl"></i>
        </div>
        <div class="z-10">
            <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Active SIMs</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white"><?php echo number_format($total_sys_act); ?></h3>
        </div>
    </div>
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 flex items-center gap-5 hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden">
        <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-red-50 dark:bg-red-500/5 opacity-50 blur-2xl pointer-events-none"></div>
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 shadow-sm border border-red-100 dark:border-red-500/20 z-10">
            <i class="ph-fill ph-x-circle text-3xl"></i>
        </div>
        <div class="z-10">
            <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-1">Terminated</p>
            <h3 class="text-3xl font-black text-slate-800 dark:text-white"><?php echo number_format($total_sys_term); ?></h3>
        </div>
    </div>
</div>

<div class="mb-8 rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up overflow-hidden relative" style="animation-delay: 0.2s;">
    <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
    <div class="p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"><i class="ph-fill ph-trend-up text-xl"></i></div>
            <div>
                <h6 class="text-lg font-bold text-slate-800 dark:text-white">Activity Trends</h6>
                <p class="text-xs text-slate-500 dark:text-slate-400">Activations vs Terminations over time</p>
            </div>
        </div>
        <div id="lifecycleChart" style="height: 280px;"></div>
    </div>
</div>

<div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden mb-10" style="animation-delay: 0.3s;">
    <div class="border-b border-slate-100 dark:border-slate-800 px-6 py-5 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shadow-sm"><i class="ph-fill ph-database text-xl"></i></div>
            <div><h4 class="text-lg font-bold text-slate-800 dark:text-white">Inventory Management</h4><p class="text-xs text-slate-500">Manage pool statuses per provider inbound batch.</p></div>
        </div>
    </div>

    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern" id="table-inventory">
            <thead>
                <tr>
                    <th class="ps-6 w-1/5">Client Entity</th>
                    <th class="w-1/6">Project Name</th>
                    <th class="w-1/6">Source PO / Batch</th>
                    <th class="w-[28%]">Inventory Status Pool</th>
                    <th class="text-center pe-6 w-[18%]">Management Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if(empty($dashboard_data)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400"><div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-3"><i class="ph-fill ph-folder-dashed text-3xl opacity-50"></i></div><p class="font-medium">No inventory data uploaded yet.</p></td></tr>
                <?php else: ?>
                    <?php foreach($dashboard_data as $index => $row): 
                        $tot = (int)$row['total_uploaded']; $act = (int)$row['cnt_active']; $term = (int)$row['cnt_term']; $avail = (int)$row['cnt_avail'];
                        $pA = ($tot>0)?($act/$tot)*100:0; $pT = ($tot>0)?($term/$tot)*100:0; $pV = 100-$pA-$pT;
                        
                        $stats = ['total'=>$tot, 'active'=>$act, 'terminated'=>$term, 'available'=>$avail]; 
                        $json = htmlspecialchars(json_encode(['id'=>$row['po_id'], 'po'=>$row['provider_po'], 'batch'=>$row['batch_name'], 'comp'=>$row['company_name'], 'stats'=>$stats]), ENT_QUOTES);
                        $animDelay = min($index * 0.05, 0.5);
                    ?>
                    <tr class="table-row-hover transition-colors animate-fade-in-up opacity-0 group" style="animation-delay: <?= $animDelay ?>s;">
                        <td class="ps-6">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500 border border-indigo-100 dark:border-indigo-500/20 shrink-0"><i class="ph-fill ph-buildings"></i></div>
                                <span class="font-bold text-slate-800 dark:text-white text-sm line-clamp-2"><?= e($row['company_name']) ?></span>
                            </div>
                        </td>
                        
                        <td>
                            <div class="font-bold text-slate-700 dark:text-slate-300 text-sm flex items-center gap-1.5"><i class="ph-fill ph-folder-open text-amber-500"></i> <span class="line-clamp-2"><?= e($row['project_name']) ?></span></div>
                        </td>

                        <td>
                            <div class="flex flex-col gap-1.5">
                                <span class="font-bold text-slate-800 dark:text-white text-xs font-mono bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded w-max border border-slate-200 dark:border-slate-700"><?= e($row['provider_po']) ?></span>
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Batch: <?= e($row['batch_name']) ?></span>
                            </div>
                        </td>

                        <td class="pe-4">
                            <div class="flex justify-between items-end mb-1.5">
                                <span class="text-[11px] font-black text-slate-700 dark:text-slate-300">AVAIL: <?= number_format($avail) ?></span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total: <?= number_format($tot) ?></span>
                            </div>
                            <div class="progress-track bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden flex w-full">
                                <div class="bar-seg bg-a animate-grow" style="width:<?=$pA?>%" title="Active: <?=$pA?>%"></div>
                                <div class="bar-seg bg-t animate-grow" style="width:<?=$pT?>%" title="Terminated: <?=$pT?>%"></div>
                                <div class="bar-seg bg-v animate-grow" style="width:<?=$pV?>%" title="Available: <?=$pV?>%"></div>
                            </div>
                            <div class="flex gap-3 mt-2 text-[9px] font-black uppercase tracking-widest">
                                <span class="text-emerald-500 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Act: <?= number_format($act) ?></span>
                                <span class="text-red-500 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Trm: <?= number_format($term) ?></span>
                            </div>
                        </td>

                        <td class="pe-6 text-center">
                            <div class="flex flex-col gap-1.5 w-full max-w-[140px] mx-auto">
                                <button onclick='openMgr(<?=$json?>,"activate")' class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg border border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-colors text-xs font-bold group/btn">
                                    <span class="flex items-center gap-1.5"><i class="ph-bold ph-play"></i> Activate</span>
                                </button>
                                <button onclick='openMgr(<?=$json?>,"terminate")' class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors text-xs font-bold group/btn">
                                    <span class="flex items-center gap-1.5"><i class="ph-bold ph-stop"></i> Terminate</span>
                                </button>
                                <button onclick='openHistory(<?=$json?>)' class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-xs font-bold group/btn">
                                    <span class="flex items-center gap-1.5"><i class="ph-bold ph-clock-counter-clockwise"></i> History</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalUpload" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-7 py-5 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white">
            <div class="flex items-center gap-3"><i class="ph-bold ph-cloud-arrow-up text-2xl"></i><h5 class="text-lg font-bold">Upload Master Batch</h5></div>
            <button type="button" class="btn-close-modal text-white opacity-70 hover:opacity-100 transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <div class="p-8 bg-slate-50 dark:bg-slate-900/50">
            <form id="formUploadMaster">
                <input type="hidden" name="action" value="upload_master_bulk"><input type="hidden" name="is_ajax" value="1">
                
                <div class="mb-4">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Select Provider PO</label>
                    <select name="po_provider_id" id="poSelect" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium outline-none focus:border-indigo-500 dark:text-white shadow-sm transition-all cursor-pointer" required onchange="fetchBatchInfo(this.value)">
                        <option value="">-- Choose PO --</option>
                        <?php foreach($list_providers_new as $p): ?>
                            <option value="<?=$p['id']?>"><?=$p['po_number']?> (Alloc: <?=number_format($p['sim_qty'])?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-5">
                    <div class="relative border-2 border-dashed border-indigo-200 dark:border-indigo-500/30 rounded-2xl bg-indigo-50/50 dark:bg-indigo-900/10 text-center p-8 transition-colors hover:border-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer" id="dropZone">
                        <input type="file" name="upload_file" id="fIn" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="$('#fTxt').text(this.files[0].name).addClass('text-indigo-600 dark:text-indigo-400')">
                        <div class="flex flex-col items-center justify-center pointer-events-none">
                            <i class="ph-fill ph-file-csv text-5xl text-indigo-300 dark:text-indigo-500/50 mb-3"></i>
                            <div id="fTxt" class="text-sm font-bold text-slate-600 dark:text-slate-300">Click or Drag CSV/Excel here</div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mt-2 bg-white dark:bg-slate-800 px-2 py-1 rounded shadow-sm">Header Req: MSISDN</div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-2">
                    <div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Batch Name</label><input type="text" name="activation_batch" id="batchInput" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 px-4 py-2.5 text-sm font-bold text-slate-500 outline-none shadow-sm cursor-not-allowed" placeholder="Auto-filled" readonly required></div>
                    <div><label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Upload Date</label><input type="date" name="date_field" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium outline-none focus:border-indigo-500 shadow-sm dark:text-white" value="<?=date('Y-m-d')?>" required></div>
                </div>

                <div class="mt-4 hidden" id="progCont">
                    <div class="flex justify-between items-end mb-1.5"><span class="text-xs font-bold text-indigo-600 dark:text-indigo-400" id="progText">Uploading data...</span><span class="text-xs font-black font-mono text-slate-600 dark:text-slate-300" id="progPct">0%</span></div>
                    <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden"><div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" id="progBar" style="width: 0%"></div></div>
                </div>
                
                <button type="submit" class="mt-6 w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition-all hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95 flex items-center justify-center gap-2" id="btnUp"><i class="ph-bold ph-upload-simple"></i> Start Upload Process</button>
            </form>
        </div>
    </div>
</div>

<div id="modalHistory" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-5xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col h-[85vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-[#24303F] shrink-0">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl border border-indigo-100 dark:border-indigo-500/20"><i class="ph-fill ph-clock-counter-clockwise"></i></div>
                <div><h5 class="text-xl font-black text-slate-800 dark:text-white leading-tight" id="histTitle">Activity History</h5><p class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase mt-1" id="histSubtitle">-</p></div>
            </div>
            <button type="button" class="btn-close-modal h-10 w-10 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        
        <div class="flex-1 flex overflow-hidden">
            <div class="w-1/3 border-r border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 flex flex-col">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-800/50 shrink-0"><span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Transaction Logs</span></div>
                <div id="histSummaryList" class="overflow-y-auto custom-scrollbar flex-1 p-2"></div>
            </div>
            <div class="w-2/3 bg-white dark:bg-[#24303F] flex flex-col">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 shrink-0"><span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Detailed Records</span></div>
                <div id="histDetailView" class="overflow-y-auto custom-scrollbar flex-1 p-6 relative">
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 opacity-50"><i class="ph-fill ph-hand-pointing text-6xl mb-4"></i><span class="font-bold text-sm">Select a log from the left panel.</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalMgr" class="modal-container fixed inset-0 z-[105] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-6xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col h-[90vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-[#24303F] shrink-0">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl border border-blue-100 dark:border-blue-500/20"><i class="ph-fill ph-sim-card"></i></div>
                <div><h5 class="text-xl font-black text-slate-800 dark:text-white leading-tight" id="mgrTitle">Manage SIMs</h5><p class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase mt-1" id="mgrSubtitle">-</p></div>
            </div>
            <button type="button" class="btn-close-modal h-10 w-10 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        
        <div class="grid grid-cols-3 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-[#24303F] shrink-0">
            <div class="p-4 text-center border-r border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors relative group/tab" id="btnFilterTotal" onclick="switchFilter('all')">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 transition-colors">Total Available</p>
                <h4 class="text-2xl font-black text-slate-800 dark:text-white" id="stTotal">-</h4>
                <div class="absolute bottom-0 left-0 w-full h-1 bg-blue-500 scale-x-0 group-[.active]/tab:scale-x-100 transition-transform origin-left"></div>
            </div>
            <div class="p-4 text-center border-r border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors relative group/tab" id="btnFilterActive" onclick="switchFilter('terminate')">
                <p class="text-[10px] font-black uppercase tracking-widest text-emerald-500 mb-1 transition-colors">Active Status</p>
                <h4 class="text-2xl font-black text-emerald-600 dark:text-emerald-400" id="stActive">-</h4>
                <div class="absolute bottom-0 left-0 w-full h-1 bg-emerald-500 scale-x-0 group-[.active]/tab:scale-x-100 transition-transform origin-left"></div>
            </div>
            <div class="p-4 text-center cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors relative group/tab" id="btnFilterTerm" onclick="switchFilter('view_terminated')">
                <p class="text-[10px] font-black uppercase tracking-widest text-red-500 mb-1 transition-colors">Terminated Status</p>
                <h4 class="text-2xl font-black text-red-600 dark:text-red-400" id="stTerm">-</h4>
                <div class="absolute bottom-0 left-0 w-full h-1 bg-red-500 scale-x-0 group-[.active]/tab:scale-x-100 transition-transform origin-left"></div>
            </div>
        </div>

        <div class="p-5 bg-slate-50/50 dark:bg-slate-900/30 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <div class="flex flex-col gap-3">
                <div class="flex gap-3">
                    <textarea id="sKey" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3 text-sm outline-none focus:border-blue-500 shadow-sm resize-none custom-scrollbar dark:text-white" rows="1" placeholder="Paste multiple MSISDNs / ICCIDs separated by space or enter..."></textarea>
                    <button onclick="doSearch()" class="rounded-xl bg-slate-800 dark:bg-slate-700 px-6 py-3 text-sm font-bold text-white transition-all hover:bg-slate-900 dark:hover:bg-slate-600 shadow-sm whitespace-nowrap flex items-center gap-2"><i class="ph-bold ph-magnifying-glass"></i> Search</button>
                </div>
                <div class="flex justify-between items-center px-1">
                    <span class="text-xs font-bold text-slate-500 flex items-center gap-1.5"><i class="ph-fill ph-info text-blue-500"></i> <span id="hintText">Filtering data...</span></span>
                    <label class="flex items-center gap-2 cursor-pointer bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded-lg shadow-sm">
                        <input type="checkbox" id="checkAll" onchange="toggleAll(this)" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 cursor-pointer">
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Select All Displayed</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar bg-slate-50/30 dark:bg-[#24303F] p-4 relative" id="sList"></div>
        
        <div class="border-t border-slate-200 dark:border-slate-700 px-6 py-4 bg-white dark:bg-slate-800 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 p-1 shadow-sm">
                <button onclick="changePage(-1)" id="btnPrev" class="px-3 py-1.5 rounded-lg text-sm font-bold text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all disabled:opacity-30"><i class="ph-bold ph-caret-left"></i></button>
                <span class="text-xs font-black uppercase tracking-widest text-slate-500 px-3" id="pageInfo">Page 1</span>
                <button onclick="changePage(1)" id="btnNext" class="px-3 py-1.5 rounded-lg text-sm font-bold text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:shadow-sm transition-all disabled:opacity-30"><i class="ph-bold ph-caret-right"></i></button>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block leading-none">Selected</span>
                    <span class="text-xl font-black text-blue-600 dark:text-blue-400 leading-none" id="selCount">0</span>
                </div>
                <div class="h-8 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>
                <input type="date" id="actDate" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-bold outline-none focus:border-blue-500 shadow-sm dark:text-white" value="<?=date('Y-m-d')?>">
                <button id="btnProc" disabled onclick="doProc()" class="rounded-xl bg-slate-200 dark:bg-slate-700 px-8 py-2.5 text-sm font-bold text-slate-500 transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">Select Items</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>
    // TOAST NOTIF
    function toast(t,m){ let c=t==='success'?'toast-success':'toast-error'; let i=t==='success'?'ph-fill ph-check-circle text-emerald-500':'ph-fill ph-warning-circle text-red-500'; let h=`<div class="toast-item ${c}"><i class="${i} text-3xl"></i><div><div class="font-black uppercase tracking-widest text-[10px] text-slate-400 mb-0.5">${t}</div><div class="text-sm font-bold text-slate-700 dark:text-white">${m}</div></div></div>`; $('#toastCont').append(h); setTimeout(()=>$('#toastCont').children().first().remove(),4000); }

    // MODAL TOGGLES
    $('.btn-close-modal').click(function() { $(this).closest('.modal-container').removeClass('flex').addClass('hidden'); $('body').css('overflow', 'auto'); });
    $('.modal-container').click(function(e) { if(e.target === this) { $(this).removeClass('flex').addClass('hidden'); $('body').css('overflow', 'auto'); } });

    // UPLOAD LOGIC
    function openUploadModal() { $('#formUploadMaster')[0].reset(); $('#progCont').addClass('hidden'); $('#btnUp').prop('disabled',false).html('<i class="ph-bold ph-upload-simple"></i> Start Upload Process'); $('#batchInput').val(''); $('body').css('overflow', 'hidden'); $('#modalUpload').removeClass('hidden').addClass('flex'); }
    function fetchBatchInfo(id) { if(!id){$('#batchInput').val('');return;} $.post('process_sim_tracking.php', {action:'get_po_details', id:id}, function(res){ if(res.status==='success'){$('#batchInput').val(res.batch_name||'BATCH 1');} else{toast('error',res.message);$('#batchInput').val('');} },'json'); }
    $('#formUploadMaster').on('submit', function(e){ e.preventDefault(); let fd=new FormData(this); if($('#batchInput').val()===''){toast('error','Batch Name Missing');return;} $('#btnUp').prop('disabled',true); $('#progCont').removeClass('hidden'); $.ajax({xhr:function(){var x=new window.XMLHttpRequest();x.upload.addEventListener("progress",e=>{if(e.lengthComputable){var p=Math.round((e.loaded/e.total)*100);$('#progBar').css('width',p+'%');$('#progPct').text(p+'%');}},false);return x;},type:'POST',url:'process_sim_tracking.php',data:fd,contentType:false,processData:false,dataType:'json',success:function(r){if(r.status==='success'){$('#progBar').addClass('bg-emerald-500').removeClass('bg-indigo-600');toast('success',r.message);setTimeout(()=>location.reload(),1500);}else{$('#progBar').addClass('bg-red-500').removeClass('bg-indigo-600');toast('error',r.message);$('#btnUp').prop('disabled',false).html('<i class="ph-bold ph-arrow-counter-clockwise"></i> Retry Upload');}},error:function(x){toast('error',x.responseText);$('#btnUp').prop('disabled',false).html('<i class="ph-bold ph-arrow-counter-clockwise"></i> Retry Upload');}}); });

    // MANAGER LOGIC
    let cId=0, cMode='', cBatch='', cPage=1, totalPages=1, cSearch='';
    
    function openMgr(d, m) { 
        cId=d.id; cBatch=d.batch; cPage=1; cSearch='';
        $('#mgrTitle').text('Manage SIMs'); 
        $('#mgrSubtitle').text(`${d.comp} - PO: ${d.po}`); 
        $('#sKey').val(''); 
        switchFilter(m);
        $('body').css('overflow', 'hidden'); $('#modalMgr').removeClass('hidden').addClass('flex');
    }

    function switchFilter(mode) {
        cMode = mode; cPage = 1;
        $('#btnFilterTotal, #btnFilterActive, #btnFilterTerm').removeClass('active');
        let hint = '';
        if(mode === 'activate') {
            $('#btnFilterTotal').addClass('active'); hint = 'Showing <b class="text-slate-800 dark:text-white">Available</b> SIMs. Ready for Activation.';
        } else if(mode === 'terminate') {
            $('#btnFilterActive').addClass('active'); hint = 'Showing <b class="text-emerald-600 dark:text-emerald-400">Active</b> SIMs. Ready for Termination.';
        } else if(mode === 'view_terminated') {
            $('#btnFilterTerm').addClass('active'); hint = 'Showing <b class="text-red-600 dark:text-red-400">Terminated</b> SIMs. Read-only mode.';
        } else {
            $('#btnFilterTotal').addClass('active'); hint = 'Showing <b>All</b> SIMs. Select to view actions.';
        }
        $('#hintText').html(hint);
        $('#btnProc').prop('disabled', true).removeClass('bg-emerald-600 bg-red-600 hover:bg-emerald-700 hover:bg-red-700 text-white').addClass('bg-slate-200 dark:bg-slate-700 text-slate-500').html('Select Items');
        loadData();
    }

    function doSearch() { cSearch = $('#sKey').val().trim(); cPage = 1; loadData(); }
    function changePage(d) { let n = cPage + d; if(n > 0 && n <= totalPages) { cPage = n; loadData(); } }

    function loadData() {
        $('#sList').html('<div class="flex flex-col justify-center items-center h-full py-20"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div><p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Loading Records...</p></div>');
        $('#selCount').text(0); $('#checkAll').prop('checked', false);
        $('#btnProc').prop('disabled', true).html('Select Items').removeClass('bg-emerald-600 bg-red-600 text-white').addClass('bg-slate-200 text-slate-500');
        
        $.post('process_sim_tracking.php', { action:'fetch_sims', po_id:cId, search_bulk:cSearch, page:cPage, target_action: cMode }, function(res){
            if(res.status==='success'){
                if(res.stats) {
                    $('#stTotal').text(parseInt(res.stats.total||0).toLocaleString()); 
                    $('#stActive').text(parseInt(res.stats.active||0).toLocaleString());
                    $('#stTerm').text(parseInt(res.stats.terminated||0).toLocaleString());
                }
                
                let h=''; 
                if(res.data.length===0) h='<div class="flex flex-col items-center justify-center h-full py-20 opacity-50"><i class="ph-fill ph-magnifying-glass text-6xl mb-4"></i><p class="font-bold text-lg">No matching records</p></div>';
                else {
                    h = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">';
                    res.data.forEach(s => { 
                        let bClass = s.status==='Active'?'bg-emerald-100 text-emerald-800 border-emerald-200':(s.status==='Terminated'?'bg-red-100 text-red-800 border-red-200':'bg-slate-100 text-slate-600 border-slate-200');
                        let bIcon = s.status==='Active'?'ph-check-circle':(s.status==='Terminated'?'ph-x-circle':'ph-sim-card');
                        let dInfo = s.activation_date ? `<div class="text-[10px] font-bold text-slate-400 mt-1 flex items-center gap-1"><i class="ph-fill ph-calendar-blank"></i> Act: ${s.activation_date}</div>` : '';
                        
                        h += `
                        <label class="sim-item flex items-center gap-3 p-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 cursor-pointer hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md transition-all group/card">
                            <input type="checkbox" class="chk w-5 h-5 text-blue-600 rounded border-slate-300 focus:ring-blue-500" value="${s.id}" data-status="${s.status}" onclick="upd()">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="font-mono font-black text-sm text-slate-800 dark:text-white truncate">${s.msisdn}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest border ${bClass}"><i class="ph-bold ${bIcon}"></i> ${s.status}</span>
                                </div>
                                <div class="text-xs font-medium text-slate-500 truncate">ICCID: ${s.iccid||'-'}</div>
                                ${dInfo}
                            </div>
                        </label>`; 
                    });
                    h += '</div>';
                }
                $('#sList').html(h);
                totalPages = res.total_pages;
                $('#pageInfo').text(`Pg ${cPage} / ${totalPages}`);
                $('#btnPrev').prop('disabled', cPage <= 1);
                $('#btnNext').prop('disabled', cPage >= totalPages);
            } else toast('error', res.message);
        },'json');
    }

    function toggleAll(el) { $('.chk').prop('checked', el.checked); upd(); }

    function upd() { 
        let chks = $('.chk:checked'); let n = chks.length; 
        $('#selCount').text(n); 
        
        $('.sim-item').removeClass('ring-2 ring-blue-500 bg-blue-50/50 dark:bg-blue-900/20');
        chks.closest('.sim-item').addClass('ring-2 ring-blue-500 bg-blue-50/50 dark:bg-blue-900/20');

        let btn = $('#btnProc');
        if(n === 0) {
            btn.prop('disabled', true).removeClass('bg-emerald-600 bg-red-600 text-white hover:bg-emerald-700 hover:bg-red-700').addClass('bg-slate-200 dark:bg-slate-700 text-slate-500').html('Select Items');
            return;
        }

        let hasAvail = false, hasActive = false, hasTerm = false;
        chks.each(function(){
            let st = $(this).data('status');
            if(st === 'Available') hasAvail = true;
            if(st === 'Active') hasActive = true;
            if(st === 'Terminated') hasTerm = true;
        });

        if (hasAvail && !hasActive && !hasTerm) {
            btn.prop('disabled', false).removeClass('bg-slate-200 dark:bg-slate-700 text-slate-500 bg-red-600 hover:bg-red-700').addClass('bg-emerald-600 text-white hover:bg-emerald-700').html('<i class="ph-bold ph-play"></i> Activate Selected');
            btn.data('action', 'activate'); 
        } else if (hasActive && !hasAvail && !hasTerm) {
            btn.prop('disabled', false).removeClass('bg-slate-200 dark:bg-slate-700 text-slate-500 bg-emerald-600 hover:bg-emerald-700').addClass('bg-red-600 text-white hover:bg-red-700').html('<i class="ph-bold ph-stop"></i> Terminate Selected');
            btn.data('action', 'terminate');
        } else {
            btn.prop('disabled', true).removeClass('bg-emerald-600 bg-red-600 text-white').addClass('bg-slate-200 dark:bg-slate-700 text-slate-500').html('<i class="ph-bold ph-warning"></i> Invalid Mix');
        }
    }

    function doProc() {
        let ids=[]; $('.chk:checked').each(function(){ids.push($(this).val())});
        let action = $('#btnProc').data('action');
        
        if(!action || ids.length === 0) return;
        if(!confirm(`Confirm ${action.toUpperCase()} for ${ids.length} items?`)) return;
        
        $('#btnProc').prop('disabled',true).html('<i class="ph-bold ph-spinner animate-spin"></i> Processing...');
        $.post('process_sim_tracking.php', {
            action:'process_bulk_sim_action', po_provider_id:cId, mode: action, sim_ids:ids, date_field:$('#actDate').val(), batch_name:cBatch
        }, function(r){
            if(r.status==='success'){ toast('success', r.message); setTimeout(()=>location.reload(),1500); } 
            else { toast('error', r.message); upd(); }
        },'json');
    }

    // HISTORY LOGIC
    let hPoId = 0;
    function openHistory(d) {
        hPoId = d.id;
        $('#histTitle').text("History: " + d.po);
        $('#histSubtitle').text(d.comp + " | " + d.batch);
        $('#histSummaryList').html('<div class="flex justify-center py-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>');
        $('#histDetailView').html('<div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 opacity-50"><i class="ph-fill ph-hand-pointing text-6xl mb-4"></i><span class="font-bold text-sm">Select a log from the left panel.</span></div>');
        $('body').css('overflow', 'hidden'); $('#modalHistory').removeClass('hidden').addClass('flex');

        $.post('process_sim_tracking.php', {action:'fetch_history_summary', po_id:hPoId}, function(res){
            if(res.status === 'success') {
                if(res.data.length === 0) {
                    $('#histSummaryList').html('<div class="text-center py-10 text-slate-400 font-bold text-sm">No history found.</div>');
                } else {
                    let html = '<div class="flex flex-col gap-2">';
                    res.data.forEach(function(item) {
                        let isAct = (item.type === 'Activation');
                        let bCol = isAct ? 'text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-500/10 dark:border-emerald-500/20' : 'text-red-600 bg-red-50 border-red-100 dark:bg-red-500/10 dark:border-red-500/20';
                        let iCol = isAct ? 'ph-play text-emerald-500' : 'ph-stop text-red-500';
                        
                        html += `
                        <div class="history-item p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 cursor-pointer hover:border-indigo-400 hover:shadow-md transition-all group/hist" onclick="loadHistoryDetails('${item.date}', '${item.type}', this)">
                            <div class="flex justify-between items-center mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border ${bCol}"><i class="ph-bold ${iCol}"></i> ${item.type}</span>
                                <span class="text-[11px] font-bold text-slate-400"><i class="ph-fill ph-calendar-blank"></i> ${item.date}</span>
                            </div>
                            <div class="flex justify-between items-end mt-3">
                                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">${item.batch}</span>
                                <div class="text-right">
                                    <span class="text-xl font-black text-slate-800 dark:text-white leading-none">${parseInt(item.qty).toLocaleString()}</span>
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-0.5">SIMS</span>
                                </div>
                            </div>
                        </div>`;
                    });
                    $('#histSummaryList').html(html + '</div>');
                }
            } else $('#histSummaryList').html('<div class="text-center py-10 text-red-500 font-bold text-sm">Error loading history.</div>');
        }, 'json');
    }

    function loadHistoryDetails(date, type, el) {
        $('.history-item').removeClass('ring-2 ring-indigo-500 shadow-md');
        $(el).addClass('ring-2 ring-indigo-500 shadow-md');
        $('#histDetailView').html('<div class="absolute inset-0 flex justify-center items-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div></div>');

        $.post('process_sim_tracking.php', {action:'fetch_history_details', po_id:hPoId, date:date, type:type}, function(res){
            if(res.status === 'success') {
                if(res.data.length === 0) {
                    $('#histDetailView').html('<div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400"><p class="font-bold">No active details found.</p></div>');
                } else {
                    let isAct = (type === 'Activation');
                    let txtCol = isAct ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
                    let bgCol = isAct ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200';
                    
                    let html = `
                        <div class="flex justify-between items-center mb-4 pb-4 border-b border-slate-200 dark:border-slate-700">
                            <h6 class="font-black text-lg ${txtCol}">${type} Detail Logs</h6>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Count: ${res.data.length}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 content-start">`;
                    
                    res.data.forEach(function(s) {
                        html += `
                        <div class="flex items-center justify-between p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
                            <span class="font-mono font-bold text-sm text-slate-700 dark:text-slate-300">${s.msisdn}</span>
                            <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border ${bgCol}">${s.status}</span>
                        </div>`;
                    });
                    
                    $('#histDetailView').html(html + `</div>`);
                }
            } else $('#histDetailView').html('<div class="absolute inset-0 flex justify-center items-center text-red-500 font-bold">Error loading details.</div>');
        }, 'json');
    }

    // CHART INIT
    const lbl=<?= json_encode($lbls??[]) ?>; const sa=<?= json_encode($s_a??[]) ?>; const st=<?= json_encode($s_t??[]) ?>;
    if(lbl.length > 0) {
        let isDark = document.documentElement.classList.contains('dark');
        new ApexCharts(document.querySelector("#lifecycleChart"), {
            series:[{name:'Activations',data:sa},{name:'Terminations',data:st}], 
            chart:{type:'area',height:280,toolbar:{show:false}, fontFamily: 'Inter, sans-serif'}, 
            colors:['#10b981','#ef4444'], stroke:{curve:'smooth',width:2}, 
            xaxis:{categories:lbl, labels: { style: { colors: isDark ? '#94a3b8' : '#64748b' } }}, 
            yaxis: { labels: { style: { colors: isDark ? '#94a3b8' : '#64748b' } } },
            grid:{borderColor: isDark ? '#334155' : '#f1f5f9', strokeDashArray: 4}, 
            fill:{type:'gradient', gradient:{shadeIntensity:1, opacityFrom:0.5, opacityTo:0.05, stops:[0, 90, 100]}},
            tooltip: { theme: isDark ? 'dark' : 'light' }
        }).render();
    }
    
    // FILE DRAG & DROP
    const dz=document.getElementById('dropZone'), fi=document.getElementById('fIn');
    ['dragenter','dragover'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('border-indigo-400', 'bg-indigo-50');},false));
    ['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('border-indigo-400', 'bg-indigo-50');},false));
</script>

<?php require_once 'includes/footer.php'; ?>