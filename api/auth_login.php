<?php
/*
 File: api/auth_login.php
 Fungsi: Endpoint Login Pusat (API Gateway).
 Security: Password Verify + HMAC SHA256 Signature + Auto Key Gen.
*/

// 1. Set Header JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Opsional: Jika akses dari domain lain
header('Access-Control-Allow-Methods: POST');

// 2. Validasi Method (Hanya POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed (POST Only)']);
    exit;
}

// 3. Load Database Config
// Menggunakan dirname(__FILE__) agar path relatif aman terbaca dari folder api/
$root_path = dirname(__DIR__); // Naik satu folder ke root
require_once $root_path . '/includes/config.php'; 
require_once $root_path . '/includes/functions.php';

// 4. Tangkap Input (Mendukung JSON Raw & Form Data)
$input = json_decode(file_get_contents('php://input'), true);

$username = isset($input['username']) ? trim($input['username']) : (isset($_POST['username']) ? trim($_POST['username']) : '');
$password = isset($input['password']) ? trim($input['password']) : (isset($_POST['password']) ? trim($_POST['password']) : '');

// Validasi Input Kosong
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Username dan Password wajib diisi.']);
    exit;
}

try {
    $db = db_connect();
    
    // 5. Cari User di Database
    // Kita ambil semua kolom penting termasuk key
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Verifikasi Password (Hash)
    if ($user && password_verify($password, $user['password'])) {
        
        // --- FITUR SELF-HEALING SECURITY ---
        // Jika user valid tapi belum punya API Key (User Lama), generate sekarang.
        $keys_updated = false;
        
        if (empty($user['access_key']) || empty($user['secret_key'])) {
            $new_access = bin2hex(random_bytes(16)); // 32 karakter
            $new_secret = bin2hex(random_bytes(32)); // 64 karakter
            
            // Simpan ke DB
            $upd = $db->prepare("UPDATE users SET access_key = ?, secret_key = ? WHERE id = ?");
            $upd->execute([$new_access, $new_secret, $user['id']]);
            
            // Update variabel user di memori saat ini
            $user['access_key'] = $new_access;
            $user['secret_key'] = $new_secret;
            $keys_updated = true;
        }

        // 7. GENERATE DIGITAL SIGNATURE (HMAC)
        // Signature ini membuktikan bahwa data user tidak dimanipulasi di tengah jalan
        $timestamp = time();
        
        // Payload string: Kombinasi data unik user + waktu
        $payload_string = $user['id'] . $user['username'] . $user['role'] . $timestamp;
        
        // Sign menggunakan Secret Key user (Hanya server yang tahu secret key ini)
        $signature = hash_hmac('sha256', $payload_string, $user['secret_key']);

        // 8. RESPONSE SUKSES
        // Kirim data yang dibutuhkan Frontend/Session, tapi JANGAN kirim password/secret_key
        echo json_encode([
            'status' => true,
            'message' => 'Login Success',
            'data' => [
                'user_id'     => (int)$user['id'],
                'username'    => $user['username'],
                'role'        => $user['role'],
                'company_id'  => $user['company_id'], // Penting untuk Multi-tenant
                'force_pass'  => (int)$user['force_password_change'], // Penting untuk keamanan pass
                'access_key'  => $user['access_key'], // Public Key untuk validasi sesi
                'timestamp'   => $timestamp,
                'login_signature' => $signature,
                'key_generated' => $keys_updated // Info debug (opsional)
            ]
        ]);

    } else {
        // Login Gagal
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => false, 'message' => 'Username atau Password salah.']);
    }

} catch (Exception $e) {
    // Error Server / Database
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database/Server Error: ' . $e->getMessage()]);
}
?>