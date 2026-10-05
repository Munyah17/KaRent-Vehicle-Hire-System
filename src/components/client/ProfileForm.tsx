'use client';

import { useActionState } from 'react';
import { Save } from 'lucide-react';
import { saveProfileAction } from '@/app/client/profile/actions';
import type { ActionState } from '@/app/client/actions';
import type { Client } from './data';

export default function ProfileForm({ client }: { client: Client }) {
  const [state, action, pending] = useActionState<ActionState, FormData>(saveProfileAction, null);

  return (
    <form action={action} className="grid grid-cols-2 gap-4">
      {state?.error && (
        <div className="col-span-2 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}
      {state?.success && (
        <div className="col-span-2 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
          {state.success}
        </div>
      )}
      <div className="col-span-2">
        <label className="label">Full name</label>
        <input name="full_name" className="input" defaultValue={client.full_name} required />
      </div>
      <div>
        <label className="label">Date of birth</label>
        <input
          type="date"
          name="dob"
          className="input"
          defaultValue={client.dob ? String(client.dob).slice(0, 10) : ''}
        />
      </div>
      <div>
        <label className="label">Phone</label>
        <input name="phone" className="input" defaultValue={client.phone ?? ''} />
      </div>
      <div className="col-span-2">
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
        <input
          type="date"
          name="licence_expiry"
          className="input"
          defaultValue={client.licence_expiry ? String(client.licence_expiry).slice(0, 10) : ''}
        />
      </div>
      <div>
        <label className="label">Tax / TIN no. (optional)</label>
        <input
          name="tax_no"
          className="input"
          defaultValue={client.tax_no ?? ''}
          placeholder="Buyer TIN for fiscal receipts"
        />
      </div>
      <div className="col-span-2">
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="fiscalise" defaultChecked={!!client.fiscalise} />
          I want fiscalised (ZIMRA FDMS) receipts for my payments
        </label>
      </div>
      <div className="col-span-2">
        <button className="btn-primary w-full justify-center" disabled={pending}>
          <Save className="w-4 h-4" /> {pending ? 'Saving…' : 'Save Profile'}
        </button>
      </div>
    </form>
  );
}
