'use client';

import { useRef, useActionState } from 'react';
import { Car } from 'lucide-react';
import { loginAction, LoginState } from '@/app/(public)/login/action';

const DEMO = { email: 'john@demo.test', password: 'Client@123' };

export default function LoginForm() {
  const [state, action, pending] = useActionState<LoginState, FormData>(loginAction, null);
  const emailRef = useRef<HTMLInputElement>(null);
  const passRef = useRef<HTMLInputElement>(null);

  return (
    <form action={action} className="space-y-4">
      <div className="text-center mb-6">
        <span className="inline-flex w-12 h-12 rounded-xl bg-blue-600 text-white items-center justify-center mb-3">
          <Car className="w-6 h-6" />
        </span>
        <h1 className="text-xl font-semibold text-slate-800">Welcome back</h1>
        <p className="text-sm text-slate-500">Sign in to your account</p>
      </div>
      {state?.error && (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}
      <div>
        <label className="label">Email</label>
        <input ref={emailRef} type="email" name="email" required className="input" placeholder="you@example.com" />
      </div>
      <div>
        <label className="label">Password</label>
        <input ref={passRef} type="password" name="password" required className="input" />
      </div>
      <button className="btn-primary w-full justify-center !py-2.5" disabled={pending}>
        Sign In
      </button>
      <div className="rounded-lg bg-blue-50 border border-blue-100 p-3 text-xs text-blue-800">
        <div className="flex items-center justify-between gap-2">
          <span><strong>Demo client:</strong> {DEMO.email} / {DEMO.password}</span>
          <button
            type="button"
            onClick={() => {
              if (emailRef.current) emailRef.current.value = DEMO.email;
              if (passRef.current) passRef.current.value = DEMO.password;
            }}
            className="shrink-0 bg-blue-600 hover:bg-blue-700 text-white rounded px-2.5 py-1 font-medium"
          >
            Fill
          </button>
        </div>
      </div>
    </form>
  );
}
