<?php
// =========================================================================
// FILE 2: sim_tracking_upload.php
// DESC: Dedicated Page for Bulk Uploading SIM Data
// =========================================================================
ini_set('display_errors', 0); error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/functions.php'; 
require_once 'includes/header.php'; 
require_once 'includes/sidebar.php'; 
require_once 'includes/sim_helper.php'; 

$db = db_connect();

// Fetch PO Provider yang BELUM pernah di-upload ke inventory
$list_providers = [];
if($db) {
    try { 
        $sql = "SELECT id, po_number, batch_name, sim_qty 
                FROM sim_tracking_po 
                WHERE type='provider' 
                AND id NOT IN (SELECT DISTINCT po_provider_id FROM sim_inventory) 
                ORDER BY id DESC";
        if($db instanceof PDO) {
            $list_providers = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $res = mysqli_query($db, $sql);
            while($r = mysqli_fetch_assoc($res)) $list_providers[] = $r;
        }
    } catch(Exception $e){}
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
    body { background-color: #f8fafc; font-family: 'Plus Jakarta Sans', sans-serif; color: #334155; }
    
    .card-wizard { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; margin-top: 20px; }
    .card-header-wiz { background: #fff; padding: 25px 40px; border-bottom: 1px solid #e2e8f0; }
    .card-body-wiz { padding: 40px; }
    
    .step-indicator { display: flex; align-items: center; justify-content: space-between; max-width: 600px; margin: 0 auto; }
    .step-item { display: flex; align-items: center; gap: 12px; color: #94a3b8; font-weight: 600; font-size: 0.95rem; }
    .step-item.active { color: #4f46e5; }
    .step-circle { width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; transition: 0.3s; }
    .step-item.active .step-circle { background: #4f46e5; color: #fff; box-shadow: 0 0 0 4px #e0e7ff; }
    .step-line { flex-grow: 1; height: 2px; background: #e2e8f0; margin: 0 20px; }
    
    .upload-zone { border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 16px; padding: 60px 20px; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
    .upload-zone:hover { border-color: #4f46e5; background: #eef2ff; }
    .upload-icon { font-size: 3.5rem; color: #94a3b8; margin-bottom: 15px; display: block; }
    
    .progress-cont { display: none; margin-top: 40px; }
    .progress-bar-custom { background-color: #4f46e5; height: 100%; width: 0%; transition: width 0.3s ease; border-radius: 6px; }
    
    .preview-box { display: none; text-align: center; padding-top: 20px; }
    .success-anim { width: 80px; height: 80px; background: #dcfce7; color: #166534; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 20px auto; }
    .table-preview { width: 100%; margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .table-preview th { background: #f8fafc; font-size: 0.75rem; text-transform: uppercase; padding: 12px; border-bottom: 1px solid #e2e8f0; }
    .table-preview td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
    
    .btn-action { padding: 12px 30px; font-weight: 600; border-radius: 8px; transition: 0.2s; }
    .btn-upload { background: #4f46e5; color: white; border: none; }
    .btn-upload:hover { background: #4338ca; transform: translateY(-1px); color: white; }
    .btn-back { background: white; border: 1px solid #e2e8f0; color: #64748b; }
    .btn-back:hover { background: #f8fafc; color: #334155; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="fw-bold text-dark mb-1">Bulk Inventory Upload</h3><p class="text-muted small m-0">Import large datasets safely.</p></div>
        <a href="sim_tracking_status.php" class="btn btn-back btn-action"><i class="bi bi-arrow-left me-2"></i> Back to Dashboard</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-10">
            <div class="card-wizard">
                <div class="card-header-wiz">
                    <div class="step-indicator">
                        <div class="step-item active" id="step1-ind"><div class="step-circle">1</div><span>Source</span></div>
                        <div class="step-line"></div>
                        <div class="step-item" id="step2-ind"><div class="step-circle">2</div><span>Upload</span></div>
                        <div class="step-line"></div>
                        <div class="step-item" id="step3-ind"><div class="step-circle">3</div><span>Finish</span></div>
                    </div>
                </div>
                
                <div class="card-body-wiz">
                    <div id="alertArea"></div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_master_bulk">
                        <input type="hidden" name="is_ajax" value="1">

                        <div class="row mb-5">
                            <div class="col-md-7">
                                <label class="form-label fw-bold text-dark mb-2">1. Select Provider PO <span class="text-danger">*</span></label>
                                <select name="po_provider_id" id="poSelect" class="form-select form-select-lg shadow-none border-secondary-subtle" required onchange="fetchBatchInfo(this.value)">
                                    <option value="">-- Choose available PO --</option>
                                    <?php foreach($list_providers as $p): ?>
                                        <option value="<?=$p['id']?>"><?=$p['po_number']?> (Qty: <?=number_format($p['sim_qty'])?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold text-dark mb-2">Batch Name (Locked)</label>
                                <input type="text" name="activation_batch" id="batchInput" class="form-control form-control-lg bg-light text-secondary" placeholder="Auto-filled from PO..." readonly required>
                                <div class="form-text text-muted mt-1 small"><i class="bi bi-lock-fill"></i> Read-only from database</div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label fw-bold text-dark mb-2">2. Upload Data File <span class="text-danger">*</span></label>
                            <div class="upload-zone" id="dropZone">
                                <input type="file" name="upload_file" id="fileInput" style="position:absolute; width:100%; height:100%; top:0; left:0; opacity:0; cursor:pointer;" required accept=".csv, .xlsx">
                                <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                <h5 class="fw-bold text-dark mb-2" id="dragText">Click or Drag & Drop File Here</h5>
                                <p class="text-muted mb-0">Supported: .csv, .xlsx</p>
                                <div class="mt-3"><span class="badge bg-light text-dark border">Header Required: MSISDN</span></div>
                                <div class="file-info" id="fileInfo"></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-bold text-dark">Upload Date:</span>
                                <input type="date" name="date_field" class="form-control" value="<?=date('Y-m-d')?>" required>
                            </div>
                            <button type="submit" class="btn btn-upload btn-action btn-lg px-5 shadow-sm" id="btnSubmit"><i class="bi bi-cloud-upload-fill me-2"></i> Start Bulk Upload</button>
                        </div>
                    </form>

                    <div class="progress-cont" id="progCont">
                        <div class="d-flex justify-content-between fw-bold mb-1"><span id="progText">Uploading...</span><span id="progPct">0%</span></div>
                        <div class="progress" style="height:12px;"><div class="progress-bar progress-bar-custom progress-bar-striped progress-bar-animated" id="progBar" style="width:0%"></div></div>
                    </div>

                    <div class="preview-box" id="previewBox">
                        <div class="success-anim"><i class="bi bi-check-lg"></i></div>
                        <h3 class="fw-bold text-dark mb-2">Upload Complete!</h3>
                        <p class="text-muted mb-4" id="successMsg">Data successfully imported.</p>
                        
                        <div class="text-start">
                            <h6 class="fw-bold text-dark border-bottom pb-2">Preview (First 5 Rows)</h6>
                            <div class="table-preview">
                                <table class="table table-striped mb-0" id="prevTable"><thead><tr><th>MSISDN</th><th>ICCID</th><th>IMSI</th><th>SN</th></tr></thead><tbody></tbody></table>
                            </div>
                        </div>

                        <div class="mt-5">
                            <a href="sim_tracking_status.php" class="btn btn-upload btn-action me-2">Go to Dashboard</a>
                            <button onclick="location.reload()" class="btn btn-back btn-action">Upload Another File</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    // 1. AUTO FILL BATCH (AJAX CALL)
    function fetchBatchInfo(id) {
        if(!id) { $('#batchInput').val(''); return; }
        
        // Panggil backend process_sim_tracking.php
        $.post('process_sim_tracking.php', { action: 'get_po_details', id: id }, function(res){
            if(res.status === 'success') {
                $('#batchInput').val(res.batch_name || 'BATCH 1'); // Isi dan biarkan readonly
            } else {
                alert(res.message);
                $('#poSelect').val(''); // Reset jika error
                $('#batchInput').val('');
            }
        }, 'json');
    }

    // 2. DRAG & DROP UI
    const dz = document.getElementById('dropZone'); const fi = document.getElementById('fileInput');
    ['dragenter', 'dragover'].forEach(e => dz.addEventListener(e, (ev)=>{ ev.preventDefault(); dz.style.backgroundColor='#eef2ff'; dz.style.borderColor='#4f46e5'; }));
    ['dragleave', 'drop'].forEach(e => dz.addEventListener(e, (ev)=>{ ev.preventDefault(); dz.style.backgroundColor='#f8fafc'; dz.style.borderColor='#cbd5e1'; }));
    fi.addEventListener('change', function() {
        if(this.files.length > 0) {
            $('#dragText').hide();
            $('.upload-icon').removeClass('bi-cloud-arrow-up').addClass('bi-file-earmark-excel-fill text-success');
            $('#fileInfo').text(this.files[0].name).fadeIn();
            $('#step2-ind').addClass('active');
        }
    });

    // 3. UPLOAD HANDLER
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault();
        let fd = new FormData(this);
        $('#btnSubmit').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing...');
        $('#uploadForm').css('opacity', '0.5'); $('#progCont').slideDown(); $('#alertArea').html('');

        $.ajax({
            xhr: function() { var xhr = new window.XMLHttpRequest(); xhr.upload.addEventListener("progress", function(evt) { if (evt.lengthComputable) { var pct = Math.round((evt.loaded / evt.total) * 100); $('#progBar').css('width', pct + '%'); $('#progPct').text(pct + '%'); if(pct === 100) $('#progText').text('Server Validating...'); } }, false); return xhr; },
            type: 'POST', url: 'process_sim_tracking.php', data: fd, contentType: false, processData: false, dataType: 'json',
            success: function(res) {
                $('#progCont').hide(); $('#uploadForm').hide();
                if (res.status === 'success') {
                    $('#step3-ind').addClass('active'); $('#previewBox').fadeIn(); $('#successMsg').html(`Successfully imported <b>${res.count}</b> records.`);
                    let tbody = '';
                    if(res.preview && res.preview.length > 0) { res.preview.forEach(row => { tbody += `<tr><td class="fw-bold font-monospace text-primary">${row.msisdn}</td><td>${row.iccid||'-'}</td><td>${row.imsi||'-'}</td><td>${row.sn||'-'}</td></tr>`; }); }
                    $('#prevTable tbody').html(tbody);
                } else {
                    $('#uploadForm').show().css('opacity', '1'); $('#btnSubmit').prop('disabled', false).text('Start Bulk Upload');
                    $('#alertArea').html(`<div class="alert alert-danger shadow-sm border-0"><div class="fw-bold">Upload Failed</div><div>${res.message}</div></div>`);
                }
            },
            error: function(xhr) {
                $('#progCont').hide(); $('#uploadForm').show().css('opacity', '1'); $('#btnSubmit').prop('disabled', false).text('Start Bulk Upload');
                $('#alertArea').html(`<div class="alert alert-danger shadow-sm border-0"><div class="fw-bold">System Error</div><div>${xhr.responseText.substring(0, 100)}</div></div>`);
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>