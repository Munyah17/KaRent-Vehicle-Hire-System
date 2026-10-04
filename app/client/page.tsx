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
      <h1 className="cp-h1">Welcome back, {firstName}</h1>
      <p className="cp-sub">
        Account {client.client_no} · KYC <Badge status={client.kyc_status} />
      </p>

      {client.kyc_status !== 'verified' && (
        <div className="cp-alert warn">
          Complete your verification to speed up future bookings.{' '}
          <Link href="/client/profile" style={{ textDecoration: 'underline', fontWeight: 700 }}>
            Update profile & documents
          </Link>
        </div>
      )}

      <div className="cp-kpis">
        <div className="cp-kpi">
          <div className="row">
            <div>
              <div className="l">Wallet balance</div>
              <div className="v">{money(wallet)}</div>
            </div>
            <Wallet size={22} color="#087f70" />
          </div>
        </div>
        <div className="cp-kpi">
          <div className="row">
            <div>
              <div className="l">Outstanding balance</div>
              <div className={`v${outstanding > 0 ? ' red' : ' green'}`}>{money(outstanding)}</div>
            </div>
            <AlertCircle size={22} color={outstanding > 0 ? '#c0392b' : '#08705f'} />
          </div>
        </div>
        <div className="cp-kpi">
          <div className="row">
            <div>
              <div className="l">Deposit held</div>
              <div className="v">{money(depositHeld)}</div>
            </div>
            <PiggyBank size={22} color="#087f70" />
          </div>
        </div>
        <Link href="/vehicles" className="cp-kpi cta">
          <div>
            <div className="l">Ready for your next trip?</div>
            <div className="v">Book a vehicle</div>
          </div>
          <ArrowRight size={22} />
        </Link>
      </div>

      <div className="cp-grid2">
        <div className="cp-card">
          <h3>Current booking</h3>
          {current ? (
            <>
              <div className="row">
                <div>
                  <p style={{ margin: 0, fontWeight: 700 }}>
                    {current.make} {current.model} ({current.reg_no})
                  </p>
                  <p className="muted" style={{ margin: '4px 0 0', fontSize: '.84rem' }}>
                    Return due {fmtDateTime(current.return_at)}
                  </p>
                </div>
                <Badge status={current.status} />
              </div>
              <div className="cp-actions" style={{ marginTop: 14 }}>
                <Link href={`/client/bookings/${current.id}`} className="button secondary" style={{ padding: '8px 14px', fontSize: '.8rem' }}>
                  Details
                </Link>
                <Link href={`/client/bookings/${current.id}#extend`} className="button secondary" style={{ padding: '8px 14px', fontSize: '.8rem' }}>
                  Request extension
                </Link>
              </div>
            </>
          ) : (
            <p className="muted" style={{ fontSize: '.9rem' }}>No vehicle currently on hire.</p>
          )}

          <h3 style={{ marginTop: 28 }}>Upcoming booking</h3>
          {upcoming ? (
            <div className="row">
              <div>
                <p style={{ margin: 0, fontWeight: 700 }}>
                  {upcoming.make} {upcoming.model} ({upcoming.reg_no})
                </p>
                <p className="muted" style={{ margin: '4px 0 0', fontSize: '.84rem' }}>
                  Pickup {fmtDateTime(upcoming.pickup_at)}
                </p>
              </div>
              <Badge status={upcoming.status} />
            </div>
          ) : (
            <p className="muted" style={{ fontSize: '.9rem' }}>No upcoming bookings.</p>
          )}
        </div>

        <div className="cp-card">
          <h3>Recent wallet activity</h3>
          {recentTx.length ? (
            <ul className="cp-list" style={{ margin: '0 -24px' }}>
              {recentTx.map((t: WalletTxn) => (
                <li key={t.id}>
                  <div>
                    <p style={{ margin: 0, fontWeight: 600 }}>{t.description ?? t.type.replace(/_/g, ' ')}</p>
                    <span className="t">{fmtDateTime(t.created_at)}</span>
                  </div>
                  <span style={{ fontWeight: 800, color: Number(t.amount) < 0 ? '#c0392b' : '#0a6b52' }}>
                    {Number(t.amount) < 0 ? '−' : '+'}{money(Math.abs(Number(t.amount)))}
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted" style={{ fontSize: '.9rem' }}>No transactions yet.</p>
          )}
          <Link href="/client/wallet" className="button secondary" style={{ width: '100%', marginTop: 16 }}>
            View wallet
          </Link>
        </div>
      </div>
    </>
  );
}
