import Link from 'next/link';
import StatusBadge from './StatusBadge';

export default function VehicleCard({
  id,
  make,
  model,
  year,
  transmission,
  fuel_type,
  seats,
  daily_rate,
  status,
  photo,
  hires,
  reg_no = '',
}: {
  id: number;
  make: string;
  model: string;
  year: number | null;
  transmission: string;
  fuel_type: string;
  seats: number;
  daily_rate: number | string;
  status: string;
  photo: string;
  hires?: number;
  reg_no?: string;
}) {
  const rate = typeof daily_rate === 'string' ? parseFloat(daily_rate) : daily_rate;
  const badgeStatus = status === 'on_hire' ? 'reserved' : status;
  const vehUrl = `/vehicles/${id}`;
  const subject = `Inquiry: ${make} ${model}${reg_no ? ` (${reg_no})` : ''}`;
  const inquireUrl = `/contact?subject=${encodeURIComponent(subject)}`;

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
      <Link href={vehUrl}>
        <img src={photo} className="w-full h-60 object-cover" alt={`${make} ${model}`} />
      </Link>
      <div className="p-4">
        <div className="flex items-start justify-between">
          <h3 className="font-semibold text-slate-800">
            <Link href={vehUrl} className="hover:text-blue-600">
              {make} {model}
            </Link>
          </h3>
          <StatusBadge status={badgeStatus} />
        </div>
        <p className="text-xs text-slate-500 mt-1">
          {year ?? '-'} · {transmission.charAt(0).toUpperCase() + transmission.slice(1)} ·{' '}
          {fuel_type.charAt(0).toUpperCase() + fuel_type.slice(1)} · {seats} seats
          {hires !== undefined && hires !== null ? (
            <>
              {' '}
              · <span className="text-blue-600 font-medium">{hires} hire{hires === 1 ? '' : 's'}</span>
            </>
          ) : null}
        </p>
        <p className="text-lg font-semibold text-blue-600 mt-2">
          ${rate.toFixed(2)}
          <span className="text-sm font-normal text-slate-400">/day</span>
        </p>
        <div className="flex gap-2 mt-3">
          <Link
            href={inquireUrl}
            className="flex-1 text-center border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-md px-3 py-2 text-xs font-medium"
          >
            Inquire
          </Link>
          <Link
            href={vehUrl}
            className="flex-1 text-center bg-blue-600 hover:bg-blue-700 text-white rounded-md px-3 py-2 text-xs font-medium"
          >
            Book Now
          </Link>
        </div>
      </div>
    </div>
  );
}
