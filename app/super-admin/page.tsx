import { redirect } from 'next/navigation';
import { cookies } from 'next/headers';
import bcrypt from 'bcryptjs';
import { SignJWT } from 'jose';
import { supabase } from '@/lib/supabase';
import { getSession, COOKIE } from '@/lib/auth';
import SuperAdminForm from '@/components/admin/SuperAdminForm';

export const metadata = {
  title: 'Super Admin Setup',
};

export default async function SuperAdminPage({
  searchParams,
}: {
  searchParams?: Promise<{ error?: string }>;
}) {
  const session = await getSession();
  if (session?.role === 'SUPER_ADMIN') redirect('/admin/dashboard');

  // Setup page is only usable until the first SUPER_ADMIN exists.
  const { data: saRole } = await supabase
    .from('roles')
    .select('id')
    .eq('name', 'SUPER_ADMIN')
    .maybeSingle();
  if (saRole) {
    const { count } = await supabase
      .from('users')
      .select('id', { count: 'exact', head: true })
      .eq('role_id', saRole.id);
    if ((count ?? 0) > 0) redirect('/admin/login');
  }

  const params = await searchParams;
  const err = params?.error;

  const themeInit = `try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}`;

  async function createAction(formData: FormData) {
    'use server';
    const name = String(formData.get('name') ?? '').trim();
    const email = String(formData.get('email') ?? '').trim().toLowerCase();
    const password = String(formData.get('password') ?? '');
    const confirm = String(formData.get('confirm') ?? '');

    if (!name || !email || password.length < 8 || password !== confirm) {
      redirect('/super-admin?error=' + encodeURIComponent(
        !name || !email
          ? 'Name and email are required.'
          : password.length < 8
            ? 'Password must be at least 8 characters.'
            : 'Passwords do not match.'
      ));
    }

    const { data: existing } = await supabase
      .from('users')
      .select('id')
      .eq('email', email)
      .maybeSingle();
    if (existing) redirect('/super-admin?error=' + encodeURIComponent('That email is already registered.'));

    const { data: role } = await supabase
      .from('roles')
      .select('id')
      .eq('name', 'SUPER_ADMIN')
      .single();
    if (!role) redirect('/super-admin?error=' + encodeURIComponent('SUPER_ADMIN role missing — run migrations/seed first.'));

    const { data: user, error } = await supabase
      .from('users')
      .insert({
        role_id: role.id,
        name,
        email,
        password_hash: bcrypt.hashSync(password, 10),
        status: 'active',
      })
      .select('id, name, email')
      .single();
    if (error || !user) redirect('/super-admin?error=' + encodeURIComponent(error?.message ?? 'Could not create account.'));

    const token = await new SignJWT({ id: user.id, name: user.name, email: user.email, role: 'SUPER_ADMIN' })
      .setProtectedHeader({ alg: 'HS256' })
      .setIssuedAt()
      .setExpirationTime('7d')
      .sign(new TextEncoder().encode(process.env.SESSION_SECRET || 'dev-secret-change-me'));

    const jar = await cookies();
    jar.set(COOKIE, token, { httpOnly: true, sameSite: 'lax', path: '/', maxAge: 7 * 86400 });

    redirect('/admin/dashboard');
  }

  return (
    <>
      <link href="/assets/admin/css/font-face.css" rel="stylesheet" />
      <link href="/assets/admin/vendor/bootstrap-5.3.8.min.css" rel="stylesheet" />
      <link href="/assets/admin/css/theme.css" rel="stylesheet" />
      <link href="/assets/admin/css/app.css" rel="stylesheet" />
      <script src="/assets/vendor/lucide.min.js" defer></script>
      <script dangerouslySetInnerHTML={{ __html: themeInit }} />
      <script dangerouslySetInnerHTML={{ __html: "document.body.className = 'app auth-page';" }} />
      <main className="login-wrap">
        <div className="login-content">
          <a href="/" className="auth-brand">
            <span className="logo-mark">K</span>
            <span className="logo-text">KaRent</span>
          </a>
          <h1 className="auth-title">Super Admin</h1>
          <p className="auth-subtitle">
            Create a super-administrator account. Already have one?{' '}
            <a href="/admin/login">Sign in</a>.
          </p>
          <SuperAdminForm action={createAction} error={err} />
        </div>
      </main>
    </>
  );
}
