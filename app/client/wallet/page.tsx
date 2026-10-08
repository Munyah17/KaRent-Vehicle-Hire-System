import { money, fmtDateTime } from '@/lib/helpers';
import { requireClient, walletBalance, walletTransactions } from '@/components/client/data';

export const metadata = { title: 'My Wallet' };

const ucwords = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default async function WalletPage() {
  const { client } = await requireClient();
  const [balance, txns] = await Promise.all([walletBalance(client.id), walletTransactions(client.id)]);

  return (
    <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div className="card h-fit">
        <p className="text-sm text-slate-500">Current balance</p>
        <p className="text-3xl font-semibold text-slate-800 mt-1">{money(balance)}</p>
        <p className="text-sm text-slate-500 mt-6">
          Top-ups are processed via Paynow or at the office. Your balance can be used to pay
          outstanding booking amounts from the booking page.
        </p>
      </div>
      <div className="xl:col-span-2 card !p-0 overflow-x-auto">
        <div className="px-6 py-4 border-b border-gray-100">
          <h3 className="font-semibold text-slate-800">Transaction History</h3>
        </div>
        <div className="overflow-x-auto">
      <table className="w-full">
          <thead>
            <tr>
              <th className="th">Ref</th>
              <th className="th">Type</th>
              <th className="th">Description</th>
              <th className="th">Date</th>
              <th className="th text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            {txns.map((t) => (
              <tr className="table-row" key={t.id}>
                <td className="td font-medium">{t.ref}</td>
                <td className="td">{ucwords(t.type)}</td>
                <td className="td">{t.description ?? '—'}</td>
                <td className="td">{fmtDateTime(t.created_at)}</td>
                <td
                  className={`td text-right font-semibold ${
                    Number(t.amount) < 0 ? 'text-red-600' : 'text-green-600'
                  }`}
                >
                  {Number(t.amount) < 0 ? '−' : '+'}
                  {money(Math.abs(Number(t.amount)))}
                </td>
              </tr>
            ))}
            {!txns.length && (
              <tr>
                <td colSpan={5} className="td text-center py-10 text-slate-400">
                  No transactions yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
        </div>
      </div>
    </div>
  );
}
