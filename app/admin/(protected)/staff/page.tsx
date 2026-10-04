import { supabase } from '@/lib/supabase';
import { fmtDate, Badge } from '@/lib/helpers';

interface StaffRow {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  status: string;
  last_login_at: string | null;
  created_at: string;
  roles: { name: string } | { name: string }[] | null;
}

/** Strip characters that would break a PostgREST `or` filter expression. */
function orSafe(value: string): string {
  return value.replace(/[(),\\"]/g, '');
}

export default async function StaffPage({ searchParams }: { searchParams?: Promise<{ q?: string; role?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const role = (params?.role ?? '').trim();

  let qb = supabase
    .from('users')
    .select('id, name, email, phone, status, last_login_at, created_at, roles!inner(name)')
    .in('roles.name', ['SUPER_ADMIN', 'STAFF'])
    .order('name')
    .limit(200);

  if (q) {
    qb = qb.or(`name.ilike.%${orSafe(q)}%,email.ilike.%${orSafe(q)}%`);
  }
  if (role) {
    qb = qb.eq('roles.name', role);
  }

  const { data, error } = await qb;
  if (error) throw error;
  const rows = (data ?? []) as unknown as StaffRow[];

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
            {rows.map((s) => {
              const roleName = Array.isArray(s.roles) ? s.roles[0]?.name : s.roles?.name;
              return (
                <tr key={s.id} className="border-t border-slate-100 hover:bg-slate-50">
                  <td className="px-5 py-3 font-medium text-slate-700">{s.name}</td>
                  <td className="px-5 py-3 text-slate-600">{s.email}</td>
                  <td className="px-5 py-3 text-slate-600">{s.phone ?? '—'}</td>
                  <td className="px-5 py-3 text-slate-600">{roleName}</td>
                  <td className="px-5 py-3"><Badge status={s.status} /></td>
                  <td className="px-5 py-3 text-slate-600">{fmtDate(s.last_login_at)}</td>
                  <td className="px-5 py-3 text-slate-600">{fmtDate(s.created_at)}</td>
                </tr>
              );
            })}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No staff found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
