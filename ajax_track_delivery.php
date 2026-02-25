<?php
// =========================================================================
// FILE: ajax_track_delivery.php
// UPDATE: Fix DB Connection Error & Sync UI with Helpdesk Price Reference
// =========================================================================
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Load Koneksi Database dari environment sistem SAAT INI
if (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
} else if (file_exists('../includes/config.php')) {
    require_once '../includes/config.php';
}

if (file_exists('includes/sim_helper.php')) {
    require_once 'includes/sim_helper.php';
} else if (file_exists('../includes/sim_helper.php')) {
    require_once '../includes/sim_helper.php';
}

// Inisialisasi DB
$db = null;
if (function_exists('db_connect')) {
    $db = db_connect();
}

if (!isset($_GET['resi']) || !isset($_GET['kurir'])) {
    echo "<div class='alert alert-danger m-3'>Invalid Request</div>";
    exit;
}

$resi = htmlspecialchars(trim($_GET['resi']));
$kurir = htmlspecialchars(trim($_GET['kurir']));
$apiKey = '485762cb-0ade-41d3-afad-6da124ff90cb'; // API Key KlikResi Anda

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
    echo "<div class='alert alert-danger m-3'>cURL Error: $err</div>";
    exit;
}

// 3. Decode JSON
$result = json_decode($response, true);

if (!isset($result['data']) || !is_array($result['data'])) {
    echo "<div class='alert alert-warning text-center p-4 m-3 rounded'>
            <i class='bi bi-exclamation-circle fs-1'></i><br>
            <strong>Data tidak ditemukan.</strong><br>
            Pastikan nomor resi dan kurir benar.<br>
          </div>";
    exit;
}

$data = $result['data'];
$mainStatus = $data['status'] ?? 'Unknown';

// Penentuan Warna Status
$statusLower = strtolower($mainStatus);
$statusColor = '#059669'; // Default Green (Mirip gambar referensi)
if(strpos($statusLower, 'problem') !== false || strpos($statusLower, 'returned') !== false) {
    $statusColor = '#dc2626'; // Red
} elseif(strpos($statusLower, 'transit') !== false || strpos($statusLower, 'process') !== false) {
    $statusColor = '#2563eb'; // Blue
}

// =================================================================================
// 4. LOGIC UPDATE DATABASE (Disesuaikan dengan tabel sim_tracking_logistics)
// =================================================================================
if (strpos($statusLower, 'delivered') !== false && $db) {
    $deliveredDate = null;
    if (isset($data['histories']) && is_array($data['histories'])) {
        foreach ($data['histories'] as $history) {
            if (stripos($history['status'] ?? '', 'delivered') !== false) {
                $deliveredDate = date('Y-m-d', strtotime($history['date']));
                break; 
            }
        }
    }
    
    if (!$deliveredDate) {
        $deliveredDate = date('Y-m-d'); // Fallback hari ini
    }

    try {
        if (isset($db_type) && $db_type === 'pdo') {
            $stmt = $db->prepare("UPDATE sim_tracking_logistics SET status = 'Delivered', received_date = ? WHERE awb = ?");
            $stmt->execute([$deliveredDate, $resi]);
        } else {
            $safe_dd = mysqli_real_escape_string($db, $deliveredDate);
            $safe_resi = mysqli_real_escape_string($db, $resi);
            mysqli_query($db, "UPDATE sim_tracking_logistics SET status='Delivered', received_date='$safe_dd' WHERE awb='$safe_resi'");
        }
    } catch (Exception $e) {}
}
// =================================================================================

// Ekstrak data origin/destination
$origin_name = $data['origin']['contact_name'] ?? 'PT. LINKSFIELD NETWORKS IND';
$origin_addr = $data['origin']['address'] ?? '-';
$dest_name = $data['destination']['contact_name'] ?? 'UNKNOWN DESTINATION';
$dest_addr = $data['destination']['address'] ?? '-';
?>

<style>
    .trk-container { font-family: 'Inter', -apple-system, sans-serif; background: #fff; border-radius: 8px;}
    
    /* Box Informasi Atas */
    .trk-top-box { padding: 20px 24px; border-bottom: 8px solid #f3f4f6; }
    
    .trk-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .trk-resi-info { font-size: 0.8rem; color: #6b7280; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;}
    .trk-resi-info strong { color: #111827; font-weight: 800; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.85rem;}
    .trk-courier-badge { background-color: #374151; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; margin-left: 4px; }
    
    .trk-ext-link { font-size: 0.75rem; font-weight: 600; color: #2563eb; border: 1px solid #bfdbfe; padding: 4px 10px; border-radius: 6px; text-decoration: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
    .trk-ext-link:hover { background-color: #eff6ff; color: #1d4ed8; }

    .trk-status-label { font-size: 0.7rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
    .trk-status-val { font-size: 1.5rem; font-weight: 700; margin-bottom: 20px; line-height: 1.2;}

    /* Kotak Origin -> Destination (Simetris) */
    .trk-route-box { display: flex; align-items: stretch; background-color: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
    .trk-route-col { flex: 1; padding: 14px 18px; width: 45%; }
    .trk-route-origin { border-right: 1px solid #e5e7eb; text-align: left; position: relative;}
    .trk-route-dest { text-align: right; border-left: 1px solid #fff;} /* Offset untuk arrow */
    
    .trk-route-arrow-icon { position: absolute; right: -12px; top: 50%; transform: translateY(-50%); background: #f8fafc; color: #9ca3af; font-size: 1.1rem; width: 24px; text-align: center; z-index: 2;}

    .trk-loc-label { font-size: 0.65rem; font-weight: 800; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: block; }
    .trk-loc-name { font-size: 0.85rem; font-weight: 700; color: #111827; margin-bottom: 2px; text-transform: uppercase; }
    .trk-loc-dest-name { color: #2563eb; }
    .trk-loc-addr { font-size: 0.7rem; color: #6b7280; line-height: 1.4; display: block; text-transform: uppercase;}

    /* Bagian History */
    .trk-history-box { padding: 20px 24px; background-color: #fff; }
    .trk-history-title { font-size: 0.8rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px; }
    
    /* Timeline List (Persis Gambar) */
    .trk-timeline { position: relative; padding-left: 16px; margin-top: 5px; }
    .trk-timeline::before { content: ''; position: absolute; top: 6px; bottom: 0; left: 5px; width: 2px; background-color: #e5e7eb; }
    
    .tl-item { position: relative; padding-bottom: 24px; }
    .tl-item:last-child { padding-bottom: 0; }
    
    .tl-dot { position: absolute; left: -16px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background-color: #fff; border: 2px solid #d1d5db; z-index: 2; }
    .tl-item.first .tl-dot { background-color: #2563eb; border-color: #2563eb; box-shadow: 0 0 0 3px #dbeafe; }
    
    .tl-date { font-size: 0.75rem; color: #6b7280; margin-bottom: 4px; display: block; }
    .tl-status { font-size: 0.85rem; font-weight: 700; color: #1e40af; margin-bottom: 6px; }
    .tl-item.first .tl-status { color: #111827; }
    
    .tl-msg { font-size: 0.8rem; color: #4b5563; background-color: #e2e8f0; padding: 10px 14px; border-radius: 6px; display: block; line-height: 1.4; border: 1px solid #cbd5e1; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);}
</style>

<div class="trk-container">
    <div class="trk-top-box">
        <div class="trk-header-row">
            <div class="trk-resi-info">
                RESI: <strong><?= $resi ?></strong> <span class="mx-1 text-muted fw-normal">|</span> COURIER: <span class="trk-courier-badge"><?= strtoupper($kurir) ?></span>
            </div>
            <a href="https://klikresi.com/tracking?resi=<?= $resi ?>&kurir=<?= $kurir ?>" target="_blank" class="trk-ext-link">
                External <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>

        <div class="trk-status-label">CURRENT STATUS</div>
        <div class="trk-status-val" style="color: <?= $statusColor ?>;"><?= $mainStatus ?></div>

        <div class="trk-route-box">
            <div class="trk-route-col trk-route-origin">
                <span class="trk-loc-label">ORIGIN</span>
                <div class="trk-loc-name"><?= htmlspecialchars($origin_name) ?></div>
                <span class="trk-loc-addr"><?= htmlspecialchars($origin_addr) ?></span>
                <i class="bi bi-arrow-right trk-route-arrow-icon"></i>
            </div>
            <div class="trk-route-col trk-route-dest">
                <span class="trk-loc-label">DESTINATION</span>
                <div class="trk-loc-name trk-loc-dest-name"><?= htmlspecialchars($dest_name) ?></div>
                <span class="trk-loc-addr"><?= htmlspecialchars($dest_addr) ?></span>
            </div>
        </div>
    </div>

    <div class="trk-history-box">
        <div class="trk-history-title">SHIPMENT HISTORY</div>
        
        <div class="trk-timeline">
            <?php if(isset($data['histories']) && is_array($data['histories']) && count($data['histories']) > 0): ?>
                <?php foreach($data['histories'] as $index => $hist): 
                    $isFirst = ($index === 0) ? 'first' : '';
                    
                    // Konversi Tanggal "2025-11-28T14:07:00+07:00" -> "28 Nov 2025 14:07"
                    $dateObj = date_create($hist['date']);
                    $formattedDate = $dateObj ? $dateObj->format('d M Y H:i') : $hist['date'];
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
                    Tidak ada riwayat perjalanan logistik.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>