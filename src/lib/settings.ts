import { cache } from 'react';
import { supabase } from './supabase';

/**
 * Settings are loaded once per request — React cache() dedupes this
 * across the whole render tree (layout, metadata, page, components),
 * so a page costs a single settings round-trip no matter how many
 * keys it asks for.
 */
export const allSettings = cache(async (): Promise<Record<string, string>> => {
  const { data } = await supabase.from('settings').select('key, value');
  return Object.fromEntries((data ?? []).map((r) => [r.key, r.value ?? '']));
});

export async function setting(key: string, fallback = ''): Promise<string> {
  const all = await allSettings();
  return all[key] ?? fallback;
}

export async function setSetting(key: string, value: string): Promise<void> {
  await supabase.from('settings').upsert({ key, value }, { onConflict: 'key' });
}

export const siteName = () => setting('company_name', 'KaRent');
