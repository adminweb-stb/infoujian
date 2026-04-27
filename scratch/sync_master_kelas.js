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
  
  // 1. Add prodi column to master_kelas
  await db.query(`ALTER TABLE master_kelas ADD COLUMN IF NOT EXISTS prodi VARCHAR(10)`);
  
  // 2. Mapping Logic
  const mappings = [
    { codes: ['I'], prodi: 'IF' }, // Informatika (IF, IAD, IKM)
    { codes: ['M'], prodi: 'MH' }, // Manajemen Hutan (MH, MKH, MMH)
    { codes: ['KW', 'KPP'], prodi: 'KW' }, // Kewirausahaan
    { codes: ['KB'], prodi: 'KB' }, // Kebidanan
    { codes: ['AB'], prodi: 'AB' }, // Agribisnis
    { codes: ['BI'], prodi: 'BI' }  // Bisnis Digital
  ];
  
  for (const m of mappings) {
      for (const code of m.codes) {
          await db.query(`UPDATE master_kelas SET prodi = ? WHERE nama_kelas LIKE ?`, [m.prodi, `${code}%`]);
      }
  }
  
  console.log("[OK] master_kelas prodi mapping completed.");
  await db.end();
};
run();
