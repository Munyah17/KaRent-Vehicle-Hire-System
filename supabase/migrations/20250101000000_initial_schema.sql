-- ============================================================
-- KaRent — Supabase/PostgreSQL initial migration
-- Ported from MySQL schema in database/schema.sql
-- ============================================================

-- Use pgcrypto for secure tokens/hashes if ever needed via SQL.
create extension if not exists "pgcrypto" with schema extensions;

-- ---------- Updated_at trigger helper ----------
create or replace function public.update_updated_at_column()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

-- ---------- Access control ----------
create table public.roles (
  id integer generated always as identity primary key,
  name varchar(50) not null unique,
  code varchar(50) not null unique,
  label varchar(100) not null
);

create table public.permissions (
  id integer generated always as identity primary key,
  code varchar(60) not null unique,
  label varchar(120) not null,
  module varchar(60) not null
);

create table public.role_permissions (
  role_id integer not null,
  permission_id integer not null,
  primary key (role_id, permission_id),
  foreign key (role_id) references public.roles(id) on delete cascade,
  foreign key (permission_id) references public.permissions(id) on delete cascade
);

create table public.users (
  id integer generated always as identity primary key,
  role_id integer not null,
  name varchar(120) not null,
  email varchar(190) not null unique,
  username varchar(60) unique,
  phone varchar(40),
  password_hash varchar(255) not null,
  status text not null default 'active' check (status in ('active','suspended','disabled')),
  must_change_password boolean not null default false,
  last_login_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  foreign key (role_id) references public.roles(id)
);
create index idx_users_status on public.users(status);
create trigger trg_users_updated_at before update on public.users
  for each row execute function public.update_updated_at_column();

create table public.user_permissions (
  user_id integer not null,
  permission_id integer not null,
  allowed boolean not null default true,
  primary key (user_id, permission_id),
  foreign key (user_id) references public.users(id) on delete cascade,
  foreign key (permission_id) references public.permissions(id) on delete cascade
);

create table public.login_attempts (
  id bigint generated always as identity primary key,
  email varchar(190) not null,
  ip varchar(45) not null,
  successful boolean not null default false,
  attempted_at timestamptz not null default now()
);
create index idx_attempts on public.login_attempts(email, ip, attempted_at);

create table public.password_resets (
  id bigint generated always as identity primary key,
  user_id integer not null,
  token_hash char(64) not null,
  expires_at timestamptz not null,
  used_at timestamptz,
  created_at timestamptz not null default now(),
  foreign key (user_id) references public.users(id) on delete cascade
);
create index idx_reset_token on public.password_resets(token_hash);

-- ---------- Clients ----------
create table public.clients (
  id integer generated always as identity primary key,
  user_id integer unique,
  client_no varchar(20) not null unique,
  full_name varchar(160) not null,
  dob date,
  phone varchar(40),
  email varchar(190),
  address text,
  national_id varchar(60),
  licence_no varchar(60),
  licence_expiry date,
  photo varchar(255),
  tax_no varchar(60),
  fiscalise boolean not null default false,
  kyc_status text not null default 'pending' check (kyc_status in ('pending','under_review','verified','rejected')),
  account_status text not null default 'active' check (account_status in ('active','suspended')),
  source text not null default 'online' check (source in ('online','walk_in','guest')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  foreign key (user_id) references public.users(id) on delete set null
);
create index idx_clients_phone on public.clients(phone);
create index idx_clients_email on public.clients(email);
create index idx_clients_national_id on public.clients(national_id);
create index idx_clients_kyc on public.clients(kyc_status);
create trigger trg_clients_updated_at before update on public.clients
  for each row execute function public.update_updated_at_column();

create table public.client_documents (
  id integer generated always as identity primary key,
  client_id integer not null,
  doc_type text not null check (doc_type in ('national_id','drivers_licence','passport','proof_of_address','other')),
  file_path varchar(255) not null,
  status text not null default 'pending' check (status in ('pending','verified','rejected')),
  reviewed_by integer,
  reviewed_at timestamptz,
  uploaded_at timestamptz not null default now(),
  foreign key (client_id) references public.clients(id) on delete cascade,
  foreign key (reviewed_by) references public.users(id) on delete set null
);
create index idx_client_docs on public.client_documents(client_id, doc_type);

-- ---------- Vehicles ----------
create table public.vehicles (
  id integer generated always as identity primary key,
  reg_no varchar(30) not null unique,
  make varchar(60) not null,
  model varchar(60) not null,
  year smallint check (year is null or year between 1900 and 2100),
  colour varchar(40),
  transmission text not null default 'automatic' check (transmission in ('automatic','manual')),
  fuel_type text not null default 'petrol' check (fuel_type in ('petrol','diesel','hybrid','electric')),
  engine_capacity varchar(20),
  seats smallint not null default 5 check (seats > 0),
  category text not null default 'sedan' check (category in ('budget','sedan','suv','truck','premium','pool','utility')),
  mileage integer not null default 0 check (mileage >= 0),
  description text,
  daily_rate numeric(10,2) not null default 0.00,
  weekly_rate numeric(10,2),
  monthly_rate numeric(10,2),
  deposit numeric(10,2) not null default 0.00,
  excess_mileage_rate numeric(8,2),
  status text not null default 'available' check (status in ('available','reserved','on_hire','maintenance','unavailable')),
  is_public boolean not null default true,
  is_featured boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index idx_vehicles_status on public.vehicles(status);
create index idx_vehicles_public on public.vehicles(is_public, status);
create trigger trg_vehicles_updated_at before update on public.vehicles
  for each row execute function public.update_updated_at_column();

create table public.vehicle_photos (
  id integer generated always as identity primary key,
  vehicle_id integer not null,
  file_path varchar(255) not null,
  angle text not null default 'other' check (angle in ('front','back','left_side','right_side','interior_front','interior_back','top','other')),
  is_primary boolean not null default false,
  is_public boolean not null default true,
  sort_order smallint not null default 0,
  uploaded_at timestamptz not null default now(),
  foreign key (vehicle_id) references public.vehicles(id) on delete cascade
);
create index idx_vp_vehicle on public.vehicle_photos(vehicle_id);

create table public.vehicle_documents (
  id integer generated always as identity primary key,
  vehicle_id integer not null,
  doc_type text not null check (doc_type in ('registration','insurance','licence','other')),
  title varchar(120),
  file_path varchar(255) not null,
  expiry_date date,
  uploaded_at timestamptz not null default now(),
  foreign key (vehicle_id) references public.vehicles(id) on delete cascade
);

create table public.vehicle_devices (
  id integer generated always as identity primary key,
  vehicle_id integer not null,
  device_type varchar(40) not null,
  identifier varchar(120),
  status text not null default 'inactive' check (status in ('active','inactive')),
  installed_at date,
  foreign key (vehicle_id) references public.vehicles(id) on delete cascade
);

-- ---------- Suppliers / expenses ----------
create table public.suppliers (
  id integer generated always as identity primary key,
  name varchar(160) not null,
  contact_person varchar(120),
  phone varchar(40),
  email varchar(190),
  address text,
  service_category varchar(80),
  notes text,
  created_at timestamptz not null default now()
);

-- ---------- Bookings ----------
create table public.bookings (
  id integer generated always as identity primary key,
  ref varchar(20) not null unique,
  client_id integer not null,
  vehicle_id integer not null,
  pickup_at timestamptz not null,
  return_at timestamptz not null,
  status text not null default 'pending' check (status in ('pending','confirmed','active','completed','cancelled','overdue')),
  base_amount numeric(10,2) not null default 0.00,
  additional_amount numeric(10,2) not null default 0.00,
  discount numeric(10,2) not null default 0.00,
  total numeric(10,2) not null default 0.00,
  deposit_required numeric(10,2) not null default 0.00,
  notes text,
  source text not null default 'online' check (source in ('online','walk_in','guest')),
  created_by integer,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  foreign key (client_id) references public.clients(id),
  foreign key (vehicle_id) references public.vehicles(id),
  foreign key (created_by) references public.users(id) on delete set null
);
create index idx_bookings_client on public.bookings(client_id);
create index idx_bookings_vehicle_dates on public.bookings(vehicle_id, pickup_at, return_at);
create index idx_bookings_status on public.bookings(status);
create index idx_bookings_dates on public.bookings(pickup_at, return_at);
create trigger trg_bookings_updated_at before update on public.bookings
  for each row execute function public.update_updated_at_column();

create table public.booking_charges (
  id integer generated always as identity primary key,
  booking_id integer not null,
  label varchar(160) not null,
  amount numeric(10,2) not null,
  created_by integer,
  created_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete cascade,
  foreign key (created_by) references public.users(id) on delete set null
);

create table public.booking_status_history (
  id bigint generated always as identity primary key,
  booking_id integer not null,
  status varchar(30) not null,
  note varchar(255),
  changed_by integer,
  created_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete cascade,
  foreign key (changed_by) references public.users(id) on delete set null
);
create index idx_bsh on public.booking_status_history(booking_id);

create table public.booking_extensions (
  id integer generated always as identity primary key,
  booking_id integer not null,
  old_return_at timestamptz not null,
  new_return_at timestamptz not null,
  additional_amount numeric(10,2) not null default 0.00,
  status text not null default 'pending' check (status in ('pending','approved','rejected')),
  requested_by integer,
  decided_by integer,
  decided_at timestamptz,
  note varchar(255),
  created_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete cascade,
  foreign key (requested_by) references public.users(id) on delete set null,
  foreign key (decided_by) references public.users(id) on delete set null
);

-- ---------- Payments / deposits / wallets ----------
create table public.payments (
  id integer generated always as identity primary key,
  txn_id varchar(40) not null unique,
  booking_id integer,
  client_id integer not null,
  amount numeric(10,2) not null,
  method text not null check (method in ('cash','bank_transfer','paynow','card','wallet','other')),
  reference varchar(120),
  purpose text not null default 'rental' check (purpose in ('rental','deposit','topup','extension','other')),
  status text not null default 'pending' check (status in ('pending','successful','failed','cancelled','refunded')),
  poll_url varchar(255),
  paid_at timestamptz,
  staff_id integer,
  notes varchar(255),
  created_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete set null,
  foreign key (client_id) references public.clients(id),
  foreign key (staff_id) references public.users(id) on delete set null
);
create index idx_payments_booking on public.payments(booking_id, status);
create index idx_payments_client on public.payments(client_id);
create index idx_payments_status on public.payments(status);

create table public.deposits (
  id integer generated always as identity primary key,
  booking_id integer not null unique,
  client_id integer not null,
  required_amount numeric(10,2) not null default 0.00,
  received_amount numeric(10,2) not null default 0.00,
  deducted_amount numeric(10,2) not null default 0.00,
  refunded_amount numeric(10,2) not null default 0.00,
  status text not null default 'pending' check (status in ('pending','partial','held','released','forfeited')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete cascade,
  foreign key (client_id) references public.clients(id)
);
create trigger trg_deposits_updated_at before update on public.deposits
  for each row execute function public.update_updated_at_column();

create table public.deposit_transactions (
  id bigint generated always as identity primary key,
  deposit_id integer not null,
  type text not null check (type in ('received','deduction','refund','adjustment')),
  amount numeric(10,2) not null,
  reason varchar(160),
  payment_id integer,
  created_by integer,
  created_at timestamptz not null default now(),
  foreign key (deposit_id) references public.deposits(id) on delete cascade,
  foreign key (payment_id) references public.payments(id) on delete set null,
  foreign key (created_by) references public.users(id) on delete set null
);

create table public.wallets (
  id integer generated always as identity primary key,
  client_id integer not null unique,
  created_at timestamptz not null default now(),
  foreign key (client_id) references public.clients(id) on delete cascade
);

create table public.wallet_transactions (
  id bigint generated always as identity primary key,
  wallet_id integer not null,
  ref varchar(40) not null unique,
  type text not null check (type in ('topup','booking_payment','refund','adjustment','debit','credit')),
  amount numeric(10,2) not null,
  description varchar(255),
  payment_id integer,
  booking_id integer,
  created_by integer,
  created_at timestamptz not null default now(),
  foreign key (wallet_id) references public.wallets(id) on delete cascade,
  foreign key (payment_id) references public.payments(id) on delete set null,
  foreign key (booking_id) references public.bookings(id) on delete set null,
  foreign key (created_by) references public.users(id) on delete set null
);
create index idx_wallet_txn on public.wallet_transactions(wallet_id, created_at);

create sequence public.payment_txn_seq start 100000;

create or replace function public.next_txn_id()
returns text
language sql
security definer
set search_path = public
as $$
  select 'TXN-' || nextval('public.payment_txn_seq')::text;
$$;

create or replace function public.pay_booking_from_wallet(
  p_client_id integer,
  p_booking_id integer,
  p_amount numeric
)
returns table(payment_id integer, txn_id text, wallet_ref text)
language plpgsql
security definer
set search_path = public, extensions
as $$
declare
  v_wallet_id integer;
  v_balance numeric(10,2);
  v_txn_id text;
  v_ref text;
  v_payment_id integer;
begin
  if p_amount <= 0 then
    raise exception 'invalid payment amount';
  end if;

  if not exists (
    select 1 from public.bookings
    where id = p_booking_id and client_id = p_client_id
  ) then
    raise exception 'booking not found';
  end if;

  insert into public.wallets (client_id)
  values (p_client_id)
  on conflict (client_id) do nothing;

  select id into v_wallet_id
  from public.wallets
  where client_id = p_client_id
  for update;

  select coalesce(sum(amount), 0) into v_balance
  from public.wallet_transactions
  where wallet_id = v_wallet_id;

  if v_balance < p_amount then
    raise exception 'insufficient wallet balance';
  end if;

  v_txn_id := public.next_txn_id();
  v_ref := 'WT-' || gen_random_uuid()::text;

  insert into public.payments
    (txn_id, booking_id, client_id, amount, method, purpose, status, paid_at)
  values
    (v_txn_id, p_booking_id, p_client_id, p_amount, 'wallet', 'rental', 'successful', now())
  returning id into v_payment_id;

  insert into public.wallet_transactions
    (wallet_id, ref, type, amount, description, payment_id, booking_id)
  values
    (v_wallet_id, v_ref, 'booking_payment', -p_amount,
     'Payment for booking ' || (select ref from public.bookings where id = p_booking_id),
     v_payment_id, p_booking_id);

  return query select v_payment_id, v_txn_id, v_ref;
end;
$$;

revoke all on function public.next_txn_id() from public, anon, authenticated;
revoke all on function public.pay_booking_from_wallet(integer, integer, numeric) from public, anon, authenticated;
grant execute on function public.next_txn_id() to service_role;
grant execute on function public.pay_booking_from_wallet(integer, integer, numeric) to service_role;

-- ---------- Contracts / documents ----------
create table public.contract_templates (
  id integer generated always as identity primary key,
  code varchar(40) not null unique,
  name varchar(160) not null,
  body text not null,
  version integer not null default 1,
  is_active boolean not null default true,
  updated_by integer,
  updated_at timestamptz not null default now(),
  created_at timestamptz not null default now(),
  foreign key (updated_by) references public.users(id) on delete set null
);
create trigger trg_contract_templates_updated_at before update on public.contract_templates
  for each row execute function public.update_updated_at_column();

create table public.contracts (
  id integer generated always as identity primary key,
  booking_id integer not null,
  client_id integer not null,
  template_id integer,
  template_code varchar(40) not null,
  template_version integer not null,
  title varchar(160) not null,
  body text not null,
  status text not null default 'generated' check (status in ('generated','signed','void')),
  file_path varchar(255),
  created_by integer,
  created_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete cascade,
  foreign key (client_id) references public.clients(id),
  foreign key (template_id) references public.contract_templates(id) on delete set null,
  foreign key (created_by) references public.users(id) on delete set null
);
create index idx_contracts_booking on public.contracts(booking_id);

create table public.signatures (
  id integer generated always as identity primary key,
  contract_id integer not null,
  signer_name varchar(160) not null,
  signature_data text,
  signed_at timestamptz not null default now(),
  staff_id integer,
  ip varchar(45),
  foreign key (contract_id) references public.contracts(id) on delete cascade,
  foreign key (staff_id) references public.users(id) on delete set null
);

-- ---------- Checklists ----------
create table public.checklist_items (
  id integer generated always as identity primary key,
  category varchar(40) not null,
  label varchar(120) not null,
  sort_order smallint not null default 0,
  is_active boolean not null default true
);

create table public.checklists (
  id integer generated always as identity primary key,
  booking_id integer not null,
  type text not null check (type in ('collection','return')),
  mileage integer,
  fuel_level smallint check (fuel_level is null or fuel_level between 0 and 100),
  condition_notes text,
  staff_id integer,
  client_ack_name varchar(160),
  client_ack_at timestamptz,
  created_at timestamptz not null default now(),
  foreign key (booking_id) references public.bookings(id) on delete cascade,
  foreign key (staff_id) references public.users(id) on delete set null,
  unique (booking_id, type)
);

create table public.checklist_results (
  id bigint generated always as identity primary key,
  checklist_id integer not null,
  item_id integer not null,
  status text not null default 'good' check (status in ('good','damaged','missing','na')),
  comment varchar(255),
  photo varchar(255),
  foreign key (checklist_id) references public.checklists(id) on delete cascade,
  foreign key (item_id) references public.checklist_items(id),
  unique (checklist_id, item_id)
);

-- ---------- Maintenance / damage / expenses ----------
create table public.maintenance (
  id integer generated always as identity primary key,
  vehicle_id integer not null,
  maint_type varchar(80) not null,
  maint_date date not null,
  mileage integer,
  description text,
  cost numeric(10,2) not null default 0.00,
  supplier_id integer,
  receipt varchar(255),
  next_service_date date,
  next_service_mileage integer,
  created_by integer,
  created_at timestamptz not null default now(),
  foreign key (vehicle_id) references public.vehicles(id),
  foreign key (supplier_id) references public.suppliers(id) on delete set null,
  foreign key (created_by) references public.users(id) on delete set null
);
create index idx_maint_vehicle on public.maintenance(vehicle_id);
create index idx_maint_next on public.maintenance(next_service_date);

create table public.damages (
  id integer generated always as identity primary key,
  vehicle_id integer not null,
  client_id integer,
  booking_id integer,
  incident_date date not null,
  description text not null,
  photos text,
  estimated_cost numeric(10,2),
  actual_cost numeric(10,2),
  client_charge numeric(10,2),
  status text not null default 'reported' check (status in ('reported','assessed','charged','resolved')),
  notes text,
  created_by integer,
  created_at timestamptz not null default now(),
  foreign key (vehicle_id) references public.vehicles(id),
  foreign key (client_id) references public.clients(id) on delete set null,
  foreign key (booking_id) references public.bookings(id) on delete set null,
  foreign key (created_by) references public.users(id) on delete set null
);

create table public.expenses (
  id integer generated always as identity primary key,
  expense_date date not null,
  category text not null default 'other' check (category in ('fuel','maintenance','repairs','cleaning','insurance','licensing','office','marketing','other')),
  amount numeric(10,2) not null,
  description varchar(255),
  supplier_id integer,
  vehicle_id integer,
  receipt varchar(255),
  staff_id integer,
  created_at timestamptz not null default now(),
  foreign key (supplier_id) references public.suppliers(id) on delete set null,
  foreign key (vehicle_id) references public.vehicles(id) on delete set null,
  foreign key (staff_id) references public.users(id) on delete set null
);
create index idx_expenses_date on public.expenses(expense_date);
create index idx_expenses_cat on public.expenses(category);

-- ---------- Support / notifications / audit / settings ----------
create table public.support_tickets (
  id integer generated always as identity primary key,
  client_id integer not null,
  subject varchar(160) not null,
  message text not null,
  status text not null default 'open' check (status in ('open','in_progress','resolved','closed')),
  staff_reply text,
  replied_by integer,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  foreign key (client_id) references public.clients(id) on delete cascade,
  foreign key (replied_by) references public.users(id) on delete set null
);
create trigger trg_support_tickets_updated_at before update on public.support_tickets
  for each row execute function public.update_updated_at_column();

create table public.notifications (
  id bigint generated always as identity primary key,
  user_id integer,
  client_id integer,
  type varchar(60) not null,
  title varchar(190) not null,
  body text,
  link varchar(255),
  channel text not null default 'in_app' check (channel in ('in_app','email','sms','whatsapp')),
  status text not null default 'unread' check (status in ('unread','read','sent','failed')),
  created_at timestamptz not null default now(),
  foreign key (user_id) references public.users(id) on delete cascade,
  foreign key (client_id) references public.clients(id) on delete cascade
);
create index idx_notif_user on public.notifications(user_id, status);

create table public.audit_logs (
  id bigint generated always as identity primary key,
  user_id integer,
  action varchar(80) not null,
  module varchar(60) not null,
  record_type varchar(60),
  record_id bigint,
  old_value text,
  new_value text,
  ip varchar(45),
  created_at timestamptz not null default now(),
  foreign key (user_id) references public.users(id) on delete set null
);
create index idx_audit_module on public.audit_logs(module, created_at);
create index idx_audit_user on public.audit_logs(user_id);

create table public.settings (
  key varchar(80) primary key,
  value text
);

-- ---------- Fiscalisation (ZIMRA FDMS) ----------
create table public.fdms_devices (
  id integer generated always as identity primary key,
  client_id integer,
  label varchar(120),
  device_id integer not null,
  serial_number varchar(60) not null,
  model_name varchar(60) not null default 'Server',
  model_version varchar(30) not null default 'v1',
  environment text not null default 'test' check (environment in ('test','production')),
  certificate_pem text,
  private_key_pem text,
  qr_url varchar(255),
  day_state text,
  pending_receipt text,
  last_receipt_global_no integer not null default 0,
  status varchar(20) not null default 'pending',
  registered_at timestamptz,
  created_at timestamptz not null default now(),
  foreign key (client_id) references public.clients(id) on delete set null,
  unique (device_id, environment)
);

create table public.fdms_receipts (
  id integer generated always as identity primary key,
  fdms_device_id integer,
  payment_id integer,
  booking_id integer,
  invoice_no varchar(60),
  receipt_global_no integer,
  receipt_counter integer,
  server_receipt_id bigint,
  receipt_json text,
  qr_data varchar(255),
  status varchar(20) not null default 'pending',
  error text,
  created_at timestamptz not null default now(),
  foreign key (fdms_device_id) references public.fdms_devices(id) on delete set null,
  foreign key (payment_id) references public.payments(id) on delete set null,
  foreign key (booking_id) references public.bookings(id) on delete set null
);
create index idx_fdms_receipts_status on public.fdms_receipts(status);

-- ---------- Marketing ----------
create table public.hero_slides (
  id integer generated always as identity primary key,
  image varchar(255) not null,
  title varchar(120) not null,
  subtitle varchar(255),
  description text,
  cta1_label varchar(60),
  cta1_url varchar(255),
  cta2_label varchar(60),
  cta2_url varchar(255),
  overlay smallint not null default 70 check (overlay between 0 and 100),
  sort_order integer not null default 0,
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);
create index idx_hero_active on public.hero_slides(is_active, sort_order);

-- ---------- Row Level Security & Policies ----------
-- Enable RLS on every table.
alter table public.roles enable row level security;
alter table public.permissions enable row level security;
alter table public.role_permissions enable row level security;
alter table public.users enable row level security;
alter table public.user_permissions enable row level security;
alter table public.login_attempts enable row level security;
alter table public.password_resets enable row level security;
alter table public.clients enable row level security;
alter table public.client_documents enable row level security;
alter table public.vehicles enable row level security;
alter table public.vehicle_photos enable row level security;
alter table public.vehicle_documents enable row level security;
alter table public.vehicle_devices enable row level security;
alter table public.suppliers enable row level security;
alter table public.bookings enable row level security;
alter table public.booking_charges enable row level security;
alter table public.booking_status_history enable row level security;
alter table public.booking_extensions enable row level security;
alter table public.payments enable row level security;
alter table public.deposits enable row level security;
alter table public.deposit_transactions enable row level security;
alter table public.wallets enable row level security;
alter table public.wallet_transactions enable row level security;
alter table public.contract_templates enable row level security;
alter table public.contracts enable row level security;
alter table public.signatures enable row level security;
alter table public.checklist_items enable row level security;
alter table public.checklists enable row level security;
alter table public.checklist_results enable row level security;
alter table public.maintenance enable row level security;
alter table public.damages enable row level security;
alter table public.expenses enable row level security;
alter table public.support_tickets enable row level security;
alter table public.notifications enable row level security;
alter table public.audit_logs enable row level security;
alter table public.settings enable row level security;
alter table public.fdms_devices enable row level security;
alter table public.fdms_receipts enable row level security;
alter table public.hero_slides enable row level security;

-- service_role gets full access to every table.
create policy "service_role_full_access" on public.roles for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.permissions for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.role_permissions for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.users for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.user_permissions for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.login_attempts for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.password_resets for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.clients for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.client_documents for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.vehicles for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.vehicle_photos for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.vehicle_documents for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.vehicle_devices for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.suppliers for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.bookings for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.booking_charges for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.booking_status_history for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.booking_extensions for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.payments for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.deposits for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.deposit_transactions for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.wallets for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.wallet_transactions for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.contract_templates for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.contracts for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.signatures for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.checklist_items for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.checklists for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.checklist_results for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.maintenance for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.damages for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.expenses for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.support_tickets for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.notifications for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.audit_logs for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.settings for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.fdms_devices for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.fdms_receipts for all to service_role using (true) with check (true);
create policy "service_role_full_access" on public.hero_slides for all to service_role using (true) with check (true);

-- Anonymous/public read access for public-facing pages.
create policy "public_read_public_vehicles" on public.vehicles
  for select to anon, authenticated
  using (is_public = true and status not in ('maintenance','unavailable'));

create policy "public_read_public_vehicle_photos" on public.vehicle_photos
  for select to anon, authenticated
  using (is_public = true and exists (
    select 1 from public.vehicles v where v.id = vehicle_photos.vehicle_id
      and v.is_public = true and v.status not in ('maintenance','unavailable')
  ));

create policy "public_read_settings" on public.settings
  for select to anon, authenticated using (true);

create policy "public_read_active_hero_slides" on public.hero_slides
  for select to anon, authenticated using (is_active = true);

grant usage on schema public to anon, authenticated, service_role;
grant select, insert, update, delete on all tables in schema public to service_role;
grant execute on all functions in schema public to service_role;
alter default privileges in schema public grant select, insert, update, delete on tables to service_role;

-- ---------- Storage buckets & policies ----------
insert into storage.buckets (id, name, "public", avif_autodetection, file_size_limit, allowed_mime_types)
values
  ('vehicle-photos', 'vehicle-photos', true, false, 10485760, array['image/jpeg','image/png','image/webp']::text[]),
  ('kyc-documents', 'kyc-documents', false, false, 10485760, array['image/jpeg','image/png','image/webp','application/pdf']::text[])
on conflict (id) do update set
  "public" = excluded."public",
  file_size_limit = excluded.file_size_limit,
  allowed_mime_types = excluded.allowed_mime_types;

-- Storage: public vehicle photos read by anyone; full management by service_role.
create policy "public_vehicle_photos_read" on storage.objects
  for select to anon, authenticated
  using (bucket_id = 'vehicle-photos');

create policy "service_role_vehicle_photos_all" on storage.objects
  for all to service_role
  using (bucket_id = 'vehicle-photos')
  with check (bucket_id = 'vehicle-photos');

-- Storage: private KYC documents; no anonymous access; service_role can manage.
-- Authenticated ownership checks would need app-level wiring to auth.uid() via
-- metadata; for now service_role (server-side) handles all uploads/reads.
create policy "service_role_kyc_all" on storage.objects
  for all to service_role
  using (bucket_id = 'kyc-documents')
  with check (bucket_id = 'kyc-documents');
