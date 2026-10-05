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
    <div className="card !p-0 overflow-x-auto max-w-3xl">
      <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 className="font-semibold text-slate-800">Notifications</h2>
        <form action={markAllRead}>
          <button className="btn-secondary !py-1.5 text-xs">Mark all read</button>
        </form>
      </div>
      <ul className="divide-y divide-gray-100">
        {notifs.map((n) => (
          <li
            key={n.id}
            className={`flex items-start gap-4 px-6 py-4 ${n.status === 'unread' ? 'bg-blue-50/40' : ''}`}
          >
            <span className="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Bell className="w-4 h-4" />
            </span>
            <div className="flex-1">
              <p className="text-sm font-medium text-slate-800">{n.title}</p>
              <p className="text-sm text-slate-500">{n.body ?? ''}</p>
              <p className="text-xs text-slate-400 mt-1">{fmtDateTime(n.created_at)}</p>
            </div>
          </li>
        ))}
        {!notifs.length && (
          <li className="px-6 py-10 text-center text-slate-400 text-sm">No notifications.</li>
        )}
      </ul>
    </div>
  );
}
