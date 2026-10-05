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

  const company = await setting('company_name', 'KaRent');

  return (
    <div className="max-w-3xl mx-auto">
      <style>{`@media print{.site-header,#clientSidebar,#siteDrawer,#drawerBackdrop,.noprint{display:none!important}}`}</style>
      <div className="noprint mb-4 flex justify-end gap-3">
        <PrintButton />
      </div>
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-10">
        <div className="text-xs text-slate-400 border-b border-gray-100 pb-3 mb-6 flex justify-between">
          <span>
            {company} · {typedContract.title} · v{typedContract.template_version}
          </span>
          <Badge status={typedContract.status} />
        </div>
        <div
          className="text-slate-700 text-sm leading-relaxed"
          dangerouslySetInnerHTML={{ __html: typedContract.body }}
        />
        {sig && (
          <div className="mt-10 border-t border-gray-100 pt-4 text-sm">
            <p className="font-semibold">Signed by: {sig.signer_name}</p>
            <p className="text-xs text-slate-400">{fmtDateTime(sig.signed_at)}</p>
            {sig.signature_data?.startsWith('data:image') && (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={sig.signature_data} className="mt-2 h-16" alt="signature" />
            )}
          </div>
        )}
      </div>
    </div>
  );
}
