import { Metadata } from 'next';
import { setting } from '@/lib/settings';
import LoginForm from '@/components/public/LoginForm';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'KaRent');
  return { title: `Sign In · ${name}` };
}

export default async function LoginPage() {
  return (
    <main className="login-wrap">
      <div className="login-card">
        <LoginForm />
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 20, fontSize: '0.9rem' }}>
          <a href="/forgot-password.php" style={{ color: '#087f70' }}>Forgot password?</a>
          <a href="/register.php" style={{ color: '#087f70' }}>Create account</a>
        </div>
      </div>
    </main>
  );
}
