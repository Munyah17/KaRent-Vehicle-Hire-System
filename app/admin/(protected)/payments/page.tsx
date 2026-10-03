import { query } from '@/lib/db';
import { money, fmtDate, Badge } from '@/lib/helpers';

export default async function PaymentsPage({ searchParams }: { searchParams?: Promise<{ q?: string; status?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let where = 'WHERE 1=1';
  const args: any[] = [];
  if (q) {
    where += ' AND (p.txn_id LIKE ? OR c.full_name LIKE ?)';
    args.push(`%${q}%`, `%${q}%`);
  }
  if (status) {
    where += ' AND p.status = ?';
    args.push(status);
  }

  const rows = await query<any>(`
    SELECT p.id, p.txn_id, p.amount, p.method, p.purpose, p.status, p.paid_at, p.created_at,
           c.full_name AS client
    FROM payments p
    JOIN clients c ON c.id = p.client_id
    ${where}
    ORDER BY p.created_at DESC
    LIMIT 200
  `, args);

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
            {rows.map((p) => (
              <tr key={p.id} className="border-t border-slate-100 hover:bg-slate-50">
                <td className="px-5 py-3 font-medium text-slate-700">{p.txn_id}</td>
                <td className="px-5 py-3 text-slate-600">{p.client}</td>
                <td className="px-5 py-3 text-slate-600">{p.method}</td>
                <td className="px-5 py-3 text-slate-600">{p.purpose}</td>
                <td className="px-5 py-3 text-right text-slate-700">{money(p.amount)}</td>
                <td className="px-5 py-3"><Badge status={p.status} /></td>
                <td className="px-5 py-3 text-slate-600">{fmtDate(p.paid_at)}</td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No payments found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
