<?php
session_start();
define('INTERNAL_LOG', true);
require_once 'logger.php';
function log_admin_security($action) { run_visitor_logger($action, 'admin_panel'); }
define('ADMIN_PASS_HASH', '$2y$10$0qNPDgWeiNDiS/QRk//0auFMUQha/K3YTw/oIJIPOGxbPQnpkyMIO');

// Brute Force Protection
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['lockout_time'])) $_SESSION['lockout_time'] = 0;
$is_locked = ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['lockout_time']) < 300);

// Handle Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: ./"); exit(); }

// Handle Login
if (!$is_locked && isset($_POST['password'])) {
    if (password_verify($_POST['password'], ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_attempts'] = 0;
        log_admin_security('login_success');
    } else {
        $_SESSION['login_attempts']++;
        $_SESSION['lockout_time'] = time();
        $error = "Password salah! Percobaan: " . $_SESSION['login_attempts'];
        log_admin_security('login_failed_attempt');
    }
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- CONFIG & STATS LOGIC ---
$config_path = __DIR__ . '/../data/config.json';
$config = file_exists($config_path) ? json_decode(file_get_contents($config_path), true) : ['active_exam' => 'uts', 'active_period' => 'ganjil'];
$active_exam = $config['active_exam'] ?? 'uts';
$active_period = $config['active_period'] ?? 'ganjil';
$active_exam_label = $config['active_exam_label'] ?? 'Ujian Tengah Semester (UTS)';
$sem_array = ($active_period === 'ganjil') ? [1, 3, 5, 7] : [2, 4, 6, 8];

$stats = [];
$logs = ['today' => 0, 'total' => 0, 'devices' => ['Mobile' => 0, 'Desktop' => 0, 'Tablet' => 0]];
if ($is_logged_in) {
    require_once '../../core/db.php';
    foreach ($sem_array as $sem) {
        $table = "{$active_exam}_semester_$sem";
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        $stats[$sem] = ($check && $check->num_rows > 0) ? ($conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total']) : 0;
    }
    $res = $conn->query("SELECT COUNT(*) as total FROM visitor_logs WHERE DATE(created_at) = CURDATE()");
    $logs['today'] = $res ? $res->fetch_assoc()['total'] : 0;
    $res = $conn->query("SELECT COUNT(*) as total FROM visitor_logs");
    $logs['total'] = $res ? $res->fetch_assoc()['total'] : 0;
}

// API for Config Update only
if ($is_logged_in && isset($_GET['api'])) {
    header('Content-Type: application/json');
    if ($_GET['api'] === 'save_config') {
        $new_config = ['active_exam' => $_POST['active_exam'], 'active_period' => $_POST['active_period'], 'active_exam_label' => $_POST['active_exam_label']];
        file_put_contents($config_path, json_encode($new_config));
        echo json_encode(['status' => 'success']);
        exit;
    }
    if (in_array($_GET['api'], ['create', 'update', 'delete'])) {
        $sem = (int)$_POST['sem'];
        $table = "{$active_exam}_semester_$sem";
        if ($_GET['api'] === 'create') {
            $stmt = $conn->prepare("INSERT INTO $table (hari, tanggal, sesi, jam, matkul, kelas, dosen, link_server) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $_POST['hari'], $_POST['tanggal'], $_POST['sesi'], $_POST['jam'], $_POST['matkul'], $_POST['kelas'], $_POST['dosen'], $_POST['link_server']);
            $stmt->execute();
        } elseif ($_GET['api'] === 'update') {
            $stmt = $conn->prepare("UPDATE $table SET hari=?, tanggal=?, sesi=?, jam=?, matkul=?, kelas=?, dosen=?, link_server=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $_POST['hari'], $_POST['tanggal'], $_POST['sesi'], $_POST['jam'], $_POST['matkul'], $_POST['kelas'], $_POST['dosen'], $_POST['link_server'], $_POST['id']);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("DELETE FROM $table WHERE id=?");
            $stmt->bind_param("i", $_POST['id']);
            $stmt->execute();
        }

        // AUTO-SYNC TO STATIC JSON (For Student Portal)
        $json_file = __DIR__ . "/../data/{$active_exam}_semester-{$sem}.json";
        $data_json = [];
        $res_json = $conn->query("SELECT * FROM $table ORDER BY sesi ASC, jam ASC, kelas ASC");
        if ($res_json) { 
            while($row = $res_json->fetch_assoc()) { 
                $data_json[] = $row; 
            } 
        }
        file_put_contents($json_file, json_encode($data_json, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'success']);
        exit;
    }
}

// AJAX Content Request
if (isset($_GET['ajax'])) {
    // Only output the main-content part for SPA
    echo '<!-- TITLE:' . (isset($page_title) ? $page_title : 'Admin Panel') . ' -->';
    // This is a trick to return only the inner part. For simplicity, we'll extract it in JS instead.
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        .sidebar-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .tooltip-text { 
            visibility: hidden; opacity: 0; position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 1rem; padding: 5px 12px; background: #1e293b; color: white; font-size: 10px; 
            border-radius: 6px; white-space: nowrap; transition: all 0.2s; z-index: 1000; font-weight: bold;
            pointer-events: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
        }
        .sidebar-collapsed .tooltip:hover .tooltip-text { visibility: visible; opacity: 1; margin-left: 0.8rem; }
        #main-content { transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-link.active { background-color: rgba(255, 255, 255, 0.05); color: white; font-weight: 500; }
        .nav-link.active i { color: #818cf8; }
        
        /* SPA Loader */
        #nprogress { pointer-events: none; }
        #nprogress .bar { background: #6366f1; position: fixed; z-index: 2000; top: 0; left: 0; width: 100%; height: 2px; }
        .fade-content { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .sidebar-mobile-open { transform: translateX(0) !important; }
    </style>
</head>
<body class="text-slate-900 bg-slate-50 min-h-screen">

    <?php if (!$is_logged_in): ?>
    <div class="flex items-center justify-center min-h-screen p-4 bg-slate-950">
        <div class="w-full max-w-[380px] bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <img src="../assets/images/logo.png" class="w-14 mx-auto mb-4">
                <h1 class="text-xl font-bold tracking-tight uppercase">Admin Panel</h1>
                <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold tracking-[0.2em]">ST Bhinneka</p>
            </div>
            <?php if (isset($error)): ?>
                <div class="mb-6 p-3 bg-red-50 text-red-600 rounded-md border border-red-100 text-[10px] font-bold text-center"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <input type="password" name="password" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-slate-950 outline-none text-sm tracking-widest text-center" placeholder="••••••••" required autofocus>
                <button type="submit" class="w-full bg-slate-950 text-white font-bold py-3 rounded-lg hover:bg-slate-800 transition-all text-xs tracking-widest">LOGIN</button>
            </form>
        </div>
    </div>
    <?php else: ?>

    <!-- MOBILE OVERLAY -->
    <div id="mobile-overlay" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm z-[55] hidden opacity-0 transition-opacity duration-300 md:hidden"></div>

    <!-- FIXED SIDEBAR -->
    <aside id="sidebar" class="sidebar-transition fixed top-0 left-0 h-full w-56 bg-slate-950 flex flex-col z-[60] text-slate-400 border-r border-slate-800 -translate-x-full md:translate-x-0">
        <div class="p-4 h-14 border-b border-white/5 flex items-center justify-between whitespace-nowrap overflow-hidden">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" class="w-6 brightness-0 invert flex-shrink-0">
                <span class="font-bold text-white text-[11px] tracking-tight sidebar-expand-only">UJIAN ADMIN</span>
            </div>
            <button onclick="window.innerWidth < 768 ? toggleMobileSidebar() : toggleSidebar()" class="p-1 hover:bg-white/10 rounded transition-colors">
                <i id="toggle-icon" class="bi bi-chevron-left text-[10px]"></i>
            </button>
        </div>
        
        <div class="p-3 flex-1 space-y-1 overflow-visible" id="sidebar-nav">
            <div class="pb-2 px-3 text-[9px] font-bold text-slate-600 uppercase tracking-widest sidebar-expand-only">Navigation</div>
            
            <a href="index.php" class="nav-link active tooltip relative flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all">
                <i class="bi bi-grid text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Dashboard</span>
                <span class="tooltip-text">Dashboard</span>
            </a>

            <a href="manage_schedules.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all">
                <i class="bi bi-calendar3 text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Manage Schedules</span>
                <span class="tooltip-text">Manage Schedules</span>
            </a>
            
            <a href="logs.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all">
                <i class="bi bi-activity text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Forensic Logs</span>
                <span class="tooltip-text">Forensic Logs</span>
            </a>
            
            <div class="pt-6 pb-2 px-3 text-[9px] font-bold text-slate-600 uppercase tracking-widest sidebar-expand-only">System</div>
            
            <button onclick="alert('Soon')" class="tooltip relative w-full flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all text-left">
                <i class="bi bi-shield-check text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Security & Backup</span>
                <span class="tooltip-text">Security & Backup</span>
            </button>
        </div>

        <div class="p-3 border-t border-white/5">
            <a href="?logout=1" class="tooltip relative flex items-center gap-3 px-3 py-2 rounded-md text-slate-500 hover:text-red-400 text-xs transition-all font-medium">
                <i class="bi bi-power text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Sign Out</span>
                <span class="tooltip-text text-red-400">Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div id="main-content" class="md:ml-56 flex-1 flex flex-col min-w-0 min-h-screen transition-all">
        <div id="spa-content-target" class="flex-1 flex flex-col fade-content">
            <header class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-8 sticky top-0 z-40">
                <div class="flex items-center gap-3">
                    <button onclick="toggleMobileSidebar()" class="md:hidden text-slate-500 hover:text-slate-900 transition-colors mr-1"><i class="bi bi-list text-xl"></i></button>
                    <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">System Overview</h2>
                </div>
                <div class="flex items-center gap-2 px-3 py-1 bg-green-50 text-green-700 rounded-full border border-green-100">
                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-[9px] font-bold uppercase tracking-widest tracking-tight">System Online</span>
                </div>
            </header>

            <main class="p-8 max-w-6xl w-full mx-auto space-y-8">
                <!-- CARDS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Today Visitors</div>
                        <div class="text-xl font-bold tracking-tight"><?= number_format($logs['today']) ?></div>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Hits</div>
                        <div class="text-xl font-bold tracking-tight"><?= number_format($logs['total']) ?></div>
                    </div>
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Active Sessions</div>
                        <div class="text-xl font-bold tracking-tight"><?= $logs['today'] ?></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <div class="xl:col-span-2 space-y-8">
                        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                            <div class="px-6 py-3 border-b border-slate-100 bg-slate-50/50">
                                <h3 class="text-[9px] font-bold text-slate-900 uppercase tracking-widest">Global State</h3>
                            </div>
                            <div class="p-6">
                                <form id="configForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest px-1">Active Exam</label>
                                        <select name="active_exam" id="cfg-exam" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded text-xs font-bold outline-none focus:border-slate-950 transition-colors">
                                            <option value="uts" <?= $active_exam == 'uts' ? 'selected' : '' ?>>UTS (Tengah Semester)</option>
                                            <option value="uas" <?= $active_exam == 'uas' ? 'selected' : '' ?>>UAS (Akhir Semester)</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[9px] font-bold text-slate-400 uppercase tracking-widest px-1">Semester Period</label>
                                        <select name="active_period" id="cfg-period" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded text-xs font-bold outline-none focus:border-slate-950 transition-colors">
                                            <option value="ganjil" <?= $active_period == 'ganjil' ? 'selected' : '' ?>>Ganjil (Odd)</option>
                                            <option value="genap" <?= $active_period == 'genap' ? 'selected' : '' ?>>Genap (Even)</option>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2 flex justify-end">
                                        <input type="hidden" id="cfg-label" name="active_exam_label">
                                        <button type="submit" class="bg-slate-950 text-white font-bold px-6 py-2 rounded text-[10px] uppercase tracking-widest hover:bg-slate-800">Save Config</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach ($sem_array as $sem): ?>
                            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-500 transition-all group">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="text-xl font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">S<?= $sem ?></div>
                                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= $stats[$sem] ?> rows</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="manage_schedules.php?sem=<?= $sem ?>" class="spa-link flex-1 text-center bg-slate-950 text-white text-[9px] font-bold py-2 rounded hover:bg-slate-800 transition-all uppercase tracking-widest">Manage Data</a>
                                    <button onclick="document.getElementById('file-<?= $sem ?>').click()" class="px-3 border border-slate-200 rounded text-slate-400 hover:text-slate-950 hover:bg-slate-50 transition-all"><i class="bi bi-upload"></i></button>
                                    <form action="import.php" method="POST" enctype="multipart/form-data" class="hidden">
                                        <input type="hidden" name="semester" value="<?= $sem ?>"><input type="file" id="file-<?= $sem ?>" name="csv_file" onchange="this.form.submit()">
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="space-y-8">
                        <div class="bg-slate-900 p-6 rounded-xl text-white">
                            <h4 class="text-[9px] font-bold uppercase tracking-widest mb-3 flex items-center gap-2"><i class="bi bi-info-circle text-indigo-400"></i> Reminder</h4>
                            <p class="text-[9px] text-slate-400 leading-relaxed italic mb-5">Dashboard statistics are cached for 1 minute.</p>
                            <a href="../data/template_jadwal.csv" class="block text-center py-2 bg-white text-slate-950 rounded font-bold text-[9px] tracking-widest uppercase hover:bg-slate-100">Get Template</a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- SPA ROUTER SCRIPT (VANILLA JS) -->
    <div id="spa-loader" class="fixed top-0 left-0 w-0 h-[2px] bg-indigo-500 z-[2000] transition-all duration-300"></div>
    <script>
        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            const mc = document.getElementById('main-content');
            const ic = document.getElementById('toggle-icon');
            if (sb.classList.contains('w-56')) {
                sb.classList.replace('w-56', 'w-20'); sb.classList.add('sidebar-collapsed'); mc.classList.replace('md:ml-56', 'md:ml-20'); ic.classList.replace('bi-chevron-left', 'bi-chevron-right'); document.querySelectorAll('.sidebar-expand-only').forEach(el => el.classList.add('hidden'));
            } else {
                sb.classList.replace('w-20', 'w-56'); sb.classList.remove('sidebar-collapsed'); mc.classList.replace('md:ml-20', 'md:ml-56'); ic.classList.replace('bi-chevron-right', 'bi-chevron-left'); document.querySelectorAll('.sidebar-expand-only').forEach(el => el.classList.remove('hidden'));
            }
        }

        function toggleMobileSidebar() {
            const sb = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            if (sb.classList.contains('sidebar-mobile-open')) {
                sb.classList.remove('sidebar-mobile-open');
                overlay.classList.replace('opacity-100', 'opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            } else {
                sb.classList.add('sidebar-mobile-open');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.replace('opacity-0', 'opacity-100'), 10);
            }
        }

        // SPA NAVIGATION ENGINE
        document.addEventListener('click', e => {
            const link = e.target.closest('a');
            if (link && link.href && link.href.includes(window.location.origin) && !link.target && !link.href.includes('logout') && !link.href.includes('.csv')) {
                e.preventDefault();
                navigateTo(link.href);
            }
        });

        window.addEventListener('popstate', () => navigateTo(window.location.href, false));

        async function navigateTo(url, push = true) {
            const loader = document.getElementById('spa-loader');
            loader.style.width = '30%';
            
            try {
                const response = await fetch(url);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Swap Content
                const newContent = doc.getElementById('spa-content-target').innerHTML;
                const target = document.getElementById('spa-content-target');
                target.classList.remove('fade-content');
                void target.offsetWidth; // Trigger reflow
                target.innerHTML = newContent;
                target.classList.add('fade-content');
                
                // Update Title & Sidebar
                document.title = doc.title;
                if (push) window.history.pushState({}, '', url);
                
                // Update Active State in Sidebar
                const pageName = url.split('/').pop().split('?')[0] || 'index.php';
                document.querySelectorAll('.nav-link').forEach(l => {
                    l.classList.remove('active');
                    if (l.getAttribute('href').includes(pageName)) l.classList.add('active');
                });

                // Re-run scripts if any
                const scripts = target.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                loader.style.width = '100%';
                setTimeout(() => loader.style.width = '0', 300);
            } catch (err) {
                console.error('Navigation failed:', err);
                window.location.href = url; // Fallback to normal load
            }
        }

        // Initial setup for forms on the page
        document.body.addEventListener('submit', async (e) => {
            if (e.target.id === 'configForm') {
                e.preventDefault();
                const sel = document.getElementById('cfg-exam');
                document.getElementById('cfg-label').value = sel.options[sel.selectedIndex].text;
                const formData = new FormData(e.target);
                const res = await fetch('./index.php?api=save_config', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') navigateTo(window.location.href, false);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>