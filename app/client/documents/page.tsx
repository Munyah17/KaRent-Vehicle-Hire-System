import Link from 'next/link';
import { FileText, IdCard } from 'lucide-react';
import { fmtDate, fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import { requireClient, listContracts, listClientDocs } from '@/components/client/data';

export const metadata = { title: 'My Documents' };

export default async function DocumentsPage() {
  const { client } = await requireClient();
  const [contracts, docs] = await Promise.all([listContracts(client.id), listClientDocs(client.id)]);

  return (
    <div className="cp-grid2" style={{ alignItems: 'start' }}>
      <div className="cp-card flush">
        <div className="hd"><h2>Contracts & Documents</h2></div>
        {contracts.length ? (
          <ul className="cp-list">
            {contracts.map((c) => (
              <li key={c.id}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                  <span style={{ width: 36, height: 36, borderRadius: 9, background: '#e3f4ec', color: '#08705f', display: 'grid', placeItems: 'center', flexShrink: 0 }}>
                    <FileText size={17} />
                  </span>
                  <div>
                    <p style={{ margin: 0, fontWeight: 700 }}>
                      {c.title} <span className="t">v{c.template_version}</span>
                    </p>
                    <span className="t">{c.booking_ref} · {fmtDateTime(c.created_at)}</span>
                  </div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <Badge status={c.status} />
                  <Link href={`/client/documents/${c.id}`} className="button secondary" style={{ padding: '7px 12px', fontSize: '.76rem' }}>
                    View / Print
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <div className="cp-empty">No documents yet — they&apos;ll appear here after a booking is confirmed.</div>
        )}
      </div>

      <div className="cp-card flush">
        <div className="hd"><h2>Verification Documents</h2></div>
        {docs.length ? (
          <ul className="cp-list">
            {docs.map((d) => (
              <li key={d.id}>
                <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                  <span style={{ width: 36, height: 36, borderRadius: 9, background: '#f0f1f0', color: '#5b6b68', display: 'grid', placeItems: 'center', flexShrink: 0 }}>
                    <IdCard size={17} />
                  </span>
                  <div>
                    <p style={{ margin: 0, fontWeight: 700, textTransform: 'capitalize' }}>{d.doc_type.replace(/_/g, ' ')}</p>
                    <span className="t">Uploaded {fmtDate(d.uploaded_at)}</span>
                  </div>
                </div>
                <Badge status={d.status} />
              </li>
            ))}
          </ul>
        ) : (
          <div className="cp-empty">No documents uploaded yet — upload them from your <Link href="/client/profile" style={{ color: '#087f70' }}>profile</Link>.</div>
        )}
      </div>
    </div>
  );
}
