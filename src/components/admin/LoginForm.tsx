'use client';

import { useSearchParams } from 'next/navigation';

export default function LoginForm({
  action,
  from,
}: {
  action: (formData: FormData) => void;
  from?: string;
}) {
  const search = useSearchParams();
  const error = search.get('error');

  return (
    <form action={action} className="login-form">
      <input type="hidden" name="from" value={from ?? '/admin/dashboard'} />
      <div className="form-group">
        <label htmlFor="email">Email address</label>
        <input
          id="email"
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
