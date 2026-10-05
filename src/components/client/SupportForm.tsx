'use client';

import { useActionState } from 'react';
import { Send } from 'lucide-react';
import { createTicketAction } from '@/app/client/support/actions';
import type { ActionState } from '@/app/client/actions';

export default function SupportForm() {
  const [state, action, pending] = useActionState<ActionState, FormData>(createTicketAction, null);

  return (
    <form action={action} className="space-y-4">
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
      <div>
        <label className="label">Subject</label>
        <input name="subject" className="input" required maxLength={160} />
      </div>
      <div>
        <label className="label">How can we help?</label>
        <textarea name="message" rows={5} className="input" required />
      </div>
      <button className="btn-primary w-full justify-center" disabled={pending}>
        <Send className="w-4 h-4" /> {pending ? 'Submitting…' : 'Submit Request'}
      </button>
    </form>
  );
}
