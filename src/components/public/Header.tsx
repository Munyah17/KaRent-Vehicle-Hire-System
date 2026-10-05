'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import type { SessionUser } from '@/lib/auth';
import { logoutAction } from './authActions';

export default function Header({ companyName, user }: { companyName: string; user: SessionUser | null }) {
  const pathname = usePathname() || '/';
  const [open, setOpen] = useState(false);

  const navItems = [
    { href: '/', label: 'Home', key: 'home' },
    { href: '/vehicles', label: 'Vehicles', key: 'vehicles' },
    { href: '/about', label: 'About', key: 'about' },
    { href: '/contact', label: 'Contact', key: 'contact' },
  ];

  const active = (key: string) => {
    if (key === 'home') return pathname === '/';
    return pathname.startsWith('/' + key);
  };

  useEffect(() => {
    if (open) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [open]);

  return (
    <>
      <header className="site-header bg-white border-b border-gray-200 text-slate-700 sticky top-0 z-40">
        <style>{`
          @media (min-width: 768px) { .nav-drawer-btn, #drawerBackdrop, #siteDrawer { display: none !important; } }
        `}</style>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <button
            id="drawerBtn"
            type="button"
            aria-label="Open menu"
            aria-controls="siteDrawer"
            onClick={() => setOpen(true)}
            className="nav-drawer-btn inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600 hover:bg-gray-50"
          >
            <i data-lucide="menu" className="w-5 h-5"></i>
          </button>
          <nav className="hidden md:flex items-center gap-8 text-sm" style={{ marginLeft: 'auto', marginRight: '1.75rem' }}>
            {navItems.map((item) => (
              <Link
                key={item.key}
                href={item.href}
                className={active(item.key) ? 'text-blue-600 font-medium' : 'text-slate-600 hover:text-slate-900'}
              >
                {item.label}
              </Link>
            ))}
          </nav>
          <div className="flex items-center gap-2 sm:gap-3 text-sm">
            <button className="theme-toggle" type="button" data-theme-toggle title="Toggle dark / light mode">
              <i data-lucide="sun-moon" className="w-4 h-4"></i>
            </button>
            {user ? (
              <>
                {user.role === 'CLIENT' ? (
                  <Link href="/client" className="hidden sm:inline text-slate-600 hover:text-slate-900">
                    My Account
                  </Link>
                ) : (
                  <Link href="/admin" className="hidden sm:inline text-slate-600 hover:text-slate-900">
                    Back Office
                  </Link>
                )}
                <form action={logoutAction} className="inline">
                  <button
                    type="submit"
                    className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium"
                  >
                    Sign out
                  </button>
                </form>
              </>
            ) : (
              <>
                <Link
                  href="/login"
                  className="border border-slate-300 text-slate-700 hover:bg-gray-50 hover:border-slate-400 px-4 py-1.5 rounded-md font-medium"
                >
                  Sign in
                </Link>
                <Link
                  href="/register"
                  className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium"
                >
                  Get Started
                </Link>
              </>
            )}
          </div>
        </div>
      </header>

      <div
        id="drawerBackdrop"
        onClick={() => setOpen(false)}
        style={{
          position: 'fixed',
          inset: 0,
          zIndex: 40,
          background: 'rgba(0,0,0,.5)',
          opacity: open ? 1 : 0,
          pointerEvents: open ? 'auto' : 'none',
          transition: 'opacity .3s',
        }}
      />
      <aside
        id="siteDrawer"
        aria-label="Site navigation"
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          bottom: 0,
          width: '16rem',
          zIndex: 50,
          background: '#fff',
          transform: open ? 'translateX(0)' : 'translateX(-100%)',
          transition: 'transform .3s',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <div className="flex items-center justify-between px-5 h-16 border-b border-gray-200">
          <span className="font-semibold text-slate-800 dark:text-slate-100">Menu</span>
          <button
            id="drawerClose"
            type="button"
            aria-label="Close menu"
            onClick={() => setOpen(false)}
            className="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600"
          >
            <i data-lucide="x" className="w-5 h-5"></i>
          </button>
        </div>
        <nav className="flex-1 px-3 py-4 space-y-1 text-sm">
          {navItems.map((item) => (
            <Link
              key={item.key}
              href={item.href}
              onClick={() => setOpen(false)}
              className={
                'drawer-link block px-3 py-2.5 rounded-md ' +
                (active(item.key)
                  ? 'text-blue-600 font-medium bg-blue-50'
                  : 'text-slate-600 hover:bg-gray-50')
              }
            >
              {item.label}
            </Link>
          ))}
        </nav>
        <div className="px-5 py-4 border-t border-gray-200 text-sm space-y-3">
          {user ? (
            <>
              <Link
                href={user.role === 'CLIENT' ? '/client' : '/admin'}
                className="block text-blue-600 font-medium"
              >
                {user.role === 'CLIENT' ? 'My Account' : 'Back Office'}
              </Link>
              <form action={logoutAction}>
                <button type="submit" className="block text-slate-600">
                  Sign out
                </button>
              </form>
            </>
          ) : (
            <>
              <Link
                href="/register"
                onClick={() => setOpen(false)}
                className="block bg-blue-600 text-white text-center rounded-md px-4 py-2 font-medium"
              >
                Get Started
              </Link>
              <Link
                href="/login"
                onClick={() => setOpen(false)}
                className="block text-center text-slate-600"
              >
                Sign in
              </Link>
            </>
          )}
        </div>
      </aside>
    </>
  );
}
