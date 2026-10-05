import { supabase } from '@/lib/supabase';
import { fmtDate, Badge } from '@/lib/helpers';

export const metadata = {
  title: 'Staff & Permissions',
};

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

export default async function StaffPage({
  searchParams,
}: {
  searchParams?: Promise<{ q?: string; role?: string }>;
}) {
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
    <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div className="xl:col-span-2 space-y-6">
        <div className="card !p-0 overflow-x-auto">
          <div className="px-6 py-4 border-b border-gray-100">
            <h3 className="font-semibold text-slate-800">Staff Accounts</h3>
          </div>
          <table className="w-full">
            <thead>
              <tr>
                <th className="th">Name</th>
                <th className="th">Email</th>
                <th className="th">Role</th>
                <th className="th">Status</th>
                <th className="th">Last login</th>
                <th className="th"></th>
              </tr>
            </thead>
            <tbody>
              {rows.map((s) => {
                const roleName = Array.isArray(s.roles) ? s.roles[0]?.name : s.roles?.name;
                return (
                  <tr key={s.id} className="table-row">
                    <td className="td">
                      <div className="flex items-center gap-3">
                        <span className="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-semibold">
                          {s.name.charAt(0).toUpperCase()}
                        </span>
                        <span className="font-medium">{s.name}</span>
                      </div>
                    </td>
                    <td className="td">{s.email}</td>
                    <td className="td">
                      {roleName === 'SUPER_ADMIN' ? (
                        <>
                          <Badge status="verified" /> SUPER_ADMIN
                        </>
                      ) : (
                        <>
                          <Badge status="active" /> STAFF
                        </>
                      )}
                    </td>
                    <td className="td">
                      <Badge status={s.status} />
                    </td>
                    <td className="td">{fmtDate(s.last_login_at)}</td>
                    <td className="td text-right space-x-2">
                      <a href="#" className="text-blue-600 text-sm hover:underline">
                        Permissions
                      </a>
                    </td>
                  </tr>
                );
              })}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="td text-center py-10 text-slate-400">
                    No staff found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="card">
        <h3 className="font-semibold text-slate-800 mb-4">Add Staff Member</h3>
        <form className="space-y-3">
          <input name="name" placeholder="Full name" className="input" required />
          <input name="email" type="email" placeholder="Email" className="input" required />
          <input name="phone" placeholder="Phone" className="input" />
          <input
            name="password"
            type="password"
            placeholder="Password (min 8 chars)"
            className="input"
            required
            minLength={8}
          />
          <button className="btn-primary w-full justify-center" type="submit">
            <i data-lucide="user-plus" className="w-4 h-4"></i> Add Staff
          </button>
        </form>
      </div>
    </div>
  );
}
