<?php
session_start();

// --- CONFIGURATION ---
$admin_pass = 'admin123'; // Change this to your desired password
$base_path = '/ujian';     // Adjust if your app is in a folder

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle Login
if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Password salah!";
    }
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

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
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
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
                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#upload-<?php echo $sem; ?>">Update Data</button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
