import { Metadata } from 'next';
import Link from 'next/link';
import {
  Wallet, BadgeCheck, Mountain, Sparkles, Truck, Wrench,
  ShieldCheck, Banknote, Headphones, CreditCard, FileCheck, MapPin,
  type LucideIcon,
} from 'lucide-react';
import { setting } from '@/lib/settings';

export const metadata: Metadata = { title: 'About' };

export default async function AboutPage() {
  const companyName = await setting('company_name', 'Vehicle Hire');

  return (
    <main>
      <section className="bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 text-white">
        <div className="max-w-7xl mx-auto px-6 py-20 text-center">
          <p className="text-blue-300 text-sm font-medium tracking-wide uppercase mb-3">
            About {companyName}
          </p>
          <h1 className="text-3xl md:text-4xl font-semibold">Reliable vehicle hire, made simple</h1>
          <p className="text-blue-200 mt-4 max-w-2xl mx-auto">
            Renting a car should be as simple as booking a seat. We run an inspected, diverse fleet with
            transparent pricing and real human support — from first click to key handover.
          </p>
          <div className="flex flex-wrap justify-center gap-3 mt-8">
            <Link href="/vehicles" className="btn-primary !px-6">
              Browse the fleet
            </Link>
            <Link
              href="/contact"
              className="border border-blue-300/40 text-blue-100 hover:bg-white/10 rounded-md px-6 py-2.5 text-sm font-medium"
            >
              Talk to us
            </Link>
          </div>
        </div>
      </section>

      <section className="max-w-5xl mx-auto px-6 py-14">
        <div className="grid grid-cols-1 md:grid-cols-5 gap-10 items-start">
          <div className="md:col-span-2">
            <h2 className="text-2xl font-semibold text-slate-800">Who we are</h2>
            <p className="text-sm text-blue-600 font-medium mt-1">Your local mobility partner</p>
          </div>
          <div className="md:col-span-3 text-slate-600 leading-relaxed space-y-4">
            <p>
              {companyName} operates a diverse, fully inspected fleet — from economical city cars and family
              sedans to 4x4 SUVs, double-cab pickups and premium executive vehicles. Whether you need a runabout
              for a day, a workhorse for a month, or a people-mover for a team, we have the vehicle and the
              paperwork sorted.
            </p>
            <p>
              Every hire is documented end-to-end: contract, inspection checklist, photos of all angles, receipts
              and deposit tracking — so you always know exactly where you stand.
            </p>
          </div>
        </div>
      </section>

      <section className="bg-gray-50 border-y border-gray-100">
        <div className="max-w-6xl mx-auto px-6 py-14">
          <div className="text-center mb-10">
            <h2 className="text-2xl font-semibold text-slate-800">Our fleet</h2>
            <p className="text-sm text-slate-500 mt-2">Five categories, one standard: clean, inspected, ready to go.</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {([
              [Wallet, 'Budget', 'Cheapest daily rates — hatchbacks and compact city cars.'],
              [BadgeCheck, 'Sedans', 'Comfortable saloons for business travel and family trips.'],
              [Mountain, 'SUVs & 4x4s', 'Ground clearance and space for rough roads and long journeys.'],
              [Sparkles, 'Premium', 'Executive and luxury models for occasions that matter.'],
              [Truck, 'Trucks & Pickups', 'Double cabs and load movers for work crews and sites.'],
              [Wrench, 'Utility', 'Work vehicles for field, farm and site operations.'],
            ] as [LucideIcon, string, string][]).map(([Icon, t, d]) => (
              <div
                key={t}
                className="bg-white border border-gray-200 rounded-xl p-5 flex gap-4 hover:shadow-md transition-shadow"
              >
                <span className="icon-box bg-blue-50 text-blue-600 shrink-0">
                  <Icon className="w-5 h-5" />
                </span>
                <div>
                  <p className="font-semibold text-slate-800 text-sm">{t}</p>
                  <p className="text-xs text-slate-500 mt-0.5">{d}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="max-w-6xl mx-auto px-6 py-14">
        <div className="text-center mb-10">
          <h2 className="text-2xl font-semibold text-slate-800">How hiring works</h2>
          <p className="text-sm text-slate-500 mt-2">Four steps from browsing to driving away.</p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          {[
            ['1', 'Book online', 'Pick a vehicle and dates, submit your request — no account required.'],
            ['2', 'Get verified', 'We confirm your licence and ID, agree the deposit and issue the contract.'],
            ['3', 'Collect', 'Handover inspection together — condition, fuel and mileage recorded.'],
            ['4', 'Return', 'Return inspection closes the hire and releases your deposit.'],
          ].map(([n, t, d]) => (
            <div key={n} className="card text-center !p-6">
              <span className="inline-flex w-10 h-10 rounded-full bg-blue-600 text-white items-center justify-center font-semibold mb-3">
                {n}
              </span>
              <p className="font-semibold text-slate-800">{t}</p>
              <p className="text-xs text-slate-500 mt-1.5">{d}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="bg-gray-50 border-y border-gray-100">
        <div className="max-w-6xl mx-auto px-6 py-14">
          <div className="text-center mb-10">
            <h2 className="text-2xl font-semibold text-slate-800">Why customers choose us</h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
            {([
              [ShieldCheck, 'Safe journeys', 'Regular maintenance plus documented pre-hire inspections with photo records on every vehicle.'],
              [Banknote, 'Transparent pricing', 'Clear daily, weekly and monthly rates — the quote you see is the quote you pay. No hidden fees.'],
              [Headphones, 'Real support', 'Booking help, extensions, replacements and roadside queries — before, during and after your rental.'],
              [CreditCard, 'Flexible payments', 'Pay online via Paynow, top up your wallet, or settle at the office — deposits tracked and refunded.'],
              [FileCheck, 'Proper paperwork', 'Every hire gets a contract, checklist and receipts — all accessible in your client account.'],
              [MapPin, 'Local expertise', 'We know the roads you drive — city commutes, cross-border trips, gravel routes and beyond.'],
            ] as [LucideIcon, string, string][]).map(([Icon, t, d]) => (
              <div key={t} className="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <Icon className="w-7 h-7 text-blue-600 mb-3" />
                <p className="font-semibold text-slate-800">{t}</p>
                <p className="text-sm text-slate-500 mt-1.5">{d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="max-w-5xl mx-auto px-6 py-14">
        <div className="rounded-2xl bg-gradient-to-br from-blue-900 to-indigo-900 p-10 text-center text-white">
          <h2 className="text-2xl font-semibold mb-2">Ready to get moving?</h2>
          <p className="text-blue-200 text-sm mb-6">
            Browse the fleet and book in minutes — no account needed. Create a free account to manage hires,
            payments and documents in one place.
          </p>
          <div className="flex flex-wrap gap-3 justify-center">
            <Link href="/vehicles" className="bg-white text-blue-900 hover:bg-blue-50 rounded-md px-6 py-2.5 text-sm font-semibold">
              Browse vehicles
            </Link>
            <Link
              href="/register"
              className="border border-white/40 text-white hover:bg-white/10 rounded-md px-6 py-2.5 text-sm font-medium"
            >
              Create account
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
