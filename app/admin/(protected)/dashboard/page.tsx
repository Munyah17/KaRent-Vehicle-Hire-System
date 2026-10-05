import { supabase } from '@/lib/supabase';
import { money, fmtDate } from '@/lib/helpers';

export const metadata = {
  title: 'Dashboard',
};

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

  const kpis = [
    { label: 'Bookings', value: map.bookings, sub: 'total reservations', icon: 'fa-calendar-check', color: 'c1' },
    { label: 'Vehicles', value: map.vehicles, sub: 'fleet size', icon: 'fa-car-side', color: 'c2' },
    { label: 'Clients', value: map.clients, sub: 'registered clients', icon: 'fa-users', color: 'c3' },
    { label: 'Payments', value: map.payments, sub: 'transactions', icon: 'fa-credit-card', color: 'c4' },
  ];

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Welcome back</h1>
          <p className="subtitle">Here&apos;s what&apos;s happening with your fleet today.</p>
        </div>
        <div className="page-header__actions">
          <a className="m-btn m-btn--ghost" href="#">
            <i className="fa-solid fa-plus"></i> New booking
          </a>
          <a className="m-btn m-btn--ghost" href="#">
            <i className="fa-solid fa-download"></i> Reports
          </a>
        </div>
      </div>

      <div className="row row-tight">
        {kpis.map((kpi) => (
          <div className="col-sm-6 col-lg-3" key={kpi.label}>
            <article className="stat-card">
              <div className="stat-card__head">
                <p className="stat-card__label">{kpi.label}</p>
                <span className={`stat-card__icon stat-card__icon--${kpi.color}`}>
                  <i className={`fa-solid ${kpi.icon}`}></i>
                </span>
              </div>
              <p className="stat-card__value">{kpi.value}</p>
              <p className="stat-card__delta">
                <span className="stat-card__delta-period">{kpi.sub}</span>
              </p>
            </article>
          </div>
        ))}
      </div>

      <div className="row row-tight" style={{ marginTop: 16 }}>
        <div className="col-sm-6 col-lg-3">
          <article className="stat-card">
            <div className="stat-card__head">
              <p className="stat-card__label">Staff</p>
              <span className="stat-card__icon stat-card__icon--c1">
                <i className="fa-solid fa-user-gear"></i>
              </span>
            </div>
            <p className="stat-card__value">{map.staff}</p>
            <p className="stat-card__delta">
              <span className="stat-card__delta-period">team members</span>
            </p>
          </article>
        </div>
      </div>

      <div className="row row-tight" style={{ marginTop: 16 }}>
        <div className="col-lg-6">
          <div className="card !p-0 overflow-x-auto">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h3 className="font-semibold text-slate-800">Today&apos;s pickups</h3>
              <a href="/admin/bookings" className="text-sm text-blue-600 hover:underline">
                View all
              </a>
            </div>
            <ul className="divide-y divide-gray-100">
              {todayBookings.map((b) => {
                const client = Array.isArray(b.clients) ? b.clients[0] : b.clients;
                const vehicle = Array.isArray(b.vehicles) ? b.vehicles[0] : b.vehicles;
                return (
                  <li
                    key={b.id}
                    className="flex items-center justify-between px-6 py-3.5"
                  >
                    <div className="flex items-center gap-3">
                      <span className="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                        <i data-lucide="car" className="w-4 h-4"></i>
                      </span>
                      <div>
                        <p className="text-sm font-medium text-slate-800">
                          {client?.full_name}
                        </p>
                        <p className="text-xs text-slate-500">{vehicle?.reg_no}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <StatusBadge status={b.status} />
                      <p className="text-xs text-slate-400 mt-1">
                        {fmtDate(b.pickup_at)}
                      </p>
                    </div>
                  </li>
                );
              })}
              {todayBookings.length === 0 && (
                <li className="px-6 py-10 text-center text-sm text-slate-400">
                  No upcoming bookings.
                </li>
              )}
            </ul>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card !p-0 overflow-x-auto">
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h3 className="font-semibold text-slate-800">Recent payments</h3>
              <a href="/admin/payments" className="text-sm text-blue-600 hover:underline">
                View all
              </a>
            </div>
            <ul className="divide-y divide-gray-100">
              {recentPayments.map((p) => {
                const client = Array.isArray(p.clients) ? p.clients[0] : p.clients;
                return (
                  <li
                    key={p.id}
                    className="flex items-center justify-between px-6 py-3"
                  >
                    <div className="flex items-center gap-3">
                      <span className="w-8 h-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                        <i data-lucide="banknote" className="w-4 h-4"></i>
                      </span>
                      <div>
                        <p className="text-sm text-slate-700">
                          {p.txn_id}{' '}
                          <span className="text-slate-400">&middot; {client?.full_name}</span>
                        </p>
                        <p className="text-xs text-slate-400">{p.method}</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-sm font-medium text-slate-800">{money(p.amount)}</p>
                      <StatusBadge status={p.status} />
                    </div>
                  </li>
                );
              })}
              {recentPayments.length === 0 && (
                <li className="px-6 py-10 text-center text-sm text-slate-400">
                  No payments yet.
                </li>
              )}
            </ul>
          </div>
        </div>
      </div>
    </>
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
    <span
      className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${colors[status] ?? 'bg-slate-100 text-slate-600'}`}
    >
      {status.replace('_', ' ')}
    </span>
  );
}
