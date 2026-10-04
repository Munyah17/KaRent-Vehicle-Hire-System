# KaRent — Next.js Application

KaRent is a vehicle hire management system built with Next.js 15, React 19, TypeScript, Tailwind CSS, and Supabase.

This repository contains the JavaScript/Vercel application. The legacy PHP application is maintained separately.

## Requirements

- Node.js 20+
- Docker Desktop
- Supabase CLI

## Environment

Create `.env.local` with:

```env
NEXT_PUBLIC_SUPABASE_URL=http://127.0.0.1:54321
SUPABASE_SERVICE_ROLE_KEY=
SESSION_SECRET=
```

Run `supabase status` after starting the local stack to obtain the local service-role key. Use a long random value for `SESSION_SECRET`. Never commit `.env.local`.

## Local development

```powershell
supabase start
supabase db reset
npm install
npm run dev
```

Open `http://localhost:3000`. Supabase Studio runs at `http://127.0.0.1:54323`.

## Build

```powershell
npm run build
npm start
```

## Deployment

Configure `NEXT_PUBLIC_SUPABASE_URL`, `SUPABASE_SERVICE_ROLE_KEY`, and `SESSION_SECRET` in Vercel for Production and Preview before deploying.

```powershell
vercel --prod
```

Apply `supabase/migrations/` to the hosted Supabase project before deploying the application.
