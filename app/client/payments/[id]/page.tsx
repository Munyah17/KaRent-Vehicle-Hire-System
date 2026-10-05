import Link from 'next/link';
import { notFound } from 'next/navigation';
import { supabase } from '@/lib/supabase';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient } from '@/components/client/data';
import type { Payment } from '@/components/client/data';

export const metadata = { title: 'Payment' };

const ucwords = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

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
    <div className="max-w-2xl">
      <div className="card">
        <div className="flex items-center justify-between mb-5">
          <div>
            <h2 className="text-lg font-semibold text-slate-800">{payment.txn_id}</h2>
            <p className="text-sm text-slate-500">Created {fmtDateTime(payment.created_at)}</p>
          </div>
          <Badge status={payment.status} />
        </div>
        <dl className="text-sm space-y-2.5">
          <div className="flex justify-between">
            <dt className="text-slate-500">Amount</dt>
            <dd className="font-medium">{money(payment.amount)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Method</dt>
            <dd className="font-medium">{ucwords(payment.method)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Purpose</dt>
            <dd className="font-medium">{payment.purpose.charAt(0).toUpperCase() + payment.purpose.slice(1)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Reference</dt>
            <dd className="font-medium">{payment.reference ?? '—'}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Booking</dt>
            <dd className="font-medium">
              {payment.booking_id && payment.booking_ref ? (
                <Link href={`/client/bookings/${payment.booking_id}`} className="text-blue-600 hover:underline">
                  {payment.booking_ref}
                </Link>
              ) : (
                '—'
              )}
            </dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Paid at</dt>
            <dd className="font-medium">{fmtDateTime(payment.paid_at)}</dd>
          </div>
          {payment.notes && (
            <div className="flex justify-between">
              <dt className="text-slate-500">Notes</dt>
              <dd className="font-medium">{payment.notes}</dd>
            </div>
          )}
        </dl>
        <div className="flex flex-wrap items-end gap-3 mt-6">
          <Link href="/client/payments" className="btn-secondary">
            Back to payments
          </Link>
          {payment.booking_id && (
            <Link href={`/client/bookings/${payment.booking_id}`} className="btn-secondary">
              View booking
            </Link>
          )}
        </div>
      </div>
    </div>
  );
}
