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
      <div className="card mb-6">
        <div className="flex flex-wrap items-center gap-4">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={photo}
            alt={`${b.make} ${b.model}`}
            className="w-20 h-14 object-cover rounded-lg border border-gray-200"
          />
          <div className="flex-1">
            <div className="flex items-center gap-3">
              <h2 className="text-lg font-semibold text-slate-800">{b.ref}</h2>
              <Badge status={b.status} />
            </div>
            <p className="text-sm text-slate-500">
              {b.make} {b.model} ({b.reg_no}) · {fmtDateTime(b.pickup_at)} → {fmtDateTime(b.return_at)}
            </p>
          </div>
          {canPay && (
            <div className="text-right">
              <p className="text-xs text-slate-400">Outstanding</p>
              <p className="text-xl font-semibold text-red-600">{money(outstanding)}</p>
            </div>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div className="xl:col-span-2 space-y-6">
          <div className="card">
            <h3 className="font-semibold text-slate-800 mb-4">Financial summary</h3>
            <dl className="text-sm space-y-2.5">
              <div className="flex justify-between">
                <dt className="text-slate-500">Rental</dt>
                <dd className="font-medium">{money(b.base_amount)}</dd>
              </div>
              {charges.map((c) => (
                <div className="flex justify-between" key={c.id}>
                  <dt className="text-slate-500">{c.label}</dt>
                  <dd className="font-medium">{money(c.amount)}</dd>
                </div>
              ))}
              {!charges.length && (
                <div className="flex justify-between">
                  <dt className="text-slate-500">Additional charges</dt>
                  <dd className="font-medium">{money(b.additional_amount)}</dd>
                </div>
              )}
              <div className="flex justify-between">
                <dt className="text-slate-500">Discount</dt>
                <dd className="font-medium text-green-600">−{money(b.discount)}</dd>
              </div>
              <div className="flex justify-between border-t border-gray-100 pt-2.5">
                <dt className="font-semibold">Total</dt>
                <dd className="font-semibold">{money(b.total)}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-slate-500">Paid</dt>
                <dd className="font-medium text-green-600">{money(paid)}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-slate-500">Outstanding</dt>
                <dd className={`font-semibold ${outstanding > 0 ? 'text-red-600' : 'text-green-600'}`}>
                  {money(outstanding)}
                </dd>
              </div>
              {deposit && (
                <div className="flex justify-between border-t border-gray-100 pt-2.5">
                  <dt className="text-slate-500">Deposit held</dt>
                  <dd className="font-medium">
                    {money(depositHeld)} <Badge status={deposit.status} />
                  </dd>
                </div>
              )}
            </dl>
          </div>

          {canPay && (
            <div className="card">
              <h3 className="font-semibold text-slate-800 mb-4">Make a payment</h3>
              <PayWalletForm bookingId={bookingId} outstanding={outstanding} walletBalance={wallet} />
            </div>
          )}

          <div className="card" id="extend">
            <h3 className="font-semibold text-slate-800 mb-4">Request extension</h3>
            {canExtend ? (
              <ExtensionForm bookingId={bookingId} minDate={minReturn} />
            ) : (
              <p className="text-sm text-slate-400">
                Extensions are available for confirmed and active bookings.
              </p>
            )}
            {extensions.length > 0 && (
              <ul className="mt-4 space-y-2 text-sm">
                {extensions.map((x) => (
                  <li key={x.id} className="flex items-center justify-between">
                    <span>
                      {fmtDate(x.new_return_at)} (+{money(x.additional_amount)})
                    </span>
                    <Badge status={x.status} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div className="space-y-6">
          <div className="card">
            <h3 className="font-semibold text-slate-800 mb-4">Documents</h3>
            <ul className="space-y-2 text-sm">
              {contracts.map((c) => (
                <li key={c.id} className="flex items-center justify-between">
                  <span className="flex items-center gap-2">
                    <FileText className="w-4 h-4 text-gray-400" />
                    {c.title}
                  </span>
                  <Link
                    href={`/client/documents/${c.id}`}
                    target="_blank"
                    className="text-blue-600 hover:underline"
                  >
                    View
                  </Link>
                </li>
              ))}
              {!contracts.length && <li className="text-slate-400">No documents yet.</li>}
            </ul>
          </div>
          <div className="card !p-0 overflow-x-auto">
            <div className="px-6 py-4 border-b border-gray-100">
              <h3 className="font-semibold text-slate-800">Payments</h3>
            </div>
            <ul className="divide-y divide-gray-100 text-sm">
              {payments.map((p) => (
                <li key={p.id} className="px-6 py-3 flex items-center justify-between">
                  <div>
                    <p className="font-medium">{p.txn_id}</p>
                    <p className="text-xs text-slate-400">
                      {p.method.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())} ·{' '}
                      {fmtDateTime(p.paid_at ?? p.created_at)}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="font-semibold">{money(p.amount)}</p>
                    <Badge status={p.status} />
                  </div>
                </li>
              ))}
              {!payments.length && <li className="px-6 py-6 text-slate-400">No payments yet.</li>}
            </ul>
          </div>
        </div>
      </div>
    </>
  );
}
