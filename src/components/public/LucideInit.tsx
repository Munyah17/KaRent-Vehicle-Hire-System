'use client';

import { useEffect } from 'react';

export default function LucideInit() {
  useEffect(() => {
    const win = typeof window !== 'undefined' ? (window as any) : null;
    if (!win?.lucide) return;

    const create = () => {
      try {
        win.lucide.createIcons();
      } catch {
        // ignore
      }
    };

    create();

    const observer = new MutationObserver((mutations) => {
      let hasIcons = false;
      for (const mutation of mutations) {
        if (mutation.type !== 'childList') continue;
        for (const node of mutation.addedNodes) {
          if ((node as HTMLElement).nodeType !== 1) continue;
          const el = node as HTMLElement;
          if (el.dataset?.lucide || el.querySelector?.('i[data-lucide]')) {
            hasIcons = true;
            break;
          }
        }
        if (hasIcons) break;
      }
      if (hasIcons) create();
    });

    observer.observe(document.body, { childList: true, subtree: true });
    return () => observer.disconnect();
  }, []);

  return null;
}
