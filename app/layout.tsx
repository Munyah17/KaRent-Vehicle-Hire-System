import type { Metadata } from 'next';
import { siteName } from '@/lib/settings';
import './globals.css';

export async function generateMetadata(): Promise<Metadata> {
  const name = await siteName();
  return {
    title: { default: name, template: `%s — ${name}` },
    description: 'Reliable vehicle hire, transparent pricing and verified payments.',
  };
}

const themeInit = `try{var t=(localStorage.getItem('karent.theme')||(document.cookie.match(/theme=(dark)/)||[])[1]);if(t==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-bs-theme','dark')}}catch(e){}`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <head>
        <script dangerouslySetInnerHTML={{ __html: themeInit }} />
      </head>
      <body className="bg-white">{children}</body>
    </html>
  );
}
