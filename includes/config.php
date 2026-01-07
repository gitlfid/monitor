<?php
// Aktifkan pelaporan error untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// 1. Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'linksfie_datapoolnew');
define('DB_PASS', 'Kumisan5'); // Password default XAMPP biasanya kosong
define('DB_NAME', 'linksfie_datapoolnew');

// 2. Konfigurasi Kunci API Telkomsel (Sesuai Poin 6)
// Diambil dari gambar image_3ce240.png
define('TELKOMSEL_API_KEY', 'w8w2svtf75ufv87rbgb7ux22');
define('TELKOMSEL_SECRET_KEY', 'a7n6TSQeXD');

// 3. Konfigurasi URL API
define('TELKOMSEL_API_URL', 'https://api.digitalcore.telkomsel.com/scrt/b2b/v2/get-balance');

// TAMBAHKAN BARIS DI BAWAH INI:
define('TELKOMSEL_PKG_URL', 'https://api.digitalcore.telkomsel.com/scrt/b2b/v2/get-whitelist-package');

// 4. Konfigurasi URL Basis
// Sesuaikan dengan nama folder proyek Anda di htdocs
define('BASE_URL', 'https://monitor.linksfield.id');

// -- URL yang akan dikirim ke Telkomsel untuk menerima status akhir --
define('TELKOMSEL_CALLBACK_URL', BASE_URL . '/admin/callback_telkomsel.php');
?>