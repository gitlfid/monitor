<?php
/*
 File: sim_information.php
 ===========================================================
 Status: UPDATED (Multi-tenant / Company Filter Support)
*/
require_once 'includes/header.php';
require_once 'includes/sidebar.php'; 
require_once 'includes/config.php'; 
$db = db_connect();

// --- LOGIKA FILTER USER (MULTI-TENANT) ---
$user_company_id = $_SESSION['company_id'] ?? null;

// 1. Ambil Data Company (Filter jika user terbatas)
if ($user_company_id) {
    // User Terbatas: Hanya ambil perusahaannya sendiri
    $stmt_comp = $db->prepare("SELECT * FROM companies WHERE id = ? ORDER BY company_name ASC");
    $stmt_comp->execute([$user_company_id]);
    $companies = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin: Ambil semua
    $companies = $db->query("SELECT * FROM companies ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// 2. Ambil Data Batches (History) - Filter jika user terbatas
$sql_batches = "SELECT b.*, c.company_name, p.project_name 
                FROM sim_batches b 
                LEFT JOIN companies c ON b.company_id = c.id 
                LEFT JOIN sim_projects p ON b.project_id = p.id";

if ($user_company_id) {
    $sql_batches .= " WHERE b.company_id = " . intval($user_company_id);
}

$sql_batches .= " ORDER BY b.upload_date DESC";
$batches = $db->query($sql_batches)->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .card-force-light { background-color: #ffffff !important; color: #333333 !important; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .nav-tabs .nav-link.active { border-bottom: 3px solid #435ebe !important; color: #435ebe !important; font-weight: bold; background: transparent; }
    .nav-tabs .nav-link { color: #6c757d; font-weight: 500; }
    .table td { vertical-align: middle; white-space: nowrap; }
    .upload-zone { border: 2px dashed #dce7f1; background-color: #fcfdfe; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; transition: 0.3s; }
    .upload-zone:hover { border-color: #435ebe; background-color: #eef3ff; }
    #progress-container { z-index: 99999 !important; background: rgba(255,255,255,0.98); }
    .form-control[type=file] { line-height: 1.5; }
    .badge-hover:hover { cursor: pointer; text-decoration: underline; opacity: 0.8; }
    .search-box-container { background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e9ecef; }
    .action-btn-group { display: flex; gap: 5px; justify-content: center; }
    .input-group .btn-action { border-color: #ced4da; background: #fff; color: #6c757d; }
    .input-group .btn-action:hover { background: #f2f4f6; color: #435ebe; }
</style>

<div class="page-heading mb-4">
    <h3>SIM Information</h3>
    <p class="text-subtitle text-muted">Batch Upload & Search Center</p>
</div>

<div class="page-content">
    <div id="progress-container" class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center">
        <div class="text-center" style="width: 400px;">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
            <h5 class="text-primary" id="progress-title">Processing...</h5>
            <div class="progress mb-2" style="height: 15px;"><div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div></div>
            <span id="progress-status" class="text-muted small">Initializing...</span>
        </div>
    </div>

    <div class="card card-force-light shadow-sm">
        <div class="card-header bg-white p-0">
            <ul class="nav nav-tabs ps-3" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#list">Data & Search</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#upload">New Upload</a></li>
            </ul>
        </div>
        
        <div class="card-body p-4">
            <div class="tab-content">
                
                <div class="tab-pane fade show active" id="list">
                    
                    <div class="search-box-container">
                        <div class="d-flex justify-content-between align-items-center mb-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#searchCollapse">
                            <h5 class="text-primary mb-0"><i class="bi bi-search me-2"></i>Advanced Search</h5>
                            <i class="bi bi-chevron-down text-muted"></i>
                        </div>
                        <div class="collapse" id="searchCollapse">
                            <form id="searchForm">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="fw-bold small">Company</label>
                                        <select class="form-select form-select-sm" name="company_id" id="search_company_id" onchange="loadProjectsAndBatches(this.value)">
                                            <?php if (!$user_company_id): ?>
                                                <option value="">-- All Companies --</option>
                                            <?php endif; ?>
                                            
                                            <?php foreach($companies as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= ($user_company_id == $c['id']) ? 'selected' : '' ?>>
                                                    <?= $c['company_name'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold small">Project</label>
                                        <select class="form-select form-select-sm" name="project_id" id="search_project_id" onchange="loadBatches($('#search_company_id').val(), this.value)">
                                            <option value="">-- All Projects --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold small">Batch Name</label>
                                        <select class="form-select form-select-sm" name="batch_search" id="search_batch_name">
                                            <option value="">-- All Batches --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold small">General Search</label>
                                        <input type="text" class="form-control form-control-sm" name="general_search" placeholder="MSISDN, ICCID, IMSI, or SN...">
                                    </div>
                                    <div class="col-12">
                                        <label class="fw-bold small text-primary">Bulk Search (Multiple)</label>
                                        <textarea class="form-control form-control-sm" name="bulk_search" rows="2" placeholder="Paste list here (comma, space, or newline)..."></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-sm btn-secondary me-2" id="btn-reset-search">Reset</button>
                                        <button type="button" class="btn btn-sm btn-primary px-4 fw-bold" id="btn-do-search">Search Data</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div id="historyView">
                        <h6 class="text-muted mb-3 border-bottom pb-2">Upload History</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100" id="table-batches">
                                <thead class="bg-light"><tr><th>Date</th><th>Company</th><th>Project</th><th>Batch Name</th><th class="text-center">Qty</th><th class="text-center">Docs</th><th class="text-center">Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach($batches as $b): ?>
                                    <tr>
                                        <td><?= date('d M Y, H:i', strtotime($b['upload_date'])) ?></td>
                                        <td><?= htmlspecialchars($b['company_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($b['project_name'] ?? '-') ?></td>
                                        <td><span class="badge bg-light text-primary border border-primary badge-hover" onclick="viewBatchDetails(<?= $b['id'] ?>, '<?= htmlspecialchars($b['batch_name'], ENT_QUOTES) ?>')"><?= htmlspecialchars($b['batch_name']) ?></span></td>
                                        <td class="text-center fw-bold"><?= number_format($b['quantity'] ?? 0) ?></td>
                                        <td class="text-center">
                                            <?php if($b['po_client_file']): ?><a href="<?= $b['po_client_file'] ?>" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-person"></i></a><?php endif; ?>
                                            <?php if($b['po_linksfield_file']): ?><a href="<?= $b['po_linksfield_file'] ?>" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-building"></i></a><?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-btn-group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewBatchDetails(<?= $b['id'] ?>, '<?= htmlspecialchars($b['batch_name'], ENT_QUOTES) ?>')"><i class="bi bi-eye"></i></button>
                                                <?php if (!$user_company_id): // Hanya Admin yang bisa edit/delete ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="bukaModalEdit(<?= $b['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteBatch(<?= $b['id'] ?>)"><i class="bi bi-trash"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="searchView" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary mb-0">Search Results</h6>
                            <button class="btn btn-sm btn-secondary" onclick="$('#btn-reset-search').click()"><i class="bi bi-arrow-left"></i> Back to History</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped w-100" id="table-search-results">
                                <thead class="bg-light"><tr><th>MSISDN</th><th>ICCID</th><th>IMSI</th><th>SN</th><th>Batch</th><th>Project</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="upload">
                    <form id="uploadForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="fw-bold">Company</label>
                                <div class="input-group">
                                    <select class="form-select" name="company_id" id="upload_company_id" onchange="loadProjects(this.value)">
                                        <?php if (!$user_company_id): ?>
                                            <option value="">-- Select --</option>
                                        <?php endif; ?>
                                        
                                        <?php foreach($companies as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($user_company_id == $c['id']) ? 'selected' : '' ?>>
                                                <?= $c['company_name'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!$user_company_id): ?>
                                        <button type="button" class="btn btn-action" onclick="openModal('company','add')">+</button>
                                        <button type="button" class="btn btn-action text-danger" onclick="deleteData('company')"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Project</label>
                                <div class="input-group">
                                    <select class="form-select" name="project_id" id="upload_project_id" disabled>
                                        <option value="">-- Select Company First --</option>
                                    </select>
                                    <?php if (!$user_company_id): ?>
                                        <button type="button" class="btn btn-action" onclick="openModal('project','add')">+</button>
                                        <button type="button" class="btn btn-action text-danger" onclick="deleteData('project')"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12"><label class="fw-bold">Batch Name (Auto)</label><input type="text" class="form-control bg-light" name="batch_name" id="batch_name" readonly></div>
                            <div class="col-12 mt-4"><div class="upload-zone" onclick="$('#excel_file').click()"><h4 class="text-primary"><i class="bi bi-cloud-upload"></i> Upload Excel</h4><input type="file" class="form-control w-50 mx-auto mt-3 mb-3" id="excel_file" accept=".xlsx,.xls,.csv" onchange="autoFillFromExcel(this)"><button type="button" class="btn btn-primary px-4 rounded-pill" id="btn-preview">Validate & Preview</button></div></div>
                        </div>
                        <div id="preview-section" class="mt-4 p-4 border rounded bg-white shadow-sm" style="display:none;">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4 border-end"><h5>Total: <span class="text-primary fw-bold" id="disp_qty">0</span></h5><input type="hidden" id="quantity" name="quantity"><input type="hidden" id="temp_csv_path"></div>
                                <div class="col-md-8"><div class="row g-2"><div class="col-6"><label class="small text-muted">PO Client</label><input type="file" name="po_client_file" class="form-control form-control-sm mb-1" onchange="autoFillPO(this,'po_client_number')"><input type="text" name="po_client_number" id="po_client_number" class="form-control form-control-sm bg-white"></div><div class="col-6"><label class="small text-muted">PO LF</label><input type="file" name="po_linksfield_file" class="form-control form-control-sm mb-1" onchange="autoFillPO(this,'po_linksfield_number')"><input type="text" name="po_linksfield_number" id="po_linksfield_number" class="form-control form-control-sm bg-white"></div></div></div>
                            </div>
                            <div class="table-responsive border rounded mb-3" style="max-height:250px;"><table class="table table-sm table-striped mb-0 small"><thead class="table-light sticky-top"><tr><th>#</th><th>MSISDN</th><th>IMSI</th><th>SN</th><th>ICCID</th></tr></thead><tbody id="preview-body"></tbody></table></div>
                            <div class="d-flex justify-content-end align-items-center gap-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="checkConfirm"><label class="form-check-label fw-bold small" for="checkConfirm">Data Valid</label></div><button type="button" id="btn-start-upload" class="btn btn-success px-5 fw-bold" disabled>START UPLOAD</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditBatch" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title fw-bold">Edit Batch</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="formEditBatch"><input type="hidden" name="action" value="edit_batch"><input type="hidden" name="id" id="edit_id"><div class="row g-3"><div class="col-md-6"><label>Company</label><select class="form-select" name="company_id" id="edit_company_id" onchange="loadProjects(this.value, 'edit_project_id')"><?php foreach($companies as $c): ?><option value="<?= $c['id'] ?>"><?= $c['company_name'] ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label>Project</label><div class="input-group"><select class="form-select" name="project_id" id="edit_project_id"></select><button type="button" class="btn btn-outline-secondary" onclick="openModal('project','add')">+</button></div></div><div class="col-12"><label>Batch Name</label><input type="text" class="form-control" name="batch_name" id="edit_batch_name"></div><div class="col-md-6"><label>PO Client</label><input type="file" name="po_client_file" class="form-control form-control-sm mb-1" onchange="autoFillPO(this,'edit_po_client_number')"><input type="text" name="po_client_number" id="edit_po_client_number" class="form-control form-control-sm"></div><div class="col-md-6"><label>PO LF</label><input type="file" name="po_linksfield_file" class="form-control form-control-sm mb-1" onchange="autoFillPO(this,'edit_po_linksfield_number')"><input type="text" name="po_linksfield_number" id="edit_po_linksfield_number" class="form-control form-control-sm"></div></div></form></div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" onclick="simpanEdit()">Save Changes</button></div></div></div></div>

<div class="modal fade" id="crudModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header border-bottom-0"><h5 class="modal-title" id="crudTitle">Form</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="crud_type"><input type="hidden" id="crud_action"><input type="hidden" id="crud_id"><div id="div_company_select" class="mb-3" style="display:none;"><label>Company</label><select id="modal_company_id" class="form-select bg-light"><?php foreach($companies as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?></select></div><label>Name</label><input type="text" id="crud_name" class="form-control"></div><div class="modal-footer border-top-0"><button class="btn btn-primary" onclick="submitCrud()">Save</button></div></div></div></div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="detailModalLabel">Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table table-striped w-100 mb-0" id="table-detail-content"><thead class="bg-light"><tr><th>No</th><th>MSISDN</th><th>IMSI</th><th>SN</th><th>ICCID</th></tr></thead><tbody></tbody></table></div></div></div></div></div>

<script src="assets/extensions/jquery/jquery.min.js"></script>
<script src="assets/extensions/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="assets/extensions/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>

<script>
const API_URL = 'api_sim_upload.php';
const CHUNK_SIZE = 1000; 
var crudModal, detailModal, editBatchModal, tableDetail;

$(document).ready(function() {
    $('#progress-container').addClass('d-none');
    
    var m1=document.getElementById('crudModal'); if(m1) crudModal=new bootstrap.Modal(m1);
    var m2=document.getElementById('detailModal'); if(m2) detailModal=new bootstrap.Modal(m2);
    var m3=document.getElementById('modalEditBatch'); if(m3) editBatchModal=new bootstrap.Modal(m3);

    if($.fn.DataTable) { 
        $('#table-batches').DataTable({ 
            "order": [[ 0, "desc" ]],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, 100, 200, -1], [10, 25, 50, 100, 200, "All"]]
        }); 
    }
    
    // INITIAL LOAD (UPDATED for Filter Logic)
    // Ambil nilai awal dari dropdown (yang mungkin sudah terset jika user terbatas)
    var initialCid = $('#search_company_id').val();
    
    // Load Batch Dropdown
    loadBatches(initialCid, '');

    // Jika user terbatas (ada company default), load projects juga secara otomatis
    if (initialCid) {
        // Untuk tab Search
        loadProjects(initialCid, 'search_project_id');
        // Untuk tab Upload
        loadProjects(initialCid, 'upload_project_id');
    }

    // Event listener untuk auto-generate batch name saat project dipilih di tab upload
    $('#upload_project_id').change(function(){ let pid=$(this).val(); if(pid) $.post(API_URL,{action:'get_next_batch_name',project_id:pid},function(res){if(res.status)$('#batch_name').val(res.next_name)},'json'); });

    // SEARCH LOGIC
    $('#btn-do-search').click(function() {
        let btn = $(this); let ori = btn.html(); btn.html('Searching...').prop('disabled', true);
        $('#historyView').hide(); $('#searchView').fadeIn();
        
        if ($.fn.DataTable.isDataTable('#table-search-results')) { $('#table-search-results').DataTable().destroy(); }

        $('#table-search-results').DataTable({
            "processing": true,
            "serverSide": true,
            "pageLength": 50,
            "lengthMenu": [[50, 100, 200, 500], [50, 100, 200, 500]],
            "ajax": {
                "url": API_URL,
                "type": "POST",
                "data": function(d) {
                    d.action = 'search_sim_data';
                    d.company_id = $('#search_company_id').val();
                    d.project_id = $('#search_project_id').val();
                    d.batch_search = $('#search_batch_name').val();
                    d.general_search = $('input[name="general_search"]').val();
                    d.bulk_search = $('textarea[name="bulk_search"]').val();
                }
            },
            "columns": [
                { "data": 0 }, 
                { "data": 1 }, 
                { "data": 2 }, 
                { "data": 3 }, 
                { "data": 4, "render": function(d){ return '<span class="badge bg-light text-dark border">'+d+'</span>'} }, 
                { "data": 5 }
            ]
        });
        btn.html(ori).prop('disabled', false);
    });

    $('#btn-reset-search').click(function() {
        // Jangan reset Company jika user terbatas
        var $comp = $('#search_company_id');
        if ($comp.find('option[value=""]').length > 0) {
            $comp.val(''); // Admin: Reset ke kosong
        } else {
            $comp.val($comp.find('option:first').val()); // User: Reset ke company sendiri
        }
        
        $('#search_project_id').val('');
        $('#search_batch_name').val('');
        $('input[name="general_search"]').val('');
        $('textarea[name="bulk_search"]').val('');
        
        $('#table-search-results').DataTable().clear().destroy();
        $('#searchView').hide();
        $('#historyView').fadeIn();
    });

    // PREVIEW & UPLOAD
    $('#btn-preview').click(function(e) {
        e.stopPropagation();
        let file = $('#excel_file')[0].files[0];
        if(!file) return alert("Please select a file!");
        let btn = $(this).html('Loading...').prop('disabled', true);
        let fd = new FormData(); fd.append('action', 'preview_excel'); fd.append('excel_file', file);
        $.ajax({
            url: API_URL, type: 'POST', data: fd, contentType:false, processData:false, dataType:'json',
            success: function(res){
                btn.html('Validate & Preview').prop('disabled', false);
                if(res.status) {
                    $('#preview-section').slideDown(); $('#quantity').val(res.quantity); $('#disp_qty').text(res.quantity.toLocaleString()); $('#temp_csv_path').val(res.temp_csv_path);
                    if(res.po_client_val) $('#po_client_number').val(res.po_client_val); if(res.po_lf_val) $('#po_linksfield_number').val(res.po_lf_val);
                    let h=''; res.preview_rows.forEach((r,i)=>h+=`<tr><td>${i+1}</td><td>${r.msisdn}</td><td>${r.imsi}</td><td>${r.sn}</td><td>${r.iccid}</td></tr>`);
                    $('#preview-body').html(h);
                } else { alert(res.message); }
            }, error: function(){ btn.html('Retry').prop('disabled',false); alert('Connection Error'); }
        });
    });
    $('#checkConfirm').change(function(){ $('#btn-start-upload').prop('disabled', !this.checked); });
    $('#btn-start-upload').click(async function() {
        if(!confirm("Start Upload?")) return;
        $('#progress-container').removeClass('d-none'); $(this).prop('disabled', true);
        try {
            let fd = new FormData($('#uploadForm')[0]); fd.append('action', 'save_batch_header');
            let head = await $.ajax({url:API_URL, type:'POST', data:fd, contentType:false, processData:false});
            if(!head.status) throw new Error(head.message);
            let bid=head.batch_id, total=parseInt($('#quantity').val()), csv=$('#temp_csv_path').val(), proc=0;
            while(proc < total) {
                let pct = Math.round((proc/total)*100); $('#progress-bar').css('width', pct+'%'); $('#progress-status').text(`Uploading... ${proc}/${total}`);
                let res = await $.post(API_URL, {action:'process_chunk', batch_id:bid, csv_path:csv, start_line:proc, chunk_size:CHUNK_SIZE}, null, 'json');
                if(!res.status) throw new Error(res.message);
                proc += res.processed_count; if(res.processed_count==0) break;
            }
            $('#progress-bar').css('width', '100%'); $.post(API_URL, {action:'delete_temp_file', csv_path:csv});
            setTimeout(()=>{alert('Success!'); location.reload();}, 500);
        } catch(e) { alert(e.message); $('#progress-container').addClass('d-none'); $(this).prop('disabled',false); }
    });
});

// Load Projects & Batches Together
function loadProjectsAndBatches(cid) {
    loadProjects(cid, 'search_project_id');
    loadBatches(cid, '');
}

function loadProjects(cid, target='upload_project_id', selId=null) {
    let $el = $('#'+target).html('<option>Loading...</option>').prop('disabled', true);
    if(!cid) { $el.html('<option>-- Select Company --</option>'); return; }
    $.post(API_URL, {action:'get_projects', company_id:cid}, function(res){
        let h = '<option value="">-- Select Project --</option>';
        if(res.status && res.data) res.data.forEach(p => { let s = (selId == p.id) ? 'selected' : ''; h += `<option value="${p.id}" ${s}>${p.project_name}</option>`; });
        $el.html(h).prop('disabled', false);
    }, 'json');
    
    // If using Search Project ID, also reload batches dropdown
    if(target == 'search_project_id') {
        loadBatches(cid, '');
    }
}

function loadBatches(cid, pid) {
    let $el = $('#search_batch_name').html('<option>Loading...</option>').prop('disabled', true);
    $.post(API_URL, {action:'get_batches', company_id:cid, project_id:pid}, function(res){
        let h = '<option value="">-- All Batches --</option>';
        if(res.status && res.data) res.data.forEach(b => { h += `<option value="${b.batch_name}">${b.batch_name}</option>`; });
        $el.html(h).prop('disabled', false);
    }, 'json');
}

function bukaModalEdit(id) {
    $.post(API_URL, {action:'get_batch_header', id:id}, function(res){
        if(res.status) {
            let d = res.data;
            $('#edit_id').val(d.id); $('#edit_company_id').val(d.company_id); $('#edit_batch_name').val(d.batch_name);
            $('#edit_po_client_number').val(d.po_client_number); $('#edit_po_linksfield_number').val(d.po_linksfield_number);
            loadProjects(d.company_id, 'edit_project_id', d.project_id);
            editBatchModal.show();
        } else { alert(res.message); }
    }, 'json').fail(function(){ alert("Connection Error"); });
}
function simpanEdit() {
    let fd = new FormData(document.getElementById('formEditBatch'));
    $.ajax({ url: API_URL, type: 'POST', data: fd, contentType: false, processData: false, dataType: 'json', success: function(res) { alert(res.message); if(res.status) location.reload(); }, error: function() { alert('Error'); } });
}
function viewBatchDetails(id,name) {
    detailModal.show(); $('#detailModalLabel').text('Details: '+name);
    if($.fn.DataTable.isDataTable('#table-detail-content')) $('#table-detail-content').DataTable().destroy();
    tableDetail=$('#table-detail-content').DataTable({processing:true,serverSide:true,pageLength:50,lengthMenu:[[50,100,200,500],[50,100,200,500]],ajax:{url:API_URL,type:'POST',data:function(d){d.action='get_batch_details_server_side';d.batch_id=id;}},columns:[{className:'text-center',orderable:false},{orderable:true},{orderable:true},{orderable:true},{orderable:true}]});
}
function deleteBatch(id) { if(confirm("Delete?")) $.post(API_URL, {action:'delete_batch', id:id}, function(){location.reload()}, 'json'); }
function autoFillPO(inpt, tgt) { if(inpt.files[0]) $('#'+tgt).val(inpt.files[0].name.replace(/\.[^/.]+$/, "")); }
function autoFillFromExcel(inpt) { /* handled in preview */ }
function openModal(type, act) { $('#crud_type').val(type);$('#crud_action').val(act);$('#crud_name').val(''); $('#crudTitle').text((act=='add'?'Add ':'Edit ')+type);
    if(type=='project' && act=='add') $('#div_company_select').show(); else $('#div_company_select').hide();
    crudModal.show();
}
function submitCrud() { let d={action:$('#crud_action').val()+'_'+$('#crud_type').val(), name:$('#crud_name').val(), company_id:$('#modal_company_id').val(), id:$('#crud_id').val()}; $.post(API_URL,d,function(r){alert(r.message);if(r.status)location.reload();},'json'); }
function deleteData(type) { let id=(type=='company')?$('#upload_company_id').val():$('#upload_project_id').val(); if(!id)return alert('Select data'); if(confirm('Delete?'))$.post(API_URL,{action:'delete_'+type,id:id},function(r){alert(r.message);if(r.status)location.reload();},'json'); }
</script>

<?php require_once 'includes/footer.php'; ?>