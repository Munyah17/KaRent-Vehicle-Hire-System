'use server';

import { revalidatePath } from 'next/cache';
import { supabase } from '@/lib/supabase';
import { getSession } from '@/lib/auth';
import { notifyStaff } from '@/components/client/data';
import type { ActionState } from '../actions';

export async function createTicketAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const user = await getSession();
  if (!user || user.role !== 'CLIENT') return { error: 'Please sign in again.' };

  const { data: client, error: clientErr } = await supabase
    .from('clients')
    .select('id, full_name')
    .eq('user_id', user.id)
    .maybeSingle();
  if (clientErr) return { error: 'Could not verify client.' };
  if (!client) return { error: 'Please sign in again.' };

  const subject = String(formData.get('subject') || '').trim();
  const message = String(formData.get('message') || '').trim();
  if (!subject || !message) return { error: 'Subject and message are required.' };
  if (subject.length > 160) return { error: 'Subject is too long.' };

  const { error } = await supabase.from('support_tickets').insert({
    client_id: client.id,
    subject,
    message,
  });
  if (error) return { error: 'Could not submit request. Please try again.' };

  await notifyStaff('support', 'New support request', `${client.full_name}: ${subject}`, '/admin');
  revalidatePath('/client/support');
  return { ok: true, success: "Request submitted — we'll get back to you soon." };
}
