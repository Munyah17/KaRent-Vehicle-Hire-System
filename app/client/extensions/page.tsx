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
    <div className="cp-grid32">
      <div className="cp-card flush">
        <div className="hd"><h2>Extension Requests</h2></div>
        <div style={{ overflowX: 'auto' }}>
          <table className="cp-table">
            <thead>
              <tr><th>Booking</th><th>Vehicle</th><th>New Return</th><th className="r">Extra Cost</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              {extsRes.map((x) => (
                <tr key={x.id}>
                  <td style={{ fontWeight: 700 }}>{x.booking_ref}</td>
                  <td>{x.make} {x.model}</td>
                  <td>{fmtDateTime(x.new_return_at)}</td>
                  <td className="r">{money(x.additional_amount)}</td>
                  <td><Badge status={x.status} /></td>
                  <td className="r">
                    <Link href={`/client/bookings/${x.booking_id}`} style={{ color: '#087f70', fontWeight: 600 }}>Booking</Link>
                  </td>
                </tr>
              ))}
              {!extsRes.length && <tr><td colSpan={6} className="cp-empty">No extension requests.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      <div className="cp-card">
        <h3>Request an extension</h3>
        <p className="muted" style={{ fontSize: '.86rem', marginTop: -6 }}>
          Open an eligible booking and choose a new return date.
        </p>
        {eligible.length ? (
          <ul className="cp-list" style={{ margin: '0 -24px -24px' }}>
            {eligible.map((b) => (
              <li key={b.id}>
                <span>
                  {b.make} {b.model} <span className="t">(due {fmtDate(b.return_at)})</span>
                </span>
                <Link href={`/client/bookings/${b.id}#extend`} style={{ color: '#087f70', fontWeight: 600 }}>Extend</Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="muted" style={{ fontSize: '.9rem' }}>No active or confirmed bookings.</p>
        )}
      </div>
    </div>
  );
}
