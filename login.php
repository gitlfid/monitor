<?php
/*
 File: login.php
 ===========================================================
 Deskripsi: Halaman Login Client-Side.
 Metode: Mengirim kredensial ke API (api/auth_login.php) via cURL.
 Keamanan: Menyimpan Access Key & Last Activity untuk validasi sesi ketat.
 Theme: Ultra-Modern Tailwind CSS & Animated
*/

// 1. Mulai Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cek jika sudah login, langsung ke dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// 3. Load Helper
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error_message = '';
$logout_message = '';
$success_message = '';

// 4. Tangkap Pesan Status dari URL
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'logout') {
        $logout_message = 'Anda berhasil logout.';
    } elseif ($_GET['status'] == 'registered') {
        $success_message = 'Registrasi berhasil! Silakan login.';
    }
}

// Tangkap Pesan Error dari Force Logout (auth_check.php)
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

// 5. Proses Submit Form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password wajib diisi!';
    } else {
        // --- MULAI REQUEST KE API ---
        
        // A. Tentukan URL API Secara Dinamis (Localhost/Domain aman)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $current_dir = dirname($_SERVER['PHP_SELF']);
        // Hapus trailing slash jika ada
        $current_dir = rtrim($current_dir, '/\\');
        $api_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $current_dir . "/api/auth_login.php";
        
        // B. Siapkan Data JSON
        $payload = json_encode([
            'username' => $username,
            'password' => $password
        ]);

        // C. Setup cURL
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        
        // Abaikan SSL verify (Hanya untuk development/localhost, aman dihapus jika production pakai HTTPS valid)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // D. Eksekusi & Tangkap Respon
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $error_message = 'Gagal terhubung ke API Login. Server Error.';
        } else {
            $result = json_decode($response, true);

            // E. Proses Hasil API
            if (isset($result['status']) && $result['status'] === true) {
                // === LOGIN SUKSES ===
                $data = $result['data'];

                // 1. Set Session Dasar
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $data['user_id'];
                $_SESSION['username'] = $data['username'];
                $_SESSION['role'] = $data['role'];
                $_SESSION['company_id'] = $data['company_id'];

                // 2. SECURITY: Simpan Access Key (Kunci Sesi)
                $_SESSION['access_key'] = $data['access_key'];

                // 3. SECURITY: Set Waktu Aktivitas (Idle Timeout)
                $_SESSION['last_activity'] = time();

                // 4. Cek Wajib Ganti Password
                if (isset($data['force_pass']) && $data['force_pass'] == 1) {
                    $_SESSION['require_password_change'] = true;
                    header('Location: change_password.php');
                    exit;
                }

                // 5. Redirect ke Dashboard
                header('Location: index.php');
                exit;

            } else {
                // Login Gagal
                $error_message = $result['message'] ?? 'Username atau password salah.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login - LinksField Monitoring</title>
    
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
        
        /* Auto-fill fix for webkit */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #1e293b !important;
        }
    </style>
</head>

<body class="min-h-screen flex bg-slate-50">

    <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:flex-none lg:w-1/2 xl:w-5/12 bg-white relative z-10 shadow-2xl">
        <div class="mx-auto w-full max-w-sm lg:max-w-md animate-fade-in-up">
            
            <div class="mb-10">
                <div class="inline-flex items-center justify-center h-12 w-12 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-500/30 mb-4">
                    <i class="ph-bold ph-shield-check text-2xl"></i>
                </div>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">
                    Welcome back.
                </h2>
                <p class="mt-2 text-sm font-medium text-slate-500">
                    Enter your credentials to access the <span class="text-indigo-600 font-bold">LinksField Datapool</span>.
                </p>
            </div>

            <?php if ($error_message): ?>
                <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-red-50 border border-red-100 text-red-800 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <i class="ph-fill ph-warning-circle text-2xl text-red-500 mt-0.5"></i>
                    <div>
                        <h5 class="text-sm font-bold">Authentication Failed</h5>
                        <p class="text-xs mt-1 text-red-600"><?php echo $error_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($logout_message || $success_message): ?>
                <div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-800 animate-fade-in-up" style="animation-delay: 0.1s;">
                    <i class="ph-fill ph-check-circle text-2xl text-emerald-500 mt-0.5"></i>
                    <div>
                        <h5 class="text-sm font-bold">Success</h5>
                        <p class="text-xs mt-1 text-emerald-600"><?php echo $logout_message ? $logout_message : $success_message; ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-8 animate-fade-in-up" style="animation-delay: 0.2s;">
                <form action="" method="POST" class="space-y-5">
                    
                    <div>
                        <label for="username" class="block text-[11px] font-black uppercase tracking-widest text-slate-500 mb-2 ml-1">Username</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph-fill ph-user text-slate-400 group-focus-within:text-indigo-500 text-xl transition-colors"></i>
                            </div>
                            <input id="username" name="username" type="text" required class="block w-full pl-12 pr-4 py-4 border border-slate-200 rounded-2xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all font-bold text-sm" placeholder="Enter your username">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2 ml-1 mr-1">
                            <label for="password" class="block text-[11px] font-black uppercase tracking-widest text-slate-500">Password</label>
                            <a href="#" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-500 transition-colors">Forgot password?</a>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph-fill ph-lock-key text-slate-400 group-focus-within:text-indigo-500 text-xl transition-colors"></i>
                            </div>
                            <input id="password" name="password" type="password" required class="block w-full pl-12 pr-12 py-4 border border-slate-200 rounded-2xl text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 bg-slate-50 focus:bg-white transition-all font-bold text-sm tracking-widest" placeholder="••••••••">
                            
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-slate-400 hover:text-indigo-600 transition-colors" onclick="const p=document.getElementById('password'); const i=this.querySelector('i'); if(p.type==='password'){p.type='text'; i.classList.replace('ph-eye','ph-eye-slash');}else{p.type='password'; i.classList.replace('ph-eye-slash','ph-eye');}">
                                <i class="ph-bold ph-eye text-lg"></i>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full flex justify-center items-center gap-2 py-4 px-4 border border-transparent rounded-2xl shadow-lg shadow-indigo-500/30 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 active:scale-95 transition-all group">
                            <span>Sign in securely</span>
                            <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </form>
                
                <div class="mt-10 pt-6 border-t border-slate-100 flex flex-col items-center justify-center gap-2">
                    <p class="text-xs font-medium text-slate-400 text-center">
                        Secured by <strong class="text-slate-600">LinksField End-to-End Encryption</strong>. <br>Authorized personnel only.
                    </p>
                </div>
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
                        <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center text-white shadow-lg">
                            <i class="ph-fill ph-chart-line-up text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Global Monitoring</h3>
                            <p class="text-sm text-indigo-200">Real-time SIM Lifecycle Status</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="h-4 bg-white/20 rounded-full w-3/4"></div>
                        <div class="h-4 bg-white/10 rounded-full w-1/2"></div>
                        <div class="flex gap-4 pt-4">
                            <div class="h-20 w-full bg-white/10 rounded-2xl border border-white/5"></div>
                            <div class="h-20 w-full bg-white/10 rounded-2xl border border-white/5"></div>
                            <div class="h-20 w-full bg-indigo-500/50 rounded-2xl border border-indigo-400/30"></div>
                        </div>
                    </div>
                </div>

                <div class="absolute -right-12 top-10 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 shadow-xl z-30 animate-fade-in-up" style="animation-delay: 0.6s;">
                    <div class="flex items-center gap-3">
                        <i class="ph-fill ph-shield-check text-emerald-400 text-3xl"></i>
                        <div>
                            <p class="text-xs font-bold text-emerald-100 uppercase tracking-widest">Security</p>
                            <p class="text-sm font-black text-white">100% Encrypted</p>
                        </div>
                    </div>
                </div>

                <div class="absolute -left-8 -bottom-6 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 shadow-xl z-30 animate-fade-in-up" style="animation-delay: 0.8s;">
                    <div class="flex items-center gap-3">
                        <i class="ph-fill ph-lightning text-amber-400 text-3xl"></i>
                        <div>
                            <p class="text-xs font-bold text-amber-100 uppercase tracking-widest">API Engine</p>
                            <p class="text-sm font-black text-white">Zero Latency</p>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div class="mt-16 text-center animate-fade-in-up" style="animation-delay: 1s;">
                <h2 class="text-3xl font-extrabold text-white mb-4 tracking-tight">Empower Your Connectivity</h2>
                <p class="text-indigo-200 text-base max-w-md mx-auto leading-relaxed">The ultimate Datapool Management System designed to seamlessly monitor, activate, and manage your global IoT network.</p>
            </div>
            
        </div>
    </div>
    
</div>

</body>
</html>