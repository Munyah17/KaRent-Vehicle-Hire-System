import { Metadata } from 'next';
import Link from 'next/link';
import { setting } from '@/lib/settings';
import VehicleCard from '@/components/public/VehicleCard';
import VehicleFilters from '@/components/public/VehicleFilters';
import { getPrimaryPhotos, listVehicles } from '@/components/public/data';

type VehicleRow = Awaited<ReturnType<typeof listVehicles>>[number] & { category?: string };

export const metadata: Metadata = { title: 'Vehicles' };

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

  const photoMap = await getPrimaryPhotos(vehicles.map((v) => v.id));
  const cards = vehicles.map((v) => ({
    ...v,
    photo: photoMap[v.id] ?? '/assets/img/car-placeholder.jpg',
  }));

  // Searching by dates/text/specs → flat result grid. Otherwise the fleet is
  // grouped into ordered category sections (mobile: snap-carousel strips).
  const isSearching = !!(q || fuel || transmission || maxPrice !== null || (pickup && returnAt) || sort);
  const orderedCats = Object.keys(catLabels);
  const sections = isSearching
    ? []
    : orderedCats
        .filter((cat) => !catLabels[category] || cat === category)
        .map((cat) => ({
          cat,
          label: catLabels[cat],
          items: cards.filter((v) => v.category?.toLowerCase() === cat),
        }))
        .filter((s) => s.items.length > 0);

  // Anything in an unmapped category still needs a home.
  if (!isSearching && !catLabels[category]) {
    const shown = new Set(sections.flatMap((s) => s.items.map((v) => v.id)));
    const rest = cards.filter((v) => !shown.has(v.id));
    if (rest.length) sections.push({ cat: 'other', label: 'Other Vehicles', items: rest });
  }

  const card = (v: (typeof cards)[number]) => (
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
  );

  return (
    <main>
      <div className="bg-gray-50 border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-6 py-8">
          <h1 className="text-2xl font-semibold text-slate-800 mb-5">Book a Vehicle</h1>
          <VehicleFilters />
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-6 py-10">
        <p className="text-sm text-slate-500 mb-6">
          {cards.length} vehicle{cards.length === 1 ? '' : 's'} {pickup && returnAt ? 'available for your dates' : 'in our fleet'}
        </p>

        {isSearching ? (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            {cards.map(card)}
          </div>
        ) : (
          sections.map((section) => (
            <section key={section.cat} className="mb-10 last:mb-0">
              <div className="flex items-baseline justify-between mb-4">
                <h2 className="text-lg font-semibold text-slate-800">{section.label}</h2>
                <span className="text-xs text-slate-400">
                  {section.items.length} vehicle{section.items.length === 1 ? '' : 's'}
                </span>
              </div>
              <div className="veh-strip">{section.items.map(card)}</div>
            </section>
          ))
        )}

        {cards.length === 0 && (
          <div className="bg-white rounded-xl border border-gray-200 text-center py-20 text-slate-400">
            No vehicles match your search — try different dates or filters.
          </div>
        )}
      </div>
    </main>
  );
}
