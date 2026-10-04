import { supabase } from '@/lib/supabase';

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

export type VehiclePhoto = {
  id: number;
  vehicle_id: number;
  file_path: string;
  is_primary: number;
};

function toVehicle(row: DbVehicle): Vehicle {
  return {
    ...row,
    is_public: row.is_public ? 1 : 0,
    is_featured: row.is_featured ? 1 : 0,
  };
}

export async function getPrimaryPhoto(vehicleId: number): Promise<string> {
  const { data, error } = await supabase
    .from('vehicle_photos')
    .select('file_path, is_primary')
    .eq('vehicle_id', vehicleId)
    .eq('is_public', true)
    .order('is_primary', { ascending: false })
    .order('sort_order', { ascending: true })
    .limit(1)
    .maybeSingle();

  if (error) throw error;
  if (!data) return '/assets/img/car-placeholder.jpg';
  return `/uploads/${data.file_path}`;
}

export async function getVehicle(id: number): Promise<Vehicle | null> {
  const { data, error } = await supabase
    .from('vehicles')
    .select('*')
    .eq('id', id)
    .eq('is_public', true)
    .maybeSingle();

  if (error) throw error;
  if (!data) return null;
  return toVehicle(data as DbVehicle);
}

export async function listVehicles(filters?: { pickup?: string; return?: string }): Promise<Vehicle[]> {
  const { data, error } = await supabase
    .from('vehicles')
    .select('*')
    .eq('is_public', true)
    .in('status', ['available', 'reserved', 'on_hire'])
    .order('is_featured', { ascending: false })
    .order('daily_rate', { ascending: true })
    .returns<DbVehicle[]>();

  if (error) throw error;
  const rows = (data ?? []).map(toVehicle);

  if (!filters?.pickup || !filters?.return) return rows;

  const pickup = new Date(filters.pickup);
  const returnAt = new Date(filters.return);
  if (isNaN(pickup.getTime()) || isNaN(returnAt.getTime()) || returnAt <= pickup) return rows;

  const p = formatDate(pickup);
  const r = formatDate(returnAt);

  const available: Vehicle[] = [];
  for (const v of rows) {
    const { count, error: countError } = await supabase
      .from('bookings')
      .select('id', { count: 'exact', head: true })
      .eq('vehicle_id', v.id)
      .in('status', ['confirmed', 'active', 'pending'])
      .lt('pickup_at', `${r} 00:00:00`)
      .gt('return_at', `${p} 00:00:00`);

    if (countError) throw countError;
    if ((count ?? 0) === 0) available.push(v);
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
