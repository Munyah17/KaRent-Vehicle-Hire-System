'use client';

import { useRouter, useSearchParams } from 'next/navigation';

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
    <form
      onSubmit={submit}
      className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col sm:flex-row gap-3 items-stretch sm:items-end max-w-2xl"
    >
      <div className="flex-1">
        <label className="block text-xs font-medium text-gray-500 mb-1">Pickup</label>
        <input
          type="date"
          name="pickup"
          defaultValue={sp.get('pickup') || fmt(today)}
          min={fmt(today)}
          className="input"
        />
      </div>
      <div className="flex-1">
        <label className="block text-xs font-medium text-gray-500 mb-1">Return</label>
        <input
          type="date"
          name="return"
          defaultValue={sp.get('return') || fmt(threeDays)}
          min={sp.get('pickup') || fmt(today)}
          className="input"
        />
      </div>
      <button className="btn-primary !px-6 justify-center">
        <i data-lucide="search" className="w-4 h-4"></i> Find a vehicle
      </button>
    </form>
  );
}
