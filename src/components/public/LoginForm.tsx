'use client';

import { useActionState } from 'react';
import { Car } from 'lucide-react';
import { loginAction, LoginState } from '@/app/login/action';

export default function LoginForm() {
  const [state, action, pending] = useActionState<LoginState, FormData>(loginAction, null);

  return (
    <form action={action} className="stack" style={{ gap: 16 }}>
      <div style={{ textAlign: 'center', marginBottom: 8 }}>
        <div style={{ width: 52, height: 52, borderRadius: 14, background: '#087f70', color: 'white', display: 'grid', placeItems: 'center', margin: '0 auto 14px' }}>
          <Car className="w-7 h-7" />
        </div>
        <h1 style={{ margin: 0, fontSize: '1.5rem' }}>Welcome back</h1>
        <p className="muted">Sign in to your account</p>
      </div>

      {state?.error && <div className="notice error">{state.error}</div>}

      <div>
        <label className="label">Email</label>
        <input name="email" type="email" placeholder="you@example.com" required className="input" />
      </div>
      <div>
        <label className="label">Password</label>
        <input name="password" type="password" required className="input" />
      </div>
      <button className="button" disabled={pending} style={{ width: '100%' }}>Sign In</button>
    </form>
  );
}
