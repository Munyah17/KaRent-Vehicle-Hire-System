'use client';

import { useActionState } from 'react';
import { requestExtensionAction } from '@/app/client/bookings/[id]/actions';
import type { ActionState } from '@/app/client/actions';

export default function ExtensionForm({ bookingId, minDate }: { bookingId: number; minDate: string }) {
  const [state, action, pending] = useActionState<ActionState, FormData>(requestExtensionAction, null);

  return (
    <form action={action} className="flex flex-wrap items-end gap-3">
      <input type="hidden" name="booking_id" value={bookingId} />
      {state?.error && (
        <div className="w-full rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}
      {state?.success && (
        <div className="w-full rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
          {state.success}
        </div>
      )}
      <div>
        <label className="label">New return date</label>
        <input type="date" name="new_return" min={minDate} className="input" required />
      </div>
      <div>
        <label className="label">Time</label>
        <input type="time" name="new_return_time" defaultValue="17:00" className="input" />
      </div>
      <button className="btn-primary" disabled={pending}>
        {pending ? 'Sending…' : 'Request'}
      </button>
    </form>
  );
}
