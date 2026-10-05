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
    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow flex flex-col h-full">
      <Link href={vehUrl} className="block h-60 shrink-0">
        <img src={photo} className="w-full h-full object-cover" alt={`${make} ${model}`} loading="lazy" decoding="async" />
      </Link>
      <div className="p-4 flex flex-col flex-1">
        <div className="h-6 flex items-center justify-between gap-2">
          <h3 className="font-semibold text-slate-800 leading-6 truncate">
            <Link href={vehUrl} className="hover:text-blue-600">
              {make} {model}
            </Link>
          </h3>
          <StatusBadge status={badgeStatus} />
        </div>
        <p className="h-5 text-xs text-slate-500 mt-1 leading-5 truncate">
          {year ?? '-'} · {transmission.charAt(0).toUpperCase() + transmission.slice(1)} ·{' '}
          {fuel_type.charAt(0).toUpperCase() + fuel_type.slice(1)} · {seats} seats
          {hires !== undefined && hires !== null ? (
            <>
              {' '}
              · <span className="text-blue-600 font-medium">{hires} hire{hires === 1 ? '' : 's'}</span>
            </>
          ) : null}
        </p>
        <div className="mt-auto">
          <p className="h-7 text-lg font-semibold text-blue-600 mt-2 leading-7 truncate">
            ${rate.toFixed(2)}
            <span className="text-sm font-normal text-slate-400">/day</span>
          </p>
          <div className="flex gap-2 mt-3">
            <Link
              href={inquireUrl}
              className="flex-1 h-9 inline-flex items-center justify-center border border-gray-300 text-slate-700 hover:bg-gray-50 rounded-md px-3 text-xs font-medium"
            >
              Inquire
            </Link>
            <Link
              href={vehUrl}
              className="flex-1 h-9 inline-flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-md px-3 text-xs font-medium"
            >
              Book Now
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
