<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ./");
    exit;
}
date_default_timezone_set('Asia/Jakarta');

require_once '../../core/db.php';

// Fetch Logs (Latest 200)
$logs = [];
$res = $conn->query("SELECT * FROM visitor_logs ORDER BY created_at DESC LIMIT 200");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
}

// API Endpoint for Live Update
if (isset($_GET['api']) && $_GET['api'] === 'get_logs_json') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $logs]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Detail | Admin Panel</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; color: #333; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .live-pulse { width: 10px; height: 10px; background: #22c55e; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; margin-right: 5px; }
        @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
        .form-check-input:checked { background-color: #22c55e; border-color: #22c55e; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-11 col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="./" class="text-decoration-none small fw-bold text-muted mb-2 d-block">← Kembali ke Panel Utama</a>
                    <h2 class="fw-bold m-0">Log Kunjungan Detail</h2>
                    <p class="text-muted small m-0">Menampilkan 200 interaksi terbaru dari mahasiswa.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch bg-white px-3 py-1 rounded shadow-sm border">
                        <input class="form-check-input" type="checkbox" id="autoUpdateToggle">
                        <label class="form-check-input-label small fw-bold ms-1" for="autoUpdateToggle">
                            <span id="liveStatus"><i class="bi bi-broadcast"></i> Live Update</span>
                        </label>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" onclick="clearLogs()">Hapus Semua Log</button>
                </div>
            </div>

            <div class="glass-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>IP & Lokasi / ISP</th>
                                <th>Perangkat & OS</th>
                                <th>Jenis Ujian</th>
                                <th>Aktivitas Mahasiswa / Bot</th>
                            </tr>
                        </thead>
                        <tbody id="logTableBody">
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" class="text-center p-5 text-muted">Belum ada data log tersimpan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php 
                                        $is_suspicious = ($log['country'] !== 'Indonesia' && $log['country'] !== 'Local/Private') || $log['is_bot']; 
                                    ?>
                                    <tr class="<?php echo $is_suspicious ? 'table-danger' : ''; ?>">
                                        <td class="fw-bold"><?php echo date('d/m H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <code class="<?php echo $is_suspicious ? 'text-danger fw-bold' : 'text-dark'; ?>"><?php echo $log['ip_address']; ?></code>
                                                <?php if($log['country'] && $log['country'] !== 'Local/Private'): ?>
                                                    <span class="small text-muted" title="<?php echo $log['country'] . ', ' . $log['city']; ?>">
                                                        📍 <?php echo $log['city'] ?: $log['country']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo $log['isp'] ?: 'ISP Internal/Localhost'; ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold">
                                                <?php if ($log['device_type'] == 'Mobile'): ?>
                                                    <i class="bi bi-smartphone text-info"></i>
                                                <?php elseif ($log['device_type'] == 'Tablet'): ?>
                                                    <i class="bi bi-tablet text-warning"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-pc-display text-secondary"></i>
                                                <?php endif; ?>
                                                <?php echo $log['brand'] ?: 'Generic'; ?>
                                            </div>
                                            <div class="text-muted small"><?php echo $log['os'] ?: 'OS Unknown'; ?></div>
                                        </td>
                                        <td>
                                            <span class="text-uppercase small fw-bold text-primary"><?php echo $log['exam_type']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <?php 
                                                    $labels = [
                                                        'page_load' => 'Buka Jadwal',
                                                        'tab_click' => 'Lihat Semester ' . ($log['semester'] ?: ''),
                                                        'theme_dark' => 'Ganti Tema: Gelap',
                                                        'theme_light' => 'Ganti Tema: Terang',
                                                        'view_all_days' => 'Lihat Semua Hari',
                                                        'view_today_only' => 'Lihat Hari Ini Saja',
                                                        'search' => 'Melakukan Pencarian',
                                                        'click_locked' => 'Akses Ujian (Terkunci)',
                                                        'click_ended' => 'Akses Ujian (Selesai)',
                                                        'click_exam_url' => 'Klik Masuk Ujian',
                                                        'unauthorized_panel_access' => 'Intai Halaman Login',
                                                        'login_failed_attempt' => 'Gagal Login (Percobaan)',
                                                        'login_success' => 'Login Admin Sukses',
                                                        'security_alert' => 'Akses Terlarang (Honeypot)'
                                                    ];
                                                    $action_text = $labels[$log['action']] ?? $log['action'];
                                                ?>
                                                <span class="badge <?php echo ($log['action'] == 'page_load' || $log['action'] == 'click_exam_url') ? 'bg-success' : 'bg-primary' ?> outline">
                                                    <?php echo $action_text; ?>
                                                </span>
                                                <?php if($log['is_bot']): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-robot"></i> BOT/CRAWLER</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
let updateInterval;

document.getElementById('autoUpdateToggle').addEventListener('change', function(e) {
    if (this.checked) {
        document.getElementById('liveStatus').innerHTML = '<div class="live-pulse"></div> Live Aktif';
        updateInterval = setInterval(fetchLogs, 10000); // 10 seconds
        fetchLogs();
    } else {
        document.getElementById('liveStatus').innerHTML = '<i class="bi bi-broadcast"></i> Live Update';
        clearInterval(updateInterval);
    }
});

async function fetchLogs() {
    try {
        const res = await fetch('./logs?api=get_logs_json');
        const result = await res.json();
        if (result.status === 'success') {
            renderLogs(result.data);
        }
    } catch (err) {}
}

function renderLogs(data) {
    const tbody = document.getElementById('logTableBody');
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center p-5 text-muted">Belum ada data log tersimpan.</td></tr>';
        return;
    }

    tbody.innerHTML = data.map(log => {
        const is_suspicious = (log.country !== 'Indonesia' && log.country !== 'Local/Private') || log.is_bot == 1;
        const timeStr = new Date(log.created_at).toLocaleDateString('id-ID', {day: '2-digit', month: '2-digit'}) + ' ' + new Date(log.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        
        return `
            <tr class="${is_suspicious ? 'table-danger' : ''}">
                <td class="fw-bold">${timeStr}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <code class="${is_suspicious ? 'text-danger fw-bold' : 'text-dark'}">${log.ip_address}</code>
                        ${log.country && log.country !== 'Local/Private' ? `<span class="small text-muted" title="${log.country}, ${log.city}">📍 ${log.city || log.country}</span>` : ''}
                    </div>
                    <div class="text-muted" style="font-size: 0.75rem;">${log.isp || 'ISP Internal/Localhost'}</div>
                </td>
                <td>
                    <div class="fw-bold">
                        ${log.device_type == 'Mobile' ? '<i class="bi bi-smartphone text-info"></i>' : (log.device_type == 'Tablet' ? '<i class="bi bi-tablet text-warning"></i>' : '<i class="bi bi-pc-display text-secondary"></i>')}
                        ${log.brand || 'Generic'}
                    </div>
                    <div class="text-muted small">${log.os || 'OS Unknown'}</div>
                </td>
                <td><span class="text-uppercase small fw-bold text-primary">${log.exam_type}</span></td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <span class="badge ${log.action == 'page_load' || log.action == 'click_exam_url' ? 'bg-success' : 'bg-primary'} outline">
                            ${(() => {
                                const labels = {
                                    'page_load': 'Buka Jadwal',
                                    'tab_click': 'Lihat Semester ' + (log.semester || ''),
                                    'theme_dark': 'Ganti Tema: Gelap',
                                    'theme_light': 'Ganti Tema: Terang',
                                    'view_all_days': 'Lihat Semua Hari',
                                    'view_today_only': 'Lihat Hari Ini Saja',
                                    'search': 'Melakukan Pencarian',
                                    'click_locked': 'Akses Ujian (Terkunci)',
                                    'click_ended': 'Akses Ujian (Selesai)',
                                    'click_exam_url': 'Klik Masuk Ujian',
                                    'unauthorized_panel_access': 'Intai Halaman Login',
                                    'login_failed_attempt': 'Gagal Login (Percobaan)',
                                    'login_success': 'Login Admin Sukses',
                                    'security_alert': 'Akses Terlarang (Honeypot)'
                                };
                                return labels[log.action] || log.action;
                            })()}
                        </span>
                        ${log.is_bot == 1 ? '<span class="badge bg-danger"><i class="bi bi-robot"></i> BOT/CRAWLER</span>' : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function clearLogs() {
    if (!confirm('Apakah Anda yakin ingin menghapus semua data log kunjungan?')) return;
    try {
        const res = await fetch('./?api=clear_logs', { method: 'POST' });
        const data = await res.json();
        if (data.status === 'success') {
            window.location.reload();
        }
    } catch(err) {
        alert('Gagal membersihkan log.');
    }
}
</script>

</body>
</html>
