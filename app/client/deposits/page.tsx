import { money } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listDeposits } from '@/components/client/data';

export const metadata = { title: 'My Deposits' };

export default async function DepositsPage() {
  const { client } = await requireClient();
  const deposits = await listDeposits(client.id);

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="px-6 py-4 border-b border-gray-100">
        <h2 className="font-semibold text-slate-800">Security Deposits</h2>
      </div>
      <div className="overflow-x-auto">
      <table className="w-full">
        <thead>
          <tr>
            <th className="th">Booking</th>
            <th className="th">Vehicle</th>
            <th className="th">Required</th>
            <th className="th">Received</th>
            <th className="th">Deductions</th>
            <th className="th">Refunded</th>
            <th className="th">Held</th>
            <th className="th">Status</th>
          </tr>
        </thead>
        <tbody>
          {deposits.map((d) => {
            const held = Number(d.received_amount) - Number(d.deducted_amount) - Number(d.refunded_amount);
            return (
              <tr className="table-row" key={d.id}>
                <td className="td font-medium">{d.booking_ref}</td>
                <td className="td">
                  {d.make} {d.model}
                </td>
                <td className="td">{money(d.required_amount)}</td>
                <td className="td">{money(d.received_amount)}</td>
                <td className="td text-red-600">{money(d.deducted_amount)}</td>
                <td className="td">{money(d.refunded_amount)}</td>
                <td className="td font-medium">{money(held)}</td>
                <td className="td">
                  <Badge status={d.status} />
                </td>
              </tr>
            );
          })}
          {!deposits.length && (
            <tr>
              <td colSpan={8} className="td text-center py-10 text-slate-400">
                No deposits yet.
              </td>
            </tr>
          )}
        </tbody>
      </table>
      </div>
    </div>
  );
}
