'use server';

import { revalidatePath } from 'next/cache';
import crypto from 'crypto';
import bcrypt from 'bcryptjs';
import { supabase } from '@/lib/supabase';
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
  const { data: client, error } = await supabase
    .from('clients')
    .select('id, user_id, full_name, kyc_status')
    .eq('user_id', user.id)
    .maybeSingle();
  if (error) return null;
  return client
    ? {
        user,
        client: client as { id: number; user_id: number; full_name: string; kyc_status: string },
      }
    : null;
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
  const fiscalise = !!formData.get('fiscalise');

  const { error: clientErr } = await supabase
    .from('clients')
    .update({
      full_name: fullName,
      dob,
      phone,
      address,
      national_id: nationalId,
      licence_no: licenceNo,
      licence_expiry: licenceExpiry,
      tax_no: taxNo,
      fiscalise,
      kyc_status: ctx.client.kyc_status === 'pending' ? 'under_review' : ctx.client.kyc_status,
    })
    .eq('id', ctx.client.id);
  if (clientErr) return { error: 'Could not update profile.' };

  const { error: userErr } = await supabase
    .from('users')
    .update({ name: fullName, phone })
    .eq('id', ctx.user.id);
  if (userErr) return { error: 'Could not update account details.' };

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

  const objectName = `${ctx.client.id}/${crypto.randomUUID()}.${ext}`;
  const bytes = Buffer.from(await file.arrayBuffer());

  const { error: uploadErr } = await supabase.storage
    .from('kyc-documents')
    .upload(objectName, bytes, { contentType: file.type, upsert: false });
  if (uploadErr) return { error: `Upload failed: ${uploadErr.message}` };

  const { error: dbErr } = await supabase.from('client_documents').insert({
    client_id: ctx.client.id,
    doc_type: docType,
    file_path: objectName,
  });

  if (dbErr) {
    await supabase.storage.from('kyc-documents').remove([objectName]);
    return { error: 'Could not save document record.' };
  }

  if (ctx.client.kyc_status === 'pending') {
    await supabase.from('clients').update({ kyc_status: 'under_review' }).eq('id', ctx.client.id);
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

  const { data: row, error } = await supabase
    .from('users')
    .select('password_hash')
    .eq('id', ctx.user.id)
    .maybeSingle();
  if (error) return { error: 'Could not verify password.' };

  const hash = normalizeHash(row?.password_hash ?? '');
  if (!row || !bcrypt.compareSync(current, hash)) {
    return { error: 'Current password is incorrect.' };
  }
  if (next.length < 8 || next !== confirm) {
    return { error: 'New passwords must match and be at least 8 characters.' };
  }

  const { error: updErr } = await supabase
    .from('users')
    .update({ password_hash: hashPassword(next) })
    .eq('id', ctx.user.id);
  if (updErr) return { error: 'Could not update password.' };

  const { error: auditErr } = await supabase.from('audit_logs').insert({
    user_id: ctx.user.id,
    action: 'change_password',
    module: 'auth',
    record_type: 'user',
    record_id: ctx.user.id,
  });
  if (auditErr) {
    console.error('audit log failed', auditErr.message);
  }
  return { ok: true, success: 'Password updated.' };
}

function normalizeHash(h: string): string {
  return h.replace(/^\$2y\$/, '$2a$');
}
