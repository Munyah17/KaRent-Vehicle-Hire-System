import { supabase } from './supabase';

export async function setting(key: string, fallback = ''): Promise<string> {
  const { data } = await supabase.from('settings').select('value').eq('key', key).single();
  return data?.value ?? fallback;
}

export async function setSetting(key: string, value: string): Promise<void> {
  await supabase.from('settings').upsert({ key, value }, { onConflict: 'key' });
}

export async function allSettings(): Promise<Record<string, string>> {
  const { data } = await supabase.from('settings').select('key, value');
  return Object.fromEntries((data ?? []).map((r) => [r.key, r.value ?? '']));
}

export const siteName = () => setting('company_name', 'KaRent');
