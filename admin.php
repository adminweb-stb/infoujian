<?php
session_start();

// --- CONFIGURATION ---
// Hash generated with: password_hash('YthLq7FLuVk3f7U8', PASSWORD_BCRYPT)
define('ADMIN_PASS_HASH', '$2y$10$0qNPDgWeiNDiS/QRk//0auFMUQha/K3YTw/oIJIPOGxbPQnpkyMIO');
$base_path = '/ujian';

// Brute Force Protection: max 5 attempts
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_time'])) $_SESSION['lockout_time'] = 0;

$is_locked = ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['lockout_time']) < 300);

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle Login
if (!$is_locked && isset($_POST['password'])) {
    if (password_verify($_POST['password'], ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_attempts'] = 0;
    } else {
        $_SESSION['login_attempts']++;
        $_SESSION['lockout_time'] = time();
        $error = "Password salah! Sisa percobaan: " . max(0, 5 - $_SESSION['login_attempts']);
    }
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- INTERNAL API FOR LIVE EDITOR ---
if ($is_logged_in && isset($_GET['api'])) {
    require_once 'db.php';
    header('Content-Type: application/json');
    $api = $_GET['api'];

    try {
        if ($api === 'get' && isset($_GET['sem'])) {
            $sem = (int)$_GET['sem'];
            $table = "semester_$sem";
            $res = $conn->query("SELECT * FROM $table ORDER BY id ASC");
            $data = [];
            if ($res) {
                while($row = $res->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            exit;
        }

        if ($api === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)$_POST['id'];
            $sem = (int)$_POST['sem'];
            $table = "semester_$sem";
            
            $stmt = $conn->prepare("UPDATE $table SET hari=?, tanggal=?, sesi=?, matkul=?, jam=?, kelas=?, dosen=?, link_server=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $_POST['hari'], $_POST['tanggal'], $_POST['sesi'], $_POST['matkul'], $_POST['jam'], $_POST['kelas'], $_POST['dosen'], $_POST['link_server'], $id);
            $stmt->execute();
            
            // Trigger Sync
            define('INTERNAL_SYNC', true);
            $semester = $sem; // Variable expected by sync_data logic
            ob_start(); include 'sync_data.php'; ob_end_clean();
            
            echo json_encode(['status' => 'success', 'msg' => 'Data berhasil diupdate']);
            exit;
        }

        if ($api === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)$_POST['id'];
            $sem = (int)$_POST['sem'];
            $table = "semester_$sem";
            
            $stmt = $conn->prepare("DELETE FROM $table WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            // Trigger Sync
            define('INTERNAL_SYNC', true);
            $semester = $sem;
            ob_start(); include 'sync_data.php'; ob_end_clean();
            
            echo json_encode(['status' => 'success', 'msg' => 'Data berhasil dihapus']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        exit;
    }
}

// If logged in, get statistics
$stats = [];
if ($is_logged_in) {
    require_once 'db.php';
    foreach ([2, 4, 6] as $sem) {
        $table = "semester_$sem";
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $res = $conn->query("SELECT COUNT(*) as total FROM $table");
            $stats[$sem] = $res->fetch_assoc()['total'];
        } else {
            $stats[$sem] = "Tabel belum dibuat";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Jadwal Ujian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; color: #333; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.2); }
        .btn-primary { background: #4f46e5; border: none; padding: 10px 20px; border-radius: 8px; }
        .btn-primary:hover { background: #4338ca; }
        .stats-num { font-size: 2rem; font-weight: 800; color: #4f46e5; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <?php if (!$is_logged_in): ?>
                <!-- LOGIN FORM -->
                <div class="glass-card p-5 mt-5">
                    <div class="text-center mb-4">
                        <img src="images/logo.png" alt="logo" width="80" class="mb-3">
                        <h2 class="fw-bold">Admin Portal</h2>
                        <p class="text-muted">Masukkan password untuk mengelola jadwal.</p>
                    </div>
                    
            <?php if ($is_locked): ?>
                <div class="alert alert-danger">Terlalu banyak percobaan login. Coba lagi dalam 5 menit.</div>
            <?php elseif (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Login</button>
                    </form>
                </div>

            <?php else: ?>
                <!-- DASHBOARD -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold">Dashboard Admin</h2>
                    <a href="?logout=1" class="btn btn-outline-danger btn-sm">Logout</a>
                </div>

                <!-- DOWNLOAD TEMPLATE -->
                <div class="alert alert-info glass-card mb-4 border-0">
                    <h5 class="fw-bold mb-2">Instruksi Update:</h5>
                    <ol class="small mb-3">
                        <li>Siapkan Data di Excel sesuai kolom template.</li>
                        <li>Simpan/Save As sebagai file <strong>CSV (.csv)</strong>.</li>
                        <li>Klik Upload di bawah sesuai Semester.</li>
                    </ol>
                    <a href="data/template_jadwal.csv" class="btn btn-sm btn-info text-white fw-bold">Download Template CSV</a>
                </div>

                <!-- SEMESTER CARDS -->
                <div class="row g-3">
                    <?php foreach ([2, 4, 6] as $sem): ?>
                        <div class="col-12">
                            <div class="glass-card p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0 fw-bold">Semester <?php echo $sem; ?></h5>
                                        <p class="text-muted small mb-0">Total Data: <?php echo $stats[$sem]; ?></p>
                                    </div>
                                    <div>
                                        <button class="btn btn-warning btn-sm me-2 fw-bold text-dark" onclick="openLiveEditor(<?php echo $sem; ?>)">Lihat & Edit Data</button>
                                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#upload-<?php echo $sem; ?>">Upload Baru</button>
                                    </div>
                                </div>
                                
                                <div class="collapse mt-3" id="upload-<?php echo $sem; ?>">
                                    <form action="import_csv.php" method="POST" enctype="multipart/form-data" class="bg-light p-3 rounded">
                                        <input type="hidden" name="semester" value="<?php echo $sem; ?>">
                                        <div class="input-group">
                                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                            <button type="submit" class="btn btn-primary">Upload</button>
                                        </div>
                                        <div class="form-text mt-1 text-danger">⚠️ Perhatian: Mengunggah file baru akan menghapus data lama di semester ini.</div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-5">
                    <a href="index.html" target="_blank" class="text-decoration-none">← Lihat Halaman Publik</a>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- LIVE EDITOR MODAL -->
<div class="modal fade" id="liveEditorModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Live Editor - Semester <span id="le-sem-badge"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered mb-0 align-middle" id="le-table" style="font-size:0.85rem">
                        <thead class="table-light">
                            <tr>
                                <th>Hari, Tgl</th>
                                <th>Sesi/Jam</th>
                                <th>Matkul</th>
                                <th>Kls</th>
                                <th>Dosen</th>
                                <th>Link Server</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="le-tbody">
                            <tr><td colspan="7" class="text-center p-4">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT FORM MODAL -->
<div class="modal fade" id="editFormModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold text-dark">Edit Jadwal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit-id" name="id">
                    <input type="hidden" id="edit-sem" name="sem">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small mb-1 fw-bold">Hari</label><input type="text" class="form-control form-control-sm" id="edit-hari" name="hari" required></div>
                        <div class="col-6"><label class="form-label small mb-1 fw-bold">Tanggal</label><input type="text" class="form-control form-control-sm" id="edit-tanggal" name="tanggal" required></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label small mb-1 fw-bold">Sesi</label><input type="text" class="form-control form-control-sm" id="edit-sesi" name="sesi"></div>
                        <div class="col-8"><label class="form-label small mb-1 fw-bold">Jam</label><input type="text" class="form-control form-control-sm" id="edit-jam" name="jam" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label small mb-1 fw-bold">Mata Kuliah</label><input type="text" class="form-control form-control-sm" id="edit-matkul" name="matkul" required></div>
                    <div class="row g-2 mb-2">
                        <div class="col-4"><label class="form-label small mb-1 fw-bold">Kelas</label><input type="text" class="form-control form-control-sm" id="edit-kelas" name="kelas"></div>
                        <div class="col-8"><label class="form-label small mb-1 fw-bold">Dosen</label><input type="text" class="form-control form-control-sm" id="edit-dosen" name="dosen"></div>
                    </div>
                    <div class="mb-4"><label class="form-label small mb-1 fw-bold">Link Server</label><input type="url" class="form-control form-control-sm" id="edit-link" name="link_server"></div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-danger px-3 fw-bold" onclick="deleteJadwal()">Hapus</button>
                        <div>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-sm btn-success px-4 fw-bold">Simpan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentSem = null;
let liveEditorModal, editFormModal;

document.addEventListener('DOMContentLoaded', () => {
    const leModalEl = document.getElementById('liveEditorModal');
    if (leModalEl) liveEditorModal = new bootstrap.Modal(leModalEl);
    
    const efModalEl = document.getElementById('editFormModal');
    if (efModalEl) editFormModal = new bootstrap.Modal(efModalEl);
    
    const form = document.getElementById('editForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            try {
                const btn = e.target.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = 'Menyimpan...'; btn.disabled = true;
                
                const res = await fetch('admin.php?api=update', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.status === 'success') {
                    editFormModal.hide();
                    loadDataToTable(currentSem); // Refresh table
                    alert('Data sukses disimpan dan website JSON telah ter-update otomatis!');
                } else {
                    alert('Error: ' + data.msg);
                }
                btn.innerHTML = originalText; btn.disabled = false;
            } catch (err) {
                alert('Gagal terhubung ke server');
            }
        });
    }
});

async function openLiveEditor(sem) {
    currentSem = sem;
    document.getElementById('le-sem-badge').innerText = sem;
    liveEditorModal.show();
    await loadDataToTable(sem);
}

async function loadDataToTable(sem) {
    const tbody = document.getElementById('le-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4">Memuat data dari database...</td></tr>';
    
    try {
        const res = await fetch(`admin.php?api=get&sem=${sem}`);
        const result = await res.json();
        
        if (result.status === 'success') {
            if (result.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-muted">Belum ada data jadwal. Silakan upload CSV terlebih dahulu.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            
            result.data.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="fw-bold">${item.hari || '-'}</div>
                        <div class="small text-muted">${item.tanggal || '-'}</div>
                    </td>
                    <td>
                        <div><span class="badge bg-secondary">Sesi ${item.sesi || '-'}</span></div>
                        <div class="small mt-1 text-primary fw-bold">${item.jam || '-'}</div>
                    </td>
                    <td class="fw-bold">${item.matkul || '-'}</td>
                    <td>${item.kelas || '-'}</td>
                    <td>${item.dosen || '-'}</td>
                    <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <a href="${item.link_server}" target="_blank">${item.link_server}</a>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-warning fw-bold text-dark w-100" onclick="openEdit(${item.id}, ${sem})">Edit</button>
                    </td>
                `;
                // Store actual data silently to avoid refetching on edit click
                tr.dataset.json = JSON.stringify(item);
                tbody.appendChild(tr);
            });
        }
    } catch(err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-danger">Gagal memuat data.</td></tr>';
    }
}

function openEdit(id, sem) {
    const tbody = document.getElementById('le-tbody');
    const rows = tbody.querySelectorAll('tr');
    let itemData = null;
    rows.forEach(r => {
        if (r.dataset.json) {
            const data = JSON.parse(r.dataset.json);
            if (data.id == id) itemData = data;
        }
    });
    
    if (itemData) {
        document.getElementById('edit-id').value = itemData.id;
        document.getElementById('edit-sem').value = sem;
        document.getElementById('edit-hari').value = itemData.hari;
        document.getElementById('edit-tanggal').value = itemData.tanggal;
        document.getElementById('edit-sesi').value = itemData.sesi;
        document.getElementById('edit-jam').value = itemData.jam;
        document.getElementById('edit-matkul').value = itemData.matkul;
        document.getElementById('edit-kelas').value = itemData.kelas;
        document.getElementById('edit-dosen').value = itemData.dosen;
        document.getElementById('edit-link').value = itemData.link_server;
        
        editFormModal.show();
    }
}

async function deleteJadwal() {
    if(!confirm("HAPUS DATA: Anda yakin ingin membuang jadwal ini secara permanen?")) return;
    
    const id = document.getElementById('edit-id').value;
    const sem = document.getElementById('edit-sem').value;
    
    const fd = new FormData();
    fd.append('id', id);
    fd.append('sem', sem);
    
    try {
        const res = await fetch('admin.php?api=delete', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            editFormModal.hide();
            loadDataToTable(currentSem);
            alert('Jadwal berhasil dihapus dan JSON web telah tersinkron!');
        } else {
            alert('Error: ' + data.msg);
        }
    } catch(e) {
        alert('Gagal terhubung ke server');
    }
}
</script>
</body>
</html>
