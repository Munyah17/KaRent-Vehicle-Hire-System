'use client';

import { useActionState } from 'react';
import { createTicketAction } from '@/app/client/support/actions';
import type { ActionState } from '@/app/client/actions';

export default function SupportForm() {
  const [state, action, pending] = useActionState<ActionState, FormData>(createTicketAction, null);

  return (
    <form action={action} className="stack">
      {state?.error && <div className="cp-alert err" style={{ marginBottom: 0 }}>{state.error}</div>}
      {state?.success && <div className="cp-alert ok" style={{ marginBottom: 0 }}>{state.success}</div>}
      <div>
        <label className="label">Subject</label>
        <input name="subject" className="input" required maxLength={160} />
      </div>
      <div>
        <label className="label">How can we help?</label>
        <textarea name="message" rows={5} className="input" required />
      </div>
      <button className="button" disabled={pending} style={{ width: '100%' }}>
        {pending ? 'Submitting…' : 'Submit Request'}
      </button>
    </form>
  );
}
