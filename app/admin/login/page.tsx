import { redirect } from 'next/navigation';
import { cookies } from 'next/headers';
import bcrypt from 'bcryptjs';
import { SignJWT } from 'jose';
import { supabase } from '@/lib/supabase';
import { getSession, COOKIE } from '@/lib/auth';
import LoginForm from '@/components/admin/LoginForm';

function normalizeHash(h: string): string {
  return h.replace(/^\$2y\$/, '$2a$');
}

/** Strip characters that would break a PostgREST `or` filter expression. */
function orSafe(value: string): string {
  return value.replace(/[(),.\\"]/g, '');
}

interface AdminUserRow {
  id: number;
  name: string;
  email: string;
  password_hash: string;
  status: string;
  roles: { name: string } | { name: string }[] | null;
}

export const metadata = {
  title: 'Staff Sign In',
};

export default async function AdminLoginPage({
  searchParams,
}: {
  searchParams?: Promise<{ from?: string }>;
}) {
  const session = await getSession();
  if (session && ['SUPER_ADMIN', 'STAFF'].includes(session.role)) {
    redirect('/admin/dashboard');
  }

  const params = await searchParams;

  const themeInit = `try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}`;

  async function loginAction(formData: FormData) {
    'use server';
    const identifier = String(formData.get('identifier') ?? '');
    const password = String(formData.get('password') ?? '');
    const rawFrom = String(formData.get('from') ?? '/admin/dashboard');
    const from = rawFrom.startsWith('/admin/') ? rawFrom : '/admin/dashboard';

    let user: AdminUserRow | null = null;
    const safeIdentifier = orSafe(identifier);
    if (safeIdentifier) {
      const { data, error } = await supabase
        .from('users')
        .select('id, name, email, password_hash, status, roles(name)')
        .or(`email.eq."${safeIdentifier}",username.eq."${safeIdentifier}"`)
        .limit(1)
        .maybeSingle();
      if (error) throw error;
      user = data as unknown as AdminUserRow | null;
    }

    const ok =
      !!user &&
      user.status === 'active' &&
      bcrypt.compareSync(password, normalizeHash(user.password_hash));

    const { error: attemptError } = await supabase
      .from('login_attempts')
      .insert({ email: identifier, ip: '', successful: ok });
    if (attemptError) throw attemptError;

    const rolesData = user?.roles;
    const role = Array.isArray(rolesData) ? rolesData[0]?.name : rolesData?.name;

    if (!ok || !user || !['SUPER_ADMIN', 'STAFF'].includes(role ?? '')) {
      redirect('/admin/login?error=1');
    }

    const { error: updateError } = await supabase
      .from('users')
      .update({ last_login_at: new Date().toISOString() })
      .eq('id', user.id);
    if (updateError) throw updateError;

    const token = await new SignJWT({ id: user.id, name: user.name, email: user.email, role })
      .setProtectedHeader({ alg: 'HS256' })
      .setIssuedAt()
      .setExpirationTime('7d')
      .sign(new TextEncoder().encode(process.env.SESSION_SECRET || 'dev-secret-change-me'));

    const jar = await cookies();
    jar.set(COOKIE, token, { httpOnly: true, sameSite: 'lax', path: '/', maxAge: 7 * 86400 });

    redirect(from);
  }

  return (
    <>
      <link href="/assets/admin/css/font-face.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/vendor/bootstrap-5.3.8.min.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/css/theme.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/css/app.css" rel="stylesheet" precedence="default" />
      <script src="/assets/vendor/lucide.min.js" defer></script>
      <script dangerouslySetInnerHTML={{ __html: themeInit }} />
      <script dangerouslySetInnerHTML={{ __html: "document.body.className = 'app auth-page';" }} />
      <main className="login-wrap">
        <div className="login-content">
          <a href="/" className="auth-brand">
            <span className="logo-mark">K</span>
            <span className="logo-text">KaRent</span>
          </a>
          <h1 className="auth-title">Staff sign in</h1>
          <p className="auth-subtitle">
            Back-office access &mdash; clients should use the{' '}
            <a href="/login">client sign-in page</a>.
          </p>

          <LoginForm action={loginAction} from={params?.from} />
        </div>
      </main>
    </>
  );
}
