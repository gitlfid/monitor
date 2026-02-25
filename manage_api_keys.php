<?php
/*
 File: manage_api_keys.php
 Fungsi: Mengelola API Keys & Force Logout User (Ultra-Modern Full-Width Layout)
*/

// Aktifkan Error Reporting untuk debug
ini_set('display_errors', 0); // Set ke 0 di production
error_reporting(E_ALL);

require_once 'includes/auth_check.php'; 
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Hanya Admin
require_admin();

$db = db_connect();
$message = '';

// --- FUNGSI HELPER ALERT TAILWIND ---
function tailwindAlert($type, $msg) {
    $colors = $type === 'success' ? 'emerald' : ($type === 'warning' ? 'amber' : 'red');
    $icon = $type === 'success' ? 'ph-check-circle' : ($type === 'warning' ? 'ph-warning' : 'ph-x-circle');
    return '<div class="relative flex items-center justify-between px-6 py-4 mb-6 text-sm font-bold text-'.$colors.'-800 bg-'.$colors.'-50 border border-'.$colors.'-200 rounded-2xl animate-fade-in-up dark:bg-'.$colors.'-500/10 dark:text-'.$colors.'-400 dark:border-'.$colors.'-500/20 shadow-sm"><div class="flex items-start gap-3"><i class="ph-fill '.$icon.' text-2xl mt-0.5"></i><div class="leading-relaxed">'.$msg.'</div></div><button type="button" class="text-'.$colors.'-600 hover:text-'.$colors.'-800 dark:hover:text-'.$colors.'-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
}

// --- LOGIKA REGENERATE (FORCE LOGOUT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_id'])) {
    $target_id = intval($_POST['regenerate_id']);
    $target_username = $_POST['target_username'] ?? 'User';
    
    try {
        // 1. Buat Key Baru yang benar-benar acak
        $new_access = bin2hex(random_bytes(16));
        $new_secret = bin2hex(random_bytes(32));
        
        // 2. Update ke Database
        $stmt = $db->prepare("UPDATE users SET access_key = ?, secret_key = ? WHERE id = ?");
        if ($stmt->execute([$new_access, $new_secret, $target_id])) {
            
            // PENTING: Mencegah Admin Ter-logout Sendiri
            if ($target_id == $_SESSION['user_id']) {
                $_SESSION['access_key'] = $new_access;
            }

            $message = tailwindAlert('success', "API Keys for <strong>" . htmlspecialchars($target_username) . "</strong> successfully regenerated.<br><span class='text-xs mt-1 block opacity-80'><i class='ph-fill ph-info'></i> If the user is currently logged in, their session has been invalidated and they will be forced to log out on their next action.</span>");
        } else {
            throw new Exception("Failed to update database.");
        }
    } catch (Exception $e) {
        $message = tailwindAlert('error', "Error: " . $e->getMessage());
    }
}

// Ambil Data User
$users = $db->query("SELECT id, username, role, access_key, secret_key FROM users ORDER BY role ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    
    /* Table Enhancements */
    .table-modern { width: 100%; border-collapse: collapse; }
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.5rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.6); }
    
    /* Smart Inputs */
    .smart-input::-ms-reveal, .smart-input::-ms-clear { display: none; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-rose-600 to-orange-500 dark:from-rose-400 dark:to-orange-400 tracking-tight">
            API & Session Management
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-shield-keyhole text-lg text-rose-500"></i> Manage access security and force user logouts (Revoke Access).
        </p>
    </div>
</div>

<?php echo $message; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <div class="rounded-3xl bg-gradient-to-br from-slate-800 to-slate-900 p-8 text-white shadow-soft relative overflow-hidden group animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-20"></div>
        <div class="absolute -right-8 -top-8 h-32 w-32 bg-rose-500 rounded-full blur-3xl opacity-20 group-hover:opacity-40 transition-opacity duration-700"></div>
        
        <h4 class="text-lg font-black mb-6 relative flex items-center gap-2">
            <i class="ph-fill ph-shield-check text-emerald-400 text-2xl"></i> Security Mechanisms
        </h4>
        
        <div class="space-y-5 relative z-10">
            <div class="flex gap-4 items-start">
                <div class="mt-0.5 h-8 w-8 rounded-lg bg-slate-700/50 border border-slate-600 flex items-center justify-center text-blue-400 shrink-0"><i class="ph-bold ph-hourglass-high"></i></div>
                <div>
                    <p class="text-sm font-bold text-white mb-1">Idle Timeout Protection</p>
                    <p class="text-xs text-slate-400 leading-relaxed">If a user is inactive for <strong>5 minutes</strong>, the system will automatically log them out to prevent unauthorized physical access.</p>
                </div>
            </div>
            
            <div class="flex gap-4 items-start">
                <div class="mt-0.5 h-8 w-8 rounded-lg bg-slate-700/50 border border-slate-600 flex items-center justify-center text-rose-400 shrink-0"><i class="ph-bold ph-power"></i></div>
                <div>
                    <p class="text-sm font-bold text-rose-300 mb-1">Force Logout (Revoke API)</p>
                    <p class="text-xs text-slate-400 leading-relaxed">Clicking <span class="bg-rose-500/20 text-rose-300 px-1.5 py-0.5 rounded font-bold border border-rose-500/30">Revoke</span> instantly regenerates database API Keys. The active session will be destroyed automatically on their next action.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-3xl bg-white dark:bg-[#24303F] p-8 shadow-soft border border-slate-100 dark:border-slate-800 relative overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500"></div>
        <h4 class="text-lg font-black text-slate-800 dark:text-white mb-6 flex items-center gap-2">
            <i class="ph-fill ph-key text-indigo-500 text-2xl"></i> Key Structures
        </h4>
        
        <div class="flex flex-col gap-5">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-bold ph-lock-key-open"></i></div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-slate-800 dark:text-white mb-1">Access Key (Public)</p>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">16-byte Hexadecimal string used as a public identifier for sessions and API requests.</p>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 flex items-center justify-center text-2xl shrink-0"><i class="ph-bold ph-lock-key"></i></div>
                <div class="flex-1">
                    <p class="text-sm font-bold text-slate-800 dark:text-white mb-1">Secret Key (Private)</p>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">32-byte Hexadecimal string used to sign requests. Must never be exposed publicly.</p>
                </div>
            </div>
        </div>
    </div>
    
</div>

<div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden relative animate-fade-in-up" style="animation-delay: 0.3s;">
    <div class="absolute top-0 left-0 w-1 h-full bg-rose-500"></div>
    
    <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h4 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="ph-fill ph-users-three text-rose-500 text-xl"></i> User API Credentials
            </h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Complete list of registered users and their session security keys.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 text-xs font-black uppercase tracking-widest border border-rose-100 dark:border-rose-500/20 shadow-sm">
            Total Valid Users: <?php echo count($users); ?>
        </span>
    </div>
    
    <div class="overflow-x-auto w-full">
        <table class="table-modern text-left">
            <thead>
                <tr>
                    <th class="ps-8 w-[20%]">User Identity</th>
                    <th class="w-[10%]">Role</th>
                    <th class="w-[25%]">Access Key</th>
                    <th class="w-[30%]">Secret Key</th>
                    <th class="text-center pe-8 w-[15%]">Security Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach($users as $index => $u): 
                    $animDelay = min($index * 0.05, 0.5); 
                ?>
                <tr class="table-row-hover transition-colors animate-fade-in-up opacity-0" style="animation-delay: <?= $animDelay ?>s;">
                    <td class="ps-8">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center font-black border border-slate-200 dark:border-slate-700 shrink-0">
                                <?= strtoupper(substr($u['username'], 0, 1)) ?>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-800 dark:text-white text-sm"><?= htmlspecialchars($u['username']) ?></span>
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mt-0.5">UID: #<?= str_pad($u['id'], 3, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>
                    </td>
                    
                    <td>
                        <?php if($u['role'] === 'admin'): ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-400 border border-purple-200 dark:border-purple-500/20 text-[10px] font-black uppercase tracking-widest"><i class="ph-fill ph-crown"></i> Admin</span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 text-[10px] font-black uppercase tracking-widest"><i class="ph-fill ph-user"></i> User</span>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <?php if($u['access_key']): ?>
                            <code class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1.5 rounded-lg border border-indigo-100 dark:border-indigo-800 select-all block w-max shadow-sm truncate max-w-[200px]" title="<?= $u['access_key'] ?>">
                                <?= $u['access_key'] ?>
                            </code>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-amber-600 bg-amber-50 dark:bg-amber-500/10 px-3 py-1.5 rounded-lg border border-amber-100 dark:border-amber-500/20"><i class="ph-fill ph-warning-circle"></i> Not Generated</span>
                        <?php endif; ?>
                    </td>
                    
                    <td>
                        <?php if($u['secret_key']): ?>
                            <div class="relative w-full max-w-[280px] group">
                                <input type="password" id="sec_<?= $u['id'] ?>" class="smart-input w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 px-4 py-2.5 pr-12 text-xs font-mono font-bold text-slate-700 dark:text-slate-300 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-sm" value="<?= $u['secret_key'] ?>" readonly>
                                <button type="button" onclick="toggleSecret(<?= $u['id'] ?>)" class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-lg text-slate-400 hover:bg-white dark:hover:bg-slate-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all flex items-center justify-center shadow-sm" title="Reveal Secret Key">
                                    <i id="icon_<?= $u['id'] ?>" class="ph-bold ph-eye text-lg"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="text-xs font-medium text-slate-400 italic flex items-center gap-1.5"><i class="ph ph-hourglass"></i> Requires first login</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="pe-8 text-center">
                        <form method="POST" class="m-0" onsubmit="return confirm('SECURITY WARNING:\n\nYou are about to regenerate API Keys for this user.\nIf they are currently logged in, their session will be INVALIDATED and forced to logout.\n\nProceed?');">
                            <input type="hidden" name="regenerate_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="target_username" value="<?= htmlspecialchars($u['username']) ?>">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 dark:hover:text-white transition-all text-xs font-bold shadow-sm active:scale-95 w-full max-w-[140px] mx-auto group/btn" title="Force Logout User">
                                <i class="ph-bold ph-arrows-clockwise text-base group-hover/btn:-rotate-90 transition-transform duration-300"></i> Revoke Access
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Toggle Password Visibility
function toggleSecret(id) {
    var input = document.getElementById("sec_" + id);
    var icon = document.getElementById("icon_" + id);
    
    if (input.type === "password") {
        input.type = "text";
        if(icon) { 
            icon.classList.remove('ph-eye'); 
            icon.classList.add('ph-eye-slash', 'text-indigo-600', 'dark:text-indigo-400'); 
        }
    } else {
        input.type = "password";
        if(icon) { 
            icon.classList.remove('ph-eye-slash', 'text-indigo-600', 'dark:text-indigo-400'); 
            icon.classList.add('ph-eye'); 
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>