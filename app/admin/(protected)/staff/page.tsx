import { query } from '@/lib/db';
import { fmtDate, Badge } from '@/lib/helpers';

export default async function StaffPage({ searchParams }: { searchParams?: Promise<{ q?: string; role?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const role = (params?.role ?? '').trim();

  let where = 'WHERE u.role_id IN (1,2)';
  const args: any[] = [];
  if (q) {
    where += ' AND (u.name LIKE ? OR u.email LIKE ?)';
    args.push(`%${q}%`, `%${q}%`);
  }
  if (role) {
    where += ' AND r.name = ?';
    args.push(role);
  }

  const rows = await query<any>(`
    SELECT u.id, u.name, u.email, u.phone, u.status, u.last_login_at, u.created_at, r.name AS role
    FROM users u
    JOIN roles r ON r.id = u.role_id
    ${where}
    ORDER BY u.name
    LIMIT 200
  `, args);

  const roles = ['SUPER_ADMIN', 'STAFF'];

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-semibold text-slate-800">Staff</h1>
        <form className="flex gap-2">
          <input name="q" defaultValue={q} placeholder="Search name, email" className="rounded-md border border-slate-300 px-3 py-2 text-sm" />
          <select name="role" defaultValue={role} className="rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">All roles</option>
            {roles.map((r) => <option key={r} value={r}>{r}</option>)}
          </select>
          <button type="submit" className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Filter</button>
        </form>
      </div>

      <div className="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 text-slate-600">
            <tr>
              <th className="px-5 py-3 text-left">Name</th>
              <th className="px-5 py-3 text-left">Email</th>
              <th className="px-5 py-3 text-left">Phone</th>
              <th className="px-5 py-3 text-left">Role</th>
              <th className="px-5 py-3 text-left">Status</th>
              <th className="px-5 py-3 text-left">Last login</th>
              <th className="px-5 py-3 text-left">Created</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((s) => (
              <tr key={s.id} className="border-t border-slate-100 hover:bg-slate-50">
                <td className="px-5 py-3 font-medium text-slate-700">{s.name}</td>
                <td className="px-5 py-3 text-slate-600">{s.email}</td>
                <td className="px-5 py-3 text-slate-600">{s.phone ?? '—'}</td>
                <td className="px-5 py-3 text-slate-600">{s.role}</td>
                <td className="px-5 py-3"><Badge status={s.status} /></td>
                <td className="px-5 py-3 text-slate-600">{fmtDate(s.last_login_at)}</td>
                <td className="px-5 py-3 text-slate-600">{fmtDate(s.created_at)}</td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No staff found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
