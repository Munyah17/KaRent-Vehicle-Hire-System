'use server';

import { revalidatePath } from 'next/cache';
import { supabase } from '@/lib/supabase';
import { getSession } from '@/lib/auth';
import type { ActionState } from '../actions';

export async function updateContactAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const user = await getSession();
  if (!user || user.role !== 'CLIENT') return { error: 'Please sign in again.' };

  const { data: client, error: clientErr } = await supabase
    .from('clients')
    .select('id')
    .eq('user_id', user.id)
    .maybeSingle();
  if (clientErr || !client) return { error: 'Please sign in again.' };

  const phone = String(formData.get('phone') || '').trim();

  const { error: userErr } = await supabase.from('users').update({ phone }).eq('id', user.id);
  if (userErr) return { error: 'Could not update contact details.' };
  const { error: cliErr } = await supabase
    .from('clients')
    .update({ phone })
    .eq('id', (client as { id: number }).id);
  if (cliErr) return { error: 'Could not update contact details.' };

  revalidatePath('/client/settings');
  revalidatePath('/client/profile');
  return { ok: true, success: 'Contact details updated.' };
}
