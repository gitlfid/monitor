<?php
// ajax_track_delivery.php

// 1. Load Koneksi Database
// Sesuaikan path ini jika struktur folder berbeda
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Gunakan koneksi PDO standar sistem Anda
$db = db_connect();

if (!isset($_GET['resi']) || !isset($_GET['kurir'])) {
    echo "<div class='p-4 text-center text-danger'>Parameter tracking tidak lengkap.</div>";
    exit;
}

// Sanitasi & Format Input
$resi = trim($_GET['resi']);
// API biasanya butuh huruf kecil (jne, jnt, sicepat)
$kurir = strtolower(trim($_GET['kurir'])); 
$apiKey = '485762cb-0ade-41d3-afad-6da124ff90cb'; // API Key dari file master Anda

// 2. Panggil API KlikResi
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://klikresi.com/api/trackings/$resi/couriers/$kurir",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    "x-api-key: $apiKey"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "<div class='alert alert-danger m-3'>Koneksi API Gagal: $err</div>";
    exit;
}

// 3. Decode JSON
$result = json_decode($response, true);

// Cek apakah data valid atau Resi Not Found di API
if (!isset($result['data'])) {
    echo "<div class='alert alert-warning text-center m-4'>
            <i class='bi bi-exclamation-triangle fs-1 text-warning mb-2 d-block'></i>
            <strong>Data Tidak Ditemukan</strong><br>
            <span class='small text-muted'>Resi: $resi | Kurir: ".strtoupper($kurir)."</span><br>
            <small class='mt-2 d-block'>Response: " . htmlspecialchars($response) . "</small>
          </div>";
    exit;
}

$data = $result['data'];
$statusColor = ($data['status'] == 'Delivered') ? 'text-success' : 'text-primary';

// =================================================================================
// 4. LOGIC AUTO-UPDATE DATABASE
// =================================================================================
if ($data['status'] == 'Delivered') {
    $deliveredDate = date('Y-m-d'); // Default hari ini
    
    // Coba cari tanggal persis dari history
    if (isset($data['histories']) && is_array($data['histories'])) {
        foreach ($data['histories'] as $history) {
            if (stripos($history['status'], 'delivered') !== false) {
                $deliveredDate = date('Y-m-d', strtotime($history['date']));
                break;
            }
        }
    }

    try {
        // Update tabel lokal jika status berubah jadi Delivered
        $sqlUpd = "UPDATE sim_tracking_logistics SET 
                   status = 'Delivered', 
                   received_date = ? 
                   WHERE awb = ? AND type = 'delivery' AND status != 'Delivered'";
        
        $stmt = $db->prepare($sqlUpd);
        $stmt->execute([$deliveredDate, $resi]);
    } catch (Exception $e) {
        // Silent fail agar user tetap melihat data tracking walau update db gagal
    }
}
// =================================================================================
?>

<style>
    .track-card { background: #fff; border-radius: 0; padding: 20px; } /* Reset border radius for modal fit */
    .timeline { position: relative; padding-left: 30px; border-left: 2px solid #e9ecef; margin-top: 15px; }
    .timeline-item { position: relative; margin-bottom: 25px; }
    .timeline-dot { 
        position: absolute; left: -36px; top: 0; width: 14px; height: 14px; 
        border-radius: 50%; background: #fff; border: 3px solid #ced4da; 
    }
    .timeline-item:first-child .timeline-dot { border-color: #435ebe; background: #435ebe; }
    .timeline-date { font-size: 0.75rem; color: #6c757d; margin-bottom: 2px; }
    .timeline-status { font-weight: 700; color: #435ebe; font-size: 0.9rem; }
    .route-arrow { font-size: 1.2rem; color: #ced4da; margin: 0 10px; }
</style>

<div class="track-card">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
        <div>
            <div class="text-muted small text-uppercase fw-bold">
                RESI: <span class="font-monospace text-dark"><?= $resi ?></span> | COURIER: <?= strtoupper($kurir) ?>
            </div>
        </div>
        <div>
            <a href="https://berdu.id/cek-resi?courier=<?= $kurir ?>&resi=<?= $resi ?>" target="_blank" class="btn btn-outline-primary btn-sm pt-0 pb-0" style="font-size: 0.7rem;">
                External <i class="bi bi-box-arrow-up-right ms-1"></i>
            </a>
        </div>
    </div>
    
    <div class="row align-items-center mb-4">
        <div class="col-12 mb-2">
            <small class="text-muted text-uppercase fw-bold" style="font-size:0.7rem">CURRENT STATUS</small>
            <h3 class="<?= $statusColor ?> fw-bold mb-0"><?= $data['status'] ?></h3>
        </div>
        
        <div class="col-12 bg-light p-3 rounded d-flex align-items-center justify-content-between">
            <div class="text-truncate" style="max-width: 40%">
                <small class="text-muted text-uppercase d-block" style="font-size:0.65rem">ORIGIN</small>
                <span class="fw-bold text-dark small"><?= $data['origin']['city'] ?? 'ORIGIN' ?></span>
            </div>
            <i class="bi bi-arrow-right route-arrow"></i>
            <div class="text-end text-truncate" style="max-width: 40%">
                <small class="text-muted text-uppercase d-block" style="font-size:0.65rem">DESTINATION</small>
                <span class="fw-bold text-dark small"><?= $data['destination']['city'] ?? 'DESTINATION' ?></span>
            </div>
        </div>
    </div>

    <h6 class="text-uppercase text-muted fw-bold small mb-3">Shipment History</h6>
    
    <div class="timeline">
        <?php if(isset($data['histories']) && is_array($data['histories'])): ?>
            <?php foreach($data['histories'] as $hist): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-date">
                        <?= date('d M Y H:i', strtotime($hist['date'])) ?>
                    </div>
                    <div class="timeline-status mb-1">
                        <?= $hist['status'] ?>
                    </div>
                    <div class="bg-light p-2 rounded border text-muted small">
                        <?= $hist['message'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted text-center small">Riwayat perjalanan belum tersedia.</p>
        <?php endif; ?>
    </div>
</div>