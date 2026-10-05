import { setting } from '@/lib/settings';
import { fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import SupportForm from '@/components/client/SupportForm';
import { requireClient, listTickets } from '@/components/client/data';

export const metadata = { title: 'Support' };

const STATUS_MAP: Record<string, string> = { open: 'pending', resolved: 'completed' };

export default async function SupportPage() {
  const { client } = await requireClient();
  const [tickets, phone, email] = await Promise.all([
    listTickets(client.id),
    setting('company_phone', ''),
    setting('company_email', ''),
  ]);

  return (
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div className="card">
        <h2 className="font-semibold text-slate-800 mb-4">Raise a request</h2>
        <SupportForm />
        <div className="mt-6 text-sm text-slate-500 space-y-1">
          <p className="font-medium text-slate-700">Prefer to reach us directly?</p>
          <p>{phone}</p>
          <p>{email}</p>
        </div>
      </div>
      <div className="card !p-0 overflow-x-auto">
        <div className="px-6 py-4 border-b border-gray-100">
          <h3 className="font-semibold text-slate-800">My Requests</h3>
        </div>
        <ul className="divide-y divide-gray-100">
          {tickets.map((t) => (
            <li key={t.id} className="px-6 py-4">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-800">{t.subject}</p>
                <Badge status={STATUS_MAP[t.status] ?? t.status} />
              </div>
              <p className="text-sm text-slate-500 mt-1">{t.message}</p>
              {t.staff_reply && (
                <p className="text-sm mt-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-blue-800">
                  <strong>Reply:</strong> {t.staff_reply}
                </p>
              )}
              <p className="text-xs text-slate-400 mt-2">{fmtDateTime(t.created_at)}</p>
            </li>
          ))}
          {!tickets.length && (
            <li className="px-6 py-10 text-center text-slate-400 text-sm">No requests yet.</li>
          )}
        </ul>
      </div>
    </div>
  );
}
