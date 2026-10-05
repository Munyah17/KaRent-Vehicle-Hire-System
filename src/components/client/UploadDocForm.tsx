'use client';

import { useActionState } from 'react';
import { Upload } from 'lucide-react';
import { uploadDocAction } from '@/app/client/profile/actions';
import type { ActionState } from '@/app/client/actions';

export default function UploadDocForm() {
  const [state, action, pending] = useActionState<ActionState, FormData>(uploadDocAction, null);

  return (
    <form action={action} className="grid grid-cols-2 gap-3">
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
      <select name="doc_type" className="input">
        <option value="national_id">National ID</option>
        <option value="drivers_licence">Driver&apos;s Licence</option>
        <option value="passport">Passport</option>
        <option value="proof_of_address">Proof of Address</option>
        <option value="other">Other</option>
      </select>
      <input
        type="file"
        name="doc"
        required
        className="input !py-1.5"
        accept="image/jpeg,image/png,image/webp,application/pdf"
      />
      <button className="btn-secondary col-span-2 justify-center" disabled={pending}>
        <Upload className="w-4 h-4" /> {pending ? 'Uploading…' : 'Upload Document'}
      </button>
    </form>
  );
}
