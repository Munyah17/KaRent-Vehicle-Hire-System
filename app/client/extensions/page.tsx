import Link from 'next/link';
import { supabase } from '@/lib/supabase';
import { money, fmtDate, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listClientExtensions } from '@/components/client/data';

export const metadata = { title: 'Extensions' };

export default async function ExtensionsPage() {
  const { client } = await requireClient();
  const [extsRes, eligibleRes] = await Promise.all([
    listClientExtensions(client.id),
    supabase
      .from('bookings')
      .select('id, ref, vehicles!inner(make, model), return_at')
      .eq('client_id', client.id)
      .in('status', ['active', 'confirmed']),
  ]);

  if (eligibleRes.error) throw new Error(`eligible bookings: ${eligibleRes.error.message}`);

  const eligible = (eligibleRes.data ?? []).map((row) => {
    const typed = row as Record<string, unknown>;
    const vehicles = typed.vehicles as { make: string; model: string };
    return {
      id: typed.id as number,
      ref: typed.ref as string,
      make: vehicles.make,
      model: vehicles.model,
      return_at: typed.return_at as string,
    };
  });

  return (
    <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div className="xl:col-span-2 card !p-0 overflow-x-auto">
        <div className="px-6 py-4 border-b border-gray-100">
          <h2 className="font-semibold text-slate-800">Extension Requests</h2>
        </div>
        <table className="w-full">
          <thead>
            <tr>
              <th className="th">Booking</th>
              <th className="th">Vehicle</th>
              <th className="th">New Return</th>
              <th className="th">Extra Cost</th>
              <th className="th">Status</th>
              <th className="th"></th>
            </tr>
          </thead>
          <tbody>
            {extsRes.map((x) => (
              <tr className="table-row" key={x.id}>
                <td className="td font-medium">{x.booking_ref}</td>
                <td className="td">
                  {x.make} {x.model}
                </td>
                <td className="td">{fmtDateTime(x.new_return_at)}</td>
                <td className="td">{money(x.additional_amount)}</td>
                <td className="td">
                  <Badge status={x.status} />
                </td>
                <td className="td text-right">
                  <Link href={`/client/bookings/${x.booking_id}`} className="text-blue-600 text-sm hover:underline">
                    Booking
                  </Link>
                </td>
              </tr>
            ))}
            {!extsRes.length && (
              <tr>
                <td colSpan={6} className="td text-center py-10 text-slate-400">
                  No extension requests.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="card">
        <h3 className="font-semibold text-slate-800 mb-2">Request an extension</h3>
        <p className="text-sm text-slate-500 mb-4">Open an eligible booking and choose a new return date.</p>
        <ul className="space-y-2 text-sm">
          {eligible.map((b) => (
            <li key={b.id} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
              <span>
                {b.make} {b.model} <span className="text-slate-400">(due {fmtDate(b.return_at)})</span>
              </span>
              <Link href={`/client/bookings/${b.id}#extend`} className="text-blue-600 hover:underline">
                Extend
              </Link>
            </li>
          ))}
          {!eligible.length && <li className="text-slate-400">No active or confirmed bookings.</li>}
        </ul>
      </div>
    </div>
  );
}
