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
            className="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-200 text-slate-600 hover:bg-gray-50"
          >
            <Menu className="w-5 h-5" />
          </button>
          <div className="flex items-center gap-3 sm:gap-4 text-sm">
            <button
              className="theme-toggle"
              type="button"
              data-theme-toggle
              title="Toggle dark / light mode"
              onClick={toggleTheme}
            >
              <SunMoon className="w-4 h-4" />
            </button>
            <Link href="/client/notifications" className="relative text-slate-500 hover:text-slate-700">
              <Bell className="w-5 h-5" />
              {unread > 0 && (
                <span className="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">
                  {unread}
                </span>
              )}
            </Link>
            <span className="text-slate-500 hidden md:block">{name}</span>
            <form action={logoutAction}>
              <button
                type="submit"
                className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-md font-medium"
              >
                Sign out
              </button>
            </form>
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
      </aside>

      <div id="portalBody" className="max-w-7xl mx-auto">
        {/* Fixed desktop sidebar (hidden on mobile — drawer handles it) */}
        <aside id="clientSidebar" aria-label="Portal navigation">
          <div className="px-5 h-16 flex items-center border-b border-gray-200 font-semibold text-slate-800">
            Menu
          </div>
          <nav className="flex-1 px-3 py-4 space-y-1 text-sm">{navLinks(false)}</nav>
        </aside>
        <main className="px-4 sm:px-6 py-6 sm:py-8 w-full">{children}</main>
      </div>
      <footer className="border-t border-gray-200 py-6 text-center text-xs text-slate-400">
        &copy; {new Date().getFullYear()} {companyName}
      </footer>
    </>
  );
}
