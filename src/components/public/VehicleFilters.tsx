'use client';

import { useRouter, usePathname, useSearchParams } from 'next/navigation';
import { FormEvent } from 'react';

const catLabels: Record<string, string> = {
  budget: 'Budget',
  sedan: 'Sedan',
  suv: 'SUV',
  truck: 'Truck / Pickup',
  premium: 'Premium',
  pool: 'Pool',
  utility: 'Utility',
};

export default function VehicleFilters() {
  const router = useRouter();
  const pathname = usePathname();
  const sp = useSearchParams();

  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const form = new FormData(e.currentTarget);
    const next = new URLSearchParams();
    for (const [key, value] of form.entries()) {
      if (value) next.set(key, String(value));
    }
    router.push(`${pathname}?${next.toString()}`);
  };

  return (
    <form method="get" onSubmit={submit} className="flex flex-wrap items-end gap-3">
      <div className="relative">
        <label className="label">Search</label>
        <input name="q" defaultValue={sp.get('q') ?? ''} placeholder="Make or model" className="input w-44" />
      </div>
      <div>
        <label className="label">Pickup</label>
        <input type="date" name="pickup" defaultValue={sp.get('pickup') ?? ''} className="input" />
      </div>
      <div>
        <label className="label">Return</label>
        <input type="date" name="return" defaultValue={sp.get('return') ?? ''} className="input" />
      </div>
      <div>
        <label className="label">Category</label>
        <select name="category" defaultValue={sp.get('category') ?? ''} className="input w-36">
          <option value="">Any</option>
          {Object.entries(catLabels).map(([val, lab]) => (
            <option key={val} value={val}>
              {lab}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="label">Fuel</label>
        <select name="fuel" defaultValue={sp.get('fuel') ?? ''} className="input w-32">
          <option value="">Any</option>
          {['petrol', 'diesel', 'hybrid', 'electric'].map((f) => (
            <option key={f} value={f}>
              {f.charAt(0).toUpperCase() + f.slice(1)}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label className="label">Transmission</label>
        <select name="transmission" defaultValue={sp.get('transmission') ?? ''} className="input w-36">
          <option value="">Any</option>
          <option value="automatic">Automatic</option>
          <option value="manual">Manual</option>
        </select>
      </div>
      <div>
        <label className="label">Max /day</label>
        <input
          type="number"
          name="max_price"
          defaultValue={sp.get('max_price') ?? ''}
          className="input w-28"
          min={0}
        />
      </div>
      <button className="btn-primary">
        <i data-lucide="search" className="w-4 h-4"></i> Search
      </button>
    </form>
  );
}
