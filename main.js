// --- Core App Logic (Extracted from index.html) ---

// Core Constants
const CACHE_TIME = 30 * 1000;
let currentData = [];
let currentSemester = null;
let activeExam = 'uts';
let activePeriod = 'ganjil';

// UI Elements
const desktopBody = document.getElementById('desktopBody');
const mobileCardList = document.getElementById('mobileCardList');
const tableWrapper = document.getElementById('tableWrapper');
const noData = document.getElementById('noData');
const infoBar = document.getElementById('infoHariTanggal');
const searchInput = document.getElementById('searchInput');
const refreshStatus = document.getElementById('refreshStatus');
const themeToggle = document.getElementById('themeToggle');

// Initialize Theme
const getCookie = (name) => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
};
const savedTheme = localStorage.getItem('theme') || getCookie('theme');
const useDark = savedTheme !== 'light';

if (useDark) {
    document.body.setAttribute('data-theme', 'dark');
    if (themeToggle) themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
}

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        if (document.body.getAttribute('data-theme') === 'dark') {
            document.body.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            document.cookie = "theme=light; path=/; max-age=31536000";
            themeToggle.innerHTML = '<i class="bi bi-moon-stars"></i>';
            logEvent('theme_light', currentSemester);
        } else {
            document.body.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            document.cookie = "theme=dark; path=/; max-age=31536000";
            themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
            logEvent('theme_dark', currentSemester);
        }
    });
}

const dateFilter = document.getElementById('dateFilter');
if (dateFilter) {
    dateFilter.addEventListener('change', () => {
        renderTable(currentData);
        const selectedText = dateFilter.options[dateFilter.selectedIndex].text;
        logEvent('date_filter', currentSemester, `Semester ${currentSemester}: ${selectedText}`);
    });
}

const updateInfoBar = () => {
    const now = new Date();
    const days = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
    const formattedDate = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    if (infoBar) {
        infoBar.innerHTML = `<i class="bi bi-calendar-event"></i> &nbsp; ${days[now.getDay()]}, ${formattedDate}`;
        infoBar.style.display = 'block';
    }
    const yearEl = document.getElementById("year");
    if (yearEl) yearEl.textContent = now.getFullYear();
};

const showSkeleton = () => {
    const isDesktop = window.innerWidth > 768;
    if (tableWrapper) tableWrapper.style.display = 'block';
    if (noData) noData.style.display = 'none';
    if (isDesktop && desktopBody) {
        desktopBody.innerHTML = Array(4).fill(`
            <tr class="table-row-premium">
                <td><div style="padding:0"><div class="skeleton-block sm" style="margin-bottom:6px"></div><div class="skeleton-block sm" style="width:60px"></div></div></td>
                <td><div class="skeleton-block sm" style="width:70px;margin:auto"></div></td>
                <td><div class="skeleton-block lg"></div></td>
                <td><div class="skeleton-block md"></div></td>
                <td><div class="skeleton-block btn" style="margin:auto"></div></td>
            </tr>`).join('');
    } else if (mobileCardList) {
        mobileCardList.innerHTML = Array(3).fill(`
            <div class="skeleton-card">
                <div class="skeleton-block sm" style="width:100px"></div>
                <div class="skeleton-block lg" style="width:90%"></div>
                <div style="display:flex;gap:1rem">
                    <div class="skeleton-block sm" style="width:80px"></div>
                    <div class="skeleton-block sm" style="width:120px"></div>
                </div>
                <div class="skeleton-block btn"></div>
            </div>`).join('');
    }
};

const loadData = async (semester, silent = false) => {
    document.documentElement.setAttribute('data-semester-theme', semester);
    currentSemester = semester;
    
    if (!silent) showSkeleton();
    
    const cacheKey = `data_${activeExam}_sem_${semester}`;
    const cached = localStorage.getItem(cacheKey);
    const cacheTime = localStorage.getItem(`${cacheKey}_time`);
    const now = Date.now();

    if (cached && cacheTime && (now - cacheTime < CACHE_TIME)) {
        currentData = JSON.parse(cached);
        if (!silent) {
            renderTable(currentData);
            if (refreshStatus) refreshStatus.innerText = `Update terakhir: ${new Date(parseInt(cacheTime)).toLocaleTimeString()}`;
        }
        return;
    }

    try {
        if (!silent && refreshStatus) refreshStatus.innerText = "Memperbarui data...";
        // NEW API ENDPOINT
        const response = await fetch(`/api/schedules?exam=${activeExam}&semester=${semester}&v=${now}`);
        if (!response.ok) throw new Error('File data tidak ditemukan');
        
        const data = await response.json();
        localStorage.setItem(cacheKey, JSON.stringify(data));
        localStorage.setItem(`${cacheKey}_time`, now.toString());
        
        const dataChanged = JSON.stringify(currentData) !== JSON.stringify(data);
        currentData = data;
        
        if (!silent || dataChanged) {
            populateDateFilter(data);
            renderTable(currentData);
        }
        if (!silent && refreshStatus) refreshStatus.innerText = "Data diperbarui dari server.";
    } catch (err) {
        console.error('Fetch error:', err);
        if (cached) {
            currentData = JSON.parse(cached);
            if (!silent) {
                renderTable(currentData);
                if (refreshStatus) refreshStatus.innerText = "Gagal menyinkronkan. Menggunakan data cache.";
            }
        }
    }
};

// Date Formatter - defined early so populateDateFilter can use it
const formatIndoDate = (dateStr) => {
    if (!dateStr || !dateStr.includes('/')) return dateStr;
    const [d, m, y] = dateStr.split('/');
    const months = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    return `${parseInt(d)} ${months[parseInt(m)]} ${y}`;
};

const populateDateFilter = (data) => {
    if (!dateFilter) return;
    const dates = [...new Set(data.map(i => i.tanggal))].sort((a, b) => {
        const [da, ma, ya] = a.split('/').map(Number);
        const [db, mb, yb] = b.split('/').map(Number);
        return new Date(ya, ma - 1, da) - new Date(yb, mb - 1, db);
    });
    
    let html = '<option value="today">Hari Ini</option><option value="all">Semua Jadwal</option>';
    dates.forEach(d => {
        const item = data.find(i => i.tanggal === d);
        const label = `${item.hari || ''}, ${formatIndoDate(d)}`;
        html += `<option value="${d}">${label}</option>`;
    });
    dateFilter.innerHTML = html;
    dateFilter.value = "today";
};

const renderTable = (data) => {
    const now = new Date();
    const hours = now.getHours();
    const currentTime = hours + (now.getMinutes() / 60);
    
    let displayDate = new Date(now);
    if (hours < 6) displayDate.setDate(now.getDate() - 1);
    
    const displayDateStr = `${String(displayDate.getDate()).padStart(2, '0')}/${String(displayDate.getMonth() + 1).padStart(2, '0')}/${displayDate.getFullYear()}`;

    let filtered = data;
    const term = searchInput ? searchInput.value.toLowerCase() : '';
    const prodi = document.getElementById('prodiFilter')?.value;
    const dateVal = document.getElementById('dateFilter')?.value || 'today';
    
    // Helper to parse date
    const parseDate = (s) => {
        const [d, m, y] = (s || "").split('/').map(Number);
        return new Date(y, m - 1, d);
    };
    const todayDate = new Date(displayDate.getFullYear(), displayDate.getMonth(), displayDate.getDate());

    if (term || prodi) {
        filtered = data.filter(i => {
            const matchTerm = !term || (
                (i["matkul"] || "").toLowerCase().includes(term) ||
                (i["kelas"] || "").toLowerCase().includes(term) ||
                (i["dosen"] || "").toLowerCase().includes(term)
            );
            const matchProdi = !prodi || (i["prodi"] === prodi);
            
            // If searching, we usually show everything that matches
            // but if a specific date is selected (not today/all), filter by it
            const matchDate = (dateVal === 'today' || dateVal === 'all') ? true : (i["tanggal"] === dateVal);
            
            return matchTerm && matchProdi && matchDate;
        });
    } else {
        // SMART FILTERING based on dateVal
        filtered = data.filter(item => {
            const itemDate = parseDate(item["tanggal"]);
            if (dateVal === 'all') {
                // Show Today + Future
                return itemDate >= todayDate;
            } else if (dateVal === 'today') {
                // Show Today only
                return (item["tanggal"] || "") === displayDateStr;
            } else {
                // Specific Date Selected
                return (item["tanggal"] || "") === dateVal;
            }
        });
    }

    const isShowingAll = dateVal === 'all';
    const isShowingSpecific = dateVal !== 'today' && dateVal !== 'all';

    if (filtered.length === 0) {
        if (tableWrapper) tableWrapper.style.display = 'block';
        if (noData) noData.style.display = 'block';
        if (desktopBody) desktopBody.innerHTML = '';
        if (mobileCardList) mobileCardList.innerHTML = '';
        return;
    }

    let focusData = filtered.map((item, idx) => {
        const statusObj = getStatus(item["tanggal"], item["jam"], currentTime);
        return { ...item, ...statusObj, originalIndex: idx };
    });

    // Always Hide finished items from the view (unless searching or specific date)
    if (!term) {
        const totalItems = focusData.length;
        focusData = focusData.filter((item, idx) => {
            // If specific date is picked, show all in that date
            if (isShowingSpecific) return true;

            // Show Ongoing and Future/Upcoming
            if (item.status === "Berlangsung" || item.status.startsWith("Belum Dimulai")) return true;
            // Keep the very last 'Selesai' item only if we are in Today view for context
            if (!isShowingAll && item.status === "Selesai" && idx === totalItems - 1) return true;
            return false;
        });
    }

    if (noData) noData.style.display = 'none';
    if (tableWrapper) tableWrapper.style.display = 'block';
    if (desktopBody) desktopBody.innerHTML = '';
    if (mobileCardList) mobileCardList.innerHTML = '';

    const desktopContainer = document.getElementById('desktopContainer');
    // Important: replace isEvaluationMode with isShowingAll or isShowingSpecific for date separators
    const showSeparators = isShowingAll || isShowingSpecific;
    if (window.innerWidth > 768) {
        if (desktopContainer) desktopContainer.style.setProperty('display', 'block', 'important');
        if (mobileCardList) mobileCardList.style.setProperty('display', 'none', 'important');
        renderDesktopView(focusData, currentTime, showSeparators);
    } else {
        if (desktopContainer) desktopContainer.style.setProperty('display', 'none', 'important');
        if (mobileCardList) mobileCardList.style.setProperty('display', 'block', 'important');
        renderMobileView(focusData, currentTime, showSeparators);
    }
};

// formatIndoDate moved to top (before populateDateFilter)

const renderDesktopView = (data, currentTime, showSeparators) => {
    let lastDate = "";
    if (!desktopBody) return;
    desktopBody.innerHTML = data.map((item, index) => {
        const dateRaw = `${item["hari"] || '-'}, ${item["tanggal"] || '-'}`;
        const datePretty = `${item["hari"] || '-'}, ${formatIndoDate(item["tanggal"])}`;
        let dateHeader = "";
        if (showSeparators && dateRaw !== lastDate) {
            dateHeader = `<tr><td colspan="5"><div class="date-separator">${datePretty}</div></td></tr>`;
            lastDate = dateRaw;
        }

        const { status, badgeClass, clrClass } = getStatus(item["tanggal"], item["jam"], currentTime);
        const serverUrl = item["link_server"] || "#";
        const isBerlangsung = status === 'Berlangsung';
        const isSelesai = status === 'Selesai';
        const isOffline = serverUrl === 'OFFLINE';

        let btnHtmlDesktop = isOffline ? 
            `<span class="btn-premium btn-offline" style="padding:0.5rem; font-size:12px; display:inline-block; text-align:center;"><i class="bi bi-buildings"></i> Offline</span>` :
            `<a href="${isBerlangsung ? serverUrl : 'javascript:void(0)'}" 
                ${isBerlangsung ? 'target="_blank"' : ''}
                class="btn-premium ${isBerlangsung ? 'btn-active' : (isSelesai ? 'btn-ended' : 'btn-upcoming')}" 
                style="padding:0.5rem; font-size:12px">
                ${isBerlangsung ? 'Masuk ujian' : (isSelesai ? 'Selesai' : '<i class="bi bi-lock-fill"></i> Terkunci')}
            </a>`;

        return `
            ${dateHeader}
            <tr class="table-row-premium ${clrClass}">
                <td>
                    <div style="font-weight:700; color:var(--primary); display:flex; align-items:center; gap:0.5rem; margin-bottom: 0.3rem;">
                        ${item["jam"] || '-'}
                        <span class="sesi-label" style="font-size:var(--fz-micro)">Sesi ${item["sesi"] || index + 1}</span>
                    </div>
                    <span class="status-badge ${badgeClass}" style="font-size:var(--fz-micro); padding: 0.2rem 0.6rem;">${status}</span>
                </td>
                    <td>
                        <div class="kelas-badge">${item["kelas"] || '-'}</div>
                        <div style="font-size:11px; color:var(--primary); font-weight:800; margin-top:4px;">
                            ${item["ruang"] ? `<i class="bi bi-geo-alt-fill"></i> ${item["ruang"]}` : ''}
                        </div>
                    </td>
                    <td style="font-weight:700; color:var(--text-main); font-size: var(--fz-title)">${item["matkul"] || '-'}</td>
                    <td style="color:var(--text-muted); font-size:13px;">${item["dosen"] || '-'}</td>
                <td><span class="tooltip-wrapper" ${!isBerlangsung && !isSelesai ? `data-tooltip="Dibuka ${item['tanggal'] || '-'} pukul ${item['jam'] ? item['jam'].split('-')[0].trim() : '-'}"` : ''}>${btnHtmlDesktop}</span></td>
            </tr>
        `;
    }).join('');
};

const renderMobileView = (data, currentTime, showSeparators) => {
    let lastDate = "";
    if (!mobileCardList) return;
    mobileCardList.innerHTML = data.map((item, index) => {
        const dateRaw = `${item["hari"] || '-'}, ${item["tanggal"] || '-'}`;
        const datePretty = `${item["hari"] || '-'}, ${formatIndoDate(item["tanggal"])}`;
        let dateHeader = (showSeparators && dateRaw !== lastDate) ? `<div class="date-separator">${datePretty}</div>` : "";
        if (dateHeader) lastDate = dateRaw;

        const { status, badgeClass, clrClass } = getStatus(item["tanggal"], item["jam"], currentTime);
        const serverUrl = item["link_server"] || "#";
        const isBerlangsung = status === 'Berlangsung';
        const isSelesai = status === 'Selesai';
        const isOffline = serverUrl === 'OFFLINE';

        let btnHtmlMobile = isOffline ? 
            `<span class="btn-premium btn-offline" style="display:block; text-align:center;"><i class="bi bi-buildings"></i> Offline</span>` :
            `<a href="${isBerlangsung ? serverUrl : 'javascript:void(0)'}" 
               ${isBerlangsung ? 'target="_blank"' : ''}
               class="btn-premium ${isBerlangsung ? 'btn-active' : (isSelesai ? 'btn-ended' : 'btn-upcoming')}">
               ${isBerlangsung ? 'Masuk ujian' : (isSelesai ? 'Selesai' : '<i class="bi bi-lock-fill"></i> Terkunci')}
            </a>`;

        return `
            ${dateHeader}
            <div class="exam-card ${clrClass}">
                <div class="card-meta-header">
                    <div class="time-header"><i class="bi bi-clock-history"></i> ${item["jam"] || '-'} <span class="sesi-label">Sesi ${item["sesi"] || index+1}</span></div>
                    <span class="status-badge ${badgeClass}" style="font-size:9px; padding: 0.1rem 0.4rem;">${status}</span>
                </div>
                <div class="card-title">${item["matkul"] || '-'}</div>
                <div class="card-grid">
                    <div class="grid-item"><i class="bi bi-door-open"></i> ${item["kelas"] || '-'}</div>
                    <div class="grid-item">${item["ruang"] ? `<i class="bi bi-geo-alt-fill"></i> ${item["ruang"]}` : ''}</div>
                    <div class="grid-item" style="grid-column: span 2;"><i class="bi bi-person-badge"></i> ${item["dosen"] || '-'}</div>
                </div>
                <span class="tooltip-wrapper" ${!isBerlangsung && !isSelesai ? `data-tooltip="Dibuka ${item['tanggal'] || '-'} pukul ${item['jam'] ? item['jam'].split('-')[0].trim() : '-'}"` : ''}>${btnHtmlMobile}</span>
            </div>
        `;
    }).join('');
};

const getStatus = (dateStr, jamRaw, currentTime) => {
    let status = "Belum Dimulai";
    let badgeClass = "status-upcoming";
    let clrClass = "";
    const now = new Date();
    const parseDate = (s) => {
        const [d, m, y] = s.split('/').map(Number);
        return new Date(y, m - 1, d);
    };
    const examDate = parseDate(dateStr);
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    if (examDate > today) {
        const [d, m] = dateStr.split('/');
        const monthNames = ["", "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
        status = `Belum Dimulai (${d} ${monthNames[parseInt(m)]})`;
        badgeClass = "status-future";
        clrClass = "card-future";
    } else if (examDate < today) {
        status = "Selesai";
        badgeClass = "status-ended";
        clrClass = "card-ended row-ended";
    } else {
        if (jamRaw && jamRaw.includes("-")) {
            const [mulai, selesai] = jamRaw.split("-").map(j => parseFloat(j.trim().replace(":", ".").replace(",", ".")));
            if (currentTime >= mulai && currentTime <= selesai) {
                status = "Berlangsung"; badgeClass = "status-active"; clrClass = "card-active row-active";
            } else if (currentTime > selesai) {
                status = "Selesai"; badgeClass = "status-ended"; clrClass = "card-ended row-ended";
            }
        }
    }
    return { status, badgeClass, clrClass };
};

const logEvent = async (action, semester, context = '') => {
    try {
        const payload = {
            action,
            semester: semester || 0,
            exam_type: activeExam,
            context: String(context),
            resolution: `${screen.width}x${screen.height}`,
            path: window.location.pathname + window.location.search,
            page_title: document.title,
        };
        fetch('/api/log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true
        }).catch(() => {});
    } catch (e) {}
};

const initApp = async () => {
    updateInfoBar();
    showSkeleton();
    let config = {};
    try {
        const res = await fetch('/api/config?v=' + Date.now());
        config = await res.json();
        activeExam = config.active_exam || 'uts';
        activePeriod = config.active_period || 'ganjil';
        const titleLabel = config.active_exam_label || "Jadwal Ujian";
        const h1 = document.querySelector('.header-text h1');
        if (h1) h1.textContent = titleLabel;
        document.title = titleLabel + " | ST Bhinneka";
    } catch (e) {
        config = { active_period: 'genap', max_semester: 6 };
    }

    const tabsContainer = document.getElementById('semesterTabs');
    if (!tabsContainer) return;
    tabsContainer.innerHTML = '';
    const maxSem = config.max_semester || 6;
    const semesters = ((config.active_period === 'ganjil') ? [1, 3, 5, 7] : [2, 4, 6, 8]).filter(s => s <= maxSem);
    
    semesters.forEach((sem, idx) => {
        const a = document.createElement('a');
        a.href = "#";
        a.className = "nav-link" + (idx === 0 ? " active" : "");
        a.dataset.semester = sem;
        a.textContent = "Semester " + sem;
        a.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.nav-link').forEach(nav => nav.classList.remove('active'));
            a.classList.add('active');
            loadData(sem);
            logEvent('tab_click', sem);
        });
        tabsContainer.appendChild(a);
    });

    if (semesters.length > 0) {
        loadData(semesters[0]);
        logEvent('page_load', semesters[0]);
    }
};

if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const term = e.target.value.toLowerCase();
        searchTimeout = setTimeout(() => {
            renderTable(currentData);
            if (term.length >= 3) logEvent('search', currentSemester, term);
        }, 300);
    });
}

const prodiFilter = document.getElementById('prodiFilter');
if (prodiFilter) {
    prodiFilter.addEventListener('change', () => {
        renderTable(currentData);
        const selectedText = prodiFilter.options[prodiFilter.selectedIndex].text;
        logEvent('prodi_filter', currentSemester, `Semester ${currentSemester}: ${selectedText}`);
    });
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest('.btn-premium');
    if (btn) {
        let matkul = '';
        const card = e.target.closest('.exam-card');
        const row = e.target.closest('.table-row-premium');
        if (card) matkul = card.querySelector('.card-title')?.textContent?.trim();
        else if (row) matkul = row.querySelector('td:nth-child(3)')?.textContent?.trim();

        if (btn.classList.contains('btn-upcoming')) logEvent('click_locked', currentSemester, matkul);
        else if (btn.classList.contains('btn-ended')) logEvent('click_ended', currentSemester, matkul);
        else if (btn.classList.contains('btn-active')) logEvent('click_exam_url', currentSemester, matkul);
        else if (btn.classList.contains('btn-offline')) logEvent('click_offline', currentSemester, matkul);
    }
});

const vFinalBtn = document.getElementById('vFinalScrollTop');
if (vFinalBtn) {
    window.addEventListener('scroll', () => {
        vFinalBtn.classList.toggle('visible', window.pageYOffset > 300);
    });
    vFinalBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        logEvent('scroll_top', currentSemester);
    });
}

// Log device type on load
const deviceType = window.innerWidth <= 768 ? 'mobile' : 'desktop';
logEvent('device_type', null, `${deviceType}_${screen.width}x${screen.height}`);

// Log page exit with duration
const _pageStart = Date.now();
window.addEventListener('beforeunload', () => {
    const duration = Math.round((Date.now() - _pageStart) / 1000);
    logEvent('page_exit', currentSemester, `duration_${duration}s`);
});

window.addEventListener('resize', () => renderTable(currentData));
initApp();
setInterval(() => { if (currentSemester) loadData(currentSemester, true); }, 30000);
