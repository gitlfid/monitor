<?php
/*
 File: manage_users.php
 ===========================================================
 Feature: CRUD User + Assign Company + Auto Reset Password (Fixed)
 Status: UPDATED (English Language)
*/

// ============================================================
// 1. PHP LOGIC (Backend)
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load Auth & Config
require_once 'includes/auth_check.php'; 
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check Mail Helper
if (file_exists('includes/mail_helper.php')) {
    require_once 'includes/mail_helper.php';
} else {
    function send_credentials_email($to, $user, $pass) { return false; }
}

// Ensure only Admin access
require_admin();

$db = db_connect();
$message = '';

// --- PASSWORD GENERATOR FUNCTION ---
function generate_password($length = 8) {
    // Remove ambiguous characters: 0, O, 1, l, I
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, $length);
}

// --- LOGIC 1: DELETE USER ---
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id == $_SESSION['user_id']) {
        $message = "<div class='alert alert-warning alert-dismissible fade show'>You cannot delete your own account!<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    } else {
        try {
            $stmt_del = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->execute([$delete_id]);
            $message = "<div class='alert alert-success alert-dismissible fade show'>User successfully deleted.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>Failed to delete: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
    }
}

// --- LOGIC 2: CREATE NEW USER ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_role = $_POST['role'] ?? 'user';
    $new_company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : NULL;
    
    if ($new_username && $new_email) {
        try {
            $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$new_username, $new_email]);
            
            if ($check->rowCount() > 0) {
                $message = "<div class='alert alert-danger alert-dismissible fade show'>Username or Email already exists!<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            } else {
                // Generate Clean Password
                $generated_password = generate_password();
                // Hash Password for Database
                $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (username, password, email, role, company_id, created_by, force_password_change) VALUES (?, ?, ?, ?, ?, ?, 1)");
                
                if ($stmt->execute([$new_username, $hashed_password, $new_email, $new_role, $new_company_id, $_SESSION['user_id']])) {
                    // Send Real Password via Email
                    if (function_exists('send_credentials_email')) {
                        send_credentials_email($new_email, $new_username, $generated_password);
                    }
                    $message = "<div class='alert alert-success alert-dismissible fade show'>User <strong>$new_username</strong> created successfully! Password sent to email.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
                } else {
                    $message = "<div class='alert alert-danger alert-dismissible fade show'>Failed to save to database.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
                }
            }
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show'>Error: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
    }
}

// --- LOGIC 3: UPDATE / EDIT USER (AUTO RESET PASSWORD) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $edit_id = intval($_POST['edit_user_id']);
    $edit_email = trim($_POST['edit_email']);
    $edit_role = $_POST['edit_role'];
    $edit_company_id = !empty($_POST['edit_company_id']) ? $_POST['edit_company_id'] : NULL;
    
    // Check if reset password checkbox is checked
    $should_reset_pass = isset($_POST['reset_password_flag']) && $_POST['reset_password_flag'] == '1';

    try {
        if ($edit_id == $_SESSION['user_id'] && $edit_role != 'admin') {
             $message = "<div class='alert alert-warning alert-dismissible fade show'>You cannot change your own role to User.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        } else {
            // A. Base Update Query
            $sql = "UPDATE users SET email = ?, role = ?, company_id = ? WHERE id = ?";
            $params = [$edit_email, $edit_role, $edit_company_id, $edit_id];
            $email_notif_status = "";
            $pw_msg = "";

            // B. If Reset Password Requested
            if ($should_reset_pass) {
                // 1. Generate New Password
                $new_auto_pass = generate_password();
                
                // 2. Hash Password for DB
                $hashed_pw = password_hash($new_auto_pass, PASSWORD_DEFAULT);

                // 3. Update Query (Add password & force change)
                $sql = "UPDATE users SET email = ?, role = ?, company_id = ?, password = ?, force_password_change = 1 WHERE id = ?";
                $params = [$edit_email, $edit_role, $edit_company_id, $hashed_pw, $edit_id];
                
                // 4. Send Real Password to Email
                $stmt_get_user = $db->prepare("SELECT username FROM users WHERE id = ?");
                $stmt_get_user->execute([$edit_id]);
                $user_data = $stmt_get_user->fetch(PDO::FETCH_ASSOC);
                
                if ($user_data && function_exists('send_credentials_email')) {
                    $send_status = send_credentials_email($edit_email, $user_data['username'], $new_auto_pass);
                    
                    if ($send_status) {
                        $email_notif_status = " <br><span class='badge bg-success'><i class='bi bi-envelope-check'></i> New Password Sent to Email</span>";
                    } else {
                        // Show password on screen IF email fails
                        $email_notif_status = " <br><span class='badge bg-danger'>Email Failed. New Password: <strong>$new_auto_pass</strong> (Copy Now!)</span>";
                    }
                }
                $pw_msg = " and password has been auto-reset.";
            }

            // C. Execute Update
            $stmt_update = $db->prepare($sql);
            if ($stmt_update->execute($params)) {
                $message = "<div class='alert alert-success alert-dismissible fade show'>User data updated$pw_msg $email_notif_status<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show'>Failed to update data.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }
        }
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'>Error: " . $e->getMessage() . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
    }
}

// --- GET COMPANY DATA ---
try {
    $stmt_comp = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
    $companies_list = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $companies_list = [];
}

// --- GET USER DATA ---
$sql_users = "SELECT u.*, c.company_name 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              ORDER BY u.created_at DESC";
$stmt_list = $db->query($sql_users);
$all_users = $stmt_list->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// 2. HTML VIEW (Frontend)
// ============================================================
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Manage Users & Access</h3>
                <p class="text-subtitle text-muted">Create users, edit roles, and manage company access scope.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    
    <?php echo $message; ?>

    <section class="row">
        <div class="col-12 col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title"><i class="bi bi-person-plus-fill"></i> Add New User</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="create_user" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" id="username" name="username" class="form-control" placeholder="Ex: user_telkomsel" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="email@client.com" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Application Role</label>
                                <select name="role" id="role" class="form-select">
                                    <option value="user">User (View Only)</option>
                                    <option value="admin">Administrator (Full Access)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="company_id" class="form-label fw-bold text-primary">Company Scope Access</label>
                                <select name="company_id" id="company_id" class="form-select">
                                    <option value="">-- Global Access (All Companies) --</option>
                                    <?php foreach ($companies_list as $comp): ?>
                                        <option value="<?php echo $comp['id']; ?>">
                                            <?php echo htmlspecialchars($comp['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">If empty, user can see <strong>all</strong> companies.</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> User must change password on first login.</small>
                            <button type="submit" class="btn btn-primary px-4">Create Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Access Logic</h4>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="mb-3">
                            <span class="badge bg-success">Admin + All Companies</span>
                            <p class="text-sm mt-1">Role Admin with empty Company. Can manage all clients and users.</p>
                        </div>
                        <div class="mb-3">
                            <span class="badge bg-info">User + Single Company</span>
                            <p class="text-sm mt-1">Role User assigned to a specific company. Can only view data for that company.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Registered Users</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Company Access</th>
                                    <th>Status</th>
                                    <th>Date Created</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($all_users) > 0): ?>
                                    <?php foreach ($all_users as $u): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?php echo htmlspecialchars($u['username']); ?>
                                                <div class="small text-muted fw-normal"><?php echo htmlspecialchars($u['email']); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($u['role'] === 'admin'): ?>
                                                    <span class="badge bg-primary">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($u['company_name'])): ?>
                                                    <span class="badge bg-info text-dark">
                                                        <i class="bi bi-building"></i> <?php echo htmlspecialchars($u['company_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-dark">Global / All</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($u['force_password_change']) && $u['force_password_change'] == 1): ?>
                                                    <span class="text-warning small"><i class="bi bi-shield-lock"></i> Force Pass</span>
                                                <?php else: ?>
                                                    <span class="text-success small"><i class="bi bi-check-circle"></i> Secure</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($u['created_at'] ?? 'now')); ?></td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-warning btn-edit"
                                                            data-id="<?php echo $u['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                                            data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                                            data-role="<?php echo $u['role']; ?>"
                                                            data-company="<?php echo $u['company_id']; ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>

                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <a href="?delete_id=<?php echo $u['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Are you sure you want to delete user <?php echo $u['username']; ?>?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled><i class="bi bi-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted p-4">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>

<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User & Access Rights</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="edit_user_id" id="edit_user_id">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control bg-light" id="edit_username" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="edit_role" id="edit_role" class="form-select">
                            <option value="user">User (View Only)</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">Company Access</label>
                        <select name="edit_company_id" id="edit_company_id" class="form-select">
                            <option value="">-- Global Access (All Companies) --</option>
                            <?php foreach ($companies_list as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>">
                                    <?php echo htmlspecialchars($comp['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Restrict user to a specific company.</small>
                    </div>

                    <hr>

                    <div class="p-3 bg-light rounded border border-danger">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="reset_password_flag" id="reset_password_flag" value="1">
                            <label class="form-check-label fw-bold text-danger" for="reset_password_flag">
                                <i class="bi bi-key-fill"></i> Generate & Email New Password
                            </label>
                        </div>
                        <small class="text-muted d-block mt-2">
                            If checked, system will generate a secure password (letters & numbers) and send it automatically to user's email.
                        </small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script src="assets/extensions/jquery/jquery.min.js"></script>
<script>
    $(document).ready(function() {
        // On Edit Button Click
        $('.btn-edit').on('click', function() {
            // Get data from attributes
            var id = $(this).data('id');
            var username = $(this).data('username');
            var email = $(this).data('email');
            var role = $(this).data('role');
            var company = $(this).data('company');

            // Fill modal
            $('#edit_user_id').val(id);
            $('#edit_username').val(username);
            $('#edit_email').val(email);
            $('#edit_role').val(role);
            $('#edit_company_id').val(company);
            
            // Reset checkbox
            $('#reset_password_flag').prop('checked', false);

            // Show Modal
            var editModal = new bootstrap.Modal(document.getElementById('modalEditUser'));
            editModal.show();
        });
    });
</script>