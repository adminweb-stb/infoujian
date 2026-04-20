<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ./");
    exit;
}
date_default_timezone_set('Asia/Jakarta');

require_once '../../core/db.php';

// Migration: Add new columns if they don't exist (Forensics 2.0)
$check_context = $conn->query("SHOW COLUMNS FROM visitor_logs LIKE 'context'");
if ($check_context && $check_context->num_rows == 0) {
    $conn->query("ALTER TABLE visitor_logs ADD COLUMN context TEXT AFTER is_bot");
    $conn->query("ALTER TABLE visitor_logs ADD COLUMN referrer TEXT AFTER context");
    $conn->query("ALTER TABLE visitor_logs ADD COLUMN resolution VARCHAR(50) AFTER referrer");
}

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
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = 200;
    
    $api_logs = [];
    $stmt = $conn->prepare("SELECT * FROM visitor_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) {
        $api_logs[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'data' => $api_logs, 'has_more' => count($api_logs) === $limit]);
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
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(255,255,255,0.3); }
        .live-pulse { width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; margin-right: 8px; }
        @keyframes pulse { 0% { transform: scale(0.9); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); } 70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } 100% { transform: scale(0.9); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        .stat-card { transition: transform 0.2s; border-left: 4px solid #4f46e5; }
        .stat-card:hover { transform: translateY(-3px); }
        .filter-btn { cursor: pointer; transition: all 0.3s; background: #fff; padding: 0.5rem 1.25rem; border-radius: 999px; border: 1px solid #e2e8f0; font-weight: 600; font-size: 0.8rem; color: #64748b; }
        .filter-btn:hover { background: #f8fafc; border-color: #cbd5e1; color: #4f46e5; }
        .filter-btn.active { background: #4f46e5; color: #fff; border-color: #4f46e5; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .filter-btn.active.btn-danger-active { background: #ef4444; border-color: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
        .table-responsive { border-radius: 12px; }
        .table-hover tbody tr:hover { background-color: rgba(79, 70, 229, 0.02) !important; }
        .ip-link { font-family: 'JetBrains Mono', monospace; text-decoration: none; font-weight: 600; }
        .ip-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container-fluid py-5 px-4">
    <div class="row justify-content-center">
        <div class="col-12">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="./" class="text-decoration-none small fw-bold text-muted mb-2 d-block">← Kembali ke Panel Utama</a>
                    <h2 class="fw-bold m-0">Log Kunjungan Detail</h2>
                    <p class="text-muted small m-0">Menampilkan 200 interaksi terbaru dari mahasiswa.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-success btn-sm fw-bold shadow-sm" onclick="exportToCSV()">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="clearLogs()">Hapus Log</button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="glass-card p-3 stat-card" style="border-left-color: #4f46e5;">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Total Hits (200)</div>
                        <div class="h4 fw-bold mb-0" id="statHits"><?php echo count($logs); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-3 stat-card" style="border-left-color: #ef4444;">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Ancaman Keamanan</div>
                        <div class="h4 fw-bold text-danger mb-0" id="statSecurity">
                            <?php 
                                echo count(array_filter($logs, function($l) { 
                                    return in_array($l['action'], ['security_alert', 'unauthorized_panel_access', 'login_failed_attempt']); 
                                })); 
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-3 stat-card" style="border-left-color: #10b981;">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Akses Ujian Sukses</div>
                        <div class="h4 fw-bold text-success mb-0" id="statSuccess">
                            <?php echo count(array_filter($logs, function($l) { return $l['action'] === 'click_exam_url'; })); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-3 stat-card" style="border-left-color: #f59e0b;">
                        <div class="text-muted small fw-bold text-uppercase mb-1">Deteksi Bot</div>
                        <div class="h4 fw-bold text-warning mb-0" id="statBot"><?php echo count(array_filter($logs, function($l) { return $l['is_bot']; })); ?></div>
                    </div>
                </div>
            </div>

            <!-- New Control Bar -->
            <div class="glass-card p-3 mb-4 shadow-sm">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex gap-2" id="filterTabs">
                        <div class="filter-btn active" data-filter="all">Semua</div>
                        <div class="filter-btn" data-filter="security" data-active-class="btn-danger-active">⚠️ Keamanan</div>
                        <div class="filter-btn" data-filter="student">🎓 Mahasiswa</div>
                        <div class="filter-btn" data-filter="bot">🤖 Bot</div>
                    </div>
                    <div class="search-box flex-grow-1" style="max-width: 400px; position: relative;">
                        <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b;"></i>
                        <input type="text" id="logSearch" class="form-control" placeholder="Cari IP, Aktivitas, atau Mahasiswa..." style="padding-left: 35px; border-radius: 999px;">
                    </div>
                    <div class="form-check form-switch bg-light px-3 py-2 rounded-pill border m-0 d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" id="autoUpdateToggle" style="cursor: pointer;">
                        <label class="form-check-input-label small fw-bold ms-2 mb-0" for="autoUpdateToggle" style="cursor: pointer;">
                            <span id="liveStatus"><i class="bi bi-broadcast"></i> Live Update</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="glass-card p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>IP & Lokasi / ISP</th>
                                <th>Layar & Perangkat</th>
                                <th>Aktivitas Mahasiswa / Bot</th>
                                <th>Target / Info Tambahan</th>
                                <th>Asal (Referrer)</th>
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
                                        <td class="text-muted" style="font-size: 0.72rem; white-space: nowrap;"><?php echo date('d/m H:i:s', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <a href="https://who.is/whois-ip/ip-address/<?php echo $log['ip_address']; ?>" target="_blank" class="ip-link <?php echo $is_suspicious ? 'text-danger' : 'text-indigo'; ?>">
                                                    <?php echo $log['ip_address']; ?>
                                                </a>
                                                <?php if($log['country'] && $log['country'] !== 'Local/Private'): ?>
                                                    <span class="small text-muted" title="<?php echo $log['country'] . ', ' . $log['city']; ?>">
                                                        📍 <?php echo $log['city'] ?: $log['country']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted micro-text"><?php echo $log['isp'] ?: 'ISP Internal/Localhost'; ?></div>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="bi bi-display-fill text-muted me-1"></i> <?php echo $log['resolution'] ?? 'N/A'; ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php if (($log['device_type'] ?? '') == 'Mobile'): ?>
                                                    <i class="bi bi-smartphone text-info"></i>
                                                <?php elseif (($log['device_type'] ?? '') == 'Tablet'): ?>
                                                    <i class="bi bi-tablet text-warning"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-pc-display text-secondary"></i>
                                                <?php endif; ?>
                                                <?php echo $log['brand'] ?? 'Generic'; ?> (<?php echo $log['os'] ?? 'OS'; ?>)
                                            </div>
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
                                                        'search' => 'Pencarian',
                                                        'click_locked' => 'Akses Ujian (Terkunci)',
                                                        'click_ended' => 'Akses Ujian (Selesai)',
                                                        'click_exam_url' => 'Klik Masuk Ujian',
                                                        'unauthorized_panel_access' => 'Intai Halaman Login',
                                                        'login_failed_attempt' => 'Gagal Login (Percobaan)',
                                                        'login_success' => 'Login Admin Sukses',
                                                        'security_alert' => 'Akses Terlarang (Honeypot)'
                                                    ];
                                                    $action_text = $labels[$log['action']] ?? $log['action'];
                                                    
                                                    // Dynamic Badge Color
                                                    $badge_class = 'bg-primary';
                                                    if (in_array($log['action'], ['security_alert', 'unauthorized_panel_access', 'login_failed_attempt'])) $badge_class = 'bg-danger';
                                                    else if (in_array($log['action'], ['click_exam_url', 'login_success'])) $badge_class = 'bg-success';
                                                    else if (in_array($log['action'], ['click_locked', 'click_ended'])) $badge_class = 'bg-secondary';
                                                    else if (in_array($log['action'], ['theme_dark', 'theme_light', 'view_all_days', 'view_today_only', 'search'])) $badge_class = 'bg-info';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?> outline">
                                                    <?php echo $action_text; ?>
                                                </span>
                                                <span class="text-uppercase micro-text fw-bold text-indigo"><?php echo $log['exam_type']; ?></span>
                                                <?php if($log['is_bot']): ?>
                                                    <span class="badge bg-danger"><i class="bi bi-robot"></i> BOT</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($log['context'] ?? ''): ?>
                                                <div class="p-2 rounded bg-light border small text-dark">
                                                    <?php echo htmlspecialchars($log['context']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small italic">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-truncate" style="max-width: 250px;">
                                            <?php if($log['referrer'] ?? ''): ?>
                                                <a href="<?php echo $log['referrer']; ?>" target="_blank" class="small text-decoration-none text-muted" title="<?php echo $log['referrer']; ?>">
                                                    <i class="bi bi-link-45deg"></i> <?php echo parse_url($log['referrer'], PHP_URL_HOST) ?: $log['referrer']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Muat Lebih Banyak Button -->
            <div class="text-center mt-4 mb-5" id="loadMoreContainer">
                <button class="btn glass-card px-5 py-2 fw-bold text-indigo" id="loadMoreBtn" onclick="loadMoreLogs()">
                    <i class="bi bi-arrow-down-circle me-1"></i> Muat Lebih Banyak
                </button>
            </div>

        </div>
    </div>
</div>

<script>
let updateInterval;
let allLogs = <?php echo json_encode($logs); ?>;
let currentFilter = 'all';
let searchKeyword = '';

// Filter Handling
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active', 'btn-danger-active'));
        this.classList.add('active');
        if (this.dataset.activeClass) this.classList.add(this.dataset.activeClass);
        
        currentFilter = this.dataset.filter;
        renderLogs(allLogs);
    });
});

// Search Handling
document.getElementById('logSearch').addEventListener('input', function(e) {
    searchKeyword = e.target.value.toLowerCase();
    renderLogs(allLogs);
});

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

let currentOffset = 0;
const LIMIT = 200;

async function fetchLogs() {
    try {
        // Only fetch top 200 for live update (refresh current view if at page 0)
        const res = await fetch(`./logs.php?api=get_logs_json&offset=0`);
        const result = await res.json();
        if (result.status === 'success') {
            // Update allLogs but keep what we've loaded previously if we are scrolled down
            // For simplicity, live update only refreshes the first page
            if (currentOffset === 0) {
                allLogs = result.data;
                renderLogs(allLogs);
            }
        }
    } catch (err) {}
}

async function loadMoreLogs() {
    const btn = document.getElementById('loadMoreBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memuat...';

    try {
        currentOffset += LIMIT;
        const res = await fetch(`./logs.php?api=get_logs_json&offset=${currentOffset}`);
        const result = await res.json();
        
        if (result.status === 'success') {
            if (result.data.length > 0) {
                allLogs = [...allLogs, ...result.data];
                renderLogs(allLogs);
                
                if (!result.has_more) {
                    document.getElementById('loadMoreContainer').innerHTML = '<p class="text-muted small">Semua log telah dimuat.</p>';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } else {
                document.getElementById('loadMoreContainer').innerHTML = '<p class="text-muted small">Semua log telah dimuat.</p>';
            }
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Gagal memuat data lebih banyak.');
    }
}

function renderLogs(data) {
    const tbody = document.getElementById('logTableBody');
    
    // Apply Filters
    let filtered = data.filter(log => {
        // 1. Categorical Filter
        if (currentFilter === 'security') {
            const is_sec = ['security_alert', 'unauthorized_panel_access', 'login_failed_attempt'].includes(log.action) || log.is_bot == 1;
            if (!is_sec) return false;
        } else if (currentFilter === 'bot') {
            if (log.is_bot != 1) return false;
        } else if (currentFilter === 'student') {
            if (log.is_bot == 1) return false;
        }

        // 2. Search Keyword
        if (searchKeyword) {
            const text = (log.ip_address + log.action + log.context + log.brand + log.os).toLowerCase();
            if (!text.includes(searchKeyword)) return false;
        }
        return true;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center p-5 text-muted">Tidak ada data yang cocok dengan filter ini.</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(log => {
        const is_suspicious = (log.country !== 'Indonesia' && log.country !== 'Local/Private') || log.is_bot == 1;
        const timeStr = new Date(log.created_at).toLocaleDateString('id-ID', {day: '2-digit', month: '2-digit'}) + ' ' + new Date(log.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        
        return `
            <tr class="${is_suspicious ? 'table-danger' : ''}">
                <td class="text-muted" style="font-size: 0.72rem; white-space: nowrap;">${timeStr}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <a href="https://who.is/whois-ip/ip-address/${log.ip_address}" target="_blank" class="ip-link ${is_suspicious ? 'text-danger' : 'text-indigo'}">${log.ip_address}</a>
                        ${log.country && log.country !== 'Local/Private' ? `<span class="small text-muted" title="${log.country}, ${log.city}">📍 ${log.city || log.country}</span>` : ''}
                    </div>
                    <div class="text-muted micro-text">${log.isp || 'ISP Internal/Localhost'}</div>
                </td>
                <td>
                    <div><i class="bi bi-display-fill text-muted me-1"></i> ${log.resolution || 'N/A'}</div>
                    <div class="text-muted small">
                        ${log.device_type == 'Mobile' ? '<i class="bi bi-smartphone text-info"></i>' : (log.device_type == 'Tablet' ? '<i class="bi bi-tablet text-warning"></i>' : '<i class="bi bi-pc-display text-secondary"></i>')}
                        ${log.brand || 'Generic'} (${log.os || 'OS'})
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        ${(() => {
                            const labels = {
                                'page_load': 'Buka Jadwal',
                                'tab_click': 'Lihat Semester ' + (log.semester || ''),
                                'theme_dark': 'Ganti Tema: Gelap',
                                'theme_light': 'Ganti Tema: Terang',
                                'view_all_days': 'Lihat Semua Hari',
                                'view_today_only': 'Lihat Hari Ini Saja',
                                'search': 'Pencarian',
                                'click_locked': 'Akses Ujian (Terkunci)',
                                'click_ended': 'Akses Ujian (Selesai)',
                                'click_exam_url': 'Klik Masuk Ujian',
                                'unauthorized_panel_access': 'Intai Halaman Login',
                                'login_failed_attempt': 'Gagal Login (Percobaan)',
                                'login_success': 'Login Admin Sukses',
                                'security_alert': 'Akses Terlarang (Honeypot)'
                            };
                            
                            let badgeClass = 'bg-primary';
                            if (['security_alert', 'unauthorized_panel_access', 'login_failed_attempt'].includes(log.action)) badgeClass = 'bg-danger';
                            else if (['click_exam_url', 'login_success'].includes(log.action)) badgeClass = 'bg-success';
                            else if (['click_locked', 'click_ended'].includes(log.action)) badgeClass = 'bg-secondary';
                            else if (['theme_dark', 'theme_light', 'view_all_days', 'view_today_only', 'search'].includes(log.action)) badgeClass = 'bg-info';

                            return `<span class="badge ${badgeClass} outline">${labels[log.action] || log.action}</span>`;
                        })()}
                        <span class="text-uppercase micro-text fw-bold text-indigo">${log.exam_type}</span>
                        ${log.is_bot == 1 ? '<span class="badge bg-danger"><i class="bi bi-robot"></i> BOT</span>' : ''}
                    </div>
                </td>
                <td>
                    ${log.context ? `<div class="p-2 rounded bg-light border small text-dark">${log.context}</div>` : '<span class="text-muted small italic">-</span>'}
                </td>
                <td class="text-truncate" style="max-width: 250px;">
                    ${log.referrer ? `<a href="${log.referrer}" target="_blank" class="small text-decoration-none text-muted"><i class="bi bi-link-45deg"></i> ${new URL(log.referrer).hostname || log.referrer}</a>` : '<span class="text-muted small">-</span>'}
                </td>
            </tr>
        `;
    }).join('');

    // Update Stats
    document.getElementById('statHits').innerText = data.length;
    document.getElementById('statSecurity').innerText = data.filter(l => ['security_alert', 'unauthorized_panel_access', 'login_failed_attempt'].includes(l.action)).length;
    document.getElementById('statSuccess').innerText = data.filter(l => l.action === 'click_exam_url').length;
    document.getElementById('statBot').innerText = data.filter(l => l.is_bot == 1).length;
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

function exportToCSV() {
    if (allLogs.length === 0) return alert('Tidak ada data untuk diekspor');
    
    let csv = 'Waktu,IP Address,Lokasi,ISP,Perangkat,OS,Resolusi,Aksi,Target/Context,Referrer\n';
    allLogs.forEach(log => {
        const time = log.created_at;
        const ip = log.ip_address;
        const loc = (log.city || log.country || 'Unknown').replace(/,/g, '');
        const isp = (log.isp || '').replace(/,/g, '');
        const dev = log.brand || 'Generic';
        const os = log.os || 'OS';
        const res = log.resolution || 'N/A';
        const action = log.action;
        const ctx = (log.context || '-').replace(/,/g, '|').replace(/\n/g, ' ');
        const ref = (log.referrer || '-').replace(/,/g, '');
        
        csv += `${time},${ip},${loc},${isp},${dev},${os},${res},${action},${ctx},${ref}\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', `log_ujian_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}
</script>

</body>
</html>
