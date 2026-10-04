import { supabase } from '@/lib/supabase';
import { money, fmtDate } from '@/lib/helpers';

interface TodayBookingRow {
  id: number;
  ref: string;
  pickup_at: string;
  status: string;
  clients: { full_name: string } | { full_name: string }[] | null;
  vehicles: { reg_no: string } | { reg_no: string }[] | null;
}

interface RecentPaymentRow {
  id: number;
  txn_id: string;
  amount: number;
  method: string;
  status: string;
  paid_at: string | null;
  clients: { full_name: string } | { full_name: string }[] | null;
}

export default async function DashboardPage() {
  const { data: staffRoles, error: rolesError } = await supabase
    .from('roles')
    .select('id')
    .in('name', ['SUPER_ADMIN', 'STAFF']);
  if (rolesError) throw rolesError;
  const staffRoleIds = (staffRoles ?? []).map((r: { id: number }) => r.id);

  const [bookingsRes, vehiclesRes, clientsRes, paymentsRes, staffRes] = await Promise.all([
    supabase.from('bookings').select('id', { count: 'exact', head: true }),
    supabase.from('vehicles').select('id', { count: 'exact', head: true }),
    supabase.from('clients').select('id', { count: 'exact', head: true }),
    supabase.from('payments').select('id', { count: 'exact', head: true }),
    supabase.from('users').select('id', { count: 'exact', head: true }).in('role_id', staffRoleIds.length ? staffRoleIds : [-1]),
  ]);
  for (const res of [bookingsRes, vehiclesRes, clientsRes, paymentsRes, staffRes]) {
    if (res.error) throw res.error;
  }
  const map: Record<string, number> = {
    bookings: bookingsRes.count ?? 0,
    vehicles: vehiclesRes.count ?? 0,
    clients: clientsRes.count ?? 0,
    payments: paymentsRes.count ?? 0,
    staff: staffRes.count ?? 0,
  };

  const dayStart = new Date();
  dayStart.setHours(0, 0, 0, 0);
  const dayEnd = new Date(dayStart);
  dayEnd.setDate(dayEnd.getDate() + 1);

  const { data: todayData, error: todayError } = await supabase
    .from('bookings')
    .select('id, ref, pickup_at, status, clients!inner(full_name), vehicles!inner(reg_no)')
    .gte('pickup_at', dayStart.toISOString())
    .lt('pickup_at', dayEnd.toISOString())
    .order('pickup_at', { ascending: true })
    .limit(5);
  if (todayError) throw todayError;
  const todayBookings = (todayData ?? []) as unknown as TodayBookingRow[];

  const { data: paymentsData, error: paymentsError } = await supabase
    .from('payments')
    .select('id, txn_id, amount, method, status, paid_at, clients!inner(full_name)')
    .order('created_at', { ascending: false })
    .limit(5);
  if (paymentsError) throw paymentsError;
  const recentPayments = (paymentsData ?? []) as unknown as RecentPaymentRow[];

  return (
    <div>
      <h1 className="text-2xl font-semibold text-slate-800 mb-6">Dashboard</h1>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-8">
        {[
          { key: 'bookings', label: 'Bookings', color: 'bg-blue-600' },
          { key: 'vehicles', label: 'Vehicles', color: 'bg-indigo-600' },
          { key: 'clients', label: 'Clients', color: 'bg-emerald-600' },
          { key: 'payments', label: 'Payments', color: 'bg-amber-600' },
          { key: 'staff', label: 'Staff', color: 'bg-slate-700' },
        ].map((c) => (
          <div key={c.key} className="rounded-lg bg-white p-5 shadow-sm border border-slate-200">
            <div className={`mb-2 h-1.5 w-8 rounded ${c.color}`} />
            <div className="text-3xl font-semibold text-slate-800">{map[c.key] ?? 0}</div>
            <div className="text-sm text-slate-500">{c.label}</div>
          </div>
        ))}
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <section className="rounded-lg bg-white border border-slate-200 shadow-sm">
          <div className="border-b border-slate-200 px-5 py-3 font-medium text-slate-700">Today&apos;s pickups</div>
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-slate-600">
              <tr><th className="px-5 py-2 text-left">Ref</th><th className="px-5 py-2 text-left">Client</th><th className="px-5 py-2 text-left">Vehicle</th><th className="px-5 py-2 text-left">Status</th></tr>
            </thead>
            <tbody>
              {todayBookings.map((b) => {
                const client = Array.isArray(b.clients) ? b.clients[0] : b.clients;
                const vehicle = Array.isArray(b.vehicles) ? b.vehicles[0] : b.vehicles;
                return (
                  <tr key={b.id} className="border-t border-slate-100">
                    <td className="px-5 py-2 font-medium text-slate-700">{b.ref}</td>
                    <td className="px-5 py-2 text-slate-600">{client?.full_name}</td>
                    <td className="px-5 py-2 text-slate-600">{vehicle?.reg_no}</td>
                    <td className="px-5 py-2"><StatusBadge status={b.status} /></td>
                  </tr>
                );
              })}
              {todayBookings.length === 0 && <tr><td colSpan={4} className="px-5 py-4 text-slate-400">No pickups today.</td></tr>}
            </tbody>
          </table>
        </section>

        <section className="rounded-lg bg-white border border-slate-200 shadow-sm">
          <div className="border-b border-slate-200 px-5 py-3 font-medium text-slate-700">Recent payments</div>
          <table className="w-full text-sm">
            <thead className="bg-slate-50 text-slate-600">
              <tr><th className="px-5 py-2 text-left">Txn</th><th className="px-5 py-2 text-left">Client</th><th className="px-5 py-2 text-right">Amount</th><th className="px-5 py-2 text-left">Status</th></tr>
            </thead>
            <tbody>
              {recentPayments.map((p) => {
                const client = Array.isArray(p.clients) ? p.clients[0] : p.clients;
                return (
                  <tr key={p.id} className="border-t border-slate-100">
                    <td className="px-5 py-2 font-medium text-slate-700">{p.txn_id}</td>
                    <td className="px-5 py-2 text-slate-600">{client?.full_name}</td>
                    <td className="px-5 py-2 text-right text-slate-700">{money(p.amount)}</td>
                    <td className="px-5 py-2"><StatusBadge status={p.status} /></td>
                  </tr>
                );
              })}
              {recentPayments.length === 0 && <tr><td colSpan={4} className="px-5 py-4 text-slate-400">No payments yet.</td></tr>}
            </tbody>
          </table>
        </section>
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const colors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    active: 'bg-indigo-100 text-indigo-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    successful: 'bg-emerald-100 text-emerald-700',
    failed: 'bg-red-100 text-red-700',
    refunded: 'bg-slate-100 text-slate-600',
    on_hire: 'bg-indigo-100 text-indigo-700',
    maintenance: 'bg-orange-100 text-orange-700',
    available: 'bg-green-100 text-green-700',
  };
  return (
    <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${colors[status] ?? 'bg-slate-100 text-slate-600'}`}>
      {status.replace('_', ' ')}
    </span>
  );
}
