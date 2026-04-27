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
  
  const [rows] = await db.query("SELECT link_server FROM uts_semester_2 WHERE link_server != 'OFFLINE' LIMIT 1");
  console.log("Example Link Server in DB:", rows[0] ? `'${rows[0].link_server}'` : "No data found");
  
  await db.end();
};
run();
