import Link from 'next/link';
import { supabase } from '@/lib/supabase';
import { setting } from '@/lib/settings';
import HeroSlider, { HeroSlide } from '@/components/public/HeroSlider';
import SearchForm from '@/components/public/SearchForm';
import VehicleCard from '@/components/public/VehicleCard';
import { getPrimaryPhoto, Vehicle } from '@/components/public/data';
import { Globe, User, ShieldCheck } from 'lucide-react';

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
  is_featured: boolean;
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

function toVehicle(row: DbVehicle): Vehicle {
  return {
    ...row,
    is_public: row.is_public ? 1 : 0,
    is_featured: row.is_featured ? 1 : 0,
  };
}

export default async function HomePage() {
  const [companyName, slidesRaw, vehiclesRaw] = await Promise.all([
    setting('company_name', 'KaRent'),
    supabase
      .from('hero_slides')
      .select('image, title, subtitle, description, cta1_label, cta1_url, cta2_label, cta2_url, overlay')
      .eq('is_active', true)
      .order('sort_order', { ascending: true })
      .order('id', { ascending: true })
      .limit(20)
      .returns<SlideRow[]>(),
    supabase
      .from('vehicles')
      .select('*')
      .eq('is_public', true)
      .in('status', ['available', 'reserved', 'on_hire'])
      .order('is_featured', { ascending: false })
      .order('daily_rate', { ascending: true })
      .limit(6)
      .returns<DbVehicle[]>(),
  ]);

  if (slidesRaw.error) throw slidesRaw.error;
  if (vehiclesRaw.error) throw vehiclesRaw.error;

  const slides: HeroSlide[] = (slidesRaw.data ?? []).length
    ? (slidesRaw.data ?? []).map((s) => ({ ...s, image: `/uploads/${s.image}` }))
    : [
        {
          image: '/assets/img/car-placeholder.jpg',
          title: companyName,
          subtitle: 'Easy Bookings · Safe Journeys · Complete Control',
          description: 'Reliable vehicles, transparent pricing and verified payments.',
          cta1_label: 'Browse Fleet',
          cta1_url: '/vehicles',
          cta2_label: 'Get Started',
          cta2_url: '/vehicles',
          overlay: 70,
        },
      ];

  const vehicles = (vehiclesRaw.data ?? []).map(toVehicle);

  const cards = await Promise.all(
    vehicles.map(async (v) => ({
      ...v,
      photo: await getPrimaryPhoto(v.id),
    }))
  );

  return (
    <main>
      <HeroSlider slides={slides} />

      <section className="hero" style={{ padding: '40px 0 56px' }}>
        <div className="shell hero-grid">
          <div>
            <p className="eyebrow">Plan your trip</p>
            <h1>Find the right vehicle for any journey.</h1>
            <p className="lead">Search available cars, pickups and SUVs for your dates. Prices shown are live and verified from our fleet system.</p>
          </div>
          <SearchForm />
        </div>
      </section>

      <section className="section alt">
        <div className="shell">
          <div className="grid" style={{ gridTemplateColumns: 'repeat(3,1fr)' }}>
            <div className="panel">
              <div style={{ width: 40, height: 40, borderRadius: 10, background: '#e7f6f1', color: '#08705f', display: 'grid', placeItems: 'center', marginBottom: 14 }}>
                <Globe className="w-5 h-5" />
              </div>
              <h3>Browse as guest</h3>
              <p className="muted">No account needed — browse vehicles, check availability and prices. <Link href="/vehicles" style={{ color: '#087f70', fontWeight: 700 }}>View vehicles →</Link></p>
            </div>
            <div className="panel">
              <div style={{ width: 40, height: 40, borderRadius: 10, background: '#e7f6f1', color: '#08705f', display: 'grid', placeItems: 'center', marginBottom: 14 }}>
                <User className="w-5 h-5" />
              </div>
              <h3>Client portal</h3>
              <p className="muted">Manage bookings, payments, deposits and documents. <Link href="/login" style={{ color: '#087f70', fontWeight: 700 }}>Sign in →</Link></p>
            </div>
            <div className="panel">
              <div style={{ width: 40, height: 40, borderRadius: 10, background: '#e7f6f1', color: '#08705f', display: 'grid', placeItems: 'center', marginBottom: 14 }}>
                <ShieldCheck className="w-5 h-5" />
              </div>
              <h3>Simple &amp; secure</h3>
              <p className="muted">Verified payments via Paynow, transparent pricing, real photos.</p>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="shell">
          <div className="section-head">
            <h2>Our Fleet</h2>
            <Link href="/vehicles" style={{ color: '#087f70', fontWeight: 700 }}>View all →</Link>
          </div>
          <div className="grid">
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
              />
            ))}
          </div>
        </div>
      </section>
    </main>
  );
}
