<?php
/*
 File: change_password.php
 ===========================================================
 Deskripsi: Halaman Wajib Ganti Password (Force Password Change)
 Theme: Ultra-Modern Tailwind CSS (Identik dengan login.php)
*/

// ============================================================
// 1. LOGIKA PHP (Backend)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Jika belum login, tendang ke login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Jika user TIDAK diwajibkan ganti password, lempar ke dashboard
// (Agar user yang sudah aman tidak bisa iseng buka halaman ini)
if (!isset($_SESSION['require_password_change']) || $_SESSION['require_password_change'] !== true) {
    header('Location: index.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_pass) || empty($confirm_pass)) {
        $error_message = "Please fill in both password fields.";
    } elseif ($new_pass !== $confirm_pass) {
        $error_message = "Password confirmation does not match.";
    } elseif (strlen($new_pass) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        try {
            $db = db_connect();
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            
            // === UPDATE PENTING ===
            // Update password baru DAN set force_password_change = 0
            $stmt = $db->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
            
            if ($stmt->execute([$hashed, $_SESSION['user_id']])) {
                
                // Hapus session 'paksaan' agar user bisa akses menu lain
                unset($_SESSION['require_password_change']);
                
                // Redirect ke dashboard dengan pesan sukses via JS
                echo "<script>
                    alert('Password successfully updated! Your account is now secure. Proceeding to Dashboard...'); 
                    window.location='index.php';
                </script>";
                exit;
            } else {
                $error_message = "Failed to update database. Please try again.";
            }
        } catch (Exception $e) {
            $error_message = "System Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Security - LinksField Monitoring</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #ffffff; }
        
        /* Custom Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>

<body class="min-h-screen flex bg-slate-50">

    <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:w-1/2 xl:w-5/12 bg-white relative z-10 shadow-2xl">
        <div class="mx-auto w-full max-w-sm lg:max-w-md animate-fade-in-up">
            
            <div class="mb-8">
                <div class="inline-flex items-center justify-center h-12 w-12 rounded-2xl bg-amber-500 text-white shadow-lg shadow-amber-500/30 mb-4">
                    <i class="ph-bold ph-shield-warning text-2xl"></i>
                </div>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">
                    Security Update Required.
                </h2>
                <p class="mt-2 text-sm font-medium text-slate-500 leading-relaxed">
                    For your protection, you are required to set a new, secure password before accessing the <span class="text-indigo-600 font-bold">LinksField System</span>.
                </p>
            </div>

            <?php if ($error_message): ?>
                <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-100 text-red-800 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <i class="ph-fill ph-warning-circle text-2xl text-red-500 mt-0.5"></i>
                    <div>
                        <h5 class="text-sm font-bold">Update Failed</h5>
                        <p class="text-xs mt-1 text-red-600"><?php echo $error_message; ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-100 text-amber-800 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <i class="ph-fill ph-lock-key text-2xl text-amber-500 mt-0.5"></i>
                    <div>
                        <h5 class="text-sm font-bold">Action Required</h5>
                        <p class="text-xs mt-1 text-amber-700">Your dashboard access is locked until a new password is set.</p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-6 animate-fade-in-up" style="animation-delay: 0.2s;">
                <form action="" method="POST" class="space-y-5">
                    
                    <div>
                        <label for="new_password" class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">New Password</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph-fill ph-key text-slate-400 group-focus-within:text-indigo-500 text-xl transition-colors"></i>
                            </div>
                            <input id="new_password" name="new_password" type="password" required class="block w-full pl-12 pr-12 py-4 border border-slate-200 rounded-2xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all font-bold text-sm tracking-widest" placeholder="Min. 6 characters">
                            
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-slate-400 hover:text-indigo-600 transition-colors" onclick="toggleSecret('new_password')">
                                <i id="icon_new_password" class="ph-bold ph-eye text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">Confirm Password</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph-fill ph-shield-check text-slate-400 group-focus-within:text-indigo-500 text-xl transition-colors"></i>
                            </div>
                            <input id="confirm_password" name="confirm_password" type="password" required class="block w-full pl-12 pr-12 py-4 border border-slate-200 rounded-2xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all font-bold text-sm tracking-widest" placeholder="Repeat new password">
                            
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-slate-400 hover:text-indigo-600 transition-colors" onclick="toggleSecret('confirm_password')">
                                <i id="icon_confirm_password" class="ph-bold ph-eye text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 flex flex-col gap-3">
                        <button type="submit" class="w-full flex justify-center items-center gap-2 py-4 px-4 border border-transparent rounded-2xl shadow-lg shadow-indigo-500/30 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 active:scale-95 transition-all group">
                            <span>Save Secure Password</span>
                            <i class="ph-bold ph-check-circle group-hover:scale-110 transition-transform"></i>
                        </button>
                        
                        <a href="logout.php" class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-slate-200 rounded-2xl text-sm font-bold text-slate-500 hover:bg-slate-100 hover:text-red-500 transition-all">
                            <i class="ph-bold ph-sign-out"></i> Cancel & Logout
                        </a>
                    </div>
                </form>
            </div>
            
        </div>
    </div>

    <div class="hidden lg:block relative w-0 flex-1 bg-slate-900 overflow-hidden">
        
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-900 via-slate-900 to-purple-900 z-0"></div>
        <div class="absolute inset-0 z-0 opacity-40 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')]"></div>
        
        <div class="absolute top-1/4 -left-12 w-96 h-96 bg-purple-500 rounded-full mix-blend-screen filter blur-[100px] opacity-50 animate-blob z-0"></div>
        <div class="absolute top-1/3 right-12 w-96 h-96 bg-indigo-500 rounded-full mix-blend-screen filter blur-[100px] opacity-50 animate-blob animation-delay-2000 z-0"></div>
        <div class="absolute -bottom-20 left-1/3 w-96 h-96 bg-cyan-600 rounded-full mix-blend-screen filter blur-[100px] opacity-50 animate-blob animation-delay-4000 z-0"></div>

        <div class="absolute inset-0 flex flex-col justify-center items-center p-12 z-10 pointer-events-none">
            
            <div class="relative w-full max-w-lg animate-fade-in-up" style="animation-delay: 0.4s;">
                
                <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl p-8 shadow-2xl relative z-20">
                    <div class="flex items-center gap-4 border-b border-white/10 pb-6 mb-6">
                        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white shadow-lg">
                            <i class="ph-fill ph-lock-key text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Fortified Security</h3>
                            <p class="text-sm text-indigo-200">Mandatory Credential Refresh</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="h-4 bg-white/20 rounded-full w-3/4"></div>
                        <div class="h-4 bg-white/10 rounded-full w-1/2"></div>
                        <div class="flex gap-4 pt-4">
                            <div class="h-20 w-full bg-white/10 rounded-2xl border border-white/5 flex items-center justify-center"><i class="ph-fill ph-shield-check text-3xl text-emerald-400 opacity-50"></i></div>
                            <div class="h-20 w-full bg-white/10 rounded-2xl border border-white/5 flex items-center justify-center"><i class="ph-fill ph-fingerprint text-3xl text-blue-400 opacity-50"></i></div>
                        </div>
                    </div>
                </div>

                <div class="absolute -right-12 top-10 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 shadow-xl z-30 animate-fade-in-up" style="animation-delay: 0.6s;">
                    <div class="flex items-center gap-3">
                        <i class="ph-fill ph-check-circle text-emerald-400 text-3xl"></i>
                        <div>
                            <p class="text-xs font-bold text-emerald-100 uppercase tracking-widest">Protocol</p>
                            <p class="text-sm font-black text-white">100% Secured</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-16 text-center animate-fade-in-up" style="animation-delay: 1s;">
                <h2 class="text-3xl font-extrabold text-white mb-4 tracking-tight">Protect Your Access</h2>
                <p class="text-indigo-200 text-base max-w-md mx-auto leading-relaxed">Updating your password ensures that only you have access to critical Datapool management functions.</p>
            </div>
            
        </div>
    </div>
    
</div>

<script>
    // UX Enhancement: Toggle Password Visibility
    function toggleSecret(id) {
        var input = document.getElementById(id);
        var icon = document.getElementById("icon_" + id);
        
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace('ph-eye', 'ph-eye-slash');
        } else {
            input.type = "password";
            icon.classList.replace('ph-eye-slash', 'ph-eye');
        }
    }
</script>

</body>
</html>