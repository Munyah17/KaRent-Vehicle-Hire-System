import Link from 'next/link';
import { FileText } from 'lucide-react';
import { fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listContracts } from '@/components/client/data';

export const metadata = { title: 'My Documents' };

export default async function DocumentsPage() {
  const { client } = await requireClient();
  const contracts = await listContracts(client.id);

  return (
    <div className="card !p-0 overflow-x-auto max-w-4xl">
      <div className="px-6 py-4 border-b border-gray-100">
        <h2 className="font-semibold text-slate-800">Contracts & Documents</h2>
      </div>
      <ul className="divide-y divide-gray-100">
        {contracts.map((c) => (
          <li key={c.id} className="flex items-center justify-between px-6 py-4">
            <div className="flex items-center gap-3">
              <span className="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <FileText className="w-5 h-5" />
              </span>
              <div>
                <p className="text-sm font-medium text-slate-800">
                  {c.title} <span className="text-xs text-slate-400">v{c.template_version}</span>
                </p>
                <p className="text-xs text-slate-400">
                  {c.booking_ref} · {fmtDateTime(c.created_at)}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <Badge status={c.status} />
              <Link
                href={`/client/documents/${c.id}`}
                target="_blank"
                className="btn-secondary !py-1.5 !px-3 text-xs"
              >
                View / Print
              </Link>
            </div>
          </li>
        ))}
        {!contracts.length && (
          <li className="px-6 py-10 text-center text-slate-400 text-sm">
            No documents yet — they&apos;ll appear here after a booking is confirmed.
          </li>
        )}
      </ul>
    </div>
  );
}
