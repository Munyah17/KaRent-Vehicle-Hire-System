'use server';

import { revalidatePath } from 'next/cache';
import { pool, one, run } from '@/lib/db';
import { getSession } from '@/lib/auth';
import { money } from '@/lib/helpers';
import {
  findClientBooking,
  bookingOutstanding,
  nextTxnId,
  notifyStaff,
  notifyUser,
} from '@/components/client/data';
import type { ActionState } from '../../actions';

async function requireClientCtx() {
  const user = await getSession();
  if (!user || user.role !== 'CLIENT') return null;
  const client = await one<{ id: number; user_id: number; full_name: string; email: string | null }>(
    'SELECT id, user_id, full_name, email FROM clients WHERE user_id = ?',
    [user.id]
  );
  return client ? { user, client } : null;
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

  const newReturn = `${dateStr} ${timeStr || '17:00'}:00`;
  const newTs = new Date(newReturn.replace(' ', 'T'));
  const oldTs = new Date(String(b.return_at).replace(' ', 'T'));
  if (isNaN(newTs.getTime()) || newTs <= oldTs) {
    return { error: 'New return date must be later than the current return date.' };
  }

  const days = Math.ceil((newTs.getTime() - oldTs.getTime()) / 86400000);
  const additional = Math.round(days * Number(b.daily_rate) * 100) / 100;

  await run(
    `INSERT INTO booking_extensions (booking_id, old_return_at, new_return_at, additional_amount, requested_by)
     VALUES (?,?,?,?,?)`,
    [bookingId, b.return_at, newReturn, additional, ctx.user.id]
  );
  await notifyStaff(
    'extension',
    `Extension requested ${b.ref}`,
    `Requested return: ${newReturn} (+${money(additional)})`,
    `admin/booking.php?id=${bookingId}`
  );
  revalidatePath(`/client/bookings/${bookingId}`);
  revalidatePath('/client/extensions');
  return { ok: true, success: `Extension requested — pending approval. Extra cost: ${money(additional)}` };
}

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

  const conn = await pool().getConnection();
  let paymentId = 0;
  try {
    await conn.beginTransaction();

    await conn.execute('INSERT IGNORE INTO wallets (client_id) VALUES (?)', [ctx.client.id]);
    const [wRows] = await conn.execute('SELECT id FROM wallets WHERE client_id = ? FOR UPDATE', [ctx.client.id]);
    const walletId = (wRows as { id: number }[])[0]?.id;
    if (!walletId) throw new Error('no wallet');

    const [bRows] = await conn.execute(
      'SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions WHERE wallet_id = ?',
      [walletId]
    );
    const balance = Number((bRows as { s: string }[])[0]?.s ?? 0);
    if (balance < amount) {
      await conn.rollback();
      return { error: 'Insufficient wallet balance.' };
    }

    const wref = 'WT-' + Math.random().toString(36).slice(2, 12).toUpperCase();
    await conn.execute(
      `INSERT INTO wallet_transactions (wallet_id, ref, type, amount, description, booking_id)
       VALUES (?,?,?,?,?,?)`,
      [walletId, wref, 'booking_payment', -Math.abs(amount), `Payment ${b.ref}`, bookingId]
    );

    const txn = await nextTxnId();
    const [res] = await conn.execute(
      `INSERT INTO payments (txn_id, booking_id, client_id, amount, method, purpose, status, paid_at)
       VALUES (?,?,?,?,'wallet','rental','successful',NOW())`,
      [txn, bookingId, ctx.client.id, amount]
    );
    paymentId = (res as { insertId: number }).insertId;
    await conn.commit();
  } catch {
    try { await conn.rollback(); } catch {}
    return { error: 'Could not record payment.' };
  } finally {
    conn.release();
  }

  await run(
    "INSERT INTO audit_logs (user_id, action, module, record_type, record_id, new_value) VALUES (?,?,?,?,?,?)",
    [ctx.user.id, 'record_payment', 'payments', 'payment', paymentId, JSON.stringify({ amount, method: 'wallet', purpose: 'rental', booking_id: bookingId })]
  ).catch(() => {});
  await notifyUser(
    ctx.user.id,
    'payment',
    'Payment received',
    `${money(amount)} received (wallet).`,
    'client/payments.php'
  ).catch(() => {});

  revalidatePath(`/client/bookings/${bookingId}`);
  revalidatePath('/client/payments');
  revalidatePath('/client/wallet');
  return { ok: true, success: `${money(amount)} paid from wallet.` };
}
