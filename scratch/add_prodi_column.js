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
  
  const tables = ['uts_semester_2', 'uts_semester_4', 'uts_semester_6', 'semester_2', 'semester_4', 'semester_6'];
  const prodiCodes = ['IF', 'AB', 'MH', 'KB', 'BI', 'KW'];
  
  for (const table of tables) {
      try {
          console.log(`Processing table: ${table}`);
          // 1. Add prodi column if not exists
          await db.query(`ALTER TABLE ${table} ADD COLUMN IF NOT EXISTS prodi VARCHAR(10) AFTER matkul`);
          
          // 2. Populate prodi based on kelas prefix
          for (const code of prodiCodes) {
              await db.query(`UPDATE ${table} SET prodi = ? WHERE kelas LIKE ?`, [code, `${code}%`]);
          }
          console.log(`[OK] Table ${table} updated.`);
      } catch(e) {
          console.error(`[SKIP] Table ${table} error:`, e.message);
      }
  }
  
  await db.end();
  console.log("Database Migration Completed Successfully.");
};
run();
