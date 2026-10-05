'use client';

import { useActionState } from 'react';
import { loginAction, LoginState } from '@/app/login/action';

export default function LoginForm() {
  const [state, action, pending] = useActionState<LoginState, FormData>(loginAction, null);

  return (
    <form action={action} className="space-y-4">
      <div className="text-center mb-6">
        <span className="inline-flex w-12 h-12 rounded-xl bg-blue-600 text-white items-center justify-center mb-3">
          <i data-lucide="car" className="w-6 h-6"></i>
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
        <input type="email" name="email" required className="input" placeholder="you@example.com" />
      </div>
      <div>
        <label className="label">Password</label>
        <input type="password" name="password" required className="input" />
      </div>
      <button className="btn-primary w-full justify-center !py-2.5" disabled={pending}>
        Sign In
      </button>
    </form>
  );
}
