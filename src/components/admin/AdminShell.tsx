'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';
import { logoutAction } from '@/app/admin/actions';

interface AdminUser {
  name: string;
  email: string;
  role: string;
}

interface NavItem {
  key: string;
  label: string;
  href?: string;
  icon: string;
  children?: { key: string; label: string; href: string }[];
}

const nav: NavItem[] = [
  { key: 'dashboard', label: 'Dashboard', href: '/admin/dashboard', icon: 'fa-gauge' },
  {
    key: 'bookings',
    label: 'Bookings',
    icon: 'fa-calendar-check',
    children: [
      { key: 'bookings', label: 'All bookings', href: '/admin/bookings' },
      { key: 'bookings', label: 'New booking', href: '#' },
      { key: 'calendar', label: 'Calendar', href: '#' },
    ],
  },
  {
    key: 'vehicles',
    label: 'Fleet',
    icon: 'fa-car-side',
    children: [
      { key: 'vehicles', label: 'Vehicles', href: '/admin/vehicles' },
      { key: 'maintenance', label: 'Maintenance', href: '#' },
      { key: 'damages', label: 'Damages', href: '#' },
    ],
  },
  {
    key: 'clients',
    label: 'Clients',
    icon: 'fa-users',
    children: [
      { key: 'clients', label: 'Clients Management', href: '/admin/clients' },
      { key: 'kyc', label: 'KYC review', href: '#' },
      { key: 'wallets', label: 'Wallets', href: '#' },
      { key: 'deposits', label: 'Deposits', href: '#' },
    ],
  },
  {
    key: 'payments',
    label: 'Finance',
    icon: 'fa-credit-card',
    children: [
      { key: 'payments', label: 'Payments', href: '/admin/payments' },
      { key: 'fiscal', label: 'Fiscalisation', href: '#' },
      { key: 'expenses', label: 'Expenses', href: '#' },
    ],
  },
  {
    key: 'contracts',
    label: 'Documents',
    icon: 'fa-file-lines',
    children: [
      { key: 'contracts', label: 'Contracts', href: '#' },
      { key: 'templates', label: 'Templates', href: '#' },
      { key: 'hero', label: 'Hero slides', href: '#' },
    ],
  },
  {
    key: 'reports',
    label: 'System',
    icon: 'fa-gear',
    children: [
      { key: 'reports', label: 'Reports', href: '#' },
      { key: 'staff', label: 'Staff Management', href: '/admin/staff' },
      { key: 'settings', label: 'Settings', href: '#' },
      { key: 'audit', label: 'Audit log', href: '#' },
    ],
  },
];

const pageTitles: Record<string, string> = {
  '/admin/dashboard': 'Dashboard',
  '/admin/bookings': 'Bookings',
  '/admin/vehicles': 'Vehicles',
  '/admin/clients': 'Clients',
  '/admin/payments': 'Payments',
  '/admin/staff': 'Staff & Permissions',
};

export default function AdminShell({
  children,
  user,
}: {
  children: React.ReactNode;
  user: AdminUser;
}) {
  const pathname = usePathname();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [openSubs, setOpenSubs] = useState<Record<string, boolean>>({});
  const [notifOpen, setNotifOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);

  const initials = useMemo(
    () => (user.name?.charAt(0)?.toUpperCase() ?? 'A'),
    [user.name]
  );

  const pageTitle = pageTitles[pathname] ?? 'Admin';

  useEffect(() => {
    document.body.classList.add('app');
    return () => {
      document.body.classList.remove('app');
    };
  }, []);

  useEffect(() => {
    if (mobileOpen) {
      document.body.classList.add('sidebar-open');
    } else {
      document.body.classList.remove('sidebar-open');
    }
  }, [mobileOpen]);

  useEffect(() => {
    if (typeof window !== 'undefined' && (window as any).lucide) {
      (window as any).lucide.createIcons();
    }
  }, [pathname, notifOpen, accountOpen]);

  function toggleSub(key: string) {
    setOpenSubs((prev) => ({ ...prev, [key]: !prev[key] }));
  }

  function isActive(item: NavItem): boolean {
    if (item.href) return pathname === item.href;
    return (
      item.children?.some((c) => pathname === c.href || pathname.startsWith(`${c.href}/`)) ??
      false
    );
  }

  function isChildActive(child: { key: string; href: string }): boolean {
    return pathname === child.href || pathname.startsWith(`${child.href}/`);
  }

  async function handleLogout() {
    await logoutAction();
    window.location.href = '/admin/login';
  }

  return (
    <div className="page-wrapper">
      {/* Mobile topbar */}
      <header className="header-mobile d-block d-lg-none">
        <div className="header-mobile__bar">
          <div className="header-mobile-inner">
            <Link className="logo" href="/admin/dashboard">
              <span className="logo-mark">K</span>
              <span className="logo-text">KaRent</span>
            </Link>
            <button
              className="sidebar-toggle js-sidebar-toggle"
              type="button"
              aria-label="Open menu"
              onClick={() => setMobileOpen(true)}
            >
              <i className="fa-solid fa-bars"></i>
            </button>
          </div>
        </div>
      </header>

      {/* Sidebar */}
      <aside className={`menu-sidebar ${mobileOpen ? 'show-sidebar' : ''}`} id="main-sidebar">
        <div className="logo">
          <Link className="logo-link" href="/admin/dashboard">
            <span className="logo-mark">K</span>
            <span className="logo-text">KaRent</span>
          </Link>
          <button
            className="sidebar-close js-sidebar-toggle"
            type="button"
            aria-label="Close navigation"
            onClick={() => setMobileOpen(false)}
          >
            <i className="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div className="menu-sidebar__content js-scrollbar1">
          <nav className="navbar-sidebar">
            <ul className="list-unstyled navbar__list">
              {nav.map((item) => {
                if (item.children) {
                  const active = isActive(item);
                  const open = openSubs[item.key] || active;
                  return (
                    <li key={item.key} className={`has-sub ${active ? 'active' : ''}`}>
                      <a
                        className={`js-arrow ${open ? 'open' : ''}`}
                        href="#"
                        onClick={(e) => {
                          e.preventDefault();
                          toggleSub(item.key);
                        }}
                      >
                        <i className={`fa-solid ${item.icon}`}></i>
                        {item.label}
                      </a>
                      <ul
                        className="list-unstyled navbar__sub-list js-sub-list"
                        style={{ display: open ? 'block' : 'none' }}
                      >
                        {item.children.map((child) => (
                          <li
                            key={`${item.key}-${child.key}`}
                            className={isChildActive(child) ? 'active' : ''}
                          >
                            <Link href={child.href} onClick={() => setMobileOpen(false)}>
                              {child.label}
                            </Link>
                          </li>
                        ))}
                      </ul>
                    </li>
                  );
                }
                return (
                  <li key={item.key} className={isActive(item) ? 'active' : ''}>
                    <Link href={item.href!} onClick={() => setMobileOpen(false)}>
                      <i className={`fa-solid ${item.icon}`}></i>
                      {item.label}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
        </div>
      </aside>

      {/* Main column */}
      <div className="page-container">
        <header className="header-desktop">
          <div className="section__content section__content--p30">
            <div className="container-fluid">
              <div className="header-wrap">
                <div className="d-flex align-items-center gap-3">
                  <h1
                    className="page-title m-0"
                    style={{ fontSize: '1.05rem', fontWeight: 600 }}
                  >
                    {pageTitle}
                  </h1>
                </div>
                <div className="header-button">
                  <button
                    className="theme-toggle"
                    type="button"
                    data-theme-toggle
                    title="Toggle dark / light mode"
                    onClick={() => {
                      const html = document.documentElement;
                      const dark = html.getAttribute('data-bs-theme') === 'dark';
                      if (dark) {
                        html.classList.remove('dark');
                        html.removeAttribute('data-bs-theme');
                        localStorage.setItem('karent.theme', 'light');
                      } else {
                        html.classList.add('dark');
                        html.setAttribute('data-bs-theme', 'dark');
                        localStorage.setItem('karent.theme', 'dark');
                      }
                    }}
                  >
                    <i className="fa-solid fa-circle-half-stroke"></i>
                  </button>
                  <div className="noti-wrap">
                    <div
                      className="noti__item js-item-menu"
                      role="button"
                      tabIndex={0}
                      aria-haspopup="true"
                      aria-label="Notifications"
                      onClick={() => setNotifOpen((v) => !v)}
                    >
                      <i className="fa-solid fa-bell"></i>
                      <div
                        className="notifi-dropdown js-dropdown"
                        style={{ display: notifOpen ? 'block' : 'none' }}
                      >
                        <div className="notifi__title">
                          <p>You have 0 unread notifications</p>
                        </div>
                        <div className="notifi__item">
                          <div className="content">
                            <p>No notifications yet.</p>
                          </div>
                        </div>
                        <div className="notifi__footer">
                          <Link href="#">All notifications</Link>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div className="account-wrap">
                    <div
                      className="account-item clearfix js-item-menu"
                      role="button"
                      tabIndex={0}
                      aria-haspopup="true"
                      aria-label="Account menu"
                      onClick={() => setAccountOpen((v) => !v)}
                    >
                      <div className="image">
                        <span className="img-cir img-40 bg-c1 d-inline-flex align-items-center justify-content-center fw-semibold text-white">
                          {initials}
                        </span>
                      </div>
                      <div className="content">
                        <a className="js-acc-btn" href="#">
                          {user.name}
                        </a>
                      </div>
                      <div
                        className="account-dropdown js-dropdown"
                        style={{ display: accountOpen ? 'block' : 'none' }}
                      >
                        <div className="info clearfix">
                          <div className="image">
                            <span className="img-cir img-40 bg-c1 d-inline-flex align-items-center justify-content-center fw-semibold text-white">
                              {initials}
                            </span>
                          </div>
                          <div className="content">
                            <h5 className="name">
                              <a href="#">{user.name}</a>
                            </h5>
                            <span className="email">{user.email}</span>
                            <span
                              className="d-block"
                              style={{ fontSize: '.7rem', opacity: 0.6 }}
                            >
                              {user.role.replace('_', ' ')}
                            </span>
                          </div>
                        </div>
                        <div className="account-dropdown__body">
                          <div className="account-dropdown__item">
                            <Link href="#">
                              <i className="fa-solid fa-bell"></i>Notifications
                            </Link>
                          </div>
                          <div className="account-dropdown__item">
                            <Link href="#">
                              <i className="fa-solid fa-key"></i>Change password
                            </Link>
                          </div>
                        </div>
                        <div className="account-dropdown__footer">
                          <a href="#" onClick={(e) => { e.preventDefault(); handleLogout(); }}>
                            <i className="fa-solid fa-power-off"></i>Logout
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </header>

        <main className="main-content">
          <div className="section__content section__content--p30">
            <div className="container-fluid">{children}</div>
          </div>
        </main>
      </div>

      {mobileOpen && (
        <div
          className="sidebar-backdrop d-lg-none"
          onClick={() => setMobileOpen(false)}
          aria-hidden="true"
        />
      )}
    </div>
  );
}
