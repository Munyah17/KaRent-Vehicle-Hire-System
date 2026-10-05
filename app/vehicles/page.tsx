import { Metadata } from 'next';
import Link from 'next/link';
import { setting } from '@/lib/settings';
import VehicleCard from '@/components/public/VehicleCard';
import VehicleFilters from '@/components/public/VehicleFilters';
import { getPrimaryPhoto, listVehicles } from '@/components/public/data';

type VehicleRow = Awaited<ReturnType<typeof listVehicles>>[number] & { category?: string };

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'Vehicle Hire');
  return { title: `Vehicles · ${name}` };
}

export default async function VehiclesPage({
  searchParams,
}: {
  searchParams: Promise<{
    pickup?: string;
    return?: string;
    q?: string;
    fuel?: string;
    transmission?: string;
    max_price?: string;
    category?: string;
    sort?: string;
  }>;
}) {
  const sp = await searchParams;
  const pickup = sp.pickup ?? '';
  const returnAt = sp.return ?? '';
  const q = (sp.q ?? '').trim().toLowerCase();
  const fuel = sp.fuel ?? '';
  const transmission = sp.transmission ?? '';
  const maxPrice = sp.max_price ? Number(sp.max_price) : null;
  const category = sp.category ?? '';
  const sort = sp.sort ?? '';

  const catLabels: Record<string, string> = {
    budget: 'Budget',
    sedan: 'Sedan',
    suv: 'SUV',
    truck: 'Truck / Pickup',
    premium: 'Premium',
    pool: 'Pool',
    utility: 'Utility',
  };

  let vehicles: VehicleRow[] = (await listVehicles(
    pickup && returnAt ? { pickup, return: returnAt } : undefined
  )) as VehicleRow[];

  // Client-side filtering (keeps data.ts unchanged)
  if (fuel) {
    vehicles = vehicles.filter((v) => v.fuel_type.toLowerCase() === fuel.toLowerCase());
  }
  if (transmission) {
    vehicles = vehicles.filter((v) => v.transmission.toLowerCase() === transmission.toLowerCase());
  }
  if (maxPrice !== null && !isNaN(maxPrice)) {
    vehicles = vehicles.filter((v) => Number(v.daily_rate) <= maxPrice);
  }
  if (catLabels[category]) {
    vehicles = vehicles.filter((v) => v.category?.toLowerCase() === category.toLowerCase());
  }
  if (q) {
    vehicles = vehicles.filter(
      (v) =>
        v.make.toLowerCase().includes(q) ||
        v.model.toLowerCase().includes(q)
    );
  }
  if (sort === 'popular') {
    // Keep the order produced by the popularity logic; data.ts has no popularity sort.
    // We leave the list as-is to match the PHP intent (popular sort is a flag only here).
  }

  const cards = await Promise.all(
    vehicles.map(async (v) => {
      const photo = await getPrimaryPhoto(v.id);
      return { ...v, photo };
    })
  );

  return (
    <main>
      <div className="bg-gray-50 border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-6 py-8">
          <h1 className="text-2xl font-semibold text-slate-800 mb-5">Available Vehicles</h1>
          <VehicleFilters />
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-6 py-10">
        <p className="text-sm text-slate-500 mb-6">
          {cards.length} vehicle{cards.length === 1 ? '' : 's'} {pickup && returnAt ? 'available for your dates' : 'in our fleet'}
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          {cards.map((v) => (
            <VehicleCard
              key={v.id}
              id={v.id}
              make={v.make}
              model={v.model}
              year={v.year}
              transmission={v.transmission}
              fuel_type={v.fuel_type}
              seats={v.seats}
              daily_rate={v.daily_rate}
              status={v.status}
              photo={v.photo}
              reg_no={v.reg_no}
            />
          ))}
        </div>
        {cards.length === 0 && (
          <div className="bg-white rounded-xl border border-gray-200 text-center py-20 text-slate-400">
            No vehicles match your search — try different dates or filters.
          </div>
        )}
      </div>
    </main>
  );
}
