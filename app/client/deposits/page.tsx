import Link from 'next/link';
import { money } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listDeposits } from '@/components/client/data';

export const metadata = { title: 'My Deposits' };

export default async function DepositsPage() {
  const { client } = await requireClient();
  const deposits = await listDeposits(client.id);

  return (
    <div className="cp-card flush">
      <div className="hd"><h2>Security Deposits</h2></div>
      <div style={{ overflowX: 'auto' }}>
        <table className="cp-table">
          <thead>
            <tr>
              <th>Booking</th><th>Vehicle</th><th className="r">Required</th><th className="r">Received</th>
              <th className="r">Deductions</th><th className="r">Refunded</th><th className="r">Held</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            {deposits.map((d) => {
              const held = Number(d.received_amount) - Number(d.deducted_amount) - Number(d.refunded_amount);
              return (
                <tr key={d.id}>
                  <td style={{ fontWeight: 700 }}>
                    <Link href={`/client/bookings/${d.booking_id}`} style={{ color: '#087f70' }}>{d.booking_ref}</Link>
                  </td>
                  <td>{d.make} {d.model}</td>
                  <td className="r">{money(d.required_amount)}</td>
                  <td className="r">{money(d.received_amount)}</td>
                  <td className="r" style={{ color: '#c0392b' }}>{money(d.deducted_amount)}</td>
                  <td className="r">{money(d.refunded_amount)}</td>
                  <td className="r" style={{ fontWeight: 700 }}>{money(held)}</td>
                  <td><Badge status={d.status} /></td>
                </tr>
              );
            })}
            {!deposits.length && <tr><td colSpan={8} className="cp-empty">No deposits yet.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
