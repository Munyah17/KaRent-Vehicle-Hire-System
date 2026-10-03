import { notFound } from 'next/navigation';
import { one } from '@/lib/db';
import { setting } from '@/lib/settings';
import { fmtDateTime } from '@/lib/helpers';
import Badge from '@/components/client/Badge';
import PrintButton from '@/components/client/PrintButton';
import { requireClient } from '@/components/client/data';
import type { Contract } from '@/components/client/data';

export const metadata = { title: 'Document' };

export default async function ContractPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const { client } = await requireClient();
  const contract = await one<Contract>(
    `SELECT ct.*, b.ref AS booking_ref FROM contracts ct
     JOIN bookings b ON b.id = ct.booking_id
     WHERE ct.id = ? AND ct.client_id = ?`,
    [Number(id), client.id]
  );
  if (!contract) notFound();

  const [company, sig] = await Promise.all([
    setting('company_name', 'KaRent'),
    one<{ signer_name: string; signature_data: string | null; signed_at: string }>(
      'SELECT signer_name, signature_data, signed_at FROM signatures WHERE contract_id = ? ORDER BY id DESC LIMIT 1',
      [contract.id]
    ),
  ]);

  return (
    <div className="cp-doc">
      <div className="noprint" style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 16 }}>
        <PrintButton />
      </div>
      <div className="cp-doc-paper">
        <div className="cp-doc-meta">
          <span>{company} · {contract.title} · v{contract.template_version}</span>
          <Badge status={contract.status} />
        </div>
        <div className="cp-doc-body" dangerouslySetInnerHTML={{ __html: contract.body }} />
        {sig && (
          <div className="cp-doc-sig">
            <p style={{ margin: 0, fontWeight: 700 }}>Signed by: {sig.signer_name}</p>
            <p className="muted" style={{ margin: '4px 0 0', fontSize: '.78rem' }}>{fmtDateTime(sig.signed_at)}</p>
            {sig.signature_data?.startsWith('data:image') && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={sig.signature_data} alt="Signature" style={{ marginTop: 10, height: 64 }} />
            )}
          </div>
        )}
      </div>
    </div>
  );
}
