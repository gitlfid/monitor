<?php
require_once 'includes/header.php';

$db = db_connect();
// Ambil 100 log terbaru dari tabel inject_history
// Sesuaikan query ini jika Anda hanya ingin melihat status tertentu
$stmt = $db->query("SELECT * FROM inject_history ORDER BY updated_at DESC LIMIT 100");
$logs = $stmt->fetchAll();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Injection Status Log (Table: inject_history)</h1>
            </div>
        </div>
    </div>
</div>
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Displaying the 100 most recent injection logs</h3>
                    </div>
                    <div class="card-body">
                        <table id="logTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Update Time</th>
                                    <th>Request ID</th>
                                    <th>Status</th>
                                    <th>Error Code</th>
                                    <th>Error Message</th>
                                    <th>Detail Response (JSON)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['updated_at'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['request_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $status = htmlspecialchars($log['status'] ?? 'N/A');
                                        $badge_class = 'badge-secondary';
                                        if (strtoupper($status) == 'SUCCESS') {
                                            $badge_class = 'badge-success';
                                        } elseif (strtoupper($status) == 'FAILED') {
                                            $badge_class = 'badge-danger';
                                        } elseif (strtoupper($status) == 'SUBMITTED') {
                                            $badge_class = 'badge-info';
                                        }
                                        echo "<span class='badge {$badge_class}'>{$status}</span>";
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['error_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['error_message'] ?? 'N/A'); ?></td>
                                    <td>
                                        <pre style="white-space: pre-wrap; word-break: break-all; max-height: 100px; overflow-y: auto;"></pre>
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
</section>

<?php ob_start(); ?>
<script>
$(function () {
    $("#logTable").DataTable({
        "responsive": true, 
        "lengthChange": false, 
        "autoWidth": false,
        "order": [[ 0, "desc" ]] // Urutkan berdasarkan Waktu Update
    });
});
</script>
<?php
$page_scripts = ob_get_clean();
require_once 'includes/footer.php';
// Tambahkan script spesifik halaman ini ke footer
echo $page_scripts;
?>