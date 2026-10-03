'use client';

export default function PrintButton() {
  return (
    <button type="button" className="button noprint" onClick={() => window.print()}>
      Print / Save as PDF
    </button>
  );
}
