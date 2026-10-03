'use server';

import { query, run } from '@/lib/db';
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
    const staff = await query<{ id: number }>(
      "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code IN ('SUPER_ADMIN','STAFF') AND u.status = 'active'"
    );

    const title = `Website enquiry: ${subject}`;
    const body = `${name} (${email}): ${message}`;
    const ip = (await headers()).get('x-forwarded-for')?.split(',')[0]?.trim() || '';
    const link = `/admin/support`;

    for (const s of staff) {
      await run(
        'INSERT INTO notifications (user_id, client_id, type, title, body, link, channel, status) VALUES (?, 1, ?, ?, ?, ?, ?, ?)',
        [s.id, 'enquiry', title, body, link, 'in_app', 'unread']
      );
    }

    return { success: true };
  } catch (e) {
    return { error: 'Could not send message. Please try again later.' };
  }
}
