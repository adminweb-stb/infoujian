import mysql from 'mysql2/promise';
import fs from 'fs';
import path from 'path';
import dotenv from 'dotenv';
dotenv.config();

const importBackup = async () => {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    multipleStatements: true
  });

  const dbName = process.env.DB_NAME || 'ujian_db';
  await connection.query(`CREATE DATABASE IF NOT EXISTS \`${dbName}\``);
  await connection.query(`USE \`${dbName}\``);

  const backupDir = 'backups';
  const files = fs.readdirSync(backupDir).filter(f => f.endsWith('.sql')).sort().reverse();
  
  if (files.length === 0) {
    console.error("No backup files found!");
    process.exit(1);
  }

  const latestFile = path.join(backupDir, files[0]);
  console.log(`Importing ${latestFile}...`);

  const sql = fs.readFileSync(latestFile, 'utf8');
  
  try {
    await connection.query(sql);
    console.log("[OK] Import successful!");
  } catch (err) {
    console.error("[ERR] Import failed:", err.message);
  }

  await connection.end();
};

importBackup();
