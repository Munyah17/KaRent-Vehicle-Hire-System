import Link from 'next/link';
import { Phone, Mail, MapPin } from 'lucide-react';

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
  return (
    <footer className="footer">
      <div className="shell footer-grid">
        <div>
          <h3>{companyName}</h3>
          <p className="muted">Easy bookings, safe journeys, complete control.</p>
        </div>
        <div className="footer-links">
          <h3>Explore</h3>
          <Link href="/vehicles">Vehicles</Link>
          <Link href="/about">About</Link>
          <Link href="/contact">Contact</Link>
          <Link href="/login">Sign In</Link>
        </div>
        <div>
          <h3>Contact</h3>
          <ul className="footer-links">
            {phone && <li><Phone className="w-4 h-4" style={{ display: 'inline', marginRight: 8 }} />{phone}</li>}
            {email && <li><Mail className="w-4 h-4" style={{ display: 'inline', marginRight: 8 }} />{email}</li>}
            {address && <li><MapPin className="w-4 h-4" style={{ display: 'inline', marginRight: 8 }} />{address}</li>}
          </ul>
        </div>
      </div>
      <div className="shell copyright">
        © {new Date().getFullYear()} {companyName}. All rights reserved.
      </div>
    </footer>
  );
}
