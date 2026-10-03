'use client';

import { useRouter, useSearchParams, usePathname } from 'next/navigation';
import { useTransition } from 'react';

export default function VehicleFilters() {
  const router = useRouter();
  const pathname = usePathname();
  const sp = useSearchParams();
  const [pending, start] = useTransition();

  function update(key: string, value: string) {
    start(() => {
      const next = new URLSearchParams(sp.toString());
      if (value) next.set(key, value);
      else next.delete(key);
      router.push(`${pathname}?${next.toString()}`);
    });
  }

  return (
    <div className="filters">
      <div>
        <label className="label">Pickup</label>
        <input type="date" className="input" defaultValue={sp.get('pickup') || ''} onChange={(e) => update('pickup', e.target.value)} />
      </div>
      <div>
        <label className="label">Return</label>
        <input type="date" className="input" defaultValue={sp.get('return') || ''} onChange={(e) => update('return', e.target.value)} />
      </div>
      <button className="button" disabled={pending} style={{ width: '100%' }}>Filter</button>
    </div>
  );
}
