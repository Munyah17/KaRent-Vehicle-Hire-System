import { query } from '@/lib/db';
import { money, fmtDate, Badge } from '@/lib/helpers';

export default async function VehiclesPage({ searchParams }: { searchParams?: Promise<{ q?: string; status?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let where = 'WHERE 1=1';
  const args: any[] = [];
  if (q) {
    where += ' AND (reg_no LIKE ? OR make LIKE ? OR model LIKE ?)';
    args.push(`%${q}%`, `%${q}%`, `%${q}%`);
  }
  if (status) {
    where += ' AND status = ?';
    args.push(status);
  }

  const rows = await query<any>(`
    SELECT id, reg_no, make, model, year, colour, transmission, fuel_type, seats, daily_rate, status, is_public
    FROM vehicles
    ${where}
    ORDER BY make, model
    LIMIT 200
  `, args);

  const statuses = ['available', 'reserved', 'on_hire', 'maintenance', 'unavailable'];

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-semibold text-slate-800">Vehicles</h1>
        <form className="flex gap-2">
          <input name="q" defaultValue={q} placeholder="Search reg, make, model" className="rounded-md border border-slate-300 px-3 py-2 text-sm" />
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
              <th className="px-5 py-3 text-left">Registration</th>
              <th className="px-5 py-3 text-left">Make / Model</th>
              <th className="px-5 py-3 text-left">Year</th>
              <th className="px-5 py-3 text-left">Fuel</th>
              <th className="px-5 py-3 text-left">Seats</th>
              <th className="px-5 py-3 text-right">Daily rate</th>
              <th className="px-5 py-3 text-left">Status</th>
              <th className="px-5 py-3 text-left">Public</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((v) => (
              <tr key={v.id} className="border-t border-slate-100 hover:bg-slate-50">
                <td className="px-5 py-3 font-medium text-slate-700">{v.reg_no}</td>
                <td className="px-5 py-3 text-slate-600">{v.make} {v.model}</td>
                <td className="px-5 py-3 text-slate-600">{v.year ?? '—'}</td>
                <td className="px-5 py-3 text-slate-600">{v.fuel_type}</td>
                <td className="px-5 py-3 text-slate-600">{v.seats}</td>
                <td className="px-5 py-3 text-right text-slate-700">{money(v.daily_rate)}</td>
                <td className="px-5 py-3"><Badge status={v.status} /></td>
                <td className="px-5 py-3 text-slate-600">{v.is_public ? 'Yes' : 'No'}</td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={8} className="px-5 py-6 text-slate-400">No vehicles found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
