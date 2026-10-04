import { notFound } from 'next/navigation';
import { supabase } from '@/lib/supabase';
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

  const { data: contract, error: contractErr } = await supabase
    .from('contracts')
    .select('*, bookings!inner(ref)')
    .eq('id', Number(id))
    .eq('client_id', client.id)
    .maybeSingle();
  if (contractErr) throw new Error(`contract: ${contractErr.message}`);
  if (!contract) notFound();

  const typedContract = contract as unknown as Contract & { bookings: { ref: string } };

  const { data: sig, error: sigErr } = await supabase
    .from('signatures')
    .select('signer_name, signature_data, signed_at')
    .eq('contract_id', typedContract.id)
    .order('id', { ascending: false })
    .limit(1)
    .maybeSingle();
  if (sigErr) throw new Error(`signatures: ${sigErr.message}`);

  const [company] = await Promise.all([setting('company_name', 'KaRent')]);

  return (
    <div className="cp-doc">
      <div className="noprint" style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 16 }}>
        <PrintButton />
      </div>
      <div className="cp-doc-paper">
        <div className="cp-doc-meta">
          <span>{company} · {typedContract.title} · v{typedContract.template_version}</span>
          <Badge status={typedContract.status} />
        </div>
        <div className="cp-doc-body" dangerouslySetInnerHTML={{ __html: typedContract.body }} />
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
