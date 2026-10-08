-- ============================================================
-- KaRent — Supabase/PostgreSQL seed data
-- Converted from MySQL database/seed.sql
-- Demo passwords (hashes are portable bcrypt $2y$):
--   admin@demo.test / Admin@123
--   staff@demo.test / Staff@123
--   munyah@demo.test  / Client@123
-- Real-person super-admin record and credentials are omitted.
-- ============================================================

-- ---------- Roles & permissions ----------
insert into public.roles (id, name, code, label)
overriding system value
values
  (1, 'SUPER_ADMIN', 'SUPER_ADMIN', 'Super Admin'),
  (2, 'STAFF', 'STAFF', 'Admin / Staff'),
  (3, 'CLIENT', 'CLIENT', 'Client');

insert into public.permissions (id, code, label, module)
overriding system value
values
  (1,  'dashboard',   'Dashboard',          'dashboard'),
  (2,  'vehicles',    'Vehicles',           'fleet'),
  (3,  'clients',     'Clients',            'clients'),
  (4,  'kyc',         'KYC Review',         'clients'),
  (5,  'bookings',    'Bookings',           'operations'),
  (6,  'calendar',    'Calendar',           'operations'),
  (7,  'payments',    'Payments',           'finance'),
  (8,  'deposits',    'Deposits',           'finance'),
  (9,  'wallets',     'Wallets',            'finance'),
  (10, 'checklists',  'Checklists',         'operations'),
  (11, 'contracts',   'Contracts',          'documents'),
  (12, 'maintenance', 'Maintenance',        'fleet'),
  (13, 'expenses',    'Expenses',           'finance'),
  (14, 'reports',     'Reports',            'reports'),
  (15, 'settings',    'Settings',           'system'),
  (16, 'staff',       'Staff Management',   'system'),
  (17, 'audit',       'Audit Log',          'system');

-- STAFF default permissions (everything except staff management, settings, audit)
insert into public.role_permissions (role_id, permission_id)
select 2, id from public.permissions where id not in (15, 16, 17);

-- ---------- Users ----------
-- password = Admin@123
-- password = Staff@123
-- password = Client@123
insert into public.users (id, role_id, name, email, username, phone, password_hash, status)
overriding system value
values
  (1, 1, 'Super Admin', 'admin@demo.test', null, '+263 770 000 001',
   '$2y$10$zLhbMwGS.BgVy3X78A1oDeojDUF8bPCAv0DOpXCfFoMqYZJrt8Pz6', 'active'),
  (2, 2, 'Tariro Moyo', 'staff@demo.test', null, '+263 770 000 002',
   '$2y$10$AFoPCeMTuFI3mSu8zGpFpOvUZ2OR3BnmWfUgMyoJiPAUqy4mN8qOq', 'active'),
  (3, 3, 'Munyah Dube', 'munyah@demo.test', null, '+263 771 234 567',
   '$2y$10$D905zgOrMFX7psdDZlvjhuajZ/0XBKjv751q7UNwZwjFeeoeQYeUe', 'active'),
  (4, 3, 'Sarah Nkomo', 'sarah@demo.test', null, '+263 772 345 678',
   '$2y$10$D905zgOrMFX7psdDZlvjhuajZ/0XBKjv751q7UNwZwjFeeoeQYeUe', 'active'),
  (5, 3, 'Peter Chikore', 'peter@demo.test', null, '+263 773 456 789',
   '$2y$10$D905zgOrMFX7psdDZlvjhuajZ/0XBKjv751q7UNwZwjFeeoeQYeUe', 'active'),
  (6, 3, 'Walk-in Client', 'walkin@demo.test', null, '+263 774 567 890',
   '$2y$10$D905zgOrMFX7psdDZlvjhuajZ/0XBKjv751q7UNwZwjFeeoeQYeUe', 'active');

-- ---------- Clients ----------
insert into public.clients (id, user_id, client_no, full_name, dob, phone, email, address,
                            national_id, licence_no, licence_expiry, kyc_status, source)
overriding system value
values
  (1, 3, 'CL-1001', 'Munyah Dube', '1985-04-12', '+263 771 234 567', 'munyah@demo.test',
   '12 Borrowdale Rd, Harare', '63-1234567-A-42', 'D458712', '2028-06-30', 'verified', 'online'),
  (2, 4, 'CL-1002', 'Sarah Nkomo', '1990-11-03', '+263 772 345 678', 'sarah@demo.test',
   '45 Hillside, Bulawayo', '08-7654321-B-10', 'D882110', '2027-01-15', 'verified', 'online'),
  (3, 5, 'CL-1003', 'Peter Chikore', '1978-07-22', '+263 773 456 789', 'peter@demo.test',
   '7 Msasa Grove, Mutare', '75-5551234-C-88', 'D129900', '2025-12-01', 'under_review', 'online'),
  (4, 6, 'CL-1004', 'Walk-in Client', '1995-02-14', '+263 774 567 890', 'walkin@demo.test',
   '3 CBD Avenue, Harare', '63-9998877-D-21', 'D771234', '2029-03-20', 'pending', 'walk_in');

insert into public.wallets (id, client_id)
overriding system value
values (1, 1), (2, 2), (3, 3), (4, 4);

-- ---------- Vehicles ----------
insert into public.vehicles (id, reg_no, make, model, year, colour, transmission, fuel_type,
                              engine_capacity, seats, category, mileage, description, daily_rate,
                              weekly_rate, monthly_rate, deposit, status, is_public, is_featured)
overriding system value
values
  (1, 'ABC 1234', 'Toyota', 'Corolla', 2021, 'White', 'automatic', 'petrol', '1.8L', 5, 'sedan', 45200,
   'Reliable sedan, fuel efficient, ideal for city and highway driving.', 40, 240, 850, 200, 'on_hire', true, true),
  (2, 'ABD 5678', 'Honda', 'Fit', 2019, 'Silver', 'automatic', 'petrol', '1.3L', 5, 'budget', 62100,
   'Compact hatchback, easy to park, very economical.', 30, 170, 620, 150, 'available', true, false),
  (3, 'ABE 9012', 'Toyota', 'Hilux', 2022, 'Grey', 'manual', 'diesel', '2.4L', 5, 'truck', 38400,
   'Double cab pickup, built for tough terrain and heavy loads.', 85, 500, 1800, 350, 'available', true, true),
  (4, 'ABF 3456', 'Nissan', 'X-Trail', 2020, 'Black', 'automatic', 'petrol', '2.0L', 7, 'suv', 51300,
   'Spacious 7-seat SUV, comfortable for family trips.', 65, 390, 1400, 300, 'reserved', true, true),
  (5, 'ABG 7890', 'Ford', 'Ranger', 2021, 'Blue', 'manual', 'diesel', '2.2L', 5, 'truck', 47800,
   'Rugged double cab, great for off-road and work sites.', 80, 470, 1700, 350, 'maintenance', true, false),
  (6, 'ABH 2345', 'Mazda', 'CX-5', 2021, 'Red', 'automatic', 'petrol', '2.0L', 5, 'suv', 40200,
   'Stylish crossover with premium interior.', 60, 350, 1250, 250, 'available', true, false),
  (7, 'ABJ 6789', 'Toyota', 'Fortuner', 2023, 'Pearl White', 'automatic', 'diesel', '2.8L', 7, 'suv', 21500,
   'Premium 7-seater SUV, powerful and comfortable.', 95, 560, 2000, 400, 'available', true, true),
  (8, 'ABK 1122', 'Kia', 'Picanto', 2020, 'Yellow', 'automatic', 'petrol', '1.0L', 4, 'budget', 55900,
   'Small city car, cheapest option for short trips.', 25, 140, 500, 100, 'available', true, false);

insert into public.vehicle_photos (vehicle_id, file_path, angle, is_primary, is_public, sort_order)
values
  (1, 'vehicles/demo-1.jpg', 'front', true, true, 0),
  (1, 'vehicles/demo-1b.jpg', 'other', false, true, 1),
  (1, 'vehicles/demo-1c.jpg', 'interior_front', false, true, 2),
  (2, 'vehicles/demo-2.jpg', 'front', true, true, 0),
  (3, 'vehicles/demo-3.jpg', 'front', true, true, 0),
  (4, 'vehicles/demo-4.jpg', 'front', true, true, 0),
  (5, 'vehicles/demo-5.jpg', 'front', true, true, 0),
  (6, 'vehicles/demo-6.jpg', 'front', true, true, 0),
  (7, 'vehicles/demo-7.jpg', 'front', true, true, 0),
  (8, 'vehicles/demo-8.jpg', 'front', true, true, 0);

-- ---------- Bookings ----------
insert into public.bookings (id, ref, client_id, vehicle_id, pickup_at, return_at, status,
                              base_amount, additional_amount, discount, total, deposit_required,
                              source, created_by, created_at)
overriding system value
values
  (1, 'BK-10001', 1, 1, (now() - interval '4 days'), (now() + interval '3 days'), 'active',
   280.00, 0.00, 0.00, 280.00, 200.00, 'walk_in', 2, (now() - interval '5 days')),
  (2, 'BK-10002', 2, 4, (now() + interval '2 days'), (now() + interval '6 days'), 'confirmed',
   260.00, 0.00, 0.00, 260.00, 300.00, 'online', null, (now() - interval '3 days')),
  (3, 'BK-10003', 3, 6, (now() - interval '20 days'), (now() - interval '15 days'), 'completed',
   300.00, 25.00, 15.00, 310.00, 250.00, 'online', null, (now() - interval '22 days')),
  (4, 'BK-10004', 4, 8, (now() - interval '2 days'), (now() + interval '1 day'), 'active',
   75.00, 0.00, 0.00, 75.00, 100.00, 'walk_in', 2, (now() - interval '2 days')),
  (5, 'BK-10005', 2, 3, (now() + interval '10 days'), (now() + interval '17 days'), 'pending',
   595.00, 0.00, 0.00, 595.00, 350.00, 'online', null, (now() - interval '1 day')),
  (6, 'BK-10006', 1, 2, (now() - interval '40 days'), (now() - interval '37 days'), 'completed',
   90.00, 0.00, 0.00, 90.00, 150.00, 'online', null, (now() - interval '42 days'));

insert into public.booking_status_history (booking_id, status, note, changed_by, created_at)
values
  (1, 'pending', 'Walk-in booking created', 2, (now() - interval '5 days')),
  (1, 'confirmed', 'Confirmed by staff', 2, (now() - interval '5 days')),
  (1, 'active', 'Vehicle handed over', 2, (now() - interval '4 days')),
  (2, 'pending', 'Online booking request', null, (now() - interval '3 days')),
  (2, 'confirmed', 'Payment verified', 1, (now() - interval '3 days')),
  (3, 'pending', 'Online booking request', null, (now() - interval '22 days')),
  (3, 'confirmed', 'Confirmed', 1, (now() - interval '21 days')),
  (3, 'active', 'Vehicle handed over', 2, (now() - interval '20 days')),
  (3, 'completed', 'Vehicle returned, checklist done', 2, (now() - interval '15 days')),
  (4, 'pending', 'Walk-in booking created', 2, (now() - interval '2 days')),
  (4, 'confirmed', 'Confirmed by staff', 2, (now() - interval '2 days')),
  (4, 'active', 'Vehicle handed over', 2, (now() - interval '2 days')),
  (5, 'pending', 'Online booking request', null, (now() - interval '1 day')),
  (6, 'completed', 'Completed hire', null, (now() - interval '37 days'));

insert into public.booking_charges (booking_id, label, amount, created_by)
values (3, 'Extra driver fee', 25.00, 2);

-- ---------- Payments ----------
insert into public.payments (id, txn_id, booking_id, client_id, amount, method, reference,
                              purpose, status, paid_at, staff_id, created_at)
overriding system value
values
  (1, 'TXN-90001', 1, 1, 280.00, 'cash', 'RCP-001', 'rental', 'successful', (now() - interval '5 days'), 2, (now() - interval '5 days')),
  (2, 'TXN-90002', 1, 1, 200.00, 'cash', 'RCP-002', 'deposit', 'successful', (now() - interval '5 days'), 2, (now() - interval '5 days')),
  (3, 'TXN-90003', 2, 2, 260.00, 'paynow', 'PN-339911', 'rental', 'successful', (now() - interval '3 days'), null, (now() - interval '3 days')),
  (4, 'TXN-90004', 3, 3, 310.00, 'paynow', 'PN-118822', 'rental', 'successful', (now() - interval '21 days'), null, (now() - interval '21 days')),
  (5, 'TXN-90005', 3, 3, 250.00, 'card', 'CARD-9921', 'deposit', 'successful', (now() - interval '21 days'), null, (now() - interval '21 days')),
  (6, 'TXN-90006', 4, 4, 75.00, 'cash', 'RCP-003', 'rental', 'successful', (now() - interval '2 days'), 2, (now() - interval '2 days')),
  (7, 'TXN-90007', 4, 4, 100.00, 'cash', 'RCP-004', 'deposit', 'successful', (now() - interval '2 days'), 2, (now() - interval '2 days')),
  (8, 'TXN-90008', 6, 1, 90.00, 'paynow', 'PN-556677', 'rental', 'successful', (now() - interval '41 days'), null, (now() - interval '41 days')),
  (9, 'TXN-90009', 6, 1, 150.00, 'paynow', 'PN-556678', 'deposit', 'successful', (now() - interval '41 days'), null, (now() - interval '41 days')),
  (10, 'TXN-90010', null, 1, 50.00, 'paynow', 'PN-777001', 'topup', 'successful', (now() - interval '10 days'), null, (now() - interval '10 days'));

-- ---------- Deposits ----------
insert into public.deposits (id, booking_id, client_id, required_amount, received_amount,
                              deducted_amount, refunded_amount, status)
overriding system value
values
  (1, 1, 1, 200.00, 200.00, 0.00, 0.00, 'held'),
  (2, 2, 2, 300.00, 0.00, 0.00, 0.00, 'pending'),
  (3, 3, 3, 250.00, 250.00, 20.00, 230.00, 'released'),
  (4, 4, 4, 100.00, 100.00, 0.00, 0.00, 'held'),
  (5, 6, 1, 150.00, 150.00, 0.00, 150.00, 'released');

insert into public.deposit_transactions (deposit_id, type, amount, reason, payment_id, created_by, created_at)
values
  (1, 'received', 200.00, 'Deposit collected', 2, 2, (now() - interval '5 days')),
  (3, 'received', 250.00, 'Deposit collected', 5, null, (now() - interval '21 days')),
  (3, 'deduction', 20.00, 'Fuel top-up', null, 2, (now() - interval '15 days')),
  (3, 'refund', 230.00, 'Deposit refund', null, 2, (now() - interval '15 days')),
  (4, 'received', 100.00, 'Deposit collected', 7, 2, (now() - interval '2 days')),
  (5, 'received', 150.00, 'Deposit collected', 9, null, (now() - interval '41 days')),
  (5, 'refund', 150.00, 'Deposit refunded', null, 1, (now() - interval '37 days'));

-- ---------- Wallet ledger ----------
insert into public.wallet_transactions (wallet_id, ref, type, amount, description, payment_id, booking_id)
values
  (1, 'WT-1001', 'topup', 50.00, 'Wallet top-up via Paynow', 10, null),
  (1, 'WT-1002', 'booking_payment', -20.00, 'Part payment booking BK-10006', null, 6),
  (1, 'WT-1003', 'refund', 10.00, 'Goodwill refund', null, null),
  (2, 'WT-2001', 'topup', 100.00, 'Wallet top-up', null, null),
  (3, 'WT-3001', 'topup', 40.00, 'Wallet top-up', null, null);

-- ---------- Checklist items ----------
insert into public.checklist_items (id, category, label, sort_order)
overriding system value
values
  (1, 'EXTERIOR', 'Front bumper', 1), (2, 'EXTERIOR', 'Rear bumper', 2), (3, 'EXTERIOR', 'Bonnet', 3),
  (4, 'EXTERIOR', 'Roof', 4), (5, 'EXTERIOR', 'Doors', 5), (6, 'EXTERIOR', 'Mirrors', 6),
  (7, 'EXTERIOR', 'Windows', 7), (8, 'EXTERIOR', 'Lights', 8), (9, 'EXTERIOR', 'Wheels', 9),
  (10, 'EXTERIOR', 'Tyres', 10),
  (11, 'INTERIOR', 'Seats', 11), (12, 'INTERIOR', 'Dashboard', 12), (13, 'INTERIOR', 'Air conditioning', 13),
  (14, 'INTERIOR', 'Radio', 14), (15, 'INTERIOR', 'Seatbelts', 15), (16, 'INTERIOR', 'Floor mats', 16),
  (17, 'INTERIOR', 'Spare wheel', 17), (18, 'INTERIOR', 'Jack', 18), (19, 'INTERIOR', 'Warning triangle', 19),
  (20, 'INTERIOR', 'Fire extinguisher', 20);

-- ---------- Suppliers ----------
insert into public.suppliers (id, name, contact_person, phone, email, service_category)
overriding system value
values
  (1, 'AutoFix Garage', 'Mike Tembo', '+263 712 000 111', 'mike@autofix.demo', 'Maintenance & Repairs'),
  (2, 'CleanRide Detailing', 'Anna Zhou', '+263 712 000 222', 'anna@cleanride.demo', 'Cleaning'),
  (3, 'FuelStop', 'Front Desk', '+263 712 000 333', 'sales@fuelstop.demo', 'Fuel'),
  (4, 'SecureInsure Brokers', 'T. Ngwenya', '+263 712 000 444', 'info@secureinsure.demo', 'Insurance');

-- ---------- Maintenance ----------
insert into public.maintenance (vehicle_id, maint_type, maint_date, mileage, description, cost,
                                 supplier_id, next_service_date, next_service_mileage, created_by)
values
  (5, 'Engine Repair', (current_date - interval '1 day'), 47800, 'Gearbox inspection and oil leak repair.', 450.00, 1,
   (current_date + interval '30 day'), 53000, 2),
  (1, 'Service', (current_date - interval '60 day'), 44000, 'Full service: oil, filters, brakes check.', 180.00, 1,
   (current_date + interval '120 day'), 50000, 2),
  (3, 'Tyres', (current_date - interval '30 day'), 37500, 'Replaced two rear tyres.', 220.00, 1,
   null, null, 2),
  (6, 'Service', (current_date - interval '15 day'), 39500, 'Scheduled service.', 160.00, 1,
   (current_date + interval '150 day'), 45000, 2);

-- ---------- Damages ----------
insert into public.damages (vehicle_id, client_id, booking_id, incident_date, description,
                             estimated_cost, actual_cost, client_charge, status, created_by)
values
  (6, 3, 3, (current_date - interval '16 day'), 'Small scratch on rear left door panel.', 80.00, 65.00, 20.00, 'resolved', 2);

-- ---------- Expenses ----------
insert into public.expenses (expense_date, category, amount, description, supplier_id, vehicle_id, staff_id)
values
  ((current_date - interval '1 day'), 'maintenance', 450.00, 'Gearbox repair — Ford Ranger', 1, 5, 2),
  ((current_date - interval '3 day'), 'fuel', 120.00, 'Fleet fuel top-up', 3, null, 2),
  ((current_date - interval '6 day'), 'cleaning', 35.00, 'Interior valet — Mazda CX-5', 2, 6, 2),
  ((current_date - interval '9 day'), 'insurance', 210.00, 'Quarterly fleet insurance instalment', 4, null, 1),
  ((current_date - interval '12 day'), 'repairs', 90.00, 'Brake pads — Toyota Corolla', 1, 1, 2),
  ((current_date - interval '15 day'), 'office', 48.00, 'Printer ink & stationery', null, null, 1);

-- ---------- Contract templates ----------
insert into public.contract_templates (id, code, name, body, version)
overriding system value
values
  (1, 'hire_agreement', 'Vehicle Hire Agreement',
   '<h2>VEHICLE HIRE AGREEMENT</h2>
<p>This agreement is made between <strong>{{company_name}}</strong> ("the Company") and
<strong>{{client_name}}</strong> (Client No: {{client_no}}, ID: {{client_id}}) ("the Client").</p>
<h3>Vehicle</h3>
<p>Make/Model: {{vehicle_make}} {{vehicle_model}} &nbsp;|&nbsp; Registration: {{registration}} &nbsp;|&nbsp; Year: {{vehicle_year}}</p>
<h3>Hire Period</h3>
<p>Pickup: {{pickup_date}} &nbsp;|&nbsp; Return: {{return_date}}</p>
<h3>Charges</h3>
<p>Rental amount: {{rental_amount}} &nbsp;|&nbsp; Security deposit: {{deposit_amount}}</p>
<h3>Terms</h3>
<ol>
<li>The Client shall use the vehicle responsibly and in accordance with the law.</li>
<li>The vehicle shall be returned in the same condition as collected, fair wear and tear excepted.</li>
<li>The Client is liable for damage, fines and losses during the hire period.</li>
<li>The security deposit is refundable subject to inspection on return.</li>
<li>Late returns attract additional charges at the prevailing daily rate.</li>
</ol>
<p>Client signature: ____________________ &nbsp; Date: {{generated_date}}</p>', 1),
  (2, 'terms', 'Terms & Conditions',
   '<h2>TERMS & CONDITIONS</h2>
<p>These terms govern vehicle hire from {{company_name}}.</p>
<ol>
<li>Drivers must hold a valid licence and meet minimum age requirements.</li>
<li>Bookings are confirmed only after payment verification.</li>
<li>Cancellations may attract a fee per the cancellation policy.</li>
<li>Vehicles may not be taken across borders without written consent.</li>
<li>The Company may refuse service where fraud or abuse is suspected.</li>
</ol>', 1),
  (3, 'privacy', 'Privacy Policy',
   '<h2>PRIVACY POLICY</h2>
<p>{{company_name}} collects personal information (name, contact details, identity
documents) solely for providing vehicle hire services, legal compliance and account
management. We do not sell personal data. Clients may request access or correction
of their records by contacting us.</p>', 1),
  (4, 'deposit_ack', 'Deposit Acknowledgement',
   '<h2>SECURITY DEPOSIT ACKNOWLEDGEMENT</h2>
<p>Received from {{client_name}} (Client No: {{client_no}}) a security deposit of
{{deposit_amount}} for the hire of {{vehicle_make}} {{vehicle_model}} ({{registration}}),
booking {{booking_ref}}.</p>
<p>The deposit is refundable on satisfactory return of the vehicle, less any
deductions for damage, fuel, late return, excess mileage or missing equipment.</p>
<p>Date: {{generated_date}} &nbsp; Received by: ____________________</p>', 1),
  (5, 'handover', 'Vehicle Handover',
   '<h2>VEHICLE HANDOVER RECORD</h2>
<p>Client: {{client_name}} &nbsp;|&nbsp; Booking: {{booking_ref}}</p>
<p>Vehicle: {{vehicle_make}} {{vehicle_model}} ({{registration}})</p>
<p>Handover date: {{pickup_date}} &nbsp;|&nbsp; Mileage: {{mileage}} &nbsp;|&nbsp; Fuel: {{fuel_level}}</p>
<p>The undersigned acknowledge the vehicle condition as recorded in the attached checklist.</p>
<p>Staff: ____________________ &nbsp; Client: ____________________</p>', 1),
  (6, 'return', 'Vehicle Return',
   '<h2>VEHICLE RETURN RECORD</h2>
<p>Client: {{client_name}} &nbsp;|&nbsp; Booking: {{booking_ref}}</p>
<p>Vehicle: {{vehicle_make}} {{vehicle_model}} ({{registration}})</p>
<p>Return date: {{return_date}} &nbsp;|&nbsp; Mileage: {{mileage}} &nbsp;|&nbsp; Fuel: {{fuel_level}}</p>
<p>New damage / charges (if any): {{extra_notes}}</p>
<p>Staff: ____________________ &nbsp; Client: ____________________</p>', 1),
  (7, 'checklist_doc', 'Vehicle Checklist',
   '<h2>VEHICLE INSPECTION CHECKLIST</h2>
<p>Vehicle: {{vehicle_make}} {{vehicle_model}} ({{registration}}) &nbsp;|&nbsp; Booking: {{booking_ref}}</p>
<p>{{checklist_table}}</p>
<p>Mileage: {{mileage}} &nbsp;|&nbsp; Fuel: {{fuel_level}}</p>
<p>Staff: ____________________ &nbsp; Client: ____________________</p>', 1),
  (8, 'mou', 'Memorandum of Understanding',
   '<h2>MEMORANDUM OF UNDERSTANDING</h2>
<p>Between {{company_name}} and {{client_name}} regarding the hire of
{{vehicle_make}} {{vehicle_model}} ({{registration}}) for the period
{{pickup_date}} to {{return_date}}.</p>
<p>Both parties agree to act in good faith and resolve disputes amicably before
pursuing formal remedies.</p>', 1);

-- ---------- Settings ----------
insert into public.settings ("key", "value") values
  ('company_name','Vehicle Hire (Pvt) Ltd'),
  ('company_email','admin@globalsaceweb.co.zw'),
  ('company_phone','+263 242 700 000'),
  ('company_address','24 Midlothian Avenue, Harare, Zimbabwe'),
  ('currency','USD'),
  ('currency_symbol','$'),
  ('booking_min_hours','24'),
  ('booking_cancellation_fee','15.00'),
  ('tax_rate','0'),
  ('default_deposit','200.00'),
  ('terms_page','Standard terms apply. Edit via Contracts > Templates.'),
  ('privacy_page','We respect your privacy. Edit via Contracts > Templates.'),
  -- Fiscalisation (ZIMRA FDMS) — off by default; configure at Admin > Fiscalisation
  ('fdms_enabled','0'),
  ('fdms_scope','optin'),
  ('fdms_device_id','0'),
  ('fdms_tax_id','0'),
  ('fdms_currency','USD')
on conflict ("key") do update set "value" = excluded."value";

-- ---------- Notifications sample ----------
insert into public.notifications (user_id, type, title, body, link)
values
  (1, 'booking', 'New booking request', 'Sarah Nkomo requested Nissan X-Trail for 6 days.', '/admin/bookings'),
  (1, 'kyc', 'KYC pending review', 'Peter Chikore submitted documents for review.', '/admin/clients'),
  (2, 'maintenance', 'Service due', 'Toyota Corolla due service at 50,000 km.', '/admin/vehicles'),
  (2, 'booking', 'Vehicle due for return', 'Toyota Corolla (BK-10001) due in 3 days.', '/admin/bookings');

-- ---------- Support ticket sample ----------
insert into public.support_tickets (client_id, subject, message, status)
values (3, 'Extension question', 'Can I extend my hire period online?', 'resolved');

-- ---------- Hero slides (homepage slider — max 20) ----------
insert into public.hero_slides (image, title, subtitle, description, cta1_label, cta1_url, cta2_label, cta2_url, overlay, sort_order)
values
  ('uploads/vehicles/demo-1.jpg', 'Toyota Corolla', 'Reliable Sedan', '1.8L petrol · Automatic · 5 seats · From $40/day — fuel-efficient comfort for city and highway driving.', 'Book Now', '/vehicles/1', 'Our Fleet', '/vehicles', 70, 10),
  ('uploads/vehicles/demo-2.jpg', 'Honda Fit', 'Compact City Car', '1.3L petrol · Automatic · 5 seats · From $30/day — nimble, economical and easy to park.', 'Book Now', '/vehicles/2', 'Our Fleet', '/vehicles', 70, 20),
  ('uploads/vehicles/demo-3.jpg', 'Toyota Hilux', 'Workhorse Double Cab', '2.4L diesel · Manual · 4x4 · From $85/day — built for load and rough terrain.', 'Book Now', '/vehicles/3', 'Our Fleet', '/vehicles', 70, 30),
  ('uploads/vehicles/demo-4.jpg', 'Nissan X-Trail', 'Family SUV', '2.5L petrol · Automatic · AWD · 7 seats · From $65/day — space and safety for the whole family.', 'Book Now', '/vehicles/4', 'Our Fleet', '/vehicles', 70, 40),
  ('uploads/vehicles/demo-5.jpg', 'Ford Ranger', 'Premium Bakkie', '3.0L diesel · Automatic · 4x4 · From $80/day — power and presence on any road.', 'Book Now', '/vehicles/5', 'Our Fleet', '/vehicles', 70, 50),
  ('uploads/vehicles/demo-6.jpg', 'Mazda CX-5', 'Executive Crossover', '2.5L petrol · Automatic · 5 seats · From $60/day — premium cabin, smooth ride.', 'Book Now', '/vehicles/6', 'Our Fleet', '/vehicles', 70, 60),
  ('uploads/vehicles/demo-7.jpg', 'Toyota Fortuner', 'Luxury 7-Seater SUV', '2.8L diesel · Automatic · 4x4 · From $95/day — flagship comfort for long journeys.', 'Book Now', '/vehicles/7', 'Our Fleet', '/vehicles', 70, 70),
  ('uploads/vehicles/demo-8.jpg', 'Kia Picanto', 'Budget Friendly', '1.0L petrol · Manual · 4 seats · From $25/day — the cheapest way to get moving.', 'Book Now', '/vehicles/8', 'Our Fleet', '/vehicles', 70, 80);

-- ---------- Reset identity sequences after explicit ID inserts ----------
do $$
declare
  r record;
begin
  for r in
    select table_schema as schema_name, table_name, column_name
    from information_schema.columns
    where table_schema = 'public'
      and is_identity = 'YES'
  loop
    execute format(
      'select setval(pg_get_serial_sequence(%L, %L), coalesce((select max(%I) from %I.%I), 1))',
      r.schema_name || '.' || r.table_name, r.column_name,
      r.column_name, r.schema_name, r.table_name
    );
  end loop;
end $$;
