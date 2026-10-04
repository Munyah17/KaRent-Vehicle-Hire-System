import Link from 'next/link';
import { supabase } from '@/lib/supabase';
import { money, fmtDate, Badge } from '@/lib/helpers';

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

export default async function BookingsPage({ searchParams }: { searchParams?: Promise<{ q?: string; status?: string }> }) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let qb = supabase
    .from('bookings')
    .select('id, ref, status, pickup_at, return_at, total, source, client_id, vehicle_id, clients!inner(id, full_name), vehicles!inner(id, reg_no)')
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
            {rows.map((b) => {
              const client = Array.isArray(b.clients) ? b.clients[0] : b.clients;
              const vehicle = Array.isArray(b.vehicles) ? b.vehicles[0] : b.vehicles;
              return (
                <tr key={b.id} className="border-t border-slate-100 hover:bg-slate-50">
                  <td className="px-5 py-3 font-medium text-slate-700"><Link href={`/admin/bookings/${b.id}`} className="hover:underline">{b.ref}</Link></td>
                  <td className="px-5 py-3 text-slate-600">{client?.full_name}</td>
                  <td className="px-5 py-3 text-slate-600">{vehicle?.reg_no}</td>
                  <td className="px-5 py-3 text-slate-600">{fmtDate(b.pickup_at)}</td>
                  <td className="px-5 py-3 text-slate-600">{fmtDate(b.return_at)}</td>
                  <td className="px-5 py-3 text-right text-slate-700">{money(b.total)}</td>
                  <td className="px-5 py-3"><Badge status={b.status} /></td>
                </tr>
              );
            })}
            {rows.length === 0 && <tr><td colSpan={7} className="px-5 py-6 text-slate-400">No bookings found.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
