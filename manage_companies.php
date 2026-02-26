<?php
// =========================================================================
// FILE: manage_companies.php
// DESC: Manage Clients & Projects (Ultra-Modern Tailwind CSS & Dynamic UI)
// =========================================================================

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 

$db = db_connect();
$message = '';
$error = '';

// Definisikan fungsi e() untuk keamanan XSS (jika belum ada di functions.php)
if (!function_exists('e')) {
    function e($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

// Helper function untuk generate alert Tailwind dari backend
function tailwindAlert($type, $msg) {
    if ($type === 'success') {
        return '<div class="relative flex items-center justify-between px-6 py-4 mb-6 text-sm font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-2xl animate-fade-in-up dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 shadow-sm"><div class="flex items-center gap-3"><i class="ph-fill ph-check-circle text-2xl"></i><span>'.$msg.'</span></div><button type="button" class="text-emerald-600 hover:text-emerald-800 dark:hover:text-emerald-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
    } else {
        return '<div class="relative flex items-center justify-between px-6 py-4 mb-6 text-sm font-bold text-red-800 bg-red-50 border border-red-200 rounded-2xl animate-fade-in-up dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20 shadow-sm"><div class="flex items-center gap-3"><i class="ph-fill ph-warning-circle text-2xl"></i><span>'.$msg.'</span></div><button type="button" class="text-red-600 hover:text-red-800 dark:hover:text-red-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
    }
}

// --- LOGIKA PENANGANAN POST (TIDAK BERUBAH) ---
try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        // Aksi: Simpan (Create/Update) PERUSAHAAN
        if (isset($_POST['save_company'])) {
            $company_name = trim($_POST['company_name']);
            $pic_name = trim($_POST['pic_name'] ?? '');
            $pic_phone = trim($_POST['pic_phone'] ?? '');
            $pic_email = trim($_POST['pic_email'] ?? '');
            $id = $_POST['id'];

            if (empty($id)) {
                $stmt = $db->prepare("INSERT INTO companies (company_name, pic_name, pic_phone, pic_email) VALUES (?, ?, ?, ?)");
                $stmt->execute([$company_name, $pic_name, $pic_phone, $pic_email]);
                $message = tailwindAlert('success', 'The new client entity has been successfully registered.');
            } else {
                $stmt = $db->prepare("UPDATE companies SET company_name = ?, pic_name = ?, pic_phone = ?, pic_email = ? WHERE id = ?");
                $stmt->execute([$company_name, $pic_name, $pic_phone, $pic_email, $id]);
                $message = tailwindAlert('success', 'The client entity data has been successfully updated.');
            }
        }

        // Aksi: Hapus PERUSAHAAN
        if (isset($_POST['delete_company'])) {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            $message = tailwindAlert('success', 'The client and all related projects have been permanently deleted.');
        }

        // Aksi: Simpan (Create/Update) PROYEK
        if (isset($_POST['save_project'])) {
            $company_id = $_POST['company_id'];
            $project_name = trim($_POST['project_name']);
            $subscription_key = trim($_POST['subscription_key']);
            $id = $_POST['id'];

            if (empty($id)) {
                $stmt = $db->prepare("INSERT INTO projects (company_id, project_name, subscription_key) VALUES (?, ?, ?)");
                $stmt->execute([$company_id, $project_name, $subscription_key]);
                $message = tailwindAlert('success', 'New project has been successfully linked to the client.');
            } else {
                $stmt = $db->prepare("UPDATE projects SET company_id = ?, project_name = ?, subscription_key = ? WHERE id = ?");
                $stmt->execute([$company_id, $project_name, $subscription_key, $id]);
                $message = tailwindAlert('success', 'The project configuration has been successfully updated.');
            }
        }

        // Aksi: Hapus PROYEK
        if (isset($_POST['delete_project'])) {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $message = tailwindAlert('success', 'Project data has been unlinked and deleted.');
        }
    }
} catch (PDOException $e) {
    $error = tailwindAlert('error', 'Operation failed due to database error: ' . htmlspecialchars($e->getMessage()));
}

// --- PENGAMBILAN DATA ---
$stmt_companies = $db->query("SELECT * FROM companies ORDER BY company_name ASC");
$companies = $stmt_companies->fetchAll(PDO::FETCH_ASSOC);

$stmt_projects = $db->query("SELECT p.*, c.company_name FROM projects p JOIN companies c ON p.company_id = c.id ORDER BY c.company_name ASC, p.project_name ASC");
$projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

$project_counts = [];
foreach ($projects as $proj) {
    $cid = $proj['company_id'];
    if (!isset($project_counts[$cid])) $project_counts[$cid] = 0;
    $project_counts[$cid]++;
}

$total_clients = count($companies);
$total_projects = count($projects);
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* Table Settings */
    .table-modern { width: 100%; border-collapse: collapse; }
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.25rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.5); }
    
    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center animate-fade-in-up">
    <div>
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-cyan-500 dark:from-indigo-400 dark:to-cyan-400 tracking-tight">
            Client & Project Management
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-buildings text-lg text-indigo-500"></i> Register entities, assign PICs, and configure API projects.
        </p>
    </div>
</div>

<?php echo $message; echo $error; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 animate-fade-in-up" style="animation-delay: 0.1s;">
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 relative overflow-hidden group hover:shadow-lg transition-shadow">
        <div class="absolute -right-8 -top-8 h-32 w-32 bg-indigo-500 rounded-full blur-3xl opacity-10 group-hover:opacity-20 transition-opacity"></div>
        <div class="flex items-center gap-5 relative z-10">
            <div class="h-14 w-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-3xl shrink-0 border border-indigo-100 dark:border-indigo-500/20"><i class="ph-fill ph-buildings"></i></div>
            <div>
                <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Registered Clients</h6>
                <h3 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($total_clients) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 relative overflow-hidden group hover:shadow-lg transition-shadow">
        <div class="absolute -right-8 -top-8 h-32 w-32 bg-cyan-500 rounded-full blur-3xl opacity-10 group-hover:opacity-20 transition-opacity"></div>
        <div class="flex items-center gap-5 relative z-10">
            <div class="h-14 w-14 rounded-2xl bg-cyan-50 dark:bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center text-3xl shrink-0 border border-cyan-100 dark:border-cyan-500/20"><i class="ph-fill ph-folder-open"></i></div>
            <div>
                <h6 class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-1">Active Projects</h6>
                <h3 class="text-3xl font-black text-slate-800 dark:text-white leading-none"><?= number_format($total_projects) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="animate-fade-in-up mb-10" style="animation-delay: 0.2s;">
    <div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col relative">
        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
        
        <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 shadow-sm"><i class="ph-fill ph-buildings text-2xl"></i></div>
                <div>
                    <h4 class="text-xl font-bold text-slate-800 dark:text-white">Client Directory</h4>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">List of all corporate entities and PIC contact information.</p>
                </div>
            </div>
            <button onclick="openAddCompanyModal()" class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700 active:scale-95 transition-all shadow-lg shadow-indigo-500/30 w-full sm:w-auto">
                <i class="ph-bold ph-plus text-lg"></i> Add New Client
            </button>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="w-full text-left table-modern" id="table-companies">
                <thead>
                    <tr>
                        <th class="ps-8 w-24">Ref ID</th>
                        <th>Client Entity</th>
                        <th>PIC Information</th>
                        <th>Linked Projects</th>
                        <th class="text-center pe-8 w-48">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if(empty($companies)): ?>
                        <tr><td colspan="5" class="py-12 text-center text-slate-500 font-bold"><i class="ph-fill ph-folder-dashed text-4xl mb-2 opacity-50 block"></i>No clients registered.</td></tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): 
                            $c_id = $company['id'];
                            $p_count = $project_counts[$c_id] ?? 0;
                        ?>
                        <tr class="table-row-hover transition-colors group">
                            <td class="ps-8">
                                <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-mono shadow-sm">#CMP-<?= str_pad($c_id, 3, '0', STR_PAD_LEFT) ?></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-black shadow-sm shrink-0">
                                        <?= strtoupper(substr($company['company_name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 dark:text-white text-base"><?= e($company['company_name']); ?></span>
                                        <span class="text-[9px] uppercase tracking-widest text-slate-400 font-black mt-0.5">Corporate Partner</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if(!empty($company['pic_name']) || !empty($company['pic_phone']) || !empty($company['pic_email'])): ?>
                                    <div class="flex flex-col gap-1">
                                        <?php if(!empty($company['pic_name'])): ?>
                                            <span class="font-bold text-slate-700 dark:text-slate-200 text-sm flex items-center gap-1.5"><i class="ph-fill ph-user text-indigo-500"></i> <?= e($company['pic_name']) ?></span>
                                        <?php endif; ?>
                                        
                                        <div class="flex items-center gap-3 mt-1">
                                            <?php if(!empty($company['pic_phone'])): ?>
                                                <span class="text-[11px] font-medium text-slate-500 flex items-center gap-1"><i class="ph-fill ph-phone text-slate-400"></i> <?= e($company['pic_phone']) ?></span>
                                            <?php endif; ?>
                                            <?php if(!empty($company['pic_email'])): ?>
                                                <span class="text-[11px] font-medium text-slate-500 flex items-center gap-1"><i class="ph-fill ph-envelope-simple text-slate-400"></i> <?= e($company['pic_email']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-50 dark:bg-slate-800/50 text-[10px] font-bold text-slate-400 italic border border-dashed border-slate-200 dark:border-slate-700">No PIC Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="viewCompanyProjectsPopup('<?= $c_id; ?>', '<?= addslashes(e($company['company_name'])); ?>')" class="inline-flex items-center gap-2 rounded-xl bg-cyan-50 dark:bg-cyan-500/10 px-4 py-2 text-xs font-bold text-cyan-700 dark:text-cyan-400 border border-cyan-200/60 dark:border-cyan-500/20 hover:bg-cyan-100 hover:shadow-sm transition-all group/badge">
                                    <span class="relative flex h-2 w-2">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                                    </span>
                                    <?= $p_count ?> Project<?= $p_count > 1 ? 's' : '' ?>
                                    <i class="ph-bold ph-caret-right opacity-0 group-hover/badge:opacity-100 transition-opacity -ml-1"></i>
                                </button>
                            </td>
                            <td class="pe-8 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick="viewCompanyProjectsPopup('<?= $c_id; ?>', '<?= addslashes(e($company['company_name'])); ?>')" class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 flex items-center justify-center transition-all shadow-sm" title="View Projects"><i class="ph-bold ph-list-magnifying-glass text-lg"></i></button>
                                    <button onclick="openAddProjectModalWithClient('<?= $company['id']; ?>')" class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 hover:border-emerald-200 flex items-center justify-center transition-all shadow-sm" title="Add Project"><i class="ph-bold ph-folder-plus text-lg"></i></button>
                                    <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-0.5"></div>
                                    <button class="btn-edit-company h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-amber-600 hover:bg-amber-50 hover:border-amber-200 flex items-center justify-center transition-all shadow-sm" 
                                        data-id="<?= $company['id'] ?>" 
                                        data-name="<?= e($company['company_name']) ?>" 
                                        data-pic-name="<?= e($company['pic_name'] ?? '') ?>" 
                                        data-pic-phone="<?= e($company['pic_phone'] ?? '') ?>" 
                                        data-pic-email="<?= e($company['pic_email'] ?? '') ?>" title="Edit">
                                        <i class="ph-fill ph-pencil-simple text-lg"></i>
                                    </button>
                                    <form method="POST" class="m-0" onsubmit="return confirm('WARNING: Deleting this client will also delete all associated projects.\n\nProceed?');">
                                        <input type="hidden" name="id" value="<?= $company['id'] ?>">
                                        <button type="submit" name="delete_company" class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-red-600 hover:bg-red-50 hover:border-red-200 flex items-center justify-center transition-all shadow-sm" title="Delete"><i class="ph-fill ph-trash text-lg"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="animate-fade-in-up mb-10" style="animation-delay: 0.3s;">
    <div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col relative">
        <div class="absolute top-0 left-0 w-1 h-full bg-cyan-500"></div>
        
        <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-50 dark:bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-100 dark:border-cyan-500/20 shadow-sm"><i class="ph-fill ph-folder-open text-2xl"></i></div>
                <div>
                    <h4 class="text-xl font-bold text-slate-800 dark:text-white">Project Database</h4>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">Complete list of subscription keys across all clients.</p>
                </div>
            </div>
            <button onclick="openAddProjectModalWithClient('')" class="flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-6 py-3 text-sm font-bold text-white hover:bg-cyan-700 active:scale-95 transition-all shadow-lg shadow-cyan-500/30 w-full sm:w-auto">
                <i class="ph-bold ph-plus text-lg"></i> Add New Project
            </button>
        </div>
        
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left table-modern" id="table-projects">
                <thead>
                    <tr>
                        <th class="ps-8 w-24">Proj ID</th>
                        <th>Parent Client</th>
                        <th>Project Name</th>
                        <th>API Subscription Key</th>
                        <th class="text-center pe-8 w-32">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if(empty($projects)): ?>
                        <tr><td colspan="5" class="py-12 text-center text-slate-500 font-bold"><i class="ph-fill ph-folder-dashed text-4xl mb-2 opacity-50 block"></i>No projects registered.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $pj): ?>
                        <tr class="table-row-hover transition-colors group">
                            <td class="ps-8">
                                <span class="font-mono text-[10px] font-black uppercase text-slate-400 tracking-widest bg-slate-50 dark:bg-slate-800 px-2 py-1 rounded border border-slate-100 dark:border-slate-700">#PRJ-<?= str_pad($pj['id'], 3, '0', STR_PAD_LEFT) ?></span>
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-xs font-bold text-indigo-700 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 shadow-sm"><i class="ph-fill ph-buildings"></i> <?= e($pj['company_name']) ?></span>
                            </td>
                            <td>
                                <span class="font-bold text-slate-800 dark:text-white text-sm"><?= e($pj['project_name']) ?></span>
                            </td>
                            <td>
                                <code class="text-[11px] font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 select-all shadow-sm flex items-center gap-2 w-max">
                                    <i class="ph-fill ph-key text-amber-500"></i> <?= e($pj['subscription_key']) ?>
                                </code>
                            </td>
                            <td class="pe-8 text-center">
                                <div class="flex justify-center gap-1.5">
                                    <button onclick='openEditProjectModal(<?= json_encode($pj) ?>)' class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-amber-600 hover:bg-amber-50 hover:border-amber-200 flex items-center justify-center transition-all shadow-sm" title="Edit"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                                    <form method="POST" class="m-0" onsubmit="return confirm('Delete this project?')">
                                        <input type="hidden" name="id" value="<?= $pj['id'] ?>">
                                        <button type="submit" name="delete_project" class="h-9 w-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-red-600 hover:bg-red-50 hover:border-red-200 flex items-center justify-center transition-all shadow-sm" title="Delete"><i class="ph-fill ph-trash text-lg"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="viewProjectsModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-8 py-6 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white shrink-0">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center text-3xl shadow-inner border border-white/20"><i class="ph-fill ph-folder-open"></i></div>
                <div>
                    <h5 class="text-xl font-black leading-tight" id="view_modal_title">Projects</h5>
                    <p class="text-[10px] uppercase font-bold text-indigo-200 tracking-widest mt-1">Linked Projects Database</p>
                </div>
            </div>
            <button type="button" class="btn-close-modal h-10 w-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="overflow-y-auto p-8 max-h-[60vh] bg-slate-50 dark:bg-slate-900/50 custom-scrollbar" id="project_list_container">
            </div>
        <div class="border-t border-slate-200 dark:border-slate-700 p-6 bg-white dark:bg-slate-800 flex justify-end shrink-0">
            <button type="button" class="btn-close-modal rounded-xl px-8 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm">Close Panel</button>
        </div>
    </div>
</div>

<div id="addCompanyModal" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <form method="POST" action="">
            <input type="hidden" name="id" id="edit_company_id"> 
            
            <div class="flex items-center justify-between border-b border-indigo-500 px-8 py-6 bg-indigo-600 text-white">
                <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-buildings text-2xl"></i> <span id="companyModalTitle">Client Registration</span></h5>
                <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
            </div>
            
            <div class="p-8 bg-slate-50 dark:bg-slate-900/50 space-y-6">
                <div>
                    <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Corporate Entity Name *</label>
                    <div class="relative">
                        <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <input type="text" id="edit_company_name" name="company_name" required placeholder="e.g. PT Telekomunikasi Indonesia" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3.5 pl-12 text-sm font-bold text-slate-800 dark:text-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none shadow-sm transition-all">
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <div class="absolute left-0 top-0 w-1 h-full bg-indigo-500"></div>
                    <h6 class="text-xs font-black text-slate-800 dark:text-white mb-4 flex items-center gap-2"><i class="ph-fill ph-user-circle text-indigo-500 text-lg"></i> PIC Contact Details</h6>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Full Name</label>
                            <input type="text" id="edit_pic_name" name="pic_name" placeholder="John Doe" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Phone Number</label>
                            <input type="text" id="edit_pic_phone" name="pic_phone" placeholder="+62 812..." class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Email Address</label>
                            <input type="email" id="edit_pic_email" name="pic_email" placeholder="john@company.com" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-4 py-3 text-sm focus:border-indigo-500 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-[#24303F] flex justify-end gap-3 rounded-b-3xl">
                <button type="button" class="btn-close-modal rounded-xl px-6 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Discard</button>
                <button type="submit" name="save_company" class="rounded-xl bg-indigo-600 px-8 py-3 text-sm font-bold text-white transition-all hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95 flex items-center gap-2"><i class="ph-bold ph-floppy-disk"></i> Save Client Data</button>
            </div>
        </form>
    </div>
</div>

<div id="addProjectModal" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <form method="POST" action="">
            <input type="hidden" name="id" id="edit_project_id"> 
            
            <div class="flex items-center justify-between border-b border-cyan-500 px-8 py-6 bg-cyan-600 text-white">
                <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-folder-open text-2xl"></i> <span id="projectModalTitle">Project Configuration</span></h5>
                <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
            </div>
            
            <div class="p-8 bg-slate-50 dark:bg-slate-900/50 space-y-5">
                <div>
                    <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Parent Client Entity *</label>
                    <div class="relative">
                        <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                        <select id="add_project_company_id" name="company_id" required class="w-full appearance-none rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3.5 pl-12 text-sm font-bold text-slate-800 dark:text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 outline-none shadow-sm transition-all cursor-pointer">
                            <option value="">-- Assign to Client --</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>"><?php echo e($company['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    </div>
                </div>
                
                <div>
                    <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Project Name *</label>
                    <input type="text" id="edit_project_name" name="project_name" required placeholder="e.g. IoT Smart Meter" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3.5 text-sm font-bold text-slate-800 dark:text-white focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 outline-none shadow-sm transition-all">
                </div>
                
                <div class="bg-cyan-50 dark:bg-cyan-500/5 p-4 rounded-2xl border border-cyan-100 dark:border-cyan-500/20">
                    <label class="mb-2 block text-[10px] font-black uppercase tracking-widest text-cyan-700 dark:text-cyan-400 flex items-center gap-1.5"><i class="ph-fill ph-key"></i> API Subscription Key *</label>
                    <input type="text" id="edit_project_subscription_key" name="subscription_key" required placeholder="Input unique key..." class="w-full rounded-xl border border-cyan-200 dark:border-cyan-500/30 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-mono font-bold text-slate-700 dark:text-slate-300 focus:border-cyan-500 outline-none shadow-sm transition-all">
                </div>
            </div>
            
            <div class="border-t border-slate-200 dark:border-slate-700 px-8 py-5 bg-white dark:bg-[#24303F] flex justify-end gap-3 rounded-b-3xl">
                <button type="button" class="btn-close-modal rounded-xl px-6 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Cancel</button>
                <button type="submit" name="save_project" class="rounded-xl bg-cyan-600 px-8 py-3 text-sm font-bold text-white transition-all hover:bg-cyan-700 shadow-lg shadow-cyan-500/30 active:scale-95 flex items-center gap-2"><i class="ph-bold ph-floppy-disk"></i> Save Project</button>
            </div>
        </form>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    const projectsData = <?= json_encode($projects) ?>;

    // View Projects Popup Builder
    window.viewCompanyProjectsPopup = function(companyId, companyName) {
        $('#view_modal_title').text(companyName);
        const filtered = projectsData.filter(p => p.company_id == companyId);
        let html = '';
        
        if (filtered.length === 0) {
            html = `
            <div class="flex flex-col items-center justify-center py-12 opacity-60">
                <div class="h-20 w-20 bg-slate-200 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4"><i class="ph-fill ph-folder-dashed text-4xl text-slate-400"></i></div>
                <p class="font-bold text-slate-500 dark:text-slate-400">No projects linked to this client yet.</p>
            </div>`;
        } else {
            html = `<div class="flex flex-col gap-4">`;
            filtered.forEach((p, i) => {
                html += `
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-5 rounded-2xl shadow-sm flex items-center gap-5 hover:border-indigo-300 transition-colors group">
                    <div class="h-12 w-12 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center font-black text-lg border border-indigo-100 dark:border-indigo-500/20 shrink-0 group-hover:scale-110 transition-transform">${i+1}</div>
                    <div class="flex-1 min-w-0">
                        <h6 class="font-black text-slate-800 dark:text-white text-base truncate mb-1">${p.project_name}</h6>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">API Key:</span>
                            <code class="text-[11px] font-bold text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900 px-2 py-1 rounded border border-slate-200 dark:border-slate-700 select-all truncate">${p.subscription_key}</code>
                        </div>
                    </div>
                </div>`;
            });
            html += `</div>`;
        }
        $('#project_list_container').html(html);
        $('body').css('overflow', 'hidden');
        $('#viewProjectsModal').removeClass('hidden').addClass('flex');
    };

    // Modal Triggers
    window.openAddProjectModalWithClient = function(companyId) {
        $('#projectModalTitle').text('New Project Registration');
        $('#edit_project_id').val(''); 
        $('#edit_project_name').val(''); 
        $('#edit_project_subscription_key').val('');
        if(companyId) $('#add_project_company_id').val(companyId);
        else $('#add_project_company_id').val('');
        
        $('body').css('overflow', 'hidden');
        $('#addProjectModal').removeClass('hidden').addClass('flex');
    };
    
    window.openAddCompanyModal = function() {
        $('#companyModalTitle').text('Client Registration');
        $('#edit_company_id').val(''); 
        $('#edit_company_name').val(''); 
        $('#edit_pic_name').val(''); 
        $('#edit_pic_phone').val(''); 
        $('#edit_pic_email').val('');
        $('body').css('overflow', 'hidden');
        $('#addCompanyModal').removeClass('hidden').addClass('flex');
    };
    
    $(document).on('click', '.btn-edit-company', function() {
        $('#companyModalTitle').text('Edit Client Profile');
        const d = $(this).data();
        $('#edit_company_id').val(d.id); 
        $('#edit_company_name').val(d.name); 
        $('#edit_pic_name').val(d.picName); 
        $('#edit_pic_phone').val(d.picPhone); 
        $('#edit_pic_email').val(d.picEmail);
        $('body').css('overflow', 'hidden');
        $('#addCompanyModal').removeClass('hidden').addClass('flex');
    });
    
    window.openEditProjectModal = function(data) {
        $('#projectModalTitle').text('Edit Project Configuration');
        $('#edit_project_id').val(data.id); 
        $('#add_project_company_id').val(data.company_id); 
        $('#edit_project_name').val(data.project_name); 
        $('#edit_project_subscription_key').val(data.subscription_key);
        $('body').css('overflow', 'hidden');
        $('#addProjectModal').removeClass('hidden').addClass('flex');
    };

    // Global Modal Close Handler
    $('.btn-close-modal').click(function() {
        $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
        $('body').css('overflow', 'auto');
    });
    
    // Close modal on click outside
    $('.modal-container').click(function(e) {
        if(e.target === this) {
            $(this).removeClass('flex').addClass('hidden');
            $('body').css('overflow', 'auto');
        }
    });
});
</script>
<?php 
$page_scripts = ob_get_clean(); 
require_once 'includes/footer.php'; 
?>