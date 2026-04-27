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
  
  const tables = ['uts_semester_2', 'uts_semester_4', 'uts_semester_6'];
  
  for (const table of tables) {
      try {
          console.log(`Adding 'ruang' column to table: ${table}`);
          await db.query(`ALTER TABLE ${table} ADD COLUMN IF NOT EXISTS ruang VARCHAR(50) AFTER kelas`);
          console.log(`[OK] Table ${table} updated.`);
      } catch(e) {
          console.error(`[ERR] Table ${table} error:`, e.message);
      }
  }
  
  await db.end();
  console.log("Room Column Migration Completed.");
};
run();
