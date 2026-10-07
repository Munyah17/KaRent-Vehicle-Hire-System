'use client';

export default function Error({ reset }: { error: Error; reset: () => void }) {
  return (
    <div className="min-h-[60vh] flex items-center justify-center px-6">
      <div className="max-w-md w-full text-center">
        <h1 className="text-2xl font-semibold text-slate-800 mb-2">Something went wrong</h1>
        <p className="text-sm text-slate-500 mb-6">
          We could not load this page right now. Please try again in a moment.
        </p>
        <div className="flex items-center justify-center gap-3">
          <button
            onClick={reset}
            className="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md font-medium"
          >
            Try again
          </button>
          <a
            href="/"
            className="border border-slate-300 text-slate-700 hover:bg-gray-50 px-5 py-2 rounded-md font-medium"
          >
            Go home
          </a>
        </div>
      </div>
    </div>
  );
}
