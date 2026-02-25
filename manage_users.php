<?php
// =========================================================================
// FILE: manage_users.php
// DESC: User Management (Ultra-Modern Tailwind CSS & Dynamic UI)
// =========================================================================

ini_set('display_errors', 0); error_reporting(E_ALL);

require_once 'includes/auth_check.php'; 
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check Mail Helper
if (file_exists('includes/mail_helper.php')) {
    require_once 'includes/mail_helper.php';
} else {
    function send_credentials_email($to, $user, $pass) { return false; }
}

require_admin(); // Ensure only Admin access
$db = db_connect();
$message = '';

// --- FIX: DEFINISI FUNGSI e() UNTUK ESCAPING HTML ---
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper function untuk alert Tailwind
function tailwindAlert($type, $msg) {
    $colors = $type === 'success' ? 'emerald' : ($type === 'warning' ? 'amber' : 'red');
    $icon = $type === 'success' ? 'ph-check-circle' : ($type === 'warning' ? 'ph-warning' : 'ph-x-circle');
    return '<div class="relative flex items-center justify-between px-5 py-4 mb-6 text-sm font-bold text-'.$colors.'-800 bg-'.$colors.'-50 border border-'.$colors.'-200 rounded-2xl animate-fade-in-up dark:bg-'.$colors.'-500/10 dark:text-'.$colors.'-400 dark:border-'.$colors.'-500/20 shadow-sm"><div class="flex items-center gap-3"><i class="ph-fill '.$icon.' text-xl"></i><span>'.$msg.'</span></div><button type="button" class="text-'.$colors.'-600 hover:text-'.$colors.'-800 dark:hover:text-'.$colors.'-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
}

// --- PASSWORD GENERATOR ---
function generate_password($length = 8) {
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, $length);
}

// --- LOGIC 1: DELETE USER ---
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id == $_SESSION['user_id']) {
        $message = tailwindAlert('warning', 'You cannot delete your own account!');
    } else {
        try {
            $stmt_del = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->execute([$delete_id]);
            $message = tailwindAlert('success', 'User successfully deleted.');
        } catch (Exception $e) {
            $message = tailwindAlert('error', 'Failed to delete: ' . $e->getMessage());
        }
    }
}

// --- LOGIC 2: CREATE NEW USER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_role = $_POST['role'] ?? 'user';
    $new_company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    
    if ($new_username && $new_email) {
        try {
            $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$new_username, $new_email]);
            if ($check->rowCount() > 0) {
                $message = tailwindAlert('error', 'Username or Email already exists!');
            } else {
                $generated_password = generate_password();
                $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, email, role, company_id, created_by, force_password_change) VALUES (?, ?, ?, ?, ?, ?, 1)");
                if ($stmt->execute([$new_username, $hashed_password, $new_email, $new_role, $new_company_id, $_SESSION['user_id']])) {
                    if (function_exists('send_credentials_email')) { send_credentials_email($new_email, $new_username, $generated_password); }
                    $message = tailwindAlert('success', 'User <b>'.$new_username.'</b> created successfully! Password sent to email.');
                }
            }
        } catch (Exception $e) { $message = tailwindAlert('error', $e->getMessage()); }
    }
}

// --- LOGIC 3: UPDATE USER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $edit_id = intval($_POST['edit_user_id']);
    $edit_email = trim($_POST['edit_email']);
    $edit_role = $_POST['edit_role'];
    $edit_company_id = !empty($_POST['edit_company_id']) ? $_POST['edit_company_id'] : NULL;
    $should_reset_pass = isset($_POST['reset_password_flag']) && $_POST['reset_password_flag'] == '1';

    try {
        if ($edit_id == $_SESSION['user_id'] && $edit_role != 'admin') {
             $message = tailwindAlert('warning', 'You cannot change your own role to User.');
        } else {
            $sql = "UPDATE users SET email = ?, role = ?, company_id = ? WHERE id = ?";
            $params = [$edit_email, $edit_role, $edit_company_id, $edit_id];
            $pw_msg = "";

            if ($should_reset_pass) {
                $new_auto_pass = generate_password();
                $hashed_pw = password_hash($new_auto_pass, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET email = ?, role = ?, company_id = ?, password = ?, force_password_change = 1 WHERE id = ?";
                $params = [$edit_email, $edit_role, $edit_company_id, $hashed_pw, $edit_id];
                
                $stmt_get_user = $db->prepare("SELECT username FROM users WHERE id = ?");
                $stmt_get_user->execute([$edit_id]);
                $user_data = $stmt_get_user->fetch(PDO::FETCH_ASSOC);
                
                if ($user_data && function_exists('send_credentials_email')) {
                    send_credentials_email($edit_email, $user_data['username'], $new_auto_pass);
                }
                $pw_msg = " & password auto-reset";
            }

            $stmt_update = $db->prepare($sql);
            if ($stmt_update->execute($params)) {
                $message = tailwindAlert('success', 'User data updated' . $pw_msg);
            }
        }
    } catch (Exception $e) { $message = tailwindAlert('error', $e->getMessage()); }
}

// --- GET DATA ---
$stmt_comp = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$companies_list = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);

$sql_users = "SELECT u.*, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id ORDER BY u.created_at DESC";
$all_users = $db->query($sql_users)->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">
            User Access Control
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-shield-check text-lg text-indigo-500"></i> Manage identities, roles, and company-level restrictions.
        </p>
    </div>
    <div class="animate-fade-in-up" style="animation-delay: 0.1s;">
        <button onclick="openAddUserModal()" class="group flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition-all hover:bg-indigo-700 shadow-lg shadow-indigo-500/30 active:scale-95">
            <i class="ph-bold ph-plus"></i> Add New User
        </button>
    </div>
</div>

<?php echo $message; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    
    <div class="lg:col-span-4 space-y-6 animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="rounded-3xl bg-white dark:bg-[#24303F] p-6 shadow-soft border border-slate-100 dark:border-slate-800 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-indigo-50 dark:bg-indigo-500/5 blur-xl"></div>
            <h4 class="text-sm font-black uppercase tracking-widest text-slate-400 mb-6">Access Hierarchy</h4>
            <div class="space-y-4">
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0"><i class="ph-fill ph-crown text-xl"></i></div>
                    <div><p class="text-sm font-bold text-slate-800 dark:text-white">Administrator</p><p class="text-xs text-slate-500">Full system access + Global company scope.</p></div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0"><i class="ph-fill ph-user-focus text-xl"></i></div>
                    <div><p class="text-sm font-bold text-slate-800 dark:text-white">Company User</p><p class="text-xs text-slate-500">Restricted to data from a specific client company.</p></div>
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-purple-700 p-8 text-white shadow-xl shadow-indigo-500/20 relative overflow-hidden group">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-10"></div>
            <i class="ph ph-envelope-simple absolute -right-4 -bottom-4 text-9xl opacity-10 group-hover:rotate-12 transition-transform duration-500"></i>
            <h4 class="text-lg font-black mb-2 relative">Security First</h4>
            <p class="text-sm text-indigo-100 mb-6 relative">New users will receive their auto-generated password via email and will be required to update it upon first login.</p>
            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-2 rounded-xl text-xs font-bold relative">
                <i class="ph-fill ph-shield-check text-emerald-400"></i> Encrypted Storage
            </div>
        </div>
    </div>

    <div class="lg:col-span-8 animate-fade-in-up" style="animation-delay: 0.3s;">
        <div class="rounded-3xl bg-white dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 shadow-soft overflow-hidden">
            <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                <h4 class="text-lg font-bold text-slate-800 dark:text-white">Registered Users</h4>
                <div class="relative w-48">
                    <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="userSearch" class="w-full pl-9 pr-4 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg outline-none focus:ring-2 focus:ring-indigo-500/20" placeholder="Search user...">
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="userTable">
                    <thead>
                        <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-800">
                            <th class="px-8 py-4">User Details</th>
                            <th class="px-6 py-4">Security Level</th>
                            <th class="px-6 py-4">Scope</th>
                            <th class="px-6 py-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                        <?php foreach ($all_users as $u): ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors group">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black border border-indigo-100 dark:border-indigo-800"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800 dark:text-white text-sm"><?= e($u['username']) ?></span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400"><?= e($u['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex flex-col gap-1.5">
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="inline-flex items-center gap-1.5 w-max px-2 py-0.5 rounded-md bg-purple-50 dark:bg-purple-500/10 text-[10px] font-black text-purple-700 dark:text-purple-400 border border-purple-100 dark:border-purple-500/20 uppercase"><i class="ph-fill ph-crown"></i> Administrator</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 w-max px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-black text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 uppercase"><i class="ph-fill ph-user"></i> Basic User</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($u['force_password_change'] == 1): ?>
                                        <span class="text-[9px] font-bold text-amber-500 flex items-center gap-1"><i class="ph-fill ph-lock-key"></i> Needs Reset</span>
                                    <?php else: ?>
                                        <span class="text-[9px] font-bold text-emerald-500 flex items-center gap-1"><i class="ph-fill ph-shield-check"></i> Active & Secure</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <?php if (!empty($u['company_name'])): ?>
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest mb-1">Company Restriction</span>
                                        <span class="text-sm font-bold text-slate-700 dark:text-slate-300"><?= e($u['company_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-800 text-[10px] font-black text-white uppercase tracking-tighter"><i class="ph ph-globe"></i> Global Scope</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center justify-center gap-2">
                                    <button onclick='editUser(<?= json_encode($u) ?>)' class="h-9 w-9 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 text-amber-600 hover:bg-amber-100 transition-all flex items-center justify-center shadow-sm"><i class="ph-fill ph-pencil-simple text-lg"></i></button>
                                    
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete_id=<?= $u['id'] ?>" onclick="return confirm('Delete user <?= $u['username'] ?>?')" class="h-9 w-9 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 text-red-600 hover:bg-red-100 transition-all flex items-center justify-center shadow-sm"><i class="ph-fill ph-trash text-lg"></i></a>
                                    <?php else: ?>
                                        <button class="h-9 w-9 rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 border border-slate-100 dark:border-slate-700 cursor-not-allowed flex items-center justify-center" disabled><i class="ph-fill ph-trash text-lg"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modalAddUser" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-7 py-5 bg-gradient-to-r from-indigo-600 to-indigo-800 text-white">
            <div class="flex items-center gap-3"><i class="ph-bold ph-user-plus text-2xl"></i><h5 class="text-lg font-bold">Register New Account</h5></div>
            <button type="button" class="btn-close-modal text-white opacity-70 hover:opacity-100 transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <form method="POST" action="" class="p-8 space-y-5 bg-slate-50/50 dark:bg-slate-900/50">
            <input type="hidden" name="create_user" value="1">
            
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Username</label>
                    <input type="text" name="username" required placeholder="ex: jhon_doe" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 transition-all shadow-sm dark:text-white">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Email Address</label>
                    <input type="email" name="email" required placeholder="email@company.com" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 transition-all shadow-sm dark:text-white">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Role</label>
                    <select name="role" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 transition-all shadow-sm dark:text-white">
                        <option value="user">User (View Only)</option>
                        <option value="admin">Administrator (Full)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Company Scope</label>
                    <select name="company_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-indigo-500 transition-all shadow-sm dark:text-white">
                        <option value="">-- Global / All --</option>
                        <?php foreach ($companies_list as $comp): ?>
                            <option value="<?= $comp['id'] ?>"><?= e($comp['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-500/5 border border-amber-200 dark:border-amber-500/20 flex gap-3 items-center">
                <i class="ph ph-info text-amber-500 text-2xl"></i>
                <p class="text-xs font-bold text-amber-800 dark:text-amber-400">System will automatically email credentials to the user. First login requires password change.</p>
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <button type="button" class="btn-close-modal px-6 py-3 rounded-2xl text-sm font-bold text-slate-500 hover:bg-slate-200 transition-all border border-slate-200">Cancel</button>
                <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-2xl text-sm font-bold shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 transition-all active:scale-95">Register User</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEditUser" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-amber-500 px-7 py-5 bg-gradient-to-r from-amber-500 to-amber-600 text-white">
            <div class="flex items-center gap-3"><i class="ph-bold ph-pencil-simple text-2xl"></i><h5 class="text-lg font-bold">Edit User Privileges</h5></div>
            <button type="button" class="btn-close-modal text-white opacity-70 hover:opacity-100 transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        <form method="POST" action="" class="p-8 space-y-6 bg-slate-50/50 dark:bg-slate-900/50">
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="edit_user_id" id="edit_user_id">
            
            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Username (Locked)</label>
                    <input type="text" id="edit_username" readonly class="w-full rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 px-4 py-3 text-sm font-black text-slate-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Email Address</label>
                    <input type="email" name="edit_email" id="edit_email" required class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-amber-500 transition-all shadow-sm dark:text-white">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Security Role</label>
                    <select name="edit_role" id="edit_role" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-amber-500 dark:text-white transition-all">
                        <option value="user">User (View Only)</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Assigned Company</label>
                    <select name="edit_company_id" id="edit_company_id" class="w-full rounded-2xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-bold outline-none focus:border-amber-500 dark:text-white transition-all">
                        <option value="">-- Global / All --</option>
                        <?php foreach ($companies_list as $comp): ?>
                            <option value="<?= $comp['id'] ?>"><?= e($comp['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border-2 border-red-100 dark:border-red-900/30 shadow-sm">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <input type="checkbox" name="reset_password_flag" id="reset_password_flag" value="1" class="w-5 h-5 text-red-600 rounded-lg focus:ring-red-500 cursor-pointer">
                    <div class="flex flex-col">
                        <span class="text-sm font-black text-red-600 dark:text-red-400 uppercase tracking-tight group-hover:translate-x-1 transition-transform">Reset Password Security</span>
                        <span class="text-[10px] font-medium text-slate-400">Generate & email a new secure password immediately.</span>
                    </div>
                </label>
            </div>

            <div class="pt-2 flex justify-end gap-3">
                <button type="button" class="btn-close-modal px-6 py-3 rounded-2xl text-sm font-bold text-slate-500 hover:bg-slate-200 transition-all border border-slate-200">Discard</button>
                <button type="submit" class="bg-amber-500 text-white px-8 py-3 rounded-2xl text-sm font-bold shadow-lg shadow-amber-500/30 hover:bg-amber-600 transition-all active:scale-95 flex items-center gap-2"><i class="ph-bold ph-floppy-disk"></i> Update Profile</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
    $(document).ready(function() {
        // Modal Handlers
        window.openAddUserModal = function() {
            $('body').css('overflow', 'hidden');
            $('#modalAddUser').removeClass('hidden').addClass('flex');
        };

        window.editUser = function(u) {
            $('#edit_user_id').val(u.id);
            $('#edit_username').val(u.username);
            $('#edit_email').val(u.email);
            $('#edit_role').val(u.role);
            $('#edit_company_id').val(u.company_id);
            $('#reset_password_flag').prop('checked', false);
            
            $('body').css('overflow', 'hidden');
            $('#modalEditUser').removeClass('hidden').addClass('flex');
        };

        $('.btn-close-modal').click(function() {
            $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
            $('body').css('overflow', 'auto');
        });

        // Search Filter
        $("#userSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#userTable tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>