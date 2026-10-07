import { redirect } from 'next/navigation';
import { getSession } from '@/lib/auth';
import AdminShell from '@/components/admin/AdminShell';

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const session = await getSession();
  if (!session || !['SUPER_ADMIN', 'STAFF'].includes(session.role)) {
    redirect('/admin/login');
  }

  return (
    <>
      <link href="/assets/admin/css/font-face.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/vendor/bootstrap-5.3.8.min.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/vendor/fontawesome-7.3.1/css/all.min.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/vendor/css-hamburgers/hamburgers.min.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/css/theme.css" rel="stylesheet" precedence="default" />
      <link href="/assets/admin/css/app.css" rel="stylesheet" precedence="default" />
      <link href="/assets/css/app.css" rel="stylesheet" precedence="default" />
      <script src="/assets/vendor/lucide.min.js" defer></script>
      <script src="/assets/admin/js/vanilla-utils.js" defer></script>
      <script src="/assets/admin/vendor/bootstrap-5.3.8.bundle.min.js" defer></script>
      <script src="/assets/admin/js/main-vanilla.js" defer></script>
      <script src="/assets/js/theme.js?v=2" defer></script>
      <script
        dangerouslySetInnerHTML={{
          __html:
            "try{if((localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1])==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}",
        }}
      />
      <AdminShell user={{ name: session.name, email: session.email, role: session.role }}>
        {children}
      </AdminShell>
    </>
  );
}
