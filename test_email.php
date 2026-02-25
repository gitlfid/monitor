<?php
/*
 File: test_email.php
 DESC: Email Diagnostic Tool (PHPMailer via Composer)
 THEME: Ultra-Modern Tailwind CSS
*/

// ============================================================
// 1. SYSTEM SETUP & AUTOLOAD
// ============================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

// [FIX] Gunakan Absolute Path untuk Autoloader Composer
$autoload_path = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // Fallback UI jika vendor tidak ditemukan
    die('
    <div style="padding: 30px; font-family: sans-serif; text-align: center; color: #333;">
        <h2 style="color: #ef4444;">Error: Composer Vendor Not Found</h2>
        <p>File <code>vendor/autoload.php</code> tidak ditemukan di: <br><code>'.$autoload_path.'</code></p>
        <p>Silakan jalankan perintah ini di terminal root project Anda:</p>
        <pre style="background: #f1f5f9; padding: 15px; border-radius: 8px; display: inline-block;">composer require phpmailer/phpmailer</pre>
    </div>');
}

// [FIX] Import Namespace PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 2. Load Application Core
require_once 'includes/auth_check.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure Admin Access
require_admin();

$db = db_connect();
$message = '';

// ============================================================
// 2. BACKEND LOGIC
// ============================================================

// Helper: Tailwind Alert
function tailwindAlert($type, $msg) {
    $colors = $type === 'success' ? 'emerald' : ($type === 'error' ? 'red' : 'amber');
    $icon = $type === 'success' ? 'ph-check-circle' : ($type === 'error' ? 'ph-warning-circle' : 'ph-info');
    return '<div class="relative flex items-center justify-between px-5 py-4 mb-6 text-sm font-bold text-'.$colors.'-800 bg-'.$colors.'-50 border border-'.$colors.'-200 rounded-2xl animate-fade-in-up dark:bg-'.$colors.'-500/10 dark:text-'.$colors.'-400 dark:border-'.$colors.'-500/20 shadow-sm"><div class="flex items-center gap-3"><i class="ph-fill '.$icon.' text-xl"></i><span>'.$msg.'</span></div><button type="button" class="text-'.$colors.'-600 hover:text-'.$colors.'-800 dark:hover:text-'.$colors.'-300 transition-colors" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
}

// Fetch SMTP Config from Database
function get_db_smtp_config($db) {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM app_config WHERE setting_key LIKE 'smtp_%'");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) { return []; }
}

$smtp_conf = get_db_smtp_config($db);

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = trim($_POST['email'] ?? '');
    
    if (empty($target)) {
        $message = tailwindAlert('warning', 'Please enter a valid target email address.');
    } elseif (empty($smtp_conf)) {
        $message = tailwindAlert('error', 'SMTP Configuration missing in database (Table: app_config).');
    } else {
        $mail = new PHPMailer(true); // Enable exceptions

        try {
            // Server Settings
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Uncomment untuk debug detail di layar
            $mail->isSMTP();
            $mail->Host       = $smtp_conf['smtp_host'] ?? '';
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_conf['smtp_user'] ?? '';
            $mail->Password   = $smtp_conf['smtp_pass'] ?? '';
            $mail->SMTPSecure = $smtp_conf['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_conf['smtp_port'] ?? 587;

            // [FIX BYPASS SSL] 
            // Bypass verifikasi sertifikat SSL untuk mengatasi error "Peer certificate CN mismatch"
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $fromName = $smtp_conf['smtp_from_name'] ?? 'System Admin';
            $mail->setFrom($smtp_conf['smtp_user'], $fromName);
            $mail->addAddress($target);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Test Email Connection [Composer]';
            $bodyContent = '
                <div style="font-family:sans-serif; max-width:600px; padding:20px; border:1px solid #e2e8f0; border-radius:12px; background-color:#ffffff;">
                    <h2 style="color:#4f46e5; margin-top:0;">Connection Successful!</h2>
                    <p style="color:#334155; font-size:14px; line-height:1.6;">
                        This email confirms that <strong>PHPMailer (Composer Version)</strong> is installed correctly and your database SMTP configuration is working.
                    </p>
                    <div style="background-color:#f8fafc; padding:15px; border-radius:8px; margin:20px 0;">
                        <ul style="margin:0; padding-left:20px; color:#475569; font-size:13px;">
                            <li><strong>Host:</strong> '.$mail->Host.'</li>
                            <li><strong>Port:</strong> '.$mail->Port.'</li>
                            <li><strong>Auth User:</strong> '.$mail->Username.'</li>
                        </ul>
                    </div>
                    <hr style="border:none; border-top:1px solid #e2e8f0; margin:20px 0;">
                    <p style="color:#94a3b8; font-size:11px; margin-bottom:0;">
                        Sent by: '.($_SESSION['username'] ?? 'System').' <br>
                        Timestamp: '.date('Y-m-d H:i:s').'
                    </p>
                </div>';
            
            $mail->Body    = $bodyContent;
            $mail->AltBody = strip_tags($bodyContent);

            $mail->send();
            $message = tailwindAlert('success', "Test email successfully sent to <strong>$target</strong>.");
        } catch (Exception $e) {
            $message = tailwindAlert('error', "Message could not be sent. <br><strong>Error:</strong> {$mail->ErrorInfo}");
        }
    }
}

// ============================================================
// 3. UI VIEW (Frontend)
// ============================================================
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    .glass-effect { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    .dark .glass-effect { background: rgba(30, 41, 59, 0.9); }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-cyan-500 dark:from-indigo-400 dark:to-cyan-400 tracking-tight">
            System Diagnostics
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-stethoscope text-lg text-indigo-500"></i> Verify PHPMailer Composer & Database Config.
        </p>
    </div>
</div>

<?php echo $message; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

    <div class="lg:col-span-7 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden flex flex-col h-full">
            <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                <h4 class="text-lg font-bold flex items-center gap-2">
                    <i class="ph-bold ph-paper-plane-tilt text-2xl"></i> Dispatch Test Email
                </h4>
                <p class="text-indigo-100 text-xs mt-1">Send a diagnostic email using Database Config.</p>
            </div>
            
            <div class="p-8 flex-1">
                <form method="POST" action="">
                    <div class="mb-6">
                        <label for="email" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Target Recipient</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="ph-fill ph-envelope-simple text-slate-400 group-focus-within:text-indigo-500 text-xl transition-colors"></i>
                            </div>
                            <input type="email" id="email" name="email" class="block w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all font-medium" placeholder="your.email@example.com" required>
                        </div>
                        <p class="mt-3 text-xs text-slate-400 flex items-center gap-1.5">
                            <i class="ph-fill ph-info text-indigo-500"></i> Enter your personal email to receive the test message.
                        </p>
                    </div>
                    
                    <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="submit" class="group relative inline-flex items-center justify-center gap-2 px-8 py-3.5 text-sm font-bold text-white transition-all bg-indigo-600 rounded-xl hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-500/30 active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            <span>Send Diagnostic Email</span>
                            <i class="ph-bold ph-paper-plane-right group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="lg:col-span-5 animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden h-full">
            <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                <h4 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-database text-indigo-500"></i> DB Configuration
                </h4>
                <span class="px-2.5 py-1 rounded-lg bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest">Active</span>
            </div>
            
            <div class="p-8">
                <div class="rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20 p-4 mb-6 flex gap-3">
                    <i class="ph-fill ph-info text-indigo-500 text-2xl shrink-0"></i>
                    <div>
                        <p class="text-xs font-bold text-indigo-800 dark:text-indigo-300">Configuration Source</p>
                        <p class="text-[11px] text-indigo-600 dark:text-indigo-400 mt-0.5">Loaded from table <code class="bg-white/50 dark:bg-black/20 px-1 rounded font-mono">app_config</code></p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400"><i class="ph-bold ph-globe"></i></div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">SMTP Host</p>
                                <p class="text-sm font-bold text-slate-700 dark:text-white"><?php echo $smtp_conf['smtp_host'] ?? '<span class="text-red-500">Not Set</span>'; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400"><i class="ph-bold ph-plugs"></i></div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">SMTP Port</p>
                                <p class="text-sm font-bold text-slate-700 dark:text-white"><?php echo $smtp_conf['smtp_port'] ?? '<span class="text-red-500">Not Set</span>'; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400"><i class="ph-bold ph-user-gear"></i></div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">SMTP User</p>
                                <p class="text-sm font-bold text-slate-700 dark:text-white">
                                    <?php 
                                    if (!empty($smtp_conf['smtp_user'])) {
                                        $parts = explode('@', $smtp_conf['smtp_user']);
                                        echo '<span class="font-mono text-xs">' . substr($parts[0], 0, 3) . '***@' . ($parts[1] ?? '...') . '</span>';
                                    } else {
                                        echo '<span class="text-red-500 text-xs">Not Set</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400"><i class="ph-bold ph-identification-card"></i></div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sender Name</p>
                                <p class="text-sm font-bold text-slate-700 dark:text-white"><?php echo $smtp_conf['smtp_from_name'] ?? 'Default'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>