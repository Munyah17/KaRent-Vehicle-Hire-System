import type { Metadata } from 'next';
import { cookies } from 'next/headers';
import { setting } from '@/lib/settings';
import './globals.css';
import Header from '@/components/public/Header';
import Footer from '@/components/public/Footer';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'KaRent');
  return {
    title: { default: `${name} · Vehicle Hire`, template: `%s · ${name}` },
    description: 'Reliable vehicle hire, transparent pricing and verified payments.',
  };
}

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  await cookies();
  const settings = {
    companyName: await setting('company_name', 'KaRent'),
    phone: await setting('company_phone', ''),
    email: await setting('company_email', ''),
    address: await setting('company_address', ''),
  };

  return (
    <html lang="en">
      <body>
        <Header companyName={settings.companyName} />
        {children}
        <Footer {...settings} />
      </body>
    </html>
  );
}
