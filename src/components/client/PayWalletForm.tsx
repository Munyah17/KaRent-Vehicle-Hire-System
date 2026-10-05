'use client';

import { useActionState } from 'react';
import { CreditCard } from 'lucide-react';
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
      <input type="hidden" name="method" value="wallet" />
      {state?.error && (
        <div className="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}
      {state?.success && (
        <div className="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
          {state.success}
        </div>
      )}
      <div className="flex flex-wrap items-end gap-3">
        <div>
          <label className="label">Amount</label>
          <input
            type="number"
            name="amount"
            step="0.01"
            min="0.01"
            max={outstanding.toFixed(2)}
            defaultValue={outstanding.toFixed(2)}
            className="input w-40"
          />
        </div>
        <button className="btn-primary" disabled={pending || walletBalance <= 0}>
          <CreditCard className="w-4 h-4" /> {pending ? 'Processing…' : 'Pay'}
        </button>
      </div>
      <p className="text-xs text-slate-400 mt-3">
        Paid from wallet — balance ${walletBalance.toFixed(2)}
        {walletBalance <= 0 && ' (top up at the office or via Paynow)'}
      </p>
    </form>
  );
}
