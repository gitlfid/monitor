<?php
// =========================================================================
// FILE: ajax_track_delivery.php
// DESC: API Fetcher & UI Renderer for Shipment Tracking (Exact Replica)
// =========================================================================

// Nonaktifkan error display agar tidak merusak format HTML/JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_GET['resi']) || !isset($_GET['kurir'])) {
    echo "<div class='alert alert-danger m-3'>Parameter resi atau kurir tidak lengkap.</div>";
    exit;
}

$resi = htmlspecialchars(trim($_GET['resi']));
$kurir = htmlspecialchars(trim(strtolower($_GET['kurir'])));
$apiKey = '485762cb-0ade-41d3-afad-6da124ff90cb'; // API Key Anda

// 1. Eksekusi cURL ke API KlikResi
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
    echo "<div class='alert alert-danger m-3'><i class='bi bi-wifi-off me-2'></i>Koneksi ke API Kurir gagal: $err</div>";
    exit;
}

// 2. Decode JSON
$result = json_decode($response, true);

if (!$result || !isset($result['data'])) {
    echo "<div class='alert alert-warning m-4 text-center'>
            <i class='bi bi-search fs-1 d-block mb-2'></i>
            <h6 class='fw-bold mb-1'>Data tidak ditemukan</h6>
            <p class='small text-muted mb-0'>Pastikan nomor resi <b>$resi</b> dan kurir <b>".strtoupper($kurir)."</b> valid.</p>
          </div>";
    exit;
}

$data = $result['data'];

// 3. Persiapkan Variabel Data
$status = $data['status'] ?? 'Unknown';
$origin_name = $data['origin']['contact_name'] ?? 'PT. LINKSFIELD NETWORKS IND';
$origin_address = $data['origin']['address'] ?? '-';
$dest_name = $data['destination']['contact_name'] ?? '-';
$dest_address = $data['destination']['address'] ?? '-';

// Warna Status
$statusColor = '#1d4ed8'; // Default Blue (InTransit)
if (strtolower($status) === 'delivered' || strtolower($status) === 'berhasil') {
    $statusColor = '#059669'; // Green
} elseif (strtolower($status) === 'problem' || strtolower($status) === 'returned') {
    $statusColor = '#dc2626'; // Red
}

$externalUrl = "https://klikresi.com/tracking?resi=$resi&kurir=$kurir"; // Fallback URL if needed
?>

<style>
    .track-wrapper { font-family: 'Inter', sans-serif; }
    .track-header { padding: 1.5rem; border-bottom: 1px solid #e5e7eb; background: #fff; }
    
    .resi-text { font-size: 0.85rem; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    .resi-val { color: #111827; font-weight: 700; font-family: 'SFMono-Regular', Consolas, monospace; }
    .btn-external { font-size: 0.75rem; color: #2563eb; border: 1px solid #bfdbfe; background: #eff6ff; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-weight: 600; transition: 0.2s; }
    .btn-external:hover { background: #dbeafe; color: #1e40af; }
    
    .status-label { font-size: 0.7rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
    .status-main { font-size: 1.75rem; font-weight: 700; margin-bottom: 1.5rem; }
    
    .route-box { display: flex; align-items: stretch; background-color: #f3f4f6; border-radius: 8px; overflow: hidden; margin-bottom: 0.5rem; }
    .route-part { flex: 1; padding: 1rem 1.25rem; }
    .route-arrow { width: 40px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 1.25rem; }
    .r-label { font-size: 0.65rem; font-weight: 800; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .r-name { font-size: 0.9rem; font-weight: 700; color: #111827; margin-bottom: 2px; text-transform: uppercase; }
    .r-dest { color: #1d4ed8; }
    .r-addr { font-size: 0.75rem; color: #6b7280; line-height: 1.4; }

    /* Timeline Section */
    .timeline-section { background-color: #f9fafb; padding: 1.5rem; border-top: 1px solid #e5e7eb; }
    .timeline-title { font-size: 0.8rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1.5rem; }
    
    .tl-wrap { position: relative; padding-left: 14px; }
    .tl-wrap::before { content: ''; position: absolute; left: 6px; top: 8px; bottom: 0; width: 2px; background-color: #e5e7eb; }
    
    .tl-item { position: relative; padding-bottom: 1.5rem; }
    .tl-item:last-child { padding-bottom: 0; }
    
    .tl-dot { position: absolute; left: -14px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background-color: #fff; border: 2px solid #d1d5db; z-index: 2; }
    .tl-item.first .tl-dot { background-color: #2563eb; border-color: #2563eb; }
    
    .tl-date { font-size: 0.75rem; color: #6b7280; margin-bottom: 4px; }
    .tl-status { font-size: 0.85rem; font-weight: 700; color: #1d4ed8; margin-bottom: 6px; }
    .tl-item.first .tl-status { color: #111827; }
    
    .tl-msg { background-color: #e5e7eb; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; color: #4b5563; line-height: 1.4; display: inline-block; width: 100%; border: 1px solid #d1d5db; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); }
</style>

<div class="track-wrapper">
    <div class="track-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="resi-text">
                RESI: <span class="resi-val"><?= $resi ?></span> &nbsp;|&nbsp; COURIER: <span class="resi-val"><?= strtoupper($kurir) ?></span>
            </div>
            <a href="<?= $externalUrl ?>" target="_blank" class="btn-external">External <i class="bi bi-box-arrow-up-right ms-1"></i></a>
        </div>

        <div class="status-label">CURRENT STATUS</div>
        <div class="status-main" style="color: <?= $statusColor ?>;"><?= $status ?></div>

        <div class="route-box">
            <div class="route-part">
                <div class="r-label">ORIGIN</div>
                <div class="r-name text-dark"><?= htmlspecialchars($origin_name) ?></div>
                <div class="r-addr"><?= htmlspecialchars($origin_address) ?></div>
            </div>
            <div class="route-arrow">
                <i class="bi bi-arrow-right"></i>
            </div>
            <div class="route-part text-end">
                <div class="r-label">DESTINATION</div>
                <div class="r-name r-dest"><?= htmlspecialchars($dest_name) ?></div>
                <div class="r-addr"><?= htmlspecialchars($dest_address) ?></div>
            </div>
        </div>
    </div>

    <div class="timeline-section">
        <div class="timeline-title">SHIPMENT HISTORY</div>
        
        <?php if (!empty($data['histories']) && is_array($data['histories'])): ?>
            <div class="tl-wrap">
                <?php foreach ($data['histories'] as $index => $hist): 
                    $isFirst = ($index === 0) ? 'first' : '';
                    
                    // Format Date dari 2025-11-28T14:07:00+07:00 -> 28 Nov 2025 14:07
                    $dateObj = new DateTime($hist['date']);
                    $formattedDate = $dateObj->format('d M Y H:i');
                ?>
                    <div class="tl-item <?= $isFirst ?>">
                        <div class="tl-dot"></div>
                        <div class="tl-date"><?= $formattedDate ?></div>
                        <div class="tl-status"><?= htmlspecialchars($hist['status']) ?></div>
                        <div class="tl-msg"><?= htmlspecialchars($hist['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted small border p-3 rounded bg-white">Tidak ada riwayat perjalanan logistik untuk saat ini.</div>
        <?php endif; ?>
    </div>
</div>