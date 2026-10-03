import { redirect } from 'next/navigation';
import { cookies } from 'next/headers';
import bcrypt from 'bcryptjs';
import { SignJWT } from 'jose';
import { one, run } from '@/lib/db';
import { getSession, COOKIE } from '@/lib/auth';
import LoginForm from '@/components/admin/LoginForm';

function normalizeHash(h: string): string {
  return h.replace(/^\$2y\$/, '$2a$');
}

export default async function AdminLoginPage({ searchParams }: { searchParams?: Promise<{ from?: string }> }) {
  const session = await getSession();
  if (session && ['SUPER_ADMIN', 'STAFF'].includes(session.role)) {
    redirect('/admin/dashboard');
  }

  const params = await searchParams;

  async function loginAction(formData: FormData) {
    'use server';
    const identifier = String(formData.get('identifier') ?? '');
    const password = String(formData.get('password') ?? '');
    const rawFrom = String(formData.get('from') ?? '/admin/dashboard');
    const from = rawFrom.startsWith('/admin/') ? rawFrom : '/admin/dashboard';

    const user = await one<any>(
      `SELECT u.id, u.name, u.email, u.password_hash, u.status, r.name AS role
       FROM users u JOIN roles r ON r.id = u.role_id
       WHERE (u.email = ? OR u.username = ?) LIMIT 1`,
      [identifier, identifier]
    );

    const ok = user && user.status === 'active' && bcrypt.compareSync(password, normalizeHash(user.password_hash));
    await run('INSERT INTO login_attempts (email, ip, successful) VALUES (?, ?, ?)', [identifier, '', ok ? 1 : 0]);

    if (!ok || !['SUPER_ADMIN', 'STAFF'].includes(user.role)) {
      redirect('/admin/login?error=1');
    }

    await run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [user.id]);

    const token = await new SignJWT({ id: user.id, name: user.name, email: user.email, role: user.role })
      .setProtectedHeader({ alg: 'HS256' })
      .setIssuedAt()
      .setExpirationTime('7d')
      .sign(new TextEncoder().encode(process.env.SESSION_SECRET || 'dev-secret-change-me'));

    const jar = await cookies();
    jar.set(COOKIE, token, { httpOnly: true, sameSite: 'lax', path: '/', maxAge: 7 * 86400 });

    redirect(from);
  }

  return (
    <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
      <LoginForm action={loginAction} from={params?.from} />
    </div>
  );
}
