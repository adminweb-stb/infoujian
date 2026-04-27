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
  
  // 1. Create master_kelas table
  await db.query(`CREATE TABLE IF NOT EXISTS master_kelas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nama_kelas VARCHAR(50) UNIQUE NOT NULL
  )`);
  
  // 2. Extract unique classes from all schedule tables
  const tables = ['uts_semester_2', 'uts_semester_4', 'uts_semester_6'];
  let allClassNames = new Set();
  
  for (const table of tables) {
      try {
          const [rows] = await db.query(`SELECT DISTINCT kelas FROM ${table}`);
          rows.forEach(r => { if(r.kelas) allClassNames.add(r.kelas.trim()) });
      } catch(e) {}
  }
  
  // 3. Insert into master_kelas
  for (const className of allClassNames) {
      try {
          await db.query(`INSERT IGNORE INTO master_kelas (nama_kelas) VALUES (?)`, [className]);
      } catch(e) {}
  }
  
  console.log(`[OK] master_kelas created with ${allClassNames.size} unique classes.`);
  await db.end();
};
run();
