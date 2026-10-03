'use server';

import { revalidatePath } from 'next/cache';
import { writeFile, mkdir } from 'fs/promises';
import path from 'path';
import crypto from 'crypto';
import bcrypt from 'bcryptjs';
import { run, one } from '@/lib/db';
import { getSession, hashPassword } from '@/lib/auth';
import { notifyStaff } from '@/components/client/data';
import type { ActionState } from '../actions';

const DOC_TYPES = new Set(['national_id', 'drivers_licence', 'passport', 'proof_of_address', 'other']);
const MIME_EXT: Record<string, string> = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
  'application/pdf': 'pdf',
};
const MAX_BYTES = 5 * 1024 * 1024;

async function requireClientCtx() {
  const user = await getSession();
  if (!user || user.role !== 'CLIENT') return null;
  const client = await one<{ id: number; user_id: number; full_name: string; kyc_status: string }>(
    'SELECT id, user_id, full_name, kyc_status FROM clients WHERE user_id = ?',
    [user.id]
  );
  return client ? { user, client } : null;
}

export async function saveProfileAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const ctx = await requireClientCtx();
  if (!ctx) return { error: 'Please sign in again.' };

  const fullName = String(formData.get('full_name') || '').trim();
  if (!fullName) return { error: 'Full name is required.' };
  const phone = String(formData.get('phone') || '').trim() || null;
  const dob = String(formData.get('dob') || '') || null;
  const address = String(formData.get('address') || '').trim() || null;
  const nationalId = String(formData.get('national_id') || '').trim() || null;
  const licenceNo = String(formData.get('licence_no') || '').trim() || null;
  const licenceExpiry = String(formData.get('licence_expiry') || '') || null;
  const taxNo = String(formData.get('tax_no') || '').trim() || null;
  const fiscalise = formData.get('fiscalise') ? 1 : 0;

  await run(
    `UPDATE clients SET full_name=?, dob=?, phone=?, address=?, national_id=?,
       licence_no=?, licence_expiry=?, tax_no=?, fiscalise=? WHERE id=?`,
    [fullName, dob, phone, address, nationalId, licenceNo, licenceExpiry, taxNo, fiscalise, ctx.client.id]
  );
  await run('UPDATE users SET name = ?, phone = ? WHERE id = ?', [fullName, phone, ctx.user.id]);
  if (ctx.client.kyc_status === 'pending') {
    await run("UPDATE clients SET kyc_status='under_review' WHERE id = ?", [ctx.client.id]);
  }
  revalidatePath('/client/profile');
  revalidatePath('/client');
  return { ok: true, success: 'Profile updated.' };
}

export async function uploadDocAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const ctx = await requireClientCtx();
  if (!ctx) return { error: 'Please sign in again.' };

  const docType = String(formData.get('doc_type') || 'other');
  const file = formData.get('doc');
  if (!(file instanceof File) || file.size === 0) return { error: 'No file uploaded.' };
  if (file.size > MAX_BYTES) return { error: `File exceeds maximum size of ${Math.round(MAX_BYTES / 1048576)}MB.` };
  const ext = MIME_EXT[file.type];
  if (!ext || !DOC_TYPES.has(docType)) return { error: `Invalid file type (${file.type || 'unknown'}).` };

  const dir = path.join(process.cwd(), 'storage', 'kyc');
  await mkdir(dir, { recursive: true });
  const name = crypto.randomBytes(16).toString('hex') + '.' + ext;
  await writeFile(path.join(dir, name), Buffer.from(await file.arrayBuffer()));

  await run('INSERT INTO client_documents (client_id, doc_type, file_path) VALUES (?,?,?)', [
    ctx.client.id,
    docType,
    'kyc/' + name,
  ]);
  if (ctx.client.kyc_status === 'pending') {
    await run("UPDATE clients SET kyc_status='under_review' WHERE id = ?", [ctx.client.id]);
  }
  await notifyStaff(
    'kyc',
    'KYC document uploaded',
    `${ctx.client.full_name} uploaded a ${docType.replace(/_/g, ' ')}`,
    `admin/client-edit.php?id=${ctx.client.id}`
  ).catch(() => {});
  revalidatePath('/client/profile');
  revalidatePath('/client/documents');
  return { ok: true, success: 'Document uploaded for review.' };
}

export async function changePasswordAction(prev: ActionState, formData: FormData): Promise<ActionState> {
  const ctx = await requireClientCtx();
  if (!ctx) return { error: 'Please sign in again.' };

  const current = String(formData.get('current') || '');
  const next = String(formData.get('new') || '');
  const confirm = String(formData.get('confirm') || '');

  const row = await one<{ password_hash: string }>('SELECT password_hash FROM users WHERE id = ?', [ctx.user.id]);
  const hash = row?.password_hash?.replace(/^\$2y\$/, '$2a$') ?? '';
  if (!row || !bcrypt.compareSync(current, hash)) {
    return { error: 'Current password is incorrect.' };
  }
  if (next.length < 8 || next !== confirm) {
    return { error: 'New passwords must match and be at least 8 characters.' };
  }
  await run('UPDATE users SET password_hash = ? WHERE id = ?', [hashPassword(next), ctx.user.id]);
  await run(
    "INSERT INTO audit_logs (user_id, action, module, record_type, record_id) VALUES (?,?,?,?,?)",
    [ctx.user.id, 'change_password', 'auth', 'user', ctx.user.id]
  ).catch(() => {});
  return { ok: true, success: 'Password updated.' };
}
