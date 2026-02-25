<?php
/*
 File: upload_csv.php (Versi Mazer - Layout Fixed)
 ======================================
 Tugas:
 1. Menambahkan dropdown Company & Project (wajib)
 2. Membaca CSV/XLSX berdasarkan "Nama Header".
 3. Mem-parsing 'Package Name' untuk mengambil 'quota_value'.
 4. Menyimpan company_id & project_id ke 'inject_history'.
 5. Mencegah duplikasi data berdasarkan 'Request ID'.
*/

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; // Sidebar otomatis membuka layout wrapper
require_once __DIR__ . '/vendor/autoload.php'; // Composer

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$db = db_connect();
if (!$db) {
    die("Fatal Error: Gagal terhubung ke database. Cek konfigurasi.");
}

$message = '';
$error = '';
$total_rows_processed = 0;
$total_rows_skipped_duplicate = 0;
$total_rows_skipped_empty = 0;

// Ambil daftar company untuk dropdown
try {
    $companies_stmt = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Gagal mengambil daftar company: " . $e->getMessage();
    $companies = [];
}

// Proses jika ada file yang di-upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit']) && isset($_FILES['csv_file'])) {

    // Validasi Company & Project
    $selected_company_id = $_POST['company_id'] ?? 0;
    $selected_project_id = $_POST['project_id'] ?? 0;

    if (empty($selected_company_id) || empty($selected_project_id)) {
        $error = "Error: Harap pilih Company dan Project terlebih dahulu.";
    }
    else {
        $allowed_extensions = ['csv', 'xlsx'];
        $original_filename = basename($_FILES['csv_file']['name']);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $error = "Error: Format file salah. Harap upload file .csv atau .xlsx";
        } elseif ($_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['csv_file']['tmp_name'];

            try {
                $batch_user_id = $_SESSION['user_id'] ?? 0;
                $batch_username = $_SESSION['username'] ?? 'File Upload';
                $batch_denom_name = 'Upload: ' . htmlspecialchars($original_filename);
                $batch_id = null;

                $header = [];
                $data_lines_array = [];
                $file_empty = true;

                // [LOGIKA BACA FILE CSV/XLSX]
                if ($file_extension === 'xlsx') {
                    $spreadsheet = IOFactory::load($file_tmp_path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $highestRow = $worksheet->getHighestRow();
                    $highestColumn = $worksheet->getHighestColumn();

                    if ($highestRow >= 1) {
                        $header_raw = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];
                         if (empty(array_filter($header_raw))) { throw new Exception("Header XLSX tidak bisa dibaca atau kosong."); }
                        $header = array_map('trim', $header_raw);

                        if ($highestRow >= 2) {
                            $file_empty = false;
                            for ($row = 2; $row <= $highestRow; $row++) {
                                 $rowData = $worksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
                                 if (!empty(array_filter($rowData, fn($cell) => !is_null($cell) && $cell !== ''))) {
                                     $data_lines_array[] = $rowData;
                                 }
                            }
                        }
                    }
                     if ($file_empty && empty($header)) { throw new Exception("File XLSX kosong atau tidak bisa dibaca."); }

                } else { // 'csv'
                    $csv_content = file_get_contents($file_tmp_path);
                    if ($csv_content === false) { throw new Exception("Gagal membaca isi file CSV."); }
                    $csv_content = preg_replace('/^\x{FEFF}/u', '', $csv_content);
                    $lines = preg_split('/\r\n|\r|\n/', $csv_content, -1, PREG_SPLIT_NO_EMPTY);

                    if (!empty($lines)) {
                        $header_line = array_shift($lines);
                        $header_raw = str_getcsv($header_line);
                         if ($header_raw === false || empty(array_filter($header_raw))) { throw new Exception("Header CSV tidak bisa dibaca."); }
                        $header = array_map('trim', $header_raw);

                        if (!empty($lines)) {
                            $file_empty = false;
                            foreach ($lines as $line) {
                                 $data = str_getcsv($line);
                                 if ($data !== false && !empty(array_filter($data))) {
                                     $data_lines_array[] = $data;
                                 }
                            }
                        }
                    }
                     if ($file_empty && empty($header)) { throw new Exception("File CSV kosong atau tidak bisa dibaca."); }
                }

                if ($file_empty) { throw new Exception("File hanya berisi header, tidak ada baris data."); }

                // Cari posisi kolom
                $time_col = array_search('Submit Time', $header);
                $reqid_col = array_search('Request ID', $header); 
                $msisdn_col = array_search('Target MSISDN', $header);
                $pkg_col = array_search('Package Name', $header);
                $status_col = array_search('Status', $header);
                
                // Validasi Header
                if ($time_col === false || $reqid_col === false || $msisdn_col === false || $pkg_col === false || $status_col === false) {
                    $error = "Format File salah. Header tidak lengkap. Pastikan ada: 'Submit Time', 'Request ID', 'Target MSISDN', 'Package Name', 'Status'.";
                    $error .= "<br><small>Header terbaca: " . implode(', ', $header) . "</small>";
                } else {
                    // --- DB TRANSACTION ---
                    $db->beginTransaction();

                    $stmt_batch = $db->prepare("INSERT INTO inject_batches (user_id, username, denom_name, status) VALUES (?, ?, ?, 'PROCESSING')");
                    $stmt_batch->execute([$batch_user_id, $batch_username, $batch_denom_name]);
                    $batch_id = $db->lastInsertId();

                    $stmt_check = $db->prepare("SELECT id FROM inject_history WHERE request_id = ?");

                    $stmt_insert = $db->prepare("
                        INSERT INTO inject_history
                            (batch_id, company_id, project_id, created_at, request_id, msisdn_target, denom_name, status, quota_value, quota_unit)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $row_index = 0;
                    foreach ($data_lines_array as $data) {
                        $row_index++;
                        // Validasi jumlah kolom
                        if(count($data) < max($time_col, $reqid_col, $msisdn_col, $pkg_col, $status_col) + 1) {
                             $total_rows_skipped_empty++; 
                             continue;
                        }

                        $submit_time = $data[$time_col];
                        $request_id = trim($data[$reqid_col]);
                        $msisdn = $data[$msisdn_col];
                        $package_name = $data[$pkg_col];
                        $status = strtoupper($data[$status_col] ?? 'UNKNOWN');

                        if (empty($request_id) || empty($msisdn)) {
                             $total_rows_skipped_empty++;
                             continue;
                        }

                        // Cek Duplikasi Request ID
                        $stmt_check->execute([$request_id]);
                        if ($stmt_check->fetch()) {
                            $total_rows_skipped_duplicate++;
                            continue;
                        }

                        // Parsing Kuota
                        $quota_value = 0;
                        $quota_unit = 'GB';
                        if (preg_match('/(\d+(\.\d+)?)\s*(GB|MB)/i', (string)$package_name, $matches)) {
                            $quota_value = (float)$matches[1];
                            $quota_unit = strtoupper($matches[3]);
                        }

                        // Konversi Tanggal
                        try {
                            $submit_time_str = trim((string)$submit_time);
                            $dt = false; 
                            if (is_numeric($submit_time_str) && $submit_time_str > 25569) {
                                 $unix_timestamp = ($submit_time_str - 25569) * 86400;
                                 $dt = new DateTime("@$unix_timestamp");
                            } else {
                                $formats_to_try = [
                                    'Y-m-d H:i:s', 'Y-m-d H:i', 'm/d/Y H:i', 'd/m/Y H:i',
                                    'm/d/Y g:i A', 'm/d/Y', 'Y-m-d'
                                ];
                                foreach ($formats_to_try as $format) {
                                    $dt = DateTime::createFromFormat($format, $submit_time_str);
                                    if ($dt !== false) break; 
                                }
                            }
                            if ($dt === false) throw new Exception("Format tanggal tidak dikenali: '$submit_time_str'");
                            $submit_time_sql = $dt->format('Y-m-d H:i:s');
                        } catch (Exception $e) {
                             throw new Exception("Gagal konversi tanggal di baris data ke-".$row_index.": ".$e->getMessage());
                        }

                        // Insert Log
                        $stmt_insert->execute([
                            $batch_id, 
                            $selected_company_id,
                            $selected_project_id,
                            $submit_time_sql, 
                            $request_id, 
                            $msisdn, 
                            $package_name,
                            $status, 
                            $quota_value, 
                            $quota_unit
                        ]);
                        $total_rows_processed++; 
                    }

                    $db->prepare("UPDATE inject_batches SET total_numbers = ?, status = 'COMPLETED' WHERE id = ?")
                       ->execute([$total_rows_processed, $batch_id]);

                    $db->commit();
                    $message = "<strong>Sukses!</strong> Berhasil memproses file.<br>";
                    $message .= " - Company: <b>" . htmlspecialchars($_POST['company_name_text']) . "</b><br>";
                    $message .= " - Project: <b>" . htmlspecialchars($_POST['project_name_text']) . "</b><br>";
                    $message .= " - Baris berhasil diimpor: <b>$total_rows_processed</b><br>";
                    if ($total_rows_skipped_duplicate > 0) {
                        $message .= " - Baris dilewati (Request ID duplikat): <b>$total_rows_skipped_duplicate</b><br>";
                    }
                    if ($total_rows_skipped_empty > 0) {
                        $message .= " - Baris dilewati (Data tidak lengkap): <b>$total_rows_skipped_empty</b>";
                    }

                } 

            } catch (PDOException $e) {
                if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
                $error = "Error Database: " . $e->getMessage();
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
                $error = "Error Membaca File XLSX: " . $e->getMessage();
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
                $error = "Error Memproses File: " . $e->getMessage();
            }

        } else {
            $error = "Gagal meng-upload file. Error code: " . $_FILES['csv_file']['error'];
        }
    }
}
?>

<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6">
                <h3>Upload Report (CSV / XLSX)</h3>
                <p class="text-subtitle text-muted">Upload injection logs here.</p>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <div class="row">
        <div class="col-12 col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Form Upload Report</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data" class="form form-vertical">
                            <div class="form-body">
                                <div class="row">
                                    
                                    <div class="col-md-6 col-12">
                                        <div class="form-group mb-3">
                                            <label for="company_id" class="form-label">Company</label>
                                            <select class="form-select" id="company_id" name="company_id" required>
                                                <option value="">-- Select Company --</option>
                                                <?php foreach ($companies as $company): ?>
                                                    <option value="<?php echo $company['id']; ?>">
                                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-12">
                                        <div class="form-group mb-3">
                                            <label for="project_id" class="form-label">Project</label>
                                            <select class="form-select" id="project_id" name="project_id" required disabled>
                                                <option value="">-- Select Company First --</option>
                                            </select>
                                        </div>
                                    </div>

                                    <input type="hidden" name="company_name_text" id="company_name_text">
                                    <input type="hidden" name="project_name_text" id="project_name_text">

                                    <div class="col-12">
                                        <hr>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-group mb-3">
                                            <label for="csv_file" class="form-label">Choose file report (.csv / .xlsx)</label>
                                            <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv, .xlsx" required>
                                            <small class="text-muted d-block mt-1">
                                                The file format must have a header: <br>
                                                <code>Submit Time, Request ID, Target MSISDN, Package Name, Status</code>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-12 d-flex justify-content-end mt-3">
                                        <button type="submit" name="submit" class="btn btn-primary me-1 mb-1">
                                            <i class="bi bi-cloud-arrow-up-fill"></i> Upload and Process Data
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4>Workflow</h4>
                </div>
                <div class="card-body">
                    <p>This page reads a CSV or XLSX file containing injection logs.</p>
                    <ol class="ps-3">
                        <li>Select the target <b>Company</b> and <b>Project</b>.</li>
                        <li>Choose file <b>CSV</b> or <b>XLSX</b>.</li>
                        <li>The system will read the file header.</li>
                        <li>Data with <b>Request ID</b> those already in the database will be skipped.</li>
                        <li>Data will be saved with the <code>company_id</code> and <code>project_id</code> you selected.</li>
                        <li>See the results in <a href="injection_calendar.php" class="text-decoration-underline">Injection Calendar</a>.</li>
                    </ol>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="assets/extensions/jquery/jquery.min.js"></script>
<script>
$(document).ready(function() {
    // Script untuk dropdown dinamis
    $('#company_id').on('change', function() {
        var companyId = $(this).val();
        var companyName = $(this).find('option:selected').text();
        $('#company_name_text').val($.trim(companyName));
        
        var $projectDropdown = $('#project_id');
        $projectDropdown.prop('disabled', true).html('<option value="">Loading...</option>');
        $('#project_name_text').val('');

        if (companyId) {
            $.ajax({
                url: 'ajax/get_projects_by_company.php',
                type: 'GET',
                data: { company_id: companyId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === true && response.projects.length > 0) {
                        $projectDropdown.prop('disabled', false).html('<option value="">-- Select Project --</option>');
                        response.projects.forEach(function(project) {
                            $projectDropdown.append(
                                $('<option></option>').val(project.id).text(project.project_name)
                            );
                        });
                    } else {
                        $projectDropdown.html('<option value="">-- No projects --</option>');
                    }
                },
                error: function() {
                    $projectDropdown.html('<option value="">-- Failed to load --</option>');
                }
            });
        } else {
            $projectDropdown.html('<option value="">-- Select Company First --</option>');
        }
    });
    
    // Simpan nama project saat dipilih
    $('#project_id').on('change', function() {
        var projectName = $(this).find('option:selected').text();
        $('#project_name_text').val($.trim(projectName));
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>