import mysql from 'mysql2/promise';

declare global {
  // eslint-disable-next-line no-var
  var __karentPool: mysql.Pool | undefined;
}

export function pool(): mysql.Pool {
  if (!global.__karentPool) {
    global.__karentPool = mysql.createPool({
      host: process.env.DB_HOST || '127.0.0.1',
      port: Number(process.env.DB_PORT || 3307),
      database: process.env.DB_NAME || 'vehicle_hire',
      user: process.env.DB_USER || 'root',
      password: process.env.DB_PASS || '',
      connectionLimit: 10,
      namedPlaceholders: false,
      dateStrings: true,
    });
  }
  return global.__karentPool;
}

export async function query<T = any>(sql: string, params: any[] = []): Promise<T[]> {
  const [rows] = await pool().execute(sql, params);
  return rows as T[];
}

export async function one<T = any>(sql: string, params: any[] = []): Promise<T | null> {
  const rows = await query<T>(sql, params);
  return rows[0] ?? null;
}

export async function run(sql: string, params: any[] = []): Promise<mysql.ResultSetHeader> {
  const [res] = await pool().execute(sql, params);
  return res as mysql.ResultSetHeader;
}
