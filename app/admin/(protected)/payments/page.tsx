import { supabase } from '@/lib/supabase';
import { money, fmtDate, Badge } from '@/lib/helpers';

export const metadata = {
  title: 'Payments',
};

interface PaymentRow {
  id: number;
  txn_id: string;
  amount: number;
  method: string;
  purpose: string;
  status: string;
  paid_at: string | null;
  created_at: string;
  clients: { full_name: string } | { full_name: string }[] | null;
}

/** Strip characters that would break a PostgREST `or` filter expression. */
function orSafe(value: string): string {
  return value.replace(/[(),\\"]/g, '');
}

export default async function PaymentsPage({
  searchParams,
}: {
  searchParams?: Promise<{ q?: string; status?: string }>;
}) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let qb = supabase
    .from('payments')
    .select('id, txn_id, amount, method, purpose, status, paid_at, created_at, clients!inner(full_name)')
    .order('created_at', { ascending: false })
    .limit(200);

  if (q) {
    const pat = `%${orSafe(q)}%`;
    const { data: clientRows, error: clientsError } = await supabase
      .from('clients')
      .select('id')
      .ilike('full_name', pat);
    if (clientsError) throw clientsError;
    const clientIds = (clientRows ?? []).map((r: { id: number }) => r.id);
    const ors = [`txn_id.ilike.${pat}`];
    if (clientIds.length) ors.push(`client_id.in.(${clientIds.join(',')})`);
    qb = qb.or(ors.join(','));
  }
  if (status) {
    qb = qb.eq('status', status);
  }

  const { data, error } = await qb;
  if (error) throw error;
  const rows = (data ?? []) as unknown as PaymentRow[];

  const statuses = ['pending', 'successful', 'failed', 'cancelled', 'refunded'];
  const methods = ['cash', 'bank_transfer', 'paynow', 'card', 'wallet', 'other'];

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" className="flex flex-wrap items-center gap-3 flex-1">
          <select name="status" defaultValue={status} className="input w-40">
            <option value="">All Statuses</option>
            {statuses.map((s) => (
              <option key={s} value={s}>
                {s.charAt(0).toUpperCase() + s.slice(1)}
              </option>
            ))}
          </select>
          <select name="method" defaultValue="" className="input w-44">
            <option value="">All Methods</option>
            {methods.map((m) => (
              <option key={m} value={m}>
                {m
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
          <i data-lucide="plus" className="w-4 h-4"></i> New Payment
        </a>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr>
              <th className="th">Txn ID</th>
              <th className="th">Client</th>
              <th className="th">Method</th>
              <th className="th">Purpose</th>
              <th className="th">Status</th>
              <th className="th">Date</th>
              <th className="th text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((p) => {
              const client = Array.isArray(p.clients) ? p.clients[0] : p.clients;
              return (
                <tr key={p.id} className="table-row">
                  <td className="td font-medium">{p.txn_id}</td>
                  <td className="td">{client?.full_name}</td>
                  <td className="td">
                    {p.method
                      .split('_')
                      .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                      .join(' ')}
                  </td>
                  <td className="td">{p.purpose.charAt(0).toUpperCase() + p.purpose.slice(1)}</td>
                  <td className="td">
                    <Badge status={p.status} />
                  </td>
                  <td className="td">{fmtDate(p.paid_at ?? p.created_at)}</td>
                  <td className="td text-right font-medium">{money(p.amount)}</td>
                </tr>
              );
            })}
            {rows.length === 0 && (
              <tr>
                <td colSpan={7} className="td text-center py-10 text-slate-400">
                  No payments found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between px-6 py-4">
        <p className="text-sm text-slate-500">Showing {rows.length} payments</p>
      </div>
    </div>
  );
}
