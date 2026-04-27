import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const run = async () => {
  const db = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'ujian_db'
  });
  
  // 1. Create master_ruang
  await db.query(`CREATE TABLE IF NOT EXISTS master_ruang (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nama_ruang VARCHAR(50) UNIQUE NOT NULL
  )`);
  
  // 2. Insert rooms
  const rooms = ['USTB 501', 'USTB 502', 'USTB 401', 'USTB 402'];
  for (const r of rooms) {
      try {
          await db.query(`INSERT IGNORE INTO master_ruang (nama_ruang) VALUES (?)`, [r]);
      } catch(e) {}
  }
  
  console.log(`[OK] master_ruang created with ${rooms.length} rooms.`);
  await db.end();
};
run();
