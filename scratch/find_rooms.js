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
  for (let t of tables) {
      const name = Object.values(t)[0];
      try {
          const [cols] = await db.query(`DESCRIBE ${name}`);
          if (cols.some(c => c.Field.toLowerCase() === 'ruang')) {
              const [data] = await db.query(`SELECT DISTINCT ruang FROM ${name} WHERE ruang IS NOT NULL AND ruang != ""`);
              if (data.length > 0) {
                  console.log(`Table: ${name} | Found ${data.length} unique rooms:`, data.map(d => d.ruang));
              }
          }
      } catch (e) {
          // Skip if error
      }
  }
  
  await db.end();
};
run();
