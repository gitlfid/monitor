<?php
/*
 File: test_email.php
 ===========================================================
 Tools untuk diagnosa pengiriman email.
 Struktur sudah disesuaikan dengan layout standar.
*/

// ============================================================
// 1. LOGIKA PHP (Backend)
// ============================================================

// Debugging Error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load Auth & Config
require_once 'includes/auth_check.php'; 
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Cek File Mail Helper
if (file_exists('includes/mail_helper.php')) {
    require_once 'includes/mail_helper.php';
} else {
    die("Error: File includes/mail_helper.php tidak ditemukan.");
}

// Load Email Config untuk Info
if (file_exists('includes/email_config.php')) {
    include_once 'includes/email_config.php';
}

// Pastikan hanya Admin yang bisa akses
require_admin();

$message = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_email = $_POST['email'] ?? '';
    
    if (!empty($target_email)) {
        $subject = "Test Konfigurasi Email - LinksField Monitoring";
        $body = "Halo Admin,\n\n";
        $body .= "Jika Anda menerima email ini, berarti konfigurasi email server (SMTP/Mail) di platform LinksField sudah berfungsi dengan BAIK.\n\n";
        $body .= "Waktu Kirim: " . date('d F Y, H:i:s') . "\n";
        $body .= "Dikirim oleh: " . $_SESSION['username'] . "\n\n";
        $body .= "Salam,\nSistem LinksField";

        // Cek fungsi pengiriman mana yang tersedia
        $status_kirim = false;
        
        // Prioritaskan fungsi dari mail_helper.php
        if (function_exists('send_generic_email')) {
            $status_kirim = send_generic_email($target_email, $subject, $body);
        } else {
            // Fallback ke mail() bawaan PHP
            $headers = "From: Admin <no-reply@linksfield.id>";
            $status_kirim = mail($target_email, $subject, $body, $headers);
        }

        if ($status_kirim) {
            $message = "<div class='alert alert-success alert-dismissible fade show'>
                            <i class='bi bi-check-circle'></i> Email berhasil dikirim ke <strong>$target_email</strong>.<br>
                            Silakan cek Inbox atau folder Spam Anda.
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>
                            <i class='bi bi-exclamation-triangle'></i> Gagal mengirim email.<br>
                            Periksa konfigurasi SMTP Anda atau Log Server.
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        }
    } else {
        $message = "<div class='alert alert-warning alert-dismissible fade show'>Harap masukkan email tujuan.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }
}

// ============================================================
// 2. TAMPILAN (HTML)
// ============================================================
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Test Email Configuration</h3>
                <p class="text-subtitle text-muted">Alat diagnosa untuk memastikan fitur pengiriman email berjalan lancar.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Test Email</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <section class="section">
        <div class="row">
            <div class="col-12 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Kirim Test Email</h4>
                    </div>
                    <div class="card-body">
                        <?php echo $message; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email Tujuan</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required>
                                </div>
                                <small class="text-muted">Masukkan email pribadi Anda untuk mengetes.</small>
                            </div>
                            
                            <div class="form-group mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-1 mb-1">
                                    <i class="bi bi-send-fill"></i> Kirim Sekarang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Konfigurasi Terdeteksi</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-secondary color-secondary">
                            <i class="bi bi-info-circle"></i> Data ini diambil dari file <code>includes/email_config.php</code>.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <tbody>
                                    <tr>
                                        <td class="fw-bold">SMTP Host</td>
                                        <td><?php echo defined('SMTP_HOST') ? SMTP_HOST : '<span class="text-danger">Not Set</span>'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">SMTP Port</td>
                                        <td><?php echo defined('SMTP_PORT') ? SMTP_PORT : '<span class="text-danger">Not Set</span>'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">SMTP User</td>
                                        <td>
                                            <?php 
                                            if (defined('SMTP_USER')) {
                                                // Sensor email agar aman
                                                $parts = explode('@', SMTP_USER);
                                                echo substr($parts[0], 0, 3) . '***@' . ($parts[1] ?? '...');
                                            } else {
                                                echo '<span class="text-danger">Not Set</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Sender Name</td>
                                        <td><?php echo defined('FROM_NAME') ? FROM_NAME : 'Default'; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?>