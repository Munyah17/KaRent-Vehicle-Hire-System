import Link from 'next/link';
import { notFound } from 'next/navigation';
import { FileText } from 'lucide-react';
import { supabase } from '@/lib/supabase';
import { money, fmtDate, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import ExtensionForm from '@/components/client/ExtensionForm';
import PayWalletForm from '@/components/client/PayWalletForm';
import { getPrimaryPhoto } from '@/components/public/data';
import {
  requireClient,
  findClientBooking,
  amountPaid,
  bookingOutstanding,
  walletBalance,
  listBookingExtensions,
} from '@/components/client/data';
import type { Payment, Contract } from '@/components/client/data';

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const { data, error } = await supabase.from('bookings').select('ref').eq('id', Number(id)).maybeSingle();
  if (error) throw new Error(`generateMetadata: ${error.message}`);
  return { title: data?.ref ? `Booking ${data.ref}` : 'Booking' };
}

export default async function BookingDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const bookingId = Number(id);
  const { client } = await requireClient();
  const b = await findClientBooking(client.id, bookingId);
  if (!b) notFound();

  const [
    paymentsRes,
    depositRes,
    extensions,
    contractsRes,
    paid,
    outstanding,
    wallet,
    photo,
    chargesRes,
  ] = await Promise.all([
    supabase
      .from('payments')
      .select('*')
      .eq('booking_id', bookingId)
      .order('id', { ascending: false }),
    supabase
      .from('deposits')
      .select('required_amount, received_amount, deducted_amount, refunded_amount, status')
      .eq('booking_id', bookingId)
      .maybeSingle(),
    listBookingExtensions(bookingId),
    supabase
      .from('contracts')
      .select('id, title, status')
      .eq('booking_id', bookingId)
      .neq('status', 'void')
      .order('id', { ascending: false }),
    amountPaid(bookingId),
    bookingOutstanding(bookingId),
    walletBalance(client.id),
    getPrimaryPhoto(b.vehicle_id),
    supabase
      .from('booking_charges')
      .select('id, label, amount, created_at')
      .eq('booking_id', bookingId)
      .order('id', { ascending: true }),
  ]);

  if (paymentsRes.error) throw new Error(`payments: ${paymentsRes.error.message}`);
  if (depositRes.error) throw new Error(`deposit: ${depositRes.error.message}`);
  if (contractsRes.error) throw new Error(`contracts: ${contractsRes.error.message}`);
  if (chargesRes.error) throw new Error(`charges: ${chargesRes.error.message}`);

  const payments = (paymentsRes.data ?? []) as Payment[];
  const deposit = depositRes.data as {
    required_amount: string;
    received_amount: string;
    deducted_amount: string;
    refunded_amount: string;
    status: string;
  } | null;
  const contracts = (contractsRes.data ?? []) as Contract[];
  const charges = chargesRes.data as { id: number; label: string; amount: string; created_at: string }[];

  const canPay = outstanding > 0 && ['pending', 'confirmed', 'active'].includes(b.status);
  const canExtend = ['active', 'confirmed'].includes(b.status);
  const minReturn = new Date(new Date(String(b.return_at).replace(' ', 'T')).getTime() + 86400000)
    .toISOString()
    .slice(0, 10);
  const depositHeld = deposit
    ? Number(deposit.received_amount) - Number(deposit.deducted_amount) - Number(deposit.refunded_amount)
    : 0;

  return (
    <>
      <div className="cp-card">
        <div className="cp-vehicle-head">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={photo} alt={`${b.make} ${b.model}`} />
          <div style={{ flex: 1, minWidth: 220 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
              <h2 style={{ margin: 0 }}>{b.ref}</h2>
              <Badge status={b.status} />
            </div>
            <p className="muted" style={{ margin: '6px 0 0', fontSize: '.88rem' }}>
              {b.make} {b.model} ({b.reg_no}) · {fmtDateTime(b.pickup_at)} → {fmtDateTime(b.return_at)}
            </p>
          </div>
          {canPay && (
            <div style={{ textAlign: 'right' }}>
              <div className="muted" style={{ fontSize: '.72rem', textTransform: 'uppercase', letterSpacing: '.05em' }}>Outstanding</div>
              <div style={{ fontSize: '1.3rem', fontWeight: 800, color: '#c0392b' }}>{money(outstanding)}</div>
            </div>
          )}
        </div>
      </div>

      <div className="cp-grid32">
        <div>
          <div className="cp-card">
            <h3>Financial summary</h3>
            <dl className="cp-dl">
              <div className="row"><dt>Rental</dt><dd>{money(b.base_amount)}</dd></div>
              {charges.map((c) => (
                <div className="row" key={c.id}><dt>{c.label}</dt><dd>{money(c.amount)}</dd></div>
              ))}
              {!charges.length && Number(b.additional_amount) > 0 && (
                <div className="row"><dt>Additional charges</dt><dd>{money(b.additional_amount)}</dd></div>
              )}
              <div className="row"><dt>Discount</dt><dd style={{ color: '#0a6b52' }}>−{money(b.discount)}</dd></div>
              <div className="row" style={{ fontWeight: 800 }}><dt style={{ color: 'var(--ink)' }}>Total</dt><dd>{money(b.total)}</dd></div>
              <div className="row"><dt>Paid</dt><dd style={{ color: '#0a6b52' }}>{money(paid)}</dd></div>
              <div className="row">
                <dt>Outstanding</dt>
                <dd style={{ color: outstanding > 0 ? '#c0392b' : '#0a6b52' }}>{money(outstanding)}</dd>
              </div>
              {deposit && (
                <div className="row">
                  <dt>Deposit held</dt>
                  <dd>{money(depositHeld)} <Badge status={deposit.status} /></dd>
                </div>
              )}
            </dl>
          </div>

          {canPay && (
            <div className="cp-card">
              <h3>Make a payment</h3>
              <PayWalletForm bookingId={bookingId} outstanding={outstanding} walletBalance={wallet} />
            </div>
          )}

          <div className="cp-card" id="extend">
            <h3>Request extension</h3>
            {canExtend ? (
              <ExtensionForm bookingId={bookingId} minDate={minReturn} />
            ) : (
              <p className="muted" style={{ fontSize: '.9rem' }}>
                Extensions are available for confirmed and active bookings.
              </p>
            )}
            {extensions.length > 0 && (
              <ul className="cp-list" style={{ margin: '16px -24px -24px' }}>
                {extensions.map((x) => (
                  <li key={x.id}>
                    <span>{fmtDate(x.new_return_at)} <span className="t">(+{money(x.additional_amount)})</span></span>
                    <Badge status={x.status} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div>
          <div className="cp-card">
            <h3>Documents</h3>
            {contracts.length ? (
              <ul className="cp-list" style={{ margin: '0 -24px -24px' }}>
                {contracts.map((c) => (
                  <li key={c.id}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                      <FileText size={16} color="#607477" /> {c.title}
                    </span>
                    <Link href={`/client/documents/${c.id}`} style={{ color: '#087f70', fontWeight: 600 }}>
                      View
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="muted" style={{ fontSize: '.9rem' }}>No documents yet.</p>
            )}
          </div>

          <div className="cp-card flush">
            <div className="hd"><h3>Payments</h3></div>
            {payments.length ? (
              <ul className="cp-list">
                {payments.map((p) => (
                  <li key={p.id}>
                    <div>
                      <p style={{ margin: 0, fontWeight: 600 }}>{p.txn_id}</p>
                      <span className="t">
                        {p.method.replace(/_/g, ' ')} · {fmtDateTime(p.paid_at ?? p.created_at)}
                      </span>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <div style={{ fontWeight: 700 }}>{money(p.amount)}</div>
                      <Badge status={p.status} />
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="cp-empty">No payments yet.</div>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
