'use server';

import { headers } from 'next/headers';
import { redirect } from 'next/navigation';
import { attemptLogin } from '@/lib/auth';

export type LoginState = { error?: string } | null;

export async function loginAction(prev: LoginState, formData: FormData): Promise<LoginState> {
  const email = String(formData.get('email') || '').trim();
  const password = String(formData.get('password') || '');

  if (!email || !password) {
    return { error: 'Please enter your email and password.' };
  }

  const h = await headers();
  const ip = h.get('x-forwarded-for')?.split(',')[0]?.trim() || '';
  const user = await attemptLogin(email, password, ip);

  if (!user) {
    return { error: 'Invalid email or password.' };
  }

  if (user.role !== 'CLIENT') {
    return { error: 'Staff accounts must sign in at the staff portal.' };
  }

  redirect('/client/index.php');
}
