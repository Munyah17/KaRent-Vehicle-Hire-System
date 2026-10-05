import { FileText } from 'lucide-react';
import { fmtDate } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import ProfileForm from '@/components/client/ProfileForm';
import UploadDocForm from '@/components/client/UploadDocForm';
import { requireClient, listClientDocs } from '@/components/client/data';

export const metadata = { title: 'Profile & KYC' };

export default async function ProfilePage() {
  const { client } = await requireClient();
  const docs = await listClientDocs(client.id);

  return (
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <div className="card">
        <div className="flex items-center justify-between mb-5">
          <h2 className="font-semibold text-slate-800">My Profile</h2>
          <Badge status={client.kyc_status} />
        </div>
        <ProfileForm client={client} />
      </div>

      <div className="card">
        <h2 className="font-semibold text-slate-800 mb-1">Verification documents</h2>
        <p className="text-xs text-slate-400 mb-4">
          Upload your ID and driver&apos;s licence. Documents are stored securely and are never publicly
          accessible.
        </p>
        <ul className="divide-y divide-gray-100 text-sm mb-4">
          {docs.map((d) => (
            <li key={d.id} className="py-3 flex items-center justify-between">
              <span className="flex items-center gap-2">
                <FileText className="w-4 h-4 text-gray-400" />
                {d.doc_type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                <span className="text-xs text-slate-400">{fmtDate(d.uploaded_at)}</span>
              </span>
              <Badge status={d.status} />
            </li>
          ))}
          {!docs.length && <li className="py-3 text-slate-400">No documents uploaded yet.</li>}
        </ul>
        <UploadDocForm />
      </div>
    </div>
  );
}
