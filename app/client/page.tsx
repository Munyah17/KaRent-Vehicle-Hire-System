import Link from 'next/link';
import { ArrowRight, Wallet, AlertCircle, PiggyBank } from 'lucide-react';
import { supabase } from '@/lib/supabase';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, walletBalance, walletTransactions, WalletTxn } from '@/components/client/data';
import type { Booking } from '@/components/client/data';

export const metadata = { title: 'Dashboard' };

function flattenBooking(row: Record<string, unknown>): Booking {
  const vehicle = (row.vehicles ?? {}) as Record<string, unknown>;
  return {
    id: row.id as number,
    ref: row.ref as string,
    client_id: row.client_id as number,
    vehicle_id: row.vehicle_id as number,
    pickup_at: row.pickup_at as string,
    return_at: row.return_at as string,
    status: row.status as string,
    base_amount: row.base_amount as string,
    additional_amount: row.additional_amount as string,
    discount: row.discount as string,
    total: row.total as string,
    deposit_required: row.deposit_required as string,
    notes: row.notes as string | null,
    created_at: row.created_at as string,
    make: vehicle.make as string,
    model: vehicle.model as string,
    reg_no: vehicle.reg_no as string,
    daily_rate: vehicle.daily_rate as string,
  };
}

function sumAmount(rows: unknown[]): number {
  return rows.reduce<number>((sum, row) => {
    const typed = row as { amount?: string | number; total?: string | number };
    const amount = typed.amount ?? typed.total ?? 0;
    return sum + Number(amount ?? 0);
  }, 0);
}

export default async function ClientDashboard() {
  const { client } = await requireClient();
  const cid = client.id;

  const nowIso = new Date().toISOString();
  const [
    currentRes,
    upcomingRes,
    outstandingRes,
    paymentsRes,
    depositsRes,
    wallet,
    recentTx,
  ] = await Promise.all([
    supabase
      .from('bookings')
      .select('*, vehicles!inner(make, model, reg_no, daily_rate)')
      .eq('client_id', cid)
      .in('status', ['active', 'overdue'])
      .order('return_at', { ascending: true })
      .limit(1)
      .maybeSingle(),
    supabase
      .from('bookings')
      .select('*, vehicles!inner(make, model, reg_no, daily_rate)')
      .eq('client_id', cid)
      .in('status', ['pending', 'confirmed'])
      .gt('pickup_at', nowIso)
      .order('pickup_at', { ascending: true })
      .limit(1)
      .maybeSingle(),
    supabase.from('bookings').select('total').eq('client_id', cid).in('status', ['confirmed', 'active', 'overdue']),
    supabase
      .from('payments')
      .select('amount, bookings!inner(status)')
      .eq('client_id', cid)
      .eq('status', 'successful')
      .in('purpose', ['rental', 'extension']),
    supabase
      .from('deposits')
      .select('received_amount, deducted_amount, refunded_amount')
      .eq('client_id', cid)
      .in('status', ['held', 'partial']),
    walletBalance(cid),
    walletTransactions(cid, 5),
  ]);

  if (currentRes.error) throw new Error(`Current booking: ${currentRes.error.message}`);
  if (upcomingRes.error) throw new Error(`Upcoming booking: ${upcomingRes.error.message}`);
  if (outstandingRes.error) throw new Error(`Outstanding bookings: ${outstandingRes.error.message}`);
  if (paymentsRes.error) throw new Error(`Paid bookings: ${paymentsRes.error.message}`);
  if (depositsRes.error) throw new Error(`Deposits: ${depositsRes.error.message}`);

  const current = currentRes.data ? flattenBooking(currentRes.data as Record<string, unknown>) : null;
  const upcoming = upcomingRes.data ? flattenBooking(upcomingRes.data as Record<string, unknown>) : null;

  const totalOutstanding = sumAmount(outstandingRes.data ?? []);
  const successfulPayments = (paymentsRes.data ?? []).filter((row) =>
    ['confirmed', 'active', 'overdue'].includes(((row as Record<string, unknown>).bookings as { status: string }).status)
  );
  const totalPaid = sumAmount(successfulPayments);
  const outstanding = Math.max(0, totalOutstanding - totalPaid);
  const depositHeld = (depositsRes.data ?? []).reduce(
    (sum, row) =>
      sum +
      Number(((row as { received_amount: string | number }).received_amount ?? 0)) -
      Number(((row as { deducted_amount: string | number }).deducted_amount ?? 0)) -
      Number(((row as { refunded_amount: string | number }).refunded_amount ?? 0)),
    0
  );
  const firstName = client.full_name.split(' ')[0];

  return (
    <>
      <h1 className="text-2xl font-semibold text-slate-800 mb-1">Welcome back, {firstName}</h1>
      <p className="text-slate-500 text-sm mb-6">
        Account {client.client_no} · KYC <Badge status={client.kyc_status} />
      </p>

      {client.kyc_status !== 'verified' && (
        <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
          Complete your verification to speed up future bookings.{' '}
          <Link href="/client/profile" className="underline font-medium">
            Upload documents
          </Link>
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div className="kpi-card">
          <div>
            <p className="text-sm text-slate-500">Wallet balance</p>
            <p className="text-2xl font-semibold mt-1">{money(wallet)}</p>
          </div>
          <span className="icon-box bg-blue-50 text-blue-600">
            <Wallet className="w-5 h-5" />
          </span>
        </div>
        <div className="kpi-card">
          <div>
            <p className="text-sm text-slate-500">Outstanding balance</p>
            <p className={`text-2xl font-semibold mt-1 ${outstanding > 0 ? 'text-red-600' : ''}`}>
              {money(outstanding)}
            </p>
          </div>
          <span className="icon-box bg-red-50 text-red-600">
            <AlertCircle className="w-5 h-5" />
          </span>
        </div>
        <div className="kpi-card">
          <div>
            <p className="text-sm text-slate-500">Deposit held</p>
            <p className="text-2xl font-semibold mt-1">{money(depositHeld)}</p>
          </div>
          <span className="icon-box bg-green-50 text-green-600">
            <PiggyBank className="w-5 h-5" />
          </span>
        </div>
        <Link
          href="/vehicles"
          className="kpi-card !bg-blue-600 !border-blue-600 hover:!bg-blue-700 transition-colors"
        >
          <div>
            <p className="text-sm text-blue-100">Ready for your next trip?</p>
            <p className="text-xl font-semibold text-white mt-1">Book a vehicle</p>
          </div>
          <span className="icon-box bg-blue-500/40 text-white">
            <ArrowRight className="w-5 h-5" />
          </span>
        </Link>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div className="card">
          <h3 className="font-semibold text-slate-800 mb-4">Current booking</h3>
          {current ? (
            <>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">
                    {current.make} {current.model} ({current.reg_no})
                  </p>
                  <p className="text-sm text-slate-500">Return due {fmtDateTime(current.return_at)}</p>
                </div>
                <Badge status={current.status} />
              </div>
              <div className="flex gap-3 mt-4">
                <Link href={`/client/bookings/${current.id}`} className="btn-secondary !py-1.5 text-xs">
                  Details
                </Link>
                <Link href={`/client/bookings/${current.id}#extend`} className="btn-secondary !py-1.5 text-xs">
                  Request extension
                </Link>
              </div>
            </>
          ) : (
            <p className="text-sm text-slate-400">No vehicle currently on hire.</p>
          )}

          <h3 className="font-semibold text-slate-800 mt-8 mb-4">Upcoming booking</h3>
          {upcoming ? (
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">
                  {upcoming.make} {upcoming.model} ({upcoming.reg_no})
                </p>
                <p className="text-sm text-slate-500">Pickup {fmtDateTime(upcoming.pickup_at)}</p>
              </div>
              <Badge status={upcoming.status} />
            </div>
          ) : (
            <p className="text-sm text-slate-400">No upcoming bookings.</p>
          )}
        </div>

        <div className="card">
          <h3 className="font-semibold text-slate-800 mb-4">Recent wallet activity</h3>
          <ul className="divide-y divide-gray-100 text-sm">
            {recentTx.map((t: WalletTxn) => (
              <li key={t.id} className="py-3 flex items-center justify-between">
                <div>
                  <p className="font-medium text-slate-700">
                    {t.description ?? t.type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                  </p>
                  <p className="text-xs text-slate-400">{fmtDateTime(t.created_at)}</p>
                </div>
                <span className={`font-semibold ${Number(t.amount) < 0 ? 'text-red-600' : 'text-green-600'}`}>
                  {Number(t.amount) < 0 ? '−' : '+'}
                  {money(Math.abs(Number(t.amount)))}
                </span>
              </li>
            ))}
            {!recentTx.length && <li className="py-3 text-slate-400">No transactions yet.</li>}
          </ul>
          <Link href="/client/wallet" className="btn-secondary w-full justify-center mt-4">
            View wallet
          </Link>
        </div>
      </div>
    </>
  );
}
