<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
// sidebar.php sekarang otomatis membuka layout, header, dan content wrapper.

$db = db_connect();
$message = '';
$error = '';

// --- LOGIKA PENANGANAN POST (TIDAK BERUBAH) ---
try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        // Aksi: Simpan (Create/Update) PERUSAHAAN
        if (isset($_POST['save_company'])) {
            $company_name = $_POST['company_name'];
            $id = $_POST['id'];

            if (empty($id)) { // --- CREATE ---
                $stmt = $db->prepare("INSERT INTO companies (company_name) VALUES (?)");
                $stmt->execute([$company_name]);
                $message = '<div class="alert alert-success alert-dismissible fade show">The new company has been successfully added.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else { // --- UPDATE ---
                $stmt = $db->prepare("UPDATE companies SET company_name = ? WHERE id = ?");
                $stmt->execute([$company_name, $id]);
                $message = '<div class="alert alert-success alert-dismissible fade show">The company name has been successfully updated.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
        }

        // Aksi: Hapus PERUSAHAAN
        if (isset($_POST['delete_company'])) {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            $message = '<div class="alert alert-success alert-dismissible fade show">The company and all related projects have been successfully deleted.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }

        // Aksi: Simpan (Create/Update) PROYEK
        if (isset($_POST['save_project'])) {
            $company_id = $_POST['company_id'];
            $project_name = $_POST['project_name'];
            $subscription_key = $_POST['subscription_key'];
            $id = $_POST['id'];

            if (empty($id)) { // --- CREATE ---
                $stmt = $db->prepare("INSERT INTO projects (company_id, project_name, subscription_key) VALUES (?, ?, ?)");
                $stmt->execute([$company_id, $project_name, $subscription_key]);
                $message = '<div class="alert alert-success alert-dismissible fade show">New project successfully added.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else { // --- UPDATE ---
                $stmt = $db->prepare("UPDATE projects SET company_id = ?, project_name = ?, subscription_key = ? WHERE id = ?");
                $stmt->execute([$company_id, $project_name, $subscription_key, $id]);
                $message = '<div class="alert alert-success alert-dismissible fade show">The project data has been successfully updated.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
        }

        // Aksi: Hapus PROYEK
        if (isset($_POST['delete_project'])) {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $message = '<div class="alert alert-success alert-dismissible fade show">Project data has been successfully deleted.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    }
} catch (PDOException $e) {
    $error = '<div class="alert alert-danger alert-dismissible fade show">Failed to process data: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

// --- PENGAMBILAN DATA ---
$stmt_companies = $db->query("SELECT * FROM companies ORDER BY company_name ASC");
$companies = $stmt_companies->fetchAll();

$stmt_projects = $db->query("SELECT p.*, c.company_name 
                            FROM projects p
                            JOIN companies c ON p.company_id = c.id
                            ORDER BY c.company_name ASC, p.project_name ASC");
$projects = $stmt_projects->fetchAll();

$total_clients = count($companies);
$total_projects = count($projects);
?>

<div class="page-heading">
    <h3>Manage Clients & Projects</h3>
</div>

<div class="page-content">
    <?php echo $message; ?>
    <?php echo $error; ?>
    
    <div class="row">
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4"><div class="stats-icon purple mb-2"><i class="bi bi-building"></i></div></div>
                        <div class="col-md-8"><h6 class="text-muted font-semibold">Total Clients</h6><h6 class="font-extrabold mb-0"><?php echo $total_clients; ?></h6></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body px-3 py-4-5">
                    <div class="row">
                        <div class="col-md-4"><div class="stats-icon blue mb-2"><i class="bi bi-briefcase-fill"></i></div></div>
                        <div class="col-md-8"><h6 class="text-muted font-semibold">Total Projects</h6><h6 class="font-extrabold mb-0"><?php echo $total_projects; ?></h6></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Add New Company</h4></div>
                <div class="card-content">
                    <div class="card-body">
                        <form method="POST" action="manage_companies.php" class="form form-vertical">
                            <input type="hidden" name="id" value=""> 
                            <div class="form-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group has-icon-left">
                                            <label for="company_name">Company Name</label>
                                            <div class="position-relative">
                                                <input type="text" class="form-control" id="company_name" name="company_name" placeholder="e.g. PT Maju Jaya" required>
                                                <div class="form-control-icon"><i class="bi bi-building"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-end mt-3">
                                        <button type="submit" name="save_company" class="btn btn-primary me-1 mb-1">Save Company</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h4 class="card-title">List of Companies</h4></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="table-companies">
                            <thead><tr><th>#</th><th>Company Name</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($companies)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">No company data available.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($companies as $index => $company): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?>.</td>
                                    <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info btn-edit-company" data-id="<?php echo $company['id']; ?>" data-name="<?php echo htmlspecialchars($company['company_name']); ?>" data-bs-toggle="modal" data-bs-target="#editCompanyModal"><i class="bi bi-pencil-square"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: Deleting this company will also delete ALL projects. Are you sure?');">
                                            <input type="hidden" name="id" value="<?php echo $company['id']; ?>">
                                            <button type="submit" name="delete_company" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Add New Project</h4></div>
                <div class="card-content">
                    <div class="card-body">
                        <form method="POST" action="manage_companies.php" class="form form-vertical">
                            <input type="hidden" name="id" value=""> 
                            <div class="form-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label for="company_id" class="form-label">Company</label>
                                            <fieldset class="form-group">
                                                <select class="form-select" id="company_id" name="company_id" required>
                                                    <option value="">-- Select Company --</option>
                                                    <?php foreach ($companies as $company): ?>
                                                        <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </fieldset>
                                            <?php if (empty($companies)): ?><small class="text-danger">You must add the company first.</small><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-12"><div class="form-group mb-3"><label for="project_name">Project Name</label><input type="text" class="form-control" id="project_name" name="project_name" required></div></div>
                                    <div class="col-12"><div class="form-group mb-3"><label for="subscription_key">Subscription Key</label><input type="text" class="form-control" id="subscription_key" name="subscription_key" required></div></div>
                                    <div class="col-12 d-flex justify-content-end mt-3"><button type="submit" name="save_project" class="btn btn-success me-1 mb-1" <?php echo empty($companies) ? 'disabled' : ''; ?>>Save Project</button></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Project List</h4></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead><tr><th>#</th><th>Company</th><th>Project Name</th><th>Sub. Key</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($projects)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No project data available.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($projects as $index => $project): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?>.</td>
                                    <td><?php echo htmlspecialchars($project['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($project['subscription_key']); ?></small></td>
                                    <td>
                                        <button class="btn btn-sm btn-info btn-edit-project" 
                                                data-id="<?php echo $project['id']; ?>"
                                                data-company-id="<?php echo $project['company_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($project['project_name']); ?>" 
                                                data-key="<?php echo htmlspecialchars($project['subscription_key']); ?>" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editProjectModal"><i class="bi bi-pencil-square"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this project?');">
                                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                            <button type="submit" name="delete_project" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="editCompanyModalLabel">Edit Company Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="POST" action="manage_companies.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_company_id"> 
                    <div class="form-group mb-3"><label for="edit_company_name" class="form-label">Company Name</label><input type="text" class="form-control" id="edit_company_name" name="company_name" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_company" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editProjectModal" tabindex="-1" aria-labelledby="editProjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="editProjectModalLabel">Edit Project Data</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <form method="POST" action="manage_companies.php">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_project_id"> 
                    <div class="form-group mb-3">
                        <label for="edit_project_company_id" class="form-label">Company</label>
                        <select class="form-select" id="edit_project_company_id" name="company_id" required>
                            <?php foreach ($companies as $company): ?><option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3"><label for="edit_project_name" class="form-label">Project Name</label><input type="text" class="form-control" id="edit_project_name" name="project_name" required></div>
                    <div class="form-group mb-3"><label for="edit_project_subscription_key" class="form-label">Subscription Key</label><input type="text" class="form-control" id="edit_project_subscription_key" name="subscription_key" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="save_project" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="assets/extensions/jquery/jquery.min.js"></script>
<script>
$(document).ready(function() {
    $(document).on('click', '.btn-edit-company', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#edit_company_id').val(id);
        $('#edit_company_name').val(name);
    });

    $(document).on('click', '.btn-edit-project', function() {
        var id = $(this).data('id');
        var companyId = $(this).data('company-id');
        var name = $(this).data('name');
        var key = $(this).data('key');
        $('#edit_project_id').val(id);
        $('#edit_project_company_id').val(companyId);
        $('#edit_project_name').val(name);
        $('#edit_project_subscription_key').val(key);
    });
});
</script>
<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>