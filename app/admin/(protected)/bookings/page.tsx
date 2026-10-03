import Link from 'next/link';
import { query } from '@/lib/db';
import { money, fmtDate, Badge } from '@/lib/helpers';

export default async function BookingsPage({ searchParams }: { searchParams?: Promise<{ q?: string; status?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let where = 'WHERE 1=1';
  const args: any[] = [];
  if (q) {
    where += ' AND (b.ref LIKE ? OR c.full_name LIKE ? OR v.reg_no LIKE ?)';
    args.push(`%${q}%`, `%${q}%`, `%${q}%`);
  }
  if (status) {
    where += ' AND b.status = ?';
    args.push(status);
  }

  const rows = await query<any>(`
    SELECT b.id, b.ref, b.status, b.pickup_at, b.return_at, b.total, b.source,
           c.id AS client_id, c.full_name AS client,
           v.id AS vehicle_id, v.reg_no
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    JOIN vehicles v ON v.id = b.vehicle_id
    ${where}
    ORDER BY b.created_at DESC
    LIMIT 100
  `, args);

  const statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'overdue'];

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-semibold text-slate-800">Bookings</h1>
        <form className="flex gap-2">
          <input name="q" defaultValue={q} placeholder="Search ref, client, vehicle" className="rounded-md border border-slate-300 px-3 py-2 text-sm" />
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
              <th className="px-5 py-3 text-left">Ref</th>
              <th className="px-5 py-3 text-left">Client</th>
              <th className="px-5 py-3 text-left">Vehicle</th>
              <th className="px-5 py-3 text-left">Pickup</th>
              <th className="px-5 py-3 text-left">Return</th>
              <th className="px-5 py-3 text-right">Total</th>
              <th className="px-5 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((b) => (
              <tr key={b.id} className="border-t border-slate-100 hover:bg-slate-50">
                <td className="px-5 py-3 font-medium text-slate-700"><Link href={`/admin/bookings/${b.id}`} className="hover:underline">{b.ref}</Link></td>
                <td className="px-5 py-3 text-slate-600">{b.client}</td>
                <td className="px-5 py-3 text-slate-600">{b.reg_no}</td>
                <td className="px-5 py-3 text-slate-600">{fmtDate(b.pickup_at)}</td>
                <td className="px-5 py-3 text-slate-600">{fmtDate(b.return_at)}</td>
                <td className="px-5 py-3 text-right text-slate-700">{money(b.total)}</td>
                <td className="px-5 py-3"><Badge status={b.status} /></td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No bookings found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
