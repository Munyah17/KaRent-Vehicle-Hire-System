'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useCallback, useEffect, useState } from 'react';
import {
  type LucideIcon,
  LayoutDashboard,
  Car,
  CalendarCheck,
  CreditCard,
  Wallet,
  PiggyBank,
  FileText,
  ShieldCheck,
  CalendarPlus,
  LifeBuoy,
  Settings,
  Menu,
  X,
  SunMoon,
  Bell,
  RefreshCw,
  Globe,
  ChevronDown,
} from 'lucide-react';
import { logoutAction } from '@/app/client/actions';

const NAV_ITEMS: { href: string; label: string; icon: LucideIcon; exact?: boolean }[] = [
  { href: '/client', label: 'Dashboard', icon: LayoutDashboard, exact: true },
  { href: '/vehicles', label: 'Book a Vehicle', icon: Car, exact: true },
  { href: '/client/bookings', label: 'My Bookings', icon: CalendarCheck },
  { href: '/client/payments', label: 'Payments', icon: CreditCard },
  { href: '/client/wallet', label: 'Wallet', icon: Wallet },
  { href: '/client/deposits', label: 'Deposits', icon: PiggyBank },
  { href: '/client/documents', label: 'Documents', icon: FileText },
  { href: '/client/profile', label: 'Profile & KYC', icon: ShieldCheck },
  { href: '/client/extensions', label: 'Extensions', icon: CalendarPlus },
  { href: '/client/support', label: 'Support', icon: LifeBuoy },
  { href: '/client/settings', label: 'Settings', icon: Settings },
];

function toggleTheme() {
  const dark = !document.documentElement.classList.contains('dark');
  document.documentElement.classList.toggle('dark', dark);
  document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
  const t = dark ? 'dark' : 'light';
  try {
    localStorage.setItem('karent.theme', t);
  } catch {
    /* ignore */
  }
  document.cookie = 'theme=' + t + ';path=/;max-age=31536000;SameSite=Lax';
}

export default function ClientShell({
  name,
  unread,
  companyName,
  children,
}: {
  name: string;
  unread: number;
  companyName: string;
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const [acctOpen, setAcctOpen] = useState(false);

  const close = useCallback(() => setOpen(false), []);

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const isActive = (href: string, exact?: boolean) =>
    exact ? pathname === href : pathname === href || pathname.startsWith(href + '/');

  const forceRefresh = () => {
    const url = new URL(window.location.href);
    url.searchParams.set('_r', String(Date.now()));
    window.location.href = url.toString();
  };

  const initial = (name || 'U').charAt(0).toUpperCase();

  const utilLinks = (drawer: boolean) => (
    <>
      <Link
        href="/"
        onClick={drawer ? close : undefined}
        className="flex items-center gap-3 px-3 py-2.5 rounded-md text-slate-600 hover:bg-gray-50"
      >
        <Globe className="w-4 h-4" />
        Go To Website
      </Link>
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
        onClick={toggleTheme}
        className="w-full flex items-center gap-3 px-3 py-2.5 rounded-md text-slate-600 hover:bg-gray-50 text-left"
      >
        <SunMoon className="w-4 h-4" />
        Light / Dark Mode
      </button>
    </>
  );

  const navLinks = (drawer: boolean) =>
    NAV_ITEMS.map((item) => {
      const { href, label, icon: Icon } = item;
      const active = isActive(href, item.exact ?? false);
      return (
        <Link
          key={href}
          href={href}
          onClick={drawer ? close : undefined}
          className={`${drawer ? 'drawer-link ' : ''}flex items-center gap-3 px-3 py-2.5 rounded-md ${
            active ? 'text-blue-600 font-medium bg-blue-50' : 'text-slate-600 hover:bg-gray-50'
          }`}
        >
          <Icon className="w-4 h-4" />
          {label}
        </Link>
      );
    });

  return (
    <>
      <header className="site-header bg-white border-b border-gray-200 text-slate-700 sticky top-0 z-40">
        <style>{`/* Desktop: fixed sidebar inside the dashboard; mobile: drawer slides over content */
#clientSidebar{ display:none; }
@media (min-width: 1024px){
  #clientSidebar{ display:flex; position:sticky; top:64px; height:calc(100vh - 64px);
    width:248px; flex-shrink:0; overflow-y:auto; flex-direction:column;
    border-right:1px solid #e5e7eb; background:#fff; }
  #drawerBtn, #drawerBackdrop, #siteDrawer{ display:none !important; }
  #portalBody{ display:flex; align-items:flex-start; gap:0; }
  #portalBody > main{ flex:1; min-width:0; }
}`}</style>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <button
            id="drawerBtn"
            type="button"
            aria-label="Open menu"
            aria-controls="siteDrawer"
            onClick={() => setOpen(true)}
            className="inline-flex items-center justify-center w-9 h-9 -ml-1 text-slate-600 hover:text-slate-800"
          >
            <Menu className="w-5 h-5" />
          </button>
          <div className="flex items-center gap-3 sm:gap-4 text-sm relative">
            <button
              type="button"
              onClick={() => setAcctOpen((v) => !v)}
              aria-label="Account menu"
              aria-expanded={acctOpen}
              className="flex items-center gap-1.5"
            >
              <span className="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-semibold">
                {initial}
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
                    <p className="font-semibold text-slate-800 truncate">{name}</p>
                  </div>
                  <Link
                    href="/client/profile"
                    onClick={() => setAcctOpen(false)}
                    className="flex items-center gap-2.5 px-4 py-2.5 text-slate-600 hover:bg-gray-50"
                  >
                    <ShieldCheck className="w-4 h-4" /> My Profile
                  </Link>
                  <Link
                    href="/client/notifications"
                    onClick={() => setAcctOpen(false)}
                    className="flex items-center justify-between px-4 py-2.5 text-slate-600 hover:bg-gray-50"
                  >
                    <span className="flex items-center gap-2.5">
                      <Bell className="w-4 h-4" /> Notifications
                    </span>
                    {unread > 0 && (
                      <span className="bg-red-500 text-white text-[10px] rounded-full min-w-4 h-4 px-1 flex items-center justify-center">
                        {unread}
                      </span>
                    )}
                  </Link>
                  <Link
                    href="/client/settings"
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
        </div>
      </header>

      {/* Off-canvas navigation drawer */}
      <div id="drawerBackdrop" className={open ? 'open' : ''} onClick={close}></div>
      <aside id="siteDrawer" aria-label="Portal navigation" className={open ? 'open' : ''}>
        <div className="flex items-center justify-between px-5 h-16 border-b border-gray-200">
          <span className="font-semibold text-slate-800">Menu</span>
          <button
            id="drawerClose"
            type="button"
            aria-label="Close menu"
            onClick={close}
            className="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        <nav className="flex-1 px-3 py-4 space-y-1 text-sm">{navLinks(true)}</nav>
        <div className="px-3 py-4 border-t border-gray-200 space-y-1 text-sm">
          {utilLinks(true)}
        </div>
      </aside>

      <div id="portalBody" className="max-w-7xl mx-auto">
        {/* Fixed desktop sidebar (hidden on mobile — drawer handles it) */}
        <aside id="clientSidebar" aria-label="Portal navigation">
          <div className="px-5 h-16 flex items-center border-b border-gray-200 font-semibold text-slate-800">
            Menu
          </div>
          <nav className="flex-1 px-3 py-4 space-y-1 text-sm">{navLinks(false)}</nav>
          <div className="px-3 py-4 border-t border-gray-200 space-y-1 text-sm">
            {utilLinks(false)}
          </div>
        </aside>
        <main className="px-4 sm:px-6 py-6 sm:py-8 w-full">{children}</main>
      </div>
      <footer className="border-t border-gray-200 py-6 text-center text-xs text-slate-400">
        &copy; {new Date().getFullYear()} {companyName}
      </footer>
    </>
  );
}
