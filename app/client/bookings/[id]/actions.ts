'use server';

import { revalidatePath } from 'next/cache';
import { supabase } from '@/lib/supabase';
import { getSession } from '@/lib/auth';
import { money } from '@/lib/helpers';
import {
  findClientBooking,
  bookingOutstanding,
  notifyStaff,
  notifyUser,
} from '@/components/client/data';
import type { ActionState } from '../../actions';

async function requireClientCtx() {
  const user = await getSession();
  if (!user || user.role !== 'CLIENT') return null;
  const { data: client, error } = await supabase
    .from('clients')
    .select('id, user_id, full_name, email')
    .eq('user_id', user.id)
    .maybeSingle();
  if (error) return null;
  return client ? { user, client: client as { id: number; user_id: number; full_name: string; email: string | null } } : null;
}

export async function requestExtensionAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const ctx = await requireClientCtx();
  if (!ctx) return { error: 'Please sign in again.' };

  const bookingId = Number(formData.get('booking_id'));
  const dateStr = String(formData.get('new_return') || '');
  const timeStr = String(formData.get('new_return_time') || '17:00');
  if (!bookingId || !dateStr) return { error: 'Please choose a new return date.' };

  const b = await findClientBooking(ctx.client.id, bookingId);
  if (!b) return { error: 'Booking not found.' };
  if (!['active', 'confirmed'].includes(b.status)) {
    return { error: 'Extensions only apply to active/confirmed bookings.' };
  }

  const newReturn = `${dateStr}T${timeStr || '17:00'}:00`;
  const newTs = new Date(newReturn);
  const oldTs = new Date(String(b.return_at).replace(' ', 'T'));
  if (isNaN(newTs.getTime()) || newTs <= oldTs) {
    return { error: 'New return date must be later than the current return date.' };
  }

  const days = Math.ceil((newTs.getTime() - oldTs.getTime()) / 86400000);
  const additional = Math.round(days * Number(b.daily_rate) * 100) / 100;

  const { error } = await supabase.from('booking_extensions').insert({
    booking_id: bookingId,
    old_return_at: b.return_at,
    new_return_at: newReturn,
    additional_amount: additional,
    requested_by: ctx.user.id,
  });
  if (error) return { error: 'Could not request extension. Please try again.' };

  await notifyStaff(
    'extension',
    `Extension requested ${b.ref}`,
    `Requested return: ${newReturn} (+${money(additional)})`,
    `/admin/bookings`
  );
  revalidatePath(`/client/bookings/${bookingId}`);
  revalidatePath('/client/extensions');
  return { ok: true, success: `Extension requested — pending approval. Extra cost: ${money(additional)}` };
}

/*
 * Expected secure PostgreSQL RPC for atomic wallet payments.
 * Schema agent must create:
 *
 * CREATE OR REPLACE FUNCTION public.pay_booking_from_wallet(
 *   p_client_id integer,
 *   p_booking_id integer,
 *   p_amount numeric
 * ) RETURNS TABLE(payment_id integer, txn_id text, wallet_ref text)
 * LANGUAGE plpgsql
 * SECURITY DEFINER
 * AS $$
 *   DECLARE
 *     v_wallet_id integer;
 *     v_balance numeric;
 *     v_txn_id text;
 *     v_ref text;
 *     v_payment_id integer;
 *   BEGIN
 *     INSERT INTO public.wallets (client_id) VALUES (p_client_id)
 *       ON CONFLICT (client_id) DO NOTHING;
 *
 *     SELECT id INTO v_wallet_id FROM public.wallets WHERE client_id = p_client_id FOR UPDATE;
 *     IF v_wallet_id IS NULL THEN RAISE EXCEPTION 'no wallet'; END IF;
 *
 *     SELECT COALESCE(SUM(amount),0) INTO v_balance
 *     FROM public.wallet_transactions WHERE wallet_id = v_wallet_id;
 *     IF v_balance < p_amount THEN RAISE EXCEPTION 'insufficient wallet balance'; END IF;
 *
 *     v_txn_id := public.next_txn_id();
 *     v_ref := 'WT-' || gen_random_uuid();
 *
 *     INSERT INTO public.wallet_transactions (wallet_id, ref, type, amount, description, booking_id)
 *     VALUES (v_wallet_id, v_ref, 'booking_payment', -p_amount,
 *             'Payment ' || (SELECT ref FROM public.bookings WHERE id = p_booking_id), p_booking_id);
 *
 *     INSERT INTO public.payments (txn_id, booking_id, client_id, amount, method, purpose, status, paid_at)
 *     VALUES (v_txn_id, p_booking_id, p_client_id, p_amount, 'wallet', 'rental', 'successful', now())
 *     RETURNING id INTO v_payment_id;
 *
 *     RETURN QUERY SELECT v_payment_id, v_txn_id, v_ref;
 *   END;
 * $$;
 */
export async function payWalletAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const ctx = await requireClientCtx();
  if (!ctx) return { error: 'Please sign in again.' };

  const bookingId = Number(formData.get('booking_id'));
  const amount = Math.round(Number(formData.get('amount')) * 100) / 100;
  const b = await findClientBooking(ctx.client.id, bookingId);
  if (!b) return { error: 'Booking not found.' };
  if (!['pending', 'confirmed', 'active'].includes(b.status)) {
    return { error: 'This booking cannot be paid.' };
  }

  const outstanding = await bookingOutstanding(bookingId);
  if (!isFinite(amount) || amount <= 0 || amount > outstanding + 0.01) {
    return { error: 'Invalid payment amount.' };
  }

  const { data, error } = await supabase
    .rpc('pay_booking_from_wallet', {
      p_client_id: ctx.client.id,
      p_booking_id: bookingId,
      p_amount: amount,
    })
    .single();

  if (error || !data) {
    return { error: error?.message || 'Could not record payment.' };
  }

  const { payment_id: paymentId } = data as { payment_id: number; txn_id: string; wallet_ref: string };

  const { error: auditErr } = await supabase.from('audit_logs').insert({
    user_id: ctx.user.id,
    action: 'record_payment',
    module: 'payments',
    record_type: 'payment',
    record_id: paymentId,
    new_value: JSON.stringify({ amount, method: 'wallet', purpose: 'rental', booking_id: bookingId }),
  });
  if (auditErr) {
    // Audit logging failure must not break the confirmed payment; surface in server logs only.
    console.error('audit log failed', auditErr.message);
  }

  await notifyUser(
    ctx.user.id,
    'payment',
    'Payment received',
    `${money(amount)} received (wallet).`,
    '/client/payments'
  ).catch(() => {});

  revalidatePath(`/client/bookings/${bookingId}`);
  revalidatePath('/client/payments');
  revalidatePath('/client/wallet');
  return { ok: true, success: `${money(amount)} paid from wallet.` };
}
