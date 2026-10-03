'use client';

import { useActionState } from 'react';
import { payWalletAction } from '@/app/client/bookings/[id]/actions';
import type { ActionState } from '@/app/client/actions';

export default function PayWalletForm({
  bookingId,
  outstanding,
  walletBalance,
}: {
  bookingId: number;
  outstanding: number;
  walletBalance: number;
}) {
  const [state, action, pending] = useActionState<ActionState, FormData>(payWalletAction, null);

  return (
    <form action={action}>
      <input type="hidden" name="booking_id" value={bookingId} />
      {state?.error && <div className="cp-alert err">{state.error}</div>}
      {state?.success && <div className="cp-alert ok">{state.success}</div>}
      <div className="cp-form-row">
        <div className="fld">
          <label className="label">Amount</label>
          <input
            type="number"
            name="amount"
            step="0.01"
            min="0.01"
            max={outstanding.toFixed(2)}
            defaultValue={outstanding.toFixed(2)}
            required
            className="input"
            style={{ width: 160 }}
          />
        </div>
        <button className="button" disabled={pending || walletBalance <= 0}>
          {pending ? 'Processing…' : 'Pay from wallet'}
        </button>
      </div>
      <p className="muted" style={{ margin: '10px 0 0', fontSize: '.8rem' }}>
        Wallet balance: ${walletBalance.toFixed(2)}
        {walletBalance <= 0 && ' — top up is handled at the office or via Paynow.'}
      </p>
    </form>
  );
}
