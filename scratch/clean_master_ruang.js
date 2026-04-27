import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const rawRooms = `
A - 404
A - 402
B - 401
USTB - 401
USTB - 402
B - 201
A - 404
A - 402
B - 401
USTB - 401
USTB - 402
Lab Kebidanan (Klinik)
A - 404
A - 402
A - 404
A - 402
A - 401
A - 501
A - 404
A - 402
A - 404
A - 402
A - 401
A - 501
A - 404
A - 402
A - 404
A - 402
A - 401
A - 501
A - 404
A - 402
A - 404
A - 402
A - 401
A - 501
B - 202
B - 203
B - 103
USTB - 201
USTB - 202
USTB - 301
Lab Kebidanan (Klinik)
B - 101
B - 101
B - 102
B - 401
USTB - 401
USTB - 402
B - 101
B - 102
A - 404
A - 402
A - 401
A - 501
B - 401
USTB - 401
USTB - 402
B- 403
B- 403
B - 401
USTB - 401
USTB - 402
B - 401
USTB - 401
USTB - 402
B- 403
B- 403
B - 102
A - 404
A - 402
Lab Kebidanan (Kilinik)
A - 101
B - 101
Lab Kebidanan (Klinik)
Lab Kebidanan (Kilinik)
A - 101
Lab Kebidanan (Klinik)
Lab Kebidanan (Kilinik)
A - 101
Lab Kebidanan (Klinik)
Lab Kebidanan (Kilinik)
A - 101
A - 401
A - 501
B - 401
USTB - 401
USTB - 402
B- 403
B- 403
B - 103
B - 302
B - 301
A - 405
A - 406
USTB - 201
USTB - 202
USTB 301
USTB - 302
USTB - 201
USTB - 202
USTB - 301
USTB - 201
USTB - 202
USTB 301
USTB - 302
USTB - 201
USTB - 202
USTB - 301
USTB - 201
USTB - 202
USTB 301
USTB - 302
USTB - 201
USTB - 202
USTB - 301
USTB - 201
USTB - 202
USTB 301
USTB - 302
Lab Kebidanan (Kilinik)
A - 101
B - 101
B - 102
B - 201
B - 202
B - 203
B - 303
B - 102
B - 201
B - 202
B - 203
B - 303
B - 401
USTB - 401
USTB - 402
B- 403
B- 403
B - 101
B - 102
B - 201
B - 202
B - 203
B - 303
B - 101
B - 102
B - 101
B - 102
B - 201
B - 202
B - 203
B - 303
B - 101
B - 102
B - 101
B - 102
B - 201
B - 202
B - 203
B - 303
B - 101
B - 102
B - 101
B - 102
B - 201
B - 202
B - 203
B - 303
B - 101
B - 102
B - 101
B - 102
B - 201
B - 202
B - 203
B - 303
A  - 502
A  - 505
A - 403
USTB - 501
D - 402
D - 403
D - 404
A  - 502
A  - 505
B - 403
B - 501
B - 502
B - 503
A  - 305
A  - 505
C - 106
A - 403
USTB - 501
D - 402
D - 403
C - 305
D - 404
A  - 305
A  - 505
C - 106
B - 501
B - 502
C - 105
B - 503
C - 204
C - 305
C - 304
D - 402
D - 403
D - 404
C - 204
C - 305
C - 304
A - 403
USTB - 501
A - 208
C - 204
A - 207
A - 301
D - 402
D - 403
C - 305
D - 404
A - 208
C - 204
A - 207
A - 301
A - 403
USTB - 501
B - 501
B - 502
C - 105
B - 503
C - 204
C - 305
C - 304
A - 403
USTB - 501
D - 402
D - 403
D - 404
A  - 502
A  - 505
B - 403
B - 501
B - 502
B - 503
D - 402
D - 403
D - 404
A - 208
C - 204
A - 207
A - 301
A - 403
USTB - 501
D - 402
D - 403
C - 305
D - 404
A  - 305
A  - 505
C - 106
D - 402
D - 403
C - 305
D - 404
A  - 502
A  - 505
C - 204
C - 305
C - 304
A - 401
A - 405
A - 406
A  - 502
A  - 505
B - 403
B - 501
B - 502
B - 503
D - 402
D - 403
D - 404
A  - 305
A  - 505
C - 106
A - 208
C - 204
A - 207
A - 301
C - 102
C - 304
C - 104
C - 103
A  - 305
A  - 505
C - 106
B - 501
B - 502
C - 105
B - 503
D - 402
D - 403
C - 305
D - 404
A - 403
USTB - 501
A - 401
A - 405
A - 406
D - 402
D - 403
D - 404
A  - 502
A  - 505
C - 204
C - 305
C - 304
A - 403
USTB - 501
B - 403
B - 501
B - 502
B - 503
A - 401
A - 405
A - 406
D - 402
D - 403
D - 404
A - 403
USTB - 501
C - 102
C - 304
C - 104
C - 103
D - 402
D - 403
C - 305
D - 404
A  - 305
A  - 505
C - 106
A - 208
C - 204
A - 207
A - 301
A - 403
USTB - 501
B - 501
B - 502
C - 105
B - 503
C - 102
C - 304
C - 104
C - 103
D - 402
D - 403
C - 305
A - 504
USTB - 302
A - 204
A - 504
USTB - 302
B - 301
USTB - 502
C - 206
B - 301
USTB - 502
C - 206
A  - 502
A - 205
A - 503
C - 202
B - 301
B - 302
USTB - 502
C - 206
A - 504
USTB - 302
A - 503
C - 202
C - 203
A - 504
USTB - 302
A - 201
C - 202
A - 504
USTB - 302
A - 201
C - 202
B - 302
USTB - 502
C - 206
B - 303
B - 402
A - 501
B - 301
B - 302
A - 201
C - 202
A - 204
A - 205
USTB - 502
C - 203
A - 201
C - 202
C - 206
D - 401
A  - 502
A - 503
C - 202
USTB - 502
C - 203
B - 302
A - 503
C - 202
C - 203
C - 206
C - 206
D - 401
A  - 502
A - 306
A - 503
A - 504
C - 206
D - 401
A - 201
C - 202
USTB - 502
C - 203
B - 301
C - 206
B - 302
D - 401
D - 401
B - 301
B - 302
C - 206
D - 401
D - 401
A  - 502
USTB - 502
C - 203
B - 302
D - 401
D - 401
`;

const run = async () => {
  const db = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'ujian_db'
  });
  
  // Clean and Normalize
  const cleaned = rawRooms.split('\n')
    .map(r => r.trim())
    .filter(r => r.length > 0)
    .map(r => {
        // Replace " - " or "- " or " -" with "-"
        let normalized = r.replace(/\s*-\s*/g, '-');
        // Handle "USTB 301" pattern
        normalized = normalized.replace(/USTB\s+(\d+)/g, 'USTB-$1');
        // Handle typo "Kilinik" -> "Klinik"
        normalized = normalized.replace(/Kilinik/g, 'Klinik');
        return normalized;
    });

  const uniqueRooms = [...new Set(cleaned)].sort();

  console.log("Rooms to insert:", uniqueRooms);

  // Clear master_ruang
  await db.query(`DELETE FROM master_ruang`);
  
  // Insert clean data
  for (const r of uniqueRooms) {
      await db.query(`INSERT INTO master_ruang (nama_ruang) VALUES (?)`, [r]);
  }
  
  console.log(`[OK] master_ruang cleaned. Total unique rooms: ${uniqueRooms.length}`);
  await db.end();
};
run();
