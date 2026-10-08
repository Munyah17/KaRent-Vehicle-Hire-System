import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import Link from 'next/link';
import { supabase } from '@/lib/supabase';
import { ArrowLeft, CalendarCheck } from 'lucide-react';
import { getSession } from '@/lib/auth';
import {
  getVehicle,
  daysBetween,
  computeTotal,
  formatCurrency,
  VehiclePhoto,
} from '@/components/public/data';
import StatusBadge from '@/components/public/StatusBadge';
import VehicleGallery from '@/components/public/VehicleGallery';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ id: string }>;
}): Promise<Metadata> {
  const { id } = await params;
  const vehicle = await getVehicle(Number(id));
  return {
    title: vehicle ? `${vehicle.make} ${vehicle.model}` : 'Vehicle',
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

  const [session, photos] = await Promise.all([
    getSession(),
    supabase
      .from('vehicle_photos')
      .select('file_path, is_primary')
      .eq('vehicle_id', vehicle.id)
      .eq('is_public', true)
      .order('is_primary', { ascending: false })
      .order('sort_order', { ascending: true })
      .returns<VehiclePhoto[]>(),
  ]);

  if (photos.error) throw photos.error;

  const today = new Date();
  const threeDays = new Date();
  threeDays.setDate(today.getDate() + 3);
  const fmt = (d: Date) => d.toISOString().split('T')[0];

  const pickup = sp.pickup ?? fmt(today);
  const returnAt = sp.return ?? fmt(threeDays);

  const days = daysBetween(pickup, returnAt);
  const quoteBase = computeTotal(vehicle, days);

  let available = true;
  if (sp.pickup && sp.return) {
    const { count, error } = await supabase
      .from('bookings')
      .select('id', { count: 'exact', head: true })
      .eq('vehicle_id', vehicle.id)
      .in('status', ['confirmed', 'active', 'pending'])
      .lt('pickup_at', `${sp.return} 00:00:00`)
      .gt('return_at', `${sp.pickup} 00:00:00`);

    if (error) throw error;
    available = (count ?? 0) === 0;
  }

  const gallery =
    (photos.data ?? []).length
      ? (photos.data ?? []).map((p) => `/uploads/${p.file_path}`)
      : ['/assets/img/car-placeholder.jpg'];

  const bookHref = session?.role === 'CLIENT'
    ? `/client?vehicle=${vehicle.id}&pickup=${encodeURIComponent(pickup)}&return=${encodeURIComponent(returnAt)}`
    : `/login?redirect=/vehicles/${vehicle.id}`;
  const bookLabel = session?.role === 'CLIENT' ? 'Book this vehicle' : 'Book now — sign in to continue';

  return (
    <main>
      <div className="max-w-7xl mx-auto px-6 py-10">
        <Link
          href="/vehicles"
          className="text-sm text-blue-600 hover:underline mb-6 inline-flex items-center gap-1"
        >
          <ArrowLeft className="w-4 h-4" /> Back to vehicles
        </Link>
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-8 mt-4">
          <div className="xl:col-span-2">
            <VehicleGallery gallery={gallery} alt={`${vehicle.make} ${vehicle.model}`} />
            <div className="bg-white rounded-xl border border-gray-200 p-6 mt-6">
              <h2 className="font-semibold text-slate-800 mb-4">About this vehicle</h2>
              <p className="text-sm text-slate-600 leading-relaxed">
                {vehicle.description || 'No description available.'}
              </p>
              <h3 className="font-semibold text-slate-800 mt-6 mb-3">Specifications</h3>
              <dl className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Year</dt>
                  <dd className="font-medium">{vehicle.year ?? '-'}</dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Transmission</dt>
                  <dd className="font-medium">
                    {vehicle.transmission ? vehicle.transmission.charAt(0).toUpperCase() + vehicle.transmission.slice(1) : '-'}
                  </dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Fuel</dt>
                  <dd className="font-medium">
                    {vehicle.fuel_type ? vehicle.fuel_type.charAt(0).toUpperCase() + vehicle.fuel_type.slice(1) : '-'}
                  </dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Engine</dt>
                  <dd className="font-medium">{vehicle.engine_capacity ?? '—'}</dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Seats</dt>
                  <dd className="font-medium">{vehicle.seats}</dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Colour</dt>
                  <dd className="font-medium">{vehicle.colour ?? '—'}</dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Mileage</dt>
                  <dd className="font-medium">{Number(vehicle.mileage).toLocaleString()} km</dd>
                </div>
                <div className="bg-gray-50 rounded-lg p-3">
                  <dt className="text-xs text-slate-400">Registration</dt>
                  <dd className="font-medium">{vehicle.reg_no}</dd>
                </div>
              </dl>
            </div>
          </div>

          <div>
            <div className="bg-white rounded-xl border border-gray-200 p-6 sticky top-24">
              <div className="flex items-start justify-between mb-5">
                <div>
                  <h1 className="text-xl font-semibold text-slate-800">
                    {vehicle.make} {vehicle.model}
                  </h1>
                  <p className="text-sm text-slate-500">{vehicle.year}</p>
                </div>
                <StatusBadge status={vehicle.status === 'on_hire' ? 'reserved' : vehicle.status} />
              </div>
              <div className="space-y-2 text-sm border-y border-gray-100 py-4 mb-4">
                <div className="flex justify-between">
                  <span className="text-slate-500">Daily</span>
                  <span className="font-semibold">{formatCurrency(Number(vehicle.daily_rate))}</span>
                </div>
                {vehicle.weekly_rate ? (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Weekly</span>
                    <span className="font-medium">{formatCurrency(Number(vehicle.weekly_rate))}</span>
                  </div>
                ) : null}
                {vehicle.monthly_rate ? (
                  <div className="flex justify-between">
                    <span className="text-slate-500">Monthly</span>
                    <span className="font-medium">{formatCurrency(Number(vehicle.monthly_rate))}</span>
                  </div>
                ) : null}
                <div className="flex justify-between">
                  <span className="text-slate-500">Security deposit</span>
                  <span className="font-medium">{formatCurrency(Number(vehicle.deposit))}</span>
                </div>
              </div>
              <form method="get" className="space-y-3">
                <input type="hidden" name="id" value={vehicle.id} />
                <div>
                  <label className="label">Pickup</label>
                  <input type="date" name="pickup" defaultValue={pickup} min={fmt(today)} className="input" />
                </div>
                <div>
                  <label className="label">Return</label>
                  <input
                    type="date"
                    name="return"
                    defaultValue={returnAt}
                    min={fmt(today)}
                    className="input"
                  />
                </div>
                <button className="btn-secondary w-full justify-center">Check availability</button>
              </form>
              <div
                className={`mt-4 rounded-lg border px-4 py-3 text-sm ${
                  available
                    ? 'bg-green-50 border-green-200 text-green-800'
                    : 'bg-red-50 border-red-200 text-red-800'
                }`}
              >
                {available ? (
                  <>
                    <strong>Available</strong> · {days} day{days === 1 ? '' : 's'} ≈ {formatCurrency(quoteBase)} rental +{' '}
                    {formatCurrency(Number(vehicle.deposit))} deposit
                  </>
                ) : (
                  <>Not available for the selected dates.</>
                )}
              </div>
              {available && (
                <Link href={bookHref} className="btn-primary w-full justify-center mt-4 !py-3 text-base">
                  <CalendarCheck className="w-5 h-5" />
                  {bookLabel}
                </Link>
              )}
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
