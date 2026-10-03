'use client';

import { useActionState } from 'react';
import { requestExtensionAction } from '@/app/client/bookings/[id]/actions';
import type { ActionState } from '@/app/client/actions';

export default function ExtensionForm({ bookingId, minDate }: { bookingId: number; minDate: string }) {
  const [state, action, pending] = useActionState<ActionState, FormData>(requestExtensionAction, null);

  return (
    <form action={action} className="cp-form-row">
      <input type="hidden" name="booking_id" value={bookingId} />
      {state?.error && <div className="cp-alert err" style={{ width: '100%', marginBottom: 0 }}>{state.error}</div>}
      {state?.success && <div className="cp-alert ok" style={{ width: '100%', marginBottom: 0 }}>{state.success}</div>}
      <div className="fld">
        <label className="label">New return date</label>
        <input type="date" name="new_return" min={minDate} required className="input" />
      </div>
      <div className="fld">
        <label className="label">Time</label>
        <input type="time" name="new_return_time" defaultValue="17:00" className="input" />
      </div>
      <button className="button" disabled={pending}>{pending ? 'Sending…' : 'Request'}</button>
    </form>
  );
}
