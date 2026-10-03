import Link from 'next/link';
import { Car, Menu, X } from 'lucide-react';

export default function Header({ companyName }: { companyName: string }) {
  return (
    <header className="header">
      <div className="shell nav">
        <Link href="/" className="brand">
          <Car className="w-6 h-6" style={{ display: 'inline', verticalAlign: 'middle', marginRight: 8, color: '#087f70' }} />
          {companyName}
        </Link>
        <nav className="navlinks">
          <Link href="/vehicles">Vehicles</Link>
          <Link href="/about">About</Link>
          <Link href="/contact">Contact</Link>
          <Link href="/login" className="button">Sign In</Link>
        </nav>
      </div>
    </header>
  );
}
