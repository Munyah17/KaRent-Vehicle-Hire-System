import Link from 'next/link';
import { query } from '@/lib/db';
import { setting } from '@/lib/settings';
import HeroSlider, { HeroSlide } from '@/components/public/HeroSlider';
import SearchForm from '@/components/public/SearchForm';
import VehicleCard from '@/components/public/VehicleCard';
import { getPrimaryPhoto, Vehicle } from '@/components/public/data';
import { Globe, User, ShieldCheck } from 'lucide-react';

export default async function HomePage() {
  const [companyName, slidesRaw, vehicles] = await Promise.all([
    setting('company_name', 'KaRent'),
    query<{ image: string; title: string; subtitle: string | null; description: string | null; cta1_label: string | null; cta1_url: string | null; cta2_label: string | null; cta2_url: string | null; overlay: number }>(
      'SELECT image, title, subtitle, description, cta1_label, cta1_url, cta2_label, cta2_url, overlay FROM hero_slides WHERE is_active = 1 ORDER BY sort_order, id LIMIT 20'
    ),
    query<Vehicle>("SELECT * FROM vehicles WHERE is_public = 1 AND status NOT IN ('maintenance','unavailable') ORDER BY is_featured DESC, daily_rate LIMIT 6"),
  ]);

  const slides: HeroSlide[] = slidesRaw.length
    ? slidesRaw.map((s) => ({ ...s, image: `/uploads/${s.image}` }))
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
