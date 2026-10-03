'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { ChevronLeft, ChevronRight } from 'lucide-react';

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

  useEffect(() => {
    if (slides.length < 2) return;
    const t = setInterval(() => setI((x) => (x + 1) % slides.length), 6000);
    return () => clearInterval(t);
  }, [slides.length]);

  const go = (n: number) => setI((n + slides.length) % slides.length);

  if (!slides.length) return null;

  return (
    <section className="hero-slider" style={{ height: 520, position: 'relative', overflow: 'hidden' }}>
      {slides.map((s, idx) => (
        <div
          key={idx}
          style={{
            position: 'absolute',
            inset: 0,
            opacity: idx === i ? 1 : 0,
            zIndex: idx === i ? 10 : 0,
            transition: 'opacity 700ms ease',
            pointerEvents: idx === i ? 'auto' : 'none',
          }}
        >
          <img src={s.image} alt={s.title} style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }} />
          <div style={{ position: 'absolute', inset: 0, background: `rgba(0,0,0,${(s.overlay || 70) / 100})` }} />
          <div className="shell" style={{ position: 'relative', zIndex: 10, height: '100%', display: 'flex', alignItems: 'center' }}>
            <div style={{ maxWidth: 620, color: 'white' }}>
              {s.subtitle && <p className="eyebrow" style={{ color: '#a7e8de' }}>{s.subtitle}</p>}
              <h1 style={{ fontSize: 'clamp(2.2rem,5vw,4rem)', margin: '12px 0 18px', letterSpacing: '-0.04em' }}>{s.title}</h1>
              {s.description && <p style={{ fontSize: '1.1rem', color: '#e2efed', marginBottom: 28 }}>{s.description}</p>}
              <div className="actions">
                {s.cta1_label && <Link href={s.cta1_url || '/vehicles'} className="button" style={{ background: '#087f70', color: 'white' }}>{s.cta1_label}</Link>}
                {s.cta2_label && <Link href={s.cta2_url || '/vehicles'} className="button secondary">{s.cta2_label}</Link>}
              </div>
            </div>
          </div>
        </div>
      ))}
      {slides.length > 1 && (
        <>
          <button type="button" aria-label="Previous" onClick={() => go(i - 1)} style={arrowStyle}><ChevronLeft className="w-5 h-5" /></button>
          <button type="button" aria-label="Next" onClick={() => go(i + 1)} style={{ ...arrowStyle, right: 16, left: 'auto' }}><ChevronRight className="w-5 h-5" /></button>
          <div style={{ position: 'absolute', bottom: 16, left: '50%', transform: 'translateX(-50%)', zIndex: 20, display: 'flex', gap: 8 }}>
            {slides.map((_, idx) => (
              <button key={idx} aria-label={`Slide ${idx + 1}`} onClick={() => go(idx)} style={{ width: 10, height: 10, borderRadius: '50%', border: 0, background: idx === i ? 'white' : 'rgba(255,255,255,0.4)' }} />
            ))}
          </div>
        </>
      )}
    </section>
  );
}

const arrowStyle: React.CSSProperties = {
  position: 'absolute',
  left: 16,
  top: '50%',
  transform: 'translateY(-50%)',
  zIndex: 20,
  width: 44,
  height: 44,
  borderRadius: '50%',
  border: 0,
  background: 'rgba(0,0,0,0.45)',
  color: 'white',
  display: 'grid',
  placeItems: 'center',
  cursor: 'pointer',
};
