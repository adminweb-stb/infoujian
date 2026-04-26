<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ./");
    exit;
}
date_default_timezone_set('Asia/Jakarta');
require_once '../../core/db.php';

// Migration & Fetch Logs
$res = $conn->query("SELECT * FROM visitor_logs ORDER BY created_at DESC LIMIT 200");
$logs = [];
if ($res) { while($row = $res->fetch_assoc()) { $logs[] = $row; } }

// API Endpoint for Live Update
if (isset($_GET['api']) && $_GET['api'] === 'get_logs_json') {
    header('Content-Type: application/json');
    $api_logs = [];
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $stmt = $conn->prepare("SELECT * FROM visitor_logs ORDER BY created_at DESC LIMIT 200 OFFSET ?");
    $stmt->bind_param("i", $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) { $api_logs[] = $row; }
    echo json_encode(['status' => 'success', 'data' => $api_logs, 'has_more' => count($api_logs) === 200]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }
        .sidebar-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .tooltip-text { 
            visibility: hidden; opacity: 0; position: absolute; left: 100%; top: 50%; transform: translateY(-50%);
            margin-left: 1rem; padding: 5px 12px; background: #020617; color: white; font-size: 10px; 
            border-radius: 6px; white-space: nowrap; transition: all 0.2s; z-index: 1000; font-weight: 500;
            pointer-events: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
        }
        .sidebar-collapsed .tooltip:hover .tooltip-text { visibility: visible; opacity: 1; margin-left: 0.8rem; }
        #main-content { transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-link.active { background-color: rgba(255, 255, 255, 0.05); color: white; font-weight: 500; }
        .nav-link.active i { color: #818cf8; }
        .filter-pill { 
            padding: 4px 12px; border-radius: 9999px; border: 1px solid #e2e8f0; background-color: white; 
            font-size: 9px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.2s; 
            text-transform: uppercase; letter-spacing: 0.1em;
        }
        .filter-pill.active { background-color: #0f172a; color: white; border-color: #0f172a; }
        .fade-content { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .sidebar-mobile-open { transform: translateX(0) !important; }
    </style>
</head>
<body class="text-slate-900 bg-white min-h-screen">

    <!-- FIXED SIDEBAR -->
    <aside id="sidebar" class="sidebar-transition fixed top-0 left-0 h-full w-56 bg-slate-950 flex flex-col z-50 text-slate-400 border-r border-slate-800">
        <div class="p-4 h-14 border-b border-white/5 flex items-center justify-between whitespace-nowrap overflow-hidden">
            <div class="flex items-center gap-2.5">
                <img src="../assets/images/logo.png" class="w-6 brightness-0 invert flex-shrink-0">
                <span class="font-semibold text-white text-[13px] tracking-tight sidebar-expand-only">Ujian Admin</span>
            </div>
            <button onclick="toggleSidebar()" class="p-1 hover:bg-white/10 rounded-md transition-colors">
                <i id="toggle-icon" class="bi bi-chevron-left text-[11px]"></i>
            </button>
        </div>
        
        <div class="p-3 flex-1 space-y-1 overflow-visible">
            <a href="index.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2 rounded-md hover:text-white transition-all text-xs">
                <i class="bi bi-grid-1x2 text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Dashboard</span>
                <span class="tooltip-text">Dashboard</span>
            </a>
            <a href="manage_schedules.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2 rounded-md hover:text-white transition-all text-xs">
                <i class="bi bi-calendar-event text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Schedules</span>
                <span class="tooltip-text">Schedules</span>
            </a>
            <a href="logs.php" class="nav-link active tooltip relative flex items-center gap-3 px-3 py-2 rounded-md hover:text-white transition-all text-xs">
                <i class="bi bi-shield-shaded text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Activity Log</span>
                <span class="tooltip-text">Activity Log</span>
            </a>
        </div>
        <div class="p-3 border-t border-white/5">
            <a href="?logout=1" class="tooltip relative flex items-center gap-3 px-3 py-2 rounded-md text-slate-500 hover:text-red-400 text-xs transition-all font-medium">
                <i class="bi bi-box-arrow-left text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Sign Out</span>
                <span class="tooltip-text">Sign Out</span>
            </a>
        </div>
    </aside>

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
            
            <a href="index.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all">
                <i class="bi bi-grid text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Dashboard</span>
                <span class="tooltip-text">Dashboard</span>
            </a>

            <a href="manage_schedules.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all">
                <i class="bi bi-calendar3 text-sm flex-shrink-0"></i>
                <span class="sidebar-expand-only">Manage Schedules</span>
                <span class="tooltip-text">Manage Schedules</span>
            </a>
            
            <a href="logs.php" class="nav-link active tooltip relative flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 hover:text-white text-xs transition-all">
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
                    <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Activity Forensics</h2>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="exportToCSV()" class="text-[11px] font-medium bg-white border border-slate-200 hover:bg-slate-50 px-4 py-1.5 rounded-md transition-colors shadow-sm">Export</button>
                    <button onclick="clearLogs()" class="text-[11px] font-medium bg-red-50 text-red-600 hover:bg-red-100 px-4 py-1.5 rounded-md transition-colors">Clear All</button>
                </div>
            </header>

            <main class="p-6 max-w-7xl w-full mx-auto space-y-6">
                <!-- STATS -->
                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                        <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Total Logs</div>
                        <div class="text-xl font-bold tracking-tight" id="statHits"><?= count($logs) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                        <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Security Alert</div>
                        <div class="text-xl font-bold tracking-tight text-red-600" id="statSecurity"><?= count(array_filter($logs, function($l) { return in_array($l['action'], ['security_alert', 'unauthorized_panel_access']); })) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                        <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Redirections</div>
                        <div class="text-xl font-bold tracking-tight text-indigo-600" id="statSuccess"><?= count(array_filter($logs, function($l) { return $l['action'] === 'click_exam_url'; })) ?></div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-slate-200 shadow-sm">
                        <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wider mb-1">Bot Traffic</div>
                        <div class="text-xl font-bold tracking-tight text-orange-600" id="statBot"><?= count(array_filter($logs, function($l) { return $l['is_bot']; })) ?></div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div class="flex gap-2">
                            <div class="filter-pill active" data-filter="all">All</div>
                            <div class="filter-pill" data-filter="security">Security</div>
                            <div class="filter-pill" data-filter="bot">Bot</div>
                        </div>
                        <div class="relative w-64">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="logSearch" placeholder="Filter activity..." class="w-full bg-white border border-slate-200 rounded-md py-1.5 pl-9 pr-3 text-xs outline-none focus:ring-1 focus:ring-slate-400">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr class="text-slate-500 font-medium">
                                    <th class="px-6 py-3 font-semibold">Time</th>
                                    <th class="px-6 py-3 font-semibold">IP Address</th>
                                    <th class="px-6 py-3 font-semibold">Activity</th>
                                    <th class="px-6 py-3 font-semibold">Target</th>
                                </tr>
                            </thead>
                            <tbody id="logTableBody" class="divide-y divide-slate-100">
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-3.5 text-slate-400"><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td class="px-6 py-3.5">
                                            <div class="font-semibold text-slate-900"><?= $log['ip_address'] ?></div>
                                            <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[150px]"><?= $log['isp'] ?: 'Local' ?></div>
                                        </td>
                                        <td class="px-6 py-3.5">
                                            <?php $is_sec = in_array($log['action'], ['security_alert', 'unauthorized_panel_access']); ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold border <?= $is_sec ? 'bg-red-50 text-red-600 border-red-100' : 'bg-slate-100 text-slate-600 border-slate-200' ?> uppercase"><?= $log['action'] ?></span>
                                        </td>
                                        <td class="px-6 py-3.5 text-slate-500 truncate max-w-[200px] italic"><?= $log['context'] ?: '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- SPA ROUTER ENGINE -->
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
        document.addEventListener('click', e => {
            const link = e.target.closest('a');
            if (link && link.href && link.href.includes(window.location.origin) && !link.target && !link.href.includes('logout') && !link.href.includes('.csv')) {
                e.preventDefault(); navigateTo(link.href);
            }
        });
        window.addEventListener('popstate', () => navigateTo(window.location.href, false));
        async function navigateTo(url, push = true) {
            const loader = document.getElementById('spa-loader'); loader.style.width = '30%';
            try {
                const response = await fetch(url); const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const target = document.getElementById('spa-content-target');
                target.classList.remove('fade-content'); void target.offsetWidth; 
                target.innerHTML = doc.getElementById('spa-content-target').innerHTML;
                target.classList.add('fade-content');
                document.title = doc.title;
                if (push) window.history.pushState({}, '', url);
                const pageName = url.split('/').pop().split('?')[0] || 'index.php';
                document.querySelectorAll('.nav-link').forEach(l => { l.classList.remove('active'); if (l.getAttribute('href').includes(pageName)) l.classList.add('active'); });
                target.querySelectorAll('script').forEach(oldScript => { const newScript = document.createElement('script'); Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value)); newScript.appendChild(document.createTextNode(oldScript.innerHTML)); oldScript.parentNode.replaceChild(newScript, oldScript); });
                loader.style.width = '100%'; setTimeout(() => loader.style.width = '0', 300);
            } catch (err) { window.location.href = url; }
        }

        // LOGS LOGIC
        let allLogs = <?= json_encode($logs) ?>;
        document.querySelectorAll('.filter-pill').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                renderLogs(this.dataset.filter);
            });
        });

        function renderLogs(filter = 'all') {
            const tbody = document.getElementById('logTableBody');
            let filtered = allLogs.filter(log => {
                if (filter === 'security' && !(['security_alert', 'unauthorized_panel_access'].includes(log.action))) return false;
                if (filter === 'bot' && log.is_bot != 1) return false;
                return true;
            });
            tbody.innerHTML = filtered.map(log => `
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-3.5 text-slate-400">${new Date(log.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'})}</td>
                    <td class="px-6 py-3.5">
                        <div class="font-semibold text-slate-900">${log.ip_address}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[150px]">${log.isp || 'Local'}</div>
                    </td>
                    <td class="px-6 py-3.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold border ${['security_alert', 'unauthorized_panel_access'].includes(log.action) ? 'bg-red-50 text-red-600 border-red-100' : 'bg-slate-100 text-slate-600 border-slate-200'} uppercase">${log.action}</span>
                    </td>
                    <td class="px-6 py-3.5 text-slate-500 truncate max-w-[200px] italic">${log.context || '-'}</td>
                </tr>
            `).join('');
        }
    </script>
</body>
</html>
