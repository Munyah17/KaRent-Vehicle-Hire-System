'use client';

import { useActionState } from 'react';
import { changePasswordAction } from '@/app/client/profile/actions';
import type { ActionState } from '@/app/client/actions';

export default function PasswordForm() {
  const [state, action, pending] = useActionState<ActionState, FormData>(changePasswordAction, null);

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
      <button className="btn-primary w-full justify-center" disabled={pending}>
        {pending ? 'Updating…' : 'Update Password'}
      </button>
    </form>
  );
}
