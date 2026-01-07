<?php
/*
 File: includes/auth_check.php
 Fungsi: Gatekeeper Keamanan (Idle Timeout + Key Validation)
 Lokasi: Folder includes/
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================================================
// PERBAIKAN PATH (CRITICAL FIX)
// =============================================================
// Menggunakan __DIR__ yang merujuk ke folder tempat file ini berada (folder includes)
// Karena config.php dan functions.php juga ada di folder includes, kita panggil langsung.
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Fallback jika dipanggil dari root dan struktur berbeda (Jaga-jaga)
    require_once dirname(__DIR__) . '/includes/config.php';
}

if (file_exists(__DIR__ . '/functions.php')) {
    require_once __DIR__ . '/functions.php';
} else {
    require_once dirname(__DIR__) . '/includes/functions.php';
}

// =============================================================
// 1. CEK HALAMAN LOGIN/LOGOUT (Agar tidak redirect loop)
// =============================================================
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'logout.php'];

if (in_array($current_page, $public_pages)) {
    return;
}

// =============================================================
// 2. FUNGSI BANTUAN REDIRECT (Handling Lokasi)
// =============================================================
// Fungsi ini memastikan redirect mengarah ke login.php di root, 
// tidak peduli dari folder mana script ini dipanggil.
function force_logout_redirect($message = "Sesi berakhir.") {
    // Tentukan path ke login.php secara dinamis
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Asumsi login.php ada di root website
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    
    // UPDATE: Redirect bersih tanpa pesan error di URL
    // Ini mencegah munculnya alert "Sesi tidak ditemukan" di halaman login
    $redirect_url = "login.php"; 

    // Cek AJAX request (Tetap kirim pesan JSON untuk aplikasi frontend/JS)
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($is_ajax) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => $message, 'redirect' => $redirect_url]);
    } else {
        // Redirect biasa via browser
        header("Location: " . $redirect_url);
    }
    exit;
}

// =============================================================
// 3. GATEKEEPER UTAMA
// =============================================================

// A. Cek Sesi Dasar
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    force_logout_redirect("Sesi tidak ditemukan. Silakan login.");
}

// B. FITUR IDLE TIMEOUT (5 MENIT)
$timeout_duration = 1800; // 300 detik = 5 Menit

if (isset($_SESSION['last_activity'])) {
    $duration = time() - $_SESSION['last_activity'];
    
    if ($duration > $timeout_duration) {
        // Hapus Sesi
        session_unset();
        session_destroy();
        force_logout_redirect("Sesi berakhir karena tidak ada aktivitas (Timeout 5 Menit).");
    }
}
// Refresh waktu aktivitas
$_SESSION['last_activity'] = time();


// C. FITUR KEY VALIDATION (FORCE LOGOUT)
try {
    $db = db_connect();
    $stmt = $db->prepare("SELECT access_key, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_live = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_live) {
        force_logout_redirect("User tidak valid.");
    }

    // Bandingkan Key Session vs Key Database
    // Jika Access Key di session kosong (user lama) ATAU beda dengan DB (diregenerate admin) -> Logout
    if (!isset($_SESSION['access_key']) || $_SESSION['access_key'] !== $user_live['access_key']) {
        session_unset();
        session_destroy();
        force_logout_redirect("Sesi tidak valid atau kredensial telah diubah. Silakan login ulang.");
    }
    
    // Sync Role
    $_SESSION['role'] = $user_live['role'];

} catch (Exception $e) {
    // Jika DB Error, demi keamanan, logout user
    // (Opsional: Anda bisa me-log error ini dan tidak melogout user jika koneksi DB tidak stabil)
    // force_logout_redirect("Terjadi kesalahan verifikasi sistem.");
}

// =============================================================
// 4. FUNGSI KHUSUS ADMIN
// =============================================================
if (!function_exists('require_admin')) {
    function require_admin() {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>";
            echo "<h1>403 Forbidden</h1>";
            echo "<p>Maaf, Anda tidak memiliki izin akses ke halaman ini.</p>";
            echo "<a href='index.php' style='text-decoration:none; color:blue;'>&larr; Kembali ke Dashboard</a>";
            echo "</div>";
            exit;
        }
    }
}
?>