import type { Metadata } from 'next';
import Link from 'next/link';
import { Bell } from 'lucide-react';
import { requireClient, unreadCount } from '@/components/client/data';
import ClientNav from '@/components/client/ClientNav';
import { logoutAction } from './actions';
import './client.css';

export const metadata: Metadata = {
  title: { default: 'Client Portal', template: '%s · Client Portal' },
};

export default async function ClientLayout({ children }: { children: React.ReactNode }) {
  const { user, client } = await requireClient();
  const unread = await unreadCount(user.id);

  return (
    <>
      <div className="cp-top">
        <div className="shell cp-topbar">
          <div className="who">
            <strong>{client.full_name}</strong> · {client.client_no}
          </div>
          <div className="cp-toplinks">
            <Link href="/client/notifications" className="bell" aria-label="Notifications">
              <Bell size={19} />
              {unread > 0 && <span className="n">{unread > 99 ? '99' : unread}</span>}
            </Link>
            <form action={logoutAction}>
              <button type="submit" className="cp-signout">Sign out</button>
            </form>
          </div>
        </div>
        <div className="shell">
          <ClientNav />
        </div>
      </div>
      <main className="cp-main">
        <div className="shell">{children}</div>
      </main>
    </>
  );
}
