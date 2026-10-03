import { query, one } from '@/lib/db';

export type Vehicle = {
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
  is_public: number;
  is_featured: number;
  created_at: string;
};

export type VehiclePhoto = {
  id: number;
  vehicle_id: number;
  file_path: string;
  is_primary: number;
};

export async function getPrimaryPhoto(vehicleId: number): Promise<string> {
  const row = await one<VehiclePhoto>(
    'SELECT file_path, is_primary FROM vehicle_photos WHERE vehicle_id = ? AND is_public = 1 ORDER BY is_primary DESC, sort_order ASC LIMIT 1',
    [vehicleId]
  );
  if (!row) return '/assets/img/car-placeholder.jpg';
  return `/uploads/${row.file_path}`;
}

export async function getVehicle(id: number): Promise<Vehicle | null> {
  return one<Vehicle>('SELECT * FROM vehicles WHERE id = ? AND is_public = 1', [id]);
}

export async function listVehicles(filters?: { pickup?: string; return?: string }): Promise<Vehicle[]> {
  const rows = await query<Vehicle>(
    "SELECT * FROM vehicles WHERE is_public = 1 AND status NOT IN ('maintenance','unavailable') ORDER BY is_featured DESC, daily_rate"
  );
  if (!filters?.pickup || !filters?.return) return rows;

  const pickup = new Date(filters.pickup);
  const returnAt = new Date(filters.return);
  if (isNaN(pickup.getTime()) || isNaN(returnAt.getTime()) || returnAt <= pickup) return rows;

  const p = formatDate(pickup);
  const r = formatDate(returnAt);

  const available: Vehicle[] = [];
  for (const v of rows) {
    const overlap = await one<{ c: number }>(
      `SELECT COUNT(*) AS c FROM bookings
       WHERE vehicle_id = ? AND status IN ('confirmed','active','pending')
         AND pickup_at < ? AND return_at > ?`,
      [v.id, `${r} 00:00:00`, `${p} 00:00:00`]
    );
    if ((overlap?.c ?? 0) === 0) available.push(v);
  }
  return available;
}

export function formatCurrency(amount: number): string {
  return `$${amount.toFixed(2)}`;
}

export function daysBetween(start: string, end: string): number {
  const s = new Date(start);
  const e = new Date(end);
  const ms = e.getTime() - s.getTime();
  return Math.max(1, Math.ceil(ms / (1000 * 60 * 60 * 24)));
}

export function computeTotal(vehicle: Pick<Vehicle, 'daily_rate' | 'weekly_rate' | 'monthly_rate'>, days: number): number {
  const d = Number(vehicle.daily_rate) || 0;
  const w = vehicle.weekly_rate ? Number(vehicle.weekly_rate) : null;
  const m = vehicle.monthly_rate ? Number(vehicle.monthly_rate) : null;

  if (m !== null && days >= 28) {
    const months = Math.floor(days / 28);
    const remainder = days % 28;
    return months * m + remainder * d;
  }
  if (w !== null && days >= 7) {
    const weeks = Math.floor(days / 7);
    const remainder = days % 7;
    return weeks * w + remainder * d;
  }
  return days * d;
}

function formatDate(d: Date): string {
  return d.toISOString().split('T')[0];
}
