import Link from 'next/link';
import { ArrowRight, Wallet, AlertCircle, PiggyBank } from 'lucide-react';
import { one, query } from '@/lib/db';
import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, walletBalance, walletTransactions, WalletTxn } from '@/components/client/data';
import type { Booking } from '@/components/client/data';

export const metadata = { title: 'Dashboard' };

export default async function ClientDashboard() {
  const { client } = await requireClient();
  const cid = client.id;

  const [current, upcoming, outstandingRow, paidRow, depositRow, wallet, recentTx] = await Promise.all([
    one<Booking>(
      `SELECT b.*, v.make, v.model, v.reg_no, v.daily_rate FROM bookings b
       JOIN vehicles v ON v.id = b.vehicle_id
       WHERE b.client_id = ? AND b.status IN ('active','overdue') ORDER BY b.return_at LIMIT 1`,
      [cid]
    ),
    one<Booking>(
      `SELECT b.*, v.make, v.model, v.reg_no, v.daily_rate FROM bookings b
       JOIN vehicles v ON v.id = b.vehicle_id
       WHERE b.client_id = ? AND b.status IN ('pending','confirmed') AND b.pickup_at > NOW()
       ORDER BY b.pickup_at LIMIT 1`,
      [cid]
    ),
    one<{ s: string }>(
      `SELECT COALESCE(SUM(b.total),0) AS s FROM bookings b
       WHERE b.client_id = ? AND b.status IN ('confirmed','active','overdue')`,
      [cid]
    ),
    one<{ s: string }>(
      `SELECT COALESCE(SUM(p.amount),0) AS s FROM payments p JOIN bookings b ON b.id = p.booking_id
       WHERE p.client_id = ? AND p.status = 'successful' AND p.purpose IN ('rental','extension')
         AND b.status IN ('confirmed','active','overdue')`,
      [cid]
    ),
    one<{ s: string }>(
      `SELECT COALESCE(SUM(received_amount - deducted_amount - refunded_amount),0) AS s
       FROM deposits WHERE client_id = ? AND status IN ('held','partial')`,
      [cid]
    ),
    walletBalance(cid),
    walletTransactions(cid, 5),
  ]);

  const outstanding = Math.max(0, Number(outstandingRow?.s ?? 0) - Number(paidRow?.s ?? 0));
  const depositHeld = Number(depositRow?.s ?? 0);
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
