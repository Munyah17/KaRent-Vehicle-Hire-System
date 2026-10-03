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
    <div className="cp-grid2">
      <div className="cp-card" style={{ height: 'fit-content' }}>
        <h2>Raise a request</h2>
        <SupportForm />
        {(phone || email) && (
          <div style={{ marginTop: 22, fontSize: '.88rem' }}>
            <p style={{ margin: '0 0 4px', fontWeight: 700 }}>Prefer to reach us directly?</p>
            {phone && <p className="muted" style={{ margin: 0 }}>{phone}</p>}
            {email && <p className="muted" style={{ margin: 0 }}>{email}</p>}
          </div>
        )}
      </div>

      <div className="cp-card flush">
        <div className="hd"><h3>My Requests</h3></div>
        {tickets.length ? (
          <ul className="cp-list">
            {tickets.map((t) => (
              <li key={t.id} style={{ display: 'block' }}>
                <div className="row">
                  <p style={{ margin: 0, fontWeight: 700 }}>{t.subject}</p>
                  <Badge status={STATUS_MAP[t.status] ?? t.status} />
                </div>
                <p className="muted" style={{ margin: '6px 0 0', fontSize: '.86rem' }}>{t.message}</p>
                {t.staff_reply && (
                  <p style={{ margin: '10px 0 0', padding: '10px 12px', borderRadius: 9, background: '#e4effa', color: '#2b5fa3', fontSize: '.86rem' }}>
                    <strong>Reply:</strong> {t.staff_reply}
                  </p>
                )}
                <p className="t" style={{ margin: '8px 0 0' }}>{fmtDateTime(t.created_at)}</p>
              </li>
            ))}
          </ul>
        ) : (
          <div className="cp-empty">No requests yet.</div>
        )}
      </div>
    </div>
  );
}
