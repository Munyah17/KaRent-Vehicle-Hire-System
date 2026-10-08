import { Metadata } from 'next';
import Link from 'next/link';
import { Phone, Mail, MapPin, Clock, Headset, ArrowRight, type LucideIcon } from 'lucide-react';
import { setting } from '@/lib/settings';
import ContactForm from '@/components/public/ContactForm';

export const metadata: Metadata = { title: 'Contact' };

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

  const cleanPhone = phone ? phone.replace(/\s+/g, '') : '';

  const channels: [LucideIcon, string, string, string | null][] = [
    [Phone, 'Call us', phone || '-', cleanPhone ? `tel:${cleanPhone}` : null],
    [Mail, 'Email us', email || '-', email ? `mailto:${email}` : null],
    [MapPin, 'Visit us', address || '-', null],
    [Clock, 'Working hours', 'Mon–Fri 8:00–17:30 · Sat 9:00–13:00 · Sun closed', null],
  ];

  return (
    <main>
      <section className="bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 text-white">
        <div className="max-w-7xl mx-auto px-6 py-16 text-center">
          <p className="text-blue-300 text-sm font-medium tracking-wide uppercase mb-2">We&apos;re here to help</p>
          <h1 className="text-3xl md:text-4xl font-semibold">Get in touch with us</h1>
          <p className="text-blue-200 mt-3 max-w-xl mx-auto">
            Questions about a booking, our fleet, or corporate accounts — send a message and we&apos;ll reply within one
            business day.
          </p>
        </div>
      </section>

      <section className="max-w-6xl mx-auto px-6 -mt-10 pb-16">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="space-y-4">
            {channels.map(([Icon, label, value, link]) => (
              <div key={label} className="card flex items-start gap-4 hover:shadow-md transition-shadow">
                <span className="icon-box bg-blue-600 text-white shadow-sm">
                  <Icon className="w-5 h-5" />
                </span>
                <div className="min-w-0">
                  <p className="font-semibold text-slate-800">{label}</p>
                  {link ? (
                    <a href={link} className="text-sm text-blue-600 hover:underline break-words">
                      {value}
                    </a>
                  ) : (
                    <p className="text-sm text-slate-500">{value}</p>
                  )}
                </div>
              </div>
            ))}
            <div className="rounded-xl bg-blue-50 border border-blue-100 p-5">
              <p className="font-semibold text-slate-800 flex items-center gap-2">
                <Headset className="w-5 h-5 text-blue-600" /> Need an urgent hire?
              </p>
              <p className="text-sm text-slate-600 mt-1">
                Call us directly — same-day pickups are often possible on popular models.
              </p>
              <Link
                href="/vehicles"
                className="inline-flex items-center gap-1.5 text-sm text-blue-600 font-medium mt-3 hover:underline"
              >
                Browse the fleet <ArrowRight className="w-4 h-4" />
              </Link>
            </div>
          </div>

          <div className="card lg:col-span-2 !p-8">
            <h2 className="text-lg font-semibold text-slate-800">Send us a message</h2>
            <p className="text-sm text-slate-500 mb-6">Fill in the form and our team will get back to you shortly.</p>
            <ContactForm initialSubject={subject} />
          </div>
        </div>
      </section>
    </main>
  );
}
