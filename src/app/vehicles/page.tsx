import { Metadata } from 'next';
import Link from 'next/link';
import { setting } from '@/lib/settings';
import VehicleCard from '@/components/public/VehicleCard';
import VehicleFilters from '@/components/public/VehicleFilters';
import { getPrimaryPhoto, listVehicles, daysBetween, computeTotal, formatCurrency } from '@/components/public/data';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'KaRent');
  return { title: `Vehicles · ${name}` };
}

export default async function VehiclesPage({
  searchParams,
}: {
  searchParams: Promise<{ pickup?: string; return?: string }>;
}) {
  const sp = await searchParams;
  const vehicles = await listVehicles(sp);
  const cards = await Promise.all(
    vehicles.map(async (v) => {
      const photo = await getPrimaryPhoto(v.id);
      let total: string | null = null;
      if (sp.pickup && sp.return) {
        const days = daysBetween(sp.pickup, sp.return);
        total = formatCurrency(computeTotal(v, days));
      }
      return { ...v, photo, total, days: sp.pickup && sp.return ? daysBetween(sp.pickup, sp.return) : null };
    })
  );

  return (
    <main>
      <section className="page-head">
        <div className="shell">
          <h1>Browse our fleet</h1>
          <p className="muted">Check live availability and pricing for your trip dates.</p>
        </div>
      </section>

      <section className="section">
        <div className="shell">
          <VehicleFilters />

          {sp.pickup && sp.return && (
            <p className="muted" style={{ marginBottom: 18 }}>
              Showing vehicles available from <strong>{sp.pickup}</strong> to <strong>{sp.return}</strong>.
            </p>
          )}

          {cards.length === 0 ? (
            <div className="empty">
              <p>No vehicles available for the selected dates. <Link href="/vehicles" style={{ color: '#087f70' }}>Clear dates</Link></p>
            </div>
          ) : (
            <div className="grid">
              {cards.map((v) => (
                <div key={v.id}>
                  <VehicleCard
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
                  {v.total && (
                    <div style={{ marginTop: 8, padding: '10px 12px', background: '#e7f6f1', borderRadius: 10, color: '#086c5f', fontWeight: 700, fontSize: '0.92rem' }}>
                      Estimated total for {v.days} day{v.days === 1 ? '' : 's'}: {v.total}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
