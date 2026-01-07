<?php
// (1) Load header (dari folder 'includes/')
require_once 'includes/header.php'; // Path ini sudah benar
$db = db_connect();

// Ambil data klien untuk filter
$stmt = $db->query("SELECT id, company_name FROM companies ORDER BY company_name ASC");
$companies = $stmt->fetchAll();
?>

<!-- (2) Load CSS Khusus untuk FullCalendar (WAJIB) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<style>
    /* Style untuk event di kalender */
    .fc-event.event-usage {
        background-color: #007bff; /* Biru */
        border-color: #007bff;
        color: #fff;
    }
    .fc-event-title { font-weight: bold; }

    /* === PERUBAHAN: Style untuk loading di modal === */
    #detailModalBody .loading-spinner {
        display: none;
        text-align: center;
        padding: 50px;
    }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Kalender Penggunaan Harian</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Filter Data</h3>
                        <div class="card-tools">
                            <!-- Filter Klien -->
                            <select id="clientFilter" class="form-control form-control-sm" style="width: 250px;">
                                <option value="all">Tampilkan Semua Klien</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- THE CALENDAR -->
                        <div id="calendar" style="padding: 10px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- (3) Modal untuk Detail Harian -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document"> <!-- PERUBAHAN: Ganti ke modal-xl (Extra Large) -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Detail Penggunaan Tanggal: </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <!-- === PERUBAHAN: Struktur Modal Body === -->
            <div class="modal-body" id="detailModalBody">
                
                <!-- (A) Spinner Loading -->
                <div class="loading-spinner p-5 text-center">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                    <p class="mt-2">Memuat data...</p>
                </div>

                <!-- (B) Konten (Chart & Tabel) - Awalnya disembunyikan -->
                <div class="modal-content-container" style="display: none;">
                    <div class="row">
                        <!-- Kolom Pie Chart -->
                        <div class="col-md-5">
                            <h5 class="text-center">Breakdown Penggunaan Harian</h5>
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="detailPieChart"></canvas>
                            </div>
                        </div>
                        <!-- Kolom Tabel -->
                        <div class="col-md-7">
                            <h5 class="text-center">Data Detail</h5>
                            <div id="detailTableContainer" style="max-height: 300px; overflow-y: auto;">
                                <!-- Tabel akan di-load di sini -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- === BATAS PERUBAHAN === -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<!-- (4) Load JavaScript Khusus Halaman Ini -->
<?php ob_start(); ?>
<!-- Load JS Library FullCalendar (WAJIB) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/id.js"></script> <!-- Bahasa Indonesia -->
<!-- Load Chart.js (Dipakai di header.php, tapi panggil lagi untuk pastikan) -->
<script src="adminlte/plugins/chart.js/Chart.min.js"></script>


<script>
$(function () {
    // === PERUBAHAN: Variabel untuk simpan instance chart ===
    var detailChartInstance = null;

    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek'
        },
        themeSystem: 'bootstrap',
        locale: 'id',
        initialView: 'dayGridMonth',
        
        events: {
            url: 'ajax/get_usage_events.php', 
            extraParams: function() {
                return {
                    client_id: $('#clientFilter').val()
                };
            }
        }, 

        // === PERUBAHAN: Modifikasi 'dateClick' untuk Chart ===
        dateClick: function(info) {
            var clickedDate = info.dateStr;
            var formattedDate = new Date(clickedDate + 'T00:00:00').toLocaleDateString('id-ID', {
                day: '2-digit', month: 'long', year: 'numeric'
            });

            // 1. Siapkan Modal
            $('#detailModalLabel').text('Detail Penggunaan Tanggal: ' + formattedDate);
            // Tampilkan spinner, sembunyikan konten
            $('#detailModalBody .loading-spinner').show();
            $('#detailModalBody .modal-content-container').hide();
            $('#detailModal').modal('show');

            // 2. Hancurkan chart lama (jika ada)
            if (detailChartInstance) {
                detailChartInstance.destroy();
                detailChartInstance = null;
            }

            // 3. Panggil AJAX
            $.ajax({
                url: 'ajax/get_usage_details.php',
                type: 'GET',
                data: { 
                    date: clickedDate,
                    client_id: $('#clientFilter').val() 
                },
                dataType: 'json',
                success: function(response) {
                    // Sembunyikan spinner, tampilkan konten
                    $('#detailModalBody .loading-spinner').hide();
                    $('#detailModalBody .modal-content-container').show();

                    if (response.status === true && response.logs.length > 0) {
                        
                        // (A) Buat Tabel (seperti sebelumnya)
                        var tableHtml = '<table class="table table-striped table-bordered">';
                        tableHtml += '<thead><tr><th>Klien</th><th>Penggunaan Harian (GB)</th><th>Total Kumulatif (GB)</th></tr></thead>';
                        tableHtml += '<tbody>';
                        response.logs.forEach(function(log) {
                            tableHtml += '<tr>';
                            tableHtml += '<td><strong>' + log.company_name + '</strong></td>';
                            tableHtml += '<td>' + log.daily_usage_gb + ' GB</td>';
                            tableHtml += '<td>' + log.cumulative_gb + ' GB</td>';
                            tableHtml += '</tr>';
                        });
                        tableHtml += '</tbody></table>';
                        $('#detailTableContainer').html(tableHtml); // Masukkan tabel

                        // (B) Buat Pie Chart (BARU)
                        // Cek apakah API ngirim data chart
                        if (response.chart && response.chart.labels && response.chart.labels.length > 0) {
                            var ctx = document.getElementById('detailPieChart').getContext('2d');
                            detailChartInstance = new Chart(ctx, {
                                type: 'doughnut', // Tipe chart: donat
                                data: {
                                    labels: response.chart.labels, // Data label dari API
                                    datasets: [{
                                        data: response.chart.data, // Data angka dari API
                                        // Buat warna-warni otomatis
                                        backgroundColor: generateDynamicColors(response.chart.data.length),
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    legend: {
                                        position: 'bottom', // Pindahkan legenda ke bawah
                                    }
                                }
                            });
                        } else {
                            $('#detailPieChart').html('<p class="text-center text-muted">Tidak ada data chart.</p>');
                        }

                    } else {
                        // Tampilkan pesan 'tidak ada data' di kedua kontainer
                        var noDataHtml = '<p class="text-center text-warning p-5">Tidak ada data penggunaan ditemukan untuk tanggal ini.</p>';
                        $('#detailTableContainer').html(noDataHtml);
                        $('#detailPieChart').parent().html(noDataHtml); // Target parent-nya
                    }
                },
                error: function() {
                    $('#detailModalBody .loading-spinner').hide();
                    var errorHtml = '<p class="text-center text-danger p-5">Gagal mengambil data dari server.</p>';
                    $('#detailModalBody .modal-content-container').html(errorHtml).show();
                }
            });
        },
    });

    calendar.render();

    // (C) Fungsi saat filter diganti
    $('#clientFilter').on('change', function() {
        calendar.refetchEvents();
    });

    // === PERUBAHAN: Fungsi helper untuk warna chart ===
    function generateDynamicColors(count) {
        var colors = [];
        var baseColors = [
            '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', 
            '#6f42c1', '#f012be', '#fd7e14', '#20c997', '#6610f2'
        ];
        for (var i = 0; i < count; i++) {
            colors.push(baseColors[i % baseColors.length]);
        }
        return colors;
    }
});
</script>

<?php
$page_scripts = ob_get_clean();
// (5) Load footer (dari folder 'includes/')
require_once 'includes/footer.php';
// Echo script spesifik halaman ini di akhir body
echo $page_scripts;
?>