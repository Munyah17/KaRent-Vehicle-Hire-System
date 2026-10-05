import Link from 'next/link';

export default function Footer({
  companyName,
  phone,
  email,
  address,
}: {
  companyName: string;
  phone: string;
  email: string;
  address: string;
}) {
  const year = new Date().getFullYear();

  return (
    <footer className="bg-[#1e3a8a] dark-footer text-blue-200 mt-16">
      <div className="max-w-7xl mx-auto px-6 py-8 grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8 text-sm">
        <div>
          <div className="text-white font-semibold mb-3">{companyName}</div>
          <p className="text-blue-300">Reliable vehicle hire — easy bookings, safe journeys, complete control.</p>
        </div>
        <div>
          <p className="text-white font-medium mb-3">Company</p>
          <ul className="space-y-2">
            <li>
              <Link href="/about" className="hover:text-white">
                About us
              </Link>
            </li>
            <li>
              <Link href="/contact" className="hover:text-white">
                Contact
              </Link>
            </li>
          </ul>
        </div>
        <div>
          <p className="text-white font-medium mb-3">Legal</p>
          <ul className="space-y-2">
            <li>
              <Link href="/terms" className="hover:text-white">
                Terms &amp; Conditions
              </Link>
            </li>
            <li>
              <Link href="/privacy" className="hover:text-white">
                Privacy Policy
              </Link>
            </li>
          </ul>
        </div>
        <div>
          <p className="text-white font-medium mb-3">Contact</p>
          <ul className="space-y-2">
            {phone && <li>{phone}</li>}
            {email && <li>{email}</li>}
            {address && <li>{address}</li>}
          </ul>
        </div>
      </div>
      <div className="border-t border-blue-800 py-4 text-center text-xs text-blue-300">
        &copy; {year} {companyName}. All rights reserved.
        <span className="block sm:inline sm:ml-1 text-blue-400">
          Developed &amp; Powered by <strong className="text-blue-200 font-semibold">Global Space Web</strong>
        </span>
      </div>
    </footer>
  );
}
