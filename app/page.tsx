import Link from 'next/link';
import { supabase } from '@/lib/supabase';
import { setting } from '@/lib/settings';
import HeroSlider, { HeroSlide } from '@/components/public/HeroSlider';
import SearchForm from '@/components/public/SearchForm';
import VehicleCard from '@/components/public/VehicleCard';
import { getPrimaryPhotos } from '@/components/public/data';

type DbVehicle = {
  id: number;
  reg_no: string;
  make: string;
  model: string;
  year: number | null;
  colour: string | null;
  transmission: string;
  fuel_type: string;
  engine_capacity: string | null;
  seats: number;
  mileage: number;
  description: string | null;
  daily_rate: number;
  weekly_rate: number | null;
  monthly_rate: number | null;
  deposit: number;
  status: string;
  is_public: boolean;
  category: string;
  created_at: string;
};

type SlideRow = {
  image: string;
  title: string;
  subtitle: string | null;
  description: string | null;
  cta1_label: string | null;
  cta1_url: string | null;
  cta2_label: string | null;
  cta2_url: string | null;
  overlay: number;
};

export default async function HomePage() {
  const [companyName, slidesRaw, vehiclesRaw, bookingsRaw] = await Promise.all([
    setting('company_name', 'Vehicle Hire'),
    supabase
      .from('hero_slides')
      .select('image, title, subtitle, description, cta1_label, cta1_url, cta2_label, cta2_url, overlay')
      .eq('is_active', true)
      .order('sort_order', { ascending: true })
      .order('id', { ascending: true })
      .limit(20),
    supabase
      .from('vehicles')
      .select('*')
      .eq('is_public', true)
      .not('status', 'in', '("maintenance","unavailable")')
      .order('daily_rate', { ascending: true })
      .returns<DbVehicle[]>(),
    supabase
      .from('bookings')
      .select('vehicle_id')
      .in('status', ['confirmed', 'active', 'completed', 'overdue'])
      .returns<{ vehicle_id: number }[]>(),
  ]);

  if (slidesRaw.error) throw slidesRaw.error;
  if (vehiclesRaw.error) throw vehiclesRaw.error;
  if (bookingsRaw.error) throw bookingsRaw.error;

  const slides: HeroSlide[] = (slidesRaw.data ?? []).length
    ? (slidesRaw.data ?? []).map((s: SlideRow) => ({ ...s, image: `/${s.image.replace(/^\/+/, '')}` }))
    : [
        {
          image: '/assets/img/car-placeholder.jpg',
          title: companyName,
          subtitle: 'Easy Bookings · Safe Journeys · Complete Control',
          description: 'Reliable vehicles, transparent pricing and verified payments.',
          cta1_label: 'Browse Fleet',
          cta1_url: '/vehicles',
          cta2_label: 'Get Started',
          cta2_url: '/register',
          overlay: 70,
        },
      ];

  const vehicles = vehiclesRaw.data ?? [];

  const hireCounts: Record<number, number> = {};
  for (const b of bookingsRaw.data ?? []) {
    if (!b.vehicle_id) continue;
    hireCounts[b.vehicle_id] = (hireCounts[b.vehicle_id] ?? 0) + 1;
  }

  const popular = [...vehicles]
    .sort((a, b) => (hireCounts[b.id] ?? 0) - (hireCounts[a.id] ?? 0) || a.daily_rate - b.daily_rate)
    .slice(0, 4);

  const categories: Record<string, [string, string, string]> = {
    budget: ['Budget Vehicles', 'wallet', 'Economical daily drivers — lowest rates in the fleet.'],
    sedan: ['Sedans', 'car', 'Comfortable saloons for business and family trips.'],
    suv: ['SUVs & 4x4s', 'car-front', 'Space, ground clearance and all-road confidence.'],
    premium: ['Premium Vehicles', 'sparkles', 'Executive and luxury models for special occasions.'],
    truck: ['Trucks & Pickups', 'truck', 'Load-moving pickups and trucks for work crews.'],
    utility: ['People Movers & Vans', 'bus', 'Minibuses and 7-seaters for groups, staff and events.'],
    pool: ['Pool Vehicles', 'key-round', 'Shared fleet cars — first-come, first-served.'],
  };

  const byCategory: Record<string, DbVehicle[]> = {};
  for (const [cat] of Object.entries(categories)) {
    byCategory[cat] = vehicles.filter((v) => v.category === cat).slice(0, 4);
  }

  // One photo query for every vehicle shown on this page — no N+1.
  const photoMap = await getPrimaryPhotos(vehicles.map((v) => v.id));

  const enrich = (list: DbVehicle[], includeHires = false) =>
    list.map((v) => ({
      ...v,
      photo: photoMap[v.id] ?? '/assets/img/car-placeholder.jpg',
      hires: includeHires ? (hireCounts[v.id] ?? 0) : undefined,
    }));

  const popularCards = enrich(popular, true);
  const categoryCards = Object.keys(categories).map((cat) => ({
    cat,
    label: categories[cat][0],
    icon: categories[cat][1],
    blurb: categories[cat][2],
    items: enrich(byCategory[cat]),
  }));

  return (
    <main>
      <HeroSlider slides={slides} />

      <section className="bg-gray-50 border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-6 py-8">
          <SearchForm />
        </div>
      </section>

      <section className="max-w-7xl mx-auto px-4 sm:px-6 my-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="card !p-3">
            <div className="flex items-center gap-3">
              <span className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="globe" className="w-4 h-4"></i>
              </span>
              <div className="min-w-0">
                <h3 className="font-semibold text-slate-800 text-sm">Browse as guest</h3>
                <p className="text-xs text-slate-500">
                  No account needed — browse vehicles, check availability and prices.{" "}
                  <Link href="/vehicles" className="text-blue-600 font-medium hover:underline whitespace-nowrap">
                    View vehicles →
                  </Link>
                </p>
              </div>
            </div>
          </div>
          <div className="card !p-3">
            <div className="flex items-center gap-3">
              <span className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="user" className="w-4 h-4"></i>
              </span>
              <div className="min-w-0">
                <h3 className="font-semibold text-slate-800 text-sm">Client portal</h3>
                <p className="text-xs text-slate-500">
                  Manage bookings, payments, deposits, wallet and documents.{" "}
                  <Link href="/register" className="text-blue-600 font-medium hover:underline whitespace-nowrap">
                    Create account →
                  </Link>
                </p>
              </div>
            </div>
          </div>
          <div className="card !p-3">
            <div className="flex items-center gap-3">
              <span className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="shield-check" className="w-4 h-4"></i>
              </span>
              <div className="min-w-0">
                <h3 className="font-semibold text-slate-800 text-sm">Simple &amp; secure</h3>
                <p className="text-xs text-slate-500">
                  Verified payments via Paynow, transparent pricing, real photos.{" "}
                  <Link href="/terms" className="text-blue-600 font-medium hover:underline whitespace-nowrap">
                    Read terms →
                  </Link>
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {popularCards.length > 0 && (
        <section className="max-w-7xl mx-auto px-6 pb-10">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h2 className="text-2xl font-semibold text-slate-800">Popular Vehicles</h2>
              <p className="text-sm text-slate-500 mt-1">Our most-hired vehicles — ranked by actual booking history.</p>
            </div>
            <Link href="/vehicles?sort=popular" className="text-blue-600 text-sm font-medium hover:underline">
              View all →
            </Link>
          </div>
          <div className="veh-strip">
            {popularCards.map((v) => (
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
                hires={v.hires}
              />
            ))}
          </div>
        </section>
      )}

      {categoryCards.map(
        (section) =>
          section.items.length > 0 && (
            <section key={section.cat} className="max-w-7xl mx-auto px-6 pb-10">
              <div className="flex items-center justify-between mb-6">
                <div>
                  <h2 className="text-2xl font-semibold text-slate-800">{section.label}</h2>
                  <p className="text-sm text-slate-500 mt-1">{section.blurb}</p>
                </div>
                <Link
                  href={`/vehicles?category=${section.cat}`}
                  className="text-blue-600 text-sm font-medium hover:underline"
                >
                  View all →
                </Link>
              </div>
              <div className="veh-strip">
                {section.items.map((v) => (
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
            </section>
          )
      )}

      <section className="max-w-7xl mx-auto px-6 pb-16 text-center">
        <Link href="/vehicles" className="btn-primary inline-flex !px-8 !py-3">
          <i data-lucide="layout-grid" className="w-4 h-4"></i> Browse the full fleet
        </Link>
      </section>

      <style>{`
        .veh-strip { display:grid; grid-template-columns:repeat(1, 1fr); gap:1.25rem; }
        @media (min-width: 640px){ .veh-strip { grid-template-columns:repeat(2, 1fr); } }
        @media (min-width: 1024px){ .veh-strip { grid-template-columns:repeat(4, 1fr); } }
      `}</style>
    </main>
  );
}
