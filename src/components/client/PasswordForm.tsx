'use client';

import { useActionState } from 'react';
import { changePasswordAction } from '@/app/client/profile/actions';
import type { ActionState } from '@/app/client/actions';

export default function PasswordForm() {
  const [state, action, pending] = useActionState<ActionState, FormData>(changePasswordAction, null);

  return (
    <form action={action} className="stack">
      {state?.error && <div className="cp-alert err" style={{ marginBottom: 0 }}>{state.error}</div>}
      {state?.success && <div className="cp-alert ok" style={{ marginBottom: 0 }}>{state.success}</div>}
      <div>
        <label className="label">Current password</label>
        <input type="password" name="current" required className="input" />
      </div>
      <div>
        <label className="label">New password (min 8 chars)</label>
        <input type="password" name="new" required minLength={8} className="input" />
      </div>
      <div>
        <label className="label">Confirm new password</label>
        <input type="password" name="confirm" required className="input" />
      </div>
      <button className="button" disabled={pending} style={{ width: '100%' }}>
        {pending ? 'Updating…' : 'Update Password'}
      </button>
    </form>
  );
}
