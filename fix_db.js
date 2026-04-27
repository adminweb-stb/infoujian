import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const fix = async () => {
  const db = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'ujian_db'
  });

  console.log("Membuat ulang tabel blacklist_ips...");
  await db.query(`
    CREATE TABLE IF NOT EXISTS blacklist_ips (
      id INT AUTO_INCREMENT PRIMARY KEY, 
      ip_address VARCHAR(50) UNIQUE, 
      reason TEXT, 
      country VARCHAR(100), 
      city VARCHAR(100), 
      isp VARCHAR(100), 
      threat_level ENUM('low', 'medium', 'high') DEFAULT 'medium', 
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
  `);

  console.log("Membuat ulang tabel users (jika hilang)...");
  await db.query(`
    CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY, 
      username VARCHAR(50) UNIQUE, 
      password VARCHAR(255), 
      role VARCHAR(20) DEFAULT 'admin', 
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
  `);

  console.log("[OK] Semua tabel penting sudah siap!");
  await db.end();
};

fix();
