import { redirect } from 'next/navigation';
import { getSession, SessionUser } from '@/lib/auth';
import { supabase } from '@/lib/supabase';

export type Client = {
  id: number;
  user_id: number;
  client_no: string;
  full_name: string;
  dob: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  national_id: string | null;
  licence_no: string | null;
  licence_expiry: string | null;
  tax_no: string | null;
  fiscalise: number;
  kyc_status: string;
  account_status: string;
  created_at: string;
};

export type ClientContext = { user: SessionUser; client: Client };

function checkError(label: string, result: { error?: { message: string } | null }): void {
  if (result.error) {
    throw new Error(`${label}: ${result.error.message}`);
  }
}

export async function requireClient(): Promise<ClientContext> {
  const user = await getSession();
  if (!user) redirect('/login');
  if (user.role !== 'CLIENT') redirect('/');

  const { data: client, error } = await supabase
    .from('clients')
    .select('*')
    .eq('user_id', user.id)
    .maybeSingle();
  if (error) throw new Error(`Failed to load client: ${error.message}`);
  if (!client) redirect('/login');

  return { user: user as SessionUser, client: client as unknown as Client };
}

export type Booking = {
  id: number;
  ref: string;
  client_id: number;
  vehicle_id: number;
  pickup_at: string;
  return_at: string;
  status: string;
  base_amount: string;
  additional_amount: string;
  discount: string;
  total: string;
  deposit_required: string;
  notes: string | null;
  created_at: string;
  make: string;
  model: string;
  reg_no: string;
  daily_rate: string;
};

function flattenBooking(row: Record<string, unknown>): Booking {
  const vehicle = (row.vehicles ?? {}) as Record<string, unknown>;
  return {
    id: row.id as number,
    ref: row.ref as string,
    client_id: row.client_id as number,
    vehicle_id: row.vehicle_id as number,
    pickup_at: row.pickup_at as string,
    return_at: row.return_at as string,
    status: row.status as string,
    base_amount: row.base_amount as string,
    additional_amount: row.additional_amount as string,
    discount: row.discount as string,
    total: row.total as string,
    deposit_required: row.deposit_required as string,
    notes: row.notes as string | null,
    created_at: row.created_at as string,
    make: vehicle.make as string,
    model: vehicle.model as string,
    reg_no: vehicle.reg_no as string,
    daily_rate: vehicle.daily_rate as string,
  };
}

export async function listBookings(clientId: number): Promise<Booking[]> {
  const { data, error } = await supabase
    .from('bookings')
    .select('*, vehicles!inner(make, model, reg_no, daily_rate)')
    .eq('client_id', clientId)
    .order('id', { ascending: false });
  checkError('listBookings', { error });
  return (data ?? []).map((row) => flattenBooking(row as Record<string, unknown>));
}

export async function findClientBooking(clientId: number, bookingId: number): Promise<Booking | null> {
  const { data, error } = await supabase
    .from('bookings')
    .select('*, vehicles!inner(make, model, reg_no, daily_rate)')
    .eq('id', bookingId)
    .eq('client_id', clientId)
    .maybeSingle();
  checkError('findClientBooking', { error });
  if (!data) return null;
  return flattenBooking(data as Record<string, unknown>);
}

export async function amountPaid(bookingId: number): Promise<number> {
  const { data, error } = await supabase
    .from('payments')
    .select('amount')
    .eq('booking_id', bookingId)
    .eq('status', 'successful');
  checkError('amountPaid', { error });
  return (data ?? []).reduce((sum, row) => sum + Number((row as { amount: string | number }).amount ?? 0), 0);
}

export async function bookingOutstanding(bookingId: number): Promise<number> {
  const { data: booking, error: e1 } = await supabase
    .from('bookings')
    .select('total')
    .eq('id', bookingId)
    .maybeSingle();
  checkError('bookingOutstanding', { error: e1 });
  if (!booking) return 0;

  const { data, error: e2 } = await supabase
    .from('payments')
    .select('amount')
    .eq('booking_id', bookingId)
    .eq('status', 'successful')
    .in('purpose', ['rental', 'extension']);
  checkError('bookingOutstanding', { error: e2 });
  const paid = (data ?? []).reduce((sum, row) => sum + Number((row as { amount: string | number }).amount ?? 0), 0);
  return Math.max(0, Math.round((Number((booking as { total: string | number }).total) - paid) * 100) / 100);
}

export type Payment = {
  id: number;
  txn_id: string;
  booking_id: number | null;
  client_id: number;
  amount: string;
  method: string;
  reference: string | null;
  purpose: string;
  status: string;
  poll_url: string | null;
  paid_at: string | null;
  notes: string | null;
  created_at: string;
  booking_ref?: string | null;
};

export async function listPayments(clientId: number): Promise<Payment[]> {
  const { data, error } = await supabase
    .from('payments')
    .select('*, bookings(ref)')
    .eq('client_id', clientId)
    .order('id', { ascending: false });
  checkError('listPayments', { error });
  return (data ?? []).map((row) => {
    const bookings = (row as Record<string, unknown>).bookings as { ref: string } | null;
    return {
      ...(row as unknown as Payment),
      booking_ref: bookings?.ref ?? null,
    };
  });
}

export async function walletBalance(clientId: number): Promise<number> {
  const { data: wallet, error: e1 } = await supabase
    .from('wallets')
    .select('id')
    .eq('client_id', clientId)
    .maybeSingle();
  checkError('walletBalance', { error: e1 });
  if (!wallet) return 0;

  const { data, error: e2 } = await supabase
    .from('wallet_transactions')
    .select('amount')
    .eq('wallet_id', (wallet as { id: number }).id);
  checkError('walletBalance', { error: e2 });
  return (data ?? []).reduce((sum, row) => sum + Number((row as { amount: string | number }).amount ?? 0), 0);
}

export type WalletTxn = {
  id: number;
  ref: string;
  type: string;
  amount: string;
  description: string | null;
  booking_id: number | null;
  created_at: string;
};

export async function walletTransactions(clientId: number, limit = 100): Promise<WalletTxn[]> {
  const { data: wallet, error: e1 } = await supabase
    .from('wallets')
    .select('id')
    .eq('client_id', clientId)
    .maybeSingle();
  checkError('walletTransactions', { error: e1 });
  if (!wallet) return [];

  const { data, error: e2 } = await supabase
    .from('wallet_transactions')
    .select('*')
    .eq('wallet_id', (wallet as { id: number }).id)
    .order('id', { ascending: false })
    .limit(Math.floor(limit));
  checkError('walletTransactions', { error: e2 });
  return (data ?? []) as WalletTxn[];
}

export type Deposit = {
  id: number;
  booking_id: number;
  required_amount: string;
  received_amount: string;
  deducted_amount: string;
  refunded_amount: string;
  status: string;
  booking_ref: string;
  make: string;
  model: string;
  reg_no: string;
};

export async function listDeposits(clientId: number): Promise<Deposit[]> {
  const { data, error } = await supabase
    .from('deposits')
    .select('*, bookings!inner(ref, vehicles!inner(make, model, reg_no))')
    .eq('client_id', clientId)
    .order('id', { ascending: false });
  checkError('listDeposits', { error });
  return (data ?? []).map((row) => {
    const typed = row as Record<string, unknown>;
    const bookings = typed.bookings as { ref: string; vehicles: { make: string; model: string; reg_no: string } };
    return {
      ...(typed as unknown as Deposit),
      booking_ref: bookings.ref,
      make: bookings.vehicles.make,
      model: bookings.vehicles.model,
      reg_no: bookings.vehicles.reg_no,
    };
  });
}

export type Contract = {
  id: number;
  booking_id: number;
  client_id: number;
  template_code: string;
  template_version: number;
  title: string;
  body: string;
  status: string;
  created_at: string;
  booking_ref: string;
};

export async function listContracts(clientId: number): Promise<Contract[]> {
  const { data, error } = await supabase
    .from('contracts')
    .select('*, bookings!inner(ref)')
    .eq('client_id', clientId)
    .neq('status', 'void')
    .order('id', { ascending: false });
  checkError('listContracts', { error });
  return (data ?? []).map((row) => {
    const bookings = (row as Record<string, unknown>).bookings as { ref: string };
    return {
      ...(row as unknown as Contract),
      booking_ref: bookings.ref,
    };
  });
}

export type ClientDoc = {
  id: number;
  doc_type: string;
  file_path: string;
  status: string;
  uploaded_at: string;
};

export async function listClientDocs(clientId: number): Promise<ClientDoc[]> {
  const { data, error } = await supabase
    .from('client_documents')
    .select('*')
    .eq('client_id', clientId)
    .order('id', { ascending: false });
  checkError('listClientDocs', { error });
  return (data ?? []) as ClientDoc[];
}

export type Extension = {
  id: number;
  booking_id: number;
  old_return_at: string;
  new_return_at: string;
  additional_amount: string;
  status: string;
  note: string | null;
  created_at: string;
};

export async function listBookingExtensions(bookingId: number): Promise<Extension[]> {
  const { data, error } = await supabase
    .from('booking_extensions')
    .select('*')
    .eq('booking_id', bookingId)
    .order('id', { ascending: false });
  checkError('listBookingExtensions', { error });
  return (data ?? []) as Extension[];
}

export async function listClientExtensions(clientId: number): Promise<(Extension & { booking_ref: string; make: string; model: string })[]> {
  const { data, error } = await supabase
    .from('booking_extensions')
    .select('*, bookings!inner(ref, vehicles!inner(make, model))')
    .eq('bookings.client_id', clientId)
    .order('id', { ascending: false });
  checkError('listClientExtensions', { error });
  return (data ?? []).map((row) => {
    const typed = row as Record<string, unknown>;
    const bookings = typed.bookings as { ref: string; vehicles: { make: string; model: string } };
    return {
      ...(typed as unknown as Extension),
      booking_ref: bookings.ref,
      make: bookings.vehicles.make,
      model: bookings.vehicles.model,
    };
  });
}

export type Ticket = {
  id: number;
  subject: string;
  message: string;
  status: string;
  staff_reply: string | null;
  created_at: string;
};

export async function listTickets(clientId: number): Promise<Ticket[]> {
  const { data, error } = await supabase
    .from('support_tickets')
    .select('*')
    .eq('client_id', clientId)
    .order('id', { ascending: false });
  checkError('listTickets', { error });
  return (data ?? []) as Ticket[];
}

export type Notif = {
  id: number;
  type: string;
  title: string;
  body: string | null;
  link: string | null;
  status: string;
  created_at: string;
};

export async function listNotifications(userId: number): Promise<Notif[]> {
  const { data, error } = await supabase
    .from('notifications')
    .select('*')
    .eq('user_id', userId)
    .order('id', { ascending: false })
    .limit(50);
  checkError('listNotifications', { error });
  return (data ?? []) as Notif[];
}

export async function unreadCount(userId: number): Promise<number> {
  const { count, error } = await supabase
    .from('notifications')
    .select('*', { count: 'exact', head: true })
    .eq('user_id', userId)
    .eq('status', 'unread');
  checkError('unreadCount', { error });
  return Number(count ?? 0);
}

export async function notifyUser(
  userId: number,
  type: string,
  title: string,
  body = '',
  link: string | null = null
): Promise<void> {
  const { error } = await supabase.from('notifications').insert({
    user_id: userId,
    type,
    title,
    body,
    link,
    channel: 'in_app',
    status: 'unread',
  });
  checkError('notifyUser', { error });
}

export async function notifyStaff(type: string, title: string, body = '', link: string | null = null): Promise<void> {
  const { data: roles, error: e1 } = await supabase
    .from('roles')
    .select('id')
    .in('name', ['SUPER_ADMIN', 'STAFF']);
  checkError('notifyStaff roles', { error: e1 });
  const roleIds = (roles ?? []).map((r) => (r as { id: number }).id);
  if (!roleIds.length) return;

  const { data: staff, error: e2 } = await supabase
    .from('users')
    .select('id')
    .in('role_id', roleIds)
    .eq('status', 'active');
  checkError('notifyStaff users', { error: e2 });
  for (const s of staff ?? []) {
    await notifyUser((s as { id: number }).id, type, title, body, link);
  }
}

export async function nextTxnId(): Promise<string> {
  const { data, error } = await supabase.rpc('next_txn_id');
  checkError('nextTxnId', { error });
  return data as string;
}
