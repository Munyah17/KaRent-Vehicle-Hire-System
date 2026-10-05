import Link from 'next/link';
import { Plus } from 'lucide-react';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listBookings } from '@/components/client/data';

export const metadata = { title: 'My Bookings' };

export default async function BookingsPage() {
  const { client } = await requireClient();
  const bookings = await listBookings(client.id);

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 className="font-semibold text-slate-800">My Bookings</h2>
        <Link href="/vehicles" className="btn-primary">
          <Plus className="w-4 h-4" /> New booking
        </Link>
      </div>
      <table className="w-full">
        <thead>
          <tr>
            <th className="th">Ref</th>
            <th className="th">Vehicle</th>
            <th className="th">Pickup</th>
            <th className="th">Return</th>
            <th className="th">Status</th>
            <th className="th">Total</th>
            <th className="th"></th>
          </tr>
        </thead>
        <tbody>
          {bookings.map((b) => (
            <tr className="table-row" key={b.id}>
              <td className="td font-medium">{b.ref}</td>
              <td className="td">
                {b.make} {b.model} <span className="text-xs text-slate-400">({b.reg_no})</span>
              </td>
              <td className="td">{fmtDateTime(b.pickup_at)}</td>
              <td className="td">{fmtDateTime(b.return_at)}</td>
              <td className="td">
                <Badge status={b.status} />
              </td>
              <td className="td font-medium">{money(b.total)}</td>
              <td className="td text-right">
                <Link href={`/client/bookings/${b.id}`} className="text-blue-600 text-sm hover:underline">
                  View
                </Link>
              </td>
            </tr>
          ))}
          {!bookings.length && (
            <tr>
              <td colSpan={7} className="td text-center py-10 text-slate-400">
                No bookings yet.{' '}
                <Link href="/vehicles" className="text-blue-600">
                  Browse vehicles
                </Link>
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
