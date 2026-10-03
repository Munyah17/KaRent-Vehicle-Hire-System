import { query } from '@/lib/db';
import { fmtDate, Badge } from '@/lib/helpers';

export default async function ClientsPage({ searchParams }: { searchParams?: Promise<{ q?: string; status?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let where = 'WHERE 1=1';
  const args: any[] = [];
  if (q) {
    where += ' AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR client_no LIKE ?)';
    args.push(`%${q}%`, `%${q}%`, `%${q}%`, `%${q}%`);
  }
  if (status) {
    where += ' AND account_status = ?';
    args.push(status);
  }

  const rows = await query<any>(`
    SELECT id, client_no, full_name, email, phone, kyc_status, account_status, source, created_at
    FROM clients
    ${where}
    ORDER BY created_at DESC
    LIMIT 200
  `, args);

  const statuses = ['active', 'suspended'];

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-semibold text-slate-800">Clients</h1>
        <form className="flex gap-2">
          <input name="q" defaultValue={q} placeholder="Search name, email, phone" className="rounded-md border border-slate-300 px-3 py-2 text-sm" />
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
              <th className="px-5 py-3 text-left">Client no</th>
              <th className="px-5 py-3 text-left">Name</th>
              <th className="px-5 py-3 text-left">Email</th>
              <th className="px-5 py-3 text-left">Phone</th>
              <th className="px-5 py-3 text-left">KYC</th>
              <th className="px-5 py-3 text-left">Status</th>
              <th className="px-5 py-3 text-left">Joined</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((c) => (
              <tr key={c.id} className="border-t border-slate-100 hover:bg-slate-50">
                <td className="px-5 py-3 font-medium text-slate-700">{c.client_no}</td>
                <td className="px-5 py-3 text-slate-600">{c.full_name}</td>
                <td className="px-5 py-3 text-slate-600">{c.email ?? '—'}</td>
                <td className="px-5 py-3 text-slate-600">{c.phone ?? '—'}</td>
                <td className="px-5 py-3"><Badge status={c.kyc_status} /></td>
                <td className="px-5 py-3"><Badge status={c.account_status} /></td>
                <td className="px-5 py-3 text-slate-600">{fmtDate(c.created_at)}</td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No clients found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
