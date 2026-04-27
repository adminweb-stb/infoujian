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
  
  const [tables] = await db.query("SHOW TABLES");
  console.log("Tables:", tables);
  
  // Try to find a table with 'kelas' in its name
  const kelasTable = tables.find(t => Object.values(t)[0].toLowerCase().includes('kelas'));
  if (kelasTable) {
      const tableName = Object.values(kelasTable)[0];
      const [cols] = await db.query(`DESCRIBE ${tableName}`);
      console.log(`Columns in ${tableName}:`, cols);
      const [data] = await db.query(`SELECT * FROM ${tableName} LIMIT 5`);
      console.log(`Sample data in ${tableName}:`, data);
  } else {
      console.log("No table found with 'kelas' in its name. Looking at all tables...");
  }
  
  await db.end();
};
run();
