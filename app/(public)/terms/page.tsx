import { setting } from '@/lib/settings';

export const metadata = { title: 'Terms & Conditions' };

export default async function TermsPage() {
  const company = await setting('company_name', 'Vehicle Hire');
  return (
    <main className="max-w-3xl mx-auto px-6 py-12">
      <h1 className="text-3xl font-bold text-slate-800 mb-2">Terms & Conditions</h1>
      <p className="text-sm text-slate-500 mb-8">Last updated: October 2025</p>
      <div className="space-y-6 text-sm text-slate-600 leading-relaxed">
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">1. Rental agreement</h2>
          <p>
            By booking a vehicle with {company}, you agree to the rental contract issued at
            vehicle handover. The hirer must hold a valid driver's licence, meet our minimum
            age requirements and complete identity verification before a vehicle is released.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">2. Payments & deposits</h2>
          <p>
            Rental charges are payable in advance. A refundable security deposit is held for
            the duration of the hire and released after the vehicle is returned and inspected.
            Any outstanding charges — fuel, tolls, fines or damage — may be deducted.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">3. Vehicle use</h2>
          <p>
            Vehicles may only be driven by the authorised driver(s) named on the contract.
            Off-road use, sub-letting, cross-border travel and carrying goods for reward
            require prior written consent.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">4. Liability</h2>
          <p>
            The hirer is responsible for the vehicle while in their possession and is liable
            for damage, loss or third-party claims up to the applicable excess. Insurance
            cover and exclusions are detailed in the rental contract.
          </p>
        </section>
        <section>
          <h2 className="text-lg font-semibold text-slate-800 mb-2">5. Cancellations</h2>
          <p>
            Bookings may be cancelled up to 48 hours before pickup for a full refund. Later
            cancellations may incur a fee equivalent to one day's rental.
          </p>
        </section>
      </div>
    </main>
  );
}
