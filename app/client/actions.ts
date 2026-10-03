'use server';

import { redirect } from 'next/navigation';
import { logout } from '@/lib/auth';

export type ActionState = { ok?: boolean; error?: string; success?: string } | null;

export async function logoutAction() {
  await logout();
  redirect('/login');
}
