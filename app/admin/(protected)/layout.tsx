import { redirect } from 'next/navigation';
import { getSession } from '@/lib/auth';
import AdminNav from '@/components/admin/AdminNav';

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const session = await getSession();
  if (!session || !['SUPER_ADMIN', 'STAFF'].includes(session.role)) {
    redirect('/admin/login');
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <AdminNav user={session} />
      <main className="lg:pl-64 pt-16">
        <div className="mx-auto max-w-7xl p-6">{children}</div>
      </main>
    </div>
  );
}
