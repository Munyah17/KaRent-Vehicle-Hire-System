import { money, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listPayments } from '@/components/client/data';

export const metadata = { title: 'Payments' };

const ucwords = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default async function PaymentsPage() {
  const { client } = await requireClient();
  const payments = await listPayments(client.id);

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="px-6 py-4 border-b border-gray-100">
        <h2 className="font-semibold text-slate-800">Payment History</h2>
      </div>
      <div className="overflow-x-auto">
      <table className="w-full">
        <thead>
          <tr>
            <th className="th">Txn ID</th>
            <th className="th">Booking</th>
            <th className="th">Method</th>
            <th className="th">Purpose</th>
            <th className="th">Status</th>
            <th className="th">Date</th>
            <th className="th text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
          {payments.map((p) => (
            <tr className="table-row" key={p.id}>
              <td className="td font-medium">{p.txn_id}</td>
              <td className="td">{p.booking_ref ?? '—'}</td>
              <td className="td">{ucwords(p.method)}</td>
              <td className="td">{p.purpose.charAt(0).toUpperCase() + p.purpose.slice(1)}</td>
              <td className="td">
                <Badge status={p.status} />
              </td>
              <td className="td">{fmtDateTime(p.paid_at ?? p.created_at)}</td>
              <td className="td text-right font-medium">{money(p.amount)}</td>
            </tr>
          ))}
          {!payments.length && (
            <tr>
              <td colSpan={7} className="td text-center py-10 text-slate-400">
                No payments yet.
              </td>
            </tr>
          )}
        </tbody>
      </table>
      </div>
    </div>
  );
}
