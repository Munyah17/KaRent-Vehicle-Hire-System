import Link from 'next/link';
import { notFound } from 'next/navigation';
import { one } from '@/lib/db';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient } from '@/components/client/data';
import type { Payment } from '@/components/client/data';

export const metadata = { title: 'Payment' };

export default async function PaymentDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const { client } = await requireClient();
  const p = await one<Payment>(
    `SELECT p.*, b.ref AS booking_ref FROM payments p
     LEFT JOIN bookings b ON b.id = p.booking_id
     WHERE p.id = ? AND p.client_id = ?`,
    [Number(id), client.id]
  );
  if (!p) notFound();

  return (
    <div style={{ maxWidth: 680 }}>
      <div className="cp-card">
        <div className="row" style={{ marginBottom: 18 }}>
          <div>
            <h2 style={{ margin: 0 }}>{p.txn_id}</h2>
            <p className="muted" style={{ margin: '6px 0 0', fontSize: '.85rem' }}>
              Created {fmtDateTime(p.created_at)}
            </p>
          </div>
          <Badge status={p.status} />
        </div>
        <dl className="cp-dl">
          <div className="row"><dt>Amount</dt><dd>{money(p.amount)}</dd></div>
          <div className="row"><dt>Method</dt><dd style={{ textTransform: 'capitalize' }}>{p.method.replace(/_/g, ' ')}</dd></div>
          <div className="row"><dt>Purpose</dt><dd style={{ textTransform: 'capitalize' }}>{p.purpose}</dd></div>
          <div className="row"><dt>Reference</dt><dd>{p.reference ?? '—'}</dd></div>
          <div className="row">
            <dt>Booking</dt>
            <dd>
              {p.booking_id && p.booking_ref ? (
                <Link href={`/client/bookings/${p.booking_id}`} style={{ color: '#087f70' }}>{p.booking_ref}</Link>
              ) : '—'}
            </dd>
          </div>
          <div className="row"><dt>Paid at</dt><dd>{fmtDateTime(p.paid_at)}</dd></div>
          {p.notes && <div className="row"><dt>Notes</dt><dd>{p.notes}</dd></div>}
        </dl>
        <div className="cp-actions" style={{ marginTop: 22 }}>
          <Link href="/client/payments" className="button secondary">Back to payments</Link>
          {p.booking_id && (
            <Link href={`/client/bookings/${p.booking_id}`} className="button secondary">View booking</Link>
          )}
        </div>
      </div>
    </div>
  );
}
