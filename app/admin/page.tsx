import { redirect } from 'next/navigation';
import { getSession } from '@/lib/auth';

export default async function AdminIndexPage() {
  const session = await getSession();
  if (session && ['SUPER_ADMIN', 'STAFF'].includes(session.role)) {
    redirect('/admin/dashboard');
  }
  redirect('/admin/login');
}
