import { SignJWT, jwtVerify } from 'jose';
import bcrypt from 'bcryptjs';
import { cookies } from 'next/headers';
import { one, run } from './db';

export const COOKIE = 'karent_session';
const secret = () => new TextEncoder().encode(process.env.SESSION_SECRET || 'dev-secret-change-me');

export type RoleCode = 'SUPER_ADMIN' | 'STAFF' | 'CLIENT';

export interface SessionUser {
  id: number;
  name: string;
  email: string;
  role: RoleCode;
  clientId?: number;
}

/** Map php password_hash ($2y$) to bcryptjs ($2a$). */
function normalizeHash(h: string): string {
  return h.replace(/^\$2y\$/, '$2a$');
}

export async function attemptLogin(identifier: string, password: string, ip = ''): Promise<SessionUser | null> {
  const user = await one<any>(
    `SELECT u.id, u.name, u.email, u.password_hash, u.status, r.name AS role
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.email = ? LIMIT 1`,
    [identifier]
  );
  const ok = user && user.status === 'active' && bcrypt.compareSync(password, normalizeHash(user.password_hash));
  await run('INSERT INTO login_attempts (email, ip, successful) VALUES (?, ?, ?)', [identifier, ip, ok ? 1 : 0]);
  if (!ok) return null;

  await run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [user.id]);
  const sess: SessionUser = { id: user.id, name: user.name, email: user.email, role: user.role };
  if (user.role === 'CLIENT') {
    const c = await one<any>('SELECT id FROM clients WHERE user_id = ?', [user.id]);
    if (c) sess.clientId = c.id;
  }
  const token = await new SignJWT(sess as any)
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime('7d')
    .sign(secret());
  const jar = await cookies();
  jar.set(COOKIE, token, { httpOnly: true, sameSite: 'lax', path: '/', maxAge: 7 * 86400 });
  return sess;
}

export async function logout() {
  const jar = await cookies();
  jar.delete(COOKIE);
}

export async function getSession(): Promise<SessionUser | null> {
  const jar = await cookies();
  const token = jar.get(COOKIE)?.value;
  if (!token) return null;
  try {
    const { payload } = await jwtVerify(token, secret());
    return payload as unknown as SessionUser;
  } catch {
    return null;
  }
}

export async function verifyToken(token: string): Promise<SessionUser | null> {
  try {
    const { payload } = await jwtVerify(token, secret());
    return payload as unknown as SessionUser;
  } catch {
    return null;
  }
}

export async function requireRole(...roles: RoleCode[]): Promise<SessionUser> {
  const s = await getSession();
  if (!s || !roles.includes(s.role)) throw new Error('unauthorized');
  return s;
}

export function hashPassword(pw: string): string {
  return bcrypt.hashSync(pw, 10);
}
