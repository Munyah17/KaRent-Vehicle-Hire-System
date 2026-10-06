'use client';

import { useRef } from 'react';
import { useSearchParams } from 'next/navigation';

const DEMO_STAFF = [
  { label: 'Super Admin', email: 'admin@demo.test', password: 'Admin@123' },
  { label: 'Staff', email: 'staff@demo.test', password: 'Staff@123' },
];

export default function LoginForm({
  action,
  from,
}: {
  action: (formData: FormData) => void;
  from?: string;
}) {
  const search = useSearchParams();
  const error = search.get('error');
  const idRef = useRef<HTMLInputElement>(null);
  const passRef = useRef<HTMLInputElement>(null);

  return (
    <form action={action} className="login-form">
      <input type="hidden" name="from" value={from ?? '/admin/dashboard'} />
      <div className="form-group">
        <label htmlFor="email">Email address</label>
        <input
          id="email"
          ref={idRef}
          className="au-input"
          type="email"
          name="identifier"
          placeholder="staff@company.com"
          autoComplete="email"
          required
          autoFocus
        />
      </div>
      <div className="form-group">
        <label htmlFor="password">Password</label>
        <input
          id="password"
          ref={passRef}
          className="au-input"
          type="password"
          name="password"
          autoComplete="current-password"
          required
        />
      </div>
      <button className="au-btn au-btn--green" type="submit">
        Sign in
      </button>
      <div style={{ marginTop: 14, display: 'flex', flexWrap: 'wrap', gap: 8 }}>
        {DEMO_STAFF.map((d) => (
          <button
            key={d.email}
            type="button"
            onClick={() => {
              if (idRef.current) idRef.current.value = d.email;
              if (passRef.current) passRef.current.value = d.password;
            }}
            className="au-btn au-btn--outline"
            style={{ flex: 1, minWidth: 120 }}
            title={`${d.email} / ${d.password}`}
          >
            Demo: {d.label}
          </button>
        ))}
      </div>
      <div className="register-link" style={{ marginTop: 14 }}>
        <p>
          <a href="/">&larr; Back to the website</a>
        </p>
      </div>

      {error && (
        <div className="alert alert-danger" style={{ fontSize: '.85rem', marginTop: 14 }}>
          Invalid credentials or insufficient privileges.
        </div>
      )}
    </form>
  );
}
