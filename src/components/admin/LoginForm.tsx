'use client';

import { useSearchParams } from 'next/navigation';

export default function LoginForm({ action, from }: { action: (formData: FormData) => void; from?: string }) {
  const search = useSearchParams();
  const error = search.get('error');

  return (
    <form action={action} className="w-full max-w-sm bg-white rounded-lg shadow p-8 border border-slate-200">
      <h1 className="text-2xl font-semibold text-slate-800 mb-1">Admin sign in</h1>
      <p className="text-sm text-slate-500 mb-6">KaRent back office</p>
      {error && (
        <div className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
          Invalid credentials or insufficient privileges.
        </div>
      )}
      <input type="hidden" name="from" value={from ?? '/admin/dashboard'} />
      <label className="block text-sm font-medium text-slate-700 mb-1">Email or username</label>
      <input
        name="identifier"
        type="text"
        required
        autoFocus
        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
      />
      <label className="block text-sm font-medium text-slate-700 mb-1">Password</label>
      <input
        name="password"
        type="password"
        required
        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-6"
      />
      <button
        type="submit"
        className="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        Sign in
      </button>
    </form>
  );
}
