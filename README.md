# Vehicle Hire Management System

A complete vehicle-rental management system for a hire company — public website,
client portal and staff back office sharing one MySQL database and one set of
business rules. Built in plain PHP 8 + MySQL/MariaDB with a Tailwind CSS UI.
Deploys to ordinary **cPanel shared hosting** — no Node server, Docker or
framework tooling required at runtime.

## Areas

| Area | URL | Who |
|------|-----|-----|
| Public website | `/index.php`, `/vehicles.php`, `/vehicle.php`, `/about.php`, `/contact.php` | Visitors |
| Client portal | `/client/` | Registered clients |
| Back office | `/admin/` | Staff & Super Admin |

## Requirements

- PHP **8.1+** (8.3 recommended) with extensions: `pdo_mysql`, `curl`, `openssl`,
  `mbstring`, `fileinfo`, `gd` (demo images only)
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite`/`.htaccess` (cPanel default) — or any web server that
  maps `.htaccess` equivalents
- For asset rebuilding only (build-time): Node 18+

## Directory layout

```
app/            PHP application code (bootstrap, services, shared views)
config/         config.sample.php → copy to config.php per environment
database/       schema.sql + seed.sql
public/         DOCUMENT ROOT — everything publicly reachable lives here
  admin/        staff back-office pages
  client/       client portal pages
  api/          JSON endpoints (availability check)
  paynow/       Paynow result/return URLs (+ dev simulator)
  uploads/      PUBLIC uploads (vehicle photos) — no PHP execution (.htaccess)
  assets/       compiled CSS, vendored Lucide icons, images
storage/        PRIVATE data — contracts, KYC docs, logs (served via PHP only)
src/css/        Tailwind source (rebuild → public/assets/css/app.css)
tools/          dev helpers (demo-image generator)
```

**Important:** the web root must be `public/`. `app/`, `config/`, `storage/` and
`database/` must not be directly web-accessible. `.htaccess` files are included
in each non-public directory as a safety net.

## Installation — cPanel

### 1. Files & document root — pick ONE layout

**Option A — dedicated app directory (recommended)**

1. Upload the whole project to `~/vehicle-hire/` (outside `public_html`).
2. cPanel → **Domains** → your domain → **Document Root** =
   `/home/USERNAME/vehicle-hire/public`.

   `app/`, `config/`, `storage/`, `database/` then live outside the web root —
   the cleanest and safest layout.

**Option B — inside public_html (when you can't change the docroot)**

Upload `app/`, `config/`, `database/`, `storage/` **and** the *contents* of
`public/` directly into `public_html/`:

```
public_html/
  app/  config/  database/  storage/        ← deny-listed by .htaccess
  admin/  client/  api/  paynow/            ← from public/
  uploads/  assets/                         ← from public/
  index.php  login.php  vehicles.php …      ← from public/
```

All paths resolve correctly because everything stays in one directory. The
`.htaccess` files in `app/`, `config/`, `storage/` and `database/` block web
access — **verify** this by visiting `/config/config.php` (must be 403, not
the file contents) and `/app/bootstrap.php` (403) in a browser before going
live. If your host ignores `.htaccess` (Nginx-only setups), use Option A or
ask the host to block those directories.

### 2. Database

1. cPanel → **MySQL Databases**: create a database + user, add the user to the
   database with ALL PRIVILEGES.
2. phpMyAdmin → the new DB → Import `database/schema.sql`, then
   `database/seed.sql`.

### 3. PHP version & extensions

cPanel → **Select PHP Version** / **MultiPHP Manager**: PHP 8.1+ and enable
`pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `openssl` (usually all on).

### 4. Configure

Copy `config/config.sample.php` → `config/config.php` and set:

- `db` host/user/pass/name (use the `cpaneluser_*` names created above)
- `app_url` to `https://your-domain.com`
- `env` → `'production'` and `demo_mode` → `false`
- `paynow` integration ID + key

### 5. Writable directories

`storage/` (contracts, KYC docs, logs) and `public/uploads/` (vehicle photos)
must be writable — `755` usually suffices with suPHP/LiteSpeed, `775`
otherwise. Do **not** use `777`.

### 6. First login & hardening

1. Open `https://your-domain.com` → sign in as `admin@demo.test / Admin@123`.
2. Change every demo password, delete or disable demo accounts, set
   `demo_mode => false` and `env => 'production'`.
3. Add real staff under **Staff & Permissions**.

You do **not** need to upload: `node_modules/`, `.dev/`, `tools/`, `src/`,
`package*.json`, `tailwind.config.js` — the compiled CSS at
`public/assets/css/app.css` is already built.

## Configuration (`config/config.php`)

```php
'db' => ['dsn' => 'mysql:host=localhost;dbname=DB;charset=utf8mb4', 'user' => '…', 'pass' => '…'],
'app' => ['app_url' => 'https://your-domain.com', 'demo_mode' => false],
'paynow' => ['integration_id' => '####', 'integration_key' => '…', 'test_mode' => false],
```

### Paynow (Zimbabwe)

The gateway is driven by the official **[paynow/Paynow-PHP-SDK](https://github.com/paynow/Paynow-PHP-SDK)**
(`paynow/php-sdk` on Packagist), vendored at `app/Vendor/paynow/` — no
composer needed on the server. *(Note: `pay-now/paynow-php-sdk` is a
different, unrelated Polish gateway — do not use it.)*

1. Register a merchant account at [paynow.co.zw](https://www.paynow.co.zw).
2. Under your integration, set:
   - **Result URL**: `https://your-domain.com/paynow/result.php`
     (the SDK verifies the callback signature before payments are updated)
   - **Return URL**: `https://your-domain.com/paynow/return.php`
3. Copy the Integration ID + Integration Key into `config/config.php`.
   The key lives only in `config.php` — never in the database or public files.
4. When no credentials are set and `test_mode` is on, a **dev simulator**
   (`paynow/simulate.php`) exercises the full payment lifecycle locally.
   It disables itself automatically once credentials are configured.

## Demo accounts (from `seed.sql`)

| Role | Email | Password |
|------|-------|----------|
| Super Admin | `admin@demo.test` | `Admin@123` |
| Staff | `staff@demo.test` | `Staff@123` |
| Client | `john@demo.test` | `Client@123` |
| Client (unverified KYC) | `sarah@demo.test` | `Client@123` |

Change these immediately on any non-local install.

## Business rules enforced server-side

- A vehicle cannot be double-booked: overlapping `confirmed`/`active`/`overdue`
  bookings are rejected; `maintenance`/`unavailable` vehicles cannot be booked.
- All prices are recalculated server-side — client-supplied amounts are ignored.
- Payments and deposits are tracked independently of booking status.
- Wallet balance is derived from an immutable, signed transaction ledger.
- Contract bodies are versioned — generated contracts keep the exact template
  text used at creation time.
- KYC documents live outside the web root in `storage/uploads/` and are served
  only through the authorised `admin/doc.php` viewer.
- Uploads are validated by extension + MIME type + size; `public/uploads/` has
  PHP execution disabled.
- CSRF tokens on every form; prepared statements throughout; full audit log;
  login-attempt lockout; role + permission based access on every admin page.

## Workflows

### Walk-in (staff)

Bookings → **+ New Walk-in Booking** → search client → pick existing or create
new (auto user + temp password + forced first-login change) → select vehicle
→ dates → record cash payment + deposit → confirm → collection checklist →
handover → active → return checklist → damages/charges → deposit settle →
complete.

### Online (client)

Register/login → browse → pick dates → book → pay via Paynow (or wallet /
pay on confirmation) → booking confirmed → contract generated → track status,
request extensions, view documents and ledgers in the client portal.

## Local development

```powershell
# Database: any local MySQL/MariaDB; import schema.sql + seed.sql
php -S localhost:8000 -t public        # serve
npm install; npm run build:css         # rebuild Tailwind
php tools/make_demo_images.php         # regenerate placeholder vehicle photos
```

## Backups

Back up: the database (mysqldump / cPanel Backup Wizard) plus `storage/` and
`public/uploads/` — they contain contracts and client documents.

## Updating

1. Back up files + database.
2. Upload changed files (never overwrite `config/config.php` or `storage/`).
3. Apply any new `database/*.sql` migrations via phpMyAdmin.
4. Rebuild CSS locally (`npm run build:css`) and upload `public/assets/css/app.css`.
