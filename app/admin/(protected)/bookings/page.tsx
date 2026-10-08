import Link from 'next/link';
import { supabase } from '@/lib/supabase';
import { money, fmtDate, Badge } from '@/lib/helpers';

export const metadata = {
  title: 'Bookings',
};

interface BookingRow {
  id: number;
  ref: string;
  status: string;
  pickup_at: string;
  return_at: string;
  total: number;
  source: string;
  client_id: number;
  vehicle_id: number;
  clients: { id: number; full_name: string } | { id: number; full_name: string }[] | null;
  vehicles: { id: number; reg_no: string } | { id: number; reg_no: string }[] | null;
}

/** Strip characters that would break a PostgREST `or` filter expression. */
function orSafe(value: string): string {
  return value.replace(/[(),\\"]/g, '');
}

export default async function BookingsPage({
  searchParams,
}: {
  searchParams?: Promise<{ q?: string; status?: string }>;
}) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let qb = supabase
    .from('bookings')
    .select(
      'id, ref, status, pickup_at, return_at, total, source, client_id, vehicle_id, clients!inner(id, full_name), vehicles!inner(id, reg_no)'
    )
    .order('created_at', { ascending: false })
    .limit(100);

  if (q) {
    const pat = `%${orSafe(q)}%`;
    const [clientsRes, vehiclesRes] = await Promise.all([
      supabase.from('clients').select('id').ilike('full_name', pat),
      supabase.from('vehicles').select('id').ilike('reg_no', pat),
    ]);
    if (clientsRes.error) throw clientsRes.error;
    if (vehiclesRes.error) throw vehiclesRes.error;
    const clientIds = (clientsRes.data ?? []).map((r: { id: number }) => r.id);
    const vehicleIds = (vehiclesRes.data ?? []).map((r: { id: number }) => r.id);
    const ors = [`ref.ilike.${pat}`];
    if (clientIds.length) ors.push(`client_id.in.(${clientIds.join(',')})`);
    if (vehicleIds.length) ors.push(`vehicle_id.in.(${vehicleIds.join(',')})`);
    qb = qb.or(ors.join(','));
  }
  if (status) {
    qb = qb.eq('status', status);
  }

  const { data, error } = await qb;
  if (error) throw error;
  const rows = (data ?? []) as unknown as BookingRow[];

  const statuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'overdue'];

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" className="flex flex-wrap items-center gap-3 flex-1">
          <div className="relative">
            <i data-lucide="search" className="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
            <input
              name="q"
              defaultValue={q}
              placeholder="Search ref, client, reg..."
              className="input !pl-9 w-64"
            />
          </div>
          <select name="status" defaultValue={status} className="input w-40">
            <option value="">All Statuses</option>
            {statuses.map((s) => (
              <option key={s} value={s}>
                {s.charAt(0).toUpperCase() + s.slice(1)}
              </option>
            ))}
          </select>
          <button className="btn-secondary" type="submit">
            Filter
          </button>
        </form>
        <a href="#" className="btn-secondary">
          <i data-lucide="calendar-days" className="w-4 h-4"></i> Calendar
        </a>
        <a href="#" className="btn-primary">
          <i data-lucide="plus" className="w-4 h-4"></i> New Booking
        </a>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full rsp">
          <thead>
            <tr>
              <th className="th">Ref</th>
              <th className="th">Client</th>
              <th className="th">Vehicle</th>
              <th className="th">Pickup</th>
              <th className="th">Return</th>
              <th className="th">Status</th>
              <th className="th">Total</th>
              <th className="th"></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((b) => {
              const client = Array.isArray(b.clients) ? b.clients[0] : b.clients;
              const vehicle = Array.isArray(b.vehicles) ? b.vehicles[0] : b.vehicles;
              return (
                <tr key={b.id} className="table-row">
                  <td className="td font-medium text-slate-800" data-label="Ref">{b.ref}</td>
                  <td className="td" data-label="Client">{client?.full_name}</td>
                  <td className="td" data-label="Vehicle">
                    {vehicle?.reg_no}
                  </td>
                  <td className="td" data-label="Pickup">{fmtDate(b.pickup_at)}</td>
                  <td className="td" data-label="Return">{fmtDate(b.return_at)}</td>
                  <td className="td" data-label="Status">
                    <Badge status={b.status} />
                  </td>
                  <td className="td font-medium" data-label="Total">{money(b.total)}</td>
                  <td className="td text-right" data-label="">
                    <Link href={`/admin/bookings/${b.id}`} className="text-blue-600 text-sm hover:underline">
                      View
                    </Link>
                  </td>
                </tr>
              );
            })}
            {rows.length === 0 && (
              <tr>
                <td colSpan={8} className="td text-center py-10 text-slate-400">
                  No bookings found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between px-6 py-4">
        <p className="text-sm text-slate-500">Showing {rows.length} bookings</p>
      </div>
    </div>
  );
}
