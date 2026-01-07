<?php
require_once 'email_config.php';

/**
 * Fungsi Generic untuk mengirim email sederhana
 */
function send_generic_email($to, $subject, $message) {
    // Headers standar untuk mail() bawaan PHP
    // Jika nanti Anda menggunakan PHPMailer, ubah logika di dalam fungsi ini saja.
    $headers = "From: " . FROM_NAME . " <" . SMTP_USER . ">\r\n";
    $headers .= "Reply-To: " . SMTP_USER . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Coba kirim
    return mail($to, $subject, $message, $headers);
}

/**
 * Fungsi Khusus untuk mengirim kredensial user baru
 */
function send_credentials_email($to_email, $username, $password_plain) {
    $subject = "LinksField Monitoring Account";
    
    $message = "Hello,\n\n";
    $message .= "Your account has been created by an Administrator. Here are your login details:\n\n";
    // Mendeteksi URL website otomatis
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php";
    
    $message .= "URL Login: " . $url . "\n";
    $message .= "Username: " . $username . "\n";
    $message .= "Password: " . $password_plain . "\n\n";
    $message .= "Please change your password immediately after logging in.\n";
    $message .= "\nRegards,\nLinksField";

    return send_generic_email($to_email, $subject, $message);
}
?>