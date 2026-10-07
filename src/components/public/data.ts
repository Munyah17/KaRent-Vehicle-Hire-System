import { cache } from 'react';
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
  const map = await getPrimaryPhotos([vehicleId]);
  return map[vehicleId] ?? '/assets/img/car-placeholder.jpg';
}

/** One query for every vehicle's primary photo — avoids N+1 on grids. */
export async function getPrimaryPhotos(vehicleIds: number[]): Promise<Record<number, string>> {
  if (!vehicleIds.length) return {};
  const { data, error } = await supabase
    .from('vehicle_photos')
    .select('vehicle_id, file_path, is_primary, sort_order')
    .in('vehicle_id', vehicleIds)
    .eq('is_public', true)
    .order('is_primary', { ascending: false })
    .order('sort_order', { ascending: true });

  if (error) throw error;
  const map: Record<number, string> = {};
  for (const row of data ?? []) {
    if (!map[row.vehicle_id]) map[row.vehicle_id] = `/uploads/${row.file_path}`;
  }
  return map;
}

export const getVehicle = cache(async (id: number): Promise<Vehicle | null> => {
  const { data, error } = await supabase
    .from('vehicles')
    .select('*')
    .eq('id', id)
    .eq('is_public', true)
    .maybeSingle();

  if (error) throw error;
  if (!data) return null;
  return toVehicle(data as DbVehicle);
});

export async function listVehicles(filters?: { pickup?: string; return?: string }): Promise<Vehicle[]> {
  const { data, error } = await supabase
    .from('vehicles')
    .select('*')
    .eq('is_public', true)
    .in('status', ['available', 'reserved', 'on_hire'])
    .order('is_featured', { ascending: false })
    .order('daily_rate', { ascending: true })
    .limit(200)
    .returns<DbVehicle[]>();

  if (error) throw error;
  const rows = (data ?? []).map(toVehicle);

  if (!filters?.pickup || !filters?.return) return rows;

  const pickup = new Date(filters.pickup);
  const returnAt = new Date(filters.return);
  if (isNaN(pickup.getTime()) || isNaN(returnAt.getTime()) || returnAt <= pickup) return rows;

  const p = formatDate(pickup);
  const r = formatDate(returnAt);
  const ids = rows.map((v) => v.id);
  if (!ids.length) return rows;

  // One query for all overlapping bookings instead of one per vehicle.
  const { data: overlaps, error: overlapError } = await supabase
    .from('bookings')
    .select('vehicle_id')
    .in('vehicle_id', ids)
    .in('status', ['confirmed', 'active', 'pending'])
    .lt('pickup_at', `${r} 00:00:00`)
    .gt('return_at', `${p} 00:00:00`);

  if (overlapError) throw overlapError;
  const busy = new Set((overlaps ?? []).map((b) => b.vehicle_id));
  return rows.filter((v) => !busy.has(v.id));
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
