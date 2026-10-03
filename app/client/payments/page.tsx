import Link from 'next/link';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listPayments } from '@/components/client/data';

export const metadata = { title: 'Payments' };

export default async function PaymentsPage() {
  const { client } = await requireClient();
  const payments = await listPayments(client.id);

  return (
    <div className="cp-card flush">
      <div className="hd"><h2>Payment History</h2></div>
      <div style={{ overflowX: 'auto' }}>
        <table className="cp-table">
          <thead>
            <tr>
              <th>Txn ID</th><th>Booking</th><th>Method</th><th>Purpose</th><th>Status</th>
              <th>Date</th><th className="r">Amount</th><th></th>
            </tr>
          </thead>
          <tbody>
            {payments.map((p) => (
              <tr key={p.id}>
                <td style={{ fontWeight: 700 }}>{p.txn_id}</td>
                <td>
                  {p.booking_id && p.booking_ref ? (
                    <Link href={`/client/bookings/${p.booking_id}`} style={{ color: '#087f70' }}>{p.booking_ref}</Link>
                  ) : '—'}
                </td>
                <td style={{ textTransform: 'capitalize' }}>{p.method.replace(/_/g, ' ')}</td>
                <td style={{ textTransform: 'capitalize' }}>{p.purpose}</td>
                <td><Badge status={p.status} /></td>
                <td>{fmtDateTime(p.paid_at ?? p.created_at)}</td>
                <td className="r" style={{ fontWeight: 700 }}>{money(p.amount)}</td>
                <td className="r">
                  <Link href={`/client/payments/${p.id}`} style={{ color: '#087f70', fontWeight: 600 }}>View</Link>
                </td>
              </tr>
            ))}
            {!payments.length && (
              <tr><td colSpan={8} className="cp-empty">No payments yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
