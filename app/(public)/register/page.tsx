import { Metadata } from 'next';
import { setting } from '@/lib/settings';
import RegisterForm from '@/components/public/RegisterForm';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'Vehicle Hire');
  return { title: `Create Account · ${name}` };
}

export default async function RegisterPage() {
  return (
    <main>
      <div className="min-h-[70vh] flex items-center justify-center px-6 py-16 bg-gray-50">
        <div className="w-full max-w-md">
          <div className="card !p-8">
            <RegisterForm />
            <p className="text-center text-sm mt-4 text-slate-500">
              Already registered?{' '}
              <a href="/login" className="text-blue-600 hover:underline">
                Sign in
              </a>
            </p>
          </div>
        </div>
      </div>
    </main>
  );
}
