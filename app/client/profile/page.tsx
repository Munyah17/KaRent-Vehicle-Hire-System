import { FileText } from 'lucide-react';
import { fmtDate } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import ProfileForm from '@/components/client/ProfileForm';
import UploadDocForm from '@/components/client/UploadDocForm';
import PasswordForm from '@/components/client/PasswordForm';
import { requireClient, listClientDocs } from '@/components/client/data';

export const metadata = { title: 'Profile & KYC' };

export default async function ProfilePage() {
  const { user, client } = await requireClient();
  const docs = await listClientDocs(client.id);

  return (
    <>
      <div className="cp-grid2" style={{ alignItems: 'start' }}>
        <div className="cp-card">
          <div className="row" style={{ marginBottom: 18 }}>
            <h2 style={{ margin: 0 }}>My Profile</h2>
            <Badge status={client.kyc_status} />
          </div>
          <dl className="cp-dl" style={{ marginBottom: 20 }}>
            <div className="row"><dt>Email (login)</dt><dd>{user.email}</dd></div>
            <div className="row"><dt>Client no.</dt><dd>{client.client_no}</dd></div>
            <div className="row"><dt>Member since</dt><dd>{fmtDate(client.created_at)}</dd></div>
          </dl>
          <ProfileForm client={client} />
        </div>

        <div>
          <div className="cp-card">
            <h2>Verification documents</h2>
            <p className="muted" style={{ fontSize: '.82rem', marginTop: -8 }}>
              Upload your ID and driver&apos;s licence. Documents are stored securely and reviewed by staff.
            </p>
            {docs.length > 0 && (
              <ul className="cp-list" style={{ margin: '0 -24px 16px' }}>
                {docs.map((d) => (
                  <li key={d.id}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                      <FileText size={15} color="#607477" />
                      <span style={{ textTransform: 'capitalize' }}>{d.doc_type.replace(/_/g, ' ')}</span>
                      <span className="t">{fmtDate(d.uploaded_at)}</span>
                    </span>
                    <Badge status={d.status} />
                  </li>
                ))}
              </ul>
            )}
            <UploadDocForm />
          </div>

          <div className="cp-card">
            <h2>Change password</h2>
            <PasswordForm />
          </div>
        </div>
      </div>
    </>
  );
}
