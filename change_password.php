<?php
/*
 File: change_password.php
 ===========================================================
 Struktur: Dashboard Layout (Standard Mazer)
 Logika: Wajib ganti password saat login pertama/reset.
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

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_pass) || empty($confirm_pass)) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'>Mohon isi kedua kolom password.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'>Konfirmasi password tidak cocok.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } elseif (strlen($new_pass) < 6) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'>Password minimal 6 karakter.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
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
                    alert('Password berhasil diubah! Akun Anda sekarang Aman. Silakan masuk.'); 
                    window.location='index.php';
                </script>";
                exit;
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show'>Gagal update database.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>Error: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}

// ============================================================
// 2. TAMPILAN (HTML) - Menggunakan Layout Dashboard
// ============================================================
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Ganti Password Baru</h3>
                <p class="text-subtitle text-muted">Demi keamanan, Anda <strong>wajib</strong> mengganti password default/reset sebelum melanjutkan.</p>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h4 class="card-title text-primary">Form Ganti Password</h4>
                </div>
                <div class="card-body pt-4">
                    
                    <?php echo $message; ?>

                    <div class="alert alert-light-warning color-warning mb-4">
                        <i class="bi bi-exclamation-triangle"></i> Akses ke menu lain dikunci sampai Anda mengganti password.
                    </div>

                    <form action="" method="POST">
                        <div class="form-group position-relative has-icon-left mb-4">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <div class="position-relative">
                                <input type="password" id="new_password" name="new_password" class="form-control form-control-lg" placeholder="Minimal 6 karakter" required>
                                <div class="form-control-icon">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                            </div>
                        </div>

                        <div class="form-group position-relative has-icon-left mb-4">
                            <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                            <div class="position-relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control form-control-lg" placeholder="Ulangi password baru" required>
                                <div class="form-control-icon">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="logout.php" class="text-danger fw-bold text-decoration-none">
                                <i class="bi bi-box-arrow-left"></i> Batal & Logout
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg shadow px-4">
                                <i class="bi bi-check-circle-fill me-2"></i> Simpan Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>