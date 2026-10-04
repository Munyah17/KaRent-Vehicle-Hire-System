import Link from 'next/link';
import { notFound } from 'next/navigation';
import { supabase } from '@/lib/supabase';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient } from '@/components/client/data';
import type { Payment } from '@/components/client/data';

export const metadata = { title: 'Payment' };

export default async function PaymentDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const { client } = await requireClient();

  const { data: p, error } = await supabase
    .from('payments')
    .select('*, bookings(ref)')
    .eq('id', Number(id))
    .eq('client_id', client.id)
    .maybeSingle();
  if (error) throw new Error(`payment: ${error.message}`);
  if (!p) notFound();

  const payment: Payment = {
    ...(p as unknown as Payment),
    booking_ref: (p as unknown as { bookings: { ref: string } | null }).bookings?.ref ?? null,
  };

  return (
    <div style={{ maxWidth: 680 }}>
      <div className="cp-card">
        <div className="row" style={{ marginBottom: 18 }}>
          <div>
            <h2 style={{ margin: 0 }}>{payment.txn_id}</h2>
            <p className="muted" style={{ margin: '6px 0 0', fontSize: '.85rem' }}>
              Created {fmtDateTime(payment.created_at)}
            </p>
          </div>
          <Badge status={payment.status} />
        </div>
        <dl className="cp-dl">
          <div className="row"><dt>Amount</dt><dd>{money(payment.amount)}</dd></div>
          <div className="row"><dt>Method</dt><dd style={{ textTransform: 'capitalize' }}>{payment.method.replace(/_/g, ' ')}</dd></div>
          <div className="row"><dt>Purpose</dt><dd style={{ textTransform: 'capitalize' }}>{payment.purpose}</dd></div>
          <div className="row"><dt>Reference</dt><dd>{payment.reference ?? '—'}</dd></div>
          <div className="row">
            <dt>Booking</dt>
            <dd>
              {payment.booking_id && payment.booking_ref ? (
                <Link href={`/client/bookings/${payment.booking_id}`} style={{ color: '#087f70' }}>{payment.booking_ref}</Link>
              ) : '—'}
            </dd>
          </div>
          <div className="row"><dt>Paid at</dt><dd>{fmtDateTime(payment.paid_at)}</dd></div>
          {payment.notes && <div className="row"><dt>Notes</dt><dd>{payment.notes}</dd></div>}
        </dl>
        <div className="cp-actions" style={{ marginTop: 22 }}>
          <Link href="/client/payments" className="button secondary">Back to payments</Link>
          {payment.booking_id && (
            <Link href={`/client/bookings/${payment.booking_id}`} className="button secondary">View booking</Link>
          )}
        </div>
      </div>
    </div>
  );
}
