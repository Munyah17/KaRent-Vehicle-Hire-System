'use client';

export default function SuperAdminForm({
  action,
  error,
}: {
  action: (formData: FormData) => void;
  error?: string;
}) {
  return (
    <form action={action} className="login-form">
      <div className="form-group">
        <label htmlFor="sa-name">Full name</label>
        <input id="sa-name" className="au-input" type="text" name="name" placeholder="e.g. Admin Name" required autoFocus />
      </div>
      <div className="form-group">
        <label htmlFor="sa-email">Email address</label>
        <input id="sa-email" className="au-input" type="email" name="email" placeholder="admin@company.com" autoComplete="email" required />
      </div>
      <div className="form-group">
        <label htmlFor="sa-password">Password</label>
        <input id="sa-password" className="au-input" type="password" name="password" autoComplete="new-password" required minLength={8} />
      </div>
      <div className="form-group">
        <label htmlFor="sa-confirm">Confirm password</label>
        <input id="sa-confirm" className="au-input" type="password" name="confirm" autoComplete="new-password" required minLength={8} />
      </div>
      <button className="au-btn au-btn--green" type="submit">
        Create Super Admin
      </button>
      <div className="register-link" style={{ marginTop: 14 }}>
        <p>
          <a href="/admin/login">&larr; Staff sign in</a>
        </p>
      </div>
      {error && (
        <div className="alert alert-danger" style={{ fontSize: '.85rem', marginTop: 14 }}>
          {error}
        </div>
      )}
    </form>
  );
}
