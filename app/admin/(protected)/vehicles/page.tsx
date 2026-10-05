import { supabase } from '@/lib/supabase';
import { money, Badge } from '@/lib/helpers';

export const metadata = {
  title: 'Vehicles',
};

interface VehicleRow {
  id: number;
  reg_no: string;
  make: string;
  model: string;
  year: number | null;
  colour: string | null;
  transmission: string;
  fuel_type: string;
  seats: number;
  daily_rate: number;
  status: string;
  is_public: boolean;
}

/** Strip characters that would break a PostgREST `or` filter expression. */
function orSafe(value: string): string {
  return value.replace(/[(),\\"]/g, '');
}

export default async function VehiclesPage({
  searchParams,
}: {
  searchParams?: Promise<{ q?: string; status?: string }>;
}) {
  const params = await searchParams;
  const q = (params?.q ?? '').trim();
  const status = (params?.status ?? '').trim();

  let qb = supabase
    .from('vehicles')
    .select(
      'id, reg_no, make, model, year, colour, transmission, fuel_type, seats, daily_rate, status, is_public'
    )
    .order('make')
    .order('model')
    .limit(200);

  if (q) {
    const pat = orSafe(q);
    qb = qb.or(`reg_no.ilike.%${pat}%,make.ilike.%${pat}%,model.ilike.%${pat}%`);
  }
  if (status) {
    qb = qb.eq('status', status);
  }

  const { data, error } = await qb;
  if (error) throw error;
  const rows = (data ?? []) as VehicleRow[];

  const statuses = ['available', 'reserved', 'on_hire', 'maintenance', 'unavailable'];

  return (
    <div className="card !p-0 overflow-x-auto">
      <div className="flex flex-wrap items-center gap-3 px-6 py-4 border-b border-gray-100">
        <form method="get" className="flex flex-wrap items-center gap-3 flex-1">
          <div className="relative">
            <i data-lucide="search" className="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
            <input
              name="q"
              defaultValue={q}
              placeholder="Search vehicles..."
              className="input !pl-9 w-56"
            />
          </div>
          <select name="status" defaultValue={status} className="input w-40">
            <option value="">All Statuses</option>
            {statuses.map((s) => (
              <option key={s} value={s}>
                {s
                  .split('_')
                  .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
                  .join(' ')}
              </option>
            ))}
          </select>
          <button className="btn-secondary" type="submit">
            Filter
          </button>
        </form>
        <a href="#" className="btn-primary">
          <i data-lucide="plus" className="w-4 h-4"></i> Add Vehicle
        </a>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full">
          <thead>
            <tr>
              <th className="th">Reg No</th>
              <th className="th">Make / Model</th>
              <th className="th">Year</th>
              <th className="th">Type</th>
              <th className="th">Status</th>
              <th className="th">Daily Rate</th>
              <th className="th">Visible</th>
              <th className="th"></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((v) => (
              <tr key={v.id} className="table-row">
                <td className="td font-medium text-slate-800">{v.reg_no}</td>
                <td className="td">
                  {v.make} {v.model}
                </td>
                <td className="td">{v.year ?? '—'}</td>
                <td className="td">
                  {v.fuel_type.charAt(0).toUpperCase() + v.fuel_type.slice(1)} &middot;{' '}
                  {v.transmission.charAt(0).toUpperCase() + v.transmission.slice(1)}
                </td>
                <td className="td">
                  <Badge status={v.status} />
                </td>
                <td className="td font-medium">{money(v.daily_rate)}</td>
                <td className="td">
                  <span className={v.is_public ? 'text-green-600' : 'text-gray-400'}>
                    <i
                      data-lucide={v.is_public ? 'eye' : 'eye-off'}
                      className="w-4 h-4"
                    ></i>
                  </span>
                </td>
                <td className="td text-right">
                  <a href="#" className="text-blue-600 hover:underline text-sm">
                    Edit
                  </a>
                </td>
              </tr>
            ))}
            {rows.length === 0 && (
              <tr>
                <td colSpan={8} className="td text-center py-10 text-slate-400">
                  No vehicles found.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-between px-6 py-4">
        <p className="text-sm text-slate-500">Showing {rows.length} vehicles</p>
      </div>
    </div>
  );
}
