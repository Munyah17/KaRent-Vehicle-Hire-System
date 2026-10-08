'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Menu, SunMoon, X, RefreshCw, Bell, Settings, ShieldCheck, ChevronDown } from 'lucide-react';
import type { SessionUser } from '@/lib/auth';
import { logoutAction } from './authActions';

export default function Header({ companyName }: { companyName: string }) {
  const pathname = usePathname() || '/';
  const [user, setUser] = useState<SessionUser | null>(null);
  const [acctOpen, setAcctOpen] = useState(false);

  const forceRefresh = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('_r', String(Date.now()));
    window.location.href = url.toString();
  };

  // Role-aware account links — clients get their portal pages, staff go
  // to the back office.
  const acctLinks = user
    ? user.role === 'CLIENT'
      ? { profile: '/client/profile', notif: '/client/notifications', settings: '/client/settings', home: '/client' }
      : { profile: '/admin', notif: '/admin', settings: '/admin', home: '/admin' }
    : null;

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
            className="nav-drawer-btn inline-flex items-center justify-center w-9 h-9 -ml-2 text-slate-600 hover:text-slate-900 cursor-pointer"
          >
            <Menu className="w-6 h-6" />
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
            <button className="theme-toggle hidden md:inline-flex" type="button" data-theme-toggle title="Toggle dark / light mode">
              <SunMoon className="w-4 h-4" />
            </button>
            {user && acctLinks ? (
              <div className="relative">
                <button
                  type="button"
                  onClick={() => setAcctOpen((v) => !v)}
                  aria-label="Account menu"
                  aria-expanded={acctOpen}
                  className="flex items-center gap-1.5"
                >
                  <span className="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold">
                    {(user.name || 'U').charAt(0).toUpperCase()}
                  </span>
                  <ChevronDown className="w-4 h-4 text-slate-400 hidden sm:block" />
                </button>
                {acctOpen && (
                  <>
                    <button
                      type="button"
                      aria-label="Close account menu"
                      className="fixed inset-0 z-40 cursor-default"
                      onClick={() => setAcctOpen(false)}
                    />
                    <div className="absolute right-0 top-12 z-50 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-2 text-sm">
                      <div className="px-4 py-2 border-b border-gray-100">
                        <p className="font-semibold text-slate-800 truncate">{user.name}</p>
                        <p className="text-xs text-slate-400">
                          {user.role === 'CLIENT' ? 'Client account' : user.role.replace('_', ' ')}
                        </p>
                      </div>
                      <Link
                        href={acctLinks.profile}
                        onClick={() => setAcctOpen(false)}
                        className="flex items-center gap-2.5 px-4 py-2.5 text-slate-600 hover:bg-gray-50"
                      >
                        <ShieldCheck className="w-4 h-4" /> My Profile
                      </Link>
                      <Link
                        href={acctLinks.notif}
                        onClick={() => setAcctOpen(false)}
                        className="flex items-center gap-2.5 px-4 py-2.5 text-slate-600 hover:bg-gray-50"
                      >
                        <Bell className="w-4 h-4" /> Notifications
                      </Link>
                      <Link
                        href={acctLinks.settings}
                        onClick={() => setAcctOpen(false)}
                        className="flex items-center gap-2.5 px-4 py-2.5 text-slate-600 hover:bg-gray-50"
                      >
                        <Settings className="w-4 h-4" /> Settings
                      </Link>
                      <div className="border-t border-gray-100 mt-1 pt-1">
                        <form action={logoutAction}>
                          <button
                            type="submit"
                            className="w-full flex items-center gap-2.5 px-4 py-2.5 text-slate-600 hover:bg-gray-50 text-left"
                          >
                            Sign Out
                          </button>
                        </form>
                      </div>
                    </div>
                  </>
                )}
              </div>
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
                  className="hidden sm:inline bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium"
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
        <div className="px-3 py-3 border-t border-gray-200 space-y-1 text-sm">
          <button
            type="button"
            onClick={forceRefresh}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-md text-slate-600 hover:bg-gray-50 text-left"
          >
            <RefreshCw className="w-4 h-4" />
            Force Refresh Updates
          </button>
          <button
            type="button"
            data-theme-toggle
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-md text-slate-600 hover:bg-gray-50 text-left"
          >
            <SunMoon className="w-4 h-4" />
            Light / Dark Mode
          </button>
        </div>
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

