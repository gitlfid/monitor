<?php
require_once 'includes/header.php'; // Ini sudah termasuk auth_check

$db = db_connect();

/*
=================================================
== SQL BARU DENGAN LOGIKA YANG AKURAT ==
=================================================
Logika baru:
1. (CompanyUsageWithLag): Menghitung selisih (usage - previous_usage) PER KLIEN.
2. (CompanyDailyIncremental): Menangani 3 kasus:
   - Hari Normal: (usage_mb - previous_usage_mb)
   - Hari Reset Paket: (jika usage < previous_usage), maka penggunaan harian adalah 'usage_mb' baru.
   - Hari Pertama Klien: (jika previous_usage IS NULL), maka penggunaan harian adalah 'usage_mb'
     (Ini sesuai dengan perhitungan manual Anda, misal 610 GB pada 20 Okt)
3. (FinalDailyTotal): Menjumlahkan (SUM) semua penggunaan harian dari SEMUA KLIEN.
4. (CumulativeTotal): Menjumlahkan (SUM) total kumulatif untuk ditampilkan.
5. (Final Join): Menggabungkan keduanya.
*/
$sql = "
    WITH CompanyUsageWithLag AS (
        SELECT
            company_id,
            snapshot_date,
            usage_mb,
            LAG(usage_mb, 1) OVER (PARTITION BY company_id ORDER BY snapshot_date ASC) AS previous_usage_mb
        FROM
            daily_usage_snapshots
    ),
    CompanyDailyIncremental AS (
        SELECT
            snapshot_date,
            CASE
                WHEN previous_usage_mb IS NULL THEN usage_mb -- Hari pertama klien, hitung semua sbg 'harian'
                WHEN usage_mb < previous_usage_mb THEN usage_mb -- Terjadi reset paket, hitung nilai baru sbg 'harian'
                ELSE (usage_mb - previous_usage_mb) -- Hari normal, hitung selisihnya
            END AS incremental_usage_mb
        FROM
            CompanyUsageWithLag
    ),
    FinalDailyTotal AS (
        SELECT
            snapshot_date,
            SUM(incremental_usage_mb) AS total_daily_incremental_mb
        FROM
            CompanyDailyIncremental
        GROUP BY
            snapshot_date
    ),
    CumulativeTotal AS (
        SELECT
            snapshot_date,
            SUM(usage_mb) AS total_cumulative_mb
        FROM
            daily_usage_snapshots
        GROUP BY
            snapshot_date
    )
    SELECT
        fdt.snapshot_date,
        fdt.total_daily_incremental_mb,
        ct.total_cumulative_mb
    FROM
        FinalDailyTotal fdt
    JOIN
        CumulativeTotal ct ON fdt.snapshot_date = ct.snapshot_date
    ORDER BY
        fdt.snapshot_date DESC
    LIMIT 30;
";

$stmt = $db->query($sql);
$summary_data = $stmt->fetchAll();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Rekap Penggunaan Harian</h1>
            </div>
        </div>
    </div>
</div>
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Total Penggunaan Harian (Gabungan Semua Klien) - 30 Hari Terakhir</h3>
                    </div>
                    <div class="card-body">
                        <table id="summaryTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Total Penggunaan Harian (GB)</th>
                                    <th>Total Kumulatif Penggunaan (GB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($summary_data)): ?>
                                    <tr><td colspan="3" class="text-center">Belum ada data rekap. CRON Job belum berjalan.</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach ($summary_data as $row): ?>
                                    <?php
                                    // Konversi MB ke GB
                                    $daily_usage_gb = round($row['total_daily_incremental_mb'] / 1024, 2);
                                    $cumulative_usage_gb = round($row['total_cumulative_mb'] / 1024, 2);
                                    ?>
                                    <tr>
                                        <td><?php echo (new DateTime($row['snapshot_date']))->format('d F Y'); ?></td>
                                        <td><strong><?php echo $daily_usage_gb; ?> GB</strong></td>
                                        <td><?php echo $cumulative_usage_gb; ?> GB</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-info">
                    <h5><i class="icon fas fa-info"></i> Perhitungan Akurat</h5>
                    <p>Perhitungan ini sekarang sudah akurat dengan menghitung selisih penggunaan per klien terlebih dahulu, sebelum menjumlahkannya.</p>
                    <p>Logika ini juga sudah menangani penambahan klien baru atau jika ada paket klien yang di-reset.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php ob_start(); ?>
<script>
$(function () {
    $("#summaryTable").DataTable({
        "responsive": true, 
        "lengthChange": false, 
        "autoWidth": false,
        "searching": false,
        "paging": false,
        "info": false,
        "order": [[ 0, "desc" ]] // Urutkan berdasarkan Tanggal
    });
});
</script>
<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
echo $page_scripts;
?>