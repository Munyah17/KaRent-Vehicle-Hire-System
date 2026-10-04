'use server';

import { supabase } from '@/lib/supabase';
import { headers } from 'next/headers';

export type ContactState = { success?: boolean; error?: string } | null;

export async function submitContact(prev: ContactState, formData: FormData): Promise<ContactState> {
  const name = String(formData.get('name') || '').trim();
  const email = String(formData.get('email') || '').trim();
  const subject = String(formData.get('subject') || '').trim();
  const message = String(formData.get('message') || '').trim();

  if (!name || !email || !subject || !message) {
    return { error: 'Please fill in all fields.' };
  }

  try {
    const { data: roles, error: rolesError } = await supabase
      .from('roles')
      .select('id')
      .in('code', ['SUPER_ADMIN', 'STAFF']);

    if (rolesError) throw rolesError;

    const roleIds = (roles ?? []).map((r) => r.id);
    if (roleIds.length === 0) {
      return { success: true };
    }

    const { data: staff, error: staffError } = await supabase
      .from('users')
      .select('id')
      .eq('status', 'active')
      .in('role_id', roleIds);

    if (staffError) throw staffError;

    const title = `Website enquiry: ${subject}`;
    const body = `${name} (${email}): ${message}`;
    const ip = (await headers()).get('x-forwarded-for')?.split(',')[0]?.trim() || '';
    const link = `/admin/support`;

    const notifications = (staff ?? []).map((s) => ({
      user_id: s.id,
      client_id: 1,
      type: 'enquiry',
      title,
      body,
      link,
      channel: 'in_app',
      status: 'unread',
    }));

    if (notifications.length > 0) {
      const { error: insertError } = await supabase.from('notifications').insert(notifications);
      if (insertError) throw insertError;
    }

    return { success: true };
  } catch (e) {
    return { error: 'Could not send message. Please try again later.' };
  }
}
