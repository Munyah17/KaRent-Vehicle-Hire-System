'use server';

import { logout as clearSession } from '@/lib/auth';

export async function logoutAction() {
  await clearSession();
}
