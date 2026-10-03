'use client';

import { useActionState } from 'react';
import { uploadDocAction } from '@/app/client/profile/actions';
import type { ActionState } from '@/app/client/actions';

export default function UploadDocForm() {
  const [state, action, pending] = useActionState<ActionState, FormData>(uploadDocAction, null);

  return (
    <form action={action} className="cp-two">
      {state?.error && <div className="cp-alert err" style={{ gridColumn: '1/-1', marginBottom: 0 }}>{state.error}</div>}
      {state?.success && <div className="cp-alert ok" style={{ gridColumn: '1/-1', marginBottom: 0 }}>{state.success}</div>}
      <select name="doc_type" className="input">
        <option value="national_id">National ID</option>
        <option value="drivers_licence">Driver&apos;s Licence</option>
        <option value="passport">Passport</option>
        <option value="proof_of_address">Proof of Address</option>
        <option value="other">Other</option>
      </select>
      <input type="file" name="doc" required className="input" accept="image/jpeg,image/png,image/webp,application/pdf" />
      <button className="button secondary" disabled={pending} style={{ gridColumn: '1/-1', justifySelf: 'start' }}>
        {pending ? 'Uploading…' : 'Upload Document'}
      </button>
    </form>
  );
}
