'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';

export type HeroSlide = {
  image: string;
  title: string;
  subtitle?: string | null;
  description?: string | null;
  cta1_label?: string | null;
  cta1_url?: string | null;
  cta2_label?: string | null;
  cta2_url?: string | null;
  overlay: number;
};

export default function HeroSlider({ slides }: { slides: HeroSlide[] }) {
  const [i, setI] = useState(0);
  const [touchStart, setTouchStart] = useState<number | null>(null);

  const go = useCallback(
    (n: number) => {
      setI((n + slides.length) % slides.length);
    },
    [slides.length]
  );

  useEffect(() => {
    if (slides.length < 2) return;
    const timer = setInterval(() => setI((x) => (x + 1) % slides.length), 6000);
    return () => clearInterval(timer);
  }, [slides.length]);

  if (!slides.length) return null;

  return (
    <section className="hero-slider relative overflow-hidden" id="heroSlider" style={{ height: 520 }}>
      {slides.map((s, idx) => (
        <div
          key={idx}
          data-slide={idx}
          className={`hero-slide absolute inset-0 transition-opacity duration-700 ${
            idx === i ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'
          }`}
        >
          <img
            src={s.image}
            alt={s.title}
            className="absolute inset-0 w-full h-full object-cover"
            loading={idx === 0 ? 'eager' : 'lazy'}
            fetchPriority={idx === 0 ? 'high' : 'auto'}
          />
          <div className="absolute inset-0 bg-black" style={{ opacity: s.overlay / 100 }}></div>
          <div className="relative z-10 h-full flex items-center">
            <div className="max-w-7xl mx-auto px-6 w-full">
              <div className="max-w-2xl text-white">
                {s.subtitle ? (
                  <p className="text-sm uppercase tracking-widest text-blue-200 mb-2">{s.subtitle}</p>
                ) : null}
                <h1 className="text-4xl md:text-5xl font-bold mb-3">{s.title}</h1>
                {s.description ? <p className="text-lg text-gray-200 mb-6">{s.description}</p> : null}
                <div className="flex flex-wrap gap-3">
                  {s.cta1_label ? (
                    <Link href={s.cta1_url || '/vehicles'} className="btn-primary !px-6 !py-3">
                      {s.cta1_label}
                    </Link>
                  ) : null}
                  {s.cta2_label ? (
                    <Link
                      href={s.cta2_url || '/vehicles'}
                      className="inline-flex items-center gap-2 border border-white/60 text-white hover:bg-white/10 px-6 py-3 rounded-md text-sm font-medium transition-colors"
                    >
                      {s.cta2_label}
                    </Link>
                  ) : null}
                </div>
              </div>
            </div>
          </div>
        </div>
      ))}

      {slides.length > 1 && (
        <>
          <button
            type="button"
            id="heroPrev"
            aria-label="Previous slide"
            onClick={() => go(i - 1)}
            className="absolute z-20 left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center"
          >
            <i data-lucide="chevron-left" className="w-5 h-5"></i>
          </button>
          <button
            type="button"
            id="heroNext"
            aria-label="Next slide"
            onClick={() => go(i + 1)}
            className="absolute z-20 right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center"
          >
            <i data-lucide="chevron-right" className="w-5 h-5"></i>
          </button>
          <div
            className="absolute z-20 bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5"
            id="heroDots"
          >
            {slides.map((_, idx) => (
              <button
                key={idx}
                type="button"
                data-dot={idx}
                aria-label={`Slide ${idx + 1}`}
                onClick={() => go(idx)}
                className={`w-2.5 h-2.5 rounded-full hover:bg-white transition-colors ${
                  idx === i ? 'bg-white' : 'bg-white/40'
                }`}
              />
            ))}
          </div>
        </>
      )}
      <style>{`
        .hero-slider { height: 520px; }
        @media (max-width: 768px) { .hero-slider { height: 430px; } }
      `}</style>
    </section>
  );
}
