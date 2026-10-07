export default function Loading() {
  return (
    <main className="animate-pulse">
      <div className="bg-slate-200" style={{ height: 520 }} />
      <div className="max-w-7xl mx-auto px-6 py-12 space-y-14">
        {[0, 1, 2].map((s) => (
          <section key={s}>
            <div className="h-7 w-48 bg-slate-200 rounded mb-2" />
            <div className="h-4 w-80 bg-slate-200 rounded mb-6" />
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
              {[0, 1, 2, 3].map((i) => (
                <div key={i} className="rounded-xl border border-gray-200 overflow-hidden bg-white">
                  <div className="h-60 bg-slate-200" />
                  <div className="p-4 space-y-3">
                    <div className="h-5 w-2/3 bg-slate-200 rounded" />
                    <div className="h-4 w-full bg-slate-200 rounded" />
                    <div className="h-6 w-1/3 bg-slate-200 rounded" />
                    <div className="flex gap-2 pt-1">
                      <div className="h-9 flex-1 bg-slate-200 rounded-md" />
                      <div className="h-9 flex-1 bg-slate-200 rounded-md" />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </section>
        ))}
      </div>
    </main>
  );
}
