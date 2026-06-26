"use client";

import { useState } from "react";
import { SafeImage } from "./SafeImage";

export type Slide = { url: string | null; alt: string };

export function ImageSlider({
  slides,
  title,
  discountPct,
}: {
  slides: Slide[];
  title: string;
  discountPct?: number;
}) {
  const items = slides.length ? slides : [{ url: null, alt: title }];
  const [index, setIndex] = useState(0);
  const go = (delta: number) =>
    setIndex((prev) => (prev + delta + items.length) % items.length);

  const hasMany = items.length > 1;
  // Facebook-style media: at most 5 thumbnails, the last carrying a "+N" badge.
  const thumbs = items.slice(0, 5);
  const extra = items.length - thumbs.length;

  return (
    <div>
      {/* Big preview — fixed 4:3 (same on mobile + desktop), cover so it fills
          edge-to-edge with no white letterbox gaps. */}
      <div className="relative aspect-[4/3] w-full overflow-hidden rounded-card bg-surface-2">
        <SafeImage
          src={items[index].url}
          alt={items[index].alt}
          className="h-full w-full object-cover"
        />
        {discountPct && discountPct > 0 ? (
          <span className="absolute left-3 top-3 rounded-full bg-red-600 px-2.5 py-1 text-xs font-bold text-white shadow-md">
            {discountPct}% OFF
          </span>
        ) : null}
        {hasMany && (
          <>
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Previous image"
              className="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-2xl text-white backdrop-blur transition hover:bg-black/70"
            >
              ‹
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Next image"
              className="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-2xl text-white backdrop-blur transition hover:bg-black/70"
            >
              ›
            </button>
            <span className="absolute bottom-3 right-3 rounded-full bg-black/55 px-2.5 py-1 text-xs font-medium text-white">
              {index + 1}/{items.length}
            </span>
          </>
        )}
      </div>

      {/* Thumbnails — always shown (>=1 image, incl. the main one) so every
          card keeps the same height. Fixed size, up to 5, with a "+N" badge. */}
      <div className="no-scrollbar mt-2 flex gap-2 overflow-x-auto px-3">
        {thumbs.map((s, idx) => {
          const showBadge = idx === thumbs.length - 1 && extra > 0;
          return (
            <button
              type="button"
              key={idx}
              onClick={() => setIndex(idx)}
              aria-label={`View image ${idx + 1}`}
              aria-current={idx === index}
              className={`relative aspect-square w-16 shrink-0 overflow-hidden rounded-lg border-2 transition ${
                idx === index
                  ? "border-accent"
                  : "border-border opacity-70 hover:opacity-100"
              }`}
            >
              <SafeImage src={s.url} alt={s.alt} className="h-full w-full object-cover" />
              {showBadge && (
                <span className="absolute inset-0 flex items-center justify-center bg-black/55 text-sm font-bold text-white">
                  +{extra}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
}
