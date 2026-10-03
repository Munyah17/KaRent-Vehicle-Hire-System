'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useState } from 'react';
import { logoutAction } from '@/app/admin/actions';

export default function AdminNav({ user }: { user: { name: string; role: string } }) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  const links = [
    { href: '/admin/dashboard', label: 'Dashboard' },
    { href: '/admin/bookings', label: 'Bookings' },
    { href: '/admin/vehicles', label: 'Vehicles' },
    { href: '/admin/clients', label: 'Clients' },
    { href: '/admin/payments', label: 'Payments' },
    { href: '/admin/staff', label: 'Staff' },
  ];

  const active = (href: string) => pathname === href || pathname.startsWith(`${href}/`);

  async function handleLogout() {
    await logoutAction();
    window.location.href = '/admin/login';
  }

  return (
    <>
      <button
        onClick={() => setOpen(!open)}
        className="fixed left-4 top-4 z-50 flex h-10 w-10 items-center justify-center rounded-md bg-white shadow lg:hidden"
        aria-label="Toggle menu"
      >
        <svg className="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d={open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'} />
        </svg>
      </button>
      <aside
        className={`fixed inset-y-0 left-0 z-40 w-64 transform border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0 ${open ? 'translate-x-0' : '-translate-x-full'}`}
      >
        <div className="flex h-16 items-center border-b border-slate-200 px-6">
          <span className="text-xl font-bold text-blue-700">KaRent</span>
        </div>
        <nav className="p-4 space-y-1">
          {links.map((l) => (
            <Link
              key={l.href}
              href={l.href}
              onClick={() => setOpen(false)}
              className={`block rounded-md px-3 py-2 text-sm font-medium ${active(l.href) ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100'}`}
            >
              {l.label}
            </Link>
          ))}
        </nav>
        <div className="absolute bottom-0 w-full border-t border-slate-200 p-4">
          <div className="mb-2 text-sm font-medium text-slate-800">{user.name}</div>
          <div className="mb-3 text-xs text-slate-500 uppercase tracking-wide">{user.role}</div>
          <button
            onClick={handleLogout}
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Sign out
          </button>
        </div>
      </aside>
      <div className="fixed top-0 right-0 left-0 z-30 flex h-16 items-center justify-end border-b border-slate-200 bg-white px-6 lg:left-64">
        <span className="hidden text-sm text-slate-600 sm:inline">{user.name} &middot; {user.role}</span>
      </div>
      {open && <div className="fixed inset-0 z-30 bg-black/20 lg:hidden" onClick={() => setOpen(false)} />}
    </>
  );
}
