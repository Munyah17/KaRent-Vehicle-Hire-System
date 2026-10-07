import { setting } from '@/lib/settings';

export const metadata = { title: 'Privacy Policy' };

export default async function PrivacyPage() {
  const [company, email, phone] = await Promise.all([
    setting('company_name', 'Vehicle Hire'),
    setting('company_email', ''),
    setting('company_phone', ''),
  ]);
  return (
    <main className="max-w-3xl mx-auto px-6 py-12">
      <h1 className="text-3xl font-bold text-slate-800 mb-2">Privacy Policy</h1>
      <p className="text-sm text-slate-500 mb-8">Last updated: October 2025</p>
      <div className="space-y-6 text-sm text-slate-600 leading-relaxed">
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">1. What we collect</h2>
          <p>
            {company} collects the information needed to hire out vehicles: your name,
            contact details, driver's licence and identity documents, booking history and
            payment records.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">2. How we use it</h2>
          <p>
            We use your information to process bookings and payments, verify your identity,
            manage the rental lifecycle, communicate about your hire and meet legal and
            insurance obligations. We do not sell personal information.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">3. Storage & security</h2>
          <p>
            Documents and records are stored on secured servers with access limited to
            authorised staff. Payment data is processed by our payment partners and card
            details are never stored on our systems.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">4. Your rights</h2>
          <p>
            You may request a copy, correction or deletion of your personal information,
            subject to records we are required to retain by law. Contact us
            {email ? ` at ${email}` : ''}
            {phone ? ` or ${phone}` : ''} for any privacy requests.
          </p>
        </section>
      </div>
    </main>
  );
}
