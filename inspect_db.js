import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const inspect = async () => {
  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'ujian_db',
  });

  console.log("--- DAFTAR TABEL ---");
  const [tables] = await connection.query("SHOW TABLES");
  console.log(tables);

  for (const tableObj of tables) {
    const tableName = Object.values(tableObj)[0];
    console.log(`\n--- STRUKTUR TABEL: ${tableName} ---`);
    const [columns] = await connection.query(`DESCRIBE ${tableName}`);
    console.log(columns);
  }

  await connection.end();
};

inspect();
