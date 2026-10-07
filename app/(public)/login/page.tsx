import { Metadata } from 'next';
import LoginForm from '@/components/public/LoginForm';

export const metadata: Metadata = { title: 'Sign In' };

export default async function LoginPage() {
  return (
    <main>
      <div className="min-h-[70vh] flex items-center justify-center px-6 py-16 bg-gray-50">
        <div className="w-full max-w-md">
          <div className="card !p-8">
            <LoginForm />
          </div>
          <div className="mt-4 rounded-lg bg-blue-50 border border-blue-100 p-4 text-xs text-blue-800">
            <p className="font-semibold mb-1">Demo client account</p>
            <p>john@demo.test / Client@123</p>
            <p className="mt-1 text-blue-600">
              Staff? Use the <a href="/admin/login" className="underline">staff sign-in</a>.
            </p>
          </div>
        </div>
      </div>
    </main>
  );
}
