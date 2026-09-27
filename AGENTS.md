# Project: KaRent — Vehicle Hire Management System

Plain-PHP 8 + MySQL/MariaDB vehicle rental system. Public website, client
portal and staff back office on one database. Tailwind CSS UI (compiled,
no runtime Node). Deploys to ordinary cPanel shared hosting.

## Git workflow — READ FIRST

- Remote: `origin` → https://github.com/Munyah17/KaRent-Vehicle-Hire-System.git
- **After every successful build/change, commit AND push to `origin main` —
  do not wait to be asked.**
- **Push-only, never pull.** Do not run `git pull`, `fetch`+merge, `rebase`,
  or checkout remote branches. Local is the authoritative copy; the repo is
  open source for others but changes must never flow back into local files.
- Do not amend/force-push existing history unless explicitly told.
- `config/config.php` contains real credentials — it is gitignored and must
  never be committed.

## Run locally

- PHP built-in server: `php -S localhost:8000 -t public`
- Local DB: portable MariaDB lives in `.dev/mariadb-11.8.9-winx64/` (gitignored).
  Start: `& .dev\mariadb-11.8.9-winx64\bin\mysqld.exe --datadir=.dev\data --port=3306`
- Built-in server is single-threaded on Windows — never make server-to-server
  HTTP calls to itself (causes deadlock).

## Build & verify

- CSS: `npx tailwindcss -c tailwind.config.js -i src/css/app.css -o public/assets/css/app.css --minify` (or `npm run build:css`)
- Lint all PHP: `php -l` over every file (must be 0 failures before commit)
- Demo accounts (seed.sql): `admin@demo.test/Admin@123`,
  `staff@demo.test/Staff@123`, `john@demo.test/Client@123`
- Paynow: vendored SDK in `app/Vendor/paynow/` (paynow.co.zw — NOT the Polish
  pay-now package). Without credentials + `test_mode`, `public/paynow/simulate.php`
  drives the full payment lifecycle locally.

## Conventions

- All business logic in `app/Services/*.php` — pages must stay thin.
- Server-side truth only: prices, totals, availability are recalculated in
  services; never trust POSTed amounts.
- `storage/` holds private files (KYC, contracts, logs) — served only through
  `admin/doc.php`. `public/uploads/` is public but script-free (.htaccess).
- Every admin page calls `Auth::requirePermission(...)`; every form uses
  `Csrf::field()`.
- No placeholder buttons — every control must do real work.
