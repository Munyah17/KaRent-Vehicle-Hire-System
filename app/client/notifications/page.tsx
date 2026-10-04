import { revalidatePath } from 'next/cache';
import { Bell } from 'lucide-react';
import { supabase } from '@/lib/supabase';
import { fmtDateTime } from '@/lib/helpers';
import { requireClient, listNotifications } from '@/components/client/data';

export const metadata = { title: 'Notifications' };

export default async function NotificationsPage() {
  const { user } = await requireClient();
  const notifs = await listNotifications(user.id);

  async function markAllRead() {
    'use server';
    const ctx = await requireClient();
    const { error } = await supabase
      .from('notifications')
      .update({ status: 'read' })
      .eq('user_id', ctx.user.id)
      .eq('status', 'unread');
    if (error) throw new Error(`markAllRead: ${error.message}`);
    revalidatePath('/client/notifications');
    revalidatePath('/client');
  }

  return (
    <div className="cp-card flush" style={{ maxWidth: 780 }}>
      <div className="hd">
        <h2>Notifications</h2>
        {notifs.some((n) => n.status === 'unread') && (
          <form action={markAllRead}>
            <button className="button secondary" style={{ padding: '8px 14px', fontSize: '.78rem' }}>
              Mark all read
            </button>
          </form>
        )}
      </div>
      {notifs.length ? (
        <ul className="cp-list">
          {notifs.map((n) => (
            <li key={n.id} style={n.status === 'unread' ? { background: '#f0f8f6', alignItems: 'flex-start' } : { alignItems: 'flex-start' }}>
              <div style={{ display: 'flex', gap: 12, flex: 1 }}>
                <span style={{ width: 34, height: 34, borderRadius: 9, background: '#e3f4ec', color: '#08705f', display: 'grid', placeItems: 'center', flexShrink: 0 }}>
                  <Bell size={16} />
                </span>
                <div>
                  <p style={{ margin: 0, fontWeight: 700 }}>{n.title}</p>
                  <p className="muted" style={{ margin: '3px 0 0', fontSize: '.85rem' }}>{n.body ?? ''}</p>
                  <span className="t">{fmtDateTime(n.created_at)}</span>
                </div>
              </div>
            </li>
          ))}
        </ul>
      ) : (
        <div className="cp-empty">No notifications.</div>
      )}
    </div>
  );
}
