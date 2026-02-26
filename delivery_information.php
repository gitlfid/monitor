<?php
// =========================================================================
// FILE: delivery_information.php
// DESC: Manage shipping schedules, track packages, and master data.
// THEME: Ultra-Modern Tailwind CSS & Dynamic UI
// =========================================================================

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
    /* Custom Animations */
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .modal-animate-in { animation: scaleIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    
    /* Upload Zone */
    .upload-zone { border: 2px dashed #c7d2fe; background-color: #f8fafc; transition: all 0.3s ease; cursor: pointer; }
    .upload-zone:hover, .upload-zone.dragover { border-color: #6366f1; background-color: #eef2ff; }
    .dark .upload-zone { background-color: rgba(30, 41, 59, 0.5); border-color: rgba(99, 102, 241, 0.3); }
    .dark .upload-zone:hover, .dark .upload-zone.dragover { border-color: #6366f1; background-color: rgba(99, 102, 241, 0.1); }

    /* Modern Table Formatting */
    .table-modern { width: 100%; border-collapse: collapse; }
    .table-modern thead th { background: #f8fafc; color: #64748b; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
    .table-modern tbody td { padding: 1.25rem 1.5rem; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
    .table-row-hover:hover { background-color: rgba(248, 250, 252, 0.8); }
    
    .dark .table-modern thead th { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .dark .table-modern tbody td { border-color: #334155; }
    .dark .table-row-hover:hover { background-color: rgba(30, 41, 59, 0.6); }

    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
</style>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div class="animate-fade-in-up">
        <h2 class="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400 tracking-tight">
            Delivery Information
        </h2>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1.5 flex items-center gap-1.5">
            <i class="ph ph-truck text-lg text-blue-500"></i> Manage shipping schedules, track packages, and master data.
        </p>
    </div>
</div>

<div class="flex gap-2 border-b border-slate-200 dark:border-slate-700 mb-6 animate-fade-in-up" style="animation-delay: 0.1s;">
    <button onclick="switchTab('manual')" id="tab-btn-manual" class="px-6 py-3.5 text-sm font-bold border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-pencil-simple"></i> Manual Input
    </button>
    <button onclick="switchTab('excel')" id="tab-btn-excel" class="px-6 py-3.5 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-2">
        <i class="ph-fill ph-file-xls"></i> Import Excel
    </button>
</div>

<div id="tab-content-manual" class="block animate-fade-in-up" style="animation-delay: 0.2s;">
    <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden mb-10">
        <form id="manualForm" class="p-8">
            <input type="hidden" name="action" value="save_transaction">
            
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Date *</label>
                    <input type="date" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-bold outline-none focus:border-blue-500 transition-all shadow-sm dark:text-white" name="date" id="input_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="md:col-span-3">
                    <label class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">
                        Company *
                        <button type="button" onclick="openMaster('company','Manage Companies')" class="text-blue-500 hover:text-blue-700 transition-colors" title="Manage Masters"><i class="ph-fill ph-gear text-sm"></i></button>
                    </label>
                    <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-medium outline-none focus:border-blue-500 transition-all shadow-sm cursor-pointer dark:text-white" name="company" id="dd_company"></select>
                </div>
                <div class="md:col-span-3">
                    <label class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">
                        Item Type
                        <button type="button" onclick="openMaster('item','Manage Items')" class="text-blue-500 hover:text-blue-700 transition-colors" title="Manage Masters"><i class="ph-fill ph-gear text-sm"></i></button>
                    </label>
                    <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-medium outline-none focus:border-blue-500 transition-all shadow-sm cursor-pointer dark:text-white" name="item" id="dd_item"></select>
                </div>
                <div class="md:col-span-3">
                    <label class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">
                        Data Type
                        <button type="button" onclick="openMaster('data','Manage Data Types')" class="text-blue-500 hover:text-blue-700 transition-colors" title="Manage Masters"><i class="ph-fill ph-gear text-sm"></i></button>
                    </label>
                    <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-medium outline-none focus:border-blue-500 transition-all shadow-sm cursor-pointer dark:text-white" name="data" id="dd_data"></select>
                </div>
                
                <div class="md:col-span-4">
                    <label class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">
                        Product Detail
                        <button type="button" onclick="openMaster('product','Manage Products')" class="text-blue-500 hover:text-blue-700 transition-colors" title="Manage Masters"><i class="ph-fill ph-gear text-sm"></i></button>
                    </label>
                    <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-medium outline-none focus:border-blue-500 transition-all shadow-sm cursor-pointer dark:text-white" name="product" id="dd_product"></select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Quantity *</label>
                    <input type="number" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-bold text-blue-600 dark:text-blue-400 outline-none focus:border-blue-500 transition-all shadow-sm" name="quantity" id="input_qty" placeholder="0">
                </div>
                <div class="md:col-span-3">
                    <label class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">
                        Courier
                        <button type="button" onclick="openMaster('shipping','Manage Couriers')" class="text-blue-500 hover:text-blue-700 transition-colors" title="Manage Masters"><i class="ph-fill ph-gear text-sm"></i></button>
                    </label>
                    <select class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-medium outline-none focus:border-blue-500 transition-all shadow-sm cursor-pointer dark:text-white" name="shipping" id="dd_shipping"></select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-2">Tracking No / Resi</label>
                    <input type="text" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-mono font-bold outline-none focus:border-blue-500 transition-all shadow-sm dark:text-white uppercase" name="tracking_number" placeholder="e.g. JP1234567890">
                </div>
            </div>

            <div class="flex justify-end pt-6 mt-6 border-t border-slate-100 dark:border-slate-800">
                <button type="button" id="btnSave" onclick="saveManual()" class="rounded-xl bg-blue-600 px-8 py-3.5 text-sm font-bold text-white transition-all hover:bg-blue-700 shadow-md hover:shadow-lg hover:shadow-blue-500/30 active:scale-95 flex items-center gap-2">
                    <i class="ph-bold ph-floppy-disk"></i> Save Delivery Data
                </button>
            </div>
        </form>
    </div>
</div>

<div id="tab-content-excel" class="hidden animate-fade-in-up" style="animation-delay: 0.2s;">
    <div class="rounded-3xl bg-white dark:bg-[#24303F] shadow-soft border border-slate-100 dark:border-slate-800 overflow-hidden mb-10 p-8">
        
        <div class="bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 rounded-2xl p-5 mb-8 flex gap-4 items-center">
            <i class="ph-fill ph-info text-3xl text-blue-500 shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-widest mb-1">Required Excel Columns (A-H)</p>
                <p class="text-[11px] font-mono font-bold text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-blue-100 dark:border-blue-800/50 w-max shadow-sm">
                    Date | Item | Data | Product | Qty | Company | Shipping | Resi
                </p>
            </div>
        </div>

        <div class="upload-zone relative rounded-3xl flex flex-col items-center justify-center p-16 text-center" onclick="$('#fileInput').click()">
            <input type="file" id="fileInput" class="hidden" accept=".xlsx,.xls,.csv" onchange="uploadExcel()">
            <i class="ph-fill ph-cloud-arrow-up text-6xl text-indigo-300 dark:text-indigo-500/50 mb-4 transition-transform hover:-translate-y-1"></i>
            <h5 class="text-lg font-black text-slate-700 dark:text-slate-200 mb-1">Click or drag file here to import</h5>
            <p class="text-xs font-medium text-slate-500">Supported formats: .xlsx, .xls, .csv</p>
        </div>

    </div>
</div>

<div class="rounded-3xl bg-white shadow-soft dark:bg-[#24303F] border border-slate-100 dark:border-slate-800 animate-fade-in-up relative overflow-hidden mb-10" style="animation-delay: 0.3s;">
    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
    <div class="border-b border-slate-100 dark:border-slate-800 px-8 py-6 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h4 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <i class="ph-fill ph-clock-counter-clockwise text-blue-500 text-xl"></i> Delivery History
            </h4>
        </div>
        <div class="relative w-full sm:w-64">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
            <input type="text" id="searchBox" class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-12 pr-4 text-sm font-medium outline-none focus:border-blue-500 shadow-sm transition-all dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200" placeholder="Search data..." onkeyup="loadTable()">
        </div>
    </div>
    
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse table-modern">
            <thead>
                <tr>
                    <th class="ps-8 w-32">Date</th>
                    <th class="w-48">Company</th>
                    <th class="w-64">Item Details</th>
                    <th class="text-center w-24">Qty</th>
                    <th class="w-32">Courier</th>
                    <th class="w-40">Tracking No</th>
                    <th class="text-center pe-8 w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800" id="tableData">
                </tbody>
        </table>
    </div>
</div>

<div id="modalEdit" class="modal-container fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-4xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-amber-500 px-8 py-5 bg-gradient-to-r from-amber-500 to-orange-500 text-white">
            <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-pencil-simple text-2xl"></i> Edit Delivery Data</h5>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-2xl"></i></button>
        </div>
        
        <form id="editForm" class="p-8 bg-slate-50/50 dark:bg-slate-900/50">
            <input type="hidden" name="action" value="update_transaction"><input type="hidden" name="id" id="edit_id">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Date</label>
                    <input type="date" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium outline-none focus:border-amber-500 dark:text-white shadow-sm" name="date" id="edit_date">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Item Type</label>
                    <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium outline-none focus:border-amber-500 dark:text-white shadow-sm" name="item" id="edit_item"></select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Data Type</label>
                    <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium outline-none focus:border-amber-500 dark:text-white shadow-sm" name="data" id="edit_data"></select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Product Detail</label>
                    <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium outline-none focus:border-amber-500 dark:text-white shadow-sm" name="product" id="edit_product"></select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Quantity</label>
                    <input type="number" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-amber-50 dark:bg-slate-800 px-4 py-2.5 text-sm font-black font-mono text-amber-600 dark:text-amber-400 outline-none focus:border-amber-500 shadow-sm" name="quantity" id="edit_qty">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Company</label>
                    <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-medium outline-none focus:border-amber-500 dark:text-white shadow-sm" name="company" id="edit_company"></select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Courier</label>
                        <select class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm font-medium outline-none focus:border-amber-500 dark:text-white shadow-sm" name="shipping" id="edit_shipping"></select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-1.5">Tracking No</label>
                        <input type="text" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2.5 text-sm font-mono font-bold outline-none focus:border-amber-500 dark:text-white shadow-sm uppercase" name="tracking_number" id="edit_resi">
                    </div>
                </div>
            </div>
        </form>
        <div class="border-t border-slate-200 dark:border-slate-700 p-5 bg-white dark:bg-slate-800 flex justify-end gap-3 shrink-0">
            <button type="button" class="btn-close-modal rounded-xl px-6 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>
            <button type="button" onclick="updateData()" class="rounded-xl bg-amber-500 px-8 py-2.5 text-sm font-bold text-white transition-all hover:bg-amber-600 shadow-md active:scale-95"><i class="ph-bold ph-check-circle"></i> Save Changes</button>
        </div>
    </div>
</div>

<div id="modalTracking" class="modal-container fixed inset-0 z-[110] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-3xl rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col max-h-[85vh] overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-blue-500 px-8 py-6 bg-gradient-to-r from-blue-600 to-blue-800 text-white shrink-0">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center text-3xl shadow-inner border border-white/20"><i class="ph-fill ph-truck"></i></div>
                <div>
                    <h5 class="text-xl font-black leading-tight">Live Shipment Status</h5>
                    <p class="text-[10px] uppercase font-bold text-blue-200 tracking-widest mt-1" id="trackSubtitle">Loading...</p>
                </div>
            </div>
            <button type="button" class="btn-close-modal h-10 w-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition-colors"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="overflow-y-auto p-8 flex-1 custom-scrollbar bg-slate-50 dark:bg-slate-900/30" id="trackContent">
            </div>
        <div class="border-t border-slate-200 dark:border-slate-700 p-6 bg-white dark:bg-slate-800 flex justify-end shrink-0">
            <button type="button" class="btn-close-modal rounded-xl px-8 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-sm">Close Tracker</button>
        </div>
    </div>
</div>

<div id="modalMaster" class="modal-container fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity p-4">
    <div class="w-full max-w-md rounded-3xl bg-white dark:bg-[#24303F] shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-slate-700 modal-animate-in">
        <div class="flex items-center justify-between border-b border-indigo-500 px-6 py-5 bg-indigo-600 text-white">
            <h5 class="text-lg font-bold flex items-center gap-2"><i class="ph-bold ph-gear text-xl"></i> <span id="modalTitle">Manage Data</span></h5>
            <button type="button" class="btn-close-modal text-white/70 hover:text-white transition-all"><i class="ph ph-x text-xl"></i></button>
        </div>
        <div class="p-6 bg-slate-50 dark:bg-slate-900/50">
            <div class="flex gap-2 mb-6">
                <input type="text" id="newMasterName" class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm font-bold outline-none focus:border-indigo-500 dark:text-white shadow-sm" placeholder="Type new entry name...">
                <button type="button" onclick="addMaster()" class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white transition-all hover:bg-indigo-700 shadow-md active:scale-95"><i class="ph-bold ph-plus"></i></button>
            </div>
            <input type="hidden" id="currentType">
            
            <h6 class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 px-1">Existing Entries</h6>
            <div class="max-h-[300px] overflow-y-auto custom-scrollbar border border-slate-200 dark:border-slate-700 rounded-2xl bg-white dark:bg-slate-800 shadow-sm" id="listMaster">
                </div>
        </div>
        <div class="border-t border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-800 flex justify-end">
            <button type="button" class="btn-close-modal rounded-xl px-6 py-2 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-600">Done</button>
        </div>
    </div>
</div>

<script src="assets/extensions/jquery/jquery.min.js"></script>
<script src="assets/extensions/sweetalert2/sweetalert2.min.js"></script>

<script>
const API = 'api_delivery.php';

// Tailwind Tab Toggle
function switchTab(tab) {
    if(tab === 'manual') {
        $('#tab-btn-manual').addClass('border-blue-600 text-blue-600 dark:text-blue-400').removeClass('border-transparent text-slate-500');
        $('#tab-btn-excel').removeClass('border-blue-600 text-blue-600 dark:text-blue-400').addClass('border-transparent text-slate-500');
        $('#tab-content-manual').removeClass('hidden').addClass('block');
        $('#tab-content-excel').removeClass('block').addClass('hidden');
    } else {
        $('#tab-btn-excel').addClass('border-blue-600 text-blue-600 dark:text-blue-400').removeClass('border-transparent text-slate-500');
        $('#tab-btn-manual').removeClass('border-blue-600 text-blue-600 dark:text-blue-400').addClass('border-transparent text-slate-500');
        $('#tab-content-excel').removeClass('hidden').addClass('block');
        $('#tab-content-manual').removeClass('block').addClass('hidden');
    }
}

// Global Tailwind Modal Handlers
$('.btn-close-modal').click(function() {
    $(this).closest('.modal-container').removeClass('flex').addClass('hidden');
    $('body').css('overflow', 'auto');
});
$('.modal-container').click(function(e) {
    if(e.target === this) {
        $(this).removeClass('flex').addClass('hidden');
        $('body').css('overflow', 'auto');
    }
});

$(document).ready(function() {
    loadAllDropdowns();
    loadTable();
});

// --- 1. LOAD TABLE ---
function loadTable() {
    $('#tableData').html('<tr><td colspan="7" class="text-center py-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div></td></tr>');
    $.post(API, {action: 'search_transactions', keyword: $('#searchBox').val()}, function(res) {
        let h = '';
        if(res.status=='success' && res.data.length > 0) {
            res.data.forEach(r => {
                let trackBtn = r.tracking_number ? 
                    `<button class="h-8 w-8 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-500/20 flex items-center justify-center transition-colors shadow-sm" onclick="trackResi('${r.tracking_number}','${r.shipping_name}')" title="Track"><i class="ph-bold ph-crosshair text-base"></i></button>` : 
                    `<button class="h-8 w-8 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 cursor-not-allowed flex items-center justify-center border border-slate-200 dark:border-slate-700 shadow-sm" disabled><i class="ph-bold ph-minus text-base"></i></button>`;
                
                h += `<tr class="table-row-hover transition-colors group">
                    <td class="ps-8 align-top">
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-sm">${r.delivery_date}</span>
                    </td>
                    <td class="align-top">
                        <span class="font-bold text-indigo-600 dark:text-indigo-400 text-sm bg-indigo-50 dark:bg-indigo-500/10 px-2 py-0.5 rounded-lg border border-indigo-100 dark:border-indigo-500/20 shadow-sm">${r.company_name||'-'}</span>
                    </td>
                    <td class="align-top">
                        <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-1"><span class="font-black uppercase tracking-widest opacity-70">Item:</span> <span class="font-bold text-slate-700 dark:text-slate-300">${r.item_name||'-'}</span></div>
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-1"><span class="font-black uppercase tracking-widest opacity-70">Type:</span> <span class="font-bold text-slate-700 dark:text-slate-300">${r.data_name||'-'}</span></div>
                            <div class="flex items-center justify-between"><span class="font-black uppercase tracking-widest opacity-70">Prod:</span> <span class="font-bold text-slate-700 dark:text-slate-300">${r.product_name||'-'}</span></div>
                        </div>
                    </td>
                    <td class="align-top text-center">
                        <span class="inline-flex items-center justify-center px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono font-bold text-sm border border-slate-200 dark:border-slate-700 shadow-sm">${r.quantity}</span>
                    </td>
                    <td class="align-top">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 shadow-sm"><i class="ph-fill ph-truck text-slate-400"></i> ${r.shipping_name||'-'}</span>
                    </td>
                    <td class="align-top">
                        ${r.tracking_number ? `<code class="text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2.5 py-1.5 rounded-lg border border-blue-100 dark:border-blue-800 shadow-sm select-all">${r.tracking_number}</code>` : '<span class="text-xs text-slate-400 italic">No Resi</span>'}
                    </td>
                    <td class="pe-8 align-top text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            ${trackBtn}
                            <button class="h-8 w-8 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-500/20 flex items-center justify-center transition-colors shadow-sm" onclick="openEdit(${r.id})" title="Edit"><i class="ph-fill ph-pencil-simple text-base"></i></button>
                            <button class="h-8 w-8 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 flex items-center justify-center transition-colors shadow-sm" onclick="deleteData(${r.id})" title="Delete"><i class="ph-fill ph-trash text-base"></i></button>
                        </div>
                    </td>
                </tr>`;
            });
        } else { h = '<tr><td colspan="7" class="text-center py-12"><i class="ph-fill ph-folder-dashed text-5xl text-slate-300 dark:text-slate-600 mb-3 block"></i><span class="text-slate-500 font-bold">No Delivery Records Found</span></td></tr>'; }
        $('#tableData').html(h);
    }, 'json');
}

// --- 2. TRACKING LOGIC (TAILWIND UI) ---
function trackResi(resi, cour) {
    $('#modalTracking').removeClass('hidden').addClass('flex'); $('body').css('overflow', 'hidden');
    $('#trackSubtitle').text(`AWB: ${resi} | COURIER: ${cour ? cour.toUpperCase() : 'UNK'}`);
    $('#trackContent').html('<div class="flex flex-col items-center justify-center py-16"><div class="animate-spin rounded-full h-12 w-12 border-b-4 border-blue-600 mb-4"></div><p class="text-sm font-bold text-slate-500 tracking-widest uppercase">Connecting to Courier API...</p></div>');
    
    $.post(API, {action:'track_shipment', resi:resi, courier:cour}, function(r){
        if(r.status=='success'){
            let d = r.data; let s = d.summary || d;
            let origAddr = d.origin?.address || ''; if(origAddr === 'IND') origAddr = 'INDONESIA';
            let destAddr = d.destination?.address || ''; if(destAddr === 'IND') destAddr = 'INDONESIA';

            let header = `
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-6 mb-8 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700 pb-5 mb-5">
                        <div>
                            <span class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Current Status</span>
                            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">${s.status || 'IN TRANSIT'}</div>
                        </div>
                        <div class="text-right">
                            <span class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Service & Timestamp</span>
                            <div class="font-bold text-slate-800 dark:text-white text-sm">${s.service || '-'}</div>
                            <div class="text-xs font-medium text-slate-500 mt-0.5">${s.date || ''}</div>
                        </div>
                    </div>
                    
                    <div class="flex items-start justify-between relative">
                        <div class="w-5/12 pr-4">
                            <span class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5"><i class="ph-fill ph-map-pin text-blue-500"></i> ORIGIN</span>
                            <div class="font-bold text-slate-800 dark:text-white text-sm mb-1">${d.origin?.contact_name || 'PT LinksField'}</div>
                            <div class="text-xs text-slate-500 leading-relaxed">${origAddr}</div>
                        </div>
                        <div class="w-2/12 flex justify-center text-slate-300 dark:text-slate-600 pt-3">
                            <i class="ph-bold ph-arrow-right text-3xl"></i>
                        </div>
                        <div class="w-5/12 pl-4 text-right">
                            <span class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1.5">DESTINATION <i class="ph-fill ph-flag-checkered text-emerald-500"></i></span>
                            <div class="font-bold text-indigo-600 dark:text-indigo-400 text-sm mb-1">${d.destination?.contact_name || '-'}</div>
                            <div class="text-xs text-slate-500 leading-relaxed">${destAddr}</div>
                        </div>
                    </div>
                </div>`;

            let timeline = '<h6 class="text-[11px] font-black uppercase tracking-widest text-slate-500 mb-5 px-2 flex items-center gap-2"><i class="ph-fill ph-clock-counter-clockwise text-lg"></i> Shipment History Logs</h6><div class="relative border-l-2 border-slate-200 dark:border-slate-700 ml-4 space-y-6 pb-4">';
            if(d.histories && d.histories.length > 0) {
                d.histories.forEach((h, i) => {
                    let active = i===0;
                    let dotClass = active ? 'bg-blue-500 ring-4 ring-blue-50 dark:ring-blue-900/30' : 'bg-slate-300 dark:bg-slate-600';
                    let textClass = active ? 'text-blue-600 dark:text-blue-400 font-black' : 'text-slate-700 dark:text-slate-300 font-bold';
                    let boxClass = active ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/20 text-blue-800 dark:text-blue-300 shadow-sm' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                    let dateDisplay = h.date.replace('T', ' ').substring(0, 16);
                    
                    timeline += `
                    <div class="relative pl-6">
                        <div class="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full ${dotClass} transition-all"></div>
                        <span class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">${dateDisplay}</span>
                        <div class="text-sm ${textClass} mb-2">${h.status}</div>
                        <div class="text-xs p-3.5 rounded-xl border ${boxClass} leading-relaxed">${h.message}</div>
                    </div>`;
                });
            } else { timeline += '<div class="pl-6 text-sm text-slate-500 italic">No historical logs available from courier.</div>'; }
            timeline += '</div>';
            
            $('#trackContent').html(header + timeline);
        } else {
            $('#trackContent').html(`<div class="text-center py-10 text-red-500"><i class="ph-fill ph-warning-circle text-6xl mb-3"></i><p class="font-bold text-lg mb-1">Tracking Not Found</p><p class="text-sm text-red-400">${r.message}</p></div>`);
        }
    },'json').fail(function(){
        $('#trackContent').html('<div class="text-center py-10 text-red-500"><i class="ph-fill ph-wifi-x text-6xl mb-3"></i><p class="font-bold text-lg">Connection Failed</p></div>');
    });
}

// --- CRUD ---
function saveManual() {
    if($('#input_qty').val()=='' || $('#dd_company').val()=='') return Swal.fire('Incomplete','Please complete required fields (*).','warning');
    $('#btnSave').prop('disabled',true).html('<i class="ph-bold ph-spinner animate-spin"></i> Saving...');
    $.post(API, $('#manualForm').serialize(), function(r){
        $('#btnSave').prop('disabled',false).html('<i class="ph-bold ph-floppy-disk"></i> Save Delivery Data');
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
            $('#modalEdit').removeClass('hidden').addClass('flex'); $('body').css('overflow', 'hidden');
        }
    }, 'json');
}

function updateData() {
    $.post(API, $('#editForm').serialize(), function(res) {
        if(res.status == 'success') { 
            Swal.fire('Updated', res.message, 'success'); 
            $('#modalEdit').removeClass('flex').addClass('hidden'); $('body').css('overflow', 'auto');
            loadTable(); 
        } else { Swal.fire('Error', res.message, 'error'); }
    }, 'json');
}

function deleteData(id) { 
    Swal.fire({
        title: 'Delete record?', text: "This action cannot be undone.", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b', confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(API, {action:'delete_transaction', id:id}, function(){loadTable()}, 'json');
            Swal.fire('Deleted!', 'Record has been removed.', 'success');
        }
    });
}

function uploadExcel() {
    let file = $('#fileInput')[0].files[0];
    if(!file) return;
    let fd = new FormData(); fd.append('action','import_excel'); fd.append('excel_file', file);
    Swal.fire({title:'Uploading Data...', text:'Please wait', allowOutsideClick: false, didOpen:()=>{Swal.showLoading()}});
    $.ajax({url: API, type: 'POST', data: fd, contentType: false, processData: false, dataType: 'json',
        success: function(r) { 
            if(r.status=='success'){Swal.fire('Completed',r.message,'success'); loadTable(); loadAllDropdowns();} 
            else Swal.fire('Error',r.message,'error'); 
        },
        error: function() { Swal.fire('Error','Server connection failed.','error'); }
    });
}

// DRAG DROP EXCEL ZONE
const dropZone = document.querySelector('.upload-zone');
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { dropZone.addEventListener(eventName, preventDefaults, false); });
function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
['dragenter', 'dragover'].forEach(eventName => { dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false); });
['dragleave', 'drop'].forEach(eventName => { dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false); });
dropZone.addEventListener('drop', handleDrop, false);
function handleDrop(e) {
    let dt = e.dataTransfer; let files = dt.files;
    if(files.length > 0) {
        document.getElementById('fileInput').files = files;
        uploadExcel();
    }
}

// --- MASTER DATA MANAGEMENT ---
function loadAllDropdowns() { 
    ['item','data','product','company','shipping'].forEach(t => { 
        $.post(API, {action:'get_master', type:t}, function(r) { 
            if(r.status=='success') { 
                let h='<option value="">- Select -</option>'; 
                r.data.forEach(d => h+=`<option value="${d.id}">${d.name}</option>`); 
                $('#dd_'+t).html(h); 
            } 
        }, 'json'); 
    }); 
}

function openMaster(t, ti) { 
    $('#modalTitle').text(ti); $('#currentType').val(t); $('#newMasterName').val(''); 
    loadMasterList(t); 
    $('#modalMaster').removeClass('hidden').addClass('flex'); $('body').css('overflow', 'hidden');
}

function loadMasterList(t) { 
    $.post(API, {action:'get_master', type:t}, function(r) { 
        let h=''; 
        r.data.forEach(d => {
            h += `<div class="flex justify-between items-center p-3 border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                    <span class="font-bold text-sm text-slate-700 dark:text-slate-300">${d.name}</span>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button class="h-7 w-7 rounded bg-amber-50 text-amber-500 hover:bg-amber-100 flex items-center justify-center transition-colors" onclick="editM(${d.id},'${d.name}')" title="Edit"><i class="ph-fill ph-pencil-simple text-sm"></i></button>
                        <button class="h-7 w-7 rounded bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition-colors" onclick="delM(${d.id})" title="Delete"><i class="ph-fill ph-trash text-sm"></i></button>
                    </div>
                  </div>`;
        });
        $('#listMaster').html(h); 
    },'json'); 
}

function addMaster() { 
    let t=$('#currentType').val(), n=$('#newMasterName').val().trim(); 
    if(!n)return; 
    $.post(API, {action:'add_master', type:t, name:n}, function(){ 
        loadMasterList(t); loadAllDropdowns(); $('#newMasterName').val(''); 
    },'json'); 
}

function delM(id) { 
    if(confirm('Delete this entry?')) {
        $.post(API, {action:'delete_master', type:$('#currentType').val(), id:id}, function(){ 
            loadMasterList($('#currentType').val()); loadAllDropdowns(); 
        },'json'); 
    }
}

function editM(id, old) { 
    let n = prompt("Edit Entry Name:", old); 
    if(n && n.trim()!==old) {
        $.post(API, {action:'edit_master', type:$('#currentType').val(), id:id, name:n.trim()}, function(){ 
            loadMasterList($('#currentType').val()); loadAllDropdowns(); 
        },'json'); 
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>