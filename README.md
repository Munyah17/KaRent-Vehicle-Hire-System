# KaRent — Next.js Application

KaRent is a vehicle hire management system built with Next.js 15, React 19, TypeScript, Tailwind CSS, and MySQL.

This repository contains the JavaScript/Vercel application. The legacy PHP application is maintained separately.

## Requirements

- Node.js 20+
- MySQL or MariaDB reachable from the application runtime

## Environment

Create `.env.local` with:

```env
DB_HOST=
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASS=
SESSION_SECRET=
```

Use a long random value for `SESSION_SECRET`. Never commit `.env.local`.

## Local development

```powershell
npm install
npm run dev
```

Open `http://localhost:3000`.

## Build

```powershell
npm run build
npm start
```

## Deployment

The project is configured for Vercel. Add all environment variables to the Vercel project for Production, Preview, and Development before deploying.

```powershell
vercel --prod
```

The MySQL server must accept secure connections from Vercel's serverless runtime. A database bound to `127.0.0.1` on a developer machine is not reachable from Vercel.
