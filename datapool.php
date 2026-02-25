<?php
require_once 'includes/header.php';

// Ambil SEMUA perusahaan untuk ditampilkan di grid
$db = db_connect();
$stmt = $db->query("SELECT id, project_name FROM projects ORDER BY project_name ASC");
$companies = $stmt->fetchAll();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard Monitoring Saldo</h1>
            </div>
        </div>
    </div>
</div>
<section class="content">
    <div class="container-fluid">
        
        <div class="row" id="dashboard-grid-container">
            <?php if (empty($companies)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <h5><i class="icon fas fa-info"></i> Belum Ada Klien</h5>
                        Silakan tambahkan data klien/perusahaan di halaman <a href="manage_companies.php" class="alert-link">Kelola Klien</a> terlebih dahulu.
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($companies as $company): ?>
                <div class="col-md-6">
                    <div class="card card-primary card-outline dashboard-container package-details-trigger" 
                         data-company-id="<?php echo $company['id']; ?>" 
                         style="cursor: pointer;"
                         title="Klik untuk melihat detail denom paket">
                        
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <?php echo htmlspecialchars($company['project_name']); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="loading-spinner text-center p-5">
                                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-2">Loading</p>
                            </div>

                            <div class="error-container alert alert-danger" style="display: none;">
                                <h4><i class="icon fas fa-ban"></i> Gagal Memuat Data!</h4>
                                <span class="error-message"></span>
                            </div>

                            <div class="dashboard-content" style="display: none;">
                                <div class="row">
                                    
                                    <div class="col-md-4 d-flex flex-column align-items-center justify-content-center">
                                        <div style="position: relative; height: 160px; width: 160px;">
                                            <canvas id="chart-<?php echo $company['id']; ?>"></canvas>
                                        </div>
                                        <div class="d-flex justify-content-center mt-2" style="font-size: 0.8rem;">
                                            <div class="mr-3">
                                                <i class="fas fa-circle text-success"></i> Sisa Saldo (GB)
                                            </div>
                                            <div>
                                                <i class="fas fa-circle text-warning"></i> Terpakai (GB)
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <h4 class="servicePackageName font-weight-bold text-dark mb-3">Nama Paket</h4>
                                        <div class="row mb-3">
                                            <div class="col-4">
                                                <span class="text-muted" style="font-size: 0.85rem;">SISA SALDO</span><br>
                                                <strong class="sisaSaldo text-success" style="font-size: 1.1rem;">0 GB</strong>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-muted" style="font-size: 0.85rem;">TOTAL TERPAKAI</span><br>
                                                <strong class="totalTerpakai text-warning" style="font-size: 1.1rem;">0 GB</strong>
                                            </div>
                                            <div class="col-4">
                                                <span class="text-muted" style="font-size: 0.85rem;">TOTAL KUOTA</span><br>
                                                <strong class="totalKuota" style="font-size: 1.1rem;">0 GB</strong>
                                            </div>
                                        </div>
                                        <div class="row" style="font-size: 0.9rem;">
                                            <div class="col-4">
                                                <strong>Tgl Kedaluwarsa:</strong>
                                                <p class="text-danger tanggalKedaluwarsaDate mb-0">-</p>
                                                <p class="text-danger tanggalKedaluwarsaTime mb-0">-</p>
                                            </div>
                                            <div class="col-4">
                                                <strong>Status:</strong><br>
                                                <span class="badge statusBadge">-</span>
                                            </div>
                                            <div class="col-4">
                                                <strong>Pilot MSISDN:</strong>
                                                <p class="pilotMsisdn mb-0">-</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="modal fade" id="packageModal" tabindex="-1" role="dialog" aria-labelledby="packageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document"> <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="packageModalTitle">Detail Denom Paket</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="packageDenomContent">
                <p class="text-center p-4">Memuat data...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php ob_start(); ?>
<script>

/**
 * Fungsi untuk mempopulasi data dan chart ke dalam card.
 * (Fungsi ini SAMA SEPERTI SEBELUMNYA)
 */
function populateDashboardCard($card, data) {
    // 1. Konversi data MB ke GB
    let sisaGB = parseFloat((data.balance / 1000).toFixed(2));
    let terpakaiGB = parseFloat((data.usage / 1000).toFixed(2));
    let totalGB = parseFloat((data.limitUsage / 1000).toFixed(2));
    var canvasId = $card.find('canvas').attr('id');
    if (!canvasId) return; 

    // 3. Update Chart
    var ctx = document.getElementById(canvasId).getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Sisa Saldo (GB)', 'Terpakai (GB)'],
            datasets: [{
                data: [sisaGB, terpakaiGB],
                backgroundColor: ['#28a745', '#ffc107']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 75,
            legend: { display: false },
            animation: { animateScale: true, animateRotate: true }
        }
    });

    // 4. Update Info Teks
    $card.find('.servicePackageName').text(data.servicePackageName);
    $card.find('.sisaSaldo').text(sisaGB + ' GB');
    $card.find('.totalTerpakai').text(terpakaiGB + ' GB');
    $card.find('.totalKuota').text(totalGB + ' GB');
    let expiryDate = new Date(data.expiryDate);
    let formattedDate = expiryDate.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ',';
    let formattedTime = expiryDate.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');
    $card.find('.tanggalKedaluwarsaDate').text(formattedDate);
    $card.find('.tanggalKedaluwarsaTime').text(formattedTime);
    $card.find('.pilotMsisdn').text(data.pilotCharge);
    let $statusBadge = $card.find('.statusBadge');
    $statusBadge.text(data.status);
    if (data.status.toUpperCase() === 'ACTIVE') {
        $statusBadge.removeClass('badge-secondary badge-danger').addClass('badge-success');
    } else {
        $statusBadge.removeClass('badge-secondary badge-success').addClass('badge-danger');
    }
}


/**
 * Fungsi utama yang akan dijalankan saat halaman dimuat
 */
$(document).ready(function() {
    
    // Loop untuk memuat data saldo (SAMA SEPERTI SEBELUMNYA)
    $('.dashboard-container').each(function() {
        var $card = $(this); 
        var companyId = $card.data('company-id');
        var $loader = $card.find('.loading-spinner');
        var $content = $card.find('.dashboard-content');
        var $errorContainer = $card.find('.error-container');
        var $errorMessage = $card.find('.error-message');

        if (companyId) {
            $.ajax({
                url: 'ajax/get_balance.php',
                type: 'GET',
                data: { company_id: companyId },
                dataType: 'json',
                success: function(response) {
                    $loader.hide(); 
                    if (response.status === true && response.data) {
                        populateDashboardCard($card, response.data);
                        $content.show();
                    } else {
                        $errorMessage.text(response.message || 'Data tidak ditemukan.');
                        $errorContainer.show();
                    }
                },
                error: function(xhr, status, error) {
                    $loader.hide();
                    $errorMessage.text('Error AJAX: ' + error + '. (Code: ' + xhr.status + ')');
                    $errorContainer.show();
                    console.log(xhr.responseText); 
                }
            });
        }
    });

    /* ===============================================
    == Script untuk Modal Detail Paket ==
    ===============================================
    */
    $('.package-details-trigger').on('click', function() {
        var $card = $(this);
        var companyId = $card.data('company-id');
        var companyName = $card.find('.card-title').text().trim();

        // 1. Siapkan Modal
        $('#packageModalTitle').text('Detail Denom Paket: ' + companyName);
        $('#packageDenomContent').html('<p class="text-center p-4">Memuat data denom...</p>');
        $('#packageModal').modal('show');

        // 2. Panggil AJAX baru
        $.ajax({
            url: 'ajax/get_package_info.php', // Panggil file AJAX baru
            type: 'GET',
            data: { company_id: companyId },
            dataType: 'json',
            success: function(response) {
                // 3. Cek response
                if (response.status === true && response.data && response.data.denom && response.data.denom.length > 0) {
                    var denoms = response.data.denom;
                    
                    // 4. Buat tabel HTML
                    var html = '<table class="table table-striped table-bordered">';
                    html += '<thead><tr><th>Denom ID</th><th>Nama Denom</th><th>Quota</th><th>Validitas</th></tr></thead>';
                    html += '<tbody>';
                    
                    denoms.forEach(function(item) {
                        // Mengambil data quota dari array 'denomAllowance'
                        var quota = 'N/A';
                        if (item.denomAllowance && item.denomAllowance.length > 0) {
                            var allowance = item.denomAllowance[0];
                            quota = (allowance.quotaValue || '') + ' ' + (allowance.quotaUnit || '');
                        }

                        html += '<tr>';
                        html += '<td>' + (item.denomId || '-') + '</td>';
                        html += '<td>' + (item.denomName || '-') + '</td>';
                        html += '<td><strong>' + quota.trim() + '</strong></td>';
                        
                        // --- INI ADALAH BARIS YANG DIPERBAIKI ---
                        // 'D + (' salah, seharusnya '+'
                        html += '<td>' + (item.denomValidity || '-') + '</td>';
                        // ----------------------------------------

                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    
                    // Masukkan tabel ke modal
                    $('#packageDenomContent').html(html);
                    
                } else if (response.status === true && response.data && response.data.denom && response.data.denom.length === 0) {
                     $('#packageDenomContent').html('<p class="text-center text-warning p-4">Tidak ada data denom ditemukan untuk paket ini.</p>');
                } else {
                    // Tampilkan error dari API
                    $('#packageDenomContent').html('<p class="text-center text-danger p-4">Gagal memuat data: ' + (response.message || 'Format response tidak dikenal.') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                // Tampilkan error AJAX
                $('#packageDenomContent').html('<p class="text-center text-danger p-4">Error AJAX: ' + error + ' (Code: ' + xhr.status + ')</p>');
            }
        });
    });
});
</script>
<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>