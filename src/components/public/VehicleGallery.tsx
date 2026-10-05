'use client';

import { useState } from 'react';

export default function VehicleGallery({ gallery, alt }: { gallery: string[]; alt: string }) {
  const [main, setMain] = useState(gallery[0]);

  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <img src={main} className="w-full h-80 object-cover" alt={alt} />
      {gallery.length > 1 && (
        <div className="flex gap-2 p-3">
          {gallery.map((src) => (
            <img
              key={src}
              src={src}
              onClick={() => setMain(src)}
              className="w-20 h-14 object-cover rounded-md border border-gray-200 cursor-pointer hover:border-blue-400"
              alt=""
            />
          ))}
        </div>
      )}
    </div>
  );
}
