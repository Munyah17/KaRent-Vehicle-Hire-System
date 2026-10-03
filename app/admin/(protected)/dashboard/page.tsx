import { query } from '@/lib/db';
import { money, fmtDate } from '@/lib/helpers';

export default async function DashboardPage() {
  const counts = await query<{ label: string; value: number }>(`
    SELECT 'bookings' AS label, COUNT(*) AS value FROM bookings
    UNION ALL SELECT 'vehicles', COUNT(*) FROM vehicles
    UNION ALL SELECT 'clients', COUNT(*) FROM clients
    UNION ALL SELECT 'payments', COUNT(*) FROM payments
    UNION ALL SELECT 'staff', COUNT(*) FROM users WHERE role_id IN (1,2)
  `);
  const map = Object.fromEntries(counts.map((r) => [r.label, r.value]));

  const todayBookings = await query<any>(`
    SELECT b.id, b.ref, c.full_name, v.reg_no, b.pickup_at, b.status
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    JOIN vehicles v ON v.id = b.vehicle_id
    WHERE DATE(b.pickup_at) = CURDATE()
    ORDER BY b.pickup_at ASC
    LIMIT 5
  `);

  const recentPayments = await query<any>(`
    SELECT p.id, p.txn_id, p.amount, p.method, p.status, p.paid_at, c.full_name
    FROM payments p
    JOIN clients c ON c.id = p.client_id
    ORDER BY p.created_at DESC
    LIMIT 5
  `);

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
              {todayBookings.map((b) => (
                <tr key={b.id} className="border-t border-slate-100">
                  <td className="px-5 py-2 font-medium text-slate-700">{b.ref}</td>
                  <td className="px-5 py-2 text-slate-600">{b.full_name}</td>
                  <td className="px-5 py-2 text-slate-600">{b.reg_no}</td>
                  <td className="px-5 py-2"><StatusBadge status={b.status} /></td>
                </tr>
              ))}
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
              {recentPayments.map((p) => (
                <tr key={p.id} className="border-t border-slate-100">
                  <td className="px-5 py-2 font-medium text-slate-700">{p.txn_id}</td>
                  <td className="px-5 py-2 text-slate-600">{p.full_name}</td>
                  <td className="px-5 py-2 text-right text-slate-700">{money(p.amount)}</td>
                  <td className="px-5 py-2"><StatusBadge status={p.status} /></td>
                </tr>
              ))}
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
