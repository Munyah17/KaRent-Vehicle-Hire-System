'use server';

import { headers } from 'next/headers';
import { redirect } from 'next/navigation';
import { supabase } from '@/lib/supabase';
import { hashPassword, attemptLogin } from '@/lib/auth';

export type RegisterState = { error?: string } | null;

async function nextClientNo(): Promise<string> {
  const { data, error } = await supabase.from('clients').select('client_no');
  if (error) return `CL-${Date.now()}`;
  let max = 0;
  for (const row of data ?? []) {
    const num = parseInt(String(row.client_no).replace(/^CL-/, ''), 10);
    if (!isNaN(num) && num > max) max = num;
  }
  return `CL-${max + 1}`;
}

export async function registerAction(prev: RegisterState, formData: FormData): Promise<RegisterState> {
  const name = String(formData.get('name') || '').trim();
  const email = String(formData.get('email') || '').trim().toLowerCase();
  const phone = String(formData.get('phone') || '').trim();
  const password = String(formData.get('password') || '');
  const confirm = String(formData.get('confirm') || '');

  if (!name || !email || !phone || !password || !confirm) {
    return { error: 'Please fill in all fields.' };
  }
  if (password !== confirm) {
    return { error: 'Passwords do not match.' };
  }
  if (password.length < 8) {
    return { error: 'Password must be at least 8 characters.' };
  }

  const { data: existing, error: existingError } = await supabase
    .from('users')
    .select('id')
    .eq('email', email)
    .maybeSingle();
  if (existingError) return { error: 'Could not verify email. Please try again.' };
  if (existing) return { error: 'An account with this email already exists.' };

  const { data: role, error: roleError } = await supabase
    .from('roles')
    .select('id')
    .eq('name', 'CLIENT')
    .single();
  if (roleError || !role) return { error: 'Registration is currently unavailable.' };

  const clientNo = await nextClientNo();
  const passwordHash = hashPassword(password);

  const { data: user, error: userError } = await supabase
    .from('users')
    .insert({
      role_id: role.id,
      name,
      email,
      phone,
      password_hash: passwordHash,
      status: 'active',
    })
    .select('id')
    .single();
  if (userError || !user) {
    return { error: 'Could not create account. Please try again.' };
  }

  const { data: client, error: clientError } = await supabase
    .from('clients')
    .insert({
      user_id: user.id,
      client_no: clientNo,
      full_name: name,
      phone,
      email,
      source: 'online',
      kyc_status: 'pending',
    })
    .select('id')
    .single();
  if (clientError || !client) {
    await supabase.from('users').delete().eq('id', user.id);
    return { error: 'Could not create client profile. Please try again.' };
  }

  await supabase.from('wallets').insert({ client_id: client.id });

  const h = await headers();
  const ip = h.get('x-forwarded-for')?.split(',')[0]?.trim() || '';
  await attemptLogin(email, password, ip);

  redirect('/client');
}
