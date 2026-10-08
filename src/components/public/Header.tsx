'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Menu, SunMoon, X, RefreshCw } from 'lucide-react';
import type { SessionUser } from '@/lib/auth';
import { logoutAction } from './authActions';

export default function Header({ companyName }: { companyName: string }) {
  const pathname = usePathname() || '/';
  const [user, setUser] = useState<SessionUser | null>(null);

  // Checkbox-driven drawer â€” opens/closes with zero JS. On client-side
  // navigation we clear it so the drawer doesn't stay open on the new page.
  useEffect(() => {
    const cb = document.getElementById('navdrawer') as HTMLInputElement | null;
    if (cb?.checked) cb.checked = false;
  }, [pathname]);

  useEffect(() => {
    let live = true;
    fetch('/api/me')
      .then((r) => r.json())
      .then((d) => {
        if (live && d.user) setUser(d.user);
      })
      .catch(() => {});
    return () => {
      live = false;
    };
  }, []);

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

  return (
    <>
      <input type="checkbox" id="navdrawer" className="peer sr-only" aria-hidden="true" />
      <header className="site-header bg-white border-b border-gray-200 text-slate-700 sticky top-0 z-40">
        <style>{`
          @media (min-width: 768px) { .nav-drawer-btn, #drawerBackdrop, #siteDrawer, #navdrawer { display: none !important; } }
          #navdrawer:checked ~ #drawerBackdrop { opacity: 1 !important; pointer-events: auto !important; }
          #navdrawer:checked ~ #siteDrawer { transform: translateX(0) !important; }
          #navdrawer:checked ~ .site-header { position: fixed; left: 0; right: 0; }
        `}</style>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <label
            id="drawerBtn"
            htmlFor="navdrawer"
            aria-label="Open menu"
            aria-controls="siteDrawer"
            className="nav-drawer-btn inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600 hover:bg-gray-50 cursor-pointer"
          >
            <Menu className="w-5 h-5" />
          </label>
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
            <button
              type="button"
              title="Refresh — get the latest version"
              aria-label="Refresh page"
              onClick={() => {
                // Reload via a cache-busting URL so the browser cannot
                // serve a stale cached copy after a new deployment.
                const url = new URL(window.location.href);
                url.searchParams.set('_r', String(Date.now()));
                window.location.href = url.toString();
              }}
              className="inline-flex items-center justify-center w-9 h-9 rounded-full border border-gray-200 text-slate-600 hover:bg-gray-50"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
            <button className="theme-toggle" type="button" data-theme-toggle title="Toggle dark / light mode">
              <SunMoon className="w-4 h-4" />
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

      <label
        id="drawerBackdrop"
        htmlFor="navdrawer"
        aria-label="Close menu"
        style={{
          position: 'fixed',
          inset: 0,
          zIndex: 40,
          background: 'rgba(0,0,0,.5)',
          opacity: 0,
          pointerEvents: 'none',
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
          transform: 'translateX(-100%)',
          transition: 'transform .3s',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        <div className="flex items-center justify-between px-5 h-16 border-b border-gray-200">
          <span className="font-semibold text-slate-800 dark:text-slate-100">Menu</span>
          <label
            id="drawerClose"
            htmlFor="navdrawer"
            aria-label="Close menu"
            className="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600 cursor-pointer"
          >
            <X className="w-5 h-5" />
          </label>
        </div>
        <nav className="flex-1 px-3 py-4 space-y-1 text-sm">
          {navItems.map((item) => (
            <Link
              key={item.key}
              href={item.href}
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
                className="block bg-blue-600 text-white text-center rounded-md px-4 py-2 font-medium"
              >
                Get Started
              </Link>
              <Link
                href="/login"
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

