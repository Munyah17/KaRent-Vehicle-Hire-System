import type { Metadata } from 'next';
import { headers } from 'next/headers';
import { statSync } from 'node:fs';
import { join } from 'node:path';
import { setting } from '@/lib/settings';
import { getSession } from '@/lib/auth';
import './globals.css';
import Header from '@/components/public/Header';
import Footer from '@/components/public/Footer';
import LucideInit from '@/components/public/LucideInit';

export const dynamic = 'force-dynamic';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'Vehicle Hire');
  return {
    title: { default: name, template: `%s — ${name}` },
    description: 'Reliable vehicle hire, transparent pricing and verified payments.',
  };
}

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const h = await headers();
  const path = h.get('x-invoke-path') || h.get('x-matched-path') || '/';
  const isPublicRoute =
    !path.startsWith('/admin') && !path.startsWith('/client') && !path.startsWith('/super-admin');

  const settings = {
    companyName: await setting('company_name', 'Vehicle Hire'),
    phone: await setting('company_phone', ''),
    email: await setting('company_email', ''),
    address: await setting('company_address', ''),
  };

  const user = isPublicRoute ? await getSession() : null;

  const themeInit = `try{var t=(localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1]);if(t==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}`;

  const assetV = (p: string) => {
    try {
      return Math.floor(statSync(join(process.cwd(), 'public', p)).mtimeMs);
    } catch {
      return Date.now();
    }
  };

  return (
    <html lang="en">
      <head>
        <link rel="stylesheet" href={`/assets/css/app.css?v=${assetV('assets/css/app.css')}`} />
        <script dangerouslySetInnerHTML={{ __html: themeInit }} />
        <script src={`/assets/vendor/lucide.min.js?v=${assetV('assets/vendor/lucide.min.js')}`} defer />
        <script src={`/assets/js/theme.js?v=${assetV('assets/js/theme.js')}`} defer />
      </head>
      <body className="bg-white">
        {isPublicRoute && <Header companyName={settings.companyName} user={user} />}
        {children}
        {isPublicRoute && <Footer {...settings} />}
        <LucideInit />
      </body>
    </html>
  );
}
