import { SignJWT, jwtVerify } from 'jose';
import bcrypt from 'bcryptjs';
import { cookies } from 'next/headers';
import { supabase } from './supabase';

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

function normalizeHash(h: string): string {
  return h.replace(/^\$2y\$/, '$2a$');
}

export async function attemptLogin(identifier: string, password: string, ip = ''): Promise<SessionUser | null> {
  const { data: user, error: userError } = await supabase
    .from('users')
    .select('id, name, email, password_hash, status, roles(name)')
    .eq('email', identifier)
    .maybeSingle();

  if (userError) throw userError;
  const ok = Boolean(user && user.status === 'active' && bcrypt.compareSync(password, normalizeHash(user.password_hash)));
  const { error: attemptError } = await supabase
    .from('login_attempts')
    .insert({ email: identifier, ip, successful: ok });
  if (attemptError) throw attemptError;

  if (!ok || !user) return null;

  await supabase.from('users').update({ last_login_at: new Date().toISOString() }).eq('id', user.id);

  const rolesData = (user as any).roles;
  const roleName = Array.isArray(rolesData) ? rolesData[0]?.name : rolesData?.name;

  const sess: SessionUser = {
    id: user.id,
    name: user.name,
    email: user.email,
    role: roleName as RoleCode,
  };

  if (sess.role === 'CLIENT') {
    const { data: client } = await supabase.from('clients').select('id').eq('user_id', user.id).single();
    if (client) sess.clientId = client.id;
  }

  const token = await new SignJWT(sess as any)
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime('7d')
    .sign(secret());

  const jar = await cookies();
  jar.set(COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 7 * 86400,
    secure: process.env.NODE_ENV === 'production',
  });

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
