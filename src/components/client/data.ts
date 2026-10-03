import { redirect } from 'next/navigation';
import { getSession, SessionUser } from '@/lib/auth';
import { one, query, run } from '@/lib/db';

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

export async function requireClient(): Promise<ClientContext> {
  const user = await getSession();
  if (!user) redirect('/login');
  if (user.role !== 'CLIENT') redirect('/');
  const client = await one<Client>('SELECT * FROM clients WHERE user_id = ?', [user.id]);
  if (!client) redirect('/login');
  return { user, client };
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

export function listBookings(clientId: number) {
  return query<Booking>(
    `SELECT b.*, v.make, v.model, v.reg_no, v.daily_rate
     FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
     WHERE b.client_id = ? ORDER BY b.id DESC`,
    [clientId]
  );
}

export function findClientBooking(clientId: number, bookingId: number) {
  return one<Booking>(
    `SELECT b.*, v.make, v.model, v.reg_no, v.daily_rate
     FROM bookings b JOIN vehicles v ON v.id = b.vehicle_id
     WHERE b.id = ? AND b.client_id = ?`,
    [bookingId, clientId]
  );
}

export async function amountPaid(bookingId: number): Promise<number> {
  const r = await one<{ s: string | number }>(
    "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE booking_id = ? AND status = 'successful'",
    [bookingId]
  );
  return Number(r?.s ?? 0);
}

export async function bookingOutstanding(bookingId: number): Promise<number> {
  const b = await one<{ total: string | number }>('SELECT total FROM bookings WHERE id = ?', [bookingId]);
  if (!b) return 0;
  const paid = await one<{ s: string | number }>(
    "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE booking_id = ? AND status = 'successful' AND purpose IN ('rental','extension')",
    [bookingId]
  );
  return Math.max(0, Math.round((Number(b.total) - Number(paid?.s ?? 0)) * 100) / 100);
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

export function listPayments(clientId: number) {
  return query<Payment>(
    `SELECT p.*, b.ref AS booking_ref FROM payments p
     LEFT JOIN bookings b ON b.id = p.booking_id
     WHERE p.client_id = ? ORDER BY p.id DESC`,
    [clientId]
  );
}

export async function walletBalance(clientId: number): Promise<number> {
  const r = await one<{ s: string | number }>(
    `SELECT COALESCE(SUM(t.amount),0) AS s FROM wallet_transactions t
     JOIN wallets w ON w.id = t.wallet_id WHERE w.client_id = ?`,
    [clientId]
  );
  return Number(r?.s ?? 0);
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

export function walletTransactions(clientId: number, limit = 100) {
  return query<WalletTxn>(
    `SELECT t.* FROM wallet_transactions t
     JOIN wallets w ON w.id = t.wallet_id
     WHERE w.client_id = ? ORDER BY t.id DESC LIMIT ${Math.floor(limit)}`,
    [clientId]
  );
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

export function listDeposits(clientId: number) {
  return query<Deposit>(
    `SELECT d.*, b.ref AS booking_ref, v.make, v.model, v.reg_no
     FROM deposits d
     JOIN bookings b ON b.id = d.booking_id
     JOIN vehicles v ON v.id = b.vehicle_id
     WHERE d.client_id = ? ORDER BY d.id DESC`,
    [clientId]
  );
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

export function listContracts(clientId: number) {
  return query<Contract>(
    `SELECT ct.*, b.ref AS booking_ref FROM contracts ct
     JOIN bookings b ON b.id = ct.booking_id
     WHERE ct.client_id = ? AND ct.status != 'void' ORDER BY ct.id DESC`,
    [clientId]
  );
}

export type ClientDoc = {
  id: number;
  doc_type: string;
  file_path: string;
  status: string;
  uploaded_at: string;
};

export function listClientDocs(clientId: number) {
  return query<ClientDoc>(
    'SELECT * FROM client_documents WHERE client_id = ? ORDER BY id DESC',
    [clientId]
  );
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

export function listBookingExtensions(bookingId: number) {
  return query<Extension>(
    'SELECT * FROM booking_extensions WHERE booking_id = ? ORDER BY id DESC',
    [bookingId]
  );
}

export function listClientExtensions(clientId: number) {
  return query<Extension & { booking_ref: string; make: string; model: string }>(
    `SELECT e.*, b.ref AS booking_ref, v.make, v.model
     FROM booking_extensions e
     JOIN bookings b ON b.id = e.booking_id
     JOIN vehicles v ON v.id = b.vehicle_id
     WHERE b.client_id = ? ORDER BY e.id DESC`,
    [clientId]
  );
}

export type Ticket = {
  id: number;
  subject: string;
  message: string;
  status: string;
  staff_reply: string | null;
  created_at: string;
};

export function listTickets(clientId: number) {
  return query<Ticket>(
    'SELECT * FROM support_tickets WHERE client_id = ? ORDER BY id DESC',
    [clientId]
  );
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

export function listNotifications(userId: number) {
  return query<Notif>(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50',
    [userId]
  );
}

export async function unreadCount(userId: number): Promise<number> {
  const r = await one<{ c: number }>(
    "SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND status = 'unread'",
    [userId]
  );
  return Number(r?.c ?? 0);
}

export async function notifyUser(userId: number, type: string, title: string, body = '', link: string | null = null) {
  await run(
    "INSERT INTO notifications (user_id, type, title, body, link, channel, status) VALUES (?,?,?,?,?,'in_app','unread')",
    [userId, type, title, body, link]
  );
}

export async function notifyStaff(type: string, title: string, body = '', link: string | null = null) {
  const staff = await query<{ id: number }>(
    "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name IN ('SUPER_ADMIN','STAFF') AND u.status = 'active'"
  );
  for (const s of staff) {
    await notifyUser(s.id, type, title, body, link);
  }
}

export async function nextTxnId(): Promise<string> {
  const r = await one<{ m: number | null }>(
    'SELECT MAX(CAST(SUBSTRING(txn_id, 5) AS UNSIGNED)) AS m FROM payments'
  );
  return 'TXN-' + (Number(r?.m ?? 0) + 1);
}
