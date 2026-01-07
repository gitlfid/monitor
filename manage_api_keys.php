<?php
/*
 File: manage_api_keys.php
 Fungsi: Mengelola API Keys & Force Logout User (via Regenerate)
*/

// Aktifkan Error Reporting untuk debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth_check.php'; // Ini akan memvalidasi admin
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Hanya Admin
require_admin();

$db = db_connect();
$message = '';

// --- LOGIKA REGENERATE (FORCE LOGOUT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_id'])) {
    $target_id = intval($_POST['regenerate_id']);
    $target_username = $_POST['target_username'] ?? 'User';
    
    try {
        // 1. Buat Key Baru yang benar-benar acak
        $new_access = bin2hex(random_bytes(16));
        $new_secret = bin2hex(random_bytes(32));
        
        // 2. Update ke Database
        $stmt = $db->prepare("UPDATE users SET access_key = ?, secret_key = ? WHERE id = ?");
        if ($stmt->execute([$new_access, $new_secret, $target_id])) {
            
            // PENTING: Mencegah Admin Ter-logout Sendiri
            // Jika admin meregenerate akunnya sendiri, update session admin saat ini 
            // dengan key baru agar sesi tetap valid.
            if ($target_id == $_SESSION['user_id']) {
                $_SESSION['access_key'] = $new_access;
            }

            $message = "<div class='alert alert-success alert-dismissible fade show'>
                            <i class='bi bi-check-circle-fill me-2'></i>
                            API Keys untuk <strong>" . htmlspecialchars($target_username) . "</strong> berhasil diperbarui.<br>
                            <small>Jika user tersebut sedang login, sesi mereka menjadi tidak valid dan akan otomatis ter-logout pada aktivitas berikutnya.</small>
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                        </div>";
        } else {
            throw new Exception("Gagal update database.");
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// Ambil Data User
$users = $db->query("SELECT id, username, role, access_key, secret_key FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>API & Session Management</h3>
                <p class="text-subtitle text-muted">Kelola keamanan akses dan paksa logout user (Revoke Access).</p>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <?php echo $message; ?>
    
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">User Credentials</h4>
            <span class="badge bg-light-primary">Total Users: <?php echo count($users); ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>User Info</th>
                            <th>Role</th>
                            <th>Access Key (Public)</th>
                            <th>Secret Key (Private)</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($u['username']) ?></div>
                                <div class="small text-muted">ID: <?= $u['id'] ?></div>
                            </td>
                            <td>
                                <?php if($u['role'] === 'admin'): ?>
                                    <span class="badge bg-primary">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['access_key']): ?>
                                    <code class="text-primary small"><?= $u['access_key'] ?></code>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Not Generated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['secret_key']): ?>
                                    <div class="input-group input-group-sm" style="width: 180px;">
                                        <input type="password" class="form-control font-monospace" value="<?= $u['secret_key'] ?>" id="sec_<?= $u['id'] ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleSecret(<?= $u['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Login first</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <form method="POST" onsubmit="return confirm('PERINGATAN KEAMANAN:\n\nAnda akan mengubah API Key user ini.\nJika user sedang login, sesi mereka akan HANGUS dan dipaksa logout.\n\nLanjutkan?');">
                                    <input type="hidden" name="regenerate_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="target_username" value="<?= htmlspecialchars($u['username']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Regenerate Key & Force Logout">
                                        <i class="bi bi-arrow-repeat"></i> Revoke
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-body">
            <h5><i class="bi bi-shield-check text-success me-2"></i> Mekanisme Keamanan</h5>
            <ul class="mt-2 text-muted small">
                <li class="mb-1"><strong>Idle Timeout:</strong> Jika user tidak melakukan aktivitas selama <strong>5 menit</strong>, sistem otomatis melogout mereka (diatur di <code>auth_check.php</code>).</li>
                <li class="mb-1"><strong>Force Logout (Revoke):</strong> Saat Anda menekan tombol <span class="text-danger fw-bold">Revoke</span>, Access Key di database berubah.</li>
                <li>User yang sedang login memegang "Kunci Lama" di browser mereka. Saat mereka melakukan klik menu atau refresh halaman berikutnya, sistem mendeteksi kunci tidak cocok dan langsung memutus sesi.</li>
            </ul>
        </div>
    </div>
</div>

<script>
function toggleSecret(id) {
    var x = document.getElementById("sec_" + id);
    var icon = x.nextElementSibling.querySelector('i');
    if (x.type === "password") {
        x.type = "text";
        if(icon) { icon.classList.remove('bi-eye'); icon.classList.add('bi-eye-slash'); }
    } else {
        x.type = "password";
        if(icon) { icon.classList.remove('bi-eye-slash'); icon.classList.add('bi-eye'); }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>