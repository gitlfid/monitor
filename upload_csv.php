<?php
/*
 File: upload_csv.php
 ======================================
 Tugas:
 1. Menambahkan dropdown Company & Project (wajib)
 2. Membaca CSV/XLSX berdasarkan "Nama Header".
 3. Mem-parsing 'Package Name' untuk mengambil 'quota_value'.
 4. Menyimpan company_id & project_id ke 'inject_history'.
 5. Mencegah duplikasi data berdasarkan 'Request ID'.
 Theme: Ultra-Modern Tailwind CSS
*/

require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
require_once __DIR__ . '/vendor/autoload.php'; // Composer

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$db = db_connect();
if (!$db) {
    die("Fatal Error: Gagal terhubung ke database. Cek konfigurasi.");
}

// Helper function untuk alert Tailwind
function tailwindAlert($type, $msg) {
    $colors = $type === 'success' ? 'emerald' : ($type === 'warning' ? 'amber' : 'red');
    $icon = $type === 'success' ? 'ph-check-circle' : ($type === 'warning' ? 'ph-warning' : 'ph-x-circle');
    return '<div class="relative flex items-start gap-3 px-6 py-4 mb-6 text-sm font-bold text-'.$colors.'-800 bg-'.$colors.'-50 border border-'.$colors.'-200 rounded-2xl animate-fade-in-up dark:bg-'.$colors.'-500/10 dark:text-'.$colors.'-400 dark:border-'.$colors.'-500/20 shadow-sm"><i class="ph-fill '.$icon.' text-2xl mt-0.5"></i><div class="leading-relaxed flex-1">'.$msg.'</div><button type="button" class="text-'.$colors.'-600 hover:text-'.$colors.'-800 dark:hover:text-'.$colors.'-300 transition-colors shrink-0" onclick="this.parentElement.remove()"><i class="ph ph-x text-lg"></i></button></div>';
}

$alert_output = '';
$total_rows_processed = 0;
$total_rows_skipped_duplicate = 0;
$total_rows_skipped_empty = 0;

// Ambil daftar company untuk dropdown
try {
    $companies_stmt = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $alert_output = tailwindAlert('error', "Gagal mengambil daftar company: " . $e->getMessage());
    $companies = [];
}

// Proses jika ada file yang di-upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit']) && isset($_FILES['csv_file'])) {

    // Validasi Company & Project
    $selected_company_id = $_POST['company_id'] ?? 0;
    $selected_project_id = $_POST['project_id'] ?? 0;

    if (empty($selected_company_id) || empty($selected_project_id)) {
        $alert_output = tailwindAlert('error', 'Error: Harap pilih Company dan Project terlebih dahulu.');
    }
    else {
        $allowed_extensions = ['csv', 'xlsx'];
        $original_filename = basename($_FILES['csv_file']['name']);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $alert_output = tailwindAlert('error', 'Error: Format file salah. Harap upload file .csv atau .xlsx');
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
                    $err_msg = "Format File salah. Header tidak lengkap. Pastikan ada: 'Submit Time', 'Request ID', 'Target MSISDN', 'Package Name', 'Status'.";
                    $err_msg .= "<br><span class='text-xs mt-2 block font-normal opacity-80'>Header terbaca: " . implode(', ', $header) . "</span>";
                    $alert_output = tailwindAlert('error', $err_msg);
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
                    
                    // Build Success Message
                    $success_msg = "<div class='mb-2 text-lg'>File processed successfully!</div>";
                    $success_msg .= "<div class='grid grid-cols-2 gap-2 mt-3 text-xs bg-emerald-100/50 p-3 rounded-xl border border-emerald-200/50'>";
                    $success_msg .= "<div><span class='text-emerald-600 block mb-0.5 text-[10px] uppercase tracking-widest'>Company</span><span class='font-black text-slate-800'>" . htmlspecialchars($_POST['company_name_text']) . "</span></div>";
                    $success_msg .= "<div><span class='text-emerald-600 block mb-0.5 text-[10px] uppercase tracking-widest'>Project</span><span class='font-black text-slate-800'>" . htmlspecialchars($_POST['project_name_text']) . "</span></div>";
                    $success_msg .= "</div>";
                    
                    $success_msg .= "<ul class='mt-3 space-y-1 text-sm font-medium text-slate-600'>";
                    $success_msg .= "<li class='flex items-center gap-2'><i class='ph-fill ph-check-circle text-emerald-500'></i> Rows imported: <strong class='text-slate-800 ml-auto'>$total_rows_processed</strong></li>";
                    if ($total_rows_skipped_duplicate > 0) {
                        $success_msg .= "<li class='flex items-center gap-2'><i class='ph-fill ph-warning-circle text-amber-500'></i> Duplicates skipped: <strong class='text-slate-800 ml-auto'>$total_rows_skipped_duplicate</strong></li>";
                    }
                    if ($total_rows_skipped_empty > 0) {
                        $success_msg .= "<li class='flex items-center gap-2'><i class='ph-fill ph-x-circle text-red-500'></i> Empty rows skipped: <strong class='text-slate-800 ml-auto'>$total_rows_skipped_empty</strong></li>";
                    }
                    $success_msg .= "</ul>";

                    $alert_output = tailwindAlert('success', $success_msg);

                } 

            } catch (PDOException $e) {
                if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
                $alert_output = tailwindAlert('error', "Database Error: " . $e->getMessage());
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
                $alert_output = tailwindAlert('error', "File Reading Error (XLSX): " . $e->getMessage());
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
                $alert_output = tailwindAlert('error', "Processing Error: " . $e->getMessage());
            }

        } else {
            $alert_output = tailwindAlert('error', "Failed to upload file. Error code: " . $_FILES['csv_file']['error']);
        }
    }
}
?>

<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    
    /* Drag and Drop Zone styling */
    .upload-zone { border: 2px dashed #c7d2fe; background-color: #f8fafc; transition: all 0.3s ease; }
    .upload-zone:hover, .upload-zone.dragover { border-color: #6366f1; background-color: #eef2ff; }
    .dark .upload-zone { background-color: rgba(30, 41, 59, 0.5); border-color: rgba(99, 102, 241, 0.3); }
    .dark .upload-zone:hover, .dark .upload-zone.dragover { border-color: #6366f1; background-color: rgba(99, 102, 241, 0.1); }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600 dark:from-indigo-400 dark:to-purple-400 tracking-tight">
            Data Integration
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-cloud-arrow-up text-lg text-indigo-500"></i> Upload and parse injection reports (.CSV / .XLSX).
        </p>
    </div>
</div>

<?php echo $alert_output; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    
    <div class="lg:col-span-8 animate-fade-in-up" style="animation-delay: 0.1s;">
        <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between">
                <h4 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-file-arrow-up text-indigo-500 text-xl"></i> Upload Report Form
                </h4>
            </div>
            
            <div class="p-8">
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    
                    <div class="mb-6 p-5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                        <h6 class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="ph-fill ph-plugs"></i> Target Assignment
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="company_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Company</label>
                                <div class="relative">
                                    <i class="ph-fill ph-buildings absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <select class="w-full appearance-none rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 pl-11 text-sm font-medium outline-none focus:border-indigo-500 shadow-sm cursor-pointer dark:text-white transition-all" id="company_id" name="company_id" required>
                                        <option value="">-- Select Company --</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>">
                                                <?php echo htmlspecialchars($company['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <div>
                                <label for="project_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Project</label>
                                <div class="relative">
                                    <i class="ph-fill ph-folder-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <select class="w-full appearance-none rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 pl-11 text-sm font-medium outline-none focus:border-indigo-500 shadow-sm cursor-pointer disabled:opacity-50 disabled:bg-slate-100 disabled:cursor-not-allowed dark:text-white transition-all" id="project_id" name="project_id" required disabled>
                                        <option value="">-- Select Company First --</option>
                                    </select>
                                    <i class="ph ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="company_name_text" id="company_name_text">
                    <input type="hidden" name="project_name_text" id="project_name_text">

                    <div class="mb-6">
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Source File (.CSV / .XLSX)</label>
                        <div class="upload-zone relative rounded-2xl flex flex-col items-center justify-center p-12 text-center" id="dropZoneContainer">
                            <input type="file" id="csv_file" name="csv_file" accept=".csv, .xlsx" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="updateFileName(this)">
                            
                            <div class="pointer-events-none flex flex-col items-center" id="uploadStateIdle">
                                <i class="ph-fill ph-file-xls text-6xl text-indigo-300 dark:text-indigo-500/50 mb-4 transition-transform group-hover:-translate-y-1"></i>
                                <h5 class="text-base font-bold text-slate-700 dark:text-slate-200 mb-1">Click to browse or drag file here</h5>
                                <p class="text-xs font-medium text-slate-500">Maximum file size 50MB.</p>
                            </div>

                            <div class="pointer-events-none flex flex-col items-center hidden" id="uploadStateSelected">
                                <i class="ph-fill ph-file-text text-6xl text-emerald-400 mb-4"></i>
                                <h5 class="text-base font-black text-emerald-600 dark:text-emerald-400 mb-1" id="fileNameDisplay">filename.csv</h5>
                                <p class="text-xs font-medium text-slate-500">Ready to upload.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="submit" name="submit" class="group relative inline-flex items-center justify-center gap-2 px-8 py-3.5 text-sm font-bold text-white transition-all bg-indigo-600 rounded-xl hover:bg-indigo-700 hover:shadow-lg hover:shadow-indigo-500/30 active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 w-full sm:w-auto">
                            <i class="ph-bold ph-rocket-launch text-lg group-hover:-translate-y-0.5 group-hover:translate-x-0.5 transition-transform"></i>
                            <span>Process Data</span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <div class="lg:col-span-4 animate-fade-in-up" style="animation-delay: 0.2s;">
        <div class="rounded-3xl bg-white dark:bg-[#24303F] p-8 shadow-soft border border-slate-100 dark:border-slate-800 relative overflow-hidden h-full">
            <div class="absolute top-0 right-0 p-4 opacity-10 pointer-events-none">
                <i class="ph-fill ph-gear text-9xl"></i>
            </div>
            
            <h4 class="text-base font-black text-slate-800 dark:text-white mb-6 flex items-center gap-2 relative z-10">
                <i class="ph-fill ph-info text-indigo-500"></i> Processing Workflow
            </h4>
            
            <div class="space-y-6 relative z-10">
                <div class="flex gap-4 items-start">
                    <div class="h-8 w-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black text-sm shrink-0 border border-indigo-100 dark:border-indigo-500/20 shadow-sm">1</div>
                    <div>
                        <p class="text-sm font-bold text-slate-800 dark:text-white mb-1">Target Mapping</p>
                        <p class="text-xs text-slate-500 leading-relaxed">Select the exact <b>Company</b> and <b>Project</b> where this data should be assigned.</p>
                    </div>
                </div>
                <div class="flex gap-4 items-start">
                    <div class="h-8 w-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black text-sm shrink-0 border border-indigo-100 dark:border-indigo-500/20 shadow-sm">2</div>
                    <div>
                        <p class="text-sm font-bold text-slate-800 dark:text-white mb-1">File Header Check</p>
                        <p class="text-xs text-slate-500 leading-relaxed">Ensure your file contains the following exact headers:</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-1 rounded text-slate-600 dark:text-slate-300">Submit Time</span>
                            <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-1 rounded text-slate-600 dark:text-slate-300">Request ID</span>
                            <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-1 rounded text-slate-600 dark:text-slate-300">Target MSISDN</span>
                            <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-1 rounded text-slate-600 dark:text-slate-300">Package Name</span>
                            <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2 py-1 rounded text-slate-600 dark:text-slate-300">Status</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-4 items-start">
                    <div class="h-8 w-8 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black text-sm shrink-0 border border-indigo-100 dark:border-indigo-500/20 shadow-sm">3</div>
                    <div>
                        <p class="text-sm font-bold text-slate-800 dark:text-white mb-1">Smart Parsing</p>
                        <p class="text-xs text-slate-500 leading-relaxed">The system will automatically extract Quota values from the Package Name and skip duplicate Request IDs.</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800 relative z-10">
                <a href="injection_calendar.php" class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors group">
                    View Results in Calendar <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
        </div>
    </div>

</div>

<script src="assets/extensions/jquery/jquery.min.js"></script>
<script>
$(document).ready(function() {
    // Dropdown Dependency Logic
    $('#company_id').on('change', function() {
        var companyId = $(this).val();
        var companyName = $(this).find('option:selected').text();
        $('#company_name_text').val($.trim(companyName));
        
        var $projectDropdown = $('#project_id');
        $projectDropdown.prop('disabled', true).html('<option value="">Loading projects...</option>');
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
                        $projectDropdown.html('<option value="">-- No projects found --</option>');
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
    
    // Save project name on select
    $('#project_id').on('change', function() {
        var projectName = $(this).find('option:selected').text();
        $('#project_name_text').val($.trim(projectName));
    });

    // Drag and Drop Zone Logic (Visuals)
    const dropZone = document.getElementById('dropZoneContainer');
    const fileInput = document.getElementById('csv_file');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });
});

// Update File Name Display
function updateFileName(input) {
    if (input.files && input.files[0]) {
        var fileName = input.files[0].name;
        document.getElementById('fileNameDisplay').textContent = fileName;
        document.getElementById('uploadStateIdle').classList.add('hidden');
        document.getElementById('uploadStateSelected').classList.remove('hidden');
    } else {
        document.getElementById('uploadStateIdle').classList.remove('hidden');
        document.getElementById('uploadStateSelected').classList.add('hidden');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>