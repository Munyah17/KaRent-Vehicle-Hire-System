'use client';

import { useActionState } from 'react';
import { updateContactAction } from '@/app/client/settings/actions';
import type { ActionState } from '@/app/client/actions';

export default function ContactForm({ phone }: { phone: string }) {
  const [state, action, pending] = useActionState<ActionState, FormData>(updateContactAction, null);

  return (
    <form action={action} className="space-y-3">
      {state?.error && (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}
      {state?.success && (
        <div className="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
          {state.success}
        </div>
      )}
      <label className="label">Phone</label>
      <input name="phone" className="input" defaultValue={phone} />
      <button className="btn-secondary" disabled={pending}>
        {pending ? 'Updating…' : 'Update contact'}
      </button>
    </form>
  );
}
