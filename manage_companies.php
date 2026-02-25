<?php
// =========================================================================
// FILE: manage_companies.php
// DESC: Manage Clients & Projects (Ultra-Modern, Detailed Table & Popup)
// =========================================================================

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 

$db = db_connect();
$message = '';
$error = '';

// Helper function untuk generate alert Tailwind dari backend
function tailwindAlert($type, $msg) {
    if ($type === 'success') {
        return '<div class="relative flex items-center justify-between px-5 py-4 mb-6 text-sm font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-2xl animate-fade-in-up dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 shadow-sm"><div class="flex items-center gap-3"><i class="ph-fill ph-check-circle text-xl"></i><span>'.$msg.'</span></div><button type="button" class="text-emerald-600 hover:text-emerald-800 dark:hover:text-emerald-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
    } else {
        return '<div class="relative flex items-center justify-between px-5 py-4 mb-6 text-sm font-bold text-red-800 bg-red-50 border border-red-200 rounded-2xl animate-fade-in-up dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20 shadow-sm"><div class="flex items-center gap-3"><i class="ph-fill ph-warning-circle text-xl"></i><span>'.$msg.'</span></div><button type="button" class="text-red-600 hover:text-red-800 dark:hover:text-red-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
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
                $message = tailwindAlert('success', 'The new company has been successfully added.');
            } else {
                $stmt = $db->prepare("UPDATE companies SET company_name = ?, pic_name = ?, pic_phone = ?, pic_email = ? WHERE id = ?");
                $stmt->execute([$company_name, $pic_name, $pic_phone, $pic_email, $id]);
                $message = tailwindAlert('success', 'The company name has been successfully updated.');
            }
        }

        // Aksi: Hapus PERUSAHAAN
        if (isset($_POST['delete_company'])) {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            $message = tailwindAlert('success', 'The company and all related projects have been successfully deleted.');
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
                $message = tailwindAlert('success', 'New project successfully added.');
            } else {
                $stmt = $db->prepare("UPDATE projects SET company_id = ?, project_name = ?, subscription_key = ? WHERE id = ?");
                $stmt->execute([$company_id, $project_name, $subscription_key, $id]);
                $message = tailwindAlert('success', 'The project data has been successfully updated.');
            }
        }

        // Aksi: Hapus PROYEK
        if (isset($_POST['delete_project'])) {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $message = tailwindAlert('success', 'Project data has been successfully deleted.');
        }
    }
} catch (PDOException $e) {
    $error = tailwindAlert('error', 'Failed to process data: ' . htmlspecialchars($e->getMessage()));
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
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .table-row-hover:hover td { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-row-hover:hover td { background-color: rgba(30, 41, 59, 0.5); }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center animate-fade-in-up">
    <div>
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">Manage Clients & Projects</h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5"><i class="ph ph-buildings text-lg"></i> Register companies, assign PICs, and setup API projects.</p>
    </div>
</div>

<?php echo $message; echo $error; ?>

<div class="animate-fade-in-up mb-10" style="animation-delay: 0.1s;">
    <div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col relative">
        <div class="absolute top-0 left-0 w-1 h-full bg-purple-500"></div>
        <div class="border-b border-slate-100 dark:border-slate-800 px-6 py-5 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-500/20 shadow-sm"><i class="ph-fill ph-buildings text-xl"></i></div>
                <div><h4 class="text-lg font-bold text-slate-800 dark:text-white">Client Directory</h4><p class="text-xs text-slate-500">List of all entities and PIC information.</p></div>
            </div>
            <button onclick="openAddCompanyModal()" class="flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 active:scale-95 transition-all shadow-md"><i class="ph-bold ph-plus text-base"></i> Add New Client</button>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" id="table-companies">
                <thead>
                    <tr class="bg-white dark:bg-[#24303F] border-b border-slate-200 dark:border-slate-700">
                        <th class="px-8 py-4 text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 whitespace-nowrap w-24">Ref ID</th>
                        <th class="px-8 py-4 text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 whitespace-nowrap">Client Entity</th>
                        <th class="px-8 py-4 text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 whitespace-nowrap">PIC Information</th>
                        <th class="px-8 py-4 text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 whitespace-nowrap">Linked Projects</th>
                        <th class="px-8 py-4 text-xs font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 text-center whitespace-nowrap w-44">Quick Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($companies as $company): 
                        $c_id = $company['id'];
                        $p_count = $project_counts[$c_id] ?? 0;
                    ?>
                    <tr class="table-row-hover transition-colors group">
                        <td class="px-8 py-4 align-top"><span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-1 text-xs font-bold text-slate-500 border border-slate-200 font-mono mt-1">#CMP-<?= str_pad($c_id, 3, '0', STR_PAD_LEFT) ?></span></td>
                        <td class="px-8 py-4 align-top">
                            <div class="flex flex-col"><span class="font-bold text-slate-800 dark:text-white text-base"><?php echo htmlspecialchars($company['company_name']); ?></span><span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Corporate Partner</span></div>
                        </td>
                        <td class="px-8 py-4 align-top">
                            <div class="flex flex-col gap-1 text-sm">
                                <?php if(!empty($company['pic_name'])): ?>
                                    <span class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5"><i class="ph-fill ph-user text-slate-400"></i> <?= htmlspecialchars($company['pic_name']) ?></span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1.5"><i class="ph-fill ph-phone text-slate-400"></i> <?= htmlspecialchars($company['pic_phone']) ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">No PIC assigned</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-8 py-4 align-top">
                            <span onclick="viewCompanyProjectsPopup('<?php echo $c_id; ?>', '<?php echo addslashes(htmlspecialchars($company['company_name'])); ?>')" class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 dark:bg-blue-500/10 px-3 py-1.5 text-xs font-bold text-blue-700 dark:text-blue-400 border border-blue-100 cursor-pointer hover:bg-blue-100 transition-all mt-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span> <?= $p_count ?> Project<?= $p_count > 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td class="px-8 py-4 align-top text-center">
                            <div class="flex items-center justify-center gap-2 mt-1">
                                <button onclick="viewCompanyProjectsPopup('<?php echo $c_id; ?>', '<?php echo addslashes(htmlspecialchars($company['company_name'])); ?>')" class="h-9 w-9 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center transition-all shadow-sm" title="View Projects"><i class="ph-bold ph-list-magnifying-glass text-lg"></i></button>
                                <button onclick="openAddProjectModalWithClient('<?php echo $company['id']; ?>')" class="h-9 w-9 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center transition-all shadow-sm" title="Add Project"><i class="ph-bold ph-folder-plus text-lg"></i></button>
                                <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-0.5"></div>
                                <button class="btn-edit-company h-9 w-9 rounded-xl border border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center transition-all shadow-sm" 
                                    data-id="<?= $company['id'] ?>" 
                                    data-name="<?= htmlspecialchars($company['company_name']) ?>" 
                                    data-pic-name="<?= htmlspecialchars($company['pic_name'] ?? '') ?>" 
                                    data-pic-phone="<?= htmlspecialchars($company['pic_phone'] ?? '') ?>" 
                                    data-pic-email="<?= htmlspecialchars($company['pic_email'] ?? '') ?>" title="Edit">
                                    <i class="ph-fill ph-pencil-simple text-lg"></i>
                                </button>
                                <form method="POST" class="m-0" onsubmit="return confirm('Delete this client?');">
                                    <input type="hidden" name="id" value="<?= $company['id'] ?>">
                                    <button type="submit" name="delete_company" class="h-9 w-9 rounded-xl border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 flex items-center justify-center transition-all shadow-sm"><i class="ph-fill ph-trash text-lg"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="project-table-section" class="animate-fade-in-up mb-10" style="animation-delay: 0.2s;">
    <div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col relative">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
        <div class="border-b border-slate-100 dark:border-slate-800 px-6 py-5 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-1">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20 shadow-sm"><i class="ph-fill ph-folder-open text-xl"></i></div>
                <div><h4 class="text-lg font-bold text-slate-800 dark:text-white">Project Database</h4><p class="text-xs text-slate-500">Complete list of subscription keys across all clients.</p></div>
            </div>
            <button onclick="openAddProjectModalWithClient('')" class="flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 active:scale-95 transition-all shadow-md"><i class="ph-bold ph-plus text-base"></i> Add New Project</button>
        </div>
        
        <div class="p-6 bg-slate-50/50">
            <table class="w-full text-left border-collapse" id="table-projects">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-400">ID</th>
                        <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-400">Client Name</th>
                        <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-400">Project Name</th>
                        <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-400">API Key</th>
                        <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-400 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($projects as $pj): ?>
                    <tr class="table-row-hover transition-colors">
                        <td class="px-6 py-4 font-mono text-xs text-slate-400">#PRJ-<?= $pj['id'] ?></td>
                        <td class="px-6 py-4"><span class="inline-flex rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 border border-slate-200"><?= htmlspecialchars($pj['company_name']) ?></span></td>
                        <td class="px-6 py-4 font-bold text-slate-800"><?= htmlspecialchars($pj['project_name']) ?></td>
                        <td class="px-6 py-4 font-mono text-xs text-indigo-500 font-bold"><?= htmlspecialchars($pj['subscription_key']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center gap-2">
                                <button onclick='openEditProjectModal(<?= json_encode($pj) ?>)' class="text-amber-500 hover:text-amber-700 transition-colors"><i class="ph ph-pencil-simple text-lg"></i></button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete project?')"><input type="hidden" name="id" value="<?= $pj['id'] ?>"><button type="submit" name="delete_project" class="text-red-500 hover:text-red-700 transition-colors"><i class="ph ph-trash text-lg"></i></button></form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="viewProjectsModal" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-7 py-5 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white">
            <div class="flex items-center gap-3"><i class="ph-fill ph-list-magnifying-glass text-2xl"></i><div><h5 class="text-lg font-bold" id="view_modal_title">Projects</h5><p class="text-[10px] uppercase font-bold opacity-80 tracking-widest">Registered Projects</p></div></div>
            <button type="button" class="btn-close-modal h-9 w-9 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="overflow-y-auto p-6 max-h-[60vh] bg-slate-50 dark:bg-slate-900/50" id="project_list_container"></div>
        <div class="border-t border-slate-200 p-5 bg-white dark:bg-slate-800 flex justify-end"><button type="button" class="btn-close-modal rounded-xl px-8 py-2.5 text-sm font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 transition-all">Close</button></div>
    </div>
</div>

<div id="addCompanyModal" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-2xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <form method="POST" action="manage_companies.php">
            <input type="hidden" name="id" id="edit_company_id"> 
            <div class="flex items-center justify-between border-b border-indigo-500 px-6 py-4 bg-indigo-600 text-white"><h5 class="text-base font-bold flex items-center gap-2"><i class="ph-bold ph-plus-circle text-xl"></i> Client Information</h5><button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-lg"></i></button></div>
            <div class="p-6 bg-slate-50 dark:bg-slate-900/50">
                <div class="mb-5"><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Company Name *</label><input type="text" id="edit_company_name" name="company_name" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-medium focus:border-indigo-500 outline-none shadow-sm transition-all"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2"><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">PIC Full Name</label><input type="text" id="edit_pic_name" name="pic_name" class="w-full rounded-xl border border-slate-300 bg-white dark:bg-slate-800 px-4 py-3 text-sm focus:border-indigo-500 outline-none shadow-sm"></div>
                    <div><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">PIC Phone</label><input type="text" id="edit_pic_phone" name="pic_phone" class="w-full rounded-xl border border-slate-300 bg-white dark:bg-slate-800 px-4 py-3 text-sm focus:border-indigo-500 outline-none shadow-sm"></div>
                    <div><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">PIC Email</label><input type="email" id="edit_pic_email" name="pic_email" class="w-full rounded-xl border border-slate-300 bg-white dark:bg-slate-800 px-4 py-3 text-sm focus:border-indigo-500 outline-none shadow-sm"></div>
                </div>
            </div>
            <div class="border-t border-slate-200 px-6 py-4 bg-white dark:bg-slate-800 flex justify-end gap-3"><button type="button" class="btn-close-modal rounded-xl px-5 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">Cancel</button><button type="submit" name="save_company" class="rounded-xl bg-indigo-600 px-8 py-2 text-sm font-bold text-white transition-all hover:bg-indigo-700 shadow-md active:scale-95"><i class="ph-bold ph-floppy-disk"></i> Save Data</button></div>
        </form>
    </div>
</div>

<div id="addProjectModal" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <form method="POST" action="manage_companies.php">
            <input type="hidden" name="id" id="edit_project_id"> 
            <div class="flex items-center justify-between border-b border-blue-500 px-6 py-4 bg-blue-600 text-white"><h5 class="text-base font-bold flex items-center gap-2"><i class="ph-bold ph-plus-circle text-xl"></i> Project Setup</h5><button type="button" class="btn-close-modal h-8 w-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"><i class="ph ph-x text-lg"></i></button></div>
            <div class="p-6 bg-slate-50 dark:bg-slate-900/50">
                <div class="mb-4"><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Parent Client</label><select id="add_project_company_id" name="company_id" required class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white px-4 py-3 text-sm focus:border-blue-500 outline-none shadow-sm transition-all"><?php foreach ($companies as $company): ?><option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option><?php endforeach; ?></select></div>
                <div class="mb-4"><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Project Name</label><input type="text" id="edit_project_name" name="project_name" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-blue-500 outline-none shadow-sm transition-all"></div>
                <div class="mb-2"><label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">API Key</label><input type="text" id="edit_project_subscription_key" name="subscription_key" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-mono focus:border-blue-500 outline-none shadow-sm transition-all"></div>
            </div>
            <div class="border-t border-slate-200 px-6 py-4 bg-white dark:bg-slate-800 flex justify-end gap-3"><button type="button" class="btn-close-modal rounded-xl px-5 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 transition-colors">Cancel</button><button type="submit" name="save_project" class="rounded-xl bg-blue-600 px-8 py-2 text-sm font-bold text-white transition-all hover:bg-blue-700 shadow-md active:scale-95">Save Project</button></div>
        </form>
    </div>
</div>

<?php ob_start(); ?>
<script src="assets/extensions/jquery/jquery.min.js"></script>
<script>
$(document).ready(function() {
    const projectsData = <?= json_encode($projects) ?>;

    // POPUP VIEW PROJECT DETAIL
    window.viewCompanyProjectsPopup = function(companyId, companyName) {
        $('#view_modal_title').text(companyName);
        const filtered = projectsData.filter(p => p.company_id == companyId);
        let html = '';
        if (filtered.length === 0) {
            html = `<div class="flex flex-col items-center justify-center py-10 opacity-50"><i class="ph ph-folder-dashed text-5xl mb-2"></i><p class="font-bold text-slate-400">No projects linked to this client.</p></div>`;
        } else {
            html = `<div class="flex flex-col gap-3">`;
            filtered.forEach((p, i) => {
                html += `
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 rounded-2xl shadow-sm flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-10 w-10 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-full flex items-center justify-center font-black text-sm">${i+1}</div>
                        <div>
                            <h6 class="font-black text-slate-800 dark:text-white leading-tight">${p.project_name}</h6>
                            <code class="text-[11px] font-bold text-slate-400 dark:text-indigo-400 bg-slate-50 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 mt-1 inline-block">${p.subscription_key}</code>
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

    // MODAL HANDLERS
    window.openAddProjectModalWithClient = function(companyId) {
        $('#edit_project_id').val(''); $('#edit_project_name').val(''); $('#edit_project_subscription_key').val('');
        if(companyId) $('#add_project_company_id').val(companyId);
        $('body').css('overflow', 'hidden');
        $('#addProjectModal').removeClass('hidden').addClass('flex');
    };
    window.openAddCompanyModal = function() {
        $('#edit_company_id').val(''); $('#edit_company_name').val(''); $('#edit_pic_name').val(''); $('#edit_pic_phone').val(''); $('#edit_pic_email').val('');
        $('body').css('overflow', 'hidden');
        $('#addCompanyModal').removeClass('hidden').addClass('flex');
    };
    $(document).on('click', '.btn-edit-company', function() {
        const d = $(this).data();
        $('#edit_company_id').val(d.id); $('#edit_company_name').val(d.name); $('#edit_pic_name').val(d.picName); $('#edit_pic_phone').val(d.picPhone); $('#edit_pic_email').val(d.picEmail);
        $('body').css('overflow', 'hidden');
        $('#addCompanyModal').removeClass('hidden').addClass('flex');
    });
    window.openEditProjectModal = function(data) {
        $('#edit_project_id').val(data.id); $('#add_project_company_id').val(data.company_id); $('#edit_project_name').val(data.project_name); $('#edit_project_subscription_key').val(data.subscription_key);
        $('body').css('overflow', 'hidden');
        $('#addProjectModal').removeClass('hidden').addClass('flex');
    };

    // GLOBAL MODAL CLOSE
    $('.btn-close-modal').click(function() {
        $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
        if($('.modal-container.flex').length === 0) $('body').css('overflow', 'auto');
    });
});
</script>
<?php $page_scripts = ob_get_clean(); require_once 'includes/footer.php'; ?>