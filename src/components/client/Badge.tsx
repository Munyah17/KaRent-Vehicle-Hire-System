/** Replicates PHP status_badge() — same status→classes map and markup. */
const MAP: Record<string, string> = {
  available: 'bg-green-100 text-green-800',
  reserved: 'bg-amber-100 text-amber-800',
  on_hire: 'bg-blue-100 text-blue-800',
  maintenance: 'bg-red-100 text-red-800',
  unavailable: 'bg-gray-200 text-gray-700',
  pending: 'bg-amber-100 text-amber-800',
  confirmed: 'bg-blue-100 text-blue-800',
  active: 'bg-green-100 text-green-800',
  completed: 'bg-gray-200 text-gray-700',
  cancelled: 'bg-red-100 text-red-800',
  overdue: 'bg-red-100 text-red-800',
  paid: 'bg-green-100 text-green-800',
  successful: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-800',
  refunded: 'bg-purple-100 text-purple-800',
  verified: 'bg-green-100 text-green-800',
  under_review: 'bg-amber-100 text-amber-800',
  rejected: 'bg-red-100 text-red-800',
  approved: 'bg-green-100 text-green-800',
  partial: 'bg-amber-100 text-amber-800',
  held: 'bg-blue-100 text-blue-800',
  released: 'bg-gray-200 text-gray-700',
};

const ucwords = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export default function Badge({ status }: { status?: string | null }) {
  const s = (status || 'unknown').toLowerCase();
  const cls = MAP[s] ?? 'bg-gray-200 text-gray-700';
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${cls}`}>
      {ucwords(s)}
    </span>
  );
}
