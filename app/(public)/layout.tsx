import { statSync } from 'node:fs';
import { join } from 'node:path';
import { allSettings } from '@/lib/settings';
import Header from '@/components/public/Header';
import Footer from '@/components/public/Footer';

// Public pages revalidate every 2 minutes — the layout's only data
// dependency is `allSettings` (Supabase), which is not a Dynamic API,
// so routes without cookies()/searchParams are served from cache.
export const revalidate = 120;

const assetV = (p: string) => {
  try {
    return Math.floor(statSync(join(process.cwd(), 'public', p)).mtimeMs);
  } catch {
    return Date.now();
  }
};

export default async function PublicLayout({ children }: { children: React.ReactNode }) {
  const s = await allSettings();
  const settings = {
    companyName: s.company_name || 'Vehicle Hire',
    phone: s.company_phone || '',
    email: s.company_email || '',
    address: s.company_address || '',
  };

  return (
    <>
      <link
        rel="stylesheet"
        href={`/assets/css/app.css?v=${assetV('assets/css/app.css')}`}
        precedence="default"
      />
      <script src={`/assets/js/theme.js?v=${assetV('assets/js/theme.js')}`} defer />
      <Header companyName={settings.companyName} />
      {children}
      <Footer {...settings} />
    </>
  );
}
