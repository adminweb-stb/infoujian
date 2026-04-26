<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ./");
    exit;
}
require_once '../../core/db.php';

// --- CONFIG LOGIC ---
$config_path = __DIR__ . '/../data/config.json';
$config = file_exists($config_path) ? json_decode(file_get_contents($config_path), true) : ['active_exam' => 'uts', 'active_period' => 'ganjil'];
$active_exam = $config['active_exam'] ?? 'uts';
$active_period = $config['active_period'] ?? 'ganjil';

$view_period = isset($_GET['period']) ? $_GET['period'] : $active_period;
$semesters_to_show = ($view_period === 'ganjil') ? [1, 3, 5] : [2, 4, 6];
$current_view_sem = isset($_GET['sem']) ? (int)$_GET['sem'] : $semesters_to_show[0];

// API Actions
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    if ($_GET['api'] === 'get') {
        $sem = (int)$_GET['sem'];
        $table = "{$active_exam}_semester_$sem";
        $data = [];
        $res = $conn->query("SELECT * FROM $table ORDER BY sesi ASC, jam ASC, kelas ASC");
        if ($res) { while($row = $res->fetch_assoc()) { $data[] = $row; } }
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }
}

// Stats for badges
$stats = [];
foreach ([1,2,3,4,5,6,7,8] as $s) {
    $table = "{$active_exam}_semester_$s";
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    $stats[$s] = ($check && $check->num_rows > 0) ? ($conn->query("SELECT COUNT(*) as total FROM $table")->fetch_assoc()['total']) : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules | Admin Panel</title>
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
        
        .tabs-list {
            display: inline-flex; height: 38px; align-items: center; justify-content: center;
            border-radius: 8px; background-color: #f1f5f9; padding: 3px; color: #64748b; gap: 2px;
        }
        .tab-trigger {
            display: inline-flex; align-items: center; justify-content: center; white-space: nowrap;
            border-radius: 6px; padding: 4px 14px; font-size: 12px; font-weight: 500; cursor: pointer;
            transition: all 0.15s ease; gap: 8px;
        }
        .tab-trigger.active { 
            background-color: white; color: #0f172a; 
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); 
        }
        .tab-trigger.inactive:hover { color: #0f172a; background-color: rgba(255,255,255,0.4); }

        .badge-shadcn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9999px;
            font-size: 9px; font-weight: 700; line-height: 1; transition: all 0.2s;
        }
        .tab-trigger.active .badge-shadcn { background-color: #4f46e5; color: white; }
        .tab-trigger.inactive .badge-shadcn { background-color: #e0e7ff; color: #4338ca; }

        .nav-link.active { background-color: rgba(255,255,255,0.05); color: white; }
        .nav-link.active i { color: #818cf8; }
        
        .loading-overlay { opacity: 0.4; pointer-events: none; transition: opacity 0.2s; }
        .fade-content { animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.998); } to { opacity: 1; transform: scale(1); } }
        .sidebar-mobile-open { transform: translateX(0) !important; }
    </style>
</head>
<body class="text-slate-950 bg-white min-h-screen">

    <!-- MOBILE OVERLAY -->
    <div id="mobile-overlay" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-950/50 backdrop-blur-sm z-[55] hidden opacity-0 transition-opacity duration-300 md:hidden"></div>

    <!-- FIXED SIDEBAR -->
    <aside id="sidebar" class="sidebar-transition fixed top-0 left-0 h-full w-56 bg-slate-950 flex flex-col z-[60] text-slate-400 border-r border-slate-800 -translate-x-full md:translate-x-0">
        <div class="p-4 h-14 border-b border-white/5 flex items-center justify-between whitespace-nowrap overflow-hidden">
            <div class="flex items-center gap-2.5">
                <img src="../assets/images/logo.png" class="w-6 brightness-0 invert flex-shrink-0">
                <span class="font-semibold text-white text-[13px] tracking-tight sidebar-expand-only">Ujian Admin</span>
            </div>
            <button onclick="window.innerWidth < 768 ? toggleMobileSidebar() : toggleSidebar()" class="p-1 hover:bg-white/10 rounded-md transition-colors">
                <i id="toggle-icon" class="bi bi-chevron-left text-[11px]"></i>
            </button>
        </div>
        <div class="p-3 flex-1 space-y-1 overflow-visible">
            <a href="index.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2 rounded-md hover:text-white transition-all text-xs">
                <i class="bi bi-grid-1x2 text-sm"></i><span class="sidebar-expand-only">Dashboard</span><span class="tooltip-text">Dashboard</span>
            </a>
            <a href="manage_schedules.php" class="nav-link active tooltip relative flex items-center gap-3 px-3 py-2 rounded-md hover:text-white transition-all text-xs">
                <i class="bi bi-calendar-event text-sm"></i><span class="sidebar-expand-only">Schedules</span><span class="tooltip-text">Schedules</span>
            </a>
            <a href="logs.php" class="nav-link tooltip relative flex items-center gap-3 px-3 py-2 rounded-md hover:text-white transition-all text-xs">
                <i class="bi bi-shield-shaded text-sm"></i><span class="sidebar-expand-only">Activity Log</span><span class="tooltip-text">Activity Log</span>
            </a>
        </div>
        <div class="p-3 border-t border-white/5">
            <a href="?logout=1" class="tooltip relative flex items-center gap-3 px-3 py-2 rounded-md text-slate-500 hover:text-red-400 text-xs transition-all"><i class="bi bi-box-arrow-left text-sm"></i><span class="sidebar-expand-only">Sign Out</span><span class="tooltip-text">Sign Out</span></a>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div id="main-content" class="md:ml-56 flex-1 flex flex-col min-w-0 min-h-screen transition-all">
        <div id="spa-content-target" class="flex-1 flex flex-col fade-content">
            <header class="h-14 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-8 sticky top-0 z-40">
                <div class="flex items-center gap-3">
                    <button onclick="toggleMobileSidebar()" class="md:hidden text-slate-500 hover:text-slate-900 transition-colors mr-1"><i class="bi bi-list text-xl"></i></button>
                    <h2 class="text-sm font-semibold text-slate-900 tracking-tight">Schedule Management</h2>
                    <span class="hidden sm:inline-block px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-semibold rounded border border-slate-200 uppercase"><?= $active_exam ?> <?= $active_period ?></span>
                </div>
                <div class="flex items-center gap-2 md:gap-3">
                    <button onclick="openAdd()" class="text-[11px] font-bold bg-indigo-600 text-white px-3 md:px-4 py-1.5 rounded-md hover:bg-indigo-700 transition-colors shadow-sm tracking-wide"><i class="bi bi-plus-lg mr-1"></i> Add Schedule</button>
                    <button onclick="document.getElementById('file-upload').click()" class="text-[11px] font-bold bg-slate-950 text-white px-3 md:px-4 py-1.5 rounded-md hover:bg-slate-800 transition-colors shadow-sm tracking-wide"><i class="bi bi-file-earmark-arrow-up mr-1 hidden md:inline-block"></i> Import CSV</button>
                    <form action="import.php" method="POST" enctype="multipart/form-data" class="hidden"><input type="hidden" id="import-sem" name="semester" value="<?= $current_view_sem ?>"><input type="file" id="file-upload" name="csv_file" onchange="this.form.submit()"></form>
                </div>
            </header>

            <main class="p-6 max-w-7xl w-full mx-auto space-y-6">
                <div class="flex items-center justify-between">
                    <div class="tabs-list" id="tabContainer">
                        <?php foreach ($semesters_to_show as $sem): ?>
                            <div onclick="switchTab(<?= $sem ?>)" id="tab-<?= $sem ?>" class="tab-trigger <?= ($sem === $current_view_sem) ? 'active' : 'inactive' ?>">
                                Semester <?= $sem ?>
                                <span class="badge-shadcn" id="stat-<?= $sem ?>"><?= $stats[$sem] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="togglePeriod()" class="text-xs font-medium text-slate-500 hover:text-slate-950 flex items-center gap-2 transition-colors">
                        <i class="bi bi-arrow-left-right text-[10px]"></i>
                        Switch Period
                    </button>
                </div>

                <div id="table-wrapper" class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4 bg-slate-50/50">
                        <div class="flex items-center gap-3">
                            <div class="relative w-64">
                                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" id="tableSearch" placeholder="Search records..." class="w-full bg-white border border-slate-200 rounded-md py-1.5 pl-9 pr-3 text-xs outline-none focus:ring-1 focus:ring-slate-400 transition-all">
                            </div>
                            <select id="filterHari" class="bg-white border border-slate-200 rounded-md py-1.5 px-3 text-xs outline-none focus:ring-1 focus:ring-slate-400 cursor-pointer">
                                <option value="">All Days</option>
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                                <option value="Sabtu">Sabtu</option>
                                <option value="Minggu">Minggu</option>
                            </select>
                            <select id="filterSesi" class="bg-white border border-slate-200 rounded-md py-1.5 px-3 text-xs outline-none focus:ring-1 focus:ring-slate-400 cursor-pointer">
                                <option value="">All Sessions</option>
                                <option value="I">Sesi I</option>
                                <option value="II">Sesi II</option>
                                <option value="III">Sesi III</option>
                                <option value="IV">Sesi IV</option>
                            </select>
                        </div>
                        <div class="text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-100 px-4 py-1.5 rounded-full shadow-sm">
                            Found <span id="filterCount" class="text-red-600 font-black text-xs mx-1">0</span> Records
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr class="text-slate-500 font-medium">
                                    <th class="px-6 py-3 font-semibold">Day & Date</th>
                                    <th class="px-6 py-3 font-semibold">Time Slot</th>
                                    <th class="px-6 py-3 font-semibold">Subject Title</th>
                                    <th class="px-6 py-3 font-semibold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody" class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>
                <div id="empty-state" class="hidden p-20 text-center bg-slate-50/50 rounded-lg border border-dashed border-slate-200">
                    <i class="bi bi-inbox text-slate-300 text-3xl mb-2 block"></i>
                    <p class="text-slate-500 text-xs font-medium">No records found for S<span id="view-sem-title"></span>.</p>
                </div>
            </main>
        </div>
    </div>

    <!-- MODAL ADD SCHEDULE -->
    <div id="addFormModal" class="fixed inset-0 bg-slate-950/20 backdrop-blur-[1px] z-[110] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl border border-slate-200 fade-content overflow-hidden">
            <div class="px-5 py-3.5 border-b flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <h3 class="text-[12px] font-bold text-slate-900 uppercase tracking-tight">Add New Schedule</h3>
                </div>
                <button onclick="closeModal('addFormModal')" class="text-slate-400 hover:text-slate-950 transition-colors"><i class="bi bi-x-lg text-sm"></i></button>
            </div>
            <form id="addForm" class="p-5 space-y-4">
                <div class="space-y-1.5 p-3 bg-indigo-50/50 rounded-lg border border-indigo-100 mb-1 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <label class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5"><i class="bi bi-folder2-open text-indigo-500"></i> Target Semester</label>
                        <select id="add-sem" name="sem" class="px-2 py-1 bg-white border border-indigo-200 rounded text-xs font-bold text-indigo-700 outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer shadow-sm">
                            <?php foreach ($semesters_to_show as $s): if($s <= 6): ?>
                                <option value="<?= $s ?>">Semester <?= $s ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                    </div>
                    <p class="text-[10px] text-red-600 font-bold leading-tight flex items-center gap-1 m-0"><i class="bi bi-exclamation-triangle-fill"></i> Pastikan semester sesuai!</p>
                </div>
                
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-3 md:col-span-2 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Day</label>
                        <input type="text" id="add-hari" name="hari" placeholder="e.g. Senin" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 transition-all" required>
                    </div>
                    <div class="col-span-4 md:col-span-3 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Date</label>
                        <input type="text" id="add-tanggal" name="tanggal" placeholder="e.g. 07/05/2026" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 transition-all" required>
                    </div>
                    <div class="col-span-5 md:col-span-2 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Session</label>
                        <select id="add-sesi" name="sesi" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                            <option value="I">Sesi I</option>
                            <option value="II">Sesi II</option>
                            <option value="III">Sesi III</option>
                            <option value="IV">Sesi IV</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-5 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Exam Mode</label>
                        <div class="flex items-center gap-4 bg-slate-50 px-3 py-2 rounded border border-slate-200 h-[34px]">
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="radio" name="add_mode" value="online" checked class="text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5" onchange="toggleAddMode()"> Online
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="radio" name="add_mode" value="offline" class="text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5" onchange="toggleAddMode()"> Offline
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-8 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Subject Title</label>
                        <input type="text" id="add-matkul" name="matkul" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 font-semibold" required>
                    </div>
                    <div class="col-span-12 md:col-span-4 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Time Slot</label>
                        <input type="text" id="add-jam" name="jam" placeholder="08:00 - 09:30" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500" required>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-4 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Class</label>
                        <input type="text" id="add-kelas" name="kelas" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 placeholder:text-slate-300" placeholder="e.g. IF A SR">
                    </div>
                    <div class="col-span-12 md:col-span-8 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lecturer Name</label>
                        <input type="text" id="add-dosen" name="dosen" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="space-y-1" id="add-link-container">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Exam URL Target</label>
                    <div class="relative">
                        <i class="bi bi-link-45deg absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="url" id="add-link" name="link_server" class="w-full px-3 py-2 pl-8 bg-slate-50 border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 italic text-indigo-600" placeholder="https://cbt-server.example.com">
                    </div>
                </div>

                <div class="space-y-1 hidden" id="add-ruangan-container">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Room (Ruangan)</label>
                    <div class="relative">
                        <i class="bi bi-door-open absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="add-ruangan" name="ruangan" class="w-full px-3 py-2 pl-8 bg-slate-50 border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 font-bold" placeholder="e.g. Lab Komputer 2">
                    </div>
                </div>

                <div class="pt-5 flex justify-end items-center border-t mt-4">
                    <div class="flex gap-2">
                        <button type="button" onclick="closeModal('addFormModal')" class="px-5 py-2 text-[11px] font-bold text-slate-500 hover:text-slate-900 transition-colors uppercase tracking-widest">Cancel</button>
                        <button type="submit" class="bg-emerald-600 text-white px-8 py-2 rounded text-[11px] font-bold hover:bg-emerald-700 transition-all shadow-md uppercase tracking-widest"><i class="bi bi-plus-lg mr-1"></i> Save Data</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDITOR (WIDE & COMPACT) -->
    <div id="editFormModal" class="fixed inset-0 bg-slate-950/20 backdrop-blur-[1px] z-[110] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-3xl border border-slate-200 fade-content overflow-hidden">
            <div class="px-5 py-3.5 border-b flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></div>
                    <h3 class="text-[12px] font-bold text-slate-900 uppercase tracking-tight">Modify Schedule Record</h3>
                </div>
                <button onclick="closeModal('editFormModal')" class="text-slate-400 hover:text-slate-950 transition-colors"><i class="bi bi-x-lg text-sm"></i></button>
            </div>
            <form id="editForm" class="p-5 space-y-4">
                <input type="hidden" id="edit-id" name="id">
                <input type="hidden" id="edit-sem" name="sem">
                
                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-3 md:col-span-2 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Day</label>
                        <input type="text" id="edit-hari" name="hari" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 transition-all" required>
                    </div>
                    <div class="col-span-4 md:col-span-3 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Date</label>
                        <input type="text" id="edit-tanggal" name="tanggal" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 transition-all" required>
                    </div>
                    <div class="col-span-5 md:col-span-2 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Session</label>
                        <select id="edit-sesi" name="sesi" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                            <option value="I">Sesi I</option>
                            <option value="II">Sesi II</option>
                            <option value="III">Sesi III</option>
                            <option value="IV">Sesi IV</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-5 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Exam Mode</label>
                        <div class="flex items-center gap-4 bg-slate-50 px-3 py-2 rounded border border-slate-200 h-[34px]">
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="radio" name="edit_mode" value="online" checked class="text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5" onchange="toggleEditMode()"> Online
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="radio" name="edit_mode" value="offline" class="text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5" onchange="toggleEditMode()"> Offline
                            </label>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-8 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Subject Title</label>
                        <input type="text" id="edit-matkul" name="matkul" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 font-semibold" required>
                    </div>
                    <div class="col-span-12 md:col-span-4 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Time Slot</label>
                        <input type="text" id="edit-jam" name="jam" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500" required>
                    </div>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12 md:col-span-4 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Class</label>
                        <input type="text" id="edit-kelas" name="kelas" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 placeholder:text-slate-300" placeholder="e.g. IF A SR">
                    </div>
                    <div class="col-span-12 md:col-span-8 space-y-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lecturer Name</label>
                        <input type="text" id="edit-dosen" name="dosen" class="w-full px-3 py-2 bg-white border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="space-y-1" id="edit-link-container">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Exam URL Target</label>
                    <div class="relative">
                        <i class="bi bi-link-45deg absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="url" id="edit-link" name="link_server" class="w-full px-3 py-2 pl-8 bg-slate-50 border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 italic text-indigo-600">
                    </div>
                </div>

                <div class="space-y-1 hidden" id="edit-ruangan-container">
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Room (Ruangan)</label>
                    <div class="relative">
                        <i class="bi bi-door-open absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="edit-ruangan" name="ruangan" class="w-full px-3 py-2 pl-8 bg-slate-50 border border-slate-200 rounded text-xs outline-none focus:ring-1 focus:ring-indigo-500 font-bold" placeholder="e.g. Lab Komputer 2">
                    </div>
                </div>

                <div class="pt-5 flex justify-between items-center border-t mt-4">
                    <button type="button" onclick="deleteJadwal()" class="text-[10px] font-bold text-red-500 hover:text-red-700 uppercase tracking-widest transition-colors flex items-center gap-1.5">
                        <i class="bi bi-trash text-xs"></i> Delete Record
                    </button>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeModal('editFormModal')" class="px-5 py-2 text-[11px] font-bold text-slate-500 hover:text-slate-900 transition-colors uppercase tracking-widest">Cancel</button>
                        <button type="submit" class="bg-indigo-600 text-white px-8 py-2 rounded text-[11px] font-bold hover:bg-indigo-700 transition-all shadow-md uppercase tracking-widest"><i class="bi bi-check2-circle mr-1"></i> Update Data</button>
                    </div>
                </div>
            </form>
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

        // LOGIC
        let scheduleData = [];
        let activeSem = <?= $current_view_sem ?>;
        let activePeriod = '<?= $view_period ?>';
        const allStats = <?= json_encode($stats) ?>;

        async function switchTab(sem) {
            activeSem = sem;
            document.querySelectorAll('.tab-trigger').forEach(t => t.classList.replace('active', 'inactive'));
            const activeTab = document.getElementById(`tab-${sem}`);
            if (activeTab) { activeTab.classList.replace('inactive', 'active'); }
            document.getElementById('import-sem').value = sem;
            
            const url = new URL(window.location); url.searchParams.set('sem', sem); 
            window.history.pushState({}, '', url);
            await loadSchedules();
        }

        function togglePeriod() {
            activePeriod = (activePeriod === 'ganjil') ? 'genap' : 'ganjil';
            const semesters = (activePeriod === 'ganjil') ? [1, 3, 5] : [2, 4, 6];
            activeSem = semesters[0];
            
            // Update Tabs
            let tabHtml = ''; semesters.forEach(s => { tabHtml += `<div onclick="switchTab(${s})" id="tab-${s}" class="tab-trigger ${s === activeSem ? 'active' : 'inactive'}">Semester ${s} <span class="badge-shadcn">${allStats[s] || 0}</span></div>`; });
            document.getElementById('tabContainer').innerHTML = tabHtml;
            
            // Update Add Modal Dropdown
            let addSemHtml = ''; semesters.forEach(s => { addSemHtml += `<option value="${s}">Semester ${s}</option>`; });
            document.getElementById('add-sem').innerHTML = addSemHtml;

            const url = new URL(window.location); url.searchParams.set('period', activePeriod); url.searchParams.set('sem', activeSem); window.history.pushState({}, '', url);
            switchTab(activeSem);
        }

        async function loadSchedules() {
            const tbody = document.getElementById('scheduleTableBody');
            const wrapper = document.getElementById('table-wrapper');
            wrapper.classList.add('loading-overlay');
            try {
                const res = await fetch(`./manage_schedules.php?api=get&sem=${activeSem}`);
                const result = await res.json();
                if (result.status === 'success') { scheduleData = result.data; renderTable(); }
            } catch (err) {}
            wrapper.classList.remove('loading-overlay');
        }

        function renderTable() {
            const keyword = document.getElementById('tableSearch').value.toLowerCase();
            const selectedHari = document.getElementById('filterHari').value;
            const selectedSesi = document.getElementById('filterSesi').value;
            
            const tbody = document.getElementById('scheduleTableBody');
            const emptyState = document.getElementById('empty-state');
            
            let filtered = scheduleData.filter(item => {
                // Keyword Search
                const content = [
                    item.hari, item.tanggal, item.matkul, 
                    item.dosen, item.kelas, item.jam
                ].map(v => (v || '').toLowerCase()).join(' ');
                const matchesKeyword = !keyword || content.includes(keyword);
                
                // Day Filter
                const matchesHari = !selectedHari || (item.hari || '').toLowerCase() === selectedHari.toLowerCase();
                
                // Session Filter
                const matchesSesi = !selectedSesi || String(item.sesi) === selectedSesi;
                
                return matchesKeyword && matchesHari && matchesSesi;
            });

            // Update Counter
            document.getElementById('filterCount').innerText = filtered.length;

            if (filtered.length === 0) { 
                tbody.innerHTML = ''; 
                emptyState.classList.remove('hidden'); 
                return; 
            }
            
            emptyState.classList.add('hidden');
            tbody.innerHTML = filtered.map(item => `<tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-3.5">
                    <div class="font-semibold text-slate-900">${item.hari || '-'}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5 font-medium">${item.tanggal || '-'}</div>
                </td>
                <td class="px-6 py-3.5">
                    <div class="font-semibold text-indigo-600">${item.jam || '-'}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5 font-medium">${item.sesi ? 'Sesi ' + item.sesi : ''}</div>
                </td>
                <td class="px-6 py-3.5">
                    <div class="font-semibold text-slate-800">${item.matkul || '-'}</div>
                    <div class="text-[10px] text-slate-500 font-medium mt-0.5">${item.kelas || '-'} • ${item.dosen || '-'}</div>
                </td>
                <td class="px-6 py-3.5 text-right"><button onclick='openEdit(${JSON.stringify(item)})' class="text-indigo-600 hover:text-indigo-900 font-semibold underline underline-offset-4 text-[11px]">Edit</button></td>
            </tr>`).join('');
        }

        function openEdit(item) {
            document.getElementById('edit-id').value = item.id;
            document.getElementById('edit-sem').value = activeSem;
            document.getElementById('edit-hari').value = item.hari;
            document.getElementById('edit-tanggal').value = item.tanggal;
            document.getElementById('edit-sesi').value = item.sesi;
            document.getElementById('edit-jam').value = item.jam;
            document.getElementById('edit-matkul').value = item.matkul;
            document.getElementById('edit-dosen').value = item.dosen;
            
            if (item.link_server && item.link_server.toUpperCase() === 'OFFLINE') {
                document.querySelector('input[name="edit_mode"][value="offline"]').checked = true;
                document.getElementById('edit-link').value = '';
                
                let kls = item.kelas || '';
                let rgn = '';
                const match = kls.match(/(.*)\s\((.*)\)$/);
                if (match) {
                    kls = match[1];
                    rgn = match[2];
                }
                document.getElementById('edit-kelas').value = kls.trim();
                document.getElementById('edit-ruangan').value = rgn.trim();
            } else {
                document.querySelector('input[name="edit_mode"][value="online"]').checked = true;
                document.getElementById('edit-link').value = item.link_server;
                document.getElementById('edit-kelas').value = item.kelas;
                document.getElementById('edit-ruangan').value = '';
            }
            toggleEditMode();
            
            document.getElementById('editFormModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function toggleEditMode() {
            const isOnline = document.querySelector('input[name="edit_mode"]:checked').value === 'online';
            document.getElementById('edit-link-container').classList.toggle('hidden', !isOnline);
            document.getElementById('edit-ruangan-container').classList.toggle('hidden', isOnline);
            document.getElementById('edit-link').required = isOnline;
            document.getElementById('edit-ruangan').required = !isOnline;
        }

        function openAdd() {
            document.getElementById('addForm').reset();
            document.getElementById('add-sem').value = activeSem;
            document.querySelector('input[name="add_mode"][value="online"]').checked = true;
            toggleAddMode();
            document.getElementById('addFormModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function toggleAddMode() {
            const isOnline = document.querySelector('input[name="add_mode"]:checked').value === 'online';
            document.getElementById('add-link-container').classList.toggle('hidden', !isOnline);
            document.getElementById('add-ruangan-container').classList.toggle('hidden', isOnline);
            document.getElementById('add-link').required = isOnline;
            document.getElementById('add-ruangan').required = !isOnline;
        }

        function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
        
        // Add Listeners
        document.getElementById('tableSearch')?.addEventListener('input', () => renderTable());
        document.getElementById('filterHari')?.addEventListener('change', () => renderTable());
        document.getElementById('filterSesi')?.addEventListener('change', () => renderTable());
        
        document.getElementById('addForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const isOnline = document.querySelector('input[name="add_mode"]:checked').value === 'online';
            
            if (!isOnline) {
                formData.set('link_server', 'OFFLINE');
                const ruangan = formData.get('ruangan');
                const kelas = formData.get('kelas');
                formData.set('kelas', kelas ? `${kelas} (${ruangan})` : `(${ruangan})`);
            }
            
            try {
                const res = await fetch('./index.php?api=create', { method: 'POST', body: formData });
                const data = await res.json(); if (data.status === 'success') { closeModal('addFormModal'); loadSchedules(); }
            } catch (err) {}
        });

        document.getElementById('editForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const isOnline = document.querySelector('input[name="edit_mode"]:checked').value === 'online';
            
            if (!isOnline) {
                formData.set('link_server', 'OFFLINE');
                const ruangan = formData.get('ruangan');
                const kelas = formData.get('kelas');
                formData.set('kelas', kelas ? `${kelas} (${ruangan})` : `(${ruangan})`);
            }

            try {
                const res = await fetch('./index.php?api=update', { method: 'POST', body: formData });
                const data = await res.json(); if (data.status === 'success') { closeModal('editFormModal'); loadSchedules(); }
            } catch (err) {}
        });

        async function deleteJadwal() {
            if (!confirm("Delete?")) return;
            const fd = new FormData(); fd.append('id', document.getElementById('edit-id').value); fd.append('sem', activeSem);
            try {
                const res = await fetch('./index.php?api=delete', { method: 'POST', body: fd });
                const data = await res.json(); if (data.status === 'success') { closeModal('editFormModal'); loadSchedules(); }
            } catch (err) {}
        }

        loadSchedules();
    </script>
</body>
</html>
