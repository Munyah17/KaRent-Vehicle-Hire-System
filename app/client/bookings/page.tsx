import Link from 'next/link';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listBookings } from '@/components/client/data';

export const metadata = { title: 'My Bookings' };

export default async function BookingsPage() {
  const { client } = await requireClient();
  const bookings = await listBookings(client.id);

  return (
    <div className="cp-card flush">
      <div className="hd">
        <h2>My Bookings</h2>
        <Link href="/vehicles" className="button" style={{ padding: '9px 16px', fontSize: '.82rem' }}>
          New booking
        </Link>
      </div>
      <div style={{ overflowX: 'auto' }}>
        <table className="cp-table">
          <thead>
            <tr>
              <th>Ref</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Status</th>
              <th className="r">Total</th><th></th>
            </tr>
          </thead>
          <tbody>
            {bookings.map((b) => (
              <tr key={b.id}>
                <td style={{ fontWeight: 700 }}>{b.ref}</td>
                <td>
                  {b.make} {b.model} <span className="muted" style={{ fontSize: '.78rem' }}>({b.reg_no})</span>
                </td>
                <td>{fmtDateTime(b.pickup_at)}</td>
                <td>{fmtDateTime(b.return_at)}</td>
                <td><Badge status={b.status} /></td>
                <td className="r" style={{ fontWeight: 700 }}>{money(b.total)}</td>
                <td className="r">
                  <Link href={`/client/bookings/${b.id}`} style={{ color: '#087f70', fontWeight: 600 }}>
                    View
                  </Link>
                </td>
              </tr>
            ))}
            {!bookings.length && (
              <tr>
                <td colSpan={7} className="cp-empty">
                  No bookings yet. <Link href="/vehicles" style={{ color: '#087f70' }}>Browse vehicles</Link>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
