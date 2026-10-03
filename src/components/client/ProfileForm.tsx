'use client';

import { useActionState } from 'react';
import { saveProfileAction } from '@/app/client/profile/actions';
import type { ActionState } from '@/app/client/actions';
import type { Client } from './data';

export default function ProfileForm({ client }: { client: Client }) {
  const [state, action, pending] = useActionState<ActionState, FormData>(saveProfileAction, null);

  return (
    <form action={action} className="cp-two">
      {state?.error && <div className="cp-alert err" style={{ gridColumn: '1/-1', marginBottom: 0 }}>{state.error}</div>}
      {state?.success && <div className="cp-alert ok" style={{ gridColumn: '1/-1', marginBottom: 0 }}>{state.success}</div>}
      <div style={{ gridColumn: '1/-1' }}>
        <label className="label">Full name</label>
        <input name="full_name" className="input" defaultValue={client.full_name} required />
      </div>
      <div>
        <label className="label">Date of birth</label>
        <input type="date" name="dob" className="input" defaultValue={client.dob ? String(client.dob).slice(0, 10) : ''} />
      </div>
      <div>
        <label className="label">Phone</label>
        <input name="phone" className="input" defaultValue={client.phone ?? ''} />
      </div>
      <div style={{ gridColumn: '1/-1' }}>
        <label className="label">Address</label>
        <textarea name="address" rows={2} className="input" defaultValue={client.address ?? ''} />
      </div>
      <div>
        <label className="label">National ID / Passport</label>
        <input name="national_id" className="input" defaultValue={client.national_id ?? ''} />
      </div>
      <div>
        <label className="label">Licence no.</label>
        <input name="licence_no" className="input" defaultValue={client.licence_no ?? ''} />
      </div>
      <div>
        <label className="label">Licence expiry</label>
        <input type="date" name="licence_expiry" className="input" defaultValue={client.licence_expiry ? String(client.licence_expiry).slice(0, 10) : ''} />
      </div>
      <div>
        <label className="label">Tax / TIN no. (optional)</label>
        <input name="tax_no" className="input" defaultValue={client.tax_no ?? ''} placeholder="Buyer TIN for fiscal receipts" />
      </div>
      <label style={{ gridColumn: '1/-1', display: 'flex', alignItems: 'center', gap: 8, fontSize: '.88rem' }}>
        <input type="checkbox" name="fiscalise" defaultChecked={!!client.fiscalise} />
        I want fiscalised (ZIMRA FDMS) receipts for my payments
      </label>
      <button className="button" disabled={pending} style={{ gridColumn: '1/-1', justifySelf: 'start' }}>
        {pending ? 'Saving…' : 'Save Profile'}
      </button>
    </form>
  );
}
