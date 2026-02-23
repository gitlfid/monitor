<?php
// ajax_track_delivery.php

// 1. Load Koneksi Database (Penting untuk Update Status)
require_once '../config/database.php';

if (!isset($_GET['resi']) || !isset($_GET['kurir'])) {
    echo "Invalid Request";
    exit;
}

// Sanitasi input untuk keamanan Database
$resi = $conn->real_escape_string($_GET['resi']);
$kurir = $_GET['kurir'];
$apiKey = '485762cb-0ade-41d3-afad-6da124ff90cb'; // API Key Anda

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
    echo "<div class='alert alert-danger'>cURL Error: $err</div>";
    exit;
}

// 3. Decode JSON
$result = json_decode($response, true);

// Cek apakah data ada
if (!isset($result['data'])) {
    echo "<div class='alert alert-warning text-center p-4 m-3 rounded'>
            <i class='bi bi-exclamation-circle fs-1'></i><br>
            <strong>Data tidak ditemukan.</strong><br>
            Pastikan nomor resi dan kurir benar.<br>
            <small class='text-muted'>Response: ".htmlspecialchars($response)."</small>
          </div>";
    exit;
}

$data = $result['data'];

// Penentuan Warna Status
$statusLower = strtolower($data['status']);
$statusColor = 'text-primary';
if(strpos($statusLower, 'delivered') !== false || strpos($statusLower, 'berhasil') !== false) {
    $statusColor = 'text-success';
} elseif(strpos($statusLower, 'problem') !== false || strpos($statusLower, 'returned') !== false) {
    $statusColor = 'text-danger';
}

// =================================================================================
// 4. LOGIC UPDATE DATABASE (TETAP UTUH, TIDAK DIUBAH)
// =================================================================================
if ($data['status'] == 'Delivered') {
    $deliveredDate = null;
    if (isset($data['histories']) && is_array($data['histories'])) {
        foreach ($data['histories'] as $history) {
            if (stripos($history['status'], 'delivered') !== false) {
                $deliveredDate = date('Y-m-d H:i:s', strtotime($history['date']));
                break; 
            }
        }
    }
    if ($deliveredDate) {
        $updateSql = "UPDATE deliveries SET 
                      status = 'Delivered', 
                      delivered_date = '$deliveredDate', 
                      last_tracking_update = NOW() 
                      WHERE tracking_number = '$resi'";
        $conn->query($updateSql);
    }
}
// =================================================================================
?>

<style>
    .trk-container { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; padding: 5px 15px 15px 15px; }
    
    /* Header Area */
    .trk-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; margin-bottom: 20px; }
    .trk-resi-title { font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: block; }
    .trk-resi-val { font-size: 1.1rem; font-weight: 800; color: #0f172a; font-family: 'SFMono-Regular', Consolas, monospace; }
    .trk-courier-badge { background-color: #1e293b; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-left: 8px; vertical-align: middle; }
    .trk-ext-link { font-size: 0.8rem; font-weight: 600; color: #2563eb; background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 6px 12px; border-radius: 6px; text-decoration: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
    .trk-ext-link:hover { background-color: #dbeafe; color: #1e40af; }

    /* Current Status */
    .trk-status-title { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
    .trk-status-val { font-size: 1.75rem; font-weight: 800; margin-bottom: 25px; letter-spacing: -0.5px; }

    /* Kotak Origin & Destination (Flex 33.3% agar Simetris) */
    .trk-route-card { display: flex; align-items: center; justify-content: space-between; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 20px; margin-bottom: 30px; }
    .trk-route-part { width: 33.33%; }
    .trk-route-origin { text-align: left; }
    .trk-route-arrow { text-align: center; color: #94a3b8; font-size: 1.5rem; }
    .trk-route-dest { text-align: right; }
    
    .trk-loc-label { font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: block; }
    .trk-loc-name { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 4px; text-transform: uppercase; }
    .trk-loc-addr { font-size: 0.75rem; color: #64748b; line-height: 1.4; display: block; }

    /* Timeline */
    .trk-history-head { font-size: 0.85rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; }
    .trk-timeline { position: relative; margin-left: 10px; padding-bottom: 10px; }
    .trk-timeline::before { content: ''; position: absolute; top: 5px; bottom: 0; left: 6px; width: 2px; background-color: #e2e8f0; }
    
    .tl-item { position: relative; padding-left: 30px; margin-bottom: 25px; }
    .tl-item:last-child { margin-bottom: 0; }
    .tl-dot { position: absolute; left: 0; top: 6px; width: 14px; height: 14px; border-radius: 50%; background-color: #e2e8f0; border: 2px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; z-index: 2; }
    .tl-item.first .tl-dot { background-color: #3b82f6; border-color: #fff; box-shadow: 0 0 0 2px #bfdbfe; }
    
    .tl-date { font-size: 0.8rem; color: #64748b; margin-bottom: 4px; display: block; }
    .tl-status { font-size: 0.95rem; font-weight: 700; color: #1e40af; margin-bottom: 6px; }
    .tl-item.first .tl-status { color: #0f172a; }
    .tl-msg { font-size: 0.85rem; color: #475569; background-color: #f1f5f9; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; display: block; line-height: 1.5; }
</style>

<div class="trk-container">
    <div class="trk-header">
        <div>
            <span class="trk-resi-title">Tracking Information</span>
            <span class="trk-resi-val"><?= $resi ?></span>
            <span class="trk-courier-badge"><?= $kurir ?></span>
        </div>
        <a href="https://klikresi.com/tracking?resi=<?= $resi ?>&kurir=<?= $kurir ?>" target="_blank" class="trk-ext-link">
            External <i class="bi bi-box-arrow-up-right"></i>
        </a>
    </div>

    <div class="trk-status-title">CURRENT STATUS</div>
    <div class="trk-status-val <?= $statusColor ?>"><?= htmlspecialchars($data['status']) ?></div>

    <div class="trk-route-card">
        <div class="trk-route-part trk-route-origin">
            <span class="trk-loc-label">ORIGIN</span>
            <div class="trk-loc-name"><?= htmlspecialchars($data['origin']['contact_name'] ?? 'PT. LINKSFIELD NETWORKS') ?></div>
            <span class="trk-loc-addr"><?= htmlspecialchars($data['origin']['address'] ?? '-') ?></span>
        </div>
        
        <div class="trk-route-part trk-route-arrow">
            <i class="bi bi-arrow-right"></i>
        </div>
        
        <div class="trk-route-part trk-route-dest">
            <span class="trk-loc-label">DESTINATION</span>
            <div class="trk-loc-name text-primary"><?= htmlspecialchars($data['destination']['contact_name'] ?? 'UNKNOWN DESTINATION') ?></div>
            <span class="trk-loc-addr"><?= htmlspecialchars($data['destination']['address'] ?? '-') ?></span>
        </div>
    </div>

    <div class="trk-history-head">SHIPMENT HISTORY</div>
    
    <div class="trk-timeline">
        <?php if(isset($data['histories']) && is_array($data['histories']) && count($data['histories']) > 0): ?>
            <?php foreach($data['histories'] as $index => $hist): 
                $isFirst = ($index === 0) ? 'first' : '';
                
                // Format Tanggal (Contoh: 28 Nov 2025 14:07)
                $dateObj = new DateTime($hist['date']);
                $formattedDate = $dateObj->format('d M Y H:i');
            ?>
            <div class="tl-item <?= $isFirst ?>">
                <div class="tl-dot"></div>
                <span class="tl-date"><?= $formattedDate ?></span>
                <div class="tl-status"><?= htmlspecialchars($hist['status']) ?></div>
                <span class="tl-msg"><?= htmlspecialchars($hist['message']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-light border text-center text-muted small py-4">
                <i class="bi bi-clock-history fs-3 d-block mb-2 text-secondary"></i>
                Tidak ada riwayat perjalanan logistik yang tersedia saat ini.
            </div>
        <?php endif; ?>
    </div>
</div>