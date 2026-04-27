import express from 'express';
import mysql from 'mysql2/promise';
import cors from 'cors';
import helmet from 'helmet';
import rateLimit from 'express-rate-limit';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { runVisitorLogger, checkBlacklist, addToBlacklist } from './logger.js';

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const app = express();
const PORT = process.env.PORT || 3000;
app.set('trust proxy', 1); // Fix untuk proxy Nginx Biznet
const JWT_SECRET = process.env.JWT_SECRET || 'fallback-secret';

// Database Connection Pool
let db;
const initDB = async () => {
  try {
    db = await mysql.createPool({
      host: process.env.DB_HOST || 'localhost',
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASSWORD || '',
      database: process.env.DB_NAME || 'ujian_db',
      waitForConnections: true,
      connectionLimit: 10,
    });
    console.log("[OK] Connected to MySQL Database");
  } catch (err) {
    console.error("[ERR] Database Connection Failed:", err.message);
  }
};

// --- MIDDLEWARE KEAMANAN ---

/**
 * Middleware untuk memblokir IP yang ada di Blacklist
 */
const blacklistMiddleware = async (req, res, next) => {
  let ip = req.headers['x-forwarded-for'] || req.socket.remoteAddress || '0.0.0.0';
  if (ip.includes(',')) ip = ip.split(',')[0].trim();
  if (ip === '::1') ip = '127.0.0.1';

  const blacklisted = await checkBlacklist(db, ip);
  if (blacklisted) {
    console.warn(`[BLOCKED] Request ditolak dari IP Blacklist: ${ip} | Alasan: ${blacklisted.reason}`);
    return res.status(403).json({ 
      error: "Access Denied", 
      message: "IP Anda telah diblokir karena aktivitas mencurigakan.",
      reason: blacklisted.reason
    });
  }
  next();
};

/**
 * Middleware untuk mendeteksi Intrusion/Scanning secara real-time
 */
const intrusionDetectionMiddleware = async (req, res, next) => {
  const suspiciousPaths = [
    '/wp-admin', '/wp-login', '.php', '.env', '.git', 
    'ALFA_DATA', 'shell', 'cgi-bin', 'config', 'admin'
  ];
  
  const url = req.originalUrl.toLowerCase();
  
  // Kecualikan path resmi kita agar tidak terblokir sendiri
  if (
    url.includes('/api/admin') || 
    url.includes('/api/auth') ||
    url.includes('/api/config') ||
    url.includes('/api/schedules') ||
    url.includes('/api/log') ||
    url.includes('/api/sync') ||
    url.includes('/control-center')
  ) {
    return next();
  }

  const isSuspicious = suspiciousPaths.some(path => url.includes(path.toLowerCase()));

  if (isSuspicious) {
    let ip = req.headers['x-forwarded-for'] || req.socket.remoteAddress || '0.0.0.0';
    if (ip.includes(',')) ip = ip.split(',')[0].trim();
    
    const reason = `Scanning detected: ${req.originalUrl}`;
    await addToBlacklist(db, ip, reason, 'high');
    
    // Log ke visitor_logs juga sebagai alert
    await runVisitorLogger(db, req, { action: 'security_alert', context: reason });

    return res.status(403).json({ error: "Intrusion detected and IP blacklisted." });
  }
  next();
};

/**
 * Middleware untuk memproteksi API Admin dengan JWT
 */
const authenticateToken = (req, res, next) => {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];

  if (!token) return res.sendStatus(401);

  jwt.verify(token, JWT_SECRET, (err, user) => {
    if (err) return res.sendStatus(403);
    req.user = user;
    next();
  });
};

const authorizeSuperadmin = (req, res, next) => {
  if (req.user && req.user.role === 'superadmin') {
    next();
  } else {
    res.status(403).json({ error: 'Require Super Admin role' });
  }
};

// Apply Global Security
app.use(helmet({ contentSecurityPolicy: false }));
app.use(cors());
app.use(express.json());
app.use(blacklistMiddleware);
app.use(intrusionDetectionMiddleware);

// Rate Limiting (Mencegah Brute Force / DDoS ringan)
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 150, 
  message: "Terlalu banyak permintaan dari IP ini. Silakan coba lagi nanti."
});
app.use('/api/', limiter);

// --- DATA LOGIC (DB DRIVEN) ---

let scheduleCache = new Map();

/**
 * Sinkronisasi data dari DB ke Memory Cache agar sangat cepat
 */
const syncSchedulesFromDB = async () => {
  try {
    console.log("Syncing schedules from Database to Memory Cache...");
    const tablesToSync = [
      'semester_2', 'semester_4', 'semester_6',
      'uts_semester_2', 'uts_semester_4', 'uts_semester_6'
    ];

    // Get list of actual tables in DB
    const [existingTablesRaw] = await db.query("SHOW TABLES");
    const existingTables = existingTablesRaw.map(t => Object.values(t)[0]);

    for (const table of tablesToSync) {
      if (!existingTables.includes(table)) {
        console.log(`[SKIP] Table ${table} not found, skipping sync.`);
        continue;
      }
      
      // Sort by Date then Session
      const [rows] = await db.query(`SELECT * FROM ${table} ORDER BY STR_TO_DATE(tanggal, "%d/%m/%Y") ASC, sesi ASC`);
      
      let finalKey = table.includes('uts') 
        ? `uts_semester-${table.split('_').pop()}.json`
        : `semester-${table.split('_').pop()}.json`;
      
      scheduleCache.set(finalKey, rows);
    }
    
    console.log(`[OK] Cached data from ${scheduleCache.size} tables.`);
  } catch (err) {
    console.error("[ERR] Sync Failed:", err.message);
  }
};

// --- API ROUTES ---

// 1. Config Route (Bisa diubah jadi baca DB tabel config jika ada)
app.get('/api/config', (req, res) => {
  // Default config, bisa dipindah ke DB nanti
  const config = { 
    active_exam: 'uts', 
    active_period: 'genap', 
    max_semester: 6,
    active_exam_label: "Jadwal UTS Semester Genap 2026" 
  };
  res.json(config);
});

// 2. Schedules Route (Serve from Cache)
app.get('/api/schedules', (req, res) => {
  const { exam, semester } = req.query;
  const key = `${exam}_semester-${semester}.json`;
  const data = scheduleCache.get(key);
  
  if (data) {
    res.json(data);
  } else {
    res.status(404).json({ error: "Data jadwal tidak ditemukan di database." });
  }
});

// 3. Logger Route
app.post('/api/log', async (req, res) => {
  console.log(`[LOG] Incoming activity: ${req.body.action} from ${req.ip}`);
  await runVisitorLogger(db, req, req.body);
  res.json({ status: 'logged' });
});

// --- ADMIN & AUTH ROUTES ---

// Login Admin
app.post('/api/auth/login', async (req, res) => {
  const { username, password } = req.body;
  try {
    const [rows] = await db.query('SELECT * FROM users WHERE username = ?', [username]);
    const user = rows[0];

    if (user && await bcrypt.compare(password, user.password)) {
      const token = jwt.sign({ id: user.id, username: user.username, role: user.role }, JWT_SECRET, { expiresIn: '8h' });
      res.json({ token, role: user.role });
    } else {
      res.status(401).json({ error: "Invalid credentials" });
    }
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Dashboard Stats (Protected)
app.get('/api/admin/stats', authenticateToken, async (req, res) => {
  try {
    const [rowsVisitors] = await db.query('SELECT COUNT(*) as total FROM visitor_logs');
    const [rowsBlacklisted] = await db.query('SELECT COUNT(*) as total FROM blacklist_ips');
    const [rowsIntrusions] = await db.query('SELECT COUNT(*) as total FROM visitor_logs WHERE action = "security_alert"');
    const [recent_logs] = await db.query('SELECT * FROM visitor_logs ORDER BY created_at DESC LIMIT 15');

    res.json({
      total_visitors: rowsVisitors[0].total,
      total_blacklisted: rowsBlacklisted[0].total,
      total_intrusions: rowsIntrusions[0].total,
      recent_logs
    });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Full Logs API — sorted newest first
app.get('/api/admin/logs', authenticateToken, async (req, res) => {
  try {
    const { limit = 200, action } = req.query;
    let query = 'SELECT * FROM visitor_logs';
    const params = [];
    
    if (action) {
      query += ' WHERE action = ?';
      params.push(action);
    }
    
    query += ' ORDER BY created_at DESC LIMIT ?';
    params.push(parseInt(limit));
    
    const [rows] = await db.query(query, params);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Delete Single Log
app.delete('/api/admin/logs/:id', authenticateToken, authorizeSuperadmin, async (req, res) => {
  try {
    console.log(`[ADMIN] Deleting log ID: ${req.params.id} by ${req.user.username}`);
    await db.query('DELETE FROM visitor_logs WHERE id = ?', [req.params.id]);
    res.json({ message: 'Log deleted' });
  } catch (err) {
    console.error(`[ERR] Delete log failed: ${err.message}`);
    res.status(500).json({ error: err.message });
  }
});

// Clear All Logs
app.delete('/api/admin/logs-clear-all', authenticateToken, authorizeSuperadmin, async (req, res) => {
  try {
    console.log(`[ADMIN] CLEARING ALL LOGS by ${req.user.username}`);
    await db.query('TRUNCATE TABLE visitor_logs');
    res.json({ message: 'All logs cleared' });
  } catch (err) {
    console.error(`[ERR] Clear all logs failed: ${err.message}`);
    res.status(500).json({ error: err.message });
  }
});

// User Management APIs
app.get('/api/admin/users', authenticateToken, authorizeSuperadmin, async (req, res) => {
  try {
    const [rows] = await db.query('SELECT id, username, role, created_at FROM users');
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.post('/api/admin/users', authenticateToken, authorizeSuperadmin, async (req, res) => {
  const { username, password, role } = req.body;
  try {
    const hashedPassword = await bcrypt.hash(password, 10);
    await db.query('INSERT INTO users (username, password, role) VALUES (?, ?, ?)', [username, hashedPassword, role || 'admin']);
    res.json({ message: 'User added successfully' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.delete('/api/admin/users/:id', authenticateToken, authorizeSuperadmin, async (req, res) => {
  try {
    await db.query('DELETE FROM users WHERE id = ?', [req.params.id]);
    res.json({ message: 'User deleted' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Class Management APIs
app.get('/api/admin/kelas', authenticateToken, async (req, res) => {
  try {
    const [rows] = await db.query('SELECT nama_kelas, prodi FROM master_kelas ORDER BY nama_kelas ASC');
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 4. Force Sync Route (Protected)
app.get('/api/sync', authenticateToken, async (req, res) => {
  await syncSchedulesFromDB();
  res.json({ status: 'sync_completed', timestamp: new Date() });
});

// 4.5. Get Available Tables (Protected)
app.get('/api/admin/tables', authenticateToken, async (req, res) => {
  try {
    const tablesToSync = [
      'semester_2', 'semester_4', 'semester_6',
      'uts_semester_2', 'uts_semester_4', 'uts_semester_6'
    ];
    const [existingTablesRaw] = await db.query("SHOW TABLES");
    const existingTables = existingTablesRaw.map(t => Object.values(t)[0]);
    
    const available = tablesToSync.filter(t => existingTables.includes(t));
    res.json(available);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 5. Blacklist Info (Protected)
app.get('/api/admin/blacklist', authenticateToken, async (req, res) => {
  const [rows] = await db.query('SELECT * FROM blacklist_ips ORDER BY created_at DESC');
  res.json(rows);
});

// Helper to validate table name
const isValidTable = (table) => {
  const allowed = ['semester_2', 'semester_4', 'semester_6', 'uts_semester_2', 'uts_semester_4', 'uts_semester_6'];
  return allowed.includes(table);
};

// --- SCHEDULE MANAGEMENT CRUD ---

// Get Schedules by Table
app.get('/api/admin/schedules/:table', authenticateToken, async (req, res) => {
  const { table } = req.params;
  if (!isValidTable(table)) return res.status(400).json({ error: "Invalid table name" });
  try {
    const [rows] = await db.query(`SELECT * FROM ?? ORDER BY STR_TO_DATE(tanggal, "%d/%m/%Y") ASC, sesi ASC`, [table]);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Add New Schedule
app.post('/api/admin/schedules/:table', authenticateToken, async (req, res) => {
  const { table } = req.params;
  if (!isValidTable(table)) return res.status(400).json({ error: "Invalid table name" });
  const { hari, tanggal, sesi, matkul, prodi, jam, kelas, ruang, dosen, link_server } = req.body;
  try {
    // Auto-learn new rooms
    if (ruang) await db.query('INSERT IGNORE INTO master_ruang (nama_ruang) VALUES (?)', [ruang]);
    
    const query = `INSERT INTO ?? (hari, tanggal, sesi, matkul, prodi, jam, kelas, ruang, dosen, link_server) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
    await db.query(query, [table, hari, tanggal, sesi, matkul, prodi, jam, kelas, ruang, dosen, link_server]);
    
    // Refresh Cache
    await syncSchedulesFromDB();

    res.json({ status: 'success', message: 'Jadwal berhasil ditambahkan' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Update Schedule
app.put('/api/admin/schedules/:table/:id', authenticateToken, async (req, res) => {
  const { table, id } = req.params;
  if (!isValidTable(table)) return res.status(400).json({ error: "Invalid table name" });
  const { hari, tanggal, sesi, matkul, prodi, jam, kelas, ruang, dosen, link_server } = req.body;
  try {
    // Auto-learn new rooms
    if (ruang) await db.query('INSERT IGNORE INTO master_ruang (nama_ruang) VALUES (?)', [ruang]);

    const query = `UPDATE ?? SET hari=?, tanggal=?, sesi=?, matkul=?, prodi=?, jam=?, kelas=?, ruang=?, dosen=?, link_server=? WHERE id=?`;
    await db.query(query, [table, hari, tanggal, sesi, matkul, prodi, jam, kelas, ruang, dosen, link_server, id]);
    
    // Refresh Cache
    await syncSchedulesFromDB();

    res.json({ status: 'success', message: 'Jadwal berhasil diperbarui' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Get Unique Rooms for Autocomplete
app.get('/api/admin/rooms', authenticateToken, async (req, res) => {
  try {
    const [rows] = await db.query(`SELECT nama_ruang as ruang FROM master_ruang ORDER BY nama_ruang ASC`);
    res.json(rows.map(r => r.ruang));
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Delete Schedule
app.delete('/api/admin/schedules/:table/:id', authenticateToken, async (req, res) => {
  const { table, id } = req.params;
  if (!isValidTable(table)) return res.status(400).json({ error: "Invalid table name" });
  try {
    await db.query(`DELETE FROM ?? WHERE id = ?`, [table, id]);
    
    // Refresh Cache
    await syncSchedulesFromDB();

    res.json({ status: 'success', message: 'Jadwal berhasil dihapus' });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// Static Files
app.use('/control-center', express.static(path.join(__dirname, 'control-center')));
if (process.env.NODE_ENV === 'production') {
  app.use(express.static(path.join(__dirname, 'dist')));
  app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, 'dist', 'index.html'));
  });
} else {
  // Local fallback
  app.get('/', (req, res) => {
    res.send("Server is running. Access via Vite on port 5173.");
  });
}

// Bootstrap
const start = async () => {
  await initDB();
  await syncSchedulesFromDB();
  app.listen(PORT, () => {
    console.log(`\n=========================================`);
    console.log(`SERVER UJIAN TERPROTEKSI AKTIF`);
    console.log(`Port: ${PORT}`);
    console.log(`Keamanan: Blacklist & Intrusion Detection Aktif`);
    console.log(`=========================================\n`);
  });
};

start();
