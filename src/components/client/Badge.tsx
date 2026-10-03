const TONE: Record<string, string> = {
  pending: 'amber',
  under_review: 'amber',
  partial: 'amber',
  open: 'amber',
  reported: 'amber',
  confirmed: 'blue',
  held: 'blue',
  in_progress: 'blue',
  assessed: 'blue',
  active: 'indigo',
  on_hire: 'indigo',
  charged: 'indigo',
  available: 'green',
  successful: 'green',
  verified: 'green',
  released: 'green',
  resolved: 'green',
  approved: 'green',
  paid: 'green',
  cancelled: 'red',
  overdue: 'red',
  failed: 'red',
  rejected: 'red',
  forfeited: 'red',
  suspended: 'red',
  maintenance: 'orange',
  completed: '',
  closed: '',
  refunded: '',
  unavailable: '',
  generated: 'blue',
  signed: 'green',
  void: 'red',
};

export default function Badge({ status }: { status?: string | null }) {
  const s = (status || 'unknown').toLowerCase();
  const tone = TONE[s] ?? '';
  return <span className={`cp-badge${tone ? ' ' + tone : ''}`}>{s.replace(/_/g, ' ')}</span>;
}
