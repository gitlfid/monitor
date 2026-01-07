<?php
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
    /* --- UI STYLING --- */
    .form-label { font-weight: 600; color: #444; font-size: 0.9rem; margin-bottom: 0.4rem; }
    .btn-manage { cursor: pointer; color: #435ebe; font-size: 0.85rem; margin-left: 5px; transition: 0.2s; }
    .btn-manage:hover { color: #25396f; transform: scale(1.1); }
    
    .card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-radius: 10px; }
    .nav-tabs .nav-link { color: #6c757d; font-weight: 500; }
    .nav-tabs .nav-link.active { border-bottom: 3px solid #435ebe; color: #435ebe; font-weight: bold; background: transparent; }
    
    .upload-box { border: 2px dashed #b6c2e2; background: #f8faff; border-radius: 10px; padding: 40px; text-align: center; cursor: pointer; transition: 0.3s; }
    .upload-box:hover { border-color: #435ebe; background: #eef3ff; }
    
    /* TABLE STYLING */
    .table thead th { 
        background-color: #f2f7ff; font-weight: 700; font-size: 0.8rem; 
        text-transform: uppercase; letter-spacing: 0.5px; color: #25396f; 
        vertical-align: middle; white-space: nowrap; padding: 15px; border-bottom: 2px solid #e0e6ed;
    }
    .table td { 
        vertical-align: middle; font-size: 0.9rem; border-bottom: 1px solid #f0f0f0; padding: 15px; 
    }
    
    /* Specific Column Widths */
    .col-date { white-space: nowrap; font-weight: 600; color: #666; width: 10%; }
    .col-company { font-weight: 700; color: #25396f; max-width: 200px; word-wrap: break-word; width: 20%; }
    .col-details { font-size: 0.85rem; color: #555; min-width: 220px; width: 25%; }
    .col-tracking { font-family: 'Consolas', monospace; color: #d63384; background: #fff0f6; padding: 4px 8px; border-radius: 4px; display: inline-block; font-size: 0.85rem; }
    
    /* Helper for Detail Rows */
    .detail-row { display: flex; align-items: center; margin-bottom: 4px; }
    .detail-label { width: 50px; color: #888; font-size: 0.75rem; text-transform: uppercase; font-weight: 600; }
    .detail-val { font-weight: 500; color: #333; }

    /* Action Buttons */
    .action-btn-group { display: flex; gap: 6px; justify-content: center; }
    .btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition:0.2s; border:1px solid transparent; }
    .btn-icon:hover { transform:translateY(-2px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    
    .btn-track { background:#e0f2fe; color:#0284c7; } .btn-track:hover { background:#0284c7; color:white; }
    .btn-edit { background:#fff8e1; color:#d97706; } .btn-edit:hover { background:#d97706; color:white; }
    .btn-delete { background:#fee2e2; color:#dc2626; } .btn-delete:hover { background:#dc2626; color:white; }

    /* TIMELINE STYLE */
    .tracking-timeline { list-style: none; padding-left: 25px; border-left: 2px solid #e0e0e0; margin-left: 15px; margin-top: 25px; position: relative; }
    .tracking-item { margin-bottom: 30px; position: relative; }
    .tracking-item::before { 
        content: ''; width: 14px; height: 14px; background: #fff; border: 3px solid #435ebe; border-radius: 50%; 
        position: absolute; left: -33px; top: 4px; z-index: 1; 
    }
    .tracking-item.latest::before { background: #435ebe; border-color: #435ebe; width: 18px; height: 18px; left: -35px; top: 2px; }
    .tracking-date { font-size: 0.8rem; color: #666; font-weight: bold; display: block; margin-bottom: 4px; }
    .tracking-status { font-weight: 700; font-size: 1rem; color: #25396f; margin-bottom: 4px; }
    .tracking-desc { font-size: 0.9rem; color: #555; background: #f8f9fa; padding: 12px 15px; border-radius: 8px; border: 1px solid #eee; line-height: 1.5; }
    .tracking-item.latest .tracking-desc { background: #eef3ff; border-left: 4px solid #435ebe; color: #1d3557; font-weight: 500; }
    
    /* TRACKING HEADER CARD (SYMMETRICAL & FULL TEXT) */
    .track-header-card { 
        background: #fff; 
        border: 1px solid #e0e0e0; 
        border-radius: 12px; 
        padding: 25px; 
        box-shadow: 0 8px 20px rgba(0,0,0,0.04); 
        margin-bottom: 30px; 
    }
    .track-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #999; font-weight: 700; display: block; margin-bottom: 8px; }
    .track-main-status { font-size: 1.25rem; font-weight: 800; color: #198754; letter-spacing: -0.5px; }
    
    /* Nama & Alamat dibuat wrap agar tidak terpotong */
    .track-name { font-size: 1.1rem; color: #25396f; font-weight: 700; margin-bottom: 5px; line-height: 1.3; word-wrap: break-word; }
    .track-address { font-size: 0.9rem; color: #555; line-height: 1.6; font-weight: 400; word-wrap: break-word; }
    
    .track-arrow-container { display: flex; align-items: center; justify-content: center; height: 100%; color: #ccc; font-size: 2rem; }
</style>

<div class="page-heading">
    <div class="page-title mb-4">
        <h3>Delivery Information</h3>
        <p class="text-subtitle text-muted">Manage shipping schedules, track packages, and master data.</p>
    </div>

    <section>
        <div class="card mb-4">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs ps-4" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#manual"><i class="bi bi-pencil-square me-2"></i>Manual Input</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#excel"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Import Excel</a></li>
                </ul>
            </div>
            
            <div class="card-body pt-4 px-4 pb-4">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="manual">
                        <form id="manualForm">
                            <input type="hidden" name="action" value="save_transaction">
                            <div class="row g-3">
                                <div class="col-md-3"><label class="form-label">Date *</label><input type="date" class="form-control" name="date" id="input_date" value="<?= date('Y-m-d') ?>"></div>
                                <div class="col-md-3"><label class="form-label">Company <i class="bi bi-gear-fill btn-manage" onclick="openMaster('company','Manage Company')"></i></label><select class="form-select" name="company" id="dd_company"></select></div>
                                <div class="col-md-3"><label class="form-label">Item Type <i class="bi bi-gear-fill btn-manage" onclick="openMaster('item','Manage Items')"></i></label><select class="form-select" name="item" id="dd_item"></select></div>
                                <div class="col-md-3"><label class="form-label">Data Type <i class="bi bi-gear-fill btn-manage" onclick="openMaster('data','Manage Data Types')"></i></label><select class="form-select" name="data" id="dd_data"></select></div>
                                <div class="col-md-4"><label class="form-label">Product Detail <i class="bi bi-gear-fill btn-manage" onclick="openMaster('product','Manage Products')"></i></label><select class="form-select" name="product" id="dd_product"></select></div>
                                <div class="col-md-2"><label class="form-label">Quantity *</label><input type="number" class="form-control" name="quantity" id="input_qty" placeholder="0"></div>
                                <div class="col-md-3"><label class="form-label">Courier <i class="bi bi-gear-fill btn-manage" onclick="openMaster('shipping','Manage Couriers')"></i></label><select class="form-select" name="shipping" id="dd_shipping"></select></div>
                                <div class="col-md-3"><label class="form-label">Tracking No</label><input type="text" class="form-control font-monospace" name="tracking_number" placeholder="e.g. JP1234567890"></div>
                            </div>
                            <div class="text-end mt-4 pt-3 border-top"><button type="button" class="btn btn-primary px-5 py-2" id="btnSave" onclick="saveManual()"><i class="bi bi-save2-fill me-2"></i>Save Data</button></div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="excel">
                        <div class="alert alert-light-info border-info d-flex align-items-center mb-4"><i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i><div><strong>Excel Columns (A-H):</strong> Date | Item | Data | Product | Qty | Company | Shipping | Resi</div></div>
                        <div class="upload-box" onclick="$('#fileInput').click()"><i class="bi bi-cloud-arrow-up-fill upload-icon"></i><h5>Click to Upload Excel File</h5><p class="text-muted small mb-0">Supported formats: .xlsx, .xls, .csv</p><input type="file" id="fileInput" class="d-none" accept=".xlsx,.xls,.csv" onchange="uploadExcel()"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3 align-items-center">
                    <h5 class="mb-0 text-primary">Delivery History</h5>
                    <div class="input-group w-25">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="searchBox" placeholder="Search data..." onkeyup="loadTable()">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="10%">Date</th>
                                <th width="20%">Company</th>
                                <th>Item Details</th>
                                <th class="text-center" width="8%">Qty</th>
                                <th width="12%">Courier</th>
                                <th width="15%">Tracking No</th>
                                <th class="text-center" width="15%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableData"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header border-bottom-0 pb-0"><h5 class="modal-title fw-bold">Edit Data</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body pt-4"><form id="editForm"><input type="hidden" name="action" value="update_transaction"><input type="hidden" name="id" id="edit_id"><div class="row g-3"><div class="col-md-4"><label>Date</label><input type="date" class="form-control" name="date" id="edit_date"></div><div class="col-md-4"><label>Item</label><select class="form-select" name="item" id="edit_item"></select></div><div class="col-md-4"><label>Data</label><select class="form-select" name="data" id="edit_data"></select></div><div class="col-md-6"><label>Product</label><select class="form-select" name="product" id="edit_product"></select></div><div class="col-md-6"><label>Qty</label><input type="number" class="form-control" name="quantity" id="edit_qty"></div><div class="col-md-6"><label>Company</label><select class="form-select" name="company" id="edit_company"></select></div><div class="col-md-6"><div class="row g-2"><div class="col-5"><label>Courier</label><select class="form-select" name="shipping" id="edit_shipping"></select></div><div class="col-7"><label>Resi</label><input type="text" class="form-control" name="tracking_number" id="edit_resi"></div></div></div></div></form></div><div class="modal-footer border-top-0"><button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary px-4" onclick="updateData()">Save Changes</button></div></div></div></div>

<div class="modal fade" id="modalTracking" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <div>
                    <h5 class="modal-title fw-bold"><i class="bi bi-truck text-primary me-2"></i>Shipment Status</h5>
                    <small class="text-muted" id="trackSubtitle">Loading...</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-white px-4 py-4" style="max-height: 75vh; overflow-y: auto;">
                <div id="trackContent"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMaster" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title fw-bold" id="modalTitle">Manage Data</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="input-group mb-3"><input type="text" class="form-control" id="newMasterName" placeholder="Type new name..."><button class="btn btn-primary" onclick="addMaster()">Add</button></div><input type="hidden" id="currentType"><div style="max-height: 300px; overflow-y: auto;"><ul class="list-group list-group-flush" id="listMaster"></ul></div></div></div></div></div>

<script src="assets/extensions/jquery/jquery.min.js"></script>
<script src="assets/extensions/sweetalert2/sweetalert2.min.js"></script>

<script>
const API = 'api_delivery.php';

$(document).ready(function() {
    loadAllDropdowns();
    loadTable();
});

// --- 1. LOAD TABLE ---
function loadTable() {
    $.post(API, {action: 'search_transactions', keyword: $('#searchBox').val()}, function(res) {
        let h = '';
        if(res.status=='success' && res.data.length > 0) {
            res.data.forEach(r => {
                let trackBtn = r.tracking_number ? 
                    `<button class="btn-icon btn-track" onclick="trackResi('${r.tracking_number}','${r.shipping_name}')" title="Track"><i class="bi bi-truck"></i></button>` : 
                    `<button class="btn-icon btn-light text-muted" disabled><i class="bi bi-dash"></i></button>`;
                
                h += `<tr>
                    <td class="col-date">${r.delivery_date}</td>
                    <td class="col-company">${r.company_name||'-'}</td>
                    <td class="col-details">
                        <div class="detail-row"><span class="detail-label">Item:</span> <span class="detail-val">${r.item_name||'-'}</span></div>
                        <div class="detail-row"><span class="detail-label">Type:</span> <span class="detail-val">${r.data_name||'-'}</span></div>
                        <div class="detail-row"><span class="detail-label">Prod:</span> <span class="detail-val">${r.product_name||'-'}</span></div>
                    </td>
                    <td class="text-center"><span class="badge bg-light-primary text-primary border border-primary">${r.quantity}</span></td>
                    <td><span class="fw-bold">${r.shipping_name||'-'}</span></td>
                    <td>${r.tracking_number ? `<span class="col-tracking">${r.tracking_number}</span>` : '<span class="text-muted small">-</span>'}</td>
                    <td class="text-center">
                        <div class="action-btn-group">
                            ${trackBtn}
                            <button class="btn-icon btn-edit" onclick="openEdit(${r.id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                            <button class="btn-icon btn-delete" onclick="deleteData(${r.id})" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
            });
        } else { h = '<tr><td colspan="7" class="text-center py-5 text-muted">No Data Available</td></tr>'; }
        $('#tableData').html(h);
    }, 'json');
}

// --- 2. TRACKING LOGIC (FIX TEXT) ---
function trackResi(resi, cour) {
    var m = new bootstrap.Modal(document.getElementById('modalTracking')); m.show();
    $('#trackSubtitle').text(`Resi: ${resi} | Courier: ${cour ? cour.toUpperCase() : 'UNK'}`);
    $('#trackContent').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted small">Connecting...</p></div>');
    
    $.post(API, {action:'track_shipment', resi:resi, courier:cour}, function(r){
        if(r.status=='success'){
            let d = r.data;
            let s = d.summary || d;
            // FIX: Replace "IND" with "INDONESIA" and ensure text wrapping
            let origAddr = d.origin?.address || '';
            if(origAddr === 'IND') origAddr = 'INDONESIA';
            
            let destAddr = d.destination?.address || '';
            if(destAddr === 'IND') destAddr = 'INDONESIA';

            // HEADER SYMMETRICAL
            let header = `
                <div class="track-header-card">
                    <div class="row border-bottom pb-3 mb-3">
                        <div class="col-6">
                            <span class="track-label">STATUS</span>
                            <div class="track-main-status">${s.status || 'In Transit'}</div>
                        </div>
                        <div class="col-6 text-end">
                            <span class="track-label">SERVICE / DATE</span>
                            <div class="fw-bold text-dark">${s.service || '-'}</div>
                            <div class="text-muted small">${s.date || ''}</div>
                        </div>
                    </div>
                    
                    <div class="row align-items-start">
                        <div class="col-5 text-start">
                            <span class="track-label">ORIGIN</span>
                            <div class="track-name">${d.origin?.contact_name || '-'}</div>
                            <div class="track-address">${origAddr}</div>
                        </div>
                        <div class="col-2 text-center">
                            <div class="track-arrow-container"><i class="bi bi-arrow-right"></i></div>
                        </div>
                        <div class="col-5 text-end">
                            <span class="track-label">DESTINATION</span>
                            <div class="track-name">${d.destination?.contact_name || '-'}</div>
                            <div class="track-address">${destAddr}</div>
                        </div>
                    </div>
                </div>`;

            let timeline = '<h6 class="mb-3 ps-2 fw-bold text-secondary">Shipment History</h6><ul class="tracking-timeline">';
            if(d.histories && d.histories.length > 0) {
                d.histories.forEach((h, i) => {
                    let active = i===0 ? 'latest' : '';
                    let dateDisplay = h.date.replace('T', ' ').substring(0, 16);
                    timeline += `<li class="tracking-item ${active}">
                        <span class="tracking-date">${dateDisplay}</span>
                        <div class="tracking-status text-primary">${h.status}</div>
                        <div class="tracking-desc">${h.message}</div>
                    </li>`;
                });
            } else { timeline += '<li class="text-muted ms-3">No history available.</li>'; }
            timeline += '</ul>';
            
            $('#trackContent').html(header + timeline);
        } else {
            $('#trackContent').html(`<div class="text-center py-5 text-danger"><i class="bi bi-exclamation-circle display-4"></i><p class="mt-3 mb-0 fw-bold">Not Found</p><small>${r.message}</small></div>`);
        }
    },'json').fail(function(){
        $('#trackContent').html('<div class="text-center py-5 text-danger"><i class="bi bi-wifi-off display-4"></i><p class="mt-3">Connection Failed</p></div>');
    });
}

// --- CRUD ---
function saveManual() {
    if($('#input_qty').val()=='' || $('#dd_company').val()=='') return Swal.fire('Info','Please complete the form','info');
    $('#btnSave').prop('disabled',true);
    $.post(API, $('#manualForm').serialize(), function(r){
        $('#btnSave').prop('disabled',false);
        if(r.status=='success'){ Swal.fire('Saved',r.message,'success'); $('#manualForm')[0].reset(); loadTable(); } 
        else Swal.fire('Error',r.message,'error');
    },'json');
}

function openEdit(id) {
    ['item','data','product','company','shipping'].forEach(t => { $('#edit_'+t).html($('#dd_'+t).html()); });
    $.post(API, {action: 'get_transaction', id: id}, function(res) {
        if(res.status == 'success') {
            let d = res.data;
            $('#edit_id').val(d.id); $('#edit_date').val(d.delivery_date); $('#edit_item').val(d.item_id);
            $('#edit_data').val(d.data_id); $('#edit_product').val(d.product_detail_id); $('#edit_qty').val(d.quantity);
            $('#edit_company').val(d.company_id); $('#edit_shipping').val(d.shipping_id); $('#edit_resi').val(d.tracking_number);
            new bootstrap.Modal(document.getElementById('modalEdit')).show();
        }
    }, 'json');
}

function updateData() {
    $.post(API, $('#editForm').serialize(), function(res) {
        if(res.status == 'success') { Swal.fire('Updated', res.message, 'success'); bootstrap.Modal.getInstance(document.getElementById('modalEdit')).hide(); loadTable(); }
        else { Swal.fire('Error', res.message, 'error'); }
    }, 'json');
}

function deleteData(id) { if(confirm('Delete?')) $.post(API, {action:'delete_transaction', id:id}, function(){loadTable()}, 'json'); }
function uploadExcel() {
    let fd = new FormData(); fd.append('action','import_excel'); fd.append('excel_file', $('#fileInput')[0].files[0]);
    Swal.fire({title:'Uploading...', didOpen:()=>Swal.showLoading()});
    $.ajax({url: API, type: 'POST', data: fd, contentType: false, processData: false, dataType: 'json',
        success: function(r) { if(r.status=='success'){Swal.fire('Done',r.message,'success'); loadTable(); loadAllDropdowns();} else Swal.fire('Error',r.message,'error'); }
    });
}
function loadAllDropdowns() { ['item','data','product','company','shipping'].forEach(t => { $.post(API, {action:'get_master', type:t}, function(r) { if(r.status=='success') { let h='<option value="">- Select -</option>'; r.data.forEach(d => h+=`<option value="${d.id}">${d.name}</option>`); $('#dd_'+t).html(h); } }, 'json'); }); }
function openMaster(t, ti) { $('#modalTitle').text(ti); $('#currentType').val(t); $('#newMasterName').val(''); loadMasterList(t); new bootstrap.Modal(document.getElementById('modalMaster')).show(); }
function loadMasterList(t) { $.post(API, {action:'get_master', type:t}, function(r) { let h=''; r.data.forEach(d => h+=`<li class="list-group-item d-flex justify-content-between align-items-center p-2 border-bottom"><span>${d.name}</span> <div><button class="btn btn-sm text-warning" onclick="editM(${d.id},'${d.name}')"><i class="bi bi-pencil-square"></i></button> <button class="btn btn-sm text-danger ms-1" onclick="delM(${d.id})"><i class="bi bi-trash"></i></button></div></li>`); $('#listMaster').html(h); },'json'); }
function addMaster() { let t=$('#currentType').val(), n=$('#newMasterName').val(); if(!n)return; $.post(API, {action:'add_master', type:t, name:n}, function(){ loadMasterList(t); loadAllDropdowns(); $('#newMasterName').val(''); },'json'); }
function delM(id) { if(confirm('Delete?')) $.post(API, {action:'delete_master', type:$('#currentType').val(), id:id}, function(){ loadMasterList($('#currentType').val()); loadAllDropdowns(); },'json'); }
function editM(id, old) { let n = prompt("Edit:", old); if(n && n!=old) $.post(API, {action:'edit_master', type:$('#currentType').val(), id:id, name:n}, function(){ loadMasterList($('#currentType').val()); loadAllDropdowns(); },'json'); }
</script>

<?php require_once 'includes/footer.php'; ?>