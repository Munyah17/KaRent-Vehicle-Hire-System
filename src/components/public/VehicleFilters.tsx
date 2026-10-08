'use client';

import { useRouter, usePathname, useSearchParams } from 'next/navigation';
import { FormEvent } from 'react';
import { Search } from 'lucide-react';

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

  const hasFilters = ['q', 'pickup', 'return', 'category', 'fuel', 'transmission', 'max_price', 'sort'].some(
    (k) => sp.get(k)
  );

  return (
    <form method="get" onSubmit={submit} className="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">
      <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-x-4 gap-y-3">
        <div className="col-span-2 md:col-span-1 xl:col-span-1">
          <label className="label">Search</label>
          <input name="q" defaultValue={sp.get('q') ?? ''} placeholder="Make or model" className="input w-full" />
        </div>
        <div>
          <label className="label">Pickup</label>
          <input type="date" name="pickup" defaultValue={sp.get('pickup') ?? ''} className="input w-full" />
        </div>
        <div>
          <label className="label">Return</label>
          <input type="date" name="return" defaultValue={sp.get('return') ?? ''} className="input w-full" />
        </div>
        <div>
          <label className="label">Category</label>
          <select name="category" defaultValue={sp.get('category') ?? ''} className="input w-full">
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
          <select name="fuel" defaultValue={sp.get('fuel') ?? ''} className="input w-full">
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
          <select name="transmission" defaultValue={sp.get('transmission') ?? ''} className="input w-full">
            <option value="">Any</option>
            <option value="automatic">Automatic</option>
            <option value="manual">Manual</option>
          </select>
        </div>
      </div>
      <div className="mt-4 flex items-center justify-between gap-4">
        <div className="w-40">
          <label className="label">Max /day ($)</label>
          <input
            type="number"
            name="max_price"
            defaultValue={sp.get('max_price') ?? ''}
            className="input w-full"
            min={0}
          />
        </div>
        <div className="flex items-end gap-3">
          {hasFilters && (
            <a href={pathname} className="text-sm text-slate-500 hover:text-blue-600 hover:underline pb-2">
              Reset
            </a>
          )}
          <button className="btn-primary">
            <Search className="w-4 h-4" /> Search
          </button>
        </div>
      </div>
    </form>
  );
}
