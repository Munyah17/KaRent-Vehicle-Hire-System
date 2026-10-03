'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const LINKS: { href: string; label: string; exact?: boolean }[] = [
  { href: '/client', label: 'Dashboard', exact: true },
  { href: '/client/bookings', label: 'Bookings' },
  { href: '/client/payments', label: 'Payments' },
  { href: '/client/wallet', label: 'Wallet' },
  { href: '/client/deposits', label: 'Deposits' },
  { href: '/client/documents', label: 'Documents' },
  { href: '/client/extensions', label: 'Extensions' },
  { href: '/client/support', label: 'Support' },
  { href: '/client/profile', label: 'Profile & KYC' },
];

export default function ClientNav() {
  const pathname = usePathname();
  return (
    <nav className="cp-nav" aria-label="Portal">
      {LINKS.map((l) => {
        const on = l.exact ? pathname === l.href : pathname === l.href || pathname.startsWith(l.href + '/');
        return (
          <Link key={l.href} href={l.href} className={on ? 'on' : ''} aria-current={on ? 'page' : undefined}>
            {l.label}
          </Link>
        );
      })}
    </nav>
  );
}
