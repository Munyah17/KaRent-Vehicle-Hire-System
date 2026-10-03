'use client';

import { useRouter, useSearchParams } from 'next/navigation';
import { Search } from 'lucide-react';

export default function SearchForm() {
  const router = useRouter();
  const sp = useSearchParams();

  const today = new Date();
  const threeDays = new Date();
  threeDays.setDate(today.getDate() + 3);

  function fmt(d: Date) {
    return d.toISOString().split('T')[0];
  }

  function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const pickup = data.get('pickup') as string;
    const returnAt = data.get('return') as string;
    const params = new URLSearchParams();
    if (pickup) params.set('pickup', pickup);
    if (returnAt) params.set('return', returnAt);
    router.push(`/vehicles?${params.toString()}`);
  }

  return (
    <form onSubmit={submit} className="hero-card">
      <div className="search-grid">
        <div>
          <label className="label">Pickup</label>
          <input name="pickup" type="date" defaultValue={sp.get('pickup') || fmt(today)} min={fmt(today)} className="input" />
        </div>
        <div>
          <label className="label">Return</label>
          <input name="return" type="date" defaultValue={sp.get('return') || fmt(threeDays)} min={sp.get('pickup') || fmt(today)} className="input" />
        </div>
        <button className="button" style={{ width: '100%' }}><Search className="w-4 h-4" style={{ marginRight: 8 }} />Find a vehicle</button>
      </div>
    </form>
  );
}
