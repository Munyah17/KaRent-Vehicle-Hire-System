'use client';

import { useActionState } from 'react';
import { UserPlus } from 'lucide-react';
import { registerAction, RegisterState } from '@/app/(public)/register/action';

export default function RegisterForm() {
  const [state, action, pending] = useActionState<RegisterState, FormData>(registerAction, null);

  return (
    <form action={action} className="space-y-4">
      <div className="text-center mb-6">
        <span className="inline-flex w-12 h-12 rounded-xl bg-blue-600 text-white items-center justify-center mb-3">
          <UserPlus className="w-6 h-6" />
        </span>
        <h1 className="text-xl font-semibold text-slate-800">Create your account</h1>
        <p className="text-sm text-slate-500">Book vehicles faster with an account</p>
      </div>

      {state?.error && (
        <div className="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
          {state.error}
        </div>
      )}

      <div>
        <label className="label">Full name</label>
        <input name="name" required className="input" placeholder="Your full name" />
      </div>
      <div>
        <label className="label">Email</label>
        <input type="email" name="email" required className="input" placeholder="you@example.com" />
      </div>
      <div>
        <label className="label">Phone</label>
        <input name="phone" required className="input" placeholder="Your phone number" />
      </div>
      <div>
        <label className="label">Password (min 8 chars)</label>
        <input type="password" name="password" required minLength={8} className="input" />
      </div>
      <div>
        <label className="label">Confirm password</label>
        <input type="password" name="confirm" required className="input" />
      </div>
      <button className="btn-primary w-full justify-center !py-2.5" disabled={pending}>
        Create Account
      </button>
    </form>
  );
}
