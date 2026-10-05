import { supabase } from '@/lib/supabase';
import { fmtDate, Badge } from '@/lib/helpers';

export const metadata = {
  title: 'Clients',
};

interface ClientRow {
  id: number;
  client_no: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  kyc_status: string;
  account_status: string;
  source: string;
  created_at: string;
}

/** Strip characters that would break a PostgREST `or` filter expression. */
function orSafe(value: string): string {
  return value.replace(/[(),\\"]/g, '');
}

export default async function ClientsPage({
  searchParams,
}: {
  searchParams?: Promise<{ q?: string; status?: string }>;
}) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let qb = supabase
    .from('clients')
    .select(
      'id, client_no, full_name, email, phone, kyc_status, account_status, source, created_at'
    )
    .order('created_at', { ascending: false })
    .limit(200);

  if (q) {
    const pat = orSafe(q);
    qb = qb.or(
      `full_name.ilike.%${pat}%,email.ilike.%${pat}%,phone.ilike.%${pat}%,client_no.ilike.%${pat}%`
    );
  }
  if (status) {
    qb = qb.eq('account_status', status);
  }

  const { data, error } = await qb;
  if (error) throw error;
  const rows = (data ?? []) as ClientRow[];

  const statuses = ['active', 'suspended'];

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" className="flex flex-wrap items-center gap-3 flex-1">
          <div className="relative">
            <i data-lucide="search" className="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
            <input
              name="q"
              defaultValue={q}
              placeholder="Search name, phone, ID..."
              className="input !pl-9 w-64"
            />
          </div>
          <select name="status" defaultValue={status} className="input w-44">
            <option value="">All KYC statuses</option>
            {statuses.map((s) => (
              <option key={s} value={s}>
                {s
                  .split('_')
                  .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                  .join(' ')}
              </option>
            ))}
          </select>
          <button className="btn-secondary" type="submit">
            Filter
          </button>
        </form>
        <a href="#" className="btn-primary">
          <i data-lucide="plus" className="w-4 h-4"></i> Add Client
        </a>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr>
              <th className="th">Client</th>
              <th className="th">Client No</th>
              <th className="th">Phone</th>
              <th className="th">KYC</th>
              <th className="th">Source</th>
              <th className="th">Joined</th>
              <th className="th"></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((c) => (
              <tr key={c.id} className="table-row">
                <td className="td">
                  <div className="flex items-center gap-3">
                    <span className="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-semibold">
                      {c.full_name.charAt(0).toUpperCase()}
                    </span>
                    <div>
                      <p className="font-medium text-slate-800">{c.full_name}</p>
                      <p className="text-xs text-slate-400">{c.email ?? '—'}</p>
                    </div>
                  </div>
                </td>
                <td className="td">{c.client_no}</td>
                <td className="td">{c.phone ?? '—'}</td>
                <td className="td">
                  <Badge status={c.kyc_status} />
                </td>
                <td className="td">
                  {c.source
                    .split('_')
                    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                    .join(' ')}
                </td>
                <td className="td">{fmtDate(c.created_at)}</td>
                <td className="td text-right">
                  <a href="#" className="text-blue-600 hover:underline text-sm">
                    Edit
                  </a>
                </td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan={7} className="td text-center py-10 text-slate-400">
                  No clients found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between px-6 py-4">
        <p className="text-sm text-slate-500">Showing {rows.length} clients</p>
      </div>
    </div>
  );
}
