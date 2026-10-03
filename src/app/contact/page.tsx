import { Metadata } from 'next';
import { setting } from '@/lib/settings';
import ContactForm from '@/components/public/ContactForm';
import { Phone, Mail, MapPin } from 'lucide-react';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'KaRent');
  return { title: `Contact · ${name}` };
}

export default async function ContactPage({
  searchParams,
}: {
  searchParams: Promise<{ subject?: string }>;
}) {
  const { subject = '' } = await searchParams;
  const [phone, email, address] = await Promise.all([
    setting('company_phone', ''),
    setting('company_email', ''),
    setting('company_address', ''),
  ]);

  return (
    <main>
      <section className="page-head">
        <div className="shell">
          <h1>Contact Us</h1>
          <p className="muted">We are here to help with bookings, enquiries and feedback.</p>
        </div>
      </section>

      <section className="section">
        <div className="shell contact-grid">
          <div className="stack">
            <div className="panel" style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
              <span style={{ width: 42, height: 42, borderRadius: 10, background: '#e7f6f1', color: '#08705f', display: 'grid', placeItems: 'center', flexShrink: 0 }}><Phone className="w-5 h-5" /></span>
              <div><p className="label" style={{ margin: 0 }}>Phone</p><p className="muted">{phone || '-'}</p></div>
            </div>
            <div className="panel" style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
              <span style={{ width: 42, height: 42, borderRadius: 10, background: '#e7f6f1', color: '#08705f', display: 'grid', placeItems: 'center', flexShrink: 0 }}><Mail className="w-5 h-5" /></span>
              <div><p className="label" style={{ margin: 0 }}>Email</p><p className="muted">{email || '-'}</p></div>
            </div>
            <div className="panel" style={{ display: 'flex', alignItems: 'flex-start', gap: 14 }}>
              <span style={{ width: 42, height: 42, borderRadius: 10, background: '#e7f6f1', color: '#08705f', display: 'grid', placeItems: 'center', flexShrink: 0 }}><MapPin className="w-5 h-5" /></span>
              <div><p className="label" style={{ margin: 0 }}>Address</p><p className="muted">{address || '-'}</p></div>
            </div>
          </div>
          <div className="panel">
            <h2 style={{ marginTop: 0, marginBottom: 18 }}>Send a message</h2>
            <ContactForm initialSubject={subject} />
          </div>
        </div>
      </section>
    </main>
  );
}
