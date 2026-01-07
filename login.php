<?php
/*
 File: login.php
 ===========================================================
 Deskripsi: Halaman Login Client-Side.
 Metode: Mengirim kredensial ke API (api/auth_login.php) via cURL.
 Keamanan: Menyimpan Access Key & Last Activity untuk validasi sesi ketat.
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
                // Ini yang dicek oleh auth_check.php. Jika Admin meregenerate key di DB,
                // key di session ini akan berbeda dengan DB -> User terlogout otomatis.
                $_SESSION['access_key'] = $data['access_key'];

                // 3. SECURITY: Set Waktu Aktivitas (Idle Timeout)
                // Ini titik awal waktu. auth_check.php akan membandingkan waktu sekarang dengan ini.
                // Jika selisih > 5 menit tanpa aktivitas -> User terlogout otomatis.
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Monitoring Datapool</title>
    
    <link rel="stylesheet" href="assets/compiled/css/app.css">
    <link rel="stylesheet" href="assets/compiled/css/app-dark.css">
    <link rel="stylesheet" href="assets/compiled/css/auth.css">
    <link rel="stylesheet" href="assets/extensions/@fortawesome/fontawesome-free/css/all.min.css">
</head>

<body>
    <div id="auth">
        <div class="row h-100">
            <div class="col-lg-5 col-12">
                <div id="auth-left">
                    <!-- Logo / Brand -->
                    <div class="auth-logo mb-5">
                        <a href="index.php">
                            <h3>LinksField</h3>
                        </a>
                    </div>
                    
                    <h1 class="auth-title">Log in.</h1>
                    <p class="auth-subtitle mb-5">Log in with your data that you entered during registration.</p>

                    <!-- Tampilkan Pesan Error/Sukses -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible show fade">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($logout_message): ?>
                        <div class="alert alert-success alert-dismissible show fade">
                            <?php echo $logout_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible show fade">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="text" name="username" class="form-control form-control-xl" placeholder="Username" required>
                            <div class="form-control-icon">
                                <i class="bi bi-person"></i>
                            </div>
                        </div>
                        <div class="form-group position-relative has-icon-left mb-4">
                            <input type="password" name="password" class="form-control form-control-xl" placeholder="Password" required>
                            <div class="form-control-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block btn-lg shadow-lg mt-5">Log in</button>
                    </form>
                </div>
            </div>
            
            <!-- Bagian Kanan (Gambar Background) - Tetap Kosong sesuai layout asli -->
            <div class="col-lg-7 d-none d-lg-block">
                <div id="auth-right">
                    <!-- Kosong, biarkan CSS yang mengatur background image-nya -->
                </div>
            </div>
        </div>
    </div>
</body>
</html>