import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { setting } from '@/lib/settings';
import { query, one } from '@/lib/db';
import { getVehicle, getPrimaryPhoto, daysBetween, computeTotal, formatCurrency, Vehicle, VehiclePhoto } from '@/components/public/data';
import SearchForm from '@/components/public/SearchForm';
import { Fuel, Gauge, Users, Calendar, ArrowLeft } from 'lucide-react';

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }): Promise<Metadata> {
  const { id } = await params;
  const vehicle = await getVehicle(Number(id));
  const name = await setting('company_name', 'KaRent');
  return {
    title: vehicle ? `${vehicle.make} ${vehicle.model} · ${name}` : 'Vehicle · ' + name,
  };
}

export default async function VehicleDetailPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ pickup?: string; return?: string }>;
}) {
  const { id } = await params;
  const sp = await searchParams;
  const vehicle = await getVehicle(Number(id));
  if (!vehicle) notFound();

  const [photo, photos] = await Promise.all([
    getPrimaryPhoto(vehicle.id),
    query<VehiclePhoto>('SELECT file_path, is_primary FROM vehicle_photos WHERE vehicle_id = ? AND is_public = 1 ORDER BY is_primary DESC, sort_order ASC', [vehicle.id]),
  ]);

  const days = sp.pickup && sp.return ? daysBetween(sp.pickup, sp.return) : null;
  const total = days !== null ? computeTotal(vehicle, days) : null;

  let available = true;
  if (sp.pickup && sp.return) {
    const overlap = await one<{ c: number }>(
      `SELECT COUNT(*) AS c FROM bookings
       WHERE vehicle_id = ? AND status IN ('confirmed','active','pending')
         AND pickup_at < ? AND return_at > ?`,
      [vehicle.id, `${sp.return} 00:00:00`, `${sp.pickup} 00:00:00`]
    );
    available = (overlap?.c ?? 0) === 0;
  }

  const gallery = photos.length ? photos.map((p) => `/uploads/${p.file_path}`) : [photo];

  return (
    <main>
      <section className="page-head">
        <div className="shell">
          <Link href="/vehicles" style={{ color: '#087f70', fontWeight: 700, fontSize: '0.9rem' }}><ArrowLeft className="w-4 h-4" style={{ display: 'inline', marginRight: 6, verticalAlign: 'text-bottom' }} />Back to fleet</Link>
          <h1 style={{ marginTop: 10 }}>{vehicle.make} {vehicle.model}</h1>
          <p className="muted">{vehicle.year} · {vehicle.colour || '-'} · {vehicle.reg_no}</p>
        </div>
      </section>

      <section className="section" style={{ paddingTop: 36 }}>
        <div className="shell detail">
          <div>
            <div style={{ display: 'grid', gap: 12 }}>
              <img src={gallery[0]} alt={`${vehicle.make} ${vehicle.model}`} className="detail-image" />
              {gallery.length > 1 && (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 10 }}>
                  {gallery.slice(1, 5).map((src, idx) => (
                    <img key={idx} src={src} alt="" style={{ height: 86, objectFit: 'cover', borderRadius: 10, background: '#dce6e4' }} />
                  ))}
                </div>
              )}
            </div>

            <div className="panel" style={{ marginTop: 28 }}>
              <h2>About this vehicle</h2>
              <p className="muted" style={{ marginTop: 12, whiteSpace: 'pre-wrap' }}>{vehicle.description || 'No description available.'}</p>
            </div>
          </div>

          <div>
            <div className="panel">
              <div className="row" style={{ marginBottom: 20 }}>
                <p className="price" style={{ fontSize: '2rem' }}>{formatCurrency(Number(vehicle.daily_rate))}<small> / day</small></p>
                <span className={vehicle.status === 'available' ? 'badge' : 'badge busy'}>{vehicle.status.replace(/_/g, ' ')}</span>
              </div>

              <div className="specs">
                <div className="spec"><Calendar className="w-4 h-4" style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />{vehicle.year || '-'}</div>
                <div className="spec"><Gauge className="w-4 h-4" style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />{vehicle.transmission}</div>
                <div className="spec"><Fuel className="w-4 h-4" style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />{vehicle.fuel_type}</div>
                <div className="spec"><Users className="w-4 h-4" style={{ display: 'inline', marginRight: 6, verticalAlign: 'middle' }} />{vehicle.seats} seats</div>
              </div>

              <div style={{ marginBottom: 18 }}>
                <p className="label">Select dates to check availability</p>
                <SearchForm />
              </div>

              {sp.pickup && sp.return && (
                <>
                  {!available ? (
                    <div className="notice error">Not available for {sp.pickup} to {sp.return}.</div>
                  ) : (
                    <div className="notice">
                      Available for {days} day{days === 1 ? '' : 's'}. Estimated total {formatCurrency(total ?? 0)}.
                    </div>
                  )}
                </>
              )}

              <div style={{ marginTop: 18 }}>
                <p className="label">Deposit</p>
                <p className="price">{formatCurrency(Number(vehicle.deposit))}</p>
              </div>

              <div style={{ marginTop: 24, display: 'grid', gap: 10 }}>
                <Link href={`/contact?subject=${encodeURIComponent(`Booking enquiry: ${vehicle.make} ${vehicle.model} (${vehicle.reg_no})`)}`} className="button" style={{ width: '100%' }}>Enquire now</Link>
                {sp.pickup && sp.return && available && (
                  <Link href={`/login`} className="button secondary" style={{ width: '100%' }}>Sign in to book</Link>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
