<?php
// Pastikan config sudah di-load
if (!defined('DB_HOST')) {
    require_once 'config.php';
}

/**
 * Fungsi untuk koneksi ke database menggunakan PDO.
 * @return PDO Objek koneksi PDO.
 */
function db_connect() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    return $pdo;
}

/**
 * Fungsi untuk memanggil API Telkomsel dengan signature (Poin 11 & 12).
 * @param string $url URL API yang akan dipanggil.
 * @return array Hasil decode JSON dari API.
 */
function call_telkomsel_api($url) {
    $api_key = TELKOMSEL_API_KEY;
    $secret_key = TELKOMSEL_SECRET_KEY;
    $timestamp = time(); // Timestamp saat ini

    // Generate signature sesuai Poin 11
    $signature = md5($api_key . $secret_key . $timestamp);

    // Siapkan headers (sesuai gambar image_3ce547.png dan Poin 11)
    $headers = [
        'api-key: ' . $api_key,
        'x-signature: ' . $signature,
        'x-timestamp: ' . $timestamp, // Penting: Kirim timestamp agar server bisa memvalidasi signature
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout 30 detik
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Nonaktifkan verifikasi SSL (jika perlu, untuk XAMPP)

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        return json_decode($response, true);
    } elseif ($http_code == 401 || $http_code == 403) {
         return ['status' => false, 'message' => 'Autentikasi Gagal. Cek API Key/Secret Key.', 'response' => $response];
    } else {
        return ['status' => false, 'message' => 'Gagal memanggil API. HTTP Code: ' . $http_code, 'error' => $curl_error, 'response' => $response];
    }
}

/**
 * Fungsi untuk memformat byte (atau MB) menjadi GB dengan presisi.
 * @param float $megabytes Ukuran dalam MB.
 * @return float Ukuran dalam GB.
 */
function format_gb($megabytes) {
    return round($megabytes / 1024, 2);
}

/**
 * Fungsi untuk memformat tanggal dari API.
 * @param string $date_string Tanggal dari API.
 * @return string Tanggal yang sudah diformat.
 */
function format_expiry_date($date_string) {
    try {
        $date = new DateTime($date_string);
        return $date->format('d M Y, H:i'); // Format: 25 Oct 2025, 23:59
    } catch (Exception $e) {
        return $date_string; // Kembalikan string asli jika format tidak valid
    }
}
?>