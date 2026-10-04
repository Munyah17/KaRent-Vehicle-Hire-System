import { supabase } from '@/lib/supabase';
import { money, fmtDate, Badge } from '@/lib/helpers';

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

export default async function PaymentsPage({ searchParams }: { searchParams?: Promise<{ q?: string; status?: string }> }) {
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

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-semibold text-slate-800">Payments</h1>
        <form className="flex gap-2">
          <input name="q" defaultValue={q} placeholder="Search txn, client" className="rounded-md border border-slate-300 px-3 py-2 text-sm" />
          <select name="status" defaultValue={status} className="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">All statuses</option>
            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
          <button type="submit" className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
        </form>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 text-slate-600">
            <tr>
              <th className="px-5 py-3 text-left">Txn ID</th>
              <th className="px-5 py-3 text-left">Client</th>
              <th className="px-5 py-3 text-left">Method</th>
              <th className="px-5 py-3 text-left">Purpose</th>
              <th className="px-5 py-3 text-right">Amount</th>
              <th className="px-5 py-3 text-left">Status</th>
              <th className="px-5 py-3 text-left">Paid</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((p) => {
              const client = Array.isArray(p.clients) ? p.clients[0] : p.clients;
              return (
                <tr key={p.id} className="border-t border-slate-100 hover:bg-slate-50">
                  <td className="px-5 py-3 font-medium text-slate-700">{p.txn_id}</td>
                  <td className="px-5 py-3 text-slate-600">{client?.full_name}</td>
                  <td className="px-5 py-3 text-slate-600">{p.method}</td>
                  <td className="px-5 py-3 text-slate-600">{p.purpose}</td>
                  <td className="px-5 py-3 text-right text-slate-700">{money(p.amount)}</td>
                  <td className="px-5 py-3"><Badge status={p.status} /></td>
                  <td className="px-5 py-3 text-slate-600">{fmtDate(p.paid_at)}</td>
                </tr>
              );
            })}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No payments found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
