import { one, run, query } from './db';

export async function setting(key: string, fallback = ''): Promise<string> {
  const row = await one<{ value: string }>('SELECT `value` FROM settings WHERE `key` = ?', [key]);
  return row?.value ?? fallback;
}

export async function setSetting(key: string, value: string): Promise<void> {
  await run('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [key, value]);
}

export async function allSettings(): Promise<Record<string, string>> {
  const rows = await query<{ key: string; value: string }>('SELECT `key`, `value` FROM settings');
  return Object.fromEntries(rows.map((r) => [r.key, r.value ?? '']));
}

export const siteName = () => setting('company_name', 'KaRent');
