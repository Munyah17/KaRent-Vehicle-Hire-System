import type { Metadata } from 'next';
import { requireClient, unreadCount } from '@/components/client/data';
import { setting } from '@/lib/settings';
import ClientShell from '@/components/client/ClientShell';

export const metadata: Metadata = {
  title: { default: 'Client Portal', template: '%s · Client Portal' },
};

export default async function ClientLayout({ children }: { children: React.ReactNode }) {
  const { user, client } = await requireClient();
  const [unread, companyName] = await Promise.all([
    unreadCount(user.id),
    setting('company_name', 'Vehicle Hire'),
  ]);

  return (
    <div className="bg-slate-50">
      {/* Compiled Tailwind stylesheet ported from the PHP app (card/btn/input/table/drawer classes) */}
      {/* eslint-disable-next-line @next/next/no-css-tags */}
      <link rel="stylesheet" href="/assets/css/app.css" precedence="default" />
      {/* The PHP client portal renders its own chrome — hide the public site header/footer */}
      <style>{`.header,.footer{display:none!important}`}</style>
      <ClientShell
        name={client.full_name ?? user.name ?? ''}
        unread={unread}
        companyName={companyName}
      >
        {children}
      </ClientShell>
    </div>
  );
}
