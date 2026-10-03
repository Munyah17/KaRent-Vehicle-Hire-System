import { money, fmtDateTime } from '@/lib/helpers';
import { requireClient, walletBalance, walletTransactions } from '@/components/client/data';

export const metadata = { title: 'My Wallet' };

export default async function WalletPage() {
  const { client } = await requireClient();
  const [balance, txns] = await Promise.all([walletBalance(client.id), walletTransactions(client.id)]);

  return (
    <div className="cp-grid32" style={{ gridTemplateColumns: '1fr 2fr' }}>
      <div className="cp-card" style={{ height: 'fit-content' }}>
        <div className="muted" style={{ fontSize: '.82rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '.05em' }}>
          Current balance
        </div>
        <div style={{ fontSize: '2rem', fontWeight: 800, marginTop: 6 }}>{money(balance)}</div>
        <p className="muted" style={{ fontSize: '.85rem', marginTop: 16 }}>
          Top-ups are processed via Paynow or at the office. Your balance can be used to pay
          outstanding booking amounts from the booking page.
        </p>
      </div>

      <div className="cp-card flush">
        <div className="hd"><h3>Transaction History</h3></div>
        <div style={{ overflowX: 'auto' }}>
          <table className="cp-table">
            <thead>
              <tr><th>Ref</th><th>Type</th><th>Description</th><th>Date</th><th className="r">Amount</th></tr>
            </thead>
            <tbody>
              {txns.map((t) => (
                <tr key={t.id}>
                  <td style={{ fontWeight: 700 }}>{t.ref}</td>
                  <td style={{ textTransform: 'capitalize' }}>{t.type.replace(/_/g, ' ')}</td>
                  <td>{t.description ?? '—'}</td>
                  <td>{fmtDateTime(t.created_at)}</td>
                  <td className="r" style={{ fontWeight: 700, color: Number(t.amount) < 0 ? '#c0392b' : '#0a6b52' }}>
                    {Number(t.amount) < 0 ? '−' : '+'}{money(Math.abs(Number(t.amount)))}
                  </td>
                </tr>
              ))}
              {!txns.length && <tr><td colSpan={5} className="cp-empty">No transactions yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
