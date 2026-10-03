import { createElement } from 'react';

export const money = (n: number | string | null | undefined) =>
  '$' + Number(n ?? 0).toFixed(2);

export const fmtDate = (d: string | Date | null | undefined) =>
  d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

export const fmtDateTime = (d: string | Date | null | undefined) =>
  d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
      ' ' + new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '—';

export function ref(prefix: string): string {
  const rand = Math.random().toString(36).slice(2, 8).toUpperCase();
  return `${prefix}-${Date.now().toString(36).toUpperCase()}${rand}`;
}

const BADGE: Record<string, string> = {
  available: 'bg-green-100 text-green-700',
  pending: 'bg-amber-100 text-amber-700',
  confirmed: 'bg-blue-100 text-blue-700',
  active: 'bg-indigo-100 text-indigo-700',
  on_hire: 'bg-indigo-100 text-indigo-700',
  completed: 'bg-slate-200 text-slate-700',
  cancelled: 'bg-red-100 text-red-700',
  overdue: 'bg-red-100 text-red-700',
  reserved: 'bg-amber-100 text-amber-700',
  maintenance: 'bg-orange-100 text-orange-700',
  unavailable: 'bg-slate-200 text-slate-600',
  successful: 'bg-green-100 text-green-700',
  failed: 'bg-red-100 text-red-700',
  refunded: 'bg-slate-200 text-slate-700',
  held: 'bg-blue-100 text-blue-700',
  released: 'bg-green-100 text-green-700',
  forfeited: 'bg-red-100 text-red-700',
  partial: 'bg-amber-100 text-amber-700',
  verified: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
  under_review: 'bg-amber-100 text-amber-700',
  open: 'bg-amber-100 text-amber-700',
  in_progress: 'bg-blue-100 text-blue-700',
  resolved: 'bg-green-100 text-green-700',
  closed: 'bg-slate-200 text-slate-600',
  suspended: 'bg-red-100 text-red-700',
  reported: 'bg-amber-100 text-amber-700',
  assessed: 'bg-blue-100 text-blue-700',
  charged: 'bg-indigo-100 text-indigo-700',
};

export function Badge({ status }: { status?: string | null }) {
  const s = status || 'unknown';
  const cls = BADGE[s] ?? 'bg-slate-100 text-slate-600';
  return createElement(
    'span',
    { className: `inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${cls}` },
    s.replace(/_/g, ' ')
  );
}

export const days = (from: string, to: string) =>
  Math.max(1, Math.ceil((new Date(to).getTime() - new Date(from).getTime()) / 86400000));

export const CATEGORIES = ['budget', 'sedan', 'suv', 'truck', 'premium'] as const;
export const catLabel = (c: string) => ({ budget: 'Budget', sedan: 'Sedan', suv: 'SUV', truck: 'Truck / Bakkie', premium: 'Premium', pool: 'Pool', utility: 'Utility' }[c] ?? c);
