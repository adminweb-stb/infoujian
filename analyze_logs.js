import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const analyze = async () => {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'ujian_db',
  });

  console.log("=== 1. DETEKSI IP DENGAN REQUEST TERTINGGI (SCANNING) ===");
  const [topIps] = await connection.query(`
    SELECT ip_address, COUNT(*) as total_requests, MIN(created_at) as start_time, MAX(created_at) as end_time 
    FROM visitor_logs 
    GROUP BY ip_address 
    HAVING total_requests > 50 
    ORDER BY total_requests DESC 
    LIMIT 10
  `);
  console.table(topIps);

  console.log("\n=== 2. DETEKSI POTENSI SQL INJECTION / XSS (SUSPICIOUS KEYWORDS) ===");
  const [suspicious] = await connection.query(`
    SELECT ip_address, user_agent, context, created_at 
    FROM visitor_logs 
    WHERE context LIKE '%OR 1=1%' 
       OR context LIKE '%<script>%' 
       OR context LIKE '%UNION SELECT%'
       OR context LIKE '%"%'
       OR context LIKE "%'%"
       OR user_agent LIKE '%sqlmap%'
       OR user_agent LIKE '%nmap%'
    LIMIT 20
  `);
  console.table(suspicious);

  console.log("\n=== 3. RINGKASAN TRAFIK BOT ===");
  const [botStats] = await connection.query(`
    SELECT is_bot, COUNT(*) as total 
    FROM visitor_logs 
    GROUP BY is_bot
  `);
  console.table(botStats);

  console.log("\n=== 4. LOG TERBARU DARI IP MENCURIGAKAN ===");
  if (topIps.length > 0) {
    const targetIp = topIps[0].ip_address;
    const [recentLogs] = await connection.query(`
      SELECT action, context, created_at 
      FROM visitor_logs 
      WHERE ip_address = ? 
      ORDER BY created_at DESC 
      LIMIT 10
    `, [targetIp]);
    console.log(`Log terbaru untuk IP: ${targetIp}`);
    console.table(recentLogs);
  }

  await connection.end();
};

analyze();
