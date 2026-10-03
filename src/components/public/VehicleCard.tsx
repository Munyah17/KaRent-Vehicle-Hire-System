import Link from 'next/link';
import { ArrowRight } from 'lucide-react';

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
}) {
  const rate = typeof daily_rate === 'string' ? parseFloat(daily_rate) : daily_rate;
  const badge = status === 'available' ? 'Available' : status === 'on_hire' ? 'On hire' : status;
  const busy = status !== 'available';

  return (
    <article className="vehicle">
      <Link href={`/vehicles/${id}`}>
        <img src={photo} alt={`${make} ${model}`} className="vehicle-image" />
      </Link>
      <div className="vehicle-body">
        <div className="row">
          <h3><Link href={`/vehicles/${id}`}>{make} {model}</Link></h3>
          <span className={busy ? 'badge busy' : 'badge'}>{badge}</span>
        </div>
        <p className="meta">{year || '-'} · {transmission} · {fuel_type} · {seats} seats</p>
        <p className="price">${rate.toFixed(2)}<small> / day</small></p>
        <div className="actions">
          <Link href={`/vehicles/${id}`} className="button" style={{ background: '#087f70', color: 'white', flex: 1 }}>Book now<ArrowRight className="w-4 h-4" style={{ marginLeft: 6 }} /></Link>
        </div>
      </div>
    </article>
  );
}
