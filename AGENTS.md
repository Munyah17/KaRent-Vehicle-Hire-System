# Project: KaRent — Vehicle Hire Management System

Next.js 15 (App Router) + React 19 + TypeScript + Tailwind CSS + Supabase
(Postgres). Deploys to Vercel; production domain https://rentacar.munya.co.zw.

The legacy PHP/MySQL application is maintained in a SEPARATE folder — this
workspace is JavaScript-only. Do not create .php files here.

## Git workflow — READ FIRST

- Remote: `origin` → https://github.com/Munyah17/KaRent-Vehicle-Hire-System.git
- **After every successful change, commit AND push to `origin main` —
  pushing triggers the production Vercel deploy automatically.**
- **Push-only, never pull.** Do not run `git pull`, `fetch`+merge, `rebase`,
  or checkout remote branches. Local is the authoritative copy.
- Do not amend/force-push existing history unless explicitly told.
- Secrets live in `.env.local` (local Supabase) and `.env.prod` (pulled
  production env). Both are gitignored — never commit them.

## Run locally

- `npm run dev` → http://localhost:6333
- Local Supabase must be running for data pages (URL in `.env.local`).
  If it's offline, build prerendering fails — use
  `node .dev/build-with-prod-env.js` to build against production env.

## Build & verify

- `npm run build` (runs `build:css` then `next build`) — must be green.
- `npx tsc --noEmit` for a quick type check.
- CSS: `npm run build:css` compiles `src/css/app.css` →
  `public/assets/css/app.css` via `tailwind.config.cjs`. This is loaded
  with a manual `<link>` — globals.css has no @tailwind directives. If you
  add new utility classes, the stylesheet must be rebuilt.
- Deploy: `git push` (auto) or `npx vercel deploy --prod` then
  `vercel alias set <deployment> rentacar.munya.co.zw`.
- Demo accounts (supabase/seed.sql): `admin@demo.test/Admin@123`,
  `staff@demo.test/Staff@123`, `munyah@demo.test/Client@123`.

## Conventions

- `app/` holds routes: `(public)` route group has site Header/Footer via
  its own layout; `admin/`, `client/`, `super-admin/` have none.
- Root `app/layout.tsx` is a slim shell (html/body + theme init). Do NOT
  add `headers()`/`cookies()`/`force-dynamic` there — public pages rely on
  ISR (`revalidate = 120` in `(public)/layout.tsx`).
- Session is read server-side via `getSession()` (JWT cookie) or
  client-side via `/api/me` — the public Header uses `/api/me` so public
  pages stay cacheable.
- All DB access goes through `src/lib/supabase.ts` (service-role,
  server-only, 15s fetch timeout). Never import it in client components.
- Icons: `lucide-react` components only. No `data-lucide` + UMD script on
  public pages (that combination caused a MutationObserver freeze).
- `public/uploads/` vehicle images are committed so Git-triggered
  deployments include them.
- `public/.htaccess` files are Apache-only and kept intentionally for a
  possible future cPanel deploy — they are inert on Vercel.
- `.dev/` holds JS helper scripts (seed/build utils) — dev only.
