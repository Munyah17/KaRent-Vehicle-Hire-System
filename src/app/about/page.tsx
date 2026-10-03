import { Metadata } from 'next';
import { setting } from '@/lib/settings';
import { ShieldCheck, CreditCard, Car, Headphones } from 'lucide-react';

export async function generateMetadata(): Promise<Metadata> {
  const name = await setting('company_name', 'KaRent');
  return { title: `About · ${name}` };
}

export default async function AboutPage() {
  const [companyName, address, phone, email] = await Promise.all([
    setting('company_name', 'KaRent'),
    setting('company_address', ''),
    setting('company_phone', ''),
    setting('company_email', ''),
  ]);

  return (
    <main>
      <section className="page-head">
        <div className="shell">
          <h1>About {companyName}</h1>
          <p className="muted">Reliable vehicle hire backed by transparent pricing and modern booking tools.</p>
        </div>
      </section>

      <section className="section">
        <div className="shell">
          <div className="contact-grid">
            <div className="prose">
              <p>{companyName} offers a curated fleet of cars, pickups and SUVs for short trips, business travel and family holidays. Every vehicle is inspected between hires, and all prices are calculated from our live fleet system — what you see is what you pay.</p>
              <p>Our platform gives clients a self-service portal to manage bookings, payments, deposits and documents, while our staff team handles confirmations, checklists and fleet maintenance from a single back office.</p>
              <p>Payments are verified through Paynow and other trusted channels. We keep full audit trails, digital checklists and contract records so every hire is traceable and secure.</p>

              <h2>Contact details</h2>
              <ul className="muted">
                {address && <li>{address}</li>}
                {phone && <li>{phone}</li>}
                {email && <li>{email}</li>}
              </ul>
            </div>

            <div className="grid" style={{ gridTemplateColumns: '1fr 1fr', gap: 16 }}>
              <div className="panel" style={{ textAlign: 'center' }}>
                <Car className="w-8 h-8" style={{ margin: '0 auto 14px', color: '#087f70' }} />
                <h3>Quality fleet</h3>
                <p className="muted">Sedans, SUVs, pickups and city cars for every journey.</p>
              </div>
              <div className="panel" style={{ textAlign: 'center' }}>
                <CreditCard className="w-8 h-8" style={{ margin: '0 auto 14px', color: '#087f70' }} />
                <h3>Verified payments</h3>
                <p className="muted">Paynow, card and wallet options with receipt tracking.</p>
              </div>
              <div className="panel" style={{ textAlign: 'center' }}>
                <ShieldCheck className="w-8 h-8" style={{ margin: '0 auto 14px', color: '#087f70' }} />
                <h3>Full accountability</h3>
                <p className="muted">Digital contracts, checklists and damage records.</p>
              </div>
              <div className="panel" style={{ textAlign: 'center' }}>
                <Headphones className="w-8 h-8" style={{ margin: '0 auto 14px', color: '#087f70' }} />
                <h3>Local support</h3>
                <p className="muted">Reach our team by phone, email or the contact form.</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  );
}
