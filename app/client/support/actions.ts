'use server';

import { revalidatePath } from 'next/cache';
import { run, one } from '@/lib/db';
import { getSession } from '@/lib/auth';
import { notifyStaff } from '@/components/client/data';
import type { ActionState } from '../actions';

export async function createTicketAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const user = await getSession();
  if (!user || user.role !== 'CLIENT') return { error: 'Please sign in again.' };
  const client = await one<{ id: number; full_name: string }>(
    'SELECT id, full_name FROM clients WHERE user_id = ?',
    [user.id]
  );
  if (!client) return { error: 'Please sign in again.' };

  const subject = String(formData.get('subject') || '').trim();
  const message = String(formData.get('message') || '').trim();
  if (!subject || !message) return { error: 'Subject and message are required.' };
  if (subject.length > 160) return { error: 'Subject is too long.' };

  await run('INSERT INTO support_tickets (client_id, subject, message) VALUES (?,?,?)', [
    client.id,
    subject,
    message,
  ]);
  await notifyStaff('support', 'New support request', `${client.full_name}: ${subject}`, 'admin/support.php');
  revalidatePath('/client/support');
  return { ok: true, success: "Request submitted — we'll get back to you soon." };
}
