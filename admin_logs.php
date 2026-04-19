<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin.php");
    exit;
}

require_once 'db.php';

// Fetch Logs (Latest 200)
$logs = [];
$res = $conn->query("SELECT * FROM visitor_logs ORDER BY created_at DESC LIMIT 200");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Detail | Admin Panel</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; color: #333; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-11 col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="admin.php" class="text-decoration-none small fw-bold text-muted mb-2 d-block">← Kembali ke Panel Utama</a>
                    <h2 class="fw-bold m-0">Log Kunjungan Detail</h2>
                    <p class="text-muted small m-0">Menampilkan 200 interaksi terbaru dari mahasiswa.</p>
                </div>
                <button class="btn btn-outline-danger btn-sm" onclick="clearLogs()">Hapus Semua Log</button>
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
                                <th>Smt</th>
                                <th>Aksi / Bot</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                        <td class="text-center fw-bold"><?php echo $log['semester'] ?: '-'; ?></td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span class="badge <?php echo $log['action'] == 'page_load' ? 'bg-success' : 'bg-primary' ?> outline">
                                                    <?php echo $log['action'] == 'page_load' ? 'Buka Halaman' : 'Klik Tab' ?>
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
async function clearLogs() {
    if (!confirm('Apakah Anda yakin ingin menghapus semua data log kunjungan?')) return;
    try {
        const res = await fetch('admin.php?api=clear_logs', { method: 'POST' });
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
