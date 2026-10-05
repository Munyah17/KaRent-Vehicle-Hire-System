import { fmtDate } from '@/lib/helpers';
import PasswordForm from '@/components/client/PasswordForm';
import ContactForm from '@/components/client/ContactForm';
import { requireClient } from '@/components/client/data';

export const metadata = { title: 'Account Settings' };

export default async function SettingsPage() {
  const { user, client } = await requireClient();

  return (
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 max-w-4xl">
      <div className="card">
        <h2 className="font-semibold text-slate-800 mb-4">Account</h2>
        <dl className="text-sm space-y-2 mb-5">
          <div className="flex justify-between">
            <dt className="text-slate-500">Email (login)</dt>
            <dd className="font-medium">{user.email}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Client no.</dt>
            <dd className="font-medium">{client.client_no}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-slate-500">Member since</dt>
            <dd className="font-medium">{fmtDate(client.created_at)}</dd>
          </div>
        </dl>
        <ContactForm phone={client.phone ?? ''} />
      </div>
      <div className="card">
        <h2 className="font-semibold text-slate-800 mb-4">Change password</h2>
        <PasswordForm />
      </div>
    </div>
  );
}
